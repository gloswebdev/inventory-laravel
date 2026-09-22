#!/usr/bin/env python3
"""
================================================================================
 InvoFlow Sync Agent
--------------------------------------------------------------------------------
 One always-on agent that pulls sales data out of the local ERP (MS SQL) and
 pushes the SAME rows to every configured target -- the live cloud site and the
 local XAMPP MySQL used for testing.

 Why it replaces the older scripts:
   * ONE query definition. The previous three agents each used a different
     query (different series lists, different tax columns, different sign
     rules) and all wrote to the same table, so they kept overwriting each
     other with differently-shaped data.
   * Watermark on Sl_Head.LTZ_Entry_Date, not vouch_date. A bill entered today
     with last month's date is now picked up; before, it never was.
   * Idempotent upsert keyed on (financial_year, Sl_Txn.code). No more
     "delete a date range then insert", which lost data if a batch failed.
   * Deletes and edits are caught by a reconcile pass that compares day-wise
     count + net totals against each target and repairs only the days that
     differ.

 Usage (see agent.bat for friendlier wrappers):
   python invoflow_agent.py service                  # always-on, follows schedule
   python invoflow_agent.py sync                     # manual incremental, now
   python invoflow_agent.py sync --full              # re-pull configured years
   python invoflow_agent.py sync --from 2026-04-01 --to 2026-08-22
   python invoflow_agent.py bootstrap                # first run / rebuild target
   python invoflow_agent.py reconcile                # verify + repair
   python invoflow_agent.py status                   # what is where
   python invoflow_agent.py test                     # connectivity check

 Flags usable with any command:
   --target cloud|local|all      override configured targets for this run
   --year 20262027               limit to one financial year
   --config path/to.json         alternate config file
================================================================================
"""

from __future__ import annotations

import argparse
import datetime as dt
import decimal
import json
import os
import sqlite3
import sys
import time
import traceback
from typing import Any, Callable, Dict, Iterable, List, Optional, Sequence, Tuple

if hasattr(sys.stdout, "reconfigure"):
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

try:
    import pyodbc
except ImportError:
    sys.exit("[!] pyodbc missing. Install with:  pip install pyodbc")

try:
    import requests
except ImportError:
    sys.exit("[!] requests missing. Install with:  pip install requests")

try:
    import mysql.connector
except ImportError:
    mysql = None  # only needed when the local target is enabled


AGENT_NAME = "invoflow-sync-agent"
AGENT_VERSION = "3.0.0"

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DEFAULT_CONFIG = os.path.join(BASE_DIR, "agent_config.json")
STATE_DB = os.path.join(BASE_DIR, "agent_state.sqlite")
LOG_DIR = os.path.join(BASE_DIR, "logs")


# ============================================================================ #
#  Config                                                                      #
# ============================================================================ #

CONFIG_TEMPLATE: Dict[str, Any] = {
    "source": {
        "server": "100.108.74.58",
        "database": "LOGICDBSY",
        "username": "sa",
        "password": "",
        "driver": "{ODBC Driver 17 for SQL Server}",
        "use_windows_auth": False,
        "login_timeout_seconds": 20,
        "query_timeout_seconds": 600,
    },
    "schedule": {
        # "daily"    -> run once a day at daily_at
        # "interval" -> run every interval_minutes
        # "off"      -> service idles; only manual runs happen
        "mode": "daily",
        "daily_at": "02:00",
        "interval_minutes": 1440,
        "run_on_startup": False,
        "catch_up_if_missed": True,
        "retry_after_minutes": 30,
        "max_retries_per_window": 4,
    },
    "sync": {
        # "auto" resolves to the financial year we are in right now.
        "years": ["auto"],
        "series": [],                    # [] = every series; else a whitelist
        "safety_overlap_minutes": 180,   # re-read this far behind the watermark
        "batch_size": 1000,
        "reconcile": {
            "enabled": True,
            "days": 45,                  # how far back to verify each run
            "tolerance": 0.01,
        },
    },
    "targets": {
        "local": {
            "enabled": True,
            "type": "mysql",
            "host": "127.0.0.1",
            "port": 3306,
            "database": "inventory_laravel_db",
            "username": "root",
            "password": "",
        },
        "cloud": {
            "enabled": True,
            "type": "http",
            "base_url": "https://invoflow.gloswebdev.in",
            "token": "invoflow_mssql_sync_secret_2026",
            "timeout_seconds": 120,
            "verify_ssl": False,
            "report_health": True,
        },
    },
    "logging": {
        "keep_days": 30,
    },
}


def deep_merge(base: Dict[str, Any], override: Dict[str, Any]) -> Dict[str, Any]:
    out = dict(base)
    for key, val in (override or {}).items():
        if isinstance(val, dict) and isinstance(out.get(key), dict):
            out[key] = deep_merge(out[key], val)
        else:
            out[key] = val
    return out


def load_config(path: str) -> Dict[str, Any]:
    if not os.path.exists(path):
        seed = json.loads(json.dumps(CONFIG_TEMPLATE))
        legacy = os.path.join(BASE_DIR, "bridge_config.json")
        if os.path.exists(legacy):
            # Carry over the credentials the old bridge was already using.
            with open(legacy, "r", encoding="utf-8") as fh:
                old = json.load(fh)
            old_mssql = old.get("mssql", {})
            for key in ("server", "database", "username", "password", "driver", "use_windows_auth"):
                if key in old_mssql:
                    seed["source"][key] = old_mssql[key]
            old_cloud = old.get("cloud_api", {})
            if old_cloud.get("base_url"):
                seed["targets"]["cloud"]["base_url"] = old_cloud["base_url"].split("/api/")[0]

        with open(path, "w", encoding="utf-8") as fh:
            json.dump(seed, fh, indent=4)
        print(f"[i] Created default config at {path} -- review it before the first real run.")
        return seed

    with open(path, "r", encoding="utf-8") as fh:
        return deep_merge(CONFIG_TEMPLATE, json.load(fh))


# ============================================================================ #
#  Logging                                                                     #
# ============================================================================ #

class Logger:
    def __init__(self, keep_days: int = 30):
        os.makedirs(LOG_DIR, exist_ok=True)
        self.path = os.path.join(LOG_DIR, f"agent-{dt.date.today():%Y-%m-%d}.log")
        self._prune(keep_days)

    def _prune(self, keep_days: int) -> None:
        cutoff = dt.date.today() - dt.timedelta(days=max(1, keep_days))
        for name in os.listdir(LOG_DIR):
            if not name.startswith("agent-") or not name.endswith(".log"):
                continue
            try:
                stamp = dt.datetime.strptime(name[6:-4], "%Y-%m-%d").date()
            except ValueError:
                continue
            if stamp < cutoff:
                try:
                    os.remove(os.path.join(LOG_DIR, name))
                except OSError:
                    pass

    def __call__(self, message: str, level: str = "INFO") -> None:
        line = f"[{dt.datetime.now():%Y-%m-%d %H:%M:%S}] [{level}] {message}"
        print(line, flush=True)
        try:
            with open(self.path, "a", encoding="utf-8") as fh:
                fh.write(line + "\n")
        except OSError:
            pass


log = Logger()


# ============================================================================ #
#  Persistent state                                                            #
# ============================================================================ #

class State:
    """Watermarks and run history, kept next to the agent so a target being
    unreachable never loses our place."""

    def __init__(self, path: str = STATE_DB):
        self.conn = sqlite3.connect(path)
        self.conn.execute(
            "CREATE TABLE IF NOT EXISTS watermarks ("
            " target TEXT NOT NULL, financial_year TEXT NOT NULL,"
            " value TEXT, updated_at TEXT,"
            " PRIMARY KEY (target, financial_year))"
        )
        self.conn.execute(
            "CREATE TABLE IF NOT EXISTS runs ("
            " id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, target TEXT,"
            " started_at TEXT, finished_at TEXT, status TEXT,"
            " rows_pushed INTEGER DEFAULT 0, error TEXT)"
        )
        self.conn.execute(
            "CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)"
        )
        self.conn.commit()

    def get_watermark(self, target: str, year: str) -> Optional[dt.datetime]:
        row = self.conn.execute(
            "SELECT value FROM watermarks WHERE target=? AND financial_year=?", (target, year)
        ).fetchone()
        if not row or not row[0]:
            return None
        try:
            return dt.datetime.fromisoformat(row[0])
        except ValueError:
            return None

    def set_watermark(self, target: str, year: str, value: dt.datetime) -> None:
        self.conn.execute(
            "INSERT INTO watermarks (target, financial_year, value, updated_at) VALUES (?,?,?,?) "
            "ON CONFLICT(target, financial_year) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at",
            (target, year, value.isoformat(), dt.datetime.now().isoformat()),
        )
        self.conn.commit()

    def clear_watermark(self, target: str, year: Optional[str] = None) -> None:
        if year:
            self.conn.execute("DELETE FROM watermarks WHERE target=? AND financial_year=?", (target, year))
        else:
            self.conn.execute("DELETE FROM watermarks WHERE target=?", (target,))
        self.conn.commit()

    def start_run(self, kind: str, target: str) -> int:
        cur = self.conn.execute(
            "INSERT INTO runs (kind, target, started_at, status) VALUES (?,?,?,'running')",
            (kind, target, dt.datetime.now().isoformat()),
        )
        self.conn.commit()
        return int(cur.lastrowid)

    def finish_run(self, run_id: int, status: str, rows: int = 0, error: str = "") -> None:
        self.conn.execute(
            "UPDATE runs SET finished_at=?, status=?, rows_pushed=?, error=? WHERE id=?",
            (dt.datetime.now().isoformat(), status, rows, error[:2000], run_id),
        )
        self.conn.commit()

    def get_meta(self, key: str, default: Optional[str] = None) -> Optional[str]:
        row = self.conn.execute("SELECT value FROM meta WHERE key=?", (key,)).fetchone()
        return row[0] if row else default

    def set_meta(self, key: str, value: str) -> None:
        self.conn.execute(
            "INSERT INTO meta (key, value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            (key, value),
        )
        self.conn.commit()

    def recent_runs(self, limit: int = 10) -> List[Tuple]:
        return self.conn.execute(
            "SELECT id, kind, target, started_at, finished_at, status, rows_pushed, error "
            "FROM runs ORDER BY id DESC LIMIT ?",
            (limit,),
        ).fetchall()


# ============================================================================ #
#  ERP source                                                                  #
# ============================================================================ #

# Every column the targets store, in the order the SELECT produces them.
COLUMNS: Tuple[str, ...] = (
    "financial_year", "txn_code", "vouch_code", "branch_name", "branch_code",
    "vouch_date", "vouch_time", "entry_datetime", "vouch_num",
    "series", "series_type", "is_stock_transfer",
    "act_name", "act_code", "agent_code", "agent_name", "item_det_code",
    "tot_qty", "calc_net_amt_n", "free_qty", "rate",
    "calc_tax_1", "calc_tax_2", "calc_tax_3", "discount_rs", "calc_scheme_rs",
    "calc_gross_amt", "calc_net_amt", "calc_freight", "sale_or_sr",
    "user_code", "weight_per_unit", "cf_1", "item_hd_code", "item_hd_name",
    "lot_number", "lot_code", "pur_rate", "basic_rate",
    "mobile_no", "cust_hd_code", "customer_name", "cashier_name",
    "group_name", "pack_name",
)

# A return is whatever the series master says is a return, or whatever the line
# itself is flagged as. This replaces the three different hardcoded series lists
# the old scripts each carried.
IS_RETURN = "(BS.type = 'SR' OR TXN.sale_or_sr = 'SR')"


def current_financial_year() -> str:
    today = dt.date.today()
    start = today.year if today.month >= 4 else today.year - 1
    return f"{start}{start + 1}"


def fy_label(suffix: str) -> str:
    """20262027 -> 2026-2027"""
    return f"{suffix[:4]}-{suffix[4:]}" if len(suffix) == 8 else suffix


def fy_bounds(suffix: str) -> Tuple[dt.date, dt.date]:
    start = int(suffix[:4])
    return dt.date(start, 4, 1), dt.date(start + 1, 3, 31)


def build_query(fy_suffix: str, series: Sequence[str], where_extra: str) -> str:
    series_filter = ""
    if series:
        quoted = ", ".join("'" + s.replace("'", "''") + "'" for s in series)
        series_filter = f"  AND BS.series IN ({quoted})\n"

    return f"""
SELECT
    '{fy_label(fy_suffix)}'                AS financial_year,
    TXN.code                               AS txn_code,
    HD.vouch_code                          AS vouch_code,
    BM.branch_name                         AS branch_name,
    BM.branch_code                         AS branch_code,
    HD.vouch_date                          AS vouch_date,
    HD.Vouch_Time                          AS vouch_time,
    HD.LTZ_Entry_Date                      AS entry_datetime,
    HD.vouch_num                           AS vouch_num,
    BS.series                              AS series,
    BS.type                                AS series_type,
    CAST(BS.Stock_Trans AS int)            AS is_stock_transfer,
    ACT.act_name                           AS act_name,
    ACT.act_code                           AS act_code,
    HD.agent_code                          AS agent_code,
    -- Some agent names carry a stray CR/LF from the ERP ("SACHIN RAUT\\n"), which would
    -- split them into separate rows in the drill-down. Strip it at source.
    NULLIF(LTRIM(RTRIM(REPLACE(REPLACE(AG.Agent_Name, CHAR(13), ''), CHAR(10), ''))), '') AS agent_name,
    TXN.item_det_code                      AS item_det_code,
    CASE WHEN {IS_RETURN} THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END           AS tot_qty,
    CASE WHEN {IS_RETURN} THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,
    TXN.Free_Qty                           AS free_qty,
    TXN.rate                               AS rate,
    TXN.Calc_Tax_1                         AS calc_tax_1,
    TXN.Calc_Tax_2                         AS calc_tax_2,
    TXN.Calc_Tax_3                         AS calc_tax_3,
    TXN.calc_commission                    AS discount_rs,
    TXN.Calc_Scheme_Rs                     AS calc_scheme_rs,
    TXN.Calc_Gross_Amt                     AS calc_gross_amt,
    TXN.Calc_Net_Amt                       AS calc_net_amt,
    TXN.calc_freight                       AS calc_freight,
    TXN.sale_or_sr                         AS sale_or_sr,
    IMD.User_Code                          AS user_code,
    IMD.Weight_Per_Unit                    AS weight_per_unit,
    IMD.cf_1                               AS cf_1,
    IMD.Item_Hd_Code                       AS item_hd_code,
    IMH.item_hd_name                       AS item_hd_name,
    LM.lot_number                          AS lot_number,
    LM.lot_code                            AS lot_code,
    LM.pur_rate                            AS pur_rate,
    LM.basic_rate                          AS basic_rate,
    CMH.Mobile_no                          AS mobile_no,
    CMD.cust_hd_code                       AS cust_hd_code,
    CMD.First_name                         AS customer_name,
    CM.Cashier_name                        AS cashier_name,
    GM1.group_name                         AS group_name,
    PM.Pack_Name                           AS pack_name
FROM Sl_Txn{fy_suffix} AS TXN
INNER JOIN Sl_Head{fy_suffix} AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
INNER JOIN Bill_Ser         AS BS  ON HD.Series_Code   = BS.Series_Code
LEFT  JOIN It_Mst_Det       AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code
LEFT  JOIN It_Mst_Hd        AS IMH ON IMD.Item_Hd_Code  = IMH.Item_Hd_Code
LEFT  JOIN Pack_Mst         AS PM  ON IMD.Pack_Code     = PM.Pack_Code
LEFT  JOIN Lot_Mst          AS LM  ON TXN.Lot_Code      = LM.Lot_Code
LEFT  JOIN Group_Mst        AS GM1 ON IMH.Group_Code    = GM1.Group_Code
LEFT  JOIN Cust_Mst_Hd      AS CMH ON HD.Member_Code    = CMH.Cust_Hd_Code
LEFT  JOIN Cust_Mst_Det     AS CMD ON CMH.Cust_Hd_Code  = CMD.Cust_Hd_Code
LEFT  JOIN Accounts         AS ACT ON HD.cust_code      = ACT.act_code
LEFT  JOIN Agents_Brokers   AS AG  ON HD.agent_code     = AG.Code
LEFT  JOIN Branch_Mst       AS BM  ON HD.Branch_Code    = BM.Branch_Code
LEFT  JOIN SL_Cashier_Mst   AS CM  ON HD.Cashier_Code   = CM.Code
WHERE TXN.Deleted = 0
{series_filter}{where_extra}
ORDER BY HD.LTZ_Entry_Date, TXN.code
""".strip()


class ErpSource:
    def __init__(self, cfg: Dict[str, Any]):
        self.cfg = cfg

    def connect(self):
        c = self.cfg
        driver = self._resolve_driver(c.get("driver", "{ODBC Driver 17 for SQL Server}"))
        if c.get("use_windows_auth"):
            conn_str = (
                f"DRIVER={driver};SERVER={c['server']};DATABASE={c['database']};"
                "Trusted_Connection=yes;TrustServerCertificate=yes;"
            )
        else:
            conn_str = (
                f"DRIVER={driver};SERVER={c['server']};DATABASE={c['database']};"
                f"UID={c.get('username', 'sa')};PWD={c.get('password', '')};TrustServerCertificate=yes;"
            )
        conn = pyodbc.connect(conn_str, timeout=int(c.get("login_timeout_seconds", 20)))
        conn.timeout = int(c.get("query_timeout_seconds", 600))
        return conn

    @staticmethod
    def _resolve_driver(preferred: str) -> str:
        available = pyodbc.drivers()
        bare = preferred.strip("{}")
        if bare in available:
            return "{" + bare + "}"
        for candidate in ("ODBC Driver 18 for SQL Server", "ODBC Driver 17 for SQL Server",
                          "SQL Server Native Client 11.0", "SQL Server"):
            if candidate in available:
                return "{" + candidate + "}"
        raise RuntimeError(f"No usable SQL Server ODBC driver found. Installed: {available}")

    def year_exists(self, conn, fy_suffix: str) -> bool:
        row = conn.cursor().execute(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME IN (?, ?)",
            (f"Sl_Txn{fy_suffix}", f"Sl_Head{fy_suffix}"),
        ).fetchone()
        return bool(row and row[0] == 2)

    def fetch(self, conn, fy_suffix: str, series: Sequence[str], where_extra: str,
              params: Sequence[Any] = ()) -> List[Dict[str, Any]]:
        sql = build_query(fy_suffix, series, where_extra)
        cur = conn.cursor()
        cur.execute(sql, *params) if params else cur.execute(sql)
        names = [d[0] for d in cur.description]
        return [dict(zip(names, map(normalise_value, row))) for row in cur.fetchall()]

    def txn_codes_in_window(self, conn, fy_suffix: str, series: Sequence[str],
                            date_from: dt.date, date_to: dt.date) -> List[int]:
        series_filter = ""
        if series:
            quoted = ", ".join("'" + s.replace("'", "''") + "'" for s in series)
            series_filter = f"  AND BS.series IN ({quoted})\n"
        sql = f"""
SELECT TXN.code
FROM Sl_Txn{fy_suffix} AS TXN
INNER JOIN Sl_Head{fy_suffix} AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code
WHERE TXN.Deleted = 0
{series_filter}  AND HD.vouch_date >= ? AND HD.vouch_date <= ?
""".strip()
        cur = conn.cursor()
        cur.execute(sql, date_from.isoformat(), date_to.isoformat())
        return [int(r[0]) for r in cur.fetchall()]

    def day_checksum(self, conn, fy_suffix: str, series: Sequence[str],
                     date_from: dt.date, date_to: dt.date) -> Dict[str, Tuple[int, float]]:
        series_filter = ""
        if series:
            quoted = ", ".join("'" + s.replace("'", "''") + "'" for s in series)
            series_filter = f"  AND BS.series IN ({quoted})\n"
        sql = f"""
SELECT CAST(HD.vouch_date AS date) AS d, COUNT(*) AS c,
       CAST(ROUND(SUM(TXN.Calc_Net_Amt), 2) AS decimal(18,2)) AS s
FROM Sl_Txn{fy_suffix} AS TXN
INNER JOIN Sl_Head{fy_suffix} AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code
WHERE TXN.Deleted = 0
{series_filter}  AND HD.vouch_date >= ? AND HD.vouch_date <= ?
GROUP BY CAST(HD.vouch_date AS date)
""".strip()
        cur = conn.cursor()
        cur.execute(sql, date_from.isoformat(), date_to.isoformat())
        out: Dict[str, Tuple[int, float]] = {}
        for row in cur.fetchall():
            out[str(row[0])] = (int(row[1]), float(row[2] or 0))
        return out


def normalise_value(value: Any) -> Any:
    if isinstance(value, decimal.Decimal):
        return float(value)
    if isinstance(value, dt.datetime):
        return value.strftime("%Y-%m-%d %H:%M:%S")
    if isinstance(value, dt.date):
        return value.isoformat()
    if isinstance(value, dt.time):
        return value.strftime("%H:%M:%S")
    if isinstance(value, bytes):
        return value.decode("utf-8", errors="replace")
    return value


# ============================================================================ #
#  Targets                                                                     #
# ============================================================================ #

class Target:
    name = "target"

    def push(self, rows: List[Dict[str, Any]], fy: str, allow_reset: bool = False) -> int:
        raise NotImplementedError

    def day_checksum(self, fy: str, date_from: dt.date, date_to: dt.date) -> Dict[str, Tuple[int, float]]:
        raise NotImplementedError

    def prune(self, fy: str, date_from: dt.date, date_to: dt.date, valid_codes: Sequence[int]) -> int:
        raise NotImplementedError

    def describe(self) -> str:
        return self.name

    def report_health(self, payload: Dict[str, Any]) -> None:
        pass


class CloudTarget(Target):
    name = "cloud"

    def __init__(self, cfg: Dict[str, Any], batch_size: int):
        self.base = cfg["base_url"].rstrip("/")
        self.token = cfg["token"]
        self.timeout = int(cfg.get("timeout_seconds", 120))
        self.verify = bool(cfg.get("verify_ssl", True))

        if not self.verify:
            # urllib3 prints InsecureRequestWarning on EVERY request, which buries the real
            # progress lines. Say it once, then stay quiet.
            try:
                import urllib3
                urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
            except Exception:
                pass
            log(f"TLS verification is OFF for {self.base} -- the sync token travels "
                "unverified. Set verify_ssl true in agent_config.json once the certificate "
                "is trusted.", "WARN")
        self.health_enabled = bool(cfg.get("report_health", True))
        self.batch_size = batch_size

    def _post(self, path: str, payload: Dict[str, Any], retries: int = 3) -> Dict[str, Any]:
        url = f"{self.base}{path}"
        payload = dict(payload, token=self.token)
        last = ""
        for attempt in range(1, retries + 1):
            try:
                res = requests.post(url, json=payload, timeout=self.timeout, verify=self.verify)
                if res.status_code == 200:
                    return res.json()
                last = f"HTTP {res.status_code}: {res.text[:400]}"
                if 400 <= res.status_code < 500 and res.status_code not in (408, 429):
                    break  # our fault, not worth retrying
            except Exception as exc:  # network hiccup -> back off and try again
                last = str(exc)
            if attempt < retries:
                time.sleep(min(30, 3 * attempt))
        raise RuntimeError(f"POST {path} failed: {last}")

    def push(self, rows: List[Dict[str, Any]], fy: str, allow_reset: bool = False) -> int:
        written = 0
        chunks = [rows[i:i + self.batch_size] for i in range(0, len(rows), self.batch_size)] or [[]]
        for index, chunk in enumerate(chunks):
            res = self._post("/api/sync/mssql-sales", {
                "mode": "upsert",
                "financial_year": fy,
                "allow_reset": bool(allow_reset and index == 0),
                "records": chunk,
            })
            if not res.get("success"):
                raise RuntimeError(res.get("message", "unknown cloud error"))
            written += int(res.get("written", 0))
        return written

    def day_checksum(self, fy: str, date_from: dt.date, date_to: dt.date) -> Dict[str, Tuple[int, float]]:
        res = self._post("/api/sync/mssql-sales/checksum", {
            "financial_year": fy,
            "date_from": date_from.isoformat(),
            "date_to": date_to.isoformat(),
        })
        out: Dict[str, Tuple[int, float]] = {}
        for day in res.get("days", []):
            key = str(day["vouch_date"])[:10]
            out[key] = (int(day["rows_count"]), float(day["net_sum"] or 0))
        return out

    def prune(self, fy: str, date_from: dt.date, date_to: dt.date, valid_codes: Sequence[int]) -> int:
        res = self._post("/api/sync/mssql-sales/prune", {
            "financial_year": fy,
            "date_from": date_from.isoformat(),
            "date_to": date_to.isoformat(),
            "valid_txn_codes": list(valid_codes),
        })
        return int(res.get("deleted", 0))

    def report_health(self, payload: Dict[str, Any]) -> None:
        if not self.health_enabled:
            return
        try:
            self._post("/api/sync/mssql-sales/agent-health", payload, retries=1)
        except Exception as exc:
            log(f"health report skipped: {exc}", "WARN")

    def describe(self) -> str:
        return f"cloud [{self.base}]"


class MySqlTarget(Target):
    name = "local"

    # Columns the agent will create if they are missing, so a fresh XAMPP DB and
    # the live DB end up with identical shapes.
    DDL_ADDITIONS = {
        "financial_year": "VARCHAR(15) NULL",
        "txn_code": "INT NULL",
        "vouch_code": "INT NULL",
        "entry_datetime": "DATETIME NULL",
        "series_type": "VARCHAR(5) NULL",
        "is_stock_transfer": "TINYINT(1) NOT NULL DEFAULT 0",
        "calc_freight": "DECIMAL(15,4) NOT NULL DEFAULT 0",
        "calc_tax_2": "DECIMAL(15,4) NOT NULL DEFAULT 0",
        "calc_tax_3": "DECIMAL(15,4) NOT NULL DEFAULT 0",
        "calc_gross_amt": "DECIMAL(15,4) NOT NULL DEFAULT 0",
        "calc_net_amt": "DECIMAL(15,4) NOT NULL DEFAULT 0",
        "calc_scheme_rs": "DECIMAL(15,4) NOT NULL DEFAULT 0",
    }

    def __init__(self, cfg: Dict[str, Any], batch_size: int):
        if mysql is None:
            raise RuntimeError("mysql-connector-python missing. Install with: pip install mysql-connector-python")
        self.cfg = cfg
        self.batch_size = batch_size
        self.table = "mssql_sales_records"

    def _connect(self):
        return mysql.connector.connect(
            host=self.cfg.get("host", "127.0.0.1"),
            port=int(self.cfg.get("port", 3306)),
            database=self.cfg["database"],
            user=self.cfg.get("username", "root"),
            password=self.cfg.get("password", ""),
            connection_timeout=20,
            autocommit=False,
        )

    def ensure_schema(self, allow_reset: bool = False) -> None:
        conn = self._connect()
        try:
            cur = conn.cursor()
            cur.execute(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
                "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s", (self.table,)
            )
            existing = {r[0] for r in cur.fetchall()}
            if not existing:
                raise RuntimeError(
                    f"Table `{self.table}` does not exist in {self.cfg['database']}. "
                    "Run `php artisan migrate` first."
                )

            for column, spec in self.DDL_ADDITIONS.items():
                if column not in existing:
                    log(f"[local] adding missing column {column}")
                    cur.execute(f"ALTER TABLE `{self.table}` ADD COLUMN `{column}` {spec}")

            cur.execute(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS "
                "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s", (self.table,)
            )
            indexes = {r[0] for r in cur.fetchall()}

            # Rows written by the older sync scripts carry no txn_code. MySQL lets any number
            # of them sit under a unique key (NULLs never collide), so they would survive
            # every upsert and silently double the totals. They have to go.
            cur.execute(f"SELECT COUNT(*) FROM `{self.table}` WHERE txn_code IS NULL")
            unkeyed = cur.fetchone()[0]
            if unkeyed:
                if allow_reset:
                    log(f"[local] removing {unkeyed} legacy rows without txn_code (bootstrap)", "WARN")
                    cur.execute(f"DELETE FROM `{self.table}` WHERE txn_code IS NULL")
                    unkeyed = 0
                elif "mssql_sales_uniq_line" not in indexes:
                    raise RuntimeError(
                        f"[local] {unkeyed} legacy rows have no txn_code, so the unique key cannot be "
                        "created. Run `invoflow_agent.py bootstrap` once to rebuild this table."
                    )
                else:
                    log(f"[local] {unkeyed} legacy rows without txn_code are still present and will "
                        "double-count in reports. Run `bootstrap` to clear them.", "WARN")

            if "mssql_sales_uniq_line" not in indexes and not unkeyed:
                cur.execute(
                    f"ALTER TABLE `{self.table}` ADD UNIQUE KEY `mssql_sales_uniq_line` "
                    "(`financial_year`, `txn_code`)"
                )

            if "mssql_sales_entry_idx" not in indexes:
                cur.execute(
                    f"ALTER TABLE `{self.table}` ADD INDEX `mssql_sales_entry_idx` "
                    "(`financial_year`, `entry_datetime`)"
                )

            conn.commit()
        finally:
            conn.close()

    def push(self, rows: List[Dict[str, Any]], fy: str, allow_reset: bool = False) -> int:
        self.ensure_schema(allow_reset)
        if not rows:
            return 0

        cols = list(COLUMNS) + ["created_at", "updated_at"]
        placeholders = ", ".join(["%s"] * len(cols))
        updatable = [c for c in COLUMNS if c not in ("financial_year", "txn_code")] + ["updated_at"]
        assignments = ", ".join(f"`{c}`=VALUES(`{c}`)" for c in updatable)
        sql = (
            f"INSERT INTO `{self.table}` (" + ", ".join(f"`{c}`" for c in cols) + ") "
            f"VALUES ({placeholders}) ON DUPLICATE KEY UPDATE {assignments}"
        )

        now = dt.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        payload = []
        for row in rows:
            if row.get("txn_code") is None:
                continue
            payload.append(tuple(row.get(c) for c in COLUMNS) + (now, now))

        conn = self._connect()
        written = 0
        try:
            cur = conn.cursor()
            for i in range(0, len(payload), self.batch_size):
                cur.executemany(sql, payload[i:i + self.batch_size])
                written += len(payload[i:i + self.batch_size])
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()
        return written

    def day_checksum(self, fy: str, date_from: dt.date, date_to: dt.date) -> Dict[str, Tuple[int, float]]:
        conn = self._connect()
        try:
            cur = conn.cursor()
            cur.execute(
                f"SELECT vouch_date, COUNT(*), ROUND(SUM(calc_net_amt),2) FROM `{self.table}` "
                "WHERE financial_year=%s AND vouch_date BETWEEN %s AND %s GROUP BY vouch_date",
                (fy, date_from.isoformat(), date_to.isoformat()),
            )
            return {str(r[0])[:10]: (int(r[1]), float(r[2] or 0)) for r in cur.fetchall()}
        finally:
            conn.close()

    def prune(self, fy: str, date_from: dt.date, date_to: dt.date, valid_codes: Sequence[int]) -> int:
        conn = self._connect()
        try:
            cur = conn.cursor()
            if not valid_codes:
                cur.execute(
                    f"DELETE FROM `{self.table}` WHERE financial_year=%s AND vouch_date BETWEEN %s AND %s",
                    (fy, date_from.isoformat(), date_to.isoformat()),
                )
                deleted = cur.rowcount
            else:
                # A temp table keeps the DELETE off a multi-thousand-item IN list.
                cur.execute("CREATE TEMPORARY TABLE _valid_txn (code INT PRIMARY KEY)")
                cur.executemany("INSERT IGNORE INTO _valid_txn (code) VALUES (%s)",
                                [(int(c),) for c in valid_codes])
                cur.execute(
                    f"DELETE t FROM `{self.table}` t LEFT JOIN _valid_txn v ON t.txn_code = v.code "
                    "WHERE t.financial_year=%s AND t.vouch_date BETWEEN %s AND %s AND v.code IS NULL",
                    (fy, date_from.isoformat(), date_to.isoformat()),
                )
                deleted = cur.rowcount
                cur.execute("DROP TEMPORARY TABLE _valid_txn")
            conn.commit()
            return deleted
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()

    def stats(self) -> List[Tuple]:
        conn = self._connect()
        try:
            cur = conn.cursor()
            cur.execute(
                f"SELECT financial_year, COUNT(*), MAX(entry_datetime), MAX(vouch_date) "
                f"FROM `{self.table}` GROUP BY financial_year ORDER BY financial_year"
            )
            return cur.fetchall()
        finally:
            conn.close()

    def describe(self) -> str:
        return f"local [{self.cfg.get('host')}/{self.cfg.get('database')}]"


def build_targets(cfg: Dict[str, Any], only: Optional[str] = None) -> List[Target]:
    batch = int(cfg["sync"].get("batch_size", 1000))
    targets: List[Target] = []
    for name, tcfg in cfg["targets"].items():
        if not tcfg.get("enabled", False):
            continue
        if only and only != "all" and only != name:
            continue
        if tcfg.get("type") == "http":
            targets.append(CloudTarget(tcfg, batch))
        elif tcfg.get("type") == "mysql":
            targets.append(MySqlTarget(tcfg, batch))
        else:
            log(f"Unknown target type for '{name}': {tcfg.get('type')}", "WARN")
    if only and only not in (None, "all") and not targets:
        raise SystemExit(f"[!] Target '{only}' is not enabled in the config.")
    return targets


# ============================================================================ #
#  Sync engine                                                                 #
# ============================================================================ #

class SyncEngine:
    def __init__(self, cfg: Dict[str, Any], state: State, targets: List[Target]):
        self.cfg = cfg
        self.state = state
        self.targets = targets
        self.source = ErpSource(cfg["source"])
        self.series: List[str] = list(cfg["sync"].get("series") or [])
        self.overlap = int(cfg["sync"].get("safety_overlap_minutes", 180))

    def years(self, only: Optional[str] = None) -> List[str]:
        """Resolve the configured financial years.

        "auto" is the year we are in right now, and previous_years walks back from it, so the
        list rolls forward on its own every 1st of April. Hardcoding suffixes would quietly
        stop syncing the current year the moment the FY turned over.
        """
        raw = self.cfg["sync"].get("years") or ["auto"]
        back = max(0, int(self.cfg["sync"].get("previous_years", 0)))
        resolved: List[str] = []

        def add(suffix: str) -> None:
            if suffix not in resolved:
                resolved.append(suffix)

        for item in raw:
            if str(item).lower() == "auto":
                current = current_financial_year()
                add(current)
                start = int(current[:4])
                for step in range(1, back + 1):
                    add(f"{start - step}{start - step + 1}")
            else:
                add(str(item))

        if only:
            resolved = [y for y in resolved if y == only] or [only]
        return resolved

    # ---------------------------------------------------------------- runs

    def run(self, kind: str = "incremental", year: Optional[str] = None,
            date_from: Optional[dt.date] = None, date_to: Optional[dt.date] = None,
            allow_reset: bool = False) -> Dict[str, Any]:
        summary: Dict[str, Any] = {"kind": kind, "targets": {}, "rows": 0, "ok": True}

        conn = self.source.connect()
        try:
            for fy in self.years(year):
                if not self.source.year_exists(conn, fy):
                    log(f"FY {fy_label(fy)}: no Sl_Txn{fy}/Sl_Head{fy} table in the ERP -- skipped", "WARN")
                    continue
                self._run_year(conn, fy, kind, summary, date_from, date_to, allow_reset)
        finally:
            conn.close()

        if self.cfg["sync"]["reconcile"].get("enabled", True) and kind in ("incremental", "reconcile"):
            try:
                self.reconcile(year=year)
            except Exception as exc:
                log(f"reconcile failed: {exc}", "ERROR")
                summary["ok"] = False

        return summary

    def _run_year(self, conn, fy: str, kind: str, summary: Dict[str, Any],
                  date_from: Optional[dt.date], date_to: Optional[dt.date],
                  allow_reset: bool) -> None:
        label = fy_label(fy)

        for target in self.targets:
            run_id = self.state.start_run(kind, target.name)
            try:
                if kind == "window":
                    where = "  AND HD.vouch_date >= ? AND HD.vouch_date <= ?"
                    params = (date_from.isoformat(), date_to.isoformat())
                    banner = f"{date_from} .. {date_to}"
                elif kind in ("full", "bootstrap"):
                    where = ""
                    params = ()
                    banner = "whole year"
                else:
                    since = self._resume_point(target.name, fy)
                    where = "  AND HD.LTZ_Entry_Date >= ?"
                    params = (since.strftime("%Y-%m-%d %H:%M:%S"),)
                    banner = f"entries since {since:%Y-%m-%d %H:%M}"

                log(f"[{target.name}] FY {label} -- fetching ({banner})")
                rows = self.source.fetch(conn, fy, self.series, where, params)
                log(f"[{target.name}] FY {label} -- {len(rows)} rows from ERP")

                if rows:
                    written = target.push(rows, label, allow_reset=allow_reset)
                    log(f"[{target.name}] FY {label} -- {written} rows written")
                    summary["rows"] += written

                    # Only move the watermark once the target has confirmed the write,
                    # so a failed push simply repeats next time instead of leaving a hole.
                    high = max((r.get("entry_datetime") for r in rows if r.get("entry_datetime")), default=None)
                    if high:
                        self.state.set_watermark(target.name, fy, dt.datetime.strptime(high, "%Y-%m-%d %H:%M:%S"))
                else:
                    written = 0
                    log(f"[{target.name}] FY {label} -- nothing new")

                if kind in ("full", "bootstrap"):
                    start, end = fy_bounds(fy)
                    codes = self.source.txn_codes_in_window(conn, fy, self.series, start, end)
                    removed = target.prune(label, start, end, codes)
                    if removed:
                        log(f"[{target.name}] FY {label} -- pruned {removed} stale rows")

                self.state.finish_run(run_id, "ok", written)
                summary["targets"].setdefault(target.name, {})[label] = written
            except Exception as exc:
                summary["ok"] = False
                self.state.finish_run(run_id, "failed", 0, str(exc))
                log(f"[{target.name}] FY {label} FAILED: {exc}", "ERROR")
                log(traceback.format_exc(), "DEBUG")

    def _resume_point(self, target: str, fy: str) -> dt.datetime:
        mark = self.state.get_watermark(target, fy)
        if mark is None:
            start, _ = fy_bounds(fy)
            return dt.datetime.combine(start, dt.time.min)
        return mark - dt.timedelta(minutes=self.overlap)

    # ---------------------------------------------------------- reconcile

    def reconcile(self, year: Optional[str] = None) -> Dict[str, Any]:
        """Compare day-wise count + net total between ERP and each target, then
        re-pull only the days that disagree. This is what catches bills that were
        edited or deleted after they were first synced."""
        rcfg = self.cfg["sync"]["reconcile"]
        days_back = int(rcfg.get("days", 45))
        tolerance = float(rcfg.get("tolerance", 0.01))
        result: Dict[str, Any] = {"checked": 0, "repaired": 0}

        conn = self.source.connect()
        try:
            for fy in self.years(year):
                if not self.source.year_exists(conn, fy):
                    continue
                label = fy_label(fy)
                fy_start, fy_end = fy_bounds(fy)
                window_to = min(dt.date.today(), fy_end)
                window_from = max(fy_start, window_to - dt.timedelta(days=days_back))
                if window_from > window_to:
                    continue

                erp = self.source.day_checksum(conn, fy, self.series, window_from, window_to)

                for target in self.targets:
                    try:
                        mine = target.day_checksum(label, window_from, window_to)
                    except Exception as exc:
                        log(f"[{target.name}] checksum failed for FY {label}: {exc}", "ERROR")
                        continue

                    bad_days = sorted(
                        d for d in set(erp) | set(mine)
                        if erp.get(d, (0, 0.0))[0] != mine.get(d, (0, 0.0))[0]
                        or abs(erp.get(d, (0, 0.0))[1] - mine.get(d, (0, 0.0))[1]) > tolerance
                    )
                    result["checked"] += len(set(erp) | set(mine))

                    if not bad_days:
                        log(f"[{target.name}] FY {label} reconcile clean ({window_from} .. {window_to})")
                        continue

                    log(f"[{target.name}] FY {label} -- {len(bad_days)} day(s) differ, repairing: "
                        f"{', '.join(bad_days[:8])}{' ...' if len(bad_days) > 8 else ''}", "WARN")

                    for day in bad_days:
                        day_date = dt.date.fromisoformat(day)
                        rows = self.source.fetch(
                            conn, fy, self.series,
                            "  AND HD.vouch_date >= ? AND HD.vouch_date <= ?",
                            (day, day),
                        )
                        target.push(rows, label)
                        codes = [int(r["txn_code"]) for r in rows if r.get("txn_code") is not None]
                        removed = target.prune(label, day_date, day_date, codes)
                        result["repaired"] += 1
                        log(f"[{target.name}] repaired {day}: {len(rows)} rows in, {removed} stale removed")
        finally:
            conn.close()

        return result


# ============================================================================ #
#  Scheduler                                                                   #
# ============================================================================ #

def next_daily_run(daily_at: str, after: dt.datetime) -> dt.datetime:
    try:
        hh, mm = (int(x) for x in daily_at.split(":", 1))
    except ValueError:
        hh, mm = 2, 0
    candidate = after.replace(hour=hh, minute=mm, second=0, microsecond=0)
    if candidate <= after:
        candidate += dt.timedelta(days=1)
    return candidate


def compute_next_run(cfg: Dict[str, Any], last_run: Optional[dt.datetime], now: dt.datetime) -> Optional[dt.datetime]:
    sched = cfg["schedule"]
    mode = str(sched.get("mode", "daily")).lower()

    if mode == "off":
        return None

    if mode == "interval":
        minutes = max(1, int(sched.get("interval_minutes", 1440)))
        base = last_run or now
        nxt = base + dt.timedelta(minutes=minutes)
        return nxt if nxt > now else now

    # daily
    daily_at = str(sched.get("daily_at", "02:00"))
    if sched.get("catch_up_if_missed", True) and last_run is not None:
        # If the PC was asleep past the slot, run as soon as we are back up.
        due_today = now.replace(
            hour=int(daily_at.split(":")[0]), minute=int(daily_at.split(":")[1]),
            second=0, microsecond=0,
        )
        if now >= due_today and last_run < due_today:
            return now
    return next_daily_run(daily_at, now)


def run_service(cfg: Dict[str, Any], state: State, only_target: Optional[str]) -> None:
    sched = cfg["schedule"]
    retry_minutes = max(1, int(sched.get("retry_after_minutes", 30)))
    max_retries = max(0, int(sched.get("max_retries_per_window", 4)))

    log("=" * 72)
    log(f"InvoFlow Sync Agent v{AGENT_VERSION} -- service mode")
    targets = build_targets(cfg, only_target)
    for t in targets:
        log(f"  target: {t.describe()}")
    log(f"  schedule: {sched.get('mode')} "
        f"({sched.get('daily_at') if sched.get('mode') == 'daily' else str(sched.get('interval_minutes')) + ' min'})")
    log(f"  years: {', '.join(fy_label(y) for y in SyncEngine(cfg, state, targets).years())}")
    log("=" * 72)

    last_raw = state.get_meta("last_successful_run")
    last_run = dt.datetime.fromisoformat(last_raw) if last_raw else None
    retries = 0

    if sched.get("run_on_startup", False):
        next_run = dt.datetime.now()
    else:
        next_run = compute_next_run(cfg, last_run, dt.datetime.now())

    while True:
        try:
            now = dt.datetime.now()

            if next_run is None:
                time.sleep(60)
                continue

            if now >= next_run:
                log("-" * 72)
                log(f"Scheduled sync starting (due {next_run:%Y-%m-%d %H:%M})")
                engine = SyncEngine(cfg, state, build_targets(cfg, only_target))
                summary = engine.run("incremental")

                if summary["ok"]:
                    retries = 0
                    last_run = dt.datetime.now()
                    state.set_meta("last_successful_run", last_run.isoformat())
                    next_run = compute_next_run(cfg, last_run, last_run)
                    log(f"Sync OK -- {summary['rows']} rows. Next run {next_run:%Y-%m-%d %H:%M}")
                    status = "ok"
                    error = ""
                else:
                    retries += 1
                    status = "failed"
                    error = "one or more targets failed; see agent log"
                    if retries <= max_retries:
                        next_run = dt.datetime.now() + dt.timedelta(minutes=retry_minutes)
                        log(f"Sync had failures -- retry {retries}/{max_retries} at "
                            f"{next_run:%H:%M}", "WARN")
                    else:
                        next_run = compute_next_run(cfg, dt.datetime.now(), dt.datetime.now())
                        log(f"Sync still failing after {max_retries} retries. "
                            f"Waiting for the next scheduled slot {next_run:%Y-%m-%d %H:%M}", "ERROR")
                        retries = 0

                for target in build_targets(cfg, only_target):
                    target.report_health({
                        "agent": AGENT_NAME,
                        "version": AGENT_VERSION,
                        "status": status,
                        "last_run_at": dt.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                        "next_run_at": next_run.strftime("%Y-%m-%d %H:%M:%S") if next_run else None,
                        "rows_pushed": summary["rows"],
                        "error": error,
                    })

            time.sleep(30)

        except KeyboardInterrupt:
            log("Service stopped by user.")
            return
        except Exception as exc:
            log(f"Service loop error: {exc}", "ERROR")
            log(traceback.format_exc(), "DEBUG")
            time.sleep(60)


# ============================================================================ #
#  CLI                                                                         #
# ============================================================================ #

def cmd_status(cfg: Dict[str, Any], state: State, args) -> int:
    print()
    print(f"InvoFlow Sync Agent v{AGENT_VERSION}")
    print(f"Config      : {args.config}")
    print(f"Schedule    : {cfg['schedule'].get('mode')} @ {cfg['schedule'].get('daily_at')} "
          f"(interval {cfg['schedule'].get('interval_minutes')} min)")
    last = state.get_meta("last_successful_run")
    print(f"Last OK run : {last or 'never'}")

    nxt = compute_next_run(cfg, dt.datetime.fromisoformat(last) if last else None, dt.datetime.now())
    print(f"Next run    : {nxt:%Y-%m-%d %H:%M}" if nxt else "Next run    : (schedule off)")

    print("\nWatermarks")
    rows = state.conn.execute(
        "SELECT target, financial_year, value FROM watermarks ORDER BY target, financial_year"
    ).fetchall()
    if not rows:
        print("  (none yet)")
    for target, fy, value in rows:
        print(f"  {target:<8} {fy_label(fy):<10} {value}")

    for target in build_targets(cfg, args.target):
        print(f"\nTarget: {target.describe()}")
        try:
            if isinstance(target, MySqlTarget):
                for fy, count, max_entry, max_vd in target.stats():
                    print(f"  {str(fy):<10} rows={count:<8} last_entry={max_entry} last_bill={max_vd}")
            elif isinstance(target, CloudTarget):
                res = requests.get(
                    f"{target.base}/api/sync/mssql-sales/status",
                    params={"token": target.token}, timeout=30, verify=target.verify,
                ).json()
                print(f"  total rows : {res.get('total_rows')}")
                print(f"  last bill  : {res.get('latest_date')}")
                print(f"  last sync  : {res.get('last_sync_at')}")
                for fy, info in (res.get("years") or {}).items():
                    print(f"  {fy:<10} rows={info.get('rows_count')} last_entry={info.get('max_entry')} "
                          f"unkeyed={info.get('unkeyed_rows')}")
        except Exception as exc:
            print(f"  [!] unreachable: {exc}")

    print("\nRecent runs")
    for rid, kind, target, started, finished, status, rows_pushed, error in state.recent_runs(8):
        flag = "OK " if status == "ok" else "ERR"
        print(f"  #{rid:<4} {flag} {kind:<11} {target:<7} {str(started)[:19]} rows={rows_pushed}"
              + (f"  {error[:80]}" if error else ""))
    print()
    return 0


def cmd_test(cfg: Dict[str, Any], state: State, args) -> int:
    ok = True

    print("\n[1] ERP (MS SQL)")
    try:
        source = ErpSource(cfg["source"])
        conn = source.connect()
        cur = conn.cursor()
        cur.execute("SELECT @@VERSION")
        print(f"    connected: {str(cur.fetchone()[0]).splitlines()[0][:70]}")
        for fy in SyncEngine(cfg, state, []).years():
            exists = source.year_exists(conn, fy)
            print(f"    FY {fy_label(fy)}: {'tables found' if exists else 'TABLES MISSING'}")
            ok = ok and exists
        conn.close()
    except Exception as exc:
        print(f"    [!] {exc}")
        ok = False

    for target in build_targets(cfg, args.target):
        print(f"\n[2] {target.describe()}")
        try:
            if isinstance(target, MySqlTarget):
                target.ensure_schema()
                print("    connected, schema ready")
            else:
                res = requests.get(
                    f"{target.base}/api/sync/mssql-sales/status",
                    params={"token": target.token}, timeout=30, verify=target.verify,
                )
                print(f"    HTTP {res.status_code}: {res.text[:120]}")
                ok = ok and res.status_code == 200
        except Exception as exc:
            print(f"    [!] {exc}")
            ok = False

    print("\nResult:", "ALL OK" if ok else "PROBLEMS FOUND -- see above", "\n")
    return 0 if ok else 1


def cmd_sync(cfg: Dict[str, Any], state: State, args) -> int:
    targets = build_targets(cfg, args.target)
    engine = SyncEngine(cfg, state, targets)

    if args.date_from or args.date_to:
        if not (args.date_from and args.date_to):
            raise SystemExit("[!] --from and --to must be given together.")
        summary = engine.run(
            "window", year=args.year,
            date_from=dt.date.fromisoformat(args.date_from),
            date_to=dt.date.fromisoformat(args.date_to),
        )
    elif args.full:
        summary = engine.run("full", year=args.year)
    else:
        summary = engine.run("incremental", year=args.year)

    if summary["ok"]:
        state.set_meta("last_successful_run", dt.datetime.now().isoformat())

    log(f"Done -- {summary['rows']} rows written. {'OK' if summary['ok'] else 'WITH ERRORS'}")
    return 0 if summary["ok"] else 1


def cmd_bootstrap(cfg: Dict[str, Any], state: State, args) -> int:
    targets = build_targets(cfg, args.target)
    print("\nBootstrap will REPLACE the sales data on:")
    for t in targets:
        print(f"  - {t.describe()}")
    print("\nRows written by the older sync scripts (they have no txn_code) will be removed,")
    print("then every configured financial year is pushed fresh from the ERP.")

    if not args.yes:
        answer = input("\nType 'yes' to continue: ").strip().lower()
        if answer != "yes":
            print("Aborted.")
            return 1

    for target in targets:
        state.clear_watermark(target.name)

    engine = SyncEngine(cfg, state, targets)
    summary = engine.run("bootstrap", year=args.year, allow_reset=True)

    if summary["ok"]:
        state.set_meta("last_successful_run", dt.datetime.now().isoformat())
        state.set_meta("bootstrapped_at", dt.datetime.now().isoformat())

    log(f"Bootstrap finished -- {summary['rows']} rows. {'OK' if summary['ok'] else 'WITH ERRORS'}")
    return 0 if summary["ok"] else 1


def cmd_reconcile(cfg: Dict[str, Any], state: State, args) -> int:
    engine = SyncEngine(cfg, state, build_targets(cfg, args.target))
    result = engine.reconcile(year=args.year)
    log(f"Reconcile finished -- {result['checked']} day-slots checked, {result['repaired']} repaired.")
    return 0


def cmd_service(cfg: Dict[str, Any], state: State, args) -> int:
    run_service(cfg, state, args.target)
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(
        prog="invoflow_agent",
        description="Sync ERP sales data from the local MS SQL Server to the cloud site and local MySQL.",
    )
    # Shared options live on every subcommand, so they are written after the command:
    #   invoflow_agent.py sync --target local --year 20262027
    common = argparse.ArgumentParser(add_help=False)
    common.add_argument("--config", default=DEFAULT_CONFIG, help="path to agent_config.json")
    common.add_argument("--target", default=None, choices=["cloud", "local", "all"],
                        help="restrict this run to one target")
    common.add_argument("--year", default=None, help="financial year suffix, e.g. 20262027")

    sub = parser.add_subparsers(dest="command")

    p_sync = sub.add_parser("sync", parents=[common], help="run a sync right now")
    p_sync.add_argument("--full", action="store_true", help="re-pull the whole financial year")
    p_sync.add_argument("--from", dest="date_from", help="window start, YYYY-MM-DD")
    p_sync.add_argument("--to", dest="date_to", help="window end, YYYY-MM-DD")

    p_boot = sub.add_parser("bootstrap", parents=[common], help="first-time rebuild of the target tables")
    p_boot.add_argument("--yes", action="store_true", help="skip the confirmation prompt")

    sub.add_parser("reconcile", parents=[common], help="verify recent days and repair differences")
    sub.add_parser("service", parents=[common], help="stay running and follow the configured schedule")
    sub.add_parser("status", parents=[common], help="show watermarks, targets and recent runs")
    sub.add_parser("test", parents=[common], help="check ERP and target connectivity")

    args = parser.parse_args()
    if not args.command:
        parser.print_help()
        return 0

    cfg = load_config(args.config)

    global log
    log = Logger(int(cfg.get("logging", {}).get("keep_days", 30)))

    state = State()

    handlers: Dict[str, Callable] = {
        "sync": cmd_sync,
        "bootstrap": cmd_bootstrap,
        "reconcile": cmd_reconcile,
        "service": cmd_service,
        "status": cmd_status,
        "test": cmd_test,
    }

    try:
        return handlers[args.command](cfg, state, args)
    except SystemExit:
        raise
    except KeyboardInterrupt:
        print("\nInterrupted.")
        return 130
    except Exception as exc:
        log(f"{args.command} failed: {exc}", "ERROR")
        log(traceback.format_exc(), "DEBUG")
        return 1


if __name__ == "__main__":
    sys.exit(main())

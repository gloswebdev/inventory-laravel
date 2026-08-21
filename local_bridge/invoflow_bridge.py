#!/usr/bin/env python3
"""
================================================================================
 InvoFlow MSSQL Bridge Agent (Python Standalone)
 Connects Local PC Microsoft SQL Server to InvoFlow Cloud/Hostinger API.
 Features:
   - On-Demand Query Execution via Web Console
   - Automated Background Push Sync (Hourly / Daily Schedule)
   - Auto-Starts via Windows Task Scheduler (No XAMPP / Zero PHP Server)
================================================================================
"""

import os
import sys
import time
import json
import decimal
import datetime
import html
from typing import Dict, Any, List, Tuple

# Ensure stdout and stderr handle utf-8 safely on Windows
if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8', errors='replace')
        sys.stderr.reconfigure(encoding='utf-8', errors='replace')
    except Exception:
        pass

# Try importing dependencies or prompt installation
try:
    import requests
except ImportError:
    print("[!] 'requests' module not found. Installing via pip...", flush=True)
    os.system(f"{sys.executable} -m pip install requests")
    import requests

try:
    import pyodbc
except ImportError:
    print("[!] 'pyodbc' module not found. Installing via pip...", flush=True)
    os.system(f"{sys.executable} -m pip install pyodbc")
    import pyodbc


AGENT_VERSION = "2.2.0"
CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "bridge_config.json")


def build_auto_sync_query(sync_mode: str = "incremental", days: int = 7) -> str:
    """Build MSSQL Sales Sync Query (Full Year or Rolling Incremental Days)"""
    date_filter = ""
    if sync_mode == "incremental" and days > 0:
        date_filter = f"  AND HD.vouch_date >= DATEADD(day, -{days}, CAST(GETDATE() AS DATE))\n"

    return f"""
SELECT 
    BM.branch_name,
    HD.vouch_date,
    HD.Vouch_Time,
    HD.vouch_num,
    BS.series,
    ACT.act_name,
    TXN.item_det_code,
    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END AS tot_qty,
    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,
    TXN.Free_Qty,
    TXN.rate,
    TXN.Calc_Tax_1,
    TXN.calc_commission AS discount_rs,
    IMD.User_Code,
    IMH.item_hd_name,
    GM1.group_name,
    BM.branch_code
FROM Sl_Txn20262027 AS TXN
INNER JOIN Sl_Head20262027 AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code
LEFT JOIN It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code
LEFT JOIN It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code
LEFT JOIN Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code
LEFT JOIN Accounts AS ACT ON HD.cust_code = ACT.act_code
LEFT JOIN Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code
WHERE BS.Stock_Trans = 0
  AND BS.type IN ('SL', 'SR')
  AND BS.series IN ('AMSR', 'AKSL', 'AKCS', 'AKLF', 'PNSL', 'PNCS', 'PNF', 'SPSR', 'MPSL', 'MPCS', 'SMSR', 'UPSL', 'UPCS', 'SWSR', 'MHSL', 'LKS', 'LKR', 'SWAK', 'SWPN', 'SWMP', 'SWUP')
{date_filter}ORDER BY HD.vouch_date DESC;
"""


def load_config() -> Dict[str, Any]:
    """Load settings from bridge_config.json"""
    if not os.path.exists(CONFIG_FILE):
        default_cfg = {
            "mssql": {
                "server": "localhost",
                "database": "LOGICDBSY",
                "use_windows_auth": True,
                "username": "sa",
                "password": "Logic@1234",
                "driver": "{ODBC Driver 17 for SQL Server}"
            },
            "cloud_api": {
                "base_url": "https://invoflow.gloswebdev.in/api/v1/bridge",
                "secret_token": "invoflow_bridge_key_2026",
                "poll_interval_seconds": 2
            },
            "auto_sync": {
                "enabled": True,
                "sync_interval_hours": 1,
                "sync_on_startup": True
            }
        }
        with open(CONFIG_FILE, "w", encoding="utf-8") as f:
            json.dump(default_cfg, f, indent=4)
        return default_cfg

    try:
        with open(CONFIG_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception as e:
        print(f"[!] Error reading config file: {e}. Using defaults.", flush=True)
        return {}


def get_available_odbc_driver(preferred: str) -> str:
    """Find the best available SQL Server ODBC driver installed on Windows"""
    drivers = pyodbc.drivers()
    if preferred in drivers:
        return preferred
    
    candidates = [
        "ODBC Driver 18 for SQL Server",
        "ODBC Driver 17 for SQL Server",
        "ODBC Driver 13 for SQL Server",
        "ODBC Driver 11 for SQL Server",
        "SQL Server Native Client 11.0",
        "SQL Server Native Client 10.0",
        "SQL Server"
    ]
    for c in candidates:
        if c in drivers:
            return f"{{{c}}}" if not c.startswith("{") else c
    return "{SQL Server}"


def get_mssql_connection(cfg: Dict[str, Any]):
    """Establish connection to local MSSQL database"""
    mssql_cfg = cfg.get("mssql", {})
    server = mssql_cfg.get("server", "localhost")
    database = mssql_cfg.get("database", "LOGICDBSY")
    use_win_auth = mssql_cfg.get("use_windows_auth", True)
    username = mssql_cfg.get("username", "sa")
    password = mssql_cfg.get("password", "")
    driver = get_available_odbc_driver(mssql_cfg.get("driver", "{ODBC Driver 17 for SQL Server}"))

    if use_win_auth:
        conn_str = f"DRIVER={driver};SERVER={server};DATABASE={database};Trusted_Connection=yes;TrustServerCertificate=yes;"
    else:
        conn_str = f"DRIVER={driver};SERVER={server};DATABASE={database};UID={username};PWD={password};TrustServerCertificate=yes;"

    return pyodbc.connect(conn_str, timeout=10)


def json_serializer(obj: Any) -> Any:
    """Custom JSON serializer for dates, decimals, and bytes"""
    if isinstance(obj, (datetime.date, datetime.datetime)):
        return obj.isoformat()
    if isinstance(obj, (datetime.time)):
        return obj.strftime("%H:%M:%S")
    if isinstance(obj, decimal.Decimal):
        return float(obj)
    if isinstance(obj, bytes):
        return obj.hex()
    if isinstance(obj, bytearray):
        return obj.hex()
    return str(obj)


class InvoFlowBridge:
    def __init__(self):
        self.config = load_config()
        self.api_cfg = self.config.get("cloud_api", {})
        self.base_url = self.api_cfg.get("base_url", "https://invoflow.gloswebdev.in/api/v1/bridge").rstrip("/")
        self.token = self.api_cfg.get("secret_token", "invoflow_bridge_key_2026")
        self.poll_interval = float(self.api_cfg.get("poll_interval_seconds", 2))
        self.db_name = self.config.get("mssql", {}).get("database", "LOGICDBSY")
        self.headers = {
            "X-Bridge-Token": self.token,
            "User-Agent": f"InvoFlow-Bridge-Agent/{AGENT_VERSION}",
            "Content-Type": "application/json"
        }

        # Auto sync settings
        self.auto_sync_cfg = self.config.get("auto_sync", {})
        self.auto_sync_enabled = self.auto_sync_cfg.get("enabled", True)
        self.sync_mode = self.auto_sync_cfg.get("sync_mode", "incremental") # "incremental" or "full"
        self.incremental_days = int(self.auto_sync_cfg.get("incremental_days", 7))
        self.sync_interval_hours = float(self.auto_sync_cfg.get("sync_interval_hours", 1))
        self.sync_interval_seconds = self.sync_interval_hours * 3600
        self.last_auto_sync = 0 if self.auto_sync_cfg.get("sync_on_startup", True) else time.time()

    def print_banner(self):
        mode_desc = f"Incremental (Last {self.incremental_days} Days)" if self.sync_mode == "incremental" else "Full Year"
        print("=" * 70, flush=True)
        print(f"  [+] InvoFlow Local MSSQL Bridge Agent v{AGENT_VERSION}", flush=True)
        print(f"  [*] Target MSSQL DB:   {self.db_name}", flush=True)
        print(f"  [*] Cloud API URL:     {self.base_url}", flush=True)
        print(f"  [*] Web Poll Interval: {self.poll_interval}s", flush=True)
        print(f"  [*] Auto-Sync:         {'ENABLED (Every ' + str(self.sync_interval_hours) + 'h) [' + mode_desc + ']' if self.auto_sync_enabled else 'DISABLED'}", flush=True)
        print("=" * 70, flush=True)

    def test_mssql(self) -> bool:
        """Test MSSQL local connectivity"""
        try:
            conn = get_mssql_connection(self.config)
            cursor = conn.cursor()
            cursor.execute("SELECT @@VERSION AS ver")
            row = cursor.fetchone()
            conn.close()
            print(f"[OK] Local MSSQL Connection: OK ({self.db_name})", flush=True)
            return True
        except Exception as e:
            print(f"[ERROR] Local MSSQL Connection FAILED: {e}", flush=True)
            return False

    def send_heartbeat(self):
        """Send heartbeat to Hostinger API"""
        try:
            url = f"{self.base_url}/heartbeat"
            params = {
                "agent_version": AGENT_VERSION,
                "db_name": self.db_name
            }
            res = requests.get(url, headers=self.headers, params=params, timeout=5)
            if res.status_code == 200:
                return True
            else:
                print(f"[!] Heartbeat HTTP {res.status_code}: {res.text}", flush=True)
        except Exception as e:
            pass
        return False

    def poll_for_job(self) -> Dict[str, Any]:
        """Poll Hostinger for pending jobs"""
        url = f"{self.base_url}/poll"
        params = {
            "agent_version": AGENT_VERSION,
            "db_name": self.db_name
        }
        res = requests.get(url, headers=self.headers, params=params, timeout=10)
        if res.status_code == 200:
            return res.json()
        elif res.status_code == 401:
            print(f"[!] Unauthorized: Secret Token in bridge_config.json does not match Hostinger settings!", flush=True)
        return {"status": "error"}

    def execute_query(self, query_sql: str) -> Tuple[bool, List[str], List[Dict[str, Any]], float, str]:
        """Execute query on local MSSQL server"""
        start_time = time.time()
        try:
            clean_sql = html.unescape(query_sql).strip()
            conn = get_mssql_connection(self.config)
            cursor = conn.cursor()
            cursor.execute(clean_sql)

            # Check if query returned a recordset
            if cursor.description is None:
                conn.close()
                elapsed = round(time.time() - start_time, 3)
                return True, [], [], elapsed, ""

            columns = [column[0] for column in cursor.description]
            raw_rows = cursor.fetchall()
            conn.close()

            rows = []
            for r in raw_rows:
                row_dict = {}
                for idx, col in enumerate(columns):
                    val = r[idx]
                    if isinstance(val, (datetime.date, datetime.datetime)):
                        row_dict[col] = val.isoformat()
                    elif isinstance(val, datetime.time):
                        row_dict[col] = val.strftime("%H:%M:%S")
                    elif isinstance(val, decimal.Decimal):
                        row_dict[col] = float(val)
                    elif isinstance(val, (bytes, bytearray)):
                        row_dict[col] = val.hex()
                    else:
                        row_dict[col] = val
                rows.append(row_dict)

            elapsed = round(time.time() - start_time, 3)
            return True, columns, rows, elapsed, ""

        except Exception as e:
            elapsed = round(time.time() - start_time, 3)
            return False, [], [], elapsed, str(e)

    def submit_result(self, job_token: str, is_success: bool, columns: List[str], rows: List[Dict[str, Any]], elapsed: float, error_msg: str):
        """Submit query result back to Hostinger API with chunking support"""
        url = f"{self.base_url}/submit"
        now_str = datetime.datetime.now().strftime("%H:%M:%S")

        if not is_success or len(rows) <= 2500:
            payload = {
                "job_token": job_token,
                "status": "completed" if is_success else "failed",
                "columns": columns,
                "rows": rows,
                "row_count": len(rows),
                "execution_seconds": elapsed,
                "error_message": error_msg,
                "is_chunked": False
            }
            try:
                print(f"[{now_str}] [...] Uploading {len(rows)} rows to Hostinger cloud...", flush=True)
                res = requests.post(url, headers=self.headers, data=json.dumps(payload, default=json_serializer), timeout=120)
                if res.status_code == 200:
                    print(f"[{now_str}] [OK] Results uploaded successfully for job {job_token[:8]} ({len(rows)} rows)", flush=True)
                    return True
                else:
                    print(f"[{now_str}] [!] Submit HTTP {res.status_code}: {res.text}", flush=True)
            except Exception as e:
                print(f"[!] Error submitting result: {e}", flush=True)
            return False

        # Large dataset: upload in chunks of 2500 rows
        chunk_size = 2500
        total_chunks = (len(rows) + chunk_size - 1) // chunk_size
        print(f"[{now_str}] [...] Uploading {len(rows)} rows in {total_chunks} chunks to Hostinger...", flush=True)

        for i in range(total_chunks):
            chunk_rows = rows[i * chunk_size : (i + 1) * chunk_size]
            payload = {
                "job_token": job_token,
                "status": "completed" if (i >= total_chunks - 1) else "in_progress",
                "columns": columns if i == 0 else [],
                "rows": chunk_rows,
                "chunk_index": i,
                "total_chunks": total_chunks,
                "row_count": len(rows),
                "execution_seconds": elapsed,
                "error_message": "",
                "is_chunked": True
            }
            try:
                res = requests.post(url, headers=self.headers, data=json.dumps(payload, default=json_serializer), timeout=60)
                if res.status_code != 200:
                    print(f"[!] Chunk {i+1}/{total_chunks} failed (HTTP {res.status_code}): {res.text}", flush=True)
                    return False
                print(f"    -> Chunk {i+1}/{total_chunks} uploaded ({len(chunk_rows)} rows)", flush=True)
            except Exception as e:
                print(f"[!] Error uploading chunk {i+1}: {e}", flush=True)
                return False

        print(f"[{now_str}] [OK] All {len(rows)} rows uploaded successfully in {total_chunks} chunks!", flush=True)
        return True

    def perform_auto_sync(self):
        """Perform automatic scheduled background push-sync to InvoFlow"""
        now_str = datetime.datetime.now().strftime("%H:%M:%S")
        mode_desc = f"Incremental (Last {self.incremental_days} Days)" if self.sync_mode == "incremental" else "Full Year"
        print(f"\n[{now_str}] [AUTO-SYNC] Running scheduled background sales sync ({mode_desc})...", flush=True)

        query = build_auto_sync_query(self.sync_mode, self.incremental_days)
        success, columns, rows, elapsed, error = self.execute_query(query)
        if not success:
            print(f"[{now_str}] [AUTO-SYNC ERROR] MSSQL query failed: {error}", flush=True)
            return False

        print(f"[{now_str}] [AUTO-SYNC] Fetched {len(rows)} rows from MSSQL in {elapsed}s", flush=True)

        if len(rows) == 0:
            print(f"[{now_str}] [AUTO-SYNC] No new/modified records in this window. Sync complete.", flush=True)
            return True

        url = f"{self.base_url}/push-sync"
        chunk_size = 2500
        total_chunks = (len(rows) + chunk_size - 1) // chunk_size

        for i in range(total_chunks):
            chunk_rows = rows[i * chunk_size : (i + 1) * chunk_size]
            payload = {
                "target_table": "mssql_sales_records",
                "rows": chunk_rows,
                "sync_mode": self.sync_mode,
                "truncate_old": (self.sync_mode == "full" and i == 0),
                "chunk_index": i,
                "total_chunks": total_chunks
            }
            try:
                res = requests.post(url, headers=self.headers, data=json.dumps(payload, default=json_serializer), timeout=60)
                if res.status_code != 200:
                    print(f"[!] Auto-sync chunk {i+1} failed: {res.text}", flush=True)
                    return False
            except Exception as e:
                print(f"[!] Auto-sync network error on chunk {i+1}: {e}", flush=True)
                return False

        print(f"[{now_str}] [AUTO-SYNC COMPLETE] {len(rows)} records synced automatically into InvoFlow cloud!", flush=True)
        return True

    def run(self):
        """Main Loop"""
        self.print_banner()
        self.test_mssql()

        print("\n[*] Bridge Agent is listening for queries & auto-sync schedules...", flush=True)
        last_heartbeat = 0

        # Perform startup auto-sync if enabled
        if self.auto_sync_enabled and self.auto_sync_cfg.get("sync_on_startup", True):
            try:
                self.perform_auto_sync()
                self.last_auto_sync = time.time()
            except Exception as e:
                print(f"[!] Startup auto-sync error: {e}", flush=True)

        while True:
            try:
                current_time = time.time()

                # Periodic heartbeat every 15 seconds
                if current_time - last_heartbeat > 15:
                    self.send_heartbeat()
                    last_heartbeat = current_time

                # Periodic automated background sync (e.g. every 1 hour)
                if self.auto_sync_enabled and (current_time - self.last_auto_sync > self.sync_interval_seconds):
                    self.perform_auto_sync()
                    self.last_auto_sync = time.time()

                # Poll for on-demand web queries
                poll_resp = self.poll_for_job()
                if poll_resp.get("status") == "success" and poll_resp.get("has_job"):
                    job = poll_resp.get("job", {})
                    job_id = job.get("id")
                    job_token = job.get("job_token")
                    query_sql = job.get("query_sql", "")

                    now_str = datetime.datetime.now().strftime("%H:%M:%S")
                    print(f"\n[{now_str}] [JOB #{job_id}] Received query from Web UI:", flush=True)
                    print("-" * 50, flush=True)
                    print(query_sql.strip()[:200] + ("..." if len(query_sql) > 200 else ""), flush=True)
                    print("-" * 50, flush=True)

                    # Execute query
                    success, columns, rows, elapsed, error = self.execute_query(query_sql)

                    if success:
                        print(f"[{now_str}] [OK] MSSQL execution finished in {elapsed}s ({len(rows)} rows fetched)", flush=True)
                    else:
                        print(f"[{now_str}] [ERROR] MSSQL execution FAILED ({elapsed}s): {error}", flush=True)

                    # Submit back to cloud
                    self.submit_result(job_token, success, columns, rows, elapsed, error)

                time.sleep(self.poll_interval)

            except KeyboardInterrupt:
                print("\n[!] Bridge Agent stopped by user.", flush=True)
                break
            except Exception as e:
                print(f"[!] Loop error: {e}. Retrying in {self.poll_interval}s...", flush=True)
                time.sleep(self.poll_interval)


if __name__ == "__main__":
    agent = InvoFlowBridge()
    agent.run()

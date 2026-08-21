"""
InvoFlow Direct Historical FY Sales Sync Script
Directly pushes FY 2024-2025, FY 2025-2026, or FY 2026-2027 from local MSSQL to Hostinger InvoFlow MySQL in fast HTTP chunks.
"""
import sys
import os
import time
import datetime
import json
import requests
import pyodbc

CONFIG_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "bridge_config.json")

def load_config():
    with open(CONFIG_FILE, "r", encoding="utf-8") as f:
        return json.load(f)

def get_mssql_connection(cfg):
    mssql = cfg.get("mssql", {})
    server = mssql.get("server", "localhost")
    database = mssql.get("database", "LOGICDBSY")
    driver = mssql.get("driver", "{ODBC Driver 17 for SQL Server}")
    if mssql.get("use_windows_auth", True):
        conn_str = f"DRIVER={driver};SERVER={server};DATABASE={database};Trusted_Connection=yes;TrustServerCertificate=yes;"
    else:
        conn_str = f"DRIVER={driver};SERVER={server};DATABASE={database};UID={mssql.get('username','sa')};PWD={mssql.get('password','')};TrustServerCertificate=yes;"
    return pyodbc.connect(conn_str)

def get_query_for_year(year_code: str) -> str:
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
FROM Sl_Txn{year_code} AS TXN
INNER JOIN Sl_Head{year_code} AS HD ON TXN.vouch_code = HD.vouch_code AND HD.Deleted = 0
INNER JOIN Bill_Ser AS BS ON HD.Series_Code = BS.Series_Code
LEFT JOIN It_Mst_Det AS IMD ON TXN.Item_Det_Code = IMD.Item_Det_Code
LEFT JOIN It_Mst_Hd AS IMH ON IMD.Item_Hd_Code = IMH.Item_Hd_Code
LEFT JOIN Group_Mst AS GM1 ON IMH.Group_Code = GM1.Group_Code
LEFT JOIN Accounts AS ACT ON HD.cust_code = ACT.act_code
LEFT JOIN Branch_Mst AS BM ON HD.Branch_Code = BM.Branch_Code
WHERE BS.Stock_Trans = 0
  AND BS.type IN ('SL', 'SR')
  AND BS.series IN ('AMSR', 'AKSL', 'AKCS', 'AKLF', 'PNSL', 'PNCS', 'PNF', 'SPSR', 'MPSL', 'MPCS', 'SMSR', 'UPSL', 'UPCS', 'SWSR', 'MHSL', 'LKS', 'LKR', 'SWAK', 'SWPN', 'SWMP', 'SWUP')
ORDER BY HD.vouch_date DESC;
"""

def json_serializer(obj):
    if isinstance(obj, (datetime.datetime, datetime.date)):
        return obj.isoformat()
    if isinstance(obj, bytes):
        return obj.decode("utf-8", errors="replace")
    return str(obj)

def sync_year(year_code: str, year_label: str):
    cfg = load_config()
    cloud_url = cfg["cloud_api"]["base_url"] + "/push-sync"
    token = cfg["cloud_api"]["secret_token"]
    headers = {
        "X-Bridge-Token": token,
        "User-Agent": "InvoFlow-Historical-Sync/1.0",
        "Content-Type": "application/json"
    }

    print(f"\n=======================================================")
    print(f"[*] Starting Direct Sync for: {year_label} (Sl_Txn{year_code})")
    print(f"=======================================================")

    t0 = time.time()
    conn = get_mssql_connection(cfg)
    cursor = conn.cursor()

    print("[1/3] Fetching rows from local MSSQL...", flush=True)
    sql = get_query_for_year(year_code)
    cursor.execute(sql)
    columns = [col[0] for col in cursor.description]
    raw_rows = cursor.fetchall()
    conn.close()

    rows = [dict(zip(columns, row)) for row in raw_rows]
    fetch_time = round(time.time() - t0, 2)
    print(f"[OK] Fetched {len(rows)} records in {fetch_time}s!", flush=True)

    if len(rows) == 0:
        print("[!] No records found to sync.")
        return

    chunk_size = 2000
    total_chunks = (len(rows) + chunk_size - 1) // chunk_size
    print(f"[2/3] Uploading {len(rows)} records to Hostinger in {total_chunks} chunks...", flush=True)

    for i in range(total_chunks):
        chunk = rows[i * chunk_size : (i + 1) * chunk_size]
        payload = {
            "target_table": "mssql_sales_records",
            "rows": chunk,
            "sync_mode": "full",
            "truncate_old": (i == 0),
            "chunk_index": i,
            "total_chunks": total_chunks
        }
        res = requests.post(cloud_url, headers=headers, data=json.dumps(payload, default=json_serializer), timeout=90)
        if res.status_code != 200:
            print(f"[!] Chunk {i+1}/{total_chunks} failed (HTTP {res.status_code}): {res.text}", flush=True)
            return False
        percent = round(((i + 1) / total_chunks) * 100)
        print(f"    -> Chunk {i+1}/{total_chunks} uploaded successfully ({len(chunk)} rows) [{percent}%]", flush=True)

    total_time = round(time.time() - t0, 2)
    print(f"[3/3] [COMPLETED] Successfully synced {len(rows)} rows of {year_label} in {total_time}s!\n")
    return True

if __name__ == "__main__":
    years = [
        ("20242025", "FY 2024-2025 (Historical)"),
        ("20252026", "FY 2025-2026 (Historical)"),
        ("20262027", "FY 2026-2027 (Current Year)"),
    ]

    print("=======================================================")
    print("      InvoFlow Direct Sales FY Batch Synchronizer      ")
    print("=======================================================")
    print("1. Sync FY 2024-2025 Only (27,894 rows - Historical)")
    print("2. Sync FY 2025-2026 Only (32,089 rows - Historical)")
    print("3. Sync FY 2026-2027 Only (15,147 rows - Current Year)")
    print("4. Sync ALL 3 Financial Years (75,000+ rows - Total Sync)")
    print("=======================================================")

    choice = sys.argv[1] if len(sys.argv) > 1 else "4"

    if choice == "1":
        sync_year("20242025", "FY 2024-2025")
    elif choice == "2":
        sync_year("20252026", "FY 2025-2026")
    elif choice == "3":
        sync_year("20262027", "FY 2026-2027")
    elif choice == "4":
        for y_code, y_lbl in years:
            sync_year(y_code, y_lbl)
    else:
        print("[!] Invalid option. Use 1, 2, 3, or 4.")

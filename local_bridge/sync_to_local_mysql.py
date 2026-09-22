"""
InvoFlow Local MySQL Direct Sync Script
Directly syncs MSSQL data into LOCAL XAMPP MySQL (for local dev server)
"""
import sys
import os
import time
import datetime
import json
import decimal
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

def get_mysql_connection():
    try:
        import mysql.connector
        conn = mysql.connector.connect(
            host="127.0.0.1",
            port=3306,
            database="inventory_laravel_db",
            user="root",
            password="",
            connection_timeout=10
        )
        return conn
    except ImportError:
        print("[!] mysql-connector-python not found. Installing...")
        os.system(f"{sys.executable} -m pip install mysql-connector-python")
        import mysql.connector
        conn = mysql.connector.connect(
            host="127.0.0.1",
            port=3306,
            database="inventory_laravel_db",
            user="root",
            password="",
            connection_timeout=10
        )
        return conn

def json_serializer(obj):
    if isinstance(obj, (datetime.datetime, datetime.date)):
        return obj.isoformat()
    if isinstance(obj, decimal.Decimal):
        return float(obj)
    if isinstance(obj, bytes):
        return obj.decode("utf-8", errors="replace")
    return str(obj)

RETURN_SERIES = {'AMSR', 'ISCR', 'SPSR', 'SPCN', 'MSR', 'MDS', 'SWSR', 'LKR'}

def get_query(year_code):
    return f"""
SELECT 
    BM.branch_name,
    HD.vouch_date,
    HD.Vouch_Time,
    HD.vouch_num,
    BS.series,
    ACT.act_name,
    TXN.item_det_code,
    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' OR BS.series IN ('AMSR', 'ISCR', 'SPSR', 'SPCN', 'MSR', 'MDS', 'SWSR', 'LKR') THEN TXN.Tot_Qty * -1 ELSE TXN.Tot_Qty END AS tot_qty,
    CASE WHEN TXN.sale_or_sr = 'SR' OR BS.type = 'SR' OR BS.series IN ('AMSR', 'ISCR', 'SPSR', 'SPCN', 'MSR', 'MDS', 'SWSR', 'LKR') THEN TXN.Calc_Net_Amt * -1 ELSE TXN.Calc_Net_Amt END AS calc_net_amt_n,
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
WHERE BS.series IN ('AMSR', 'ISCR', 'AKST', 'AKSL', 'AKCS', 'AKLF', 'PNSL', 'PNCS', 'PNF', 'SPSR', 'SPCN', 'SWPN', 'MPSL', 'MPCS', 'MPST', 'MSR', 'MDS', 'UPSL', 'UPCS', 'UPST', 'SWSR', 'MHSL', 'MHST', 'LKN', 'LKR')
ORDER BY HD.vouch_date;
"""

def sync_to_local_mysql(year_code, year_label):
    cfg = load_config()
    
    start_yr = year_code[:4]
    end_yr = year_code[4:]
    date_from = f"{start_yr}-04-01"
    date_to = f"{end_yr}-03-31"

    print(f"\n=======================================================")
    print(f"[*] Syncing {year_label} -> LOCAL XAMPP MySQL")
    print(f"    Date Range: {date_from} to {date_to}")
    print(f"=======================================================")

    t0 = time.time()
    
    # Fetch from MSSQL
    print("[1/3] Fetching from local MSSQL...", flush=True)
    mssql_conn = get_mssql_connection(cfg)
    cursor = mssql_conn.cursor()
    cursor.execute(get_query(year_code))
    columns = [col[0].lower() for col in cursor.description]
    raw_rows = cursor.fetchall()
    mssql_conn.close()
    
    rows = []
    now_ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    for row in raw_rows:
        d = dict(zip(columns, row))
        # Convert date
        if d.get('vouch_date') and hasattr(d['vouch_date'], 'date'):
            d['vouch_date'] = d['vouch_date'].date().isoformat()
        elif d.get('vouch_date'):
            d['vouch_date'] = str(d['vouch_date'])[:10]
        # Convert time
        if d.get('vouch_time') and hasattr(d['vouch_time'], 'strftime'):
            d['vouch_time'] = str(d['vouch_time'])
        # Convert decimals
        for k, v in d.items():
            if isinstance(v, decimal.Decimal):
                d[k] = float(v)
        d['created_at'] = now_ts
        d['updated_at'] = now_ts
        rows.append(d)
    
    print(f"[OK] Fetched {len(rows)} records in {round(time.time()-t0, 2)}s!", flush=True)

    if not rows:
        print("[!] No records found.")
        return

    # Connect to local MySQL
    print("[2/3] Connecting to LOCAL MySQL (XAMPP)...", flush=True)
    mysql_conn = get_mysql_connection()
    mysql_cursor = mysql_conn.cursor()
    
    # Delete existing range
    print(f"     Deleting existing records for {date_from} to {date_to}...", flush=True)
    mysql_cursor.execute(
        "DELETE FROM mssql_sales_records WHERE vouch_date BETWEEN %s AND %s",
        (date_from, date_to)
    )
    mysql_conn.commit()
    deleted = mysql_cursor.rowcount
    print(f"     Deleted {deleted} old records.", flush=True)
    
    # Insert in chunks
    chunk_size = 500
    total_chunks = (len(rows) + chunk_size - 1) // chunk_size
    print(f"[3/3] Inserting {len(rows)} records in {total_chunks} chunks...", flush=True)
    
    insert_sql = """INSERT INTO mssql_sales_records 
        (branch_name, vouch_date, vouch_num, series, act_name, item_det_code, 
         tot_qty, calc_net_amt_n, free_qty, rate, calc_tax_1, discount_rs, 
         user_code, item_hd_name, group_name, branch_code, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    
    inserted = 0
    for i in range(total_chunks):
        chunk = rows[i * chunk_size : (i+1) * chunk_size]
        values = [
            (
                r.get('branch_name'), r.get('vouch_date'), r.get('vouch_num'),
                r.get('series'), r.get('act_name'), str(r.get('item_det_code', '')),
                r.get('tot_qty', 0), r.get('calc_net_amt_n', 0), r.get('free_qty', 0),
                r.get('rate', 0), r.get('calc_tax_1', 0), r.get('discount_rs', 0),
                str(r.get('user_code', '')), r.get('item_hd_name'), r.get('group_name'),
                str(r.get('branch_code', '')),
                r.get('created_at'), r.get('updated_at')
            )
            for r in chunk
        ]
        mysql_cursor.executemany(insert_sql, values)
        mysql_conn.commit()
        inserted += len(chunk)
        pct = round(inserted / len(rows) * 100)
        print(f"    -> Chunk {i+1}/{total_chunks} inserted ({len(chunk)} rows) [{pct}%]", flush=True)
    
    mysql_cursor.close()
    mysql_conn.close()
    
    total_time = round(time.time() - t0, 2)
    print(f"\n[DONE] Successfully synced {inserted} rows of {year_label} to LOCAL MySQL in {total_time}s!\n")

if __name__ == "__main__":
    years = [
        ("20242025", "FY 2024-2025"),
        ("20252026", "FY 2025-2026"),
        ("20262027", "FY 2026-2027"),
    ]

    print("=======================================================")
    print("   InvoFlow LOCAL MySQL Sync (XAMPP Dev Server)        ")
    print("=======================================================")
    print("1. Sync FY 2024-2025 Only")
    print("2. Sync FY 2025-2026 Only")
    print("3. Sync FY 2026-2027 Only (Current Year)")
    print("4. Sync ALL 3 Financial Years")
    print("=======================================================")

    choice = sys.argv[1] if len(sys.argv) > 1 else "3"

    if choice == "1":
        sync_to_local_mysql("20242025", "FY 2024-2025")
    elif choice == "2":
        sync_to_local_mysql("20252026", "FY 2025-2026")
    elif choice == "3":
        sync_to_local_mysql("20262027", "FY 2026-2027")
    elif choice == "4":
        for y_code, y_lbl in years:
            sync_to_local_mysql(y_code, y_lbl)
    else:
        print(f"Invalid choice: {choice}")

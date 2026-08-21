# ⚡ InvoFlow Local MSSQL Bridge Agent (Python Standalone)

Ye script aapke **Local PC ke MSSQL Server** ko **Hostinger Live Website** ke sath securely connect karta hai.
Iske liye **XAMPP ya PHP web server ki koi zaroorat nahi hai**.

---

## 🚀 Quick Setup (Sirf 2 Minute Ka Kaam):

### Step 1: Config File Edit Karein
Open karein `bridge_config.json`:
```json
{
    "mssql": {
        "server": "localhost\\SQLEXPRESS",
        "database": "BUSY_DATA",
        "use_windows_auth": true,
        "username": "sa",
        "password": "your_password",
        "driver": "{ODBC Driver 17 for SQL Server}"
    },
    "cloud_api": {
        "base_url": "https://invoflow.gloswebdev.in/api/v1/bridge",
        "secret_token": "invoflow_bridge_key_2026",
        "poll_interval_seconds": 2
    }
}
```
* **`server`**: Apna SQL Server instance name (e.g. `localhost\SQLEXPRESS` ya `.` ya `127.0.0.1`).
* **`database`**: Apne ERP / Busy / Tally database ka naam.
* **`use_windows_auth`**: Agar Windows login use karte ho toh `true` rakhein, agar SQL SA user ho toh `false` karke `username` aur `password` daalein.
* **`base_url`**: Aapki live website ka Bridge API URL (`https://invoflow.gloswebdev.in/api/v1/bridge`).
* **`secret_token`**: InvoFlow me Bridge Settings wala token.

---

### Step 2: Agent Start Karein

Aapke paas 2 options hain:

#### Option A: 1-Click Windows Task Scheduler (Recommended - Auto-Start on PC Boot)
1. **`install_as_startup_task.bat`** par **Right Click > Run as Administrator** karein.
2. Bas! Agent Windows Task Scheduler me register ho jayega aur background me silently chalna shuru ho jayega.
3. PC restart hone par bhi apne aap start ho jayega.

#### Option B: Manual Testing Console
* **`start_bridge.bat`** par double click karein. Console window me live queries aur logs dikhayi denge.

---

## 🛠️ Requirements:
* **Python 3.9+** (https://www.python.org/downloads/ - Install karte waqt *"Add Python to PATH"* checkbox tick karein).
* Windows ODBC Driver for SQL Server (Windows me by default hota hai).

---

## 🗑️ Stop / Remove Task:
Agar kabhi auto-start hatana ho toh **`uninstall_startup_task.bat`** par **Right Click > Run as Administrator** karein.

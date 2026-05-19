# 🟠 Hostinger Par InvoFlow Deploy Karna
### Step-by-Step Complete Guide (hPanel + FTP)

---

## 🔰 Pehle Ye Check Karo (Hostinger hPanel Mein)

**hPanel → Hosting → Manage → Advanced → PHP Configuration**
- PHP Version: **8.2** select karo ✅
- Extensions ON karo: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `xml`

---

## 📦 STEP 1 — Local PC Par Zip Banao

Do alag zip files banani hain:

### Zip 1: `invoflow-app.zip` (Project files — public ke bina)
Ye folders/files include karo:
```
app/
bootstrap/
config/
database/
resources/
routes/
storage/
vendor/          ← composer install --no-dev ke baad wala
artisan
composer.json
composer.lock
.htaccess        ← root level ka
```
> ❌ **Include MAT karo**: `node_modules/`, `.git/`, `.env`, `public/`, `check_api.php`, `check_stock.php`

### Zip 2: `invoflow-public.zip` (Sirf public/ folder ka content)
```
index.php
.htaccess        ← public/ folder wala
favicon.ico
manifest.json
offline.html
service-worker.js
app_icon_512.png
icon.png
build/           ← npm run build ka output
```

---

## 🗂️ STEP 2 — Hostinger Par Folder Structure Banana

> [!CAUTION]
> Ye sabse critical step hai! Galat kiya to kuch bhi kaam nahi karega.

**Goal:**
```
/home/u123456789/        ← Hostinger root
├── domains/
│   └── yourdomain.com/
│       └── public_html/     ← Yahan SIRF public/ wala content aayega
│           ├── index.php
│           ├── .htaccess
│           ├── offline.html
│           ├── service-worker.js
│           ├── manifest.json
│           ├── build/
│           └── ...
│
└── invoflow/                ← public_html ke BAHAR — yahan project aayega
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── vendor/
    ├── storage/
    └── ...
```

### Kaise banayenge?
1. **hPanel → File Manager** kholo
2. `/home/u123456789/` pe jao (root par — `public_html` ke bahar)
3. **New Folder** banao → naam rakho `invoflow`

---

## 📤 STEP 3 — Files Upload Karo

### Part A — `invoflow/` folder mein:
1. File Manager mein `invoflow/` folder kholo
2. **Upload** → `invoflow-app.zip` upload karo
3. Zip par right-click → **Extract**

### Part B — `public_html/` mein:
1. `public_html/` folder kholo
2. **Upload** → `invoflow-public.zip` upload karo
3. Zip par right-click → **Extract**

> [!TIP]
> Agar FTP use karna chahte ho to **hPanel → FTP Accounts** se credentials lo aur FileZilla use karo. FileZilla fast hai bade files ke liye.

---

## ✏️ STEP 4 — index.php Ka Path Fix Karo

**File Manager → `public_html/index.php`** → Edit button dabao

Ye 2 lines dhundho aur badlo:

```php
// PEHLE (ye local wali lines hain):
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// BAAD MEIN (ye Hostinger wali lines hongi):
require __DIR__.'/../invoflow/vendor/autoload.php';
$app = require_once __DIR__.'/../invoflow/bootstrap/app.php';
```

**Save** karo.

---

## 🗄️ STEP 5 — Database Setup

### 5.1 — Database + User Banao:
1. **hPanel → Databases → MySQL Databases**
2. **Create Database** → naam: `invoflow_db`
3. **Create User** → naam: `invoflow_user`, password: (strong password note karo)
4. **Add User to Database** → `invoflow_user` + `invoflow_db` → **All Privileges**

### 5.2 — SQL Import Karo:
1. **hPanel → phpMyAdmin** → Login
2. Left side mein `u123456789_invoflow_db` select karo
3. **Import tab** → Choose File → `inventory_laravel_db.sql` select karo
4. **Go** button dabao → Wait karo

> [!NOTE]
> File size limit usually 50MB hoti hai phpMyAdmin mein. Tumhari SQL file ~580KB hai — bilkul fit hai ✅

---

## 🔧 STEP 6 — .env File Banao

**File Manager → `invoflow/` folder → New File → naam: `.env`**

Ye content daalo (apni values se replace karo):

```env
APP_NAME=InvoFlow
APP_ENV=production
APP_KEY=base64:uWQX+dPc16mS7ZOHKfRBKDwJ8xIQO4frYe8zRHMzk+o=
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
APP_URL=https://yourdomain.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_invoflow_db
DB_USERNAME=u123456789_invoflow_user
DB_PASSWORD=your_database_password_here

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file
CACHE_PREFIX=invoflow_

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="InvoFlow"
```

> [!WARNING]
> `DB_DATABASE`, `DB_USERNAME` mein Hostinger automatically prefix lagata hai (e.g., `u123456789_`). hPanel mein exact naam copy karo!

---

## 🔐 STEP 7 — Storage Permissions Fix Karo

**File Manager → `invoflow/storage/`** → Right click → **Permissions**
- Set to: `755` ✅

**`invoflow/bootstrap/cache/`** → Right click → **Permissions**  
- Set to: `755` ✅

---

## 🔗 STEP 8 — Storage Link (File Uploads Ke Liye)

**hPanel → Advanced → SSH Access** → Enable karo (free hai)

SSH se connect karo aur run karo:
```bash
cd ~/invoflow
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**SSH nahi milta?** Temporary PHP file banao:

`public_html/` mein `setup.php` banao:
```php
<?php
// TEMPORARY FILE — use ke baad delete karo!
$target = dirname(__DIR__) . '/invoflow/storage/app/public';
$link   = __DIR__ . '/storage';

if (!file_exists($link)) {
    symlink($target, $link);
    echo "✅ Storage linked successfully!";
} else {
    echo "ℹ️ Storage link already exists.";
}
echo "<br><strong>Ab is file ko DELETE karo!</strong>";
```

Browser mein open karo: `https://yourdomain.com/setup.php`  
Kaam ke baad **file delete karo** immediately!

---

## ✅ STEP 9 — SSL Enable Karo (Free)

1. **hPanel → Security → SSL/TLS**
2. **Install SSL** → **Lifetime Free SSL** select karo
3. **Force HTTPS** toggle ON karo

`.env` mein update karo:
```env
APP_URL=https://yourdomain.com
```

---

## 🧪 STEP 10 — Test Karo

Browser mein kholo:
```
✅ https://yourdomain.com          → Login page aana chahiye
✅ https://yourdomain.com/mobile   → Mobile dashboard
✅ https://yourdomain.com/offline.html → Offline page preview
```

---

## 🆘 Hostinger Common Errors & Fixes

| Error | Cause | Fix |
|---|---|---|
| **500 Error** | `.env` missing ya wrong path | `.env` check karo `invoflow/` mein |
| **White Screen** | index.php path galat | `../invoflow/vendor/autoload.php` fix karo |
| **Database Error** | DB credentials galat | hPanel se exact DB name copy karo (prefix sahi karo) |
| **404 on all routes** | `.htaccess` missing | `public_html/.htaccess` upload karo |
| **Permission Denied** | Storage not writable | `storage/` ko `755` karo |
| **Class not found** | vendor/ upload adhura | Dobara zip extract karo |
| **APP_KEY error** | `.env` mein key nahi | Wahi key rakho jo local `.env` mein hai |

---

## 📞 Agar Kuch Problem Aaye

1. `APP_DEBUG=true` temporarily karo → error detail dikhega
2. **`invoflow/storage/logs/laravel.log`** dekho — asli error wahan hota hai
3. **hPanel → Error Logs** section bhi check karo
4. Problem solve hone ke baad `APP_DEBUG=false` ZAROOR karo

---

## 📋 Quick Checklist

```
□ PHP 8.2 set hai hPanel mein
□ invoflow/ folder public_html ke BAHAR hai
□ public_html/ mein sirf public/ ka content hai
□ index.php mein paths fix kiye (../invoflow/...)
□ .env banaya invoflow/ mein
□ DB_DATABASE aur DB_USERNAME mein Hostinger prefix hai
□ SQL import kiya phpMyAdmin se
□ storage/ permissions 755 hain
□ SSL enabled hai
□ APP_DEBUG=false hai
```

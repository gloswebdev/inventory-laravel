# 🚀 InvoFlow — Shared Hosting Par Deploy Kaise Karein
### (Hostinger / cPanel / Any Shared Hosting)

---

## ⚙️ Requirements (Pehle Check Karo)
| Cheez | Minimum |
|---|---|
| PHP Version | **8.2+** |
| MySQL | **5.7+** |
| Extensions | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `fileinfo` |
| File Manager / FTP | ✅ Available hona chahiye |
| SSH Access | Optional but helpful |

> [!IMPORTANT]
> Hostinger par **PHP 8.2** select karo `hPanel → Advanced → PHP Configuration` se.

---

## 📦 STEP 1 — Local Machine Par Build Banao

Ye commands apne PC (XAMPP) par run karo:

```powershell
# 1. Project folder mein jao
cd C:\xampp\htdocs\inventorymanager\inventory-laravel

# 2. Composer install (production only — dev packages skip)
composer install --optimize-autoloader --no-dev

# 3. Vite/Assets build karo
npm install
npm run build

# 4. Config optimize karo
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 📁 STEP 2 — Konsi Files Upload Karni Hain?

**Upload karo (zip banao):**
```
inventory-laravel/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/           ← yahi root_html mein jayega (alag!)
├── resources/
├── routes/
├── storage/
├── vendor/
├── artisan
├── composer.json
└── .htaccess         ← root level ka
```

**Upload MAT karo (ye skip karo):**
```
❌ node_modules/
❌ .git/
❌ .env              ← server par naya banayenge
❌ check_api.php, check_stock.php  (testing files)
```

---

## 🗂️ STEP 3 — Hosting Par Folder Structure

> [!CAUTION]
> Ye sabse important step hai! Galat kiya to site nahi chalegi.

Shared hosting par structure aisa hona chahiye:

```
public_html/            ← Domain root (ye public/ ka content hai)
│   index.php           ← Laravel ka public/index.php
│   .htaccess           ← Laravel ka public/.htaccess
│   service-worker.js
│   manifest.json
│   offline.html
│   app_icon_512.png
│   favicon.ico
│   build/              ← Vite compiled assets
│
invoflow/               ← public_html ke BAHAR (same level par)
│   app/
│   bootstrap/
│   config/
│   database/
│   resources/
│   routes/
│   storage/
│   vendor/
│   artisan
│   .env                ← naya banayenge
```

> [!WARNING]
> `vendor/`, `app/`, `.env` kabhi bhi `public_html/` ke andar mat rakho. Security risk hai!

---

## ✏️ STEP 4 — public/index.php Ka Path Fix Karo

`public_html/index.php` mein ye 2 lines update karo:

```php
// PEHLE (local):
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// BAAD MEIN (hosting par — folder name apna daalo):
require __DIR__.'/../invoflow/vendor/autoload.php';
$app = require_once __DIR__.'/../invoflow/bootstrap/app.php';
```

---

## 🗄️ STEP 5 — Database Setup (cPanel)

1. **cPanel → MySQL Databases** mein jao
2. Naya database banao: `username_invoflow`
3. Naya user banao + password set karo
4. User ko database se assign karo — **All Privileges** do
5. **phpMyAdmin** open karo
6. Database select karo → **Import** tab
7. `inventory_laravel_db.sql` file upload karo → **Go**

---

## 🔧 STEP 6 — .env File Banao (Server Par)

`invoflow/` folder mein `.env` file banao (File Manager se):

```env
APP_NAME=InvoFlow
APP_ENV=production
APP_KEY=base64:uWQX+dPc16mS7ZOHKfRBKDwJ8xIQO4frYe8zRHMzk+o=
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_invoflow
DB_USERNAME=username_dbuser
DB_PASSWORD=your_db_password

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

LOG_CHANNEL=single
LOG_LEVEL=error

FILESYSTEM_DISK=local
```

> [!NOTE]
> `APP_KEY` wahi rakho jo abhi `.env` mein hai — change mat karo, warna sessions expire ho jayenge.

---

## 🔐 STEP 7 — Storage Folder Permissions

SSH available hai to:
```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

SSH nahi hai to File Manager se:
- `storage/` → Permissions: `755` (ya `775`)
- `bootstrap/cache/` → Permissions: `755`

---

## 🔗 STEP 8 — Storage Link (Agar File Upload Use Hoti Hai)

SSH se:
```bash
cd /home/username/invoflow
php artisan storage:link
```

SSH nahi hai to ye PHP script temporarily `public_html/` mein rakho:

```php
<?php
// storage_link.php — use karne ke baad DELETE karo!
$target = '../invoflow/storage/app/public';
$link   = __DIR__ . '/storage';
symlink($target, $link);
echo "Storage linked!";
```

Browser mein open karo → `yourdomain.com/storage_link.php` → fir **file delete karo**.

---

## ✅ STEP 9 — Final Check

```
✅ yourdomain.com              → Login page dikhe
✅ yourdomain.com/mobile       → Mobile dashboard dikhe
✅ APP_DEBUG=false              → .env mein set hai
✅ storage/ writable hai        → Permissions OK
✅ .env server par hai          → invoflow/.env
✅ DB connected                 → Login works
```

---

## 🆘 Common Errors & Fixes

| Error | Fix |
|---|---|
| `500 Internal Server Error` | `APP_DEBUG=true` karo temporarily, error dekho |
| `Class not found` | `composer dump-autoload` run karo |
| `No application encryption key` | `.env` mein `APP_KEY` sahi set karo |
| `SQLSTATE: Access denied` | DB credentials check karo |
| `Storage permission denied` | `storage/` ko `755` karo |
| `Route not found / 404` | `public_html/.htaccess` check karo |
| White screen | `storage/logs/laravel.log` dekho |

---

## 💡 Pro Tips

- **Subdomain use karo**: `app.yourdomain.com` → `public_html/` point karo subdir par — cleaner lagta hai
- **SSL enable karo**: cPanel → SSL/TLS → Free AutoSSL
- **APP_DEBUG=false** production par — users ko error details mat dikhao
- **Cache optimize** karo after deploy (SSH available ho to):
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

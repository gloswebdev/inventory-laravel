# Hostinger Deployment Guide for Laravel

Follow these steps to deploy your Inventory Manager application to Hostinger shared hosting.

## Step 1: Prepare Database
1. Go to Hostinger hPanel -> **MySQL Databases**.
2. Create a new database and user. Note down the **DB_NAME**, **DB_USER**, and **DB_PASSWORD**.
3. Open **phpMyAdmin** for this database.
4. Import your local database file: `inventory_laravel_db (1).sql`.

## Step 2: Prepare Project Files
1. On your local machine, select all files inside the project folder.
2. **Exclude** these to save space and avoid conflicts:
   - `node_modules/`
   - `.git/`
   - `storage/logs/*.log`
3. Create a ZIP file (e.g., `project.zip`).

## Step 3: Upload and Extract
1. Open Hostinger **File Manager**.
2. Upload `project.zip` to the **root** directory (the folder containing `public_html`, not inside it).
3. Extract the ZIP into a folder (e.g., `inventory-laravel`).

## Step 4: Configure Public Folder
1. Go inside your extracted project: `inventory-laravel/public/`.
2. Select **all files** inside it and **Move** them to the main `/public_html/` folder.
3. Open `/public_html/index.php` and update the paths to point to your project folder:

```php
// Around line 14:
require __DIR__.'/../inventory-laravel/vendor/autoload.php';

// Around line 18:
$app = require_once __DIR__.'/../inventory-laravel/bootstrap/app.php';
```

## Step 5: Update Environment Variables
1. Open `/inventory-laravel/.env` and set the following:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u123456789_db_name
DB_USERNAME=u123456789_db_user
DB_PASSWORD=your_secure_password
```

## Step 6: Set Permissions
1. In File Manager, right-click the following folders inside `inventory-laravel/`:
   - `storage`
   - `bootstrap/cache`
2. Set permissions to **775** (Recursive).

## Step 7: Optimization (If SSH is available)
Run these commands in the Hostinger Terminal:
```bash
cd inventory-laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Important Note:
- Make sure your PHP version on Hostinger matches your local version (8.2+).
- The `APP_KEY` in `.env` must remain the same as your local one for session/cookie security.

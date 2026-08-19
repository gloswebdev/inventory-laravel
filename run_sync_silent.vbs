Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "C:\xampp\php\php.exe C:\xampp\htdocs\inventorymanager\inventory-laravel\artisan mssql:sync-to-cloud", 0, False

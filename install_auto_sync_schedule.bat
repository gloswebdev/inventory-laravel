@echo off
title Setup InvoFlow Auto Sync Background Task
color 0A
echo ================================================================
echo        INVOFLOW: AUTOMATIC BACKGROUND SYNC SETUP
echo ================================================================
echo This will configure Windows Task Scheduler to run MS SQL sync
echo automatically in the background every 15 minutes silently.
echo.
schtasks /create /tn "InvoFlow_Sales_AutoSync" /tr "wscript.exe \"C:\xampp\htdocs\inventorymanager\inventory-laravel\run_sync_silent.vbs\"" /sc minute /mo 15 /f

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ================================================================
    echo [SUCCESS] Auto-sync scheduled successfully every 15 minutes!
    echo It will now run completely SILENT in the background.
    echo ================================================================
) else (
    echo.
    echo [NOTE] If permission error, please Right-Click this file and "Run as administrator".
)
echo.
pause

@echo off
title Uninstall InvoFlow Bridge Task
color 0C
cd /d "%~dp0"

echo ================================================================
echo   Uninstall InvoFlow Bridge from Windows Task Scheduler
echo ================================================================
echo.

net session >nul 2>&1
if %errorLevel% neq 0 (
    powershell -Command "Start-Process '%~f0' -Verb RunAs"
    exit /b
)

set TASK_NAME=InvoFlow_MSSQL_Bridge

echo [*] Removing Scheduled Task: %TASK_NAME%...
schtasks /delete /tn "%TASK_NAME%" /f

if %errorlevel% equ 0 (
    echo.
    echo [✓] Successfully removed %TASK_NAME% from Task Scheduler.
) else (
    echo [!] Task was not found or already removed.
)

echo.
pause

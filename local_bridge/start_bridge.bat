@echo off
title InvoFlow MSSQL Bridge Agent
color 0A
cd /d "%~dp0"

echo =======================================================
echo   InvoFlow Local MSSQL Bridge Agent
echo =======================================================
echo.

where python >nul 2>nul
if %errorlevel% neq 0 (
    color 0C
    echo [ERROR] Python is not installed or not in PATH!
    echo Please install Python 3.9+ from https://www.python.org/
    echo Make sure to check "Add Python to PATH" during installation.
    echo.
    pause
    exit /b
)

echo [*] Checking Python dependencies...
python -m pip install -q -r requirements.txt
if %errorlevel% neq 0 (
    echo [!] Pip install had warnings/errors. Attempting to run bridge anyway...
)

echo [*] Starting InvoFlow Bridge Agent...
echo.
python invoflow_bridge.py

pause

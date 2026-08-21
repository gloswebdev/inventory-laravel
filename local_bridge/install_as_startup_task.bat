@echo off
setlocal EnableDelayedExpansion
title Install InvoFlow Bridge Task
cd /d "%~dp0"

echo ================================================================
echo   InvoFlow Bridge - Windows Task Scheduler Auto-Start Setup
echo ================================================================
echo.

set "PYTHON_EXE=C:\Python314\pythonw.exe"
if not exist "!PYTHON_EXE!" (
    for /f "delims=" %%i in ('where pythonw 2^>nul') do set "PYTHON_EXE=%%i"
)
if not exist "!PYTHON_EXE!" (
    for /f "delims=" %%i in ('where python 2^>nul') do set "PYTHON_EXE=%%i"
)

echo [*] Python Executable: !PYTHON_EXE!
echo [*] Script Path:       %~dp0invoflow_bridge.py
echo.

set "TASK_NAME=InvoFlow_MSSQL_Bridge"
set "SCRIPT_PATH=%~dp0invoflow_bridge.py"

echo [*] Registering Windows Scheduled Task: !TASK_NAME!...
schtasks /delete /tn "!TASK_NAME!" /f >nul 2>&1
schtasks /create /tn "!TASK_NAME!" /tr "\"!PYTHON_EXE!\" \"!SCRIPT_PATH!\"" /sc onlogon /rl highest /f

if !errorlevel! equ 0 (
    echo.
    echo ================================================================
    echo   [SUCCESS] Task successfully registered in Task Scheduler!
    echo   - Auto-starts silently on PC Startup / Login.
    echo   - Runs 100%% hidden in background (no black window).
    echo ================================================================
    echo.
    schtasks /run /tn "!TASK_NAME!" >nul 2>&1
    echo [*] Bridge Agent is now RUNNING in background!
) else (
    echo [ERROR] Failed to create task. Error code: !errorlevel!
)

echo.
pause

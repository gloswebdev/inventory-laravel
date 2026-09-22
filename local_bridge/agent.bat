@echo off
REM ---------------------------------------------------------------------------
REM  InvoFlow Sync Agent - manual controls
REM
REM    agent                     interactive menu
REM    agent status              watermarks, targets, recent runs
REM    agent test                connectivity check
REM    agent sync                fetch new/changed bills now
REM    agent sync --target local only touch the local XAMPP MySQL
REM    agent sync --full         re-pull the whole financial year
REM    agent reconcile           verify recent days and repair differences
REM    agent bootstrap           first-time rebuild of a target
REM ---------------------------------------------------------------------------
setlocal
cd /d "%~dp0"

where python >nul 2>&1
if errorlevel 1 (
    echo [!] python not found on PATH.
    exit /b 1
)

if not "%~1"=="" (
    python invoflow_agent.py %*
    exit /b %errorlevel%
)

:menu
cls
echo ================================================================
echo             INVOFLOW SYNC AGENT - MANUAL CONTROL
echo ================================================================
echo.
echo   1. Status              - what is synced where
echo   2. Test connections    - ERP + local MySQL + cloud
echo   3. Fetch now           - both targets, new/changed bills
echo   4. Fetch now (LOCAL)   - local XAMPP MySQL only, for testing
echo   5. Fetch now (CLOUD)   - live site only
echo   6. Reconcile + repair  - verify recent days against the ERP
echo   7. Full re-pull        - whole financial year, both targets
echo   8. Bootstrap           - rebuild a target from scratch
echo   0. Exit
echo.
set /p choice="Choose: "

if "%choice%"=="1" (python invoflow_agent.py status & pause & goto menu)
if "%choice%"=="2" (python invoflow_agent.py test & pause & goto menu)
if "%choice%"=="3" (python invoflow_agent.py sync & pause & goto menu)
if "%choice%"=="4" (python invoflow_agent.py sync --target local & pause & goto menu)
if "%choice%"=="5" (python invoflow_agent.py sync --target cloud & pause & goto menu)
if "%choice%"=="6" (python invoflow_agent.py reconcile & pause & goto menu)
if "%choice%"=="7" (python invoflow_agent.py sync --full & pause & goto menu)
if "%choice%"=="8" (python invoflow_agent.py bootstrap & pause & goto menu)
if "%choice%"=="0" exit /b 0
goto menu

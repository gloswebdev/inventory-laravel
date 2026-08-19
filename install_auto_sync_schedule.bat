@echo off
title Setup InvoFlow Daily Auto Sync (02:00 AM)
color 0A
echo ================================================================
echo        INVOFLOW: DAILY NIGHT AUTO-SYNC SETUP (02:00 AM)
echo ================================================================
echo Setting up Windows Task Scheduler to run MS SQL sync every night at 02:00 AM...
echo.
powershell -ExecutionPolicy Bypass -File "%~dp0setup_daily_task.ps1"
echo.
pause

@echo off
title InvoFlow Sync Agent - install always-on service
echo ================================================================
echo   Installing the InvoFlow Sync Agent as an always-on task
echo ================================================================
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup_agent_task.ps1"
echo.
pause

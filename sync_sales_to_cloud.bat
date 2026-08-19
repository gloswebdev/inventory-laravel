@echo off
title InvoFlow - MS SQL Sales Cloud Sync Agent
color 0A
echo ================================================================
echo           INVOFLOW: MS SQL TO CLOUD AUTO-SYNC AGENT
echo ================================================================
echo Connecting to MS SQL (Tailscale 100.108.74.58) and syncing to
echo Cloud Website: https://invoflow.gloswebdev.in ...
echo.
C:\xampp\php\php.exe artisan mssql:sync-to-cloud
echo.
echo ================================================================
echo Sync finished at %DATE% %TIME%
echo ================================================================
timeout /t 5

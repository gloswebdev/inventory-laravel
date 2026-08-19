@echo off
title Remove InvoFlow Auto Sync Task
echo ================================================================
echo        REMOVE INVOFLOW AUTOMATIC BACKGROUND SYNC
echo ================================================================
schtasks /delete /tn "InvoFlow_Sales_AutoSync" /f
echo.
echo Background task removed.
pause

@echo off
title InvoFlow Sync Agent - remove always-on service
powershell -NoProfile -ExecutionPolicy Bypass -Command "if (Get-ScheduledTask -TaskName 'InvoFlow_Sync_Agent' -ErrorAction SilentlyContinue) { Stop-ScheduledTask -TaskName 'InvoFlow_Sync_Agent' -ErrorAction SilentlyContinue; Unregister-ScheduledTask -TaskName 'InvoFlow_Sync_Agent' -Confirm:$false; Write-Host 'Removed.' } else { Write-Host 'Task not installed.' }"
pause

$action = New-ScheduledTaskAction -Execute "wscript.exe" -Argument "C:\xampp\htdocs\inventorymanager\inventory-laravel\run_sync_silent.vbs"
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
Register-ScheduledTask -TaskName "InvoFlow_Sales_AutoSync" -Action $action -Trigger $trigger -Description "InvoFlow MS SQL to Cloud Sales Auto-Sync" -Force
Write-Host "InvoFlow Daily Sales Auto-Sync scheduled successfully at 02:00 AM!"

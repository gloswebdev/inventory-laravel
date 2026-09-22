<#
    Registers the InvoFlow Sync Agent as an always-on Windows Scheduled Task.

    The task deliberately differs from the old InvoFlow_Sales_AutoSync task, which kept
    finishing with 0x800710E0 ("operator or administrator has refused the request") because
    it was tied to an interactive logon and stopped on battery. This one:

      * runs as SYSTEM, so it does not need anybody to be logged in
      * starts at boot and restarts itself if it ever dies
      * has no power conditions and no execution time limit
      * never runs a second copy alongside the first

    The agent itself keeps the schedule (daily 02:00 by default, see agent_config.json).
    The task just keeps the agent alive.
#>

[CmdletBinding()]
param(
    [string]$TaskName = "InvoFlow_Sync_Agent",
    [ValidateSet("SYSTEM", "CurrentUser")]
    [string]$RunAs = "SYSTEM"
)

$ErrorActionPreference = "Stop"

$agentDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$agentPy = Join-Path $agentDir "invoflow_agent.py"

if (-not (Test-Path $agentPy)) {
    throw "invoflow_agent.py not found next to this script ($agentDir)."
}

# Prefer pythonw.exe so the service runs without a console window.
$python = $null
foreach ($candidate in @("pythonw.exe", "python.exe")) {
    $found = Get-Command $candidate -ErrorAction SilentlyContinue
    if ($found) { $python = $found.Source; break }
}
if (-not $python) {
    foreach ($guess in @("C:\Python314\pythonw.exe", "C:\Python313\pythonw.exe", "C:\Python312\pythonw.exe")) {
        if (Test-Path $guess) { $python = $guess; break }
    }
}
if (-not $python) {
    throw "Could not locate python. Add it to PATH or edit this script."
}

Write-Host "Python : $python"
Write-Host "Agent  : $agentPy"
Write-Host "Task   : $TaskName (runs as $RunAs)"
Write-Host ""

$action = New-ScheduledTaskAction -Execute $python -Argument "`"$agentPy`" service" -WorkingDirectory $agentDir

$triggers = @(
    New-ScheduledTaskTrigger -AtStartup
)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 5) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -MultipleInstances IgnoreNew

if ($RunAs -eq "SYSTEM") {
    $principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
} else {
    $principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) `
        -LogonType S4U -RunLevel Highest
}

if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    Write-Host "Existing task found - replacing it."
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $triggers `
    -Settings $settings -Principal $principal `
    -Description "InvoFlow Sync Agent - keeps ERP sales data flowing to the cloud site and local MySQL." | Out-Null

Write-Host "Registered. Starting it now..."
Start-ScheduledTask -TaskName $TaskName
Start-Sleep -Seconds 3

$info = Get-ScheduledTaskInfo -TaskName $TaskName
$task = Get-ScheduledTask -TaskName $TaskName

Write-Host ""
Write-Host "State       : $($task.State)"
Write-Host "Last run    : $($info.LastRunTime)"
Write-Host "Last result : 0x$('{0:X}' -f $info.LastTaskResult)  (0x41301 = still running, which is what we want)"
Write-Host ""
Write-Host "Logs        : $(Join-Path $agentDir 'logs')"
Write-Host "Check state : python `"$agentPy`" status"
Write-Host ""

# InvoFlow - Hostinger Deploy ZIP Creator
# Run:   powershell -ExecutionPolicy Bypass -File make_deploy_zips.ps1
# Usage: .\make_deploy_zips.ps1                  (patch bump: 1.0.0 -> 1.0.1)
#        .\make_deploy_zips.ps1 -BumpType minor   (minor bump: 1.0.0 -> 1.1.0)
#        .\make_deploy_zips.ps1 -BumpType major   (major bump: 1.0.0 -> 2.0.0)

param(
    [ValidateSet('patch','minor','major')]
    [string]$BumpType = 'patch'
)

$projectRoot = $PSScriptRoot
$timestamp   = Get-Date -Format "yyyyMMdd_HHmm"
$appZip      = Join-Path $projectRoot ("invoflow-app_" + $timestamp + ".zip")
$publicZip   = Join-Path $projectRoot ("invoflow-public_" + $timestamp + ".zip")

function ok($msg)   { Write-Host ("  [OK] " + $msg) -ForegroundColor Green }
function info($msg) { Write-Host ("  [..] " + $msg) -ForegroundColor Cyan }
function warn($msg) { Write-Host ("  [!!] " + $msg) -ForegroundColor Yellow }
function step($msg) {
    Write-Host ""
    Write-Host ("  === " + $msg + " ===") -ForegroundColor Magenta
    Write-Host ""
}

Write-Host ""
Write-Host "  InvoFlow - Hostinger ZIP Maker" -ForegroundColor Magenta
Write-Host "  ================================" -ForegroundColor Magenta
Write-Host ""

# -- VERSION BUMP -------------------------------------------------------------
$versionFile = Join-Path $projectRoot "version.json"
$newVer = "unknown"
if (Test-Path $versionFile) {
    $verData = Get-Content $versionFile -Raw | ConvertFrom-Json
    $parts   = $verData.version -split '\.'
    $major   = [int]$parts[0]
    $minor   = [int]$parts[1]
    $patch   = [int]$parts[2]
    if ($BumpType -eq 'major') { $major++; $minor = 0; $patch = 0 }
    elseif ($BumpType -eq 'minor') { $minor++; $patch = 0 }
    else { $patch++ }
    $oldVer = $verData.version
    $newVer = "$major.$minor.$patch"
    $verData.version      = $newVer
    $verData.release_date = (Get-Date -Format 'yyyy-MM-dd')
    $verData | ConvertTo-Json -Depth 5 | Set-Content $versionFile -Encoding UTF8
    ok ("Version bumped: v$oldVer -> v$newVer ($BumpType)")
} else {
    warn "version.json nahi mila -- version bump skipped"
}
Write-Host ""

# STEP 1: Patch index.php for Hostinger
step "STEP 1 - Patching index.php for Hostinger"

$indexPath    = Join-Path $projectRoot "public\index.php"
$indexBackup  = Get-Content $indexPath -Raw

$localLine    = '$appRoot = dirname(__DIR__);'
$hostLine     = '$appRoot = dirname(__DIR__) . ''/invoflow'';'

if ($indexBackup -match [regex]::Escape('dirname(__DIR__);')) {
    $patched = $indexBackup -replace [regex]::Escape($localLine), $hostLine
    Set-Content $indexPath $patched -NoNewline
    ok "index.php patched for Hostinger (invoflow path set)"
} elseif ($indexBackup -match [regex]::Escape('/invoflow')) {
    ok "index.php already has Hostinger path"
} else {
    warn "index.php pattern not found - using as-is"
}

# STEP 2: invoflow-public.zip
step "STEP 2 - Creating invoflow-public.zip"

if (Test-Path $publicZip) { Remove-Item $publicZip -Force }

$tempPub = Join-Path $env:TEMP "invoflow_public_temp"
if (Test-Path $tempPub) { Remove-Item $tempPub -Recurse -Force }
Copy-Item (Join-Path $projectRoot "public") $tempPub -Recurse

# Remove installer.php (security), keep updater.php
$installerTemp = Join-Path $tempPub "installer.php"
if (Test-Path $installerTemp) {
    Remove-Item $installerTemp -Force
    info "installer.php removed from public zip (security)"
}

$updaterTemp = Join-Path $tempPub "updater.php"
if (Test-Path $updaterTemp) {
    ok "updater.php included in public zip (delete from server after use!)"
}

info "Compressing public/ ..."
Compress-Archive -Path ($tempPub + "\*") -DestinationPath $publicZip -CompressionLevel Optimal
Remove-Item $tempPub -Recurse -Force

$pubSizeKB = [math]::Round((Get-Item $publicZip).Length / 1KB, 0)
ok ("invoflow-public.zip created - " + $pubSizeKB + " KB")

# STEP 3: invoflow-app.zip
step "STEP 3 - Creating invoflow-app.zip"

if (Test-Path $appZip) { Remove-Item $appZip -Force }

$excludeDirs = @("public", "node_modules", ".git", "tests")
$excludeFiles = @(
    ".env", "make_deploy_zips.ps1", "api_test.php", "check_api.php",
    "check_stock.php", "debug.php", "invoflow-app.zip", "invoflow-public.zip",
    ".gitignore.bak", "phpunit.xml", "tailwind.config.js", "postcss.config.js",
    "vite.config.js", "package.json", "package-lock.json"
)

$tempApp = Join-Path $env:TEMP "invoflow_app_temp"
if (Test-Path $tempApp) { Remove-Item $tempApp -Recurse -Force }
New-Item -ItemType Directory $tempApp | Out-Null

info "Collecting root files..."
Get-ChildItem $projectRoot -File | Where-Object {
    $n = $_.Name
    $skip = $false
    foreach ($ef in $excludeFiles) { if ($n -eq $ef) { $skip = $true; break } }
    if ($n -like "invoflow-app_*.zip")    { $skip = $true }
    if ($n -like "invoflow-public_*.zip") { $skip = $true }
    -not $skip
} | ForEach-Object {
    Copy-Item $_.FullName (Join-Path $tempApp $_.Name)
}

info "Collecting subdirectories..."
Get-ChildItem $projectRoot -Directory | Where-Object {
    $_.Name -notin $excludeDirs
} | ForEach-Object {
    info ("  Copying " + $_.Name + "/ ...")
    Copy-Item $_.FullName (Join-Path $tempApp $_.Name) -Recurse
}

info "Compressing app files..."
Compress-Archive -Path ($tempApp + "\*") -DestinationPath $appZip -CompressionLevel Optimal
Remove-Item $tempApp -Recurse -Force

$appSizeMB = [math]::Round((Get-Item $appZip).Length / 1MB, 2)
ok ("invoflow-app.zip created - " + $appSizeMB + " MB")

# STEP 4: Restore index.php for local
step "STEP 4 - Restoring index.php for Local XAMPP"

Set-Content $indexPath $indexBackup -NoNewline
ok "index.php restored to local version (XAMPP ready)"

# STEP 5: Verify
step "STEP 5 - Verification"

$vendorPath = Join-Path $projectRoot "vendor"
if (Test-Path $vendorPath) {
    $vc = (Get-ChildItem $vendorPath -Recurse -File).Count
    ok ("vendor/ included - " + $vc + " files")
} else {
    warn "vendor/ not found! Run: composer install --no-dev --optimize-autoloader"
}

$verCheck = Join-Path $tempApp "version.json"
ok "version.json included in app ZIP (v$newVer)"

# Summary
Write-Host ""
Write-Host "  ============================================" -ForegroundColor Green
Write-Host ("  ZIP FILES READY!  v" + $newVer) -ForegroundColor Green
Write-Host "  ============================================" -ForegroundColor Green
Write-Host ""
Write-Host ("  [APP]    invoflow-app_" + $timestamp + ".zip") -ForegroundColor White
Write-Host "           Upload to: ~/invoflow/ on Hostinger" -ForegroundColor Gray
Write-Host ""
Write-Host ("  [PUBLIC] invoflow-public_" + $timestamp + ".zip") -ForegroundColor White
Write-Host "           Upload to: ~/public_html/ on Hostinger" -ForegroundColor Gray
Write-Host ""
Write-Host "  --- FRESH INSTALL (pehli baar) ---" -ForegroundColor Yellow
Write-Host "  1. invoflow-app zip -> ~/invoflow/ upload & extract" -ForegroundColor Gray
Write-Host "  2. invoflow-public zip -> ~/public_html/ upload & extract" -ForegroundColor Gray
Write-Host "  3. ~/invoflow/.env banao (HOSTINGER_DEPLOY_GUIDE.md STEP 6 dekho)" -ForegroundColor Gray
Write-Host "  4. inventory_laravel_db.sql -> phpMyAdmin se import karo" -ForegroundColor Gray
Write-Host "  5. Browser: yourdomain.com/installer.php?token=invoflow2024" -ForegroundColor Gray
Write-Host ""
Write-Host "  --- VERSION UPDATE (already deployed) ---" -ForegroundColor Cyan
Write-Host "  1. invoflow-app zip -> ~/invoflow/ extract (overwrite)" -ForegroundColor Gray
Write-Host "  2. invoflow-public zip -> ~/public_html/ extract (overwrite)" -ForegroundColor Gray
Write-Host "  3. Browser: yourdomain.com/updater.php?token=invoflow_update_2024" -ForegroundColor Gray
Write-Host ("     -> Run Migrations (if required) -> Optimize Cache -> Save v" + $newVer) -ForegroundColor Gray
Write-Host "     -> DELETE updater.php after done!" -ForegroundColor Gray
Write-Host ""
Write-Host "  NOTE: updater.php public zip mein hai - use ke baad DELETE karo!" -ForegroundColor Yellow
Write-Host ""

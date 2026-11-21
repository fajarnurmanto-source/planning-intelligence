#requires -Version 5.1
<#
Guarded IN-PLACE repair for the EXISTING R74 Windows launcher. No new .lnk,
VBS or app. Default = READ_ONLY preflight. -Apply explicitly backs up and
replaces ONLY two CMD files, adds one helper under original launcher folder.
NEVER edits Laravel source, databases, .env, APP_KEY, or shortcut.
#>
[CmdletBinding()]
param(
    [string] $LauncherRoot = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111',
    [string] $AppRoot = 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel',
    [switch] $Apply
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$expected = @{
    'BUKA-PI-HIDDEN.vbs'='9FC0BF471E18'
    'BUKA-PI-LARAVEL-HIDDEN.cmd'='D59A6681D3A0'
    'BUKA-PI-LARAVEL.cmd'='A0238A4B33E5'
}
$templates = @{
    'BUKA-PI-LARAVEL-HIDDEN.cmd' = (Join-Path $PSScriptRoot 'windows\r74\BUKA-PI-LARAVEL-HIDDEN.cmd')
    'BUKA-PI-LARAVEL.cmd' = (Join-Path $PSScriptRoot 'windows\r74\BUKA-PI-LARAVEL.cmd')
    'PI-R74-RUNTIME.ps1' = (Join-Path $PSScriptRoot 'pi-r74-ensure-runtime.ps1')
}
$LauncherRoot = [IO.Path]::GetFullPath($LauncherRoot).TrimEnd('\')
$AppRoot = [IO.Path]::GetFullPath($AppRoot).TrimEnd('\')
if ($LauncherRoot -ne 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111') {
    throw 'PI_R74_ROOT_CHANGED_OPERATOR_REVIEW_REQUIRED'
}
if ($AppRoot -ne 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel') {
    throw 'PI_R74_APP_ROOT_CHANGED_OPERATOR_REVIEW_REQUIRED'
}
if (-not (Test-Path -LiteralPath $LauncherRoot -PathType Container)) {
    throw 'PI_R74_ORIGINAL_LAUNCHER_NOT_FOUND'
}
foreach ($name in $expected.Keys) {
    $path = Join-Path $LauncherRoot $name
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw ('PI_R74_ORIGINAL_FILE_MISSING=' + $name)
    }
    $hash = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.Substring(0,12)
    if ($hash -ne $expected[$name]) {
        throw ('PI_R74_ORIGINAL_HASH_DRIFT=' + $name + '_ACTUAL=' + $hash)
    }
    Write-Output ('PI_R74_BASELINE_SHA12=' + $name + ':' + $hash)
}
$vbs = Get-Content -LiteralPath (Join-Path $LauncherRoot 'BUKA-PI-HIDDEN.vbs') -Raw
if ($vbs.IndexOf('BUKA-PI-LARAVEL-HIDDEN.cmd',[StringComparison]::OrdinalIgnoreCase) -lt 0) {
    throw 'PI_R74_VBS_CHAIN_DRIFT'
}
foreach ($name in $templates.Keys) {
    if (-not (Test-Path -LiteralPath $templates[$name] -PathType Leaf)) {
        throw ('PI_R74_UPGRADE_SOURCE_MISSING=' + $name)
    }
}
foreach ($name in @('artisan','vendor\autoload.php','public\index.php',
        'public\regulatory-genealogy-r74.js','.env')) {
    if (-not (Test-Path -LiteralPath (Join-Path $AppRoot $name) -PathType Leaf)) {
        throw ('PI_R74_ORIGINAL_APP_FILE_MISSING=' + $name)
    }
}
$envText = Get-Content -LiteralPath (Join-Path $AppRoot '.env') -Raw
if (-not [regex]::IsMatch($envText,'(?m)^APP_KEY=\S+')) {
    throw 'PI_R74_APP_KEY_MISSING'
}
$urlMatch = [regex]::Match($envText,'(?m)^APP_URL=(.+)$')
if (-not $urlMatch.Success) { throw 'PI_R74_APP_URL_MISSING' }
$configuredUrl = $urlMatch.Groups[1].Value.Trim().Trim('"',"'").TrimEnd('/')
if ($configuredUrl -ne 'http://127.0.0.1:8820') {
    Write-Output 'PI_R74_APP_URL_MISMATCH=YES_VALUE_REDACTED'
    throw 'PI_R74_APP_URL_MISMATCH_HOLD_NO_MUTATION'
}
if (-not (Get-Command php.exe -ErrorAction SilentlyContinue)) {
    throw 'PI_R74_PHP_NOT_AVAILABLE'
}
Write-Output 'PI_R74_BASELINE=VERIFIED'
Write-Output 'PI_R74_ONE_MAIN_ORIGIN=http://127.0.0.1:8820'
Write-Output 'PI_R74_LEGACY_APP_ROOT=PRESERVED'
Write-Output 'PI_R74_SHORTCUT_AND_VBS=UNCHANGED'
Write-Output 'PI_R74_MAIN_DB_AND_ENV=UNTOUCHED'
if (-not $Apply) {
    Write-Output 'PI_R74_UPGRADE=READ_ONLY_PREFLIGHT_PASS'
    Write-Output 'PI_R74_APPLY=NOT_REQUESTED'
    return
}
# Backup both original CMD files + VBS and immutable hash receipt before writes.
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $LauncherRoot ('backups\R74_BEFORE_ONE_ORIGIN_' + $timestamp)
New-Item -ItemType Directory -Path $backup -Force | Out-Null
foreach ($name in $expected.Keys) {
    Copy-Item -LiteralPath (Join-Path $LauncherRoot $name) -Destination (Join-Path $backup $name) -ErrorAction Stop
}
$receipt = foreach ($name in $expected.Keys) {
    $copy = Join-Path $backup $name
    [pscustomobject]@{
        File = $name
        SHA256 = (Get-FileHash -LiteralPath $copy -Algorithm SHA256).Hash
    }
}
$receipt | ConvertTo-Json | Out-File -LiteralPath (Join-Path $backup 'ORIGINAL_SHA256.json') -Encoding UTF8
$staged = @{}
try {
    # Write into same volume, then atomically rename per file.
    foreach ($name in $templates.Keys) {
        $target = Join-Path $LauncherRoot $name
        $temp = $target + '.candidate-' + [guid]::NewGuid().ToString('N')
        Copy-Item -LiteralPath $templates[$name] -Destination $temp -ErrorAction Stop
        $staged[$name] = $temp
    }
    foreach ($name in @('PI-R74-RUNTIME.ps1','BUKA-PI-LARAVEL-HIDDEN.cmd','BUKA-PI-LARAVEL.cmd')) {
        Move-Item -LiteralPath $staged[$name] -Destination (Join-Path $LauncherRoot $name) -Force -ErrorAction Stop
        $staged.Remove($name)
    }
    Write-Output 'PI_R74_UPGRADE=APPLIED_TO_ORIGINAL_FOLDER'
    Write-Output ('PI_R74_BACKUP=' + $backup)
    Write-Output 'PI_R74_LNK_VBS_APP_DB_ENV=UNCHANGED'
    Write-Output 'PI_R74_NEXT=TEST_ORIGINAL_DESKTOP_ICON'
} catch {
    # Fail closed: restore both CMDs from immutable backup.
    foreach ($name in @('BUKA-PI-LARAVEL-HIDDEN.cmd','BUKA-PI-LARAVEL.cmd')) {
        Copy-Item -LiteralPath (Join-Path $backup $name) -Destination (Join-Path $LauncherRoot $name) -Force -ErrorAction Stop
    }
    throw
} finally {
    foreach ($name in @($staged.Keys)) {
        if (Test-Path -LiteralPath $staged[$name] -PathType Leaf) {
            Remove-Item -LiteralPath $staged[$name] -ErrorAction SilentlyContinue
        }
    }
}

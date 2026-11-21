#requires -Version 5.1
<#
Acceptance of existing PI R74 desktop shortcut after the guarded in-place upgrade.
Default mode is strictly READ-ONLY. -ExerciseShortcut deliberately opens the
ORIGINAL desktop .lnk, optionally twice, without starting/stopping PHP directly.
Requires source checkout of PR #78 matching deployed R74 scripts.
Never touches DB, main .env, shortcut, source, active PHP process or QA ports.
#>
[CmdletBinding()]
param(
    [switch] $ExerciseShortcut,
    [switch] $SecondClick,
    [string] $LauncherRoot = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111',
    [string] $AppRoot = 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel',
    [string] $ShortcutPath = 'C:\Users\fajar\OneDrive\Desktop\Planning Intelligence.lnk',
    [string] $BackupRoot = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111\backups\R74_BEFORE_ONE_ORIGIN_20261122-061856'
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$port = 8820
$probe = 'http://127.0.0.1:8820/regulatory-genealogy-r74.js'
$origin = 'http://127.0.0.1:8820/'
$expected = @{
    'BUKA-PI-HIDDEN.vbs'='9FC0BF471E18'
    'BUKA-PI-LARAVEL-HIDDEN.cmd'='D59A6681D3A0'
    'BUKA-PI-LARAVEL.cmd'='A0238A4B33E5'
}
if ($SecondClick -and -not $ExerciseShortcut) {
    throw 'PI_R74_SECOND_CLICK_REQUIRES_EXPLICIT_EXERCISE_SHORTCUT'
}
if ($LauncherRoot -ne 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111' -or
    $AppRoot -ne 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel') {
    throw 'PI_R74_ACCEPTANCE_ROOT_DRIFT'
}
function Sha12([string]$path) {
    return (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.Substring(0,12)
}
function CurrentPid {
    $listeners = @(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
    if ($listeners.Count -gt 1) {
        throw 'PI_R74_MULTIPLE_LISTENERS_REVIEW_REQUIRED'
    }
    if ($listeners.Count -eq 0) { return $null }
    if ($listeners[0].LocalAddress -ne '127.0.0.1') {
        throw 'PI_R74_UNEXPECTED_BIND_ADDRESS'
    }
    return [int]$listeners[0].OwningProcess
}
function Test-ExactOriginalAsset {
    $source = Join-Path $AppRoot 'public\regulatory-genealogy-r74.js'
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { return $false }
    try {
        $reply = Invoke-WebRequest -Uri $probe -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        if ($reply.StatusCode -ne 200) { return $false }
        $localBytes = [IO.File]::ReadAllBytes($source)
        $sha = [Security.Cryptography.SHA256]::Create()
        try {
            $remoteBytes = $reply.RawContentStream.ToArray()
            $localHash = [BitConverter]::ToString($sha.ComputeHash($localBytes))
            $remoteHash = [BitConverter]::ToString($sha.ComputeHash($remoteBytes))
            return $localHash -ceq $remoteHash
        } finally { $sha.Dispose() }
    } catch { return $false }
}
function Assert-VerifiedRuntime {
    $pidNumber = CurrentPid
    if ($null -eq $pidNumber) { throw 'PI_R74_ORIGIN_NOT_LISTENING' }
    $report = @(& $installedSupervisor -AppRoot $AppRoot -Port 8820 -Mode Audit)
    if ($report -notcontains 'PI_R74_ORIGIN_VERIFIED=True') {
        throw 'PI_R74_ORIGIN_IDENTITY_NOT_VERIFIED'
    }
    if (-not (Test-ExactOriginalAsset)) {
        throw 'PI_R74_ORIGINAL_ASSET_HASH_MISMATCH'
    }
    return $pidNumber
}
function Wait-VerifiedRuntime {
    for ($attempt=0; $attempt -lt 40; $attempt++) {
        Start-Sleep -Milliseconds 500
        try { return (Assert-VerifiedRuntime) } catch { }
    }
    throw 'PI_R74_LAUNCH_DID_NOT_REACH_VERIFIED_ORIGIN'
}
if (-not (Test-Path -LiteralPath $LauncherRoot -PathType Container)) {
    throw 'PI_R74_EXISTING_LAUNCHER_FOLDER_MISSING'
}
if (-not (Test-Path -LiteralPath $BackupRoot -PathType Container)) {
    throw 'PI_R74_BACKUP_FOLDER_MISSING'
}
$backupManifest = Join-Path $BackupRoot 'ORIGINAL_SHA256.json'
if (-not (Test-Path -LiteralPath $backupManifest -PathType Leaf)) {
    throw 'PI_R74_BACKUP_MANIFEST_MISSING'
}
$receipt = @(Get-Content -LiteralPath $backupManifest -Raw | ConvertFrom-Json)
foreach ($name in $expected.Keys) {
    $old = Join-Path $BackupRoot $name
    if (-not (Test-Path -LiteralPath $old -PathType Leaf)) {
        throw ('PI_R74_BACKUP_FILE_MISSING=' + $name)
    }
    $found = @($receipt | Where-Object { $_.File -eq $name })
    if ($found.Count -ne 1 -or (Get-FileHash -LiteralPath $old -Algorithm SHA256).Hash -ne $found[0].SHA256 -or
        (Sha12 $old) -ne $expected[$name]) {
        throw ('PI_R74_BACKUP_RECEIPT_INVALID=' + $name)
    }
}
Write-Output 'PI_R74_ORIGINAL_BACKUP_MANIFEST=VERIFIED'
$vbs = Join-Path $LauncherRoot 'BUKA-PI-HIDDEN.vbs'
if ((Sha12 $vbs) -ne $expected['BUKA-PI-HIDDEN.vbs']) {
    throw 'PI_R74_ORIGINAL_VBS_CHANGED'
}
$templates = @{
    'BUKA-PI-LARAVEL-HIDDEN.cmd' = (Join-Path $PSScriptRoot 'windows\r74\BUKA-PI-LARAVEL-HIDDEN.cmd')
    'BUKA-PI-LARAVEL.cmd' = (Join-Path $PSScriptRoot 'windows\r74\BUKA-PI-LARAVEL.cmd')
    'PI-R74-RUNTIME.ps1' = (Join-Path $PSScriptRoot 'pi-r74-ensure-runtime.ps1')
}
foreach ($name in $templates.Keys) {
    $deployed = Join-Path $LauncherRoot $name
    $candidate = $templates[$name]
    if (-not (Test-Path -LiteralPath $candidate -PathType Leaf) -or
        -not (Test-Path -LiteralPath $deployed -PathType Leaf)) {
        throw ('PI_R74_INSTALLED_RUNTIME_MISSING=' + $name)
    }
    if ((Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash -ne
        (Get-FileHash -LiteralPath $deployed -Algorithm SHA256).Hash) {
        throw ('PI_R74_RUNTIME_DEPLOYED_HASH_MISMATCH=' + $name)
    }
}
Write-Output 'PI_R74_TWO_CMDS_AND_SUPERVISOR=EXACT_SOURCE_MATCH'
$installedSupervisor = Join-Path $LauncherRoot 'PI-R74-RUNTIME.ps1'
if (-not (Test-Path -LiteralPath $ShortcutPath -PathType Leaf)) {
    throw 'PI_R74_ORIGINAL_DESKTOP_SHORTCUT_MISSING'
}
$shell = New-Object -ComObject WScript.Shell
$link = $shell.CreateShortcut($ShortcutPath)
if (-not $link.TargetPath.EndsWith('\wscript.exe',[StringComparison]::OrdinalIgnoreCase) -or
    $link.WorkingDirectory -ne $LauncherRoot -or
    $link.Arguments.IndexOf('BUKA-PI-HIDDEN.vbs',[StringComparison]::OrdinalIgnoreCase) -lt 0) {
    throw 'PI_R74_ORIGINAL_SHORTCUT_TARGET_CHANGED'
}
Write-Output 'PI_R74_DESKTOP_SHORTCUT_AND_VBS=UNCHANGED'
$before = CurrentPid
if ($null -eq $before) {
    Write-Output 'PI_R74_BEFORE_LAUNCH=NO_SERVER'
} else {
    [void](Assert-VerifiedRuntime)
    Write-Output ('PI_R74_BEFORE_LAUNCH_OWNED_PID=' + $before)
}
Write-Output 'PI_R74_MAIN_PORT=8820'
Write-Output 'PI_R74_QA_PORTS_NOT_TOUCHED=YES'
if (-not $ExerciseShortcut) {
    Write-Output 'PI_R74_ACCEPTANCE=READ_ONLY_INSTALLED_INTEGRITY_PASS'
    Write-Output 'PI_R74_LAUNCH_TEST=NOT_REQUESTED'
    return
}
Write-Output 'PI_R74_OPENING_EXISTING_DESKTOP_SHORTCUT=YES'
Start-Process -FilePath $ShortcutPath -ErrorAction Stop
$after = Wait-VerifiedRuntime
if ($null -ne $before -and $after -ne $before) {
    throw 'PI_R74_EXISTING_SERVER_PID_CHANGED'
}
Write-Output ('PI_R74_AFTER_FIRST_CLICK_PID=' + $after)
if ($SecondClick) {
    Start-Process -FilePath $ShortcutPath -ErrorAction Stop
    Start-Sleep -Seconds 3
    $again = Assert-VerifiedRuntime
    if ($again -ne $after) { throw 'PI_R74_DOUBLE_CLICK_STARTED_ANOTHER_SERVER' }
    Write-Output ('PI_R74_AFTER_SECOND_CLICK_SAME_PID=' + $again)
}
Write-Output 'PI_R74_EXISTING_DESKTOP_ICON_RUNTIME=PASS'
Write-Output 'PI_R74_LOGIN_AND_RESTART_E2E=REQUIRES_USER_OBSERVATION'
Write-Output ('PI_R74_EXPECTED_BROWSER_ORIGIN=' + $origin)

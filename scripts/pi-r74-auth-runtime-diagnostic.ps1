#requires -Version 5.1
<#
Diagnose recurring R74 8820 login failure without changing passwords, DB,
sessions, .env, shortcut, port, services or QA runtimes.
Reads owner of active 8820 listener, original .env metadata (redacts values),
the presence of Laravel cached config, immutable read-only SQLite account
inventory, and safe launcher log codes. No HTTP login or credential prompt.
#>
[CmdletBinding()]
param(
    [string] $AppRoot = 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel',
    [string] $LauncherRoot = 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111',
    [string] $Username = 'fajar.nurmanto'
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
if ($AppRoot -ne 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel') {
    throw 'PI_R74_APP_ROOT_NOT_ORIGINAL'
}
if ($LauncherRoot -ne 'D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111') {
    throw 'PI_R74_LAUNCHER_ROOT_NOT_ORIGINAL'
}
$expectedDb = Join-Path $AppRoot 'database\database.sqlite'
$envFile = Join-Path $AppRoot '.env'
$cacheFile = Join-Path $AppRoot 'bootstrap\cache\config.php'
if (-not (Test-Path -LiteralPath $AppRoot -PathType Container) -or
    -not (Test-Path -LiteralPath $envFile -PathType Leaf)) {
    throw 'PI_R74_ORIGINAL_APP_OR_ENV_NOT_FOUND'
}
function Get-EnvSetting([string] $key) {
    $expr = '(?m)^\s*' + [regex]::Escape($key) + '\s*=\s*(.*)$'
    $hit = [regex]::Match($script:envText,$expr)
    if (-not $hit.Success) { return $null }
    return $hit.Groups[1].Value.Trim().Trim('"',"'")
}
$envText = Get-Content -LiteralPath $envFile -Raw -ErrorAction Stop
$connection = Get-EnvSetting 'DB_CONNECTION'
$databaseSetting = Get-EnvSetting 'DB_DATABASE'
$dbUrl = Get-EnvSetting 'DB_URL'
if (-not $dbUrl) { $dbUrl = Get-EnvSetting 'DATABASE_URL' }
$appUrl = Get-EnvSetting 'APP_URL'
Write-Output 'PI_R74_LOGIN_DIAGNOSTIC=READ_ONLY'
Write-Output ('PI_R74_ENV_SQLITE_DRIVER=' + [string]($connection -eq 'sqlite' -or -not $connection))
Write-Output ('PI_R74_ENV_DB_URL_OVERRIDE_PRESENT=' + [string](-not [string]::IsNullOrWhiteSpace($dbUrl)))
Write-Output ('PI_R74_APP_URL_EXPECTED=' + [string]($appUrl -eq 'http://127.0.0.1:8820' -or $appUrl -eq 'http://127.0.0.1:8820/'))
Write-Output ('PI_R74_LARAVEL_CONFIG_CACHE_PRESENT=' + [string](Test-Path -LiteralPath $cacheFile -PathType Leaf))
if ($databaseSetting) {
    if ([IO.Path]::IsPathRooted($databaseSetting)) {
        $resolvedDb = [IO.Path]::GetFullPath($databaseSetting)
    } else {
        $resolvedDb = [IO.Path]::GetFullPath((Join-Path $AppRoot $databaseSetting))
    }
    Write-Output ('PI_R74_ENV_DB_PATH_MATCHES_RECOVERED=' + [string]($resolvedDb -eq $expectedDb))
} else {
    Write-Output 'PI_R74_ENV_DB_PATH=DEFAULT_LARAVEL_DATABASE_SQLITE_CANDIDATE'
}
Write-Output ('PI_R74_RECOVERED_DB_FILE_EXISTS=' + [string](Test-Path -LiteralPath $expectedDb -PathType Leaf))
$listener = @(Get-NetTCPConnection -State Listen -LocalPort 8820 -ErrorAction SilentlyContinue)
Write-Output ('PI_R74_8820_LISTENER_COUNT=' + $listener.Count)
if ($listener.Count -eq 1) {
    $one = $listener[0]
    Write-Output ('PI_R74_8820_LOOPBACK_ONLY=' + [string]($one.LocalAddress -eq '127.0.0.1'))
    Write-Output ('PI_R74_8820_PID=' + $one.OwningProcess)
    $proc = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $one.OwningProcess) -ErrorAction SilentlyContinue
    $cmd = if ($null -eq $proc) { '' } else { [string]$proc.CommandLine }
    Write-Output ('PI_R74_8820_PROCESS_IS_PHP=' + [string]($null -ne $proc -and $proc.Name -eq 'php.exe'))
    Write-Output ('PI_R74_8820_PROCESS_USES_RECOVERED_APP_ROOT=' + [string]($cmd.IndexOf($AppRoot,[StringComparison]::OrdinalIgnoreCase) -ge 0))
} elseif ($listener.Count -eq 0) {
    Write-Output 'PI_R74_8820_SERVER_NOT_RUNNING=YES'
} else {
    Write-Output 'PI_R74_8820_MULTIPLE_LISTENERS=REVIEW_REQUIRED'
}
$php = Get-Command php.exe -ErrorAction SilentlyContinue
if ($null -eq $php) {
    Write-Output 'PI_R74_PHP_CLI=NOT_FOUND'
} elseif (Test-Path -LiteralPath $expectedDb -PathType Leaf) {
    $probe = Join-Path $PSScriptRoot 'pi-r74-auth-db-readonly.php'
    if (-not (Test-Path -LiteralPath $probe -PathType Leaf)) {
        throw 'PI_R74_DB_READONLY_HELPER_MISSING'
    }
    & $php.Source $probe $expectedDb $Username
    if ($LASTEXITCODE -ne 0) { throw 'PI_R74_READONLY_SQLITE_ACCOUNT_PROBE_FAILED' }
}
$logFile = Join-Path $LauncherRoot 'logs\launcher-last.log'
Write-Output ('PI_R74_LAUNCHER_LOG_EXISTS=' + [string](Test-Path -LiteralPath $logFile -PathType Leaf))
if (Test-Path -LiteralPath $logFile -PathType Leaf) {
    # Only already sanitized PI_* status lines; never expose raw command lines.
    $codes = @(Get-Content -LiteralPath $logFile -Tail 80 -ErrorAction SilentlyContinue |
        Where-Object { $_ -match '^\s*PI_[A-Z0-9_]+(?:=|\s|$)' } |
        Select-Object -Last 6)
    foreach ($code in $codes) {
        if ($code -match '^\s*(PI_[A-Z0-9_]+)') {
            Write-Output ('PI_R74_LAUNCHER_REPORTED_CODE=' + $Matches[1])
        }
    }
}
Write-Output 'PI_R74_NO_ACCOUNT_PASSWORD_OR_DB_MUTATION=YES'
Write-Output 'PI_R74_DIAGNOSTIC_NEXT=CORRELATE_PHP_LISTENER_DB_CONFIGURATION_ACCOUNT_RECORD'

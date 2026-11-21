#requires -Version 5.1
<#
R74 existing unified launcher helper (source candidate, no independent app).
Audited owner entry: BUKA-PI-HIDDEN.vbs -> BUKA-PI-LARAVEL-HIDDEN.cmd.
R74 main origin: 127.0.0.1:8820 (confirmed in original CMD).
Never change main DB, .env, APP_KEY, users, shortcut or QA ports.
Audit (default) does not start a process. Ensure is opt-in via guarded in-place
upgrade of the EXISTING R74 CMD, not via another desktop shortcut.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string] $AppRoot,
    [Parameter(Mandatory=$true)][ValidateRange(1024,65535)][int] $Port,
    [ValidateSet('Audit','Ensure')][string] $Mode = 'Audit',
    [string] $PhpExe,
    [string] $ProbeRelativePath = 'regulatory-genealogy-r74.js'
)
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$Root = [IO.Path]::GetFullPath($AppRoot).TrimEnd('\','/')
$Origin = 'http://127.0.0.1:' + $Port
$ProbeFile = Join-Path (Join-Path $Root 'public') $ProbeRelativePath
$ProbeUri = $Origin + '/' + $ProbeRelativePath

function Get-Listeners {
    return @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
}
function Test-ExactAsset {
    # HTTP 200 alone is not an identity test. Compare response bytes to
    # the exact public asset in the designated original Laravel directory.
    if (-not (Test-Path -LiteralPath $ProbeFile -PathType Leaf)) { return $false }
    try {
        $r = Invoke-WebRequest -Uri $ProbeUri -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        if ($r.StatusCode -ne 200) { return $false }
        $local = (Get-FileHash -LiteralPath $ProbeFile -Algorithm SHA256).Hash
        $hash = [Security.Cryptography.SHA256]::Create()
        try {
            $body = $r.RawContentStream.ToArray()
            $remote = [BitConverter]::ToString($hash.ComputeHash($body)).Replace('-','')
        } finally { $hash.Dispose() }
        return [string]::Equals($local,$remote,[StringComparison]::OrdinalIgnoreCase)
    } catch { return $false }
}
function Test-OwnedOrigin {
    $listeners = @(Get-Listeners)
    if ($listeners.Count -eq 0) { return $false }
    foreach ($listener in $listeners) {
        if ($listener.LocalAddress -ne '127.0.0.1') {
            throw 'PI_R74_UNEXPECTED_NON_LOOPBACK_LISTENER'
        }
        $p = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $listener.OwningProcess) -ErrorAction Stop
        if ($null -eq $p -or -not $p.CommandLine) {
            throw 'PI_R74_LISTENER_OWNER_NOT_READABLE'
        }
        $line = [string]$p.CommandLine
        # The child of "php artisan serve" normally runs php -S with an
        # absolute path to Laravel's vendor server.php within app root.
        if ($line.IndexOf($Root,[StringComparison]::OrdinalIgnoreCase) -lt 0 -or
            $line -notmatch '(?i)(php|php\.exe)' -or
            ($line -notmatch '(?i)(artisan|server\.php| -S )')) {
            throw 'PI_R74_UNRECOGNIZED_PORT_OWNER'
        }
        if ($line.IndexOf((': ' + $Port),[StringComparison]::OrdinalIgnoreCase) -ge 0) {
            throw 'PI_R74_UNEXPECTED_PHP_COMMAND_FORMAT'
        }
    }
    if (-not (Test-ExactAsset)) {
        throw 'PI_R74_PROBE_HASH_OR_HEALTH_MISMATCH'
    }
    return $true
}
function Assert-SourceReady {
    foreach ($relative in @('artisan','vendor\autoload.php','public\index.php',
            'public\regulatory-genealogy-r74.js','.env')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Root $relative) -PathType Leaf)) {
            throw ('PI_R74_REQUIRED_SOURCE_MISSING=' + $relative)
        }
    }
    $envText = Get-Content -LiteralPath (Join-Path $Root '.env') -Raw
    if ($envText -notmatch '(?m)^APP_KEY=\S+') {
        throw 'PI_R74_APP_KEY_MISSING'
    }
    $m = [regex]::Match($envText,'(?m)^APP_URL=(.+)$')
    if (-not $m.Success) { throw 'PI_R74_APP_URL_MISSING' }
    $appUrl = $m.Groups[1].Value.Trim().Trim('"',"'").TrimEnd('/')
    if ($appUrl -ne $Origin) { throw 'PI_R74_APP_URL_REQUIRES_SEPARATE_REVIEW' }
    if (-not $PhpExe) {
        $php = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($null -eq $php) { throw 'PI_R74_PHP_EXE_MISSING' }
        $script:PhpExe = [string]$php.Source
    }
    if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) {
        throw 'PI_R74_PHP_EXE_NOT_FOUND'
    }
}
if (-not (Test-Path -LiteralPath $Root -PathType Container)) {
    throw 'PI_R74_ORIGINAL_APP_ROOT_MISSING'
}
Write-Output ('PI_R74_MODE=' + $Mode)
Write-Output ('PI_R74_ORIGIN=' + $Origin)
if ($Mode -eq 'Audit') {
    $listeners = @(Get-Listeners)
    Write-Output ('PI_R74_LISTENERS=' + $listeners.Count)
    if ($listeners.Count) {
        Write-Output ('PI_R74_ORIGIN_VERIFIED=' + (Test-OwnedOrigin))
    } else { Write-Output 'PI_R74_SERVER_ABSENT=YES' }
    Write-Output 'PI_R74_AUDIT_MUTATION=NONE'
    return
}
$mutex = New-Object System.Threading.Mutex($false,('Local\PI_R74_ORIGIN_' + $Port))
$locked = $false
try {
    $locked = $mutex.WaitOne(15000)
    if (-not $locked) { throw 'PI_R74_START_LOCK_TIMEOUT' }
    if (Test-OwnedOrigin) {
        Write-Output 'PI_R74_RUNTIME=REUSED'
        return
    }
    Assert-SourceReady
    $logDir = Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\Runtime'
    if (-not (Test-Path -LiteralPath $logDir -PathType Container)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    $token = [guid]::NewGuid().ToString('N')
    $stdout = Join-Path $logDir ('r74-' + $token + '.out.log')
    $stderr = Join-Path $logDir ('r74-' + $token + '.err.log')
    # Absolute artisan path links the PHP process chain back to app root.
    $artisan = Join-Path $Root 'artisan'
    $args = @($artisan,'serve','--host=127.0.0.1',('--port=' + $Port))
    $process = Start-Process -FilePath $PhpExe -ArgumentList $args -WorkingDirectory $Root -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    for ($i=0; $i -lt 60; $i++) {
        Start-Sleep -Milliseconds 500
        if (Test-ExactAsset) {
            if (-not (Test-OwnedOrigin)) { throw 'PI_R74_ORIGIN_OWNERSHIP_FAILED' }
            Write-Output 'PI_R74_RUNTIME=STARTED'
            Write-Output ('PI_R74_START_PID=' + $process.Id)
            return
        }
        if ($process.HasExited) { break }
    }
    throw 'PI_R74_START_FAILED_INSPECT_LOCALAPPDATA_LOGS'
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}

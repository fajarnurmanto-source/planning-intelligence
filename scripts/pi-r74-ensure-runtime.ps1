#requires -Version 5.1
<#
R74 runtime adapter, NOT a new desktop shortcut or installer.
Audit is read-only. Ensure is opt-in; wire into verified existing R74 WScript only.
No default port. No dotenv edits, migrations, DB copies or stop-process.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string] $AppRoot,
    [Parameter(Mandatory=$true)][ValidateRange(1024,65535)][int] $Port,
    [ValidateSet('Audit','Ensure')][string] $Mode = 'Audit',
    [string] $PhpExe
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$Root = [IO.Path]::GetFullPath($AppRoot).TrimEnd('\','/')
$Identity = 'PI_R74_LARAVEL_SINGLE_RUNTIME_20261122_V1'
$IdentityFile = Join-Path $Root 'public\pi-r74-runtime-identity.txt'
$BaseUrl = 'http://127.0.0.1:' + $Port
$IdentityUrl = $BaseUrl + '/pi-r74-runtime-identity.txt'

function Get-PortOwners {
    return @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
}
function Test-MatchedOrigin {
    try {
        $response = Invoke-WebRequest -Uri $IdentityUrl -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        return ($response.StatusCode -eq 200 -and ([string]$response.Content).Trim() -ceq $Identity)
    } catch { return $false }
}
function Assert-PortOwnership {
    $listeners = @(Get-PortOwners)
    if ($listeners.Count -eq 0) { return $false }
    $loopbackFound = $false
    foreach ($listener in $listeners) {
        if ($listener.LocalAddress -ne '127.0.0.1') {
            throw ('PI_R74_PORT_NON_LOOPBACK_LISTENER=' + $Port)
        }
        $process = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $listener.OwningProcess) -ErrorAction Stop
        if ($null -eq $process -or [string]::IsNullOrWhiteSpace($process.CommandLine)) {
            throw 'PI_R74_LISTENER_IDENTITY_UNAVAILABLE'
        }
        $command = [string]$process.CommandLine
        if ($command.IndexOf($Root,[StringComparison]::OrdinalIgnoreCase) -lt 0 -or
            $command.IndexOf('php', [StringComparison]::OrdinalIgnoreCase) -lt 0) {
            throw ('PI_R74_PORT_OWNED_BY_OTHER_APPLICATION=' + $Port)
        }
        $loopbackFound = $true
    }
    if (-not $loopbackFound -or -not (Test-MatchedOrigin)) {
        throw 'PI_R74_LISTENER_APP_IDENTITY_MISMATCH'
    }
    return $true
}
function Assert-ReleaseReady {
    foreach ($relative in @('artisan','vendor\autoload.php','public\index.php',
            'public\pi-r74-runtime-identity.txt','.env')) {
        if (-not (Test-Path -LiteralPath (Join-Path $Root $relative) -PathType Leaf)) {
            throw ('PI_R74_MISSING_RELEASE_FILE=' + $relative)
        }
    }
    if ((Get-Content -LiteralPath $IdentityFile -Raw).Trim() -cne $Identity) {
        throw 'PI_R74_RELEASE_MARKER_MISMATCH'
    }
    $envText = Get-Content -LiteralPath (Join-Path $Root '.env') -Raw
    if ($envText -notmatch '(?m)^APP_KEY=\S+' -or
        $envText -notmatch '(?m)^SESSION_COOKIE=\S+') {
        throw 'PI_R74_APP_KEY_OR_SESSION_COOKIE_NOT_CONFIGURED'
    }
    $appUrlMatch = [regex]::Match($envText,'(?m)^APP_URL=(.+)$')
    if (-not $appUrlMatch.Success) { throw 'PI_R74_APP_URL_NOT_CONFIGURED' }
    $configuredUrl = $appUrlMatch.Groups[1].Value.Trim().Trim('"',"'").TrimEnd('/')
    if ($configuredUrl -ne $BaseUrl) {
        throw 'PI_R74_APP_URL_DOES_NOT_MATCH_EXPLICIT_PORT'
    }
    if ([string]::IsNullOrWhiteSpace($PhpExe) -or
        -not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) {
        throw 'PI_R74_PHP_EXE_NOT_VERIFIED'
    }
}
if (-not (Test-Path -LiteralPath $Root -PathType Container)) {
    throw 'PI_R74_APP_ROOT_NOT_FOUND'
}
Write-Output ('PI_R74_MODE=' + $Mode)
Write-Output ('PI_R74_CONFIGURED_PORT=' + $Port)
Write-Output ('PI_R74_APP_ROOT=' + $Root)
if ($Mode -eq 'Audit') {
    $listeners = @(Get-PortOwners)
    Write-Output ('PI_R74_PORT_LISTENER_COUNT=' + $listeners.Count)
    if ($listeners.Count -gt 0) {
        $verified = Assert-PortOwnership
        Write-Output ('PI_R74_EXISTING_OWNED_ORIGIN=' + $verified)
    } else {
        Write-Output 'PI_R74_ORIGIN_NOT_RUNNING=YES'
    }
    Write-Output 'PI_R74_AUDIT_MUTATION=NONE'
    return
}
# Named mutex prevents two clicks on the existing shortcut from starting two servers.
$mutex = [System.Threading.Mutex]::new($false,('Local\PI_R74_LARAVEL_' + $Port))
$locked = $false
try {
    $locked = $mutex.WaitOne(15000)
    if (-not $locked) { throw 'PI_R74_STARTUP_MUTEX_TIMEOUT' }
    if (Assert-PortOwnership) {
        Write-Output ('PI_R74_RUNTIME=REUSED ' + $BaseUrl)
        return
    }
    Assert-ReleaseReady
    $logDir = Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\Runtime'
    if (-not (Test-Path -LiteralPath $logDir -PathType Container)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    $stamp = [guid]::NewGuid().ToString('N')
    $stdout = Join-Path $logDir ('r74-' + $stamp + '.out.log')
    $stderr = Join-Path $logDir ('r74-' + $stamp + '.err.log')
    $args = @('artisan','serve','--host=127.0.0.1',('--port=' + $Port),'--no-reload')
    $child = Start-Process -FilePath $PhpExe -ArgumentList $args -WorkingDirectory $Root -WindowStyle Hidden -PassThru -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    for ($attempt=0; $attempt -lt 60; $attempt++) {
        Start-Sleep -Milliseconds 250
        if (Test-MatchedOrigin) {
            if (-not (Assert-PortOwnership)) { throw 'PI_R74_OWNERSHIP_RACE' }
            Write-Output ('PI_R74_RUNTIME=STARTED ' + $BaseUrl)
            Write-Output ('PI_R74_STARTER_PID=' + $child.Id)
            return
        }
        if ($child.HasExited) { break }
    }
    throw 'PI_R74_STARTUP_FAILED_CHECK_LOCALAPPDATA_LOGS'
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}

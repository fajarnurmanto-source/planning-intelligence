#requires -Version 5.1
<#
PI Single Runtime Windows launcher (local QA/development).
One source, one http://127.0.0.1:8820. Never touches main DB, .env, Git,
QA servers 8822/8823, or production admission.
#>
[CmdletBinding()]
param(
    [ValidateSet('Open','Start','Status','Stop','InstallShortcut')]
    [string]$Action = 'Open',
    [string]$AppRoot = (Join-Path (Split-Path -Parent $PSScriptRoot) 'laravel')
)

Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
$AppRoot = [IO.Path]::GetFullPath($AppRoot)
$Port = 8820
$Url = 'http://127.0.0.1:8820'
$Public = Join-Path $AppRoot 'public'
$Router = Join-Path $AppRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$EnvPath = Join-Path $AppRoot '.env'

function Assert-File([string]$path, [string]$code) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw $code }
}

function Read-AppEnv {
    $entries = @{}
    foreach ($line in (Get-Content -LiteralPath $EnvPath)) {
        if ($line -match '^\s*([A-Z][A-Z0-9_]*)\s*=\s*(.*)$') {
            $key = $Matches[1]
            $value = $Matches[2].Trim()
            if ($value.Length -ge 2 -and (
                ($value.StartsWith('"') -and $value.EndsWith('"')) -or
                ($value.StartsWith("'") -and $value.EndsWith("'")))) {
                $value = $value.Substring(1, $value.Length - 2)
            }
            $entries[$key] = $value
        }
    }
    return $entries
}

function Assert-ReleaseReady {
    Assert-File (Join-Path $AppRoot 'artisan') 'PI_ARTISAN_MISSING'
    Assert-File (Join-Path $Public 'index.php') 'PI_LANDING_ENTRY_MISSING'
    Assert-File (Join-Path $AppRoot 'vendor\autoload.php') 'PI_VENDOR_MISSING'
    Assert-File $Router 'PI_PHP_ROUTER_MISSING'
    Assert-File $EnvPath 'PI_ENV_MISSING'
    $config = Read-AppEnv
    if ($config['APP_URL'] -ne $Url -or $config['APP_ENV'] -notin @('local','staging')) {
        throw 'PI_CANONICAL_APP_URL_OR_ENV_NOT_CONFIGURED'
    }
    $appKey = [string]$config['APP_KEY']
    if (-not $appKey.StartsWith('base64:') -or $appKey.Length -lt 50) {
        throw 'PI_APP_KEY_NOT_CONFIGURED'
    }
    if ($config['DB_CONNECTION'] -ne 'sqlite') { throw 'PI_EXPLICIT_SQLITE_MAIN_DB_REQUIRED' }
    $database = [string]$config['DB_DATABASE']
    if ([string]::IsNullOrWhiteSpace($database) -or $database -eq ':memory:' -or
        -not [IO.Path]::IsPathRooted($database) -or
        -not (Test-Path -LiteralPath $database -PathType Leaf)) {
        throw 'PI_MAIN_SQLITE_NOT_FOUND'
    }
    if ($config['SESSION_DRIVER'] -ne 'file') { throw 'PI_SESSION_DRIVER_NOT_FILE' }
    $cookie = [string]$config['SESSION_COOKIE']
    if ($cookie -notmatch '^pi_[a-z0-9_]+_session$' -or $cookie -match '_qa_') {
        throw 'PI_MAIN_SESSION_COOKIE_NOT_CONFIGURED'
    }
    if ($config['APP_DEBUG'] -ne 'false') { throw 'PI_APP_DEBUG_MUST_BE_FALSE' }
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw 'PI_PHP_NOT_FOUND' }
    Write-Host 'PI_MAIN_RELEASE_PREFLIGHT=PASS'
    Write-Host 'PI_APP_KEY=REUSED_NOT_EXPOSED'
    Write-Host 'PI_DATABASE=REFERENCED_NOT_MUTATED'
}

function Get-CanonicalOwner {
    $listener = Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    if (-not $listener) { return $null }
    $process = Get-CimInstance Win32_Process -Filter ('ProcessId=' + $listener.OwningProcess)
    $command = ([string]$process.CommandLine).Replace('/', '\')
    $expectedRouter = $Router.Replace('/', '\')
    if ($command.IndexOf($expectedRouter,[StringComparison]::OrdinalIgnoreCase) -lt 0 -or
        $command.IndexOf('-S 127.0.0.1:8820',[StringComparison]::OrdinalIgnoreCase) -lt 0 -or
        ([string]$process.Name) -notmatch '^php(\.exe)?$') {
        throw ('PI_PORT_8820_FOREIGN_LISTENER_PID_' + $listener.OwningProcess)
    }
    return $process
}

function Assert-Http {
    $setup = Invoke-WebRequest -Uri ($Url + '/api/auth/setup-status') -UseBasicParsing -TimeoutSec 4 -ErrorAction Stop
    $home = Invoke-WebRequest -Uri ($Url + '/index.html') -UseBasicParsing -TimeoutSec 4 -ErrorAction Stop
    if ($setup.StatusCode -ne 200 -or $home.StatusCode -ne 200 -or
        $home.Content -notmatch 'Planning') { throw 'PI_RUNTIME_HEALTH_FAILED' }
    Write-Host 'PI_HTTP_SETUP=200'
    Write-Host 'PI_HTTP_LANDING=200'
}

function Start-Canonical {
    $existing = Get-CanonicalOwner
    if ($existing) {
        Assert-Http
        Write-Host ('PI_RUNTIME=ALREADY_RUNNING_PID_' + $existing.ProcessId)
        return
    }
    Assert-ReleaseReady
    $logs = Join-Path $AppRoot 'storage\logs'
    if (-not (Test-Path -LiteralPath $logs -PathType Container)) {
        throw 'PI_LOG_DIR_MISSING'
    }
    $php = (Get-Command php -ErrorAction Stop).Source
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
    $out = Join-Path $logs ('pi-main-' + $stamp + '.out.log')
    $err = Join-Path $logs ('pi-main-' + $stamp + '.err.log')
    $arguments = @('-S','127.0.0.1:8820','-t',('"' + $Public + '"'),('"' + $Router + '"'))
    $started = Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory $AppRoot -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err -PassThru
    $ready = $false
    $lastHealth = 'WAITING_FOR_OWNED_LISTENER'
    foreach ($attempt in 1..30) {
        Start-Sleep -Milliseconds 500
        if ($started.HasExited) { $lastHealth = 'PHP_EXITED'; break }
        $owner = Get-CanonicalOwner
        # Start-Process may return a wrapper PID; verify actual listener and router.
        if ($owner) {
            try {
                Assert-Http
                $ready = $true
                break
            } catch {
                $lastHealth = 'HTTP_BOOTING'
            }
        }
    }
    if (-not $ready) { throw ('PI_START_FAILED_' + $lastHealth + '_LOG_' + $err) }
    Write-Host ('PI_RUNTIME=STARTED_PID_' + $started.Id)
}

function Open-Window {
    $edgeCandidates = @()
    $pf86 = [Environment]::GetEnvironmentVariable('ProgramFiles(x86)')
    if ($pf86) { $edgeCandidates += (Join-Path $pf86 'Microsoft\Edge\Application\msedge.exe') }
    if ($env:ProgramFiles) { $edgeCandidates += (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe') }
    $edge = $edgeCandidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
    if ($edge) {
        Start-Process -FilePath $edge -ArgumentList ('--app=' + $Url + '/index.html')
        Write-Host 'PI_WINDOW=EDGE_APP_MODE'
    } else {
        Start-Process ($Url + '/index.html')
        Write-Host 'PI_WINDOW=DEFAULT_BROWSER'
    }
}

function Install-Shortcut {
    Assert-ReleaseReady
    $expected = Join-Path (Split-Path $AppRoot -Parent) 'scripts\pi-windows-app.ps1'
    if (-not [IO.Path]::GetFullPath($expected).Equals(
        [IO.Path]::GetFullPath($PSCommandPath),[StringComparison]::OrdinalIgnoreCase)) {
        throw 'PI_SHORTCUT_NOT_IN_CANONICAL_RELEASE'
    }
    $dirs = @([Environment]::GetFolderPath('Desktop'),
        (Join-Path ([Environment]::GetFolderPath('StartMenu')) 'Programs'))
    foreach ($dir in $dirs) {
        if (-not (Test-Path -LiteralPath $dir -PathType Container)) { throw 'PI_SHORTCUT_DIR_MISSING' }
        if (Test-Path -LiteralPath (Join-Path $dir 'Planning Intelligence.lnk')) {
            throw 'PI_SHORTCUT_ALREADY_EXISTS_REFUSE_OVERWRITE'
        }
    }
    $shell = New-Object -ComObject WScript.Shell
    $powershell = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
    foreach ($dir in $dirs) {
        $shortcut = $shell.CreateShortcut((Join-Path $dir 'Planning Intelligence.lnk'))
        $shortcut.TargetPath = $powershell
        $shortcut.Arguments = ('-NoProfile -WindowStyle Hidden -ExecutionPolicy RemoteSigned -File "' +
            $PSCommandPath + '" -Action Open')
        $shortcut.WorkingDirectory = $AppRoot
        $shortcut.Description = 'Planning Intelligence, satu aplikasi di 127.0.0.1:8820'
        $shortcut.Save()
    }
    Write-Host 'PI_SHORTCUTS=DESKTOP_AND_START_MENU_INSTALLED'
}

$mutex = [System.Threading.Mutex]::new($false,'Local\PI_Single_Runtime_8820')
$lock = $false
try {
    $lock = $mutex.WaitOne(15000)
    if (-not $lock) { throw 'PI_RUNTIME_LOCK_TIMEOUT' }
    if ($Action -eq 'Status') {
        $owner = Get-CanonicalOwner
        if (-not $owner) { Write-Host 'PI_STATUS=NOT_RUNNING' }
        else {
            Assert-Http
            Write-Host ('PI_STATUS=RUNNING_PID_' + $owner.ProcessId)
        }
    } elseif ($Action -eq 'Stop') {
        $owner = Get-CanonicalOwner
        if (-not $owner) { Write-Host 'PI_RUNTIME=ALREADY_STOPPED' }
        else {
            Stop-Process -Id $owner.ProcessId -ErrorAction Stop
            Write-Host 'PI_RUNTIME=STOP_REQUESTED'
        }
    } elseif ($Action -eq 'InstallShortcut') {
        Install-Shortcut
    } else {
        Start-Canonical
        if ($Action -eq 'Open') { Open-Window }
    }
} finally {
    if ($lock) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}

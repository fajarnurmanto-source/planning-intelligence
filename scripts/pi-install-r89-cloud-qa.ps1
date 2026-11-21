#requires -Version 5.1
<#
  R89 Cloud BYOK installer - isolated Windows QA on port 8823.
  No merges, main/R88 worktree changes, database migrations or copied API keys.
  Creates only a NEW git worktree, NEW SQLite backup and NEW .env.
#>
[CmdletBinding()]
param(
    [string] $SourceRepoRoot = (Join-Path $env:USERPROFILE '.codex\worktrees\pi-r87-lean-runtime\PI_GITHUB_BRIDGE_20261108'),
    [string] $CaseRoot = 'D:\Research\PlanningIntelligence',
    [string] $Branch = 'codex/r89-user-configurable-cloud-semantic-20261121',
    [Parameter(Mandatory = $true)][ValidatePattern('^[a-fA-F0-9]{40}$')]
    [string] $ExpectedCommit,
    [int] $Port = 8823,
    [switch] $StartServer
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

function Require-File {
    param([string] $Path, [string] $Code)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw ("{0}: {1}" -f $Code, $Path)
    }
}

function Require-Tool {
    param([string] $Name)
    $cmd = Get-Command $Name -ErrorAction SilentlyContinue
    if ($null -eq $cmd) {
        throw ("REQUIRED_TOOL_MISSING: {0}" -f $Name)
    }
    return $cmd.Source
}

function Read-QA-Path {
    param([string] $EnvFile, [string] $Name)
    $regex = '^\s*' + [regex]::Escape($Name) + '\s*=\s*(.*)$'
    $line = @(Get-Content -LiteralPath $EnvFile | Where-Object { $_ -match $regex } | Select-Object -Last 1)
    if ($line.Count -ne 1) {
        throw ("R88_QA_ENV_KEY_MISSING: {0}" -f $Name)
    }
    $value = ([regex]::Match($line[0], $regex)).Groups[1].Value.Trim()
    $value = $value.Trim('"').Trim("'")
    if ([string]::IsNullOrWhiteSpace($value) -or $value.Contains([char]10) -or $value.Contains([char]13)) {
        throw ("R88_QA_ENV_KEY_EMPTY: {0}" -f $Name)
    }
    return $value
}

function Stop-On-CommandError {
    param([string] $Message)
    if ($LASTEXITCODE -ne 0) {
        throw $Message
    }
}

$null = Require-Tool 'git'
$Php = Require-Tool 'php'
$null = Require-Tool 'composer'
if ($Port -ne 8823) { throw 'R89_QA_PORT_MUST_BE_8823' }

# Before any write: assert true PI repository and known immutable inputs.
if (-not (Test-Path -LiteralPath $SourceRepoRoot -PathType Container)) {
    throw 'R88_REPOSITORY_NOT_FOUND'
}
$ResolvedRepoRoot = (& git -C $SourceRepoRoot rev-parse --show-toplevel)
Stop-On-CommandError 'GIT_REPOSITORY_NOT_ACCESSIBLE'
$ResolvedRepoRoot = ([string]$ResolvedRepoRoot).Trim()
$remote = ([string](& git -C $ResolvedRepoRoot remote get-url origin)).Trim()
Stop-On-CommandError 'GIT_REMOTE_NOT_ACCESSIBLE'
if ($remote -notmatch 'fajarnurmanto-source[/\\:]planning-intelligence(\.git)?$') {
    throw 'NOT_EXPECTED_PI_GITHUB_REPOSITORY'
}

$WorktreesRoot = Split-Path -Path (Split-Path -Path $ResolvedRepoRoot -Parent) -Parent
if ((Split-Path -Path $WorktreesRoot -Leaf) -ne 'worktrees') {
    throw 'WORKTREE_BASE_UNEXPECTED'
}
$TargetWorktree = Join-Path $WorktreesRoot 'pi-r89-cloud-qa\PI_GITHUB_BRIDGE_20261108'
$TargetApp = Join-Path $TargetWorktree 'laravel'
$OldQaEnv = Join-Path $ResolvedRepoRoot 'laravel\.env'
$OldQaDb = Join-Path $CaseRoot '08_RUNTIME\R88_QA_20261121\r88-qa.sqlite'
$QaRuntime = Join-Path $CaseRoot '08_RUNTIME\R89_CLOUD_QA_20261121'
$NewQaDb = Join-Path $QaRuntime 'r89-qa.sqlite'
$SharedEvidenceRoot = Join-Path $WorktreesRoot '08_RUNTIME'

if ((Test-Path -LiteralPath $TargetWorktree) -or (Test-Path -LiteralPath $QaRuntime)) {
    throw 'R89_TARGET_EXISTS_REFUSING_TO_REPLACE'
}
Require-File $OldQaEnv 'R88_QA_ENV_NOT_FOUND'
Require-File $OldQaDb 'R88_QA_DATABASE_NOT_FOUND'

$Ledger = Read-QA-Path $OldQaEnv 'PI_REGULATORY_LEDGER_PATH'
$Scope = Read-QA-Path $OldQaEnv 'PI_REGULATORY_SCOPE_PATH'
Require-File $Ledger 'R70C_LEDGER_NOT_FOUND'
Require-File $Scope 'R70D_SCOPE_NOT_FOUND'

$required = @(
    'R81_NATIONAL_PDF_SNAPSHOTS\observed-manifest.json',
    'R81_NATIONAL_PDF_SNAPSHOTS\pdf\PP-81-2012.pdf',
    'R81_NATIONAL_PDF_SNAPSHOTS\pdf\UU-18-2008.pdf',
    'R82_NATIONAL_PASSAGE_CANDIDATES\candidate-manifest.json'
)
foreach ($relative in $required) {
    Require-File (Join-Path $SharedEvidenceRoot $relative) 'R81_R82_QA_FILE_MISSING'
}
$knownHashes = @{
    'PP-81-2012.pdf' = '542CE23457FE19529887D3B0BD4B14FAAE7F6F1E269CBFC5B840E117B2CDEE53'
    'UU-18-2008.pdf' = 'DC7735DB65D6DEDC650FF78C36B6C6F076CF931861B005EDAB03F30E513026F4'
}
foreach ($name in @($knownHashes.Keys)) {
    $file = Join-Path $SharedEvidenceRoot ('R81_NATIONAL_PDF_SNAPSHOTS\pdf\' + $name)
    if ((Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash -ne $knownHashes[$name]) {
        throw ("OFFICIAL_PDF_HASH_MISMATCH: {0}" -f $name)
    }
}
& $Php -r 'exit((class_exists("SQLite3") && PHP_VERSION_ID >= 80300) ? 0 : 1);'
Stop-On-CommandError 'PHP_8_3_SQLITE3_REQUIRED'

if ($StartServer) {
    $listener = New-Object System.Net.Sockets.TcpListener([Net.IPAddress]::Loopback, $Port)
    try {
        $listener.Start()
    } catch {
        throw 'R89_QA_PORT_ALREADY_IN_USE'
    } finally {
        $listener.Stop()
    }
}

# Fetch exact GitHub revision without resetting any checked-out branch.
Write-Host 'R89_INSTALL_STAGE=FETCH_PINNED_SOURCE' -ForegroundColor Cyan
& git -C $ResolvedRepoRoot fetch --no-tags origin ("refs/heads/" + $Branch)
Stop-On-CommandError 'R89_GIT_FETCH_FAILED'
$Fetched = ([string](& git -C $ResolvedRepoRoot rev-parse FETCH_HEAD)).Trim()
Stop-On-CommandError 'R89_GIT_FETCH_HEAD_UNAVAILABLE'
if (-not $Fetched.Equals($ExpectedCommit, [StringComparison]::OrdinalIgnoreCase)) {
    throw ("R89_COMMIT_MISMATCH_EXPECTED_{0}_GOT_{1}" -f $ExpectedCommit, $Fetched)
}
Write-Host 'R89_SOURCE_PIN=VERIFIED'

New-Item -ItemType Directory -Path (Split-Path -Path $TargetWorktree -Parent) -Force | Out-Null
& git -C $ResolvedRepoRoot worktree add --detach $TargetWorktree $Fetched
Stop-On-CommandError 'R89_WORKTREE_ADD_FAILED'
$Actual = ([string](& git -C $TargetWorktree rev-parse HEAD)).Trim()
Stop-On-CommandError 'R89_WORKTREE_HEAD_UNAVAILABLE'
if (-not $Actual.Equals($ExpectedCommit, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'R89_WORKTREE_SHA_DRIFT'
}
Write-Host 'R89_ISOLATED_WORKTREE=VERIFIED'

# Consistent SQLite3::backup from the running R88 QA; original opened READONLY.
New-Item -ItemType Directory -Path $QaRuntime -Force | Out-Null
$BackupTool = Join-Path $TargetWorktree 'scripts\pi-r89-backup-sqlite.php'
Require-File $BackupTool 'R89_BACKUP_TOOL_MISSING'
& $Php $BackupTool $OldQaDb $NewQaDb
Stop-On-CommandError 'R89_SQLITE_CLONE_FAILED'

# Fresh secret, fresh env; NO copying of source .env, API keys or cloud vault.
$random = [Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $bytes = New-Object byte[] 32
    $random.GetBytes($bytes)
    $key = [Convert]::ToBase64String($bytes)
} finally {
    $random.Dispose()
}
$envContent = @(
    'APP_NAME="Planning Intelligence R89 Cloud QA"',
    'APP_ENV=local',
    ('APP_KEY=base64:' + $key),
    'APP_DEBUG=false',
    ('APP_URL=http://127.0.0.1:' + $Port),
    'LOG_CHANNEL=stack',
    'LOG_LEVEL=warning',
    'DB_CONNECTION=sqlite',
    ('DB_DATABASE=' + $NewQaDb.Replace('\','/')),
    'SESSION_DRIVER=file',
    'CACHE_STORE=file',
    'QUEUE_CONNECTION=sync',
    'PI_TENANT_ID=ID-DIY-YOG',
    ('PI_REGULATORY_LEDGER_PATH=' + $Ledger.Replace('\','/')),
    ('PI_REGULATORY_SCOPE_PATH=' + $Scope.Replace('\','/')),
    'PI_R89_CLOUD_ENABLED=false'
) -join [Environment]::NewLine

$NewEnv = Join-Path $TargetApp '.env'
if (Test-Path -LiteralPath $NewEnv) { throw 'NEW_WORKTREE_ENV_ALREADY_PRESENT' }
[IO.File]::WriteAllText($NewEnv, $envContent + [Environment]::NewLine, (New-Object Text.UTF8Encoding($false)))
foreach ($relative in @('bootstrap\cache','storage\framework\cache','storage\framework\sessions','storage\framework\views','storage\logs')) {
    New-Item -ItemType Directory -Path (Join-Path $TargetApp $relative) -Force | Out-Null
}
Write-Host 'R89_NEW_ENV=NO_CLOUD_KEY_COPIED'
Write-Host 'R89_CLOUD_INFERENCE=DISABLED_BY_DEFAULT'

# Composer install only. NEVER run composer setup or php artisan migrate.
Write-Host 'R89_INSTALL_STAGE=COMPOSER_DEPENDENCIES' -ForegroundColor Cyan
& composer install ("--working-dir=" + $TargetApp) --prefer-dist --no-interaction --no-progress
Stop-On-CommandError 'R89_COMPOSER_INSTALL_FAILED'

Push-Location $TargetApp
try {
    $routes = & $Php artisan route:list --path=qa-superior-r89 --json
    Stop-On-CommandError 'R89_ROUTE_LIST_FAILED'
    $routesJson = @($routes | ConvertFrom-Json)
    $uris = @($routesJson | ForEach-Object { $_.uri })
    if (-not ($uris -contains 'api/qa-superior-r89/semantic-preview') -or
        -not ($uris -contains 'api/qa-superior-r89/semantic-candidates')) {
        throw 'R89_SEMANTIC_API_ROUTES_MISSING'
    }
    Write-Host 'R89_PREVIEW_AND_INFERENCE_ROUTES=VERIFIED'

    # phpunit.xml uses in-memory SQLite; never tests against copied QA DB.
    & $Php vendor/bin/phpunit --filter 'R89SemanticRegulatoryAnalysisTest|HybridSemanticReasonerTest|RegulatoryPlanningCrosswalkTest'
    Stop-On-CommandError 'R89_FOCUSED_PHPUNIT_FAILED'
    Write-Host 'R89_FOCUSED_TESTS=PASS'
} finally {
    Pop-Location
}

if ($StartServer) {
    $Log = Join-Path $QaRuntime 'server-8823.log'
    $Err = Join-Path $QaRuntime 'server-8823-error.log'
    $Process = Start-Process -FilePath $Php -ArgumentList @('artisan','serve','--host=127.0.0.1',('--port=' + $Port)) -WorkingDirectory $TargetApp -RedirectStandardOutput $Log -RedirectStandardError $Err -PassThru
    Start-Sleep -Seconds 2
    if ($Process.HasExited) {
        throw ("R89_SERVER_EXITED_LOG_{0}" -f $Err)
    }
    Write-Host ('R89_LOCAL_SERVER_PID=' + $Process.Id)
    Write-Host ('R89_LOCAL_URL=http://127.0.0.1:' + $Port + '/development-case-r85.html')
}

Write-Host 'R89_WINDOWS_ISOLATED_QA_INSTALL=PASS' -ForegroundColor Green
Write-Host 'R88_EXISTING_WORKTREE=UNTOUCHED'
Write-Host 'R88_QA_SQLITE=READ_ONLY'
Write-Host 'MAIN_PRODUCTION_DATABASE=UNTOUCHED'
Write-Host 'R89_PROVIDER_KEYS=NOT_INSTALLED'
Write-Host 'R89_LIVE_AI=NOT_RUN'
Write-Host 'R89_PRODUCTION_ADMISSION=DENIED'

#requires -Version 5.1
<#
PI R90 strict READ-ONLY cutover risk ledger. This is NOT a deployment script.
Compare original R74 installed 8820 Laravel and SHA-pinned R88/R89 PR checkout.
Never boot, migrate, copy, edit, stop or start any product/runtime process.
Never open .env, SQLite contents, APP_KEY, provider keys or session data.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')][string]$ExpectedCommit,
    [string]$AppRoot='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$original='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
if ($AppRoot -ne $original) {throw 'PI_R90_MAIN_ROOT_NOT_PROTECTED_ORIGINAL'}
if (-not (Test-Path -LiteralPath (Join-Path $AppRoot 'artisan') -PathType Leaf) -or
    -not (Test-Path -LiteralPath (Join-Path $CandidateRepo 'laravel\artisan') -PathType Leaf)) {
    throw 'PI_R90_LARAVEL_CANDIDATE_OR_MAIN_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if ($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R90_CANDIDATE_COMMIT_MISMATCH'
}
$dirty=@(& $git -C $CandidateRepo status --porcelain)
if ($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0) {
    throw 'PI_R90_CANDIDATE_WORKTREE_NOT_CLEAN'
}
Write-Output ('PI_R90_CANDIDATE_PIN='+$head)
Write-Output 'PI_R90_MODE=READ_ONLY'
$protected=@('database\database.sqlite','.env','artisan')
foreach($relative in $protected) {
    if (-not (Test-Path -LiteralPath (Join-Path $AppRoot $relative) -PathType Leaf)) {
        throw ('PI_R90_PROTECTED_MAIN_FILE_MISSING='+$relative)
    }
}
Write-Output 'PI_R90_PROTECTED_MAIN_DB_ENV_ARTISAN=EXIST'
# Composer scripts are NEVER run on the original user directory.
$origComposer=Join-Path $AppRoot 'composer.json'
$candidateComposer=Join-Path $CandidateRepo 'laravel\composer.json'
if (-not (Test-Path -LiteralPath $origComposer -PathType Leaf) -or
    -not (Test-Path -LiteralPath $candidateComposer -PathType Leaf)) {
    throw 'PI_R90_COMPOSER_MANIFEST_MISSING'
}
$a=Get-Content -LiteralPath $origComposer -Raw | ConvertFrom-Json
$b=Get-Content -LiteralPath $candidateComposer -Raw | ConvertFrom-Json
$origRequires=@{}
$targetRequires=@{}
foreach($item in @($a.require.PSObject.Properties)) {
    $origRequires[[string]$item.Name]=[string]$item.Value
}
foreach($item in @($b.require.PSObject.Properties)) {
    $targetRequires[[string]$item.Name]=[string]$item.Value
}
$depDelta=New-Object 'System.Collections.Generic.List[string]'
foreach($pkg in @($targetRequires.Keys | Sort-Object)) {
    if (-not $origRequires.ContainsKey($pkg)) {
        $depDelta.Add('NEW:'+ $pkg)
    } elseif ($origRequires[$pkg] -cne $targetRequires[$pkg]) {
        $depDelta.Add('CONSTRAINT_CHANGED:'+ $pkg)
    }
}
foreach($pkg in @($origRequires.Keys | Sort-Object)) {
    if (-not $targetRequires.ContainsKey($pkg)) {
        $depDelta.Add('REMOVED:'+ $pkg)
    }
}
Write-Output ('PI_R90_COMPOSER_REQUIREMENT_DELTA_COUNT='+$depDelta.Count)
foreach($item in $depDelta) {Write-Output ('PI_R90_COMPOSER_REQUIREMENT_DELTA='+$item)}
$setup=$b.scripts.PSObject.Properties['setup']
if ($null -ne $setup) {
    $scriptText=@($setup.Value) -join ' '
    if ($scriptText -match '(?i)(key:generate|migrate|composer install|npm install)') {
        Write-Output 'PI_R90_COMPOSER_SETUP_UNSAFE_FOR_MAIN=YES_DO_NOT_RUN'
    }
}
$origLock=Join-Path $AppRoot 'composer.lock'
$candidateLock=Join-Path $CandidateRepo 'laravel\composer.lock'
if ((Test-Path -LiteralPath $origLock -PathType Leaf) -and
    (Test-Path -LiteralPath $candidateLock -PathType Leaf)) {
    $lockSame=(Get-FileHash -LiteralPath $origLock -Algorithm SHA256).Hash -ceq
        (Get-FileHash -LiteralPath $candidateLock -Algorithm SHA256).Hash
    Write-Output ('PI_R90_COMPOSER_LOCK_IDENTICAL='+[string]$lockSame)
} else {
    Write-Output 'PI_R90_COMPOSER_LOCK_COMPARISON=INCOMPLETE'
}
$features=[ordered]@{
    'AUTH_ROUTE'='routes\web.php'
    'AUTH_SESSION_CONTROLLER'='app\Http\Controllers\Auth\SessionController.php'
    'TENANT_MIDDLEWARE'='app\Http\Middleware\EnsurePiTenantAccess.php'
    'R85_DEVELOPMENT_CASE'='app\Http\Controllers\Api\GovernmentDevelopmentCaseController.php'
    'R86_REGULATORY_LIFECYCLE'='app\Http\Controllers\Api\RegulatoryLifecycleController.php'
    'R88_PLANNING_CROSSWALK'='app\Http\Controllers\Api\RegulatoryPlanningCrosswalkController.php'
    'R89_SEMANTIC_CONTROLLER'='app\Http\Controllers\Api\R89SemanticRegulatoryController.php'
    'R89_CLOUD_INFERENCE'='app\Http\Controllers\Api\AiCloudInferenceController.php'
    'R89_PROVIDER_SETTINGS'='app\Http\Controllers\Api\AiProviderSettingsController.php'
    'ORIGINAL_LOGIN_HTML'='public\login.html'
    'ORIGINAL_LOGIN_JS'='public\login.js'
    'INSTITUTIONAL_HOME'='public\index.html'
}
$identical=0;$different=0;$missing=0;$candidateMissing=0
foreach($feature in $features.Keys) {
    $relative=$features[$feature]
    $live=Join-Path $AppRoot $relative
    $next=Join-Path (Join-Path $CandidateRepo 'laravel') $relative
    if (-not (Test-Path -LiteralPath $next -PathType Leaf)) {
        $candidateMissing++
        Write-Output ('PI_R90_FEATURE_'+$feature+'=CANDIDATE_NOT_TRACKED')
    } elseif (-not (Test-Path -LiteralPath $live -PathType Leaf)) {
        $missing++
        Write-Output ('PI_R90_FEATURE_'+$feature+'=MISSING_ON_MAIN')
    } elseif ((Get-FileHash -LiteralPath $live -Algorithm SHA256).Hash -ceq
        (Get-FileHash -LiteralPath $next -Algorithm SHA256).Hash) {
        $identical++
        Write-Output ('PI_R90_FEATURE_'+$feature+'=IDENTICAL')
    } else {
        $different++
        Write-Output ('PI_R90_FEATURE_'+$feature+'=DIFFERENT')
    }
}
Write-Output ('PI_R90_PRIORITY_FEATURES_IDENTICAL='+$identical)
Write-Output ('PI_R90_PRIORITY_FEATURES_DIFFERENT='+$different)
Write-Output ('PI_R90_PRIORITY_FEATURES_MISSING_ON_MAIN='+$missing)
Write-Output ('PI_R90_PRIORITY_FEATURES_NOT_IN_CANDIDATE='+$candidateMissing)
# Enumerate ALL files missing from the protected original, not just priority
# samples. The earlier parity script bounded differences to 24 and could hide
# 7/8 missing entries behind other DIF copies.
$trackedRuntime=@(& $git -C $CandidateRepo ls-files -- laravel/app laravel/routes laravel/config laravel/public laravel/bootstrap/app.php)
if ($LASTEXITCODE -ne 0) {throw 'PI_R90_ALL_RUNTIME_CENSUS_FAILED'}
$allMissing=0
$candidateIndex=@{}
foreach ($entry in $trackedRuntime) {
    $p=[string]$entry
    if (-not $p.StartsWith('laravel/',[StringComparison]::Ordinal)) {continue}
    $relative=$p.Substring(8)
    if ($relative -match '(?i)(^|/)(database|storage|vendor|node_modules|secrets|private)(/|$)') {continue}
    $candidateIndex[$relative.ToLowerInvariant()]=$true
    $live=Join-Path $AppRoot ($relative -replace '/','\')
    if (-not (Test-Path -LiteralPath $live -PathType Leaf)) {
        $allMissing++
        Write-Output ('PI_R90_RUNTIME_FILE_MISSING_ON_MAIN='+$relative)
    }
}
Write-Output ('PI_R90_RUNTIME_FILES_MISSING_ON_MAIN_TOTAL='+$allMissing)
# Protect original-only code/files: a wholesale source overwrite could erase
# features not represented by the candidate branch. Count names only.
$mainOnlyCount=0
$mainOnlySamples=0
foreach ($section in @('app','routes','config','public')) {
    $folder=Join-Path $AppRoot $section
    if (-not (Test-Path -LiteralPath $folder -PathType Container)) {continue}
    foreach($file in @(Get-ChildItem -LiteralPath $folder -File -Recurse -ErrorAction Stop)) {
        $rel=$file.FullName.Substring($AppRoot.Length).TrimStart('\','/') -replace '\','/'
        if ($rel -match '(?i)(^|/)(database|storage|vendor|node_modules|secrets|private)(/|$)') {continue}
        if (-not $candidateIndex.ContainsKey($rel.ToLowerInvariant())) {
            $mainOnlyCount++
            if ($mainOnlySamples -lt 12) {
                Write-Output ('PI_R90_MAIN_ONLY_RUNTIME_SAMPLE='+$rel)
                $mainOnlySamples++
            }
        }
    }
}
Write-Output ('PI_R90_MAIN_ONLY_RUNTIME_FILES='+$mainOnlyCount)

# Inspect migration filenames and hashes, never connect or migrate the main DB.
$migrationDir=Join-Path $CandidateRepo 'laravel\database\migrations'
$migrations=@(& $git -C $CandidateRepo ls-files -- laravel/database/migrations)
if ($LASTEXITCODE -ne 0) {throw 'PI_R90_MIGRATION_CENSUS_GIT_FAILED'}
$mTotal=0;$mSame=0;$mDiff=0;$mMissing=0
foreach($p in $migrations) {
    $name=[string]$p
    if (-not $name.StartsWith('laravel/database/migrations/',[StringComparison]::Ordinal)) {continue}
    $basename=[IO.Path]::GetFileName($name)
    $next=Join-Path $migrationDir $basename
    $live=Join-Path (Join-Path $AppRoot 'database\migrations') $basename
    if (-not (Test-Path -LiteralPath $next -PathType Leaf)) {continue}
    $mTotal++
    if (-not (Test-Path -LiteralPath $live -PathType Leaf)) {
        $mMissing++
        Write-Output ('PI_R90_MIGRATION_MISSING_ON_MAIN='+$basename)
    } elseif ((Get-FileHash -LiteralPath $next -Algorithm SHA256).Hash -ceq
        (Get-FileHash -LiteralPath $live -Algorithm SHA256).Hash) {
        $mSame++
    } else {
        $mDiff++
        Write-Output ('PI_R90_MIGRATION_DIFFERENT='+$basename)
    }
}
Write-Output ('PI_R90_MIGRATION_FILES_TRACKED='+$mTotal)
Write-Output ('PI_R90_MIGRATION_FILES_IDENTICAL='+$mSame)
Write-Output ('PI_R90_MIGRATION_FILES_DIFFERENT='+$mDiff)
Write-Output ('PI_R90_MIGRATION_FILES_MISSING_ON_MAIN='+$mMissing)
# No migrations applied, and file parity does not prove schema compatibility.
Write-Output 'PI_R90_SQLITE_SCHEMA_COMPATIBILITY=NOT_YET_PROVEN'
Write-Output 'PI_R90_AUTHENTICATED_R88_R89_ACCEPTANCE=NOT_YET_PROVEN'
Write-Output 'PI_R90_ONE_ORIGIN_CUTOVER=HOLD_PENDING_ISOLATED_ACCEPTANCE'
Write-Output 'PI_R90_NO_COPY_NO_MIGRATION_NO_SECRET_ACCESS=VERIFIED'

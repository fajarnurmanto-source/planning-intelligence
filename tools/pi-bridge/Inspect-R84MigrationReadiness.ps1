#requires -Version 5.1
<#
PI R84: READ-ONLY migration readiness inspection for the canonical Laravel.
Never runs migrate, creates tables, modifies .env, or copies the active DB.
It displays no connection credentials.
#>
[CmdletBinding()]
param(
    [string]$Root = 'D:\Research\PlanningIntelligence',
    [switch]$SelfTest
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$Migration = '2026_11_15_150000_create_regulatory_evidence_reviews_table'

function Get-R84Status {
    param([string[]]$Lines,[string]$Name)
    $matches = @($Lines | Where-Object { $_.Contains($Name) })
    if ($matches.Count -ne 1) { return 'NOT_UNIQUELY_REPORTED' }
    if ($matches[0] -match '(?i)\bPending\b') { return 'PENDING' }
    if ($matches[0] -match '(?i)\bRan\b') { return 'APPLIED' }
    return 'UNKNOWN'
}

if ($SelfTest) {
    if ((Get-R84Status -Lines @('  2026_11_15_150000_create_regulatory_evidence_reviews_table    Pending') -Name $Migration) -ne 'PENDING') {
        throw 'PI_R84_PRECHECK_PENDING_PARSER_FAILED'
    }
    if ((Get-R84Status -Lines @('  2026_11_15_150000_create_regulatory_evidence_reviews_table    [3] Ran') -Name $Migration) -ne 'APPLIED') {
        throw 'PI_R84_PRECHECK_APPLIED_PARSER_FAILED'
    }
    if ((Get-R84Status -Lines @('No migration list') -Name $Migration) -ne 'NOT_UNIQUELY_REPORTED') {
        throw 'PI_R84_PRECHECK_MISSING_PARSER_FAILED'
    }
    Write-Output 'PI_R84_MIGRATION_READINESS_SELFTEST=PASS'
    Write-Output 'PI_R84_ACTIVE_DB_MUTATIONS=NONE'
    return
}

$resolvedRoot = (Resolve-Path -LiteralPath $Root -ErrorAction Stop).Path
$Laravel = Join-Path $resolvedRoot 'planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$Target = Join-Path $Laravel ('database\migrations\' + $Migration + '.php')
$UI = Join-Path $Laravel 'public\regulatory-evidence-review-r84.js'

if (!(Test-Path -LiteralPath (Join-Path $Laravel 'artisan') -PathType Leaf)) {
    throw 'PI_R84_CANONICAL_LARAVEL_NOT_FOUND'
}
if (!(Test-Path -LiteralPath $Target -PathType Leaf)) {
    Write-Output 'PI_R84_MIGRATION_SOURCE=NOT_INSTALLED'
    Write-Output 'PI_R84_MIGRATION_APPROVAL=REQUIRED'
    Write-Output 'PI_R84_ACTIVE_DB_MUTATIONS=NONE'
    return
}
if (!(Test-Path -LiteralPath $UI -PathType Leaf)) {
    Write-Output 'PI_R84_REVIEWER_UI=NOT_INSTALLED'
} else {
    Write-Output 'PI_R84_REVIEWER_UI=SOURCE_PRESENT'
}
$php = Get-Command php -ErrorAction SilentlyContinue
if (!$php) { throw 'PI_R84_PHP_NOT_FOUND' }

Push-Location $Laravel
try {
    Write-Output '=== PI R84 ACTIVE DB READ-ONLY INSPECTION ==='
    & php -l $Target
    if ($LASTEXITCODE -ne 0) { throw 'PI_R84_MIGRATION_SYNTAX_FAILED' }

    # Does NOT apply a migration, seed a database, or create a backup.
    $saved = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = @(& php artisan migrate:status --no-ansi 2>&1 | ForEach-Object { [string]$_ })
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $saved
    }
    if ($exitCode -ne 0) {
        Write-Output 'PI_R84_DATABASE_STATUS=BLOCKED_ACCESS_OR_CONFIG'
        Write-Output 'PI_R84_MIGRATION_APPROVAL=REQUIRED'
        Write-Output 'PI_R84_ACTIVE_DB_MUTATIONS=NONE'
        return
    }

    $state = Get-R84Status -Lines $output -Name $Migration
    Write-Output 'PI_R84_MIGRATION_SOURCE=INSTALLED'
    Write-Output ('PI_R84_MIGRATION_STATUS=' + $state)
    if ($state -eq 'APPLIED') {
        Write-Output 'PI_R84_DATABASE_SCHEMA=PREVIOUSLY_APPLIED_VERIFY_BACKUP_AND_AUTH'
    } elseif ($state -eq 'PENDING') {
        Write-Output 'PI_R84_DATABASE_SCHEMA=NOT_APPLIED'
    } else {
        Write-Output 'PI_R84_DATABASE_SCHEMA=UNVERIFIED'
    }
    Write-Output 'PI_R84_MIGRATION_APPROVAL=REQUIRED_FOR_ANY_NEW_APPLY'
    Write-Output 'PI_R84_ACTIVE_DB_MUTATIONS=NONE'
} finally {
    Pop-Location
}

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

Write-Host ""
Write-Host "Planning Intelligence V0.1A.2 - Apply and Acceptance Test" -ForegroundColor Cyan
Write-Host ""

$branch = (git branch --show-current | Out-String).Trim()
if ($branch -ne "v0.1-provenance-ingestion") {
    throw "Expected branch v0.1-provenance-ingestion but current branch is '$branch'."
}

$statusBefore = git status --short
if ($LASTEXITCODE -ne 0) { throw "Git status failed." }

if (-not (Test-Path ".\backend\alembic\versions\0002_provenance_ingestion.py")) {
    throw "Migration 0002_provenance_ingestion.py is missing."
}

Write-Host "1/7 Creating safety backup..." -ForegroundColor Cyan
& ".\scripts\backup.ps1"
if ($LASTEXITCODE -ne 0) { throw "Backup failed. Migration will not continue." }

function DbScalar([string]$sql) {
    $cmd = 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc ' + "'" + $sql.Replace("'", "'\''") + "'"
    $out = docker compose exec -T db sh -lc $cmd 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Database query failed: $out" }
    return (($out | Out-String).Trim())
}

$documentsBefore = [int](DbScalar "SELECT count(*) FROM documents;")
$regulationsBefore = [int](DbScalar "SELECT count(*) FROM regulations;")
$auditsBefore = [int](DbScalar "SELECT count(*) FROM audits;")
$eventsBefore = [int](DbScalar "SELECT count(*) FROM audit_events;")
$latestHashBefore = DbScalar "SELECT COALESCE(event_hash,'') FROM audit_events ORDER BY id DESC LIMIT 1;"

Write-Host "2/7 Building backend..." -ForegroundColor Cyan
docker compose build backend
if ($LASTEXITCODE -ne 0) { throw "Backend build failed." }

Write-Host "3/7 Recreating backend so Alembic applies migration 0002..." -ForegroundColor Cyan
docker compose up -d --no-deps --force-recreate backend
if ($LASTEXITCODE -ne 0) { throw "Backend recreate failed." }

$backendReady = $false
for ($i = 0; $i -lt 60; $i++) {
    $out = docker compose exec -T frontend sh -c "wget -qO- http://backend:8000/health 2>/dev/null || true"
    if (($out | Out-String) -match '"status"\s*:\s*"ok"') {
        $backendReady = $true
        break
    }
    Start-Sleep -Seconds 1
}
if (-not $backendReady) {
    docker compose logs --tail=100 --no-color backend
    throw "Backend did not become reachable after migration."
}

# Existing nginx can hold the old backend container address after recreate.
docker compose restart frontend | Out-Null
Start-Sleep -Seconds 4

Write-Host "4/7 Verifying schema..." -ForegroundColor Cyan

$alembicVersion = DbScalar "SELECT version_num FROM alembic_version;"
$hasDocumentVersions = DbScalar "SELECT CASE WHEN to_regclass('public.document_versions') IS NULL THEN 0 ELSE 1 END;"
$hasParsingRuns = DbScalar "SELECT CASE WHEN to_regclass('public.parsing_runs') IS NULL THEN 0 ELSE 1 END;"
$hasSourceUnits = DbScalar "SELECT CASE WHEN to_regclass('public.source_units') IS NULL THEN 0 ELSE 1 END;"
$evidenceCols = [int](DbScalar "SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND table_name='evidence' AND column_name IN ('document_version_id','source_unit_id');")

Write-Host "5/7 Verifying V0.0 data preservation and version backfill..." -ForegroundColor Cyan

$documentsAfter = [int](DbScalar "SELECT count(*) FROM documents;")
$regulationsAfter = [int](DbScalar "SELECT count(*) FROM regulations;")
$auditsAfter = [int](DbScalar "SELECT count(*) FROM audits;")
$eventsAfter = [int](DbScalar "SELECT count(*) FROM audit_events;")
$latestHashAfter = DbScalar "SELECT COALESCE(event_hash,'') FROM audit_events ORDER BY id DESC LIMIT 1;"
$versionedDocuments = [int](DbScalar "SELECT count(DISTINCT document_id) FROM document_versions;")
$missingVersions = [int](DbScalar "SELECT count(*) FROM documents d WHERE NOT EXISTS (SELECT 1 FROM document_versions dv WHERE dv.document_id=d.id);")
$currentSnapshotMismatch = [int](DbScalar "SELECT count(*) FROM documents d WHERE NOT EXISTS (SELECT 1 FROM document_versions dv WHERE dv.document_id=d.id AND dv.sha256=d.sha256);")

Write-Host "6/7 Verifying application health..." -ForegroundColor Cyan
$healthOk = $false
try {
    $health = Invoke-RestMethod -Uri "http://localhost:8080/health" -TimeoutSec 10
    $healthOk = ($health.status -eq "ok" -and $health.capability -eq "PROVENANCE_INGESTION_FOUNDATION")
} catch {
    $healthOk = $false
}

$pass = (
    $alembicVersion -eq "0002_provenance_ingestion" -and
    $hasDocumentVersions -eq "1" -and
    $hasParsingRuns -eq "1" -and
    $hasSourceUnits -eq "1" -and
    $evidenceCols -eq 2 -and
    $documentsBefore -eq $documentsAfter -and
    $regulationsBefore -eq $regulationsAfter -and
    $auditsBefore -eq $auditsAfter -and
    $eventsBefore -eq $eventsAfter -and
    $latestHashBefore -eq $latestHashAfter -and
    $versionedDocuments -eq $documentsAfter -and
    $missingVersions -eq 0 -and
    $currentSnapshotMismatch -eq 0 -and
    $healthOk
)

$reportPath = ".\docs\tests\V0.1A_SCHEMA_PROVENANCE_TEST.md"
New-Item -ItemType Directory -Force ".\docs\tests" | Out-Null

$verdict = if ($pass) { "PASS" } else { "FAIL" }
$report = @"
# Planning Intelligence V0.1A - Schema & Provenance Acceptance Test

Test time: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz")
Branch: $branch

## Schema

- Alembic version: $alembicVersion
- document_versions exists: $hasDocumentVersions
- parsing_runs exists: $hasParsingRuns
- source_units exists: $hasSourceUnits
- evidence provenance columns found: $evidenceCols / 2

## Existing V0.0 data preservation

| Entity | Before | After |
|---|---:|---:|
| documents | $documentsBefore | $documentsAfter |
| regulations | $regulationsBefore | $regulationsAfter |
| audits | $auditsBefore | $auditsAfter |
| audit_events | $eventsBefore | $eventsAfter |

Audit Ledger latest hash before: $latestHashBefore

Audit Ledger latest hash after: $latestHashAfter

## Document version backfill

- Documents with at least one immutable version: $versionedDocuments / $documentsAfter
- Documents missing a version: $missingVersions
- Documents whose current checksum is absent from version history: $currentSnapshotMismatch

## Application

- Provenance capability health check: $healthOk

## Verdict

$verdict

V0.1A.2 passes only when the migration is active, all existing V0.0 records are
preserved, every existing document has immutable version provenance, the Audit
Ledger is unchanged by the schema migration, and the application advertises
the provenance-ingestion foundation capability.
"@

Set-Content -LiteralPath $reportPath -Value $report -Encoding UTF8

Write-Host "7/7 Result" -ForegroundColor Cyan
Write-Host ""
if ($pass) {
    Write-Host "V0.1A.2 SCHEMA & PROVENANCE TEST: PASS" -ForegroundColor Green
} else {
    Write-Host "V0.1A.2 SCHEMA & PROVENANCE TEST: FAIL" -ForegroundColor Red
}
Write-Host "Report: $reportPath" -ForegroundColor Cyan

if (-not $pass) { exit 1 }

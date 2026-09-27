$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

$reportPath = Join-Path $root "docs\tests\V0.1A3_DETERMINISTIC_PARSING_TEST.md"
New-Item -ItemType Directory -Force (Split-Path -Parent $reportPath) | Out-Null

function Wait-Health([int]$Seconds = 90) {
    for ($i = 0; $i -lt $Seconds; $i++) {
        try {
            $r = Invoke-RestMethod "http://localhost:8080/health" -TimeoutSec 3
            if ($r.status -eq "ok") { return $r }
        } catch {}
        Start-Sleep -Seconds 1
    }
    throw "Application health did not recover within $Seconds seconds."
}

function DbScalar([string]$Sql) {
    $out = docker compose exec -T db sh -lc ('psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc ' + "'" + $Sql.Replace("'", "'\''") + "'") 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Database query failed: $out" }
    return (($out | Out-String).Trim())
}

Write-Host ""
Write-Host "Planning Intelligence V0.1A.3 - Apply and Acceptance Test" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path ".\backend\app\parser.py")) {
    throw "backend\app\parser.py is missing."
}
if (-not (Test-Path ".\backend\tests\parser_selftest.py")) {
    throw "backend\tests\parser_selftest.py is missing."
}

Write-Host "1/8 Creating safety backup..." -ForegroundColor Cyan
& ".\scripts\backup.ps1"

$beforeProjects = DbScalar "SELECT count(*) FROM jurisdictions;"
$beforePeriods = DbScalar "SELECT count(*) FROM planning_periods;"
$beforeDocuments = DbScalar "SELECT count(*) FROM documents;"
$beforeRegulations = DbScalar "SELECT count(*) FROM regulations;"
$beforeAudits = DbScalar "SELECT count(*) FROM audits;"
$beforeEvents = DbScalar "SELECT count(*) FROM audit_events;"

Write-Host "2/8 Building backend with deterministic parser dependencies..." -ForegroundColor Cyan
docker compose build backend
if ($LASTEXITCODE -ne 0) { throw "Backend build failed." }

Write-Host "3/8 Recreating backend..." -ForegroundColor Cyan
docker compose up -d --no-deps --force-recreate backend
if ($LASTEXITCODE -ne 0) { throw "Backend recreate failed." }

Start-Sleep -Seconds 3
docker compose restart frontend | Out-Null
$health = Wait-Health

Write-Host "4/8 Running pure deterministic parser self-test for PDF/DOCX/XLSX..." -ForegroundColor Cyan
$selftest = docker compose exec -T backend sh -lc 'PYTHONPATH=/app python /app/tests/parser_selftest.py 2>&1'
if ($LASTEXITCODE -ne 0) { throw "Parser self-test failed: $selftest" }
$selftestText = ($selftest | Out-String).Trim()
if ($selftestText -notmatch '"status": "PASS"') {
    throw "Parser self-test did not report PASS: $selftestText"
}

Write-Host "5/8 Locating an immutable registered document version..." -ForegroundColor Cyan
$versionId = DbScalar "SELECT id::text FROM document_versions ORDER BY created_at ASC LIMIT 1;"
if ([string]::IsNullOrWhiteSpace($versionId)) {
    throw "No document version exists. V0.1A.3 integration test requires at least one registered document version."
}

$versionExt = DbScalar "SELECT COALESCE(NULLIF(file_extension,''), regexp_replace(lower(original_name), '^.*\.', '')) FROM document_versions WHERE id = '$versionId'::uuid;"
if ($versionExt -notin @("pdf","docx","xlsx")) {
    throw "The available registered document version uses unsupported extension '$versionExt'. Supported: pdf, docx, xlsx."
}

Write-Host ("   Using document version: " + $versionId + " (" + $versionExt + ")") -ForegroundColor DarkGray

Write-Host "6/8 Parsing the registered version twice to prove reproducibility..." -ForegroundColor Cyan
$first = Invoke-RestMethod -Method Post ("http://localhost:8080/api/document-versions/" + $versionId + "/parse?force=true") -TimeoutSec 120
$second = Invoke-RestMethod -Method Post ("http://localhost:8080/api/document-versions/" + $versionId + "/parse?force=true") -TimeoutSec 120

if ($first.status -ne "SUCCEEDED" -or $second.status -ne "SUCCEEDED") {
    throw "One or both parsing runs did not succeed."
}
if ($first.source_unit_count -le 0) {
    throw "Parsing produced zero source units."
}
if ($first.source_unit_count -ne $second.source_unit_count) {
    throw "Source-unit count differs between repeated parsing runs."
}
if ($first.result_sha256 -ne $second.result_sha256) {
    throw "Result SHA-256 differs between repeated parsing runs."
}
if ($first.run_fingerprint -ne $second.run_fingerprint) {
    throw "Run fingerprint differs for identical input/parser/config."
}

$sample = Invoke-RestMethod ("http://localhost:8080/api/parsing-runs/" + $first.id + "/source-units?limit=10") -TimeoutSec 30
if (@($sample).Count -le 0) {
    throw "Source-unit retrieval returned no rows."
}
foreach ($u in @($sample)) {
    if ([string]::IsNullOrWhiteSpace($u.content_sha256) -or [string]::IsNullOrWhiteSpace($u.unit_fingerprint)) {
        throw "A sampled source unit is missing provenance hashes."
    }
}

Write-Host "7/8 Verifying V0.0/V0.1A.2 data preservation..." -ForegroundColor Cyan
$afterProjects = DbScalar "SELECT count(*) FROM jurisdictions;"
$afterPeriods = DbScalar "SELECT count(*) FROM planning_periods;"
$afterDocuments = DbScalar "SELECT count(*) FROM documents;"
$afterRegulations = DbScalar "SELECT count(*) FROM regulations;"
$afterAudits = DbScalar "SELECT count(*) FROM audits;"
$afterEvents = DbScalar "SELECT count(*) FROM audit_events;"

if ($beforeProjects -ne $afterProjects) { throw "Jurisdiction count changed unexpectedly." }
if ($beforePeriods -ne $afterPeriods) { throw "Planning-period count changed unexpectedly." }
if ($beforeDocuments -ne $afterDocuments) { throw "Document count changed unexpectedly." }
if ($beforeRegulations -ne $afterRegulations) { throw "Regulation count changed unexpectedly." }
if ($beforeAudits -ne $afterAudits) { throw "Audit count changed unexpectedly." }
if ([int]$afterEvents -lt ([int]$beforeEvents + 2)) {
    throw "Expected at least two DOCUMENT_PARSED audit events."
}

$runCount = DbScalar "SELECT count(*) FROM parsing_runs WHERE status='SUCCEEDED';"
$unitCount = DbScalar "SELECT count(*) FROM source_units;"
$parsedEvents = DbScalar "SELECT count(*) FROM audit_events WHERE event_type='DOCUMENT_PARSED';"

Write-Host "8/8 Writing acceptance report..." -ForegroundColor Cyan

$report = @"
# Planning Intelligence V0.1A.3 - Deterministic Document Parsing Acceptance Test

Test time: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz")

## Control objective

Prove that Planning Intelligence can convert immutable registered documents into reproducible, provider-independent source units with granular provenance.

## Health

Status: $($health.status)
Version: $($health.version)
AI provider: $($health.ai_provider)
Capability: $($health.capability)

## Supported deterministic formats

- PDF: PAGE + TEXT_BLOCK
- DOCX: PARAGRAPH + TABLE + TABLE_ROW + TABLE_CELL
- XLSX: SHEET + TABLE_ROW + TABLE_CELL

No generative AI provider is used by the parser.

## Pure parser self-test

```json
$selftestText
```

## Registered-document integration test

Document version: $versionId
Extension: $versionExt

Run 1: $($first.id)
Run 2: $($second.id)

Run fingerprint: $($first.run_fingerprint)
Result SHA-256: $($first.result_sha256)
Source-unit count: $($first.source_unit_count)
Page count: $($first.page_count)

Repeated-run result hash match: $($first.result_sha256 -eq $second.result_sha256)
Repeated-run source-unit-count match: $($first.source_unit_count -eq $second.source_unit_count)
Repeated-run fingerprint match: $($first.run_fingerprint -eq $second.run_fingerprint)

## Persistent state

Succeeded parsing runs: $runCount
Stored source units: $unitCount
DOCUMENT_PARSED audit events: $parsedEvents

## Core-data preservation

| Entity | Before | After | Preserved |
|---|---:|---:|---|
| jurisdictions | $beforeProjects | $afterProjects | $($beforeProjects -eq $afterProjects) |
| planning_periods | $beforePeriods | $afterPeriods | $($beforePeriods -eq $afterPeriods) |
| documents | $beforeDocuments | $afterDocuments | $($beforeDocuments -eq $afterDocuments) |
| regulations | $beforeRegulations | $afterRegulations | $($beforeRegulations -eq $afterRegulations) |
| audits | $beforeAudits | $afterAudits | $($beforeAudits -eq $afterAudits) |

## Verdict

PASS

Planning Intelligence now owns a deterministic ingestion capability that converts immutable document versions into persistent, addressable source units with stable fingerprints, content hashes, format-specific locators, and audit-trail events.

This is structured institutional evidence memory, not generative-AI document Q&A.
"@

Set-Content -LiteralPath $reportPath -Value $report -Encoding UTF8

Write-Host ""
Write-Host "V0.1A.3 DETERMINISTIC DOCUMENT PARSING TEST: PASS" -ForegroundColor Green
Write-Host ("Report: " + $reportPath) -ForegroundColor Cyan


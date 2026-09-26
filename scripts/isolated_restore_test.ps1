$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

if (-not (Test-Path ".env")) {
    throw "Missing .env. Run setup first."
}

$envMap = @{}
Get-Content ".env" | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.*)$') {
        $envMap[$matches[1]] = $matches[2]
    }
}

$dbName = $envMap["POSTGRES_DB"]
$dbUser = $envMap["POSTGRES_USER"]
$dbPass = $envMap["POSTGRES_PASSWORD"]

if ([string]::IsNullOrWhiteSpace($dbName) -or
    [string]::IsNullOrWhiteSpace($dbUser) -or
    [string]::IsNullOrWhiteSpace($dbPass)) {
    throw "POSTGRES settings are incomplete in .env."
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is not available in PATH."
}

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$reportDir = Join-Path $root "docs\tests"
New-Item -ItemType Directory -Force -Path $reportDir | Out-Null
$reportPath = Join-Path $reportDir "V0.0_ISOLATED_RESTORE_TEST.md"

$tempDir = Join-Path $root ("backups\isolated_restore_" + $stamp)
$testContainer = "planning-intelligence-restore-test-" + $stamp

$report = New-Object System.Collections.ArrayList
function AddLine([string]$text) {
    if ($null -eq $text) { $text = "" }
    [void]$report.Add($text)
}

function MainQuery([string]$sql) {
    $out = docker compose exec -T db psql -U $dbUser -d $dbName -Atc $sql 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Main database query failed: $out"
    }
    return (($out | Out-String).Trim())
}

function TestQuery([string]$sql) {
    $out = docker exec $testContainer psql -U $dbUser -d $dbName -Atc $sql 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Restore test database query failed: $out"
    }
    return (($out | Out-String).Trim())
}

function GetCountsMain {
    return [ordered]@{
        jurisdictions = [int](MainQuery "SELECT count(*) FROM jurisdictions;")
        planning_periods = [int](MainQuery "SELECT count(*) FROM planning_periods;")
        documents = [int](MainQuery "SELECT count(*) FROM documents;")
        regulations = [int](MainQuery "SELECT count(*) FROM regulations;")
        audits = [int](MainQuery "SELECT count(*) FROM audits;")
        audit_events = [int](MainQuery "SELECT count(*) FROM audit_events;")
    }
}

function GetCountsTest {
    return [ordered]@{
        jurisdictions = [int](TestQuery "SELECT count(*) FROM jurisdictions;")
        planning_periods = [int](TestQuery "SELECT count(*) FROM planning_periods;")
        documents = [int](TestQuery "SELECT count(*) FROM documents;")
        regulations = [int](TestQuery "SELECT count(*) FROM regulations;")
        audits = [int](TestQuery "SELECT count(*) FROM audits;")
        audit_events = [int](TestQuery "SELECT count(*) FROM audit_events;")
    }
}

$overallPass = $false
$containerCreated = $false

try {
    Write-Host "Checking working database..." -ForegroundColor Cyan

    $dbRunning = docker compose ps --status running -q db
    if ([string]::IsNullOrWhiteSpace(($dbRunning | Out-String))) {
        throw "Working database container is not running. Start the application first."
    }

    $before = GetCountsMain
    $latestBefore = MainQuery "SELECT id::text || '|' || event_hash FROM audit_events ORDER BY id DESC LIMIT 1;"
    $docBefore = MainQuery "SELECT relative_path || '|' || sha256 FROM documents ORDER BY uploaded_at DESC LIMIT 1;"

    if ($before.documents -lt 1) {
        throw "No document exists in the working database. Upload one test document before running this test."
    }

    Write-Host "Creating a fresh backup that includes the uploaded document..." -ForegroundColor Cyan
    & (Join-Path $PSScriptRoot "backup.ps1")

    $latestBackup = Get-ChildItem (Join-Path $root "backups\planning_backup_*.zip") |
        Sort-Object LastWriteTime -Descending |
        Select-Object -First 1

    if ($null -eq $latestBackup) {
        throw "No backup ZIP was created."
    }

    $backupHash = (Get-FileHash $latestBackup.FullName -Algorithm SHA256).Hash

    New-Item -ItemType Directory -Force -Path $tempDir | Out-Null
    Expand-Archive -Path $latestBackup.FullName -DestinationPath $tempDir -Force

    $sqlPath = Join-Path $tempDir "database.sql"
    if (-not (Test-Path $sqlPath)) {
        throw "database.sql is missing from the backup."
    }

    Write-Host "Starting disposable PostgreSQL restore container..." -ForegroundColor Cyan

    $runOutput = docker run --name $testContainer `
        -e ("POSTGRES_DB=" + $dbName) `
        -e ("POSTGRES_USER=" + $dbUser) `
        -e ("POSTGRES_PASSWORD=" + $dbPass) `
        -d pgvector/pgvector:pg16 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create restore test container: $runOutput"
    }
    $containerCreated = $true

    $ready = $false
    for ($i = 1; $i -le 40; $i++) {
        $null = docker exec $testContainer pg_isready -U $dbUser -d $dbName 2>&1
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 1
    }

    if (-not $ready) {
        throw "Restore test PostgreSQL did not become ready."
    }

    Write-Host "Restoring database.sql into the isolated database..." -ForegroundColor Cyan
    Get-Content -LiteralPath $sqlPath | docker exec -i $testContainer psql -U $dbUser -d $dbName -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) {
        throw "Restore of database.sql failed."
    }

    $after = GetCountsTest
    $latestAfter = TestQuery "SELECT id::text || '|' || event_hash FROM audit_events ORDER BY id DESC LIMIT 1;"
    $docAfter = TestQuery "SELECT relative_path || '|' || sha256 FROM documents ORDER BY uploaded_at DESC LIMIT 1;"

    $countsMatch = $true
    foreach ($key in $before.Keys) {
        if ($before[$key] -ne $after[$key]) {
            $countsMatch = $false
        }
    }

    $ledgerMatch = ($latestBefore -eq $latestAfter)
    $documentRecordMatch = ($docBefore -eq $docAfter)

    $documentFileMatch = $false
    $documentFilePath = ""
    $documentExpectedHash = ""

    if (-not [string]::IsNullOrWhiteSpace($docAfter)) {
        $parts = $docAfter -split '\|', 2
        if ($parts.Count -eq 2) {
            $relativePath = $parts[0].Replace("/", "\")
            $documentExpectedHash = $parts[1].Trim()
            $documentFilePath = Join-Path $tempDir $relativePath

            if (Test-Path $documentFilePath) {
                $actualHash = (Get-FileHash $documentFilePath -Algorithm SHA256).Hash
                $documentFileMatch = ($actualHash -ieq $documentExpectedHash)
            }
        }
    }

    $overallPass = $countsMatch -and $ledgerMatch -and $documentRecordMatch -and $documentFileMatch

    AddLine "# Planning Intelligence V0.0 - Isolated Restore Test"
    AddLine ""
    AddLine ("Test time: " + (Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz"))
    AddLine ("Working project: " + $root)
    AddLine ("Backup: " + $latestBackup.FullName)
    AddLine ("Backup size bytes: " + $latestBackup.Length)
    AddLine ("Backup SHA-256: " + $backupHash)
    AddLine ""
    AddLine "## Entity counts"
    AddLine ""
    AddLine "| Entity | Working DB | Restored DB | Match |"
    AddLine "|---|---:|---:|---|"
    foreach ($key in $before.Keys) {
        $match = "YES"
        if ($before[$key] -ne $after[$key]) { $match = "NO" }
        AddLine ("| " + $key + " | " + $before[$key] + " | " + $after[$key] + " | " + $match + " |")
    }
    AddLine ""
    AddLine "## Audit Ledger"
    AddLine ""
    AddLine ("Working latest event: " + $latestBefore)
    AddLine ("Restored latest event: " + $latestAfter)
    AddLine ("Ledger match: " + $ledgerMatch)
    AddLine ""
    AddLine "## Document verification"
    AddLine ""
    AddLine ("Working document record: " + $docBefore)
    AddLine ("Restored document record: " + $docAfter)
    AddLine ("Document record match: " + $documentRecordMatch)
    AddLine ("Backup document path: " + $documentFilePath)
    AddLine ("Expected document SHA-256: " + $documentExpectedHash)
    AddLine ("Document file hash match: " + $documentFileMatch)
    AddLine ""
    AddLine "## Verdict"
    AddLine ""
    if ($overallPass) {
        AddLine "PASS"
        AddLine ""
        AddLine "The backup successfully reconstructed the database in an isolated PostgreSQL container and preserved the latest Audit Ledger hash and uploaded document checksum."
    } else {
        AddLine "FAIL"
        AddLine ""
        AddLine "At least one count, Audit Ledger value, document record, or document checksum did not match."
    }

    $encoding = New-Object System.Text.UTF8Encoding($true)
    [System.IO.File]::WriteAllText(
        $reportPath,
        [string]::Join([Environment]::NewLine, [string[]]$report),
        $encoding
    )

    if ($overallPass) {
        Write-Host ""
        Write-Host "ISOLATED RESTORE TEST: PASS" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "ISOLATED RESTORE TEST: FAIL" -ForegroundColor Red
    }
    Write-Host ("Report: " + $reportPath) -ForegroundColor Cyan
}
finally {
    if ($containerCreated) {
        Write-Host "Removing disposable restore container..." -ForegroundColor Cyan
        docker rm -f -v $testContainer | Out-Null
    }
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

if (-not $overallPass) {
    exit 1
}

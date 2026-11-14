#requires -Version 5.1
<#
PI R82: diagnostics and optional self-repair for the local Poppler text extractor.
Never downloads or rewrites R81 PDFs/manifest. Does not perform OCR.
-InstallWslPoppler explicitly authorizes installing Ubuntu's poppler-utils ONLY
when pdftotext is missing. The default run is read-only apart from temporary
text files (deleted in finally) and an optional R82 candidate report on success.
#>
[CmdletBinding()]
param(
  [string]$Root = 'D:\Research\PlanningIntelligence',
  [switch]$InstallWslPoppler,
  [switch]$SkipBuild,
  [switch]$SelfTest
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

function Convert-R82WslPath {
  param([Parameter(Mandatory=$true)][string]$Path)
  $full = [IO.Path]::GetFullPath($Path)
  if ($full -notmatch '^([A-Za-z]):[\\/]') { throw 'R82_WSL_PATH_NOT_DRIVE_ABSOLUTE' }
  $drive = $Matches[1].ToLowerInvariant()
  $relative = $full.Substring(2).Replace('\','/')
  return ('/mnt/' + $drive + $relative)
}

function Assert-R82PinnedSources {
  param([Parameter(Mandatory=$true)][string]$Root)
  $registry = Join-Path $Root '08_RUNTIME\R81_NATIONAL_PDF_SNAPSHOTS'
  $manifestPath = Join-Path $registry 'observed-manifest.json'
  if (!(Test-Path -LiteralPath $manifestPath -PathType Leaf)) { throw 'R82_R81_MANIFEST_MISSING' }
  $manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
  if ($manifest.schema -ne 'PI_R81_NATIONAL_PDF_OBSERVED_SNAPSHOTS_V1' -or
      $manifest.tenant_id -ne 'ID-DIY-YOG' -or
      $manifest.source_count -ne 2 -or
      $manifest.production_admission -ne 'DENIED' -or
      $manifest.source_passage_verification -ne 'NOT_PERFORMED' -or
      $manifest.legal_harmonization -ne 'NOT_ASSESSED') { throw 'R82_R81_MANIFEST_GUARD_INVALID' }

  $pins = [ordered]@{
    'PP-81-2012' = @{
      file = 'PP-81-2012.pdf'
      hash = '542CE23457FE19529887D3B0BD4B14FAAE7F6F1E269CBFC5B840E117B2CDEE53'
    }
    'UU-18-2008' = @{
      file = 'UU-18-2008.pdf'
      hash = 'DC7735DB65D6DEDC650FF78C36B6C6F076CF931861B005EDAB03F30E513026F4'
    }
  }
  if (@($manifest.sources).Count -ne 2) { throw 'R82_R81_SOURCE_COUNT_MISMATCH' }
  $verified = @()
  foreach ($sourceId in $pins.Keys) {
    $pin = $pins[$sourceId]
    $record = @($manifest.sources | Where-Object { $_.instrument_id -eq $sourceId })
    if ($record.Count -ne 1 -or
        $record[0].relative_path -cne ('pdf/' + $pin.file) -or
        $record[0].sha256_observed -cne $pin.hash -or
        $record[0].acquisition_mode -cne 'OFFICIAL_HTTPS_NO_REDIRECT') {
      throw ('R82_R81_SOURCE_PROVENANCE_MISMATCH=' + $sourceId)
    }
    $file = Join-Path (Join-Path $registry 'pdf') $pin.file
    $actualFile = Get-Item -LiteralPath $file -ErrorAction Stop
    if ($actualFile.PSIsContainer -or $actualFile.Length -lt 128 -or
        $actualFile.Length -gt 32MB -or $actualFile.Length -ne [long]$record[0].bytes) {
      throw ('R82_R81_SOURCE_SIZE_MISMATCH=' + $sourceId)
    }
    $hash = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToUpperInvariant()
    if ($hash -cne $pin.hash) { throw ('R82_R81_SOURCE_SHA256_MISMATCH=' + $sourceId) }
    $verified += [pscustomobject]@{ Id=$sourceId; Path=$actualFile.FullName }
  }
  return $verified
}

function Test-R82WslPoppler {
  if (!(Get-Command wsl.exe -ErrorAction SilentlyContinue)) { return $false }
  $oldPreference = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  try {
    $discard = & wsl.exe -d Ubuntu-24.04 -e sh -c 'command -v pdftotext' 2>$null
    return ($LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace(($discard -join '')))
  }
  catch { return $false }
  finally { $ErrorActionPreference = $oldPreference }
}

function Test-R82TextLayer {
  param(
    [Parameter(Mandatory=$true)]$Document,
    [Parameter(Mandatory=$true)][string]$Stage,
    [string]$NativePdftotext,
    [bool]$WslAvailable
  )
  $attempts = @()
  $target = Join-Path $Stage ($Document.Id + '.txt')
  $methods = @()
  if (![string]::IsNullOrEmpty($NativePdftotext)) { $methods += 'WINDOWS' }
  if ($WslAvailable) { $methods += 'WSL' }
  if ($methods.Count -eq 0) {
    return [pscustomobject]@{ Id=$Document.Id; State='EXTRACTOR_MISSING'; Mode='NONE'; Bytes=0; HasPasal=$false }
  }
  foreach ($method in $methods) {
    if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Force }
    $oldPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
      if ($method -eq 'WINDOWS') {
        $discard = & $NativePdftotext '-layout' '-enc' 'UTF-8' $Document.Path $target 2>$null
      } else {
        $inputPath = Convert-R82WslPath $Document.Path
        $outputPath = Convert-R82WslPath $target
        $discard = & wsl.exe -d Ubuntu-24.04 -e pdftotext -layout -enc UTF-8 $inputPath $outputPath 2>$null
      }
      $exitCode = $LASTEXITCODE
    }
    catch { $exitCode = -1 }
    finally { $ErrorActionPreference = $oldPreference }

    if ($exitCode -ne 0 -or !(Test-Path -LiteralPath $target -PathType Leaf)) {
      $attempts += ($method + '_EXEC_FAIL')
      continue
    }
    $bytes = (Get-Item -LiteralPath $target).Length
    if ($bytes -gt 3MB) {
      $attempts += ($method + '_OUTPUT_TOO_LARGE')
      continue
    }
    $text = [IO.File]::ReadAllText($target,[Text.Encoding]::UTF8)
    if ($text.Length -lt 40 -or $text -notmatch '\p{L}') {
      $attempts += ($method + '_EMPTY_TEXT_LAYER')
      continue
    }
    $hasPasal = $text -match '(?i)\bPasal\s+[0-9]+'
    return [pscustomobject]@{
      Id=$Document.Id; State='TEXT_EXTRACTED'
      Mode=$method; Bytes=$bytes; HasPasal=[bool]$hasPasal
    }
  }
  return [pscustomobject]@{ Id=$Document.Id; State=($attempts -join ','); Mode='NONE'; Bytes=0; HasPasal=$false }
}

function Invoke-R82SelfTest {
  $test = Convert-R82WslPath 'D:\Research\PlanningIntelligence\sample.pdf'
  if ($test -cne '/mnt/d/Research/PlanningIntelligence/sample.pdf') {
    throw 'R82_WSL_PATH_SELFTEST_FAILED'
  }
  $hash = [Security.Cryptography.SHA256]::Create()
  try {
    $actual = ([BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::ASCII.GetBytes('abc')))).Replace('-','')
    if ($actual -cne 'BA7816BF8F01CFEA414140DE5DAE2223B00361A396177A9CB410FF61F20015AD') {
      throw 'R82_SHA_SELFTEST_FAILED'
    }
  } finally { $hash.Dispose() }
  Write-Output 'PI_R82_DIAGNOSTIC_OFFLINE_SELFTEST=PASS'
}

if ($SelfTest) { Invoke-R82SelfTest; return }
$base = (Resolve-Path -LiteralPath $Root -ErrorAction Stop).Path
$documents = @(Assert-R82PinnedSources -Root $base)
Write-Output 'PI_R82_SOURCE_INTEGRITY=PASS_2_OF_2'
$native = Get-Command pdftotext -ErrorAction SilentlyContinue | Select-Object -First 1
$nativeBinary = if ($native) { [string]$native.Source } else { '' }
$wslReady = Test-R82WslPoppler
Write-Output ('PI_R82_POPPLER_WINDOWS=' + $(if ($nativeBinary) { 'AVAILABLE' } else { 'MISSING' }))
Write-Output ('PI_R82_POPPLER_WSL=' + $(if ($wslReady) { 'AVAILABLE' } else { 'MISSING_OR_WSL_UNAVAILABLE' }))

if (!$nativeBinary -and !$wslReady -and $InstallWslPoppler) {
  Write-Output 'PI_R82_POPPLER_INSTALL=OPERATOR_AUTHORIZED_UBUNTU_ONLY'
  if (!(Get-Command wsl.exe -ErrorAction SilentlyContinue)) { throw 'R82_WSL_EXE_MISSING' }
  & wsl.exe -d Ubuntu-24.04 -u root -e sh -c 'apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq poppler-utils'
  if ($LASTEXITCODE -ne 0) { throw 'R82_WSL_POPPLER_INSTALL_FAILED' }
  $wslReady = Test-R82WslPoppler
  if (!$wslReady) { throw 'R82_WSL_POPPLER_NOT_AVAILABLE_AFTER_INSTALL' }
  Write-Output 'PI_R82_POPPLER_WSL=INSTALLED_AND_AVAILABLE'
}

$stage = Join-Path ([IO.Path]::GetTempPath()) ('pi-r82-textdiag-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage -ErrorAction Stop | Out-Null
try {
  $results = @()
  foreach ($doc in $documents) {
    $result = Test-R82TextLayer -Document $doc -Stage $stage -NativePdftotext $nativeBinary -WslAvailable $wslReady
    $results += $result
    Write-Output ('PI_R82_TEXT_PROBE=' + $result.Id + ';STATE=' + $result.State +
      ';METHOD=' + $result.Mode + ';BYTES=' + $result.Bytes +
      ';HAS_PASAL=' + $result.HasPasal)
  }
  if (@($results | Where-Object { $_.State -eq 'TEXT_EXTRACTED' }).Count -ne 2) {
    Write-Output 'PI_R82_DIRECT_TEXT_EXTRACTION=BLOCKED'
    Write-Output 'PI_R82_BUILD_SKIPPED=INSUFFICIENT_TEXT_OR_EXECUTABLE'
    Write-Output 'R81_PDFS_AND_MANIFEST=UNCHANGED'
    return
  }
  Write-Output 'PI_R82_DIRECT_TEXT_EXTRACTION=PASS_2_OF_2'
}
finally {
  if (Test-Path -LiteralPath $stage) {
    Remove-Item -LiteralPath $stage -Force -Recurse -ErrorAction SilentlyContinue
  }
}

if ($SkipBuild) {
  Write-Output 'PI_R82_BUILD_SKIPPED=OPERATOR_REQUEST'
  return
}
$laravel = Join-Path $base 'planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
if (!(Test-Path -LiteralPath (Join-Path $laravel 'artisan'))) { throw 'R82_CANONICAL_LARAVEL_MISSING' }
Push-Location $laravel
try {
  & php artisan pi:r82:build
  if ($LASTEXITCODE -ne 0) {
    Write-Output 'PI_R82_DIRECT_TEXT_PASS_BUT_PHP_EXTRACTION_BLOCKED=YES'
    Write-Output 'CHECK=PHP_SYMFONY_PROCESS_EXECUTION_PATH'
    return
  }
  Write-Output 'PI_R82_LOCAL_REAL_PDF_EXTRACTION=PASS'
}
finally { Pop-Location }

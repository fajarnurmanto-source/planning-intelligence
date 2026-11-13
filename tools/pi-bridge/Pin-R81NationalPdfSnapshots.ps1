#requires -Version 5.1
[CmdletBinding()]
param(
  [string]$Root = 'D:\Research\PlanningIntelligence',
  [string]$Pp81Pdf = '',
  [string]$Uu18Pdf = '',
  [switch]$SelfTest
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
Add-Type -AssemblyName System.Net.Http

function Test-PdfContainer {
  param([string]$Path)
  $file = Get-Item -LiteralPath $Path -ErrorAction Stop
  if ($file.PSIsContainer -or $file.Length -lt 128 -or $file.Length -gt 32MB) {
    throw 'R81_PDF_CONTAINER_SIZE_INVALID'
  }
  $stream = [System.IO.File]::OpenRead($file.FullName)
  try {
    $magic = New-Object byte[] 5
    if ($stream.Read($magic,0,5) -ne 5 -or [Text.Encoding]::ASCII.GetString($magic) -cne '%PDF-') {
      throw 'R81_PDF_MAGIC_INVALID'
    }
    $n = [int][Math]::Min(2048, $stream.Length)
    [void]$stream.Seek(-$n, [IO.SeekOrigin]::End)
    $tail = New-Object byte[] $n
    $read = $stream.Read($tail,0,$n)
    if ([Text.Encoding]::ASCII.GetString($tail,0,$read) -notmatch '%%EOF') {
      throw 'R81_PDF_FOOTER_MISSING'
    }
  } finally { $stream.Dispose() }
  return (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToUpperInvariant()
}

function Get-PinnedOfficialPdf {
  param([string]$Url,[string]$Destination)
  $allowed = @(
    'https://www.peraturan.go.id/files/pp81-2012bt.pdf',
    'https://dpr.go.id/dokjdih/document/uu/UU_2008_18.pdf',
    'https://www.peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
    'https://peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf'
  )
  if ($Url -cnotin $allowed) { throw 'R81_UNTRUSTED_URL' }
  $handler = New-Object Net.Http.HttpClientHandler
  $handler.AllowAutoRedirect = $false
  $handler.UseCookies = $false
  $client = [Net.Http.HttpClient]::new($handler)
  $client.Timeout = [TimeSpan]::FromSeconds(45)
  try {
    $response = $client.GetAsync($Url, [Net.Http.HttpCompletionOption]::ResponseHeadersRead).GetAwaiter().GetResult()
    try {
      if ([int]$response.StatusCode -ne 200) {
        throw ('R81_OFFICIAL_SOURCE_HTTP_' + [int]$response.StatusCode)
      }
      if ($response.Content.Headers.ContentLength -gt 32MB) { throw 'R81_DOWNLOAD_TOO_LARGE' }
      $inputStream = $response.Content.ReadAsStreamAsync().GetAwaiter().GetResult()
      $outputStream = [IO.File]::Open($Destination,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write)
      try {
        $buffer = New-Object byte[] 65536
        $total = [long]0
        while (($count = $inputStream.Read($buffer,0,$buffer.Length)) -gt 0) {
          $total += $count
          if ($total -gt 32MB) { throw 'R81_DOWNLOAD_TOO_LARGE' }
          $outputStream.Write($buffer,0,$count)
        }
      } finally { $outputStream.Dispose(); $inputStream.Dispose() }
    } finally { $response.Dispose() }
  } finally { $client.Dispose(); $handler.Dispose() }
}

function Invoke-OfflineSelfTest {
  $digest = [Security.Cryptography.SHA256]::Create()
  try {
    $actual = ([BitConverter]::ToString($digest.ComputeHash([Text.Encoding]::ASCII.GetBytes('abc')))).Replace('-','')
    if ($actual -cne 'BA7816BF8F01CFEA414140DE5DAE2223B00361A396177A9CB410FF61F20015AD') {
      throw 'R81_DIGEST_SELF_TEST_FAILED'
    }
  } finally { $digest.Dispose() }
  $tmp = Join-Path ([IO.Path]::GetTempPath()) ('pi-r81-selftest-' + [guid]::NewGuid().ToString('N'))
  New-Item -ItemType Directory -Path $tmp -ErrorAction Stop | Out-Null
  try {
    $file = Join-Path $tmp 'synthetic.pdf'
    [IO.File]::WriteAllBytes($file,[Text.Encoding]::ASCII.GetBytes('%PDF-1.4' + ('X' * 200) + '%%EOF'))
    $hash = Test-PdfContainer $file
    if ($hash -notmatch '^[A-F0-9]{64}$') { throw 'R81_PDF_TEST_FAILED' }
    [IO.File]::WriteAllText($file,'not pdf' + ('X' * 200) + '%%EOF')
    try {
      $unexpected = Test-PdfContainer $file
      throw 'R81_INVALID_CONTAINER_ACCEPTED'
    } catch {
      if ($_.Exception.Message -ne 'R81_PDF_MAGIC_INVALID') { throw }
    }
    Write-Output 'PI_R81_OFFLINE_SELFTEST=PASS'
    Write-Output 'NOTE=Container checks only, not PDF text verification'
  } finally { Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue }
}

if ($SelfTest) { Invoke-OfflineSelfTest; return }
if (!(Test-Path -LiteralPath $Root -PathType Container)) { throw 'R81_ROOT_MISSING' }
$base = (Resolve-Path -LiteralPath $Root).Path.TrimEnd('\')
$registry = Join-Path $base '08_RUNTIME\R81_NATIONAL_PDF_SNAPSHOTS'
$finalDir = Join-Path $registry 'pdf'
$manifest = Join-Path $registry 'observed-manifest.json'
$inputs = @(
  @{
    id='PP-81-2012'
    urls=@('https://www.peraturan.go.id/files/pp81-2012bt.pdf')
    file='PP-81-2012.pdf'
    local=$Pp81Pdf
  },
  @{
    id='UU-18-2008'
    urls=@(
      'https://www.peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
      'https://peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
      'https://dpr.go.id/dokjdih/document/uu/UU_2008_18.pdf'
    )
    file='UU-18-2008.pdf'
    local=$Uu18Pdf
  }
)
New-Item -ItemType Directory -Path $registry -Force | Out-Null
$stage = Join-Path $registry ('stage-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage -ErrorAction Stop | Out-Null
try {
  $records = @()
  foreach ($entry in $inputs) {
    $candidate = Join-Path $stage $entry.file
    $mode = 'OFFICIAL_HTTPS_NO_REDIRECT'
    $actualDownloadUrl = $null
    if (![string]::IsNullOrWhiteSpace($entry.local)) {
      $source = (Resolve-Path -LiteralPath $entry.local -ErrorAction Stop).Path
      Copy-Item -LiteralPath $source -Destination $candidate -ErrorAction Stop
      $mode = 'OPERATOR_SUPPLIED_UNVERIFIED_ORIGIN'
    } else {
      $errors = @()
      foreach ($url in $entry.urls) {
        try {
          # Do not accept an unexpected redirect or silently approve another host.
          Get-PinnedOfficialPdf -Url $url -Destination $candidate
          # A 200 response with HTML is not a successful official PDF source.
          $candidateHash = Test-PdfContainer $candidate
          if ($candidateHash -notmatch '^[A-F0-9]{64}
          break
        }
        catch {
          $errors += ($url + ' -> ' + $_.Exception.Message)
          # Only the temporary staging copy may be removed before retry.
          if (Test-Path -LiteralPath $candidate) {
            Remove-Item -LiteralPath $candidate -Force -ErrorAction Stop
          }
          Write-Output ('R81_OFFICIAL_CANDIDATE_FAILED=' + $entry.id + ';URL=' + $url)
        }
      }
      if ($null -eq $actualDownloadUrl) {
        throw ('R81_ACQUISITION_FAILED_' + $entry.id + ': ' + ($errors -join ' | '))
      }
    }
    $sha = Test-PdfContainer $candidate
    $records += [ordered]@{
      instrument_id=$entry.id
      official_reference_url=$entry.urls[0]
      acquisition_url=$actualDownloadUrl
      relative_path=('pdf/' + $entry.file)
      sha256_observed=$sha
      bytes=[long](Get-Item -LiteralPath $candidate).Length
      acquisition_mode=$mode
      source_identity='REVIEW_REQUIRED'
      primary_passage_evidence='NOT_VERIFIED'
      legal_harmonization='NOT_ASSESSED'
    }
    Write-Output ('R81_STAGED=' + $entry.id + ';HASH=' + $sha + ';MODE=' + $mode)
  }

  $data = [ordered]@{
    schema='PI_R81_NATIONAL_PDF_OBSERVED_SNAPSHOTS_V1'
    tenant_id='ID-DIY-YOG'
    source_count=2
    source_identity_certification='NOT_PERFORMED'
    source_passage_verification='NOT_PERFORMED'
    r80_relation_promotion='NOT_PERFORMED'
    legal_harmonization='NOT_ASSESSED'
    production_admission='DENIED'
    sources=$records
  }
  $encoded = ($data | ConvertTo-Json -Depth 8).Trim()
  foreach ($record in $records) {
    $filename = [IO.Path]::GetFileName($record.relative_path)
    $existing = Join-Path $finalDir $filename
    if (Test-Path -LiteralPath $existing) {
      if ((Test-PdfContainer $existing) -cne $record.sha256_observed) { throw ('R81_EXISTING_SNAPSHOT_DRIFT=' + $record.instrument_id) }
    }
  }
  if (Test-Path -LiteralPath $manifest) {
    if ([IO.File]::ReadAllText($manifest).Trim() -cne $encoded) { throw 'R81_MANIFEST_ALREADY_EXISTS_WITH_DIFFERENT_CONTENT' }
  }
  New-Item -ItemType Directory -Path $finalDir -Force | Out-Null
  foreach ($record in $records) {
    $filename = [IO.Path]::GetFileName($record.relative_path)
    $final = Join-Path $finalDir $filename
    if (!(Test-Path -LiteralPath $final)) {
      Copy-Item -LiteralPath (Join-Path $stage $filename) -Destination $final -ErrorAction Stop
    }
    if ((Test-PdfContainer $final) -cne $record.sha256_observed) { throw 'R81_CANONICAL_HASH_FAILED' }
  }
  if (!(Test-Path -LiteralPath $manifest)) {
    $tempManifest = $manifest + '.tmp-' + [guid]::NewGuid().ToString('N')
    [IO.File]::WriteAllText($tempManifest,$encoded + [Environment]::NewLine,[Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $tempManifest -Destination $manifest -ErrorAction Stop
  }
  Write-Output 'PI_R81_NATIONAL_PDF_SNAPSHOTS=2/2'
  Write-Output ('R81_OBSERVED_MANIFEST=' + $manifest)
  Write-Output 'PI_R81_PRIMARY_PASSAGES=NOT_VERIFIED'
  Write-Output 'PI_R81_LEGAL_HARMONIZATION=NOT_ASSESSED'
  Write-Output 'PI_R81_PRODUCTION_ADMISSION=DENIED'
  Write-Output 'PI_R81_LOCAL_CAPTURE=PASS'
} finally {
  if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue }
}
) { throw 'R81_CANDIDATE_HASH_INVALID' }
          $actualDownloadUrl = $url
          break
        }
        catch {
          $errors += ($url + ' -> ' + $_.Exception.Message)
          # Only the temporary staging copy may be removed before retry.
          if (Test-Path -LiteralPath $candidate) {
            Remove-Item -LiteralPath $candidate -Force -ErrorAction Stop
          }
          Write-Output ('R81_OFFICIAL_CANDIDATE_FAILED=' + $entry.id + ';URL=' + $url)
        }
      }
      if ($null -eq $actualDownloadUrl) {
        throw ('R81_ACQUISITION_FAILED_' + $entry.id + ': ' + ($errors -join ' | '))
      }
    }
    $sha = Test-PdfContainer $candidate
    $records += [ordered]@{
      instrument_id=$entry.id
      official_reference_url=$entry.urls[0]
      acquisition_url=$actualDownloadUrl
      relative_path=('pdf/' + $entry.file)
      sha256_observed=$sha
      bytes=[long](Get-Item -LiteralPath $candidate).Length
      acquisition_mode=$mode
      source_identity='REVIEW_REQUIRED'
      primary_passage_evidence='NOT_VERIFIED'
      legal_harmonization='NOT_ASSESSED'
    }
    Write-Output ('R81_STAGED=' + $entry.id + ';HASH=' + $sha + ';MODE=' + $mode)
  }

  $data = [ordered]@{
    schema='PI_R81_NATIONAL_PDF_OBSERVED_SNAPSHOTS_V1'
    tenant_id='ID-DIY-YOG'
    source_count=2
    source_identity_certification='NOT_PERFORMED'
    source_passage_verification='NOT_PERFORMED'
    r80_relation_promotion='NOT_PERFORMED'
    legal_harmonization='NOT_ASSESSED'
    production_admission='DENIED'
    sources=$records
  }
  $encoded = ($data | ConvertTo-Json -Depth 8).Trim()
  foreach ($record in $records) {
    $filename = [IO.Path]::GetFileName($record.relative_path)
    $existing = Join-Path $finalDir $filename
    if (Test-Path -LiteralPath $existing) {
      if ((Test-PdfContainer $existing) -cne $record.sha256_observed) { throw ('R81_EXISTING_SNAPSHOT_DRIFT=' + $record.instrument_id) }
    }
  }
  if (Test-Path -LiteralPath $manifest) {
    if ([IO.File]::ReadAllText($manifest).Trim() -cne $encoded) { throw 'R81_MANIFEST_ALREADY_EXISTS_WITH_DIFFERENT_CONTENT' }
  }
  New-Item -ItemType Directory -Path $finalDir -Force | Out-Null
  foreach ($record in $records) {
    $filename = [IO.Path]::GetFileName($record.relative_path)
    $final = Join-Path $finalDir $filename
    if (!(Test-Path -LiteralPath $final)) {
      Copy-Item -LiteralPath (Join-Path $stage $filename) -Destination $final -ErrorAction Stop
    }
    if ((Test-PdfContainer $final) -cne $record.sha256_observed) { throw 'R81_CANONICAL_HASH_FAILED' }
  }
  if (!(Test-Path -LiteralPath $manifest)) {
    $tempManifest = $manifest + '.tmp-' + [guid]::NewGuid().ToString('N')
    [IO.File]::WriteAllText($tempManifest,$encoded + [Environment]::NewLine,[Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $tempManifest -Destination $manifest -ErrorAction Stop
  }
  Write-Output 'PI_R81_NATIONAL_PDF_SNAPSHOTS=2/2'
  Write-Output ('R81_OBSERVED_MANIFEST=' + $manifest)
  Write-Output 'PI_R81_PRIMARY_PASSAGES=NOT_VERIFIED'
  Write-Output 'PI_R81_LEGAL_HARMONIZATION=NOT_ASSESSED'
  Write-Output 'PI_R81_PRODUCTION_ADMISSION=DENIED'
  Write-Output 'PI_R81_LOCAL_CAPTURE=PASS'
} finally {
  if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue }
}

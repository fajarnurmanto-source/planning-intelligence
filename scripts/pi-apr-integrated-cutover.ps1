#requires -Version 5.1
<#
APR: exact 56-file source-only protected integrated cutover.
Default PREVIEW, -Apply required. No .env/DB/APP_KEY/launcher edit,
migration, new server, Composer, source downloads or cloud inference.
CI fixture and fault injection only inside GitHub Actions RUNNER_TEMP.
#>
[CmdletBinding()]
param(
 [Parameter(Mandatory=$true)][string]$CandidateRepo,
 [Parameter(Mandatory=$true)][ValidatePattern('^[a-fA-F0-9]{40}$')][string]$ExpectedCommit,
 [switch]$Apply,
 [string]$CiFixtureRoot,
 [switch]$CiFailAfterSecondWrite,
 [int]$CiFailAfterWrite=0
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$baseline='a71bac9836654ecc9f313b19f7142ea7bc0aa0c8'
$release='49d07c955add998d40693f5d4139a3fe0268def1'
. (Join-Path $PSScriptRoot 'pi-apr-tinker-contract.ps1')
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$ci=($CiFixtureRoot -ne '')
if($ci){
 if($env:GITHUB_ACTIONS -cne 'true' -or -not $env:RUNNER_TEMP){
  throw 'PI_APR_CUTOVER_CI_FIXTURE_FORBIDDEN'
 }
 $tmp=[IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd('\','/')
 $fixture=[IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd('\','/')
 if(-not $fixture.StartsWith($tmp+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){
  throw 'PI_APR_CUTOVER_FIXTURE_OUTSIDE_RUNNER_TEMP'
 }
 $root=Join-Path $fixture 'laravel'
 $launcher=Join-Path $fixture 'launcher'
 Write-Output 'PI_APR_CUTOVER_TARGET=DISPOSABLE_CI_FIXTURE'
}elseif($CiFailAfterSecondWrite -or $CiFailAfterWrite -ne 0){
 throw 'PI_APR_CUTOVER_FAULT_INJECTION_FORBIDDEN_ON_ORIGINAL'
}else{
 Write-Output 'PI_APR_CUTOVER_TARGET=ORIGINAL_SINGLE_8820'
}
$root=[IO.Path]::GetFullPath($root).TrimEnd('\','/')
$repo=[IO.Path]::GetFullPath($CandidateRepo).TrimEnd('\','/')
$prefix=$root+[IO.Path]::DirectorySeparatorChar
$replace=@(
 'app\Services\Pi\Development\CloudSemanticCandidateProvider.php',
 'app\Services\Pi\Development\R89SemanticRegulatoryAnalysis.php',
 'app\Services\Pi\Regulatory\NormativeVersionTimeline.php',
 'app\Services\Pi\Regulatory\R82NationalEvidencePipeline.php',
 'app\Services\Pi\Regulatory\R82ProvisionTextScanner.php',
 'app\Services\Pi\Regulatory\RegulatoryGenealogyGraph.php',
 'app\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry.php',
 'public\development-case-r85.html',
 'public\index.html',
 'public\institution-home.css',
 'public\institution-home.js',
 'public\native-annex-r106.js',
 'public\official-sources-r100.js',
 'public\pi-auth-shell.js',
 'public\pi-ia-r69e.css',
 'public\regulatory-planning-r88.js',
 'routes\console.php',
 'routes\web.php'
)
$add=@(
 'app\Http\Controllers\Api\HybridPrimaryLegalController.php',
 'app\Jobs\AcquirePinnedRegulation.php',
 'app\Services\Pi\Development\AdaptivePlanningTableEvidence.php',
 'app\Services\Pi\Development\DocumentLayoutReconstructor.php',
 'app\Services\Pi\Development\DocumentStructureProfile.php',
 'app\Services\Pi\Development\FiscalContextDiagnostics.php',
 'app\Services\Pi\Development\FiscalOrganizationContext.php',
 'app\Services\Pi\Development\FiscalRecordReconstruction.php',
 'app\Services\Pi\Development\FullDocumentInventory.php',
 'app\Services\Pi\Development\MultilineFiscalTableReconstructor.php',
 'app\Services\Pi\Development\PlanningFiscalEvidenceCrosswalk.php',
 'app\Services\Pi\Development\PlanningHeaderColumnEvidence.php',
 'app\Services\Pi\Development\PlanningNomenclatureEvidence.php',
 'app\Services\Pi\Development\PlanningPageProfile.php',
 'app\Services\Pi\Development\PlanningSemanticCandidates.php',
 'app\Services\Pi\Development\RegulatoryAnnexBenchmarkProfile.php',
 'app\Services\Pi\Development\SemanticProviderRecovery.php',
 'app\Services\Pi\Regulatory\HriaDelegationBenchmark.php',
 'app\Services\Pi\Regulatory\HriaNegativeControls.php',
 'app\Services\Pi\Regulatory\HybridPrimaryLegalAnalysis.php',
 'app\Services\Pi\Regulatory\HybridPrimaryLegalReasoner.php',
 'app\Services\Pi\Regulatory\RegulatoryAcquisitionRetryPolicy.php',
 'app\Services\Pi\Regulatory\RegulatoryAlternativeSourceDiscovery.php',
 'app\Services\Pi\Regulatory\RegulatoryFreshnessAssessment.php',
 'app\Services\Pi\Regulatory\RegulatoryFreshnessObserver.php',
 'app\Services\Pi\Regulatory\RegulatoryManifestationIntake.php',
 'app\Services\Pi\Regulatory\ResilientRegulatoryAcquisition.php',
 'config\pi_rai.php',
 'resources\pi\hria-delegation-reference.json',
 'resources\pi\rai-source-registry.json',
 'resources\pi\rdi02-planning-sources.json',
 'resources\pi\rf03-rkpd-series-metadata.json',
 'resources\pi\rf03-rkpd-series-source-queue.json',
 'resources\pi\rf04-annex-benchmark-queue.json',
 'resources\pi\rf04-primary-acquisition.json',
 'resources\pi\rf04-scoped-relation-metadata.json',
 'app\Services\Pi\Development\PrimaryPdfCandidateIntake.php',
 'resources\pi\official-bpk-pdf-mirrors-r103.json'
)
# Add dependencies first, route registrations last. No removals outside rollback.
$allow=@($add)+@($replace|Where-Object {$_ -notlike 'routes\*'})+@($replace|Where-Object {$_ -like 'routes\*'})
if($allow.Count -ne 56 -or @($allow|Select-Object -Unique).Count -ne 56){throw 'PI_APR_CUTOVER_SCOPE_HOLD'}
$protected=@(
 'artisan',
 '.env',
 'database\database.sqlite',
 'composer.json',
 'composer.lock',
 'config\pi.php',
 'bootstrap\app.php',
 'app\Providers\AppServiceProvider.php',
 'public\login.html',
 'public\login.js',
 'public\overview-r69c.json',
 'public\favicon.ico',
 'public\robots.txt',
 'public\uu12-2011-r70a.pdf'
)
foreach($rel in $protected){
 if(-not (Test-Path -LiteralPath (Join-Path $root $rel) -PathType Leaf)){
  throw ('PI_APR_CUTOVER_PROTECTED_FILE_MISSING='+$rel)
 }
}
$launcherFile=Join-Path $launcher 'BUKA-PI-HIDDEN.vbs'
if(-not (Test-Path -LiteralPath $launcherFile -PathType Leaf)){
 throw 'PI_APR_CUTOVER_EXISTING_LAUNCHER_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$pin=([string](& $git -C $repo rev-parse HEAD)).Trim().ToLowerInvariant()
if($LASTEXITCODE -ne 0 -or $pin -cne $ExpectedCommit.ToLowerInvariant()){
 throw 'PI_APR_CUTOVER_CANDIDATE_COMMIT_NOT_PINNED'
}
if($pin -cne $release){throw 'PI_APR_CUTOVER_RELEASE_SCOPE_PIN_HOLD'}
$dirty=@(& $git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0){
 throw 'PI_APR_CUTOVER_QA_WORKTREE_NOT_CLEAN'
}
& $git -C $repo merge-base --is-ancestor $baseline $pin
if($LASTEXITCODE -ne 0){throw 'PI_APR_CUTOVER_APPROVED_W3C_ANCESTOR_NOT_FOUND'}
function Read-GitUtf8([string]$relative){
 $path='laravel/'+$relative.Replace([char]92,[char]47)
 $psi=New-Object Diagnostics.ProcessStartInfo
 $psi.FileName=$git
 $psi.Arguments='-C "'+$repo+'" show "'+$baseline+':'+$path+'"'
 $psi.UseShellExecute=$false
 $psi.RedirectStandardOutput=$true
 $psi.CreateNoWindow=$true
 $p=[Diagnostics.Process]::Start($psi)
 if($null -eq $p){throw 'PI_APR_CUTOVER_APPROVED_W3C_BASELINE_PROCESS_MISSING'}
 $m=New-Object IO.MemoryStream
 try{
  $p.StandardOutput.BaseStream.CopyTo($m)
  $p.WaitForExit()
  if($p.ExitCode -ne 0 -or $m.Length -lt 5 -or $m.Length -gt 1048576){
   throw ('PI_APR_CUTOVER_APPROVED_W3C_BASELINE_UNAVAILABLE='+$relative)
  }
  return [Text.UTF8Encoding]::new($false,$true).GetString($m.ToArray())
 }finally{$m.Dispose();$p.Dispose()}
}
function Normalize([string]$x){
 return ($x -replace '\r\n',[string][char]10).TrimStart([char]0xFEFF).TrimEnd([char]10,[char]13)
}
$required=@(
 @('public\development-case-r85.html','id="pi-performance-evidence"'),
 @('public\development-case-r85.js','renderPerformance(caseData)'),
 @('public\development-case-r85.js','Keluaran tidak membuktikan outcome'),
 @('public\planner-workflow-r108.css','pi-performance-card')
)
foreach($pair in $required){
 $file=Join-Path (Join-Path $repo 'laravel') $pair[0]
 if(-not (Test-Path -LiteralPath $file -PathType Leaf) -or
   -not ([IO.File]::ReadAllText($file,[Text.UTF8Encoding]::new($false,$true))).Contains($pair[1])){
  throw ('PI_APR_CUTOVER_REQUIRED_SOURCE_CONTRACT_MISSING='+$pair[0])
 }
}
$rows=New-Object 'System.Collections.Generic.List[object]'
$unknown=New-Object 'System.Collections.Generic.List[string]'
foreach($rel in $allow){
 $src=[IO.Path]::GetFullPath((Join-Path (Join-Path $repo 'laravel') $rel))
 $dst=[IO.Path]::GetFullPath((Join-Path $root $rel))
 if(-not $dst.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or
    -not (Test-Path -LiteralPath $src -PathType Leaf)){
  throw ('PI_APR_CUTOVER_UNSAFE_ALLOWLIST_PATH='+$rel)
 }
 foreach($path in @($src,$dst)){
  if(Test-Path -LiteralPath $path){
   $obj=Get-Item -LiteralPath $path -Force
   if($obj.PSIsContainer -or ($obj.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
    throw ('PI_APR_CUTOVER_FILE_REPARSE_OR_DIRECTORY_HOLD='+$rel)
   }
  }
  $parent=Split-Path $path -Parent
  while($parent -and (Test-Path -LiteralPath $parent -PathType Container)){
   $obj=Get-Item -LiteralPath $parent -Force
   if(($obj.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
    throw ('PI_APR_CUTOVER_PARENT_REPARSE_HOLD='+$rel)
   }
   if($parent -eq $root -or $parent -eq $repo){break}
   $parent=Split-Path $parent -Parent
  }
 }
 $exists=Test-Path -LiteralPath $dst -PathType Leaf
 $oldHash=if($exists){(Get-FileHash -LiteralPath $dst -Algorithm SHA256).Hash}else{$null}
 $newHash=(Get-FileHash -LiteralPath $src -Algorithm SHA256).Hash
 if($replace -contains $rel){
  if(-not $exists){throw ('PI_APR_CUTOVER_APPROVED_W3C_BASELINE_REPLACEMENT_MISSING='+$rel)}
  $actual=Normalize ([IO.File]::ReadAllText($dst,[Text.UTF8Encoding]::new($false,$true)))
  $base=Normalize (Read-GitUtf8 $rel)
  $candidate=Normalize ([IO.File]::ReadAllText($src,[Text.UTF8Encoding]::new($false,$true)))
  if($actual -cne $base -and $actual -cne $candidate){
   $unknown.Add($rel)
   Write-Output ('PI_APR_CUTOVER_UNKNOWN_LOCAL_SOURCE='+$rel)
  }
  Write-Output ('PI_APR_CUTOVER_APPROVED_W3C_BASELINE_VERIFIED='+$rel)
 }elseif($exists -and (Normalize ([IO.File]::ReadAllText($dst,[Text.UTF8Encoding]::new($false,$true)))) -cne (Normalize ([IO.File]::ReadAllText($src,[Text.UTF8Encoding]::new($false,$true))))){
  $unknown.Add($rel)
  Write-Output ('PI_APR_CUTOVER_UNKNOWN_LOCAL_SOURCE='+$rel)
 }
 $rows.Add([pscustomobject]@{
  Rel=$rel;Source=$src;Target=$dst;Existed=$exists;OldHash=$oldHash;
  NewHash=$newHash;Change=((-not $exists)-or $oldHash -cne $newHash)
 })
}
if($unknown.Count -gt 0){throw ('PI_APR_CUTOVER_UNKNOWN_LOCAL_DRIFT_HOLD='+($unknown -join ';'))}
$changes=@($rows|Where-Object {$_.Change})
Write-Output ('PI_APR_CUTOVER_QA_SHA='+$pin)
Write-Output ('PI_APR_CUTOVER_ALLOWLIST='+$rows.Count)
Write-Output ('PI_APR_CUTOVER_FILES_TO_CHANGE='+$changes.Count)
foreach($item in $changes){
 Write-Output ('PI_APR_CUTOVER_CHANGE='+$item.Rel+':'+$(if($item.Existed){'REPLACE'}else{'ADD'}))
}
Write-Output 'PI_APR_CUTOVER_DB_ENV_LAUNCHER=PROTECTED'
Write-Output 'PI_APR_CUTOVER_FISCAL_ADMISSION=ZERO'
if(-not $ci){
 $drift=@(& (Join-Path $repo 'scripts\pi-r90-drift-classifier-readonly.ps1') -CandidateRepo $repo -ExpectedCommit $pin)
 foreach($line in $drift){if($line -match '^PI_R90_(LOCK_|MIGRATION_CLASS=)'){Write-Output $line}}
 foreach($check in @('PI_R90_LOCK_ADDED=0','PI_R90_LOCK_REMOVED=0','PI_R90_LOCK_VERSION_CHANGED_COUNT=0','PI_R90_LOCK_SOURCE_REF_CHANGED_COUNT=0','PI_R90_LOCK_DEV_SCOPE_CHANGED_COUNT=0','PI_R90_DRIFT_MIGRATION_TEXT_CONTENT_DIFFERENT=0','PI_R90_DRIFT_MIGRATION_MISSING_ON_MAIN=0','PI_R90_DRIFT_MIGRATION_NON_UTF8_DIFFERENT=0','PI_R90_DRIFT_MIGRATION_BINARY_OR_UNSUPPORTED_DIFFERENT=0','PI_R90_DRIFT_MIGRATION_LARGE_TEXT_DIFFERENT=0')){if($drift -notcontains $check){throw ('PI_APR_CUTOVER_DEPENDENCY_OR_MIGRATION_HOLD='+$check)}}
 $php=(Get-Command php.exe -ErrorAction Stop).Source
 $schema=@(& $php (Join-Path $repo 'scripts\pi-r91-sqlite-schema-readonly.php'))
 if($LASTEXITCODE -ne 0 -or $schema -notcontains 'PI_R91_SCHEMA_MINIMUM=REQUIRED_COLUMNS_AND_LEDGER_PASS'){throw 'PI_APR_CUTOVER_SCHEMA_METADATA_HOLD'}
 Write-Output 'PI_APR_CUTOVER_SQLITE_MINIMUM=PASS_READ_ONLY'
 foreach($item in $rows){if($item.Rel -like '*.php'){& $php -l $item.Source|Out-Null;if($LASTEXITCODE -ne 0){throw ('PI_APR_CUTOVER_PHP_LINT_HOLD='+$item.Rel)}}}
}
if(-not $ci){
 # Prove the same command and unchanged case graph BEFORE source writes.
 $listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
 if($listener.Count -ne 1){throw 'PI_APR_CUTOVER_SINGLE_8820_LISTENER_REQUIRED'}
 $p=Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)"
 if($null -eq $p -or $p.Name -notmatch '^(php|php-cgi)\.exe
 return
}
if($changes.Count -eq 0){
 Write-Output 'PI_APR_CUTOVER_CUTOVER=NO_CHANGE_ALREADY_MATCHED'
 return
}
$serverPid=0
if(-not $ci){
 $listeners=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
 if($listeners.Count -ne 1){throw 'PI_APR_CUTOVER_SINGLE_8820_LISTENER_REQUIRED'}
 $serverPid=[int]$listeners[0].OwningProcess
 $process=Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction Stop
 if($null -eq $process -or $process.Name -notmatch '^(php|php-cgi)\.exe$'){
  throw 'PI_APR_CUTOVER_8820_NOT_EXISTING_PHP'
 }
 $cached=@(Get-ChildItem -LiteralPath (Join-Path $root 'bootstrap\cache') -File -ErrorAction SilentlyContinue |
  Where-Object {$_.Name -eq 'config.php' -or $_.Name -like 'routes-*.php'})
 if($cached.Count -ne 0){throw 'PI_APR_CUTOVER_ORIGINAL_ROUTE_OR_CONFIG_CACHE_HOLD'}
}
$protectedSha=@{}
foreach($rel in $protected){
 $protectedSha[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$launcherSha=(Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss-fff')+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)
$backup=Join-Path $launcher ('backups\APR_INTEGRATED_'+$stamp)
$stages=New-Object 'System.Collections.Generic.List[string]'
$began=$false
try{
 New-Item -ItemType Directory -Path (Join-Path $backup 'before') -Force|Out-Null
 foreach($item in $changes){
  if($item.Existed){
   $saved=Join-Path (Join-Path $backup 'before') $item.Rel
   New-Item -ItemType Directory -Path (Split-Path $saved -Parent) -Force|Out-Null
   Copy-Item -LiteralPath $item.Target -Destination $saved -ErrorAction Stop
   if((Get-FileHash -LiteralPath $saved -Algorithm SHA256).Hash -cne $item.OldHash){
    throw ('PI_APR_CUTOVER_BACKUP_SHA_HOLD='+$item.Rel)
   }
  }
 }
 $rows|Select-Object Rel,Existed,OldHash,NewHash,Change|
  ConvertTo-Json -Depth 4|Out-File -LiteralPath (Join-Path $backup 'SOURCE_MANIFEST.json') -Encoding UTF8
 foreach($item in $changes){
  New-Item -ItemType Directory -Path (Split-Path $item.Target -Parent) -Force|Out-Null
  $stage=$item.Target+'.apr-stage-'+[guid]::NewGuid().ToString('N')
  Copy-Item -LiteralPath $item.Source -Destination $stage -ErrorAction Stop
  if((Get-FileHash -LiteralPath $stage -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_APR_CUTOVER_STAGE_SHA_HOLD='+$item.Rel)
  }
  Add-Member -InputObject $item -NotePropertyName Stage -NotePropertyValue $stage -Force
  $stages.Add($stage)
 }
 $began=$true
 $written=0
 foreach($item in $changes){
  if($item.Existed){
   if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
    throw ('PI_APR_CUTOVER_TARGET_CHANGED_DURING_APPLY='+$item.Rel)
   }
   [IO.File]::Replace([string]$item.Stage,[string]$item.Target,
    [string](Join-Path $backup ('atomic-'+$written)))
  }else{
   if(Test-Path -LiteralPath $item.Target){throw ('PI_APR_CUTOVER_ADD_COLLISION='+$item.Rel)}
   Move-Item -LiteralPath $item.Stage -Destination $item.Target -ErrorAction Stop
  }
  $written++
  if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_APR_CUTOVER_APPLIED_SHA_HOLD='+$item.Rel)
  }
  if(($CiFailAfterSecondWrite -and $written -eq 2) -or ($CiFailAfterWrite -gt 0 -and $written -eq $CiFailAfterWrite)){
   throw 'PI_APR_CUTOVER_CI_INJECTED_ROLLBACK'
  }
 }
 if(-not $ci){
  $probe=Join-Path $repo 'scripts\pi-r108-wave3c-tinker-readonly.php'
  if(-not (Test-Path -LiteralPath $probe -PathType Leaf)){throw 'PI_APR_CUTOVER_TINKER_PROBE_MISSING'}
  $php=Join-Path (Split-Path $process.ExecutablePath -Parent) 'php.exe'
  if(-not (Test-Path -LiteralPath $php -PathType Leaf)){throw 'PI_APR_CUTOVER_EXISTING_PHP_CLI_MISSING'}
  Invoke-PiAprTinkerProbe -Php $php -AppRoot $root -Probe $probe
  Write-Output 'PI_APR_CUTOVER_POSTWRITE_TINKER=PASS'
 }
 foreach($rel in $protected){
  if((Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash -cne $protectedSha[$rel]){
   throw ('PI_APR_CUTOVER_PROTECTED_HASH_CHANGED='+$rel)
  }
 }
 if((Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash -cne $launcherSha){
  throw 'PI_APR_CUTOVER_LAUNCHER_SHA_CHANGED'
 }
 if(-not $ci){
  $after=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
  if($after.Count -ne 1 -or [int]$after[0].OwningProcess -ne $serverPid){
   throw 'PI_APR_CUTOVER_ORIGINAL_PHP_PID_CHANGED'
  }
  $setup=Invoke-RestMethod -Uri 'http://127.0.0.1:8820/api/auth/setup-status' -Headers @{Accept='application/json'} -TimeoutSec 12
  if($setup.needs_setup -ne $false -or -not ($setup.csrf -is [string]) -or [string]::IsNullOrWhiteSpace($setup.csrf)){
   throw 'PI_APR_CUTOVER_LOGIN_OR_CSRF_REGRESSION'
  }
  foreach($url in @('/api/qa-superior-r106/annex-fiscal-review','/api/qa-superior-r106/annex-pdf?source=base','/api/qa-superior-hria/primary-preview','/api/qa-superior-r89/semantic-preview')){
   $status=0
   try{
    $r=Invoke-WebRequest -Uri ('http://127.0.0.1:8820'+$url) -UseBasicParsing -Headers @{Accept='application/json'} -TimeoutSec 12
    $status=[int]$r.StatusCode
   }catch [System.Net.WebException]{
    if($null -eq $_.Exception.Response){throw}
    $status=[int]$_.Exception.Response.StatusCode
   }
   if($status -ne 401){throw ('PI_APR_CUTOVER_NEW_ROUTE_NOT_AUTH_GUARDED='+$url+':'+$status)}
  }
  $hasher=[Security.Cryptography.SHA256]::Create()
  try{
   foreach($rel in @($replace|Where-Object {$_ -like 'public\*'})){
    $path=$rel.Substring(7).Replace([char]92,[char]47)
    $url='http://127.0.0.1:8820/'+$path+'?apr='+[guid]::NewGuid().ToString('N')
    $r=Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 12 -Headers @{'Cache-Control'='no-cache'}
    if($r.StatusCode -ne 200){throw ('PI_APR_CUTOVER_ASSET_NOT_200='+$rel)}
    $served=[BitConverter]::ToString($hasher.ComputeHash($r.RawContentStream.ToArray())).Replace('-','')
    $source=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
    if($served -cne $source){throw ('PI_APR_CUTOVER_HTTP_ASSET_SHA_MISMATCH='+$rel)}
   }
  }finally{$hasher.Dispose()}
  Write-Output ('PI_APR_CUTOVER_ORIGINAL_PHP_PID_PRESERVED='+$serverPid)
  Write-Output 'PI_APR_CUTOVER_ORIGINAL_LOGIN_CSRF=PASS'
  Write-Output 'PI_APR_CUTOVER_ANONYMOUS_R106_API_AND_PDF=401'
  Write-Output 'PI_APR_CUTOVER_LIVE_HTML_JS_CSS_SHA=PASS'
 }
 Write-Output ('PI_APR_CUTOVER_BACKUP='+$backup)
 Write-Output 'PI_APR_CUTOVER_CODE_CUTOVER=APPLIED_VERIFIED'
 Write-Output 'PI_APR_CUTOVER_OPERATIONAL_AUTHENTICATED_UX=REQUIRES_SEPARATE_RECEIPT'
 Write-Output 'PI_APR_CUTOVER_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED'
 Write-Output 'PI_APR_CUTOVER_NUMERIC_REVIEW_PRODUCTION_ADMISSION=DENIED'
}catch{
 $reason=$_.Exception.Message
 if($began){
  foreach($item in $changes){
   if($item.Existed){
    $saved=Join-Path (Join-Path $backup 'before') $item.Rel
    if(-not (Test-Path -LiteralPath $saved -PathType Leaf)){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_BACKUP_MISSING='+$item.Rel)
    }
    $current=(Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash
    if($current -cne $item.OldHash -and $current -cne $item.NewHash){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_CONCURRENT_CHANGE='+$item.Rel)
    }
    Copy-Item -LiteralPath $saved -Destination $item.Target -Force -ErrorAction Stop
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
     throw ('PI_APR_CUTOVER_RESTORE_HASH_ERROR='+$item.Rel)
    }
   }elseif(Test-Path -LiteralPath $item.Target -PathType Leaf){
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_ADDITION_DRIFT='+$item.Rel)
    }
    Remove-Item -LiteralPath $item.Target -Force -ErrorAction Stop
   }
  }
  Write-Output 'PI_APR_CUTOVER_AUTO_ROLLBACK=RESTORED_OLD_CODE_VERIFIED'
 }
 throw ('PI_APR_CUTOVER_CUTOVER_ABORTED='+$reason)
}finally{
 foreach($stage in $stages){
  if(Test-Path -LiteralPath $stage -PathType Leaf){
   Remove-Item -LiteralPath $stage -Force -ErrorAction SilentlyContinue
  }
 }
}
){throw 'PI_APR_CUTOVER_8820_NOT_EXISTING_PHP'}
 $php=Join-Path (Split-Path $p.ExecutablePath -Parent) 'php.exe'
 $probe=Join-Path $repo 'scripts\pi-r108-wave3c-tinker-readonly.php'
 Invoke-PiAprTinkerProbe -Php $php -AppRoot $root -Probe $probe
 Write-Output 'PI_APR_CUTOVER_PREWRITE_TINKER=PASS'
}
if(-not $Apply){
 Write-Output 'PI_APR_CUTOVER_READ_ONLY_PREFLIGHT=PASS'
 return
}
if($changes.Count -eq 0){
 Write-Output 'PI_APR_CUTOVER_CUTOVER=NO_CHANGE_ALREADY_MATCHED'
 return
}
$serverPid=0
if(-not $ci){
 $listeners=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
 if($listeners.Count -ne 1){throw 'PI_APR_CUTOVER_SINGLE_8820_LISTENER_REQUIRED'}
 $serverPid=[int]$listeners[0].OwningProcess
 $process=Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction Stop
 if($null -eq $process -or $process.Name -notmatch '^(php|php-cgi)\.exe$'){
  throw 'PI_APR_CUTOVER_8820_NOT_EXISTING_PHP'
 }
 $cached=@(Get-ChildItem -LiteralPath (Join-Path $root 'bootstrap\cache') -File -ErrorAction SilentlyContinue |
  Where-Object {$_.Name -eq 'config.php' -or $_.Name -like 'routes-*.php'})
 if($cached.Count -ne 0){throw 'PI_APR_CUTOVER_ORIGINAL_ROUTE_OR_CONFIG_CACHE_HOLD'}
}
$protectedSha=@{}
foreach($rel in $protected){
 $protectedSha[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$launcherSha=(Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss-fff')+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)
$backup=Join-Path $launcher ('backups\APR_INTEGRATED_'+$stamp)
$stages=New-Object 'System.Collections.Generic.List[string]'
$began=$false
try{
 New-Item -ItemType Directory -Path (Join-Path $backup 'before') -Force|Out-Null
 foreach($item in $changes){
  if($item.Existed){
   $saved=Join-Path (Join-Path $backup 'before') $item.Rel
   New-Item -ItemType Directory -Path (Split-Path $saved -Parent) -Force|Out-Null
   Copy-Item -LiteralPath $item.Target -Destination $saved -ErrorAction Stop
   if((Get-FileHash -LiteralPath $saved -Algorithm SHA256).Hash -cne $item.OldHash){
    throw ('PI_APR_CUTOVER_BACKUP_SHA_HOLD='+$item.Rel)
   }
  }
 }
 $rows|Select-Object Rel,Existed,OldHash,NewHash,Change|
  ConvertTo-Json -Depth 4|Out-File -LiteralPath (Join-Path $backup 'SOURCE_MANIFEST.json') -Encoding UTF8
 foreach($item in $changes){
  New-Item -ItemType Directory -Path (Split-Path $item.Target -Parent) -Force|Out-Null
  $stage=$item.Target+'.apr-stage-'+[guid]::NewGuid().ToString('N')
  Copy-Item -LiteralPath $item.Source -Destination $stage -ErrorAction Stop
  if((Get-FileHash -LiteralPath $stage -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_APR_CUTOVER_STAGE_SHA_HOLD='+$item.Rel)
  }
  Add-Member -InputObject $item -NotePropertyName Stage -NotePropertyValue $stage -Force
  $stages.Add($stage)
 }
 $began=$true
 $written=0
 foreach($item in $changes){
  if($item.Existed){
   if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
    throw ('PI_APR_CUTOVER_TARGET_CHANGED_DURING_APPLY='+$item.Rel)
   }
   [IO.File]::Replace([string]$item.Stage,[string]$item.Target,
    [string](Join-Path $backup ('atomic-'+$written)))
  }else{
   if(Test-Path -LiteralPath $item.Target){throw ('PI_APR_CUTOVER_ADD_COLLISION='+$item.Rel)}
   Move-Item -LiteralPath $item.Stage -Destination $item.Target -ErrorAction Stop
  }
  $written++
  if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_APR_CUTOVER_APPLIED_SHA_HOLD='+$item.Rel)
  }
  if(($CiFailAfterSecondWrite -and $written -eq 2) -or ($CiFailAfterWrite -gt 0 -and $written -eq $CiFailAfterWrite)){
   throw 'PI_APR_CUTOVER_CI_INJECTED_ROLLBACK'
  }
 }
 if(-not $ci){
  $probe=Join-Path $repo 'scripts\pi-r108-wave3c-tinker-readonly.php'
  if(-not (Test-Path -LiteralPath $probe -PathType Leaf)){throw 'PI_APR_CUTOVER_TINKER_PROBE_MISSING'}
  $php=Join-Path (Split-Path $process.ExecutablePath -Parent) 'php.exe'
  if(-not (Test-Path -LiteralPath $php -PathType Leaf)){throw 'PI_APR_CUTOVER_EXISTING_PHP_CLI_MISSING'}
  $encoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($probe))
  $code="require base64_decode('$encoded');"
  Push-Location -LiteralPath $root
  try{
   $probeOut=@(& $php artisan tinker "--execute=$code")
   if($LASTEXITCODE -ne 0 -or $probeOut -notcontains 'PI_APR_CUTOVER_TINKER_CASE_SIX_RELATIONS_GATES=PASS'){
    throw 'PI_APR_CUTOVER_TINKER_READ_ONLY_HOLD'
   }
   Write-Output 'PI_APR_CUTOVER_TINKER_CASE_SIX_RELATIONS_GATES=PASS'
  }finally{Pop-Location}
 }
 foreach($rel in $protected){
  if((Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash -cne $protectedSha[$rel]){
   throw ('PI_APR_CUTOVER_PROTECTED_HASH_CHANGED='+$rel)
  }
 }
 if((Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash -cne $launcherSha){
  throw 'PI_APR_CUTOVER_LAUNCHER_SHA_CHANGED'
 }
 if(-not $ci){
  $after=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
  if($after.Count -ne 1 -or [int]$after[0].OwningProcess -ne $serverPid){
   throw 'PI_APR_CUTOVER_ORIGINAL_PHP_PID_CHANGED'
  }
  $setup=Invoke-RestMethod -Uri 'http://127.0.0.1:8820/api/auth/setup-status' -Headers @{Accept='application/json'} -TimeoutSec 12
  if($setup.needs_setup -ne $false -or -not ($setup.csrf -is [string]) -or [string]::IsNullOrWhiteSpace($setup.csrf)){
   throw 'PI_APR_CUTOVER_LOGIN_OR_CSRF_REGRESSION'
  }
  foreach($url in @('/api/qa-superior-r106/annex-fiscal-review','/api/qa-superior-r106/annex-pdf?source=base','/api/qa-superior-hria/primary-preview','/api/qa-superior-r89/semantic-preview')){
   $status=0
   try{
    $r=Invoke-WebRequest -Uri ('http://127.0.0.1:8820'+$url) -UseBasicParsing -Headers @{Accept='application/json'} -TimeoutSec 12
    $status=[int]$r.StatusCode
   }catch [System.Net.WebException]{
    if($null -eq $_.Exception.Response){throw}
    $status=[int]$_.Exception.Response.StatusCode
   }
   if($status -ne 401){throw ('PI_APR_CUTOVER_NEW_ROUTE_NOT_AUTH_GUARDED='+$url+':'+$status)}
  }
  $hasher=[Security.Cryptography.SHA256]::Create()
  try{
   foreach($rel in @($replace|Where-Object {$_ -like 'public\*'})){
    $path=$rel.Substring(7).Replace([char]92,[char]47)
    $url='http://127.0.0.1:8820/'+$path+'?apr='+[guid]::NewGuid().ToString('N')
    $r=Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 12 -Headers @{'Cache-Control'='no-cache'}
    if($r.StatusCode -ne 200){throw ('PI_APR_CUTOVER_ASSET_NOT_200='+$rel)}
    $served=[BitConverter]::ToString($hasher.ComputeHash($r.RawContentStream.ToArray())).Replace('-','')
    $source=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
    if($served -cne $source){throw ('PI_APR_CUTOVER_HTTP_ASSET_SHA_MISMATCH='+$rel)}
   }
  }finally{$hasher.Dispose()}
  Write-Output ('PI_APR_CUTOVER_ORIGINAL_PHP_PID_PRESERVED='+$serverPid)
  Write-Output 'PI_APR_CUTOVER_ORIGINAL_LOGIN_CSRF=PASS'
  Write-Output 'PI_APR_CUTOVER_ANONYMOUS_R106_API_AND_PDF=401'
  Write-Output 'PI_APR_CUTOVER_LIVE_HTML_JS_CSS_SHA=PASS'
 }
 Write-Output ('PI_APR_CUTOVER_BACKUP='+$backup)
 Write-Output 'PI_APR_CUTOVER_CODE_CUTOVER=APPLIED_VERIFIED'
 Write-Output 'PI_APR_CUTOVER_OPERATIONAL_AUTHENTICATED_UX=REQUIRES_SEPARATE_RECEIPT'
 Write-Output 'PI_APR_CUTOVER_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED'
 Write-Output 'PI_APR_CUTOVER_NUMERIC_REVIEW_PRODUCTION_ADMISSION=DENIED'
}catch{
 $reason=$_.Exception.Message
 if($began){
  foreach($item in $changes){
   if($item.Existed){
    $saved=Join-Path (Join-Path $backup 'before') $item.Rel
    if(-not (Test-Path -LiteralPath $saved -PathType Leaf)){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_BACKUP_MISSING='+$item.Rel)
    }
    $current=(Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash
    if($current -cne $item.OldHash -and $current -cne $item.NewHash){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_CONCURRENT_CHANGE='+$item.Rel)
    }
    Copy-Item -LiteralPath $saved -Destination $item.Target -Force -ErrorAction Stop
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
     throw ('PI_APR_CUTOVER_RESTORE_HASH_ERROR='+$item.Rel)
    }
   }elseif(Test-Path -LiteralPath $item.Target -PathType Leaf){
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
     throw ('PI_APR_CUTOVER_MANUAL_RECOVERY_REQUIRED_ADDITION_DRIFT='+$item.Rel)
    }
    Remove-Item -LiteralPath $item.Target -Force -ErrorAction Stop
   }
  }
  Write-Output 'PI_APR_CUTOVER_AUTO_ROLLBACK=RESTORED_OLD_CODE_VERIFIED'
 }
 throw ('PI_APR_CUTOVER_CUTOVER_ABORTED='+$reason)
}finally{
 foreach($stage in $stages){
  if(Test-Path -LiteralPath $stage -PathType Leaf){
   Remove-Item -LiteralPath $stage -Force -ErrorAction SilentlyContinue
  }
 }
}

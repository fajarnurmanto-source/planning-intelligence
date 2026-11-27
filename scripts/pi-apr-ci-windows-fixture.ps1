#requires -Version 5.1
<#
CI-only fixture. Synthetic original accepted W3C files, no 8820, credentials, or
real user files. Exercises preflight, injected rollback, apply and drift HOLD.
#>
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
if($env:GITHUB_ACTIONS -cne 'true' -or -not $env:RUNNER_TEMP){
 throw 'PI_APR_CUTOVER_CI_FIXTURE_ONLY'
}
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $repo rev-parse HEAD)).Trim().ToLowerInvariant()
if($LASTEXITCODE -ne 0){throw 'PI_APR_CUTOVER_CI_SOURCE_COMMIT_UNKNOWN'}
$baseline='a71bac9836654ecc9f313b19f7142ea7bc0aa0c8'
$fixture=Join-Path $env:RUNNER_TEMP ('pi-apr-fixture-'+[guid]::NewGuid().ToString('N'))
$root=Join-Path $fixture 'laravel'
$launcher=Join-Path $fixture 'launcher'
New-Item -ItemType Directory -Path $root,$launcher -Force | Out-Null
[IO.File]::WriteAllText((Join-Path $launcher 'BUKA-PI-HIDDEN.vbs'),'CI synthetic launcher, not executable')
function Save-Baseline([string]$relative){
 $target=Join-Path $root $relative
 New-Item -ItemType Directory -Path (Split-Path $target -Parent) -Force|Out-Null
 $gitPath='laravel/'+$relative.Replace([char]92,[char]47)
 $psi=New-Object Diagnostics.ProcessStartInfo
 $psi.FileName=$git
 $psi.Arguments='-C "'+$repo+'" show "'+$baseline+':'+$gitPath+'"'
 $psi.UseShellExecute=$false
 $psi.RedirectStandardOutput=$true
 $psi.CreateNoWindow=$true
 $p=[Diagnostics.Process]::Start($psi)
 if($null -eq $p){throw 'PI_APR_CUTOVER_CI_GIT_BASELINE_PROCESS_FAILED'}
 $memory=New-Object IO.MemoryStream
 try {
  $p.StandardOutput.BaseStream.CopyTo($memory)
  $p.WaitForExit()
  if($p.ExitCode -ne 0 -or $memory.Length -eq 0){throw 'PI_APR_CUTOVER_CI_BASELINE_BLOB_FAILED'}
  [IO.File]::WriteAllBytes($target,$memory.ToArray())
 }finally{$memory.Dispose();$p.Dispose()}
}
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
$guard=@(
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
foreach($rel in $replace){Save-Baseline $rel}
foreach($rel in $guard){
 $dest=Join-Path $root $rel
 New-Item -ItemType Directory -Path (Split-Path $dest -Parent) -Force|Out-Null
 if($rel -in @('.env','database\database.sqlite','public\overview-r69c.json','public\favicon.ico','public\robots.txt','public\uu12-2011-r70a.pdf')){
  [IO.File]::WriteAllText($dest,'SYNTHETIC CI ONLY / NO REAL USER DATA')
 }else{
  Copy-Item -LiteralPath (Join-Path (Join-Path $repo 'laravel') $rel) -Destination $dest -ErrorAction Stop
 }
}
$before=@{}
foreach($rel in @($replace)+@($guard)){
 $before[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$script=Join-Path $PSScriptRoot 'pi-apr-integrated-cutover.ps1'
$tokens=$null
$errors=$null
[Management.Automation.Language.Parser]::ParseFile($script,[ref]$tokens,[ref]$errors)|Out-Null
if($errors.Count -ne 0){
 $errors|Format-List
 throw 'PI_APR_CUTOVER_CI_PS51_SYNTAX_FAIL'
}
Write-Host 'PI_APR_CUTOVER_WINDOWS_POWERSHELL51_SYNTAX=PASS'
$argsFixed=@{CandidateRepo=$repo;ExpectedCommit=$head;CiFixtureRoot=$fixture}
$pre=@(& $script @argsFixed)
if($pre -notcontains 'PI_APR_CUTOVER_READ_ONLY_PREFLIGHT=PASS' -or
   $pre -notcontains 'PI_APR_CUTOVER_ALLOWLIST=56' -or
   $pre -notcontains 'PI_APR_CUTOVER_FILES_TO_CHANGE=56'){
 throw ('PI_APR_CUTOVER_CI_PREFLIGHT_FAILED '+($pre -join ';'))
}
Write-Host 'PI_APR_CUTOVER_WINDOWS_EXACT_56_READ_ONLY_PREFLIGHT=PASS'
$failed=''
try {
 & $script @argsFixed -Apply -CiFailAfterSecondWrite|Out-Null
}catch {$failed=[string]$_.Exception.Message}
if($failed -notmatch 'PI_APR_CUTOVER_CUTOVER_ABORTED=PI_APR_CUTOVER_CI_INJECTED_ROLLBACK'){
 throw ('PI_APR_CUTOVER_CI_INJECTED_ROLLBACK_FAILED='+$failed)
}
foreach($rel in @($replace)+@($guard)){
 $sha=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
 if($sha -cne $before[$rel]){throw ('PI_APR_CUTOVER_CI_ROLLBACK_DID_NOT_RESTORE='+$rel)}
}
foreach($rel in $add){
 if(Test-Path -LiteralPath (Join-Path $root $rel)){
  throw ('PI_APR_CUTOVER_CI_ROLLBACK_LEFT_NEW_FILE='+$rel)
 }
}
Write-Host 'PI_APR_CUTOVER_WINDOWS_FAULT_INJECTION_ROLLBACK=PASS'
# Inject failure after all additions and one replacement, and verify restoration.
$failed=''
try{& $script @argsFixed -Apply -CiFailAfterWrite 39|Out-Null}catch{$failed=[string]$_.Exception.Message}
if($failed -notmatch 'PI_APR_CUTOVER_CUTOVER_ABORTED=PI_APR_CUTOVER_CI_INJECTED_ROLLBACK'){throw ('PI_APR_CUTOVER_MIXED_ROLLBACK_HOLD='+$failed)}
foreach($rel in @($replace)+@($guard)){if((Get-FileHash -LiteralPath (Join-Path $root $rel)).Hash -cne $before[$rel]){throw ('PI_APR_CUTOVER_MIXED_ROLLBACK_HASH_HOLD='+$rel)}}
foreach($rel in $add){if(Test-Path -LiteralPath (Join-Path $root $rel)){throw ('PI_APR_CUTOVER_MIXED_ROLLBACK_ADD_HOLD='+$rel)}}
Write-Host 'PI_APR_CUTOVER_WINDOWS_MIXED_ROLLBACK=PASS'
$applied=@(& $script @argsFixed -Apply)
if($applied -notcontains 'PI_APR_CUTOVER_CODE_CUTOVER=APPLIED_VERIFIED' -or
   $applied -notcontains 'PI_APR_CUTOVER_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED'){
 throw ('PI_APR_CUTOVER_CI_APPLY_FAILED '+($applied -join ';'))
}
foreach($rel in @($replace)+@($add)){
 $source=Join-Path (Join-Path $repo 'laravel') $rel
 $dest=Join-Path $root $rel
 if((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -cne
    (Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash){
  throw ('PI_APR_CUTOVER_CI_FINAL_SOURCE_SHA_DIFFERENT='+$rel)
 }
}
foreach($rel in $guard){
 if((Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash -cne $before[$rel]){
  throw ('PI_APR_CUTOVER_CI_APPLY_CHANGED_PROTECTED='+$rel)
 }
}
$again=@(& $script @argsFixed -Apply)
if($again -notcontains 'PI_APR_CUTOVER_CUTOVER=NO_CHANGE_ALREADY_MATCHED'){
 throw 'PI_APR_CUTOVER_CI_SECOND_APPLY_NOT_IDEMPOTENT'
}
Write-Host 'PI_APR_CUTOVER_WINDOWS_APPLY_SOURCE_SHA_AND_IDEMPOTENCE=PASS'
$target=Join-Path $root 'public\development-case-r85.html'
[IO.File]::AppendAllText($target,([string][char]10)+'UNKNOWN CI LOCAL DRIFT')
$driftSha=(Get-FileHash $target -Algorithm SHA256).Hash
$reason=''
try{& $script @argsFixed -Apply|Out-Null}catch{$reason=[string]$_.Exception.Message}
if($reason -notmatch 'PI_APR_CUTOVER_UNKNOWN_LOCAL_DRIFT_HOLD' -or
   (Get-FileHash $target -Algorithm SHA256).Hash -cne $driftSha){
 throw 'PI_APR_CUTOVER_CI_UNREVIEWED_DRIFT_NOT_REJECTED'
}
Write-Host 'PI_APR_CUTOVER_WINDOWS_UNKNOWN_LOCAL_DRIFT=HOLD_NO_OVERWRITE'

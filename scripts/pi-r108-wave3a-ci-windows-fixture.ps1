#requires -Version 5.1
<#
CI-only fixture. Synthetic original R108 Wave1 files, no 8820, credentials, or
real user files. Exercises preflight, injected rollback, apply and drift HOLD.
#>
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
if($env:GITHUB_ACTIONS -cne 'true' -or -not $env:RUNNER_TEMP){
 throw 'PI_R108_W3A_CI_FIXTURE_ONLY'
}
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $repo rev-parse HEAD)).Trim().ToLowerInvariant()
if($LASTEXITCODE -ne 0){throw 'PI_R108_W3A_CI_SOURCE_COMMIT_UNKNOWN'}
$baseline='63ee3c30d5846ef3b28dee8b8ee8239a97ba417b'
$fixture=Join-Path $env:RUNNER_TEMP ('pi-r108-wave3a-fixture-'+[guid]::NewGuid().ToString('N'))
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
 if($null -eq $p){throw 'PI_R108_W3A_CI_GIT_BASELINE_PROCESS_FAILED'}
 $memory=New-Object IO.MemoryStream
 try {
  $p.StandardOutput.BaseStream.CopyTo($memory)
  $p.WaitForExit()
  if($p.ExitCode -ne 0 -or $memory.Length -eq 0){throw 'PI_R108_W3A_CI_BASELINE_BLOB_FAILED'}
  [IO.File]::WriteAllBytes($target,$memory.ToArray())
 }finally{$memory.Dispose();$p.Dispose()}
}
$replace=@('public\development-case-r85.js','public\development-case-r85.html','public\planner-workflow-r108.css')
$add=@()
$guard=@(
 'artisan','.env','database\database.sqlite','config\pi.php','routes\web.php',
 'public\pi-ia-r69e.js','public\pi-ia-r69e.css','public\index.html',
 'public\planner-workflow-r108.js',
 'public\native-annex-r106.js','public\native-annex-r106.css',
 'public\pi-auth-shell.js','public\regulatory-planning-r88.js',
 'public\official-sources-r100.js','public\official-sources-r100.css',
 'resources\pi\r105-owner-bpk-byte-pins.json',
 'resources\pi\bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'
)
foreach($rel in $replace){Save-Baseline $rel}
foreach($rel in $guard){
 $dest=Join-Path $root $rel
 New-Item -ItemType Directory -Path (Split-Path $dest -Parent) -Force|Out-Null
 if($rel -eq '.env' -or $rel -eq 'database\database.sqlite'){
  [IO.File]::WriteAllText($dest,'SYNTHETIC CI ONLY / NO REAL USER DATA')
 }else{
  Copy-Item -LiteralPath (Join-Path (Join-Path $repo 'laravel') $rel) -Destination $dest -ErrorAction Stop
 }
}
$before=@{}
foreach($rel in @($replace)+@($guard)){
 $before[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$script=Join-Path $PSScriptRoot 'pi-r108-wave3a-documents-cutover.ps1'
$tokens=$null
$errors=$null
[Management.Automation.Language.Parser]::ParseFile($script,[ref]$tokens,[ref]$errors)|Out-Null
if($errors.Count -ne 0){
 $errors|Format-List
 throw 'PI_R108_W3A_CI_PS51_SYNTAX_FAIL'
}
Write-Host 'PI_R108_W3A_WINDOWS_POWERSHELL51_SYNTAX=PASS'
$argsFixed=@{CandidateRepo=$repo;ExpectedCommit=$head;CiFixtureRoot=$fixture}
$pre=@(& $script @argsFixed)
if($pre -notcontains 'PI_R108_W3A_READ_ONLY_PREFLIGHT=PASS' -or
   $pre -notcontains 'PI_R108_W3A_ALLOWLIST=3' -or
   $pre -notcontains 'PI_R108_W3A_FILES_TO_CHANGE=3'){
 throw ('PI_R108_W3A_CI_PREFLIGHT_FAILED '+($pre -join ';'))
}
Write-Host 'PI_R108_W3A_WINDOWS_EXACT_THREE_READ_ONLY_PREFLIGHT=PASS'
$failed=''
try {
 & $script @argsFixed -Apply -CiFailAfterSecondWrite|Out-Null
}catch {$failed=[string]$_.Exception.Message}
if($failed -notmatch 'PI_R108_W3A_CUTOVER_ABORTED=PI_R108_W3A_CI_INJECTED_ROLLBACK'){
 throw ('PI_R108_W3A_CI_INJECTED_ROLLBACK_FAILED='+$failed)
}
foreach($rel in @($replace)+@($guard)){
 $sha=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
 if($sha -cne $before[$rel]){throw ('PI_R108_W3A_CI_ROLLBACK_DID_NOT_RESTORE='+$rel)}
}
foreach($rel in $add){
 if(Test-Path -LiteralPath (Join-Path $root $rel)){
  throw ('PI_R108_W3A_CI_ROLLBACK_LEFT_NEW_FILE='+$rel)
 }
}
Write-Host 'PI_R108_W3A_WINDOWS_FAULT_INJECTION_ROLLBACK=PASS'
$applied=@(& $script @argsFixed -Apply)
if($applied -notcontains 'PI_R108_W3A_CODE_CUTOVER=APPLIED_VERIFIED' -or
   $applied -notcontains 'PI_R108_W3A_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED'){
 throw ('PI_R108_W3A_CI_APPLY_FAILED '+($applied -join ';'))
}
foreach($rel in @($replace)+@($add)){
 $source=Join-Path (Join-Path $repo 'laravel') $rel
 $dest=Join-Path $root $rel
 if((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -cne
    (Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash){
  throw ('PI_R108_W3A_CI_FINAL_SOURCE_SHA_DIFFERENT='+$rel)
 }
}
foreach($rel in $guard){
 if((Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash -cne $before[$rel]){
  throw ('PI_R108_W3A_CI_APPLY_CHANGED_PROTECTED='+$rel)
 }
}
$again=@(& $script @argsFixed -Apply)
if($again -notcontains 'PI_R108_W3A_CUTOVER=NO_CHANGE_ALREADY_MATCHED'){
 throw 'PI_R108_W3A_CI_SECOND_APPLY_NOT_IDEMPOTENT'
}
Write-Host 'PI_R108_W3A_WINDOWS_APPLY_SOURCE_SHA_AND_IDEMPOTENCE=PASS'
$target=Join-Path $root 'public\development-case-r85.html'
[IO.File]::AppendAllText($target,([string][char]10)+'UNKNOWN CI LOCAL DRIFT')
$driftSha=(Get-FileHash $target -Algorithm SHA256).Hash
$reason=''
try{& $script @argsFixed -Apply|Out-Null}catch{$reason=[string]$_.Exception.Message}
if($reason -notmatch 'PI_R108_W3A_R108_WAVE2_BASELINE_SOURCE_DRIFT_HOLD' -or
   (Get-FileHash $target -Algorithm SHA256).Hash -cne $driftSha){
 throw 'PI_R108_W3A_CI_UNREVIEWED_DRIFT_NOT_REJECTED'
}
Write-Host 'PI_R108_W3A_WINDOWS_UNKNOWN_LOCAL_DRIFT=HOLD_NO_OVERWRITE'

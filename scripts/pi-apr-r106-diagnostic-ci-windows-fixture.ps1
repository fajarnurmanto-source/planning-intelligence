#requires -Version 5.1
# R106 diagnostic exact-one scope fixture; never accesses the original runtime.
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
if($env:GITHUB_ACTIONS -cne 'true' -or -not $env:RUNNER_TEMP){throw 'PI_R106_DIAGNOSTIC_CI_ONLY'}
$executor=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$source=Join-Path $env:RUNNER_TEMP ('pi-r106-diagnostic-release-'+[guid]::NewGuid().ToString('N'))
$pin='4c7adadc7b15421a3afea570302c348f3aeb54c8'
& git -C $executor worktree add --detach $source $pin
if($LASTEXITCODE -ne 0){throw 'PI_R106_DIAGNOSTIC_RELEASE_CHECKOUT_HOLD'}
$fixture=Join-Path $env:RUNNER_TEMP ('pi-r106-diagnostic-fixture-'+[guid]::NewGuid().ToString('N'))
$root=Join-Path $fixture 'laravel';$launcher=Join-Path $fixture 'launcher'
New-Item -ItemType Directory -Path $root,$launcher -Force|Out-Null
[IO.File]::WriteAllText((Join-Path $launcher 'BUKA-PI-HIDDEN.vbs'),'SYNTHETIC ONLY')
$replace=@('public\native-annex-r106.js')
$add=@()
$guard=@('artisan','.env','database\database.sqlite','composer.json','composer.lock','config\pi.php','bootstrap\app.php','app\Providers\AppServiceProvider.php','public\login.html','public\login.js','public\overview-r69c.json','public\favicon.ico','public\robots.txt','public\uu12-2011-r70a.pdf')
$before=@{}
foreach($rel in @($replace)+@($guard)){
 $dst=Join-Path $root $rel
 New-Item -ItemType Directory -Path (Split-Path $dst -Parent) -Force|Out-Null
 if($replace -contains $rel){
  $info=New-Object Diagnostics.ProcessStartInfo
  $info.FileName=(Get-Command git.exe).Source
  $info.Arguments='-C "'+$source+'" show "49d07c955add998d40693f5d4139a3fe0268def1:laravel/'+$rel.Replace('\','/')+'"'
  $info.UseShellExecute=$false;$info.RedirectStandardOutput=$true;$info.CreateNoWindow=$true
  $p=[Diagnostics.Process]::Start($info);$m=New-Object IO.MemoryStream
  try{$p.StandardOutput.BaseStream.CopyTo($m);$p.WaitForExit();if($p.ExitCode -ne 0){throw 'PI_R106_DIAGNOSTIC_BASELINE_READ_HOLD'};[IO.File]::WriteAllBytes($dst,$m.ToArray())}finally{$m.Dispose();$p.Dispose()}
 }else{[IO.File]::WriteAllText($dst,'SYNTHETIC PROTECTED FILE')}
 $before[$rel]=(Get-FileHash -LiteralPath $dst).Hash
}
$script=Join-Path $PSScriptRoot 'pi-apr-integrated-cutover.ps1'
$params=@{CandidateRepo=$source;ExpectedCommit=$pin;ReleaseProfile='R106Diagnostic1';CiFixtureRoot=$fixture}
$preview=@(& $script @params)
if($preview -notcontains 'PI_APR_CUTOVER_ALLOWLIST=1' -or $preview -notcontains 'PI_APR_CUTOVER_FILES_TO_CHANGE=1'){throw 'PI_R106_DIAGNOSTIC_EXACT_SCOPE_HOLD'}
try{& $script @params -Apply -CiFailAfterWrite 1|Out-Null;throw 'PI_R106_DIAGNOSTIC_NO_FAULT'}catch{if($_.Exception.Message -notmatch 'CUTOVER_ABORTED=PI_APR_CUTOVER_CI_INJECTED_ROLLBACK'){throw}}
foreach($rel in @($replace)+@($guard)){if((Get-FileHash -LiteralPath (Join-Path $root $rel)).Hash -cne $before[$rel]){throw 'PI_R106_DIAGNOSTIC_ROLLBACK_HASH_HOLD'}}
foreach($rel in $add){if(Test-Path -LiteralPath (Join-Path $root $rel)){throw 'PI_R106_DIAGNOSTIC_ROLLBACK_ADD_HOLD'}}
$applied=@(& $script @params -Apply)
if($applied -notcontains 'PI_APR_CUTOVER_CODE_CUTOVER=APPLIED_VERIFIED'){throw 'PI_R106_DIAGNOSTIC_APPLY_HOLD'}
foreach($rel in @($replace)+@($add)){if((Get-FileHash (Join-Path $root $rel)).Hash -cne (Get-FileHash (Join-Path (Join-Path $source 'laravel') $rel)).Hash){throw 'PI_R106_DIAGNOSTIC_APPLY_HASH_HOLD'}}
foreach($rel in $guard){if((Get-FileHash (Join-Path $root $rel)).Hash -cne $before[$rel]){throw 'PI_R106_DIAGNOSTIC_PROTECTED_DRIFT'}}
if(@(& $script @params -Apply) -notcontains 'PI_APR_CUTOVER_CUTOVER=NO_CHANGE_ALREADY_MATCHED'){throw 'PI_R106_DIAGNOSTIC_IDEMPOTENCE_HOLD'}
[IO.File]::AppendAllText((Join-Path $root $replace[0]),'UNKNOWN DRIFT')
try{& $script @params -Apply|Out-Null;throw 'PI_R106_DIAGNOSTIC_DRIFT_ACCEPTED'}catch{if($_.Exception.Message -notmatch 'UNKNOWN_LOCAL_DRIFT_HOLD'){throw}}
Write-Output 'PI_R106_DIAGNOSTIC_EXACT_ONE_ROLLBACK_APPLY_IDEMPOTENCE_DRIFT=PASS'

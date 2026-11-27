#requires -Version 5.1
# Read-only owner runtime reconciliation and local pinned RF05 acceptance.
param([Parameter(Mandatory=$true)][ValidatePattern('^[a-f0-9]{40}$')][string]$ExpectedCommit,[switch]$AdaptiveLayout)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$head=([string](& git -C $repo rev-parse HEAD)).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit){throw 'PI_APR_PIN_HOLD'}
if(@(& git -C $repo status --porcelain).Count -ne 0 -or $LASTEXITCODE -ne 0){throw 'PI_APR_DIRTY_HOLD'}
$runtime='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
& (Join-Path $PSScriptRoot 'pi-r90-cutover-risk-readonly.ps1') -CandidateRepo $repo -ExpectedCommit $head
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_APR_SINGLE_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_APR_PHP_HOLD'}
$files=@(& git -C $repo ls-files -- laravel/app laravel/public laravel/config laravel/routes laravel/resources/pi laravel/bootstrap/app.php)
if($LASTEXITCODE -ne 0){throw 'PI_APR_CENSUS_HOLD'}
$same=0;$different=0;$missing=0
foreach($file in $files){
 $rel=$file.Substring(8)
 $src=Join-Path $repo $file
 $dst=Join-Path $runtime $rel
 if(-not(Test-Path -LiteralPath $dst -PathType Leaf)){$missing++;Write-Output ('APR_MISSING='+$rel);continue}
 if((Get-FileHash -LiteralPath $src).Hash -ceq (Get-FileHash -LiteralPath $dst).Hash){$same++}
 else{
  $a=[IO.File]::ReadAllText($src).Replace(([string][char]13+[char]10),[string][char]10).TrimStart([char]0xFEFF)
  $b=[IO.File]::ReadAllText($dst).Replace(([string][char]13+[char]10),[string][char]10).TrimStart([char]0xFEFF)
  if($a -ceq $b){$same++}else{$different++;Write-Output ('APR_DIFFERENT='+$rel)}
 }
}
Write-Output "APR_RUNTIME_CENSUS_MATCHED=$same DIFFERENT=$different MISSING=$missing"
$root=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RAI_PUBLIC_CANDIDATE_REPOSITORY'
if(-not(Test-Path -LiteralPath $root -PathType Container)){Write-Output 'RF05_LOCAL_REPOSITORY=HOLD_MISSING';return}
$prior=$env:PI_RDI_COMPOSER_AUTOLOAD
try{
 $env:PI_RDI_COMPOSER_AUTOLOAD=Join-Path $runtime 'vendor\autoload.php'
 $out=Join-Path ([IO.Path]::GetTempPath()) ('pi-rf05-'+[guid]::NewGuid().ToString('N')+'.json')
 $mode=if($AdaptiveLayout){'--adaptive-local-pins'}else{'--local-pins'}
 $proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=1536M',('"'+(Join-Path $PSScriptRoot 'pi-rf04-corpus-inventory.php')+'"'),('"'+$root+'"'),$mode) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $out -RedirectStandardError ($out+'.stderr.log')
 if($proc.ExitCode -eq 2){Write-Output 'RF05_LOCAL_EXECUTION=HOLD';return}
 $r=Get-Content -LiteralPath $out -Raw -Encoding UTF8|ConvertFrom-Json
 foreach($p in $r.documents.PSObject.Properties){$v=$p.Value;[pscustomobject]@{id=$p.Name;sha=$v.source_sha256;pages=$v.inventory.expected_pages;routes=$v.format_profile.processing_route_counts}|ConvertTo-Json -Depth 8 -Compress|Write-Output}
 if($AdaptiveLayout){
  foreach($p in $r.documents.PSObject.Properties){
   $v=$p.Value.format_profile.adaptive_layout
   if($null -eq $v){throw 'RF05_ADAPTIVE_RECEIPT_HOLD'}
   [pscustomobject]@{id=$p.Name;second_pass=$v.route_counts;unbound_locators=$v.unbound_locator_count;binding=$v.binding}|ConvertTo-Json -Depth 8 -Compress|Write-Output
  }
 }
 Write-Output ('RF05_PROCESSED='+$r.processed_documents+'/'+$r.expected_documents)
 Write-Output ('RF05_HOLDS='+($r.holds|ConvertTo-Json -Compress))
 Write-Output ('RF05_NETWORK_REQUESTS='+$r.network_requests)
 Write-Output ('RF05_RECEIPT_SHA256='+(Get-FileHash -LiteralPath $out).Hash)
 Write-Output ('RF05_RECEIPT='+$out)
 Write-Output ('RF05_EXIT_CODE='+$proc.ExitCode)
}finally{$env:PI_RDI_COMPOSER_AUTOLOAD=$prior}
Write-Output 'APR_ORIGINAL_RUNTIME_NO_DEPLOYMENT=TRUE'

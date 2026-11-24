#requires -Version 5.1
<#
R108: explicit, source-only, fail-closed planner-facing UX cutover.
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
 [switch]$CiFailAfterSecondWrite
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$baseline='e33ef1aceb636602ed94cd82fd571f3b2841e3cf'
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$ci=($CiFixtureRoot -ne '')
if($ci){
 if($env:GITHUB_ACTIONS -cne 'true' -or -not $env:RUNNER_TEMP){
  throw 'PI_R108_W3B_CI_FIXTURE_FORBIDDEN'
 }
 $tmp=[IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd('\','/')
 $fixture=[IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd('\','/')
 if(-not $fixture.StartsWith($tmp+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){
  throw 'PI_R108_W3B_FIXTURE_OUTSIDE_RUNNER_TEMP'
 }
 $root=Join-Path $fixture 'laravel'
 $launcher=Join-Path $fixture 'launcher'
 Write-Output 'PI_R108_W3B_TARGET=DISPOSABLE_CI_FIXTURE'
}elseif($CiFailAfterSecondWrite){
 throw 'PI_R108_W3B_FAULT_INJECTION_FORBIDDEN_ON_ORIGINAL'
}else{
 Write-Output 'PI_R108_W3B_TARGET=ORIGINAL_SINGLE_8820'
}
$root=[IO.Path]::GetFullPath($root).TrimEnd('\','/')
$repo=[IO.Path]::GetFullPath($CandidateRepo).TrimEnd('\','/')
$prefix=$root+[IO.Path]::DirectorySeparatorChar
$replace=@('public\regulatory-laravel-r70g.html','public\regulatory-laravel-r70g.js','public\regulatory-workbench-r87.bundle.js')
$add=@()
$allow=@($replace)+@($add)
if($allow.Count -ne 3 -or @($allow|Select-Object -Unique).Count -ne 3){
 throw 'PI_R108_W3B_EXACT_THREE_FILE_MANIFEST_REQUIRED'
}
$protected=@(
 'public\development-case-r85.js','public\development-case-r85.html','public\planner-workflow-r108.css',
 'public\regulatory-workbench-r87.bundle.css',
 'artisan','.env','database\database.sqlite','config\pi.php','routes\web.php',
 'public\pi-ia-r69e.js','public\pi-ia-r69e.css','public\index.html',
 'public\planner-workflow-r108.js',
 'public\native-annex-r106.js','public\native-annex-r106.css',
 'public\pi-auth-shell.js','public\regulatory-planning-r88.js',
 'public\official-sources-r100.js','public\official-sources-r100.css',
 'resources\pi\r105-owner-bpk-byte-pins.json',
 'resources\pi\bpk-perwal12-dlh-0004-fiscal-candidate-r103.json'
)
foreach($rel in $protected){
 if(-not (Test-Path -LiteralPath (Join-Path $root $rel) -PathType Leaf)){
  throw ('PI_R108_W3B_PROTECTED_FILE_MISSING='+$rel)
 }
}
$launcherFile=Join-Path $launcher 'BUKA-PI-HIDDEN.vbs'
if(-not (Test-Path -LiteralPath $launcherFile -PathType Leaf)){
 throw 'PI_R108_W3B_EXISTING_LAUNCHER_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$pin=([string](& $git -C $repo rev-parse HEAD)).Trim().ToLowerInvariant()
if($LASTEXITCODE -ne 0 -or $pin -cne $ExpectedCommit.ToLowerInvariant()){
 throw 'PI_R108_W3B_CANDIDATE_COMMIT_NOT_PINNED'
}
$dirty=@(& $git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0){
 throw 'PI_R108_W3B_QA_WORKTREE_NOT_CLEAN'
}
& $git -C $repo merge-base --is-ancestor $baseline $pin
if($LASTEXITCODE -ne 0){throw 'PI_R108_W3B_R108_WAVE3A_ANCESTOR_NOT_FOUND'}
function Read-GitUtf8([string]$relative){
 $path='laravel/'+$relative.Replace([char]92,[char]47)
 $psi=New-Object Diagnostics.ProcessStartInfo
 $psi.FileName=$git
 $psi.Arguments='-C "'+$repo+'" show "'+$baseline+':'+$path+'"'
 $psi.UseShellExecute=$false
 $psi.RedirectStandardOutput=$true
 $psi.CreateNoWindow=$true
 $p=[Diagnostics.Process]::Start($psi)
 if($null -eq $p){throw 'PI_R108_W3B_R108_WAVE3A_BASELINE_PROCESS_MISSING'}
 $m=New-Object IO.MemoryStream
 try{
  $p.StandardOutput.BaseStream.CopyTo($m)
  $p.WaitForExit()
  if($p.ExitCode -ne 0 -or $m.Length -lt 5 -or $m.Length -gt 1048576){
   throw ('PI_R108_W3B_R108_WAVE3A_BASELINE_UNAVAILABLE='+$relative)
  }
  return [Text.UTF8Encoding]::new($false,$true).GetString($m.ToArray())
 }finally{$m.Dispose();$p.Dispose()}
}
function Normalize([string]$x){
 return ($x -replace '\r\n',[string][char]10).TrimStart([char]0xFEFF).TrimEnd([char]10,[char]13)
}
$required=@(
 @('public\regulatory-laravel-r70g.html','data-pi-regulatory-task="riwayat"'),
 @('public\regulatory-laravel-r70g.html','Pilih pekerjaan telaah regulasi'),
 @('public\regulatory-laravel-r70g.js','function reveal(hash)'),
 @('public\regulatory-workbench-r87.bundle.js','function reveal(hash)')
)
foreach($pair in $required){
 $file=Join-Path (Join-Path $repo 'laravel') $pair[0]
 if(-not (Test-Path -LiteralPath $file -PathType Leaf) -or
   -not ([IO.File]::ReadAllText($file,[Text.UTF8Encoding]::new($false,$true))).Contains($pair[1])){
  throw ('PI_R108_W3B_REQUIRED_SOURCE_CONTRACT_MISSING='+$pair[0])
 }
}
$rows=New-Object 'System.Collections.Generic.List[object]'
foreach($rel in $allow){
 $src=[IO.Path]::GetFullPath((Join-Path (Join-Path $repo 'laravel') $rel))
 $dst=[IO.Path]::GetFullPath((Join-Path $root $rel))
 if(-not $dst.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or
    -not (Test-Path -LiteralPath $src -PathType Leaf)){
  throw ('PI_R108_W3B_UNSAFE_ALLOWLIST_PATH='+$rel)
 }
 foreach($path in @($src,$dst)){
  if(Test-Path -LiteralPath $path){
   $obj=Get-Item -LiteralPath $path -Force
   if($obj.PSIsContainer -or ($obj.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
    throw ('PI_R108_W3B_FILE_REPARSE_OR_DIRECTORY_HOLD='+$rel)
   }
  }
  $parent=Split-Path $path -Parent
  while($parent -and (Test-Path -LiteralPath $parent -PathType Container)){
   $obj=Get-Item -LiteralPath $parent -Force
   if(($obj.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
    throw ('PI_R108_W3B_PARENT_REPARSE_HOLD='+$rel)
   }
   if($parent -eq $root -or $parent -eq $repo){break}
   $parent=Split-Path $parent -Parent
  }
 }
 $exists=Test-Path -LiteralPath $dst -PathType Leaf
 $oldHash=if($exists){(Get-FileHash -LiteralPath $dst -Algorithm SHA256).Hash}else{$null}
 $newHash=(Get-FileHash -LiteralPath $src -Algorithm SHA256).Hash
 if($replace -contains $rel){
  if(-not $exists){throw ('PI_R108_W3B_R108_WAVE3A_BASELINE_REPLACEMENT_MISSING='+$rel)}
  $actual=Normalize ([IO.File]::ReadAllText($dst,[Text.UTF8Encoding]::new($false,$true)))
  $base=Normalize (Read-GitUtf8 $rel)
  $candidate=Normalize ([IO.File]::ReadAllText($src,[Text.UTF8Encoding]::new($false,$true)))
  if($actual -cne $base -and $actual -cne $candidate){
   throw ('PI_R108_W3B_R108_WAVE3A_BASELINE_SOURCE_DRIFT_HOLD='+$rel)
  }
  Write-Output ('PI_R108_W3B_R108_WAVE3A_BASELINE_VERIFIED='+$rel)
 }elseif($exists -and $oldHash -cne $newHash){
  throw ('PI_R108_W3B_ADDITION_COLLIDES_WITH_UNKNOWN_FILE='+$rel)
 }
 $rows.Add([pscustomobject]@{
  Rel=$rel;Source=$src;Target=$dst;Existed=$exists;OldHash=$oldHash;
  NewHash=$newHash;Change=((-not $exists)-or $oldHash -cne $newHash)
 })
}
$changes=@($rows|Where-Object {$_.Change})
Write-Output ('PI_R108_W3B_QA_SHA='+$pin)
Write-Output ('PI_R108_W3B_ALLOWLIST='+$rows.Count)
Write-Output ('PI_R108_W3B_FILES_TO_CHANGE='+$changes.Count)
foreach($item in $changes){
 Write-Output ('PI_R108_W3B_CHANGE='+$item.Rel+':'+$(if($item.Existed){'REPLACE'}else{'ADD'}))
}
Write-Output 'PI_R108_W3B_DB_ENV_LAUNCHER=PROTECTED'
Write-Output 'PI_R108_W3B_FISCAL_ADMISSION=ZERO'
if(-not $Apply){
 Write-Output 'PI_R108_W3B_READ_ONLY_PREFLIGHT=PASS'
 return
}
if($changes.Count -eq 0){
 Write-Output 'PI_R108_W3B_CUTOVER=NO_CHANGE_ALREADY_MATCHED'
 return
}
$serverPid=0
if(-not $ci){
 $listeners=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
 if($listeners.Count -ne 1){throw 'PI_R108_W3B_SINGLE_8820_LISTENER_REQUIRED'}
 $serverPid=[int]$listeners[0].OwningProcess
 $process=Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction Stop
 if($null -eq $process -or $process.Name -notmatch '^(php|php-cgi)\.exe$'){
  throw 'PI_R108_W3B_8820_NOT_EXISTING_PHP'
 }
 $cached=@(Get-ChildItem -LiteralPath (Join-Path $root 'bootstrap\cache') -File -ErrorAction SilentlyContinue |
  Where-Object {$_.Name -eq 'config.php' -or $_.Name -like 'routes-*.php'})
 if($cached.Count -ne 0){throw 'PI_R108_W3B_ORIGINAL_ROUTE_OR_CONFIG_CACHE_HOLD'}
}
$protectedSha=@{}
foreach($rel in $protected){
 $protectedSha[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$launcherSha=(Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss-fff')+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)
$backup=Join-Path $launcher ('backups\R108_WAVE3B_REGULATORY_'+$stamp)
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
    throw ('PI_R108_W3B_BACKUP_SHA_HOLD='+$item.Rel)
   }
  }
 }
 $rows|Select-Object Rel,Existed,OldHash,NewHash,Change|
  ConvertTo-Json -Depth 4|Out-File -LiteralPath (Join-Path $backup 'SOURCE_MANIFEST.json') -Encoding UTF8
 foreach($item in $changes){
  New-Item -ItemType Directory -Path (Split-Path $item.Target -Parent) -Force|Out-Null
  $stage=$item.Target+'.r108w3b-stage-'+[guid]::NewGuid().ToString('N')
  Copy-Item -LiteralPath $item.Source -Destination $stage -ErrorAction Stop
  if((Get-FileHash -LiteralPath $stage -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_R108_W3B_STAGE_SHA_HOLD='+$item.Rel)
  }
  Add-Member -InputObject $item -NotePropertyName Stage -NotePropertyValue $stage -Force
  $stages.Add($stage)
 }
 $began=$true
 $written=0
 foreach($item in $changes){
  if($item.Existed){
   if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
    throw ('PI_R108_W3B_TARGET_CHANGED_DURING_APPLY='+$item.Rel)
   }
   [IO.File]::Replace([string]$item.Stage,[string]$item.Target,
    [string](Join-Path $backup ('atomic-'+$written)))
  }else{
   if(Test-Path -LiteralPath $item.Target){throw ('PI_R108_W3B_ADD_COLLISION='+$item.Rel)}
   Move-Item -LiteralPath $item.Stage -Destination $item.Target -ErrorAction Stop
  }
  $written++
  if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
   throw ('PI_R108_W3B_APPLIED_SHA_HOLD='+$item.Rel)
  }
  if($CiFailAfterSecondWrite -and $written -eq 2){
   throw 'PI_R108_W3B_CI_INJECTED_ROLLBACK'
  }
 }
 foreach($rel in $protected){
  if((Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash -cne $protectedSha[$rel]){
   throw ('PI_R108_W3B_PROTECTED_HASH_CHANGED='+$rel)
  }
 }
 if((Get-FileHash -LiteralPath $launcherFile -Algorithm SHA256).Hash -cne $launcherSha){
  throw 'PI_R108_W3B_LAUNCHER_SHA_CHANGED'
 }
 if(-not $ci){
  $after=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
  if($after.Count -ne 1 -or [int]$after[0].OwningProcess -ne $serverPid){
   throw 'PI_R108_W3B_ORIGINAL_PHP_PID_CHANGED'
  }
  $setup=Invoke-RestMethod -Uri 'http://127.0.0.1:8820/api/auth/setup-status' -Headers @{Accept='application/json'} -TimeoutSec 12
  if($setup.needs_setup -ne $false -or -not ($setup.csrf -is [string]) -or [string]::IsNullOrWhiteSpace($setup.csrf)){
   throw 'PI_R108_W3B_LOGIN_OR_CSRF_REGRESSION'
  }
  foreach($url in @('/api/qa-superior-r106/annex-fiscal-review','/api/qa-superior-r106/annex-pdf?source=base')){
   $status=0
   try{
    $r=Invoke-WebRequest -Uri ('http://127.0.0.1:8820'+$url) -UseBasicParsing -Headers @{Accept='application/json'} -TimeoutSec 12
    $status=[int]$r.StatusCode
   }catch [System.Net.WebException]{
    if($null -eq $_.Exception.Response){throw}
    $status=[int]$_.Exception.Response.StatusCode
   }
   if($status -ne 401){throw ('PI_R108_W3B_NEW_ROUTE_NOT_AUTH_GUARDED='+$url+':'+$status)}
  }
  $hasher=[Security.Cryptography.SHA256]::Create()
  try{
   foreach($rel in @('public\regulatory-laravel-r70g.html','public\regulatory-laravel-r70g.js','public\regulatory-workbench-r87.bundle.js')){
    $path=$rel.Substring(7).Replace([char]92,[char]47)
    $url='http://127.0.0.1:8820/'+$path+'?r108='+[guid]::NewGuid().ToString('N')
    $r=Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 12 -Headers @{'Cache-Control'='no-cache'}
    if($r.StatusCode -ne 200){throw ('PI_R108_W3B_ASSET_NOT_200='+$rel)}
    $served=[BitConverter]::ToString($hasher.ComputeHash($r.RawContentStream.ToArray())).Replace('-','')
    $source=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
    if($served -cne $source){throw ('PI_R108_W3B_HTTP_ASSET_SHA_MISMATCH='+$rel)}
   }
  }finally{$hasher.Dispose()}
  Write-Output ('PI_R108_W3B_ORIGINAL_PHP_PID_PRESERVED='+$serverPid)
  Write-Output 'PI_R108_W3B_ORIGINAL_LOGIN_CSRF=PASS'
  Write-Output 'PI_R108_W3B_ANONYMOUS_R106_API_AND_PDF=401'
  Write-Output 'PI_R108_W3B_LIVE_HTML_JS_CSS_SHA=PASS'
 }
 Write-Output ('PI_R108_W3B_BACKUP='+$backup)
 Write-Output 'PI_R108_W3B_CODE_CUTOVER=APPLIED_VERIFIED'
 Write-Output 'PI_R108_W3B_SQLITE_ENV_APPKEY_LAUNCHER=UNCHANGED'
 Write-Output 'PI_R108_W3B_NUMERIC_REVIEW_PRODUCTION_ADMISSION=DENIED'
}catch{
 $reason=$_.Exception.Message
 if($began){
  foreach($item in $changes){
   if($item.Existed){
    $saved=Join-Path (Join-Path $backup 'before') $item.Rel
    if(-not (Test-Path -LiteralPath $saved -PathType Leaf)){
     throw ('PI_R108_W3B_MANUAL_RECOVERY_REQUIRED_BACKUP_MISSING='+$item.Rel)
    }
    $current=(Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash
    if($current -cne $item.OldHash -and $current -cne $item.NewHash){
     throw ('PI_R108_W3B_MANUAL_RECOVERY_REQUIRED_CONCURRENT_CHANGE='+$item.Rel)
    }
    Copy-Item -LiteralPath $saved -Destination $item.Target -Force -ErrorAction Stop
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.OldHash){
     throw ('PI_R108_W3B_RESTORE_HASH_ERROR='+$item.Rel)
    }
   }elseif(Test-Path -LiteralPath $item.Target -PathType Leaf){
    if((Get-FileHash -LiteralPath $item.Target -Algorithm SHA256).Hash -cne $item.NewHash){
     throw ('PI_R108_W3B_MANUAL_RECOVERY_REQUIRED_ADDITION_DRIFT='+$item.Rel)
    }
    Remove-Item -LiteralPath $item.Target -Force -ErrorAction Stop
   }
  }
  Write-Output 'PI_R108_W3B_AUTO_ROLLBACK=RESTORED_OLD_CODE_VERIFIED'
 }
 throw ('PI_R108_W3B_CUTOVER_ABORTED='+$reason)
}finally{
 foreach($stage in $stages){
  if(Test-Path -LiteralPath $stage -PathType Leaf){
   Remove-Item -LiteralPath $stage -Force -ErrorAction SilentlyContinue
  }
 }
}

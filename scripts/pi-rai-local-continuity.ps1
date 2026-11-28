#requires -Version 5.1
param([Parameter(Mandatory=$true)][string]$ExpectedCommit)
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if((& git -C $repo rev-parse HEAD).Trim() -cne $ExpectedCommit -or $LASTEXITCODE -ne 0){throw 'PI_RAI_PIN_HOLD'}
if(@(& git -C $repo status --porcelain).Count -ne 0 -or $LASTEXITCODE -ne 0){throw 'PI_RAI_DIRTY_HOLD'}
$listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen)
if($listener.Count -ne 1){throw 'PI_RAI_LISTENER_HOLD'}
$php=(Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)").ExecutablePath
if(-not $php -or (Split-Path $php -Leaf) -ne 'php.exe'){throw 'PI_RAI_PHP_HOLD'}
$src=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RF04_CORPUS\187aae97c8544be9be3a49de05406c0c'
$root=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\RAI_PUBLIC_CANDIDATE_REPOSITORY'
if(-not(Test-Path -LiteralPath $root)){New-Item -ItemType Directory -Path $root|Out-Null}
$out=Join-Path $env:LOCALAPPDATA ('PlanningIntelligence\RAI_RECEIPTS\'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $out|Out-Null
$receipt=Join-Path $out 'continuity.json'
$script=Join-Path $repo 'scripts\pi-rai-local-continuity.php'
$proc=Start-Process -FilePath $php -ArgumentList @('-d','memory_limit=768M',('"'+$script+'"'),('"'+$src+'"'),('"'+$root+'"')) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $receipt -RedirectStandardError ($receipt+'.stderr.log')
if($proc.ExitCode -eq 2){Get-Content -LiteralPath ($receipt+'.stderr.log');throw 'PI_RAI_EXECUTION_HOLD'}
$r=Get-Content -LiteralPath $receipt -Raw -Encoding UTF8|ConvertFrom-Json
$r.records.PSObject.Properties|ForEach-Object {Write-Output ($_.Name+' '+($_.Value|ConvertTo-Json -Compress))}
Write-Output ('RAI_PROCESSED='+$r.processed+'/'+$r.expected)
Write-Output ('RAI_OFFLINE_TRANSPORT_CALLS='+$r.offline_transport_calls)
Write-Output ('RAI_HOLDS='+($r.holds|ConvertTo-Json -Compress))
Write-Output ('RAI_RECEIPT='+$receipt)
Write-Output ('RAI_RECEIPT_SHA256='+(Get-FileHash -LiteralPath $receipt -Algorithm SHA256).Hash)
if($proc.ExitCode -ne 0 -or $r.processed -ne 8 -or $r.offline_transport_calls -ne 0){throw 'PI_RAI_CONTINUITY_HOLD'}
Write-Output 'RAI_LOCAL_CONTINUITY=PASS_CANDIDATE_ONLY'

#requires -Version 5.1
<#
PI R100: safe 8-file code-and-index rollout to ORIGINAL single Laravel origin.
Default READ_ONLY. No migrations, Composer, password or APP_KEY changes.
One restore manifest, source SHA256, baseline text drift HOLD, rollback.
Synthetic -CiFixtureRoot is restricted to GitHub Actions RUNNER_TEMP.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[a-fA-F0-9]{40}$')][string]$ExpectedCommit,
    [switch]$Apply,
    [string]$CiFixtureRoot,
    [switch]$CiFailAfterFirstSwap
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$base='1046b339ccc432795b1b2fd659cbcd77ffa65e21'
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$ci=-not [string]::IsNullOrWhiteSpace($CiFixtureRoot)
if($ci) {
    if($env:GITHUB_ACTIONS -ne 'true' -or [string]::IsNullOrWhiteSpace($env:RUNNER_TEMP)) {
        throw 'PI_R100_CI_FIXTURE_FORBIDDEN_OUTSIDE_GITHUB_ACTIONS'
    }
    $runner=[IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd([char]92,[char]47)
    $fixture=[IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd([char]92,[char]47)
    if(-not $fixture.StartsWith(($runner+[IO.Path]::DirectorySeparatorChar),[StringComparison]::OrdinalIgnoreCase)) {
        throw 'PI_R100_FIXTURE_OUTSIDE_EPHEMERAL_RUNNER'
    }
    $root=Join-Path $fixture 'laravel'
    $launcher=Join-Path $fixture 'launcher'
    Write-Output 'PI_R100_TARGET=DISPOSABLE_WINDOWS_CI'
} elseif($CiFailAfterFirstSwap) {
    throw 'PI_R100_FAULT_INJECTION_FORBIDDEN_ON_ORIGINAL'
} else {
    Write-Output 'PI_R100_TARGET=PROTECTED_ORIGINAL_8820'
}
$replacements=@('config\pi.php','routes\web.php','public\development-case-r85.html')
$additions=@(
    'app\Http\Controllers\Api\OfficialSourceMetadataController.php',
    'app\Services\Pi\Development\OfficialSourceMetadataCatalogue.php',
    'resources\pi\official-source-metadata-r100.json',
    'public\official-sources-r100.css',
    'public\official-sources-r100.js'
)
$allow=@($replacements)+@($additions)
$protected=@(
    'artisan','.env','database\database.sqlite','public\overview-r69c.json',
    'public\uu12-2011-r70a.pdf','public\development-case-r85.css',
    'public\regulatory-planning-r88.js',
    'resources\pi\affairs-nomenclature-seed-r87.json',
    'resources\pi\operational-definitions-r88.json'
)
if($allow.Count -ne 8 -or @($allow|Select-Object -Unique).Count -ne 8) {
    throw 'PI_R100_ALLOWLIST_INVALID'
}
foreach($rel in $protected) {
    if(-not (Test-Path -LiteralPath (Join-Path $root $rel) -PathType Leaf)) {
        throw ('PI_R100_PROTECTED_SENTINEL_MISSING='+$rel)
    }
}
if(-not (Test-Path -LiteralPath (Join-Path $launcher 'BUKA-PI-HIDDEN.vbs') -PathType Leaf)){
    throw 'PI_R100_ORIGINAL_LAUNCHER_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R100_SOURCE_PIN_MISMATCH'
}
$dirty=@(& $git -C $CandidateRepo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0){throw 'PI_R100_CANDIDATE_NOT_CLEAN'}
& $git -C $CandidateRepo merge-base --is-ancestor $base $head
if($LASTEXITCODE -ne 0){throw 'PI_R100_NOT_DESCENDANT_OF_ACCEPTED_R94'}

function Normalize-Text([string]$value) {
    return ($value -replace '\r\n',[string][char]10).TrimStart([char]0xFEFF).TrimEnd([char]10,[char]13)
}
$catalogue=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\resources\pi\official-source-metadata-r100.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if($catalogue.schema -cne 'PI_R100_OFFICIAL_SOURCE_METADATA_CATALOGUE_V1' -or
   @($catalogue.documents).Count -ne 18 -or
   $catalogue.document_bytes_verification -cne 'NOT_ACQUIRED_NOT_HASH_PINNED' -or
   $catalogue.numeric_comparison -cne 'BLOCKED' -or
   $catalogue.production_admission -cne 'DENIED' -or
   $catalogue.cross_year_subactivity_equivalence -cne 'NOT_VERIFIED') {
    throw 'PI_R100_METADATA_CONTRACT_UNSAFE'
}
foreach($doc in @($catalogue.documents)){
    if($doc.source_metadata_status -cne 'OFFICIAL_JDIH_INDEXED' -or
       $doc.document_bytes_status -cne 'NOT_ACQUIRED_NOT_HASH_PINNED' -or
       $null -ne $doc.document_sha256 -or $null -ne $doc.numeric_amount -or
       $doc.official_url -notmatch '^https://jdih\.jogjakota\.go\.id/(home/produk_hukum/detail/[0-9]+|asset/naskah/[a-zA-Z0-9._-]+\.pdf)$') {
        throw ('PI_R100_UNTRUSTED_OFFICIAL_METADATA='+$doc.id)
    }
}
$sourceRoute=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\routes\web.php') -Raw
$sourceConfig=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\config\pi.php') -Raw
$sourceHtml=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\public\development-case-r85.html') -Raw
if(-not $sourceRoute.Contains('/qa-superior-r100/official-sources') -or
   -not $sourceRoute.Contains("Route::middleware(['auth', 'pi.tenant'])") -or
   -not $sourceConfig.Contains("pi/official-source-metadata-r100.json") -or
   -not $sourceConfig.Contains("env('PI_R89_CLOUD_ENABLED', false)") -or
   -not $sourceHtml.Contains('id="r100-list"')) {
    throw 'PI_R100_AUTH_CONFIG_OR_UI_GUARD_MISSING'
}
$root=[IO.Path]::GetFullPath($root).TrimEnd([char]92,[char]47)
$prefix=$root+[IO.Path]::DirectorySeparatorChar
$records=New-Object 'System.Collections.Generic.List[object]'
foreach($rel in $allow){
    $src=[IO.Path]::GetFullPath((Join-Path (Join-Path $CandidateRepo 'laravel') $rel))
    $target=[IO.Path]::GetFullPath((Join-Path $root $rel))
    if(-not $target.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or
       -not (Test-Path -LiteralPath $src -PathType Leaf)){
        throw ('PI_R100_UNSAFE_ALLOWLIST_PATH='+$rel)
    }
    $parent=Split-Path $target -Parent
    while($parent.Length -ge $root.Length){
        if(Test-Path -LiteralPath $parent -PathType Container){
            $folder=Get-Item -LiteralPath $parent -Force
            if(($folder.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
                throw ('PI_R100_REPARSE_FOLDER_FORBIDDEN='+$rel)
            }
        }
        if($parent -eq $root){break}
        $parent=Split-Path $parent -Parent
    }
    $existing=Test-Path -LiteralPath $target -PathType Leaf
    if(Test-Path -LiteralPath $target){
        $item=Get-Item -LiteralPath $target -Force
        if($item.PSIsContainer -or
           ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
            throw ('PI_R100_EXISTING_FILE_UNSAFE='+$rel)
        }
    }
    if($replacements -contains $rel){
        if(-not $existing){throw ('PI_R100_PROTECTED_CODE_MISSING='+$rel)}
        $gitpath='laravel/'+$rel.Replace([char]92,[char]47)
        $prior=@(& $git -C $CandidateRepo show ($base+':'+$gitpath))
        if($LASTEXITCODE -ne 0 -or $prior.Count -eq 0) {
            throw ('PI_R100_BASELINE_NOT_READABLE='+$rel)
        }
        $before=Normalize-Text ($prior -join [string][char]10)
        $now=Normalize-Text ([IO.File]::ReadAllText($target))
        $new=Normalize-Text ([IO.File]::ReadAllText($src))
        if($now -cne $before -and $now -cne $new){
            throw ('PI_R100_ORIGINAL_CODE_DRIFT_HOLD='+$rel)
        }
    }
    $newSha=(Get-FileHash -LiteralPath $src -Algorithm SHA256).Hash
    $oldSha=if($existing){(Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash}else{$null}
    if(($additions -contains $rel) -and $existing -and $oldSha -ne $newSha){
        throw ('PI_R100_EXISTING_NEW_SOURCE_DIFFERS_HOLD='+$rel)
    }
    $records.Add([pscustomobject]@{
        Rel=$rel;Source=$src;Target=$target;Existed=$existing
        OldSha=$oldSha;NewSha=$newSha
        Change=((-not $existing) -or $oldSha -ne $newSha)
    })
}
$changes=@($records|Where-Object {$_.Change})
Write-Output ('PI_R100_SOURCE_COMMIT='+$head)
Write-Output ('PI_R100_ALLOWLIST_FILES='+$records.Count)
Write-Output ('PI_R100_FILES_TO_CHANGE='+$changes.Count)
Write-Output 'PI_R100_OFFICIAL_METADATA_RECORDS=18'
Write-Output 'PI_R100_SOURCE_BYTES_VERIFIED=0'
Write-Output 'PI_R100_SUBACTIVITY_NUMBERS_ADMITTED=0'
foreach($entry in $changes) {
    Write-Output ('PI_R100_CHANGE='+$entry.Rel+':'+$(if($entry.Existed){'REPLACE'}else{'ADD'}))
}
if(-not $Apply){
    Write-Output 'PI_R100_READ_ONLY_PREFLIGHT=PASS'
    return
}
if($changes.Count -eq 0){
    Write-Output 'PI_R100_CODE_RELEASE=NO_CHANGE'
    return
}
$serverPid=0
if(-not $ci){
    $listener=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
    if($listener.Count -ne 1){throw 'PI_R100_ORIGINAL_SINGLE_PHP_8820_REQUIRED'}
    $serverPid=[int]$listener[0].OwningProcess
    $process=Get-CimInstance Win32_Process -Filter "ProcessId=$serverPid" -ErrorAction Stop
    if($null -eq $process -or $process.Name -notmatch '^(php|php-cgi)\.exe$') {
        throw 'PI_R100_8820_NOT_PHP'
    }
    $cached=@(Get-ChildItem -LiteralPath (Join-Path $root 'bootstrap\cache') -File -ErrorAction SilentlyContinue |
        Where-Object {$_.Name -eq 'config.php' -or $_.Name -like 'routes-*.php'})
    if($cached.Count -gt 0){throw 'PI_R100_ORIGINAL_CACHED_ROUTES_HOLD'}
}
$protectedSha=@{}
foreach($rel in $protected){
    $protectedSha[$rel]=(Get-FileHash -LiteralPath (Join-Path $root $rel) -Algorithm SHA256).Hash
}
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss-fff')+'-'+[guid]::NewGuid().ToString('N').Substring(0,8)
$backup=Join-Path $launcher ('backups\R100_OFFICIAL_SOURCES_'+$stamp)
$stages=New-Object 'System.Collections.Generic.List[string]'
$started=$false
try {
    New-Item -ItemType Directory -Path (Join-Path $backup 'before') -Force | Out-Null
    foreach($entry in $records){
        if($entry.Existed){
            $backupFile=Join-Path (Join-Path $backup 'before') $entry.Rel
            New-Item -ItemType Directory -Path (Split-Path $backupFile -Parent) -Force | Out-Null
            Copy-Item -LiteralPath $entry.Target -Destination $backupFile -ErrorAction Stop
            if((Get-FileHash $backupFile -Algorithm SHA256).Hash -ne $entry.OldSha){
                throw ('PI_R100_BACKUP_HASH_MISMATCH='+$entry.Rel)
            }
        }
    }
    $records | Select-Object Rel,Existed,Change,OldSha,NewSha | ConvertTo-Json -Depth 3 |
        Out-File -LiteralPath (Join-Path $backup 'SOURCE_MANIFEST.json') -Encoding UTF8
    foreach($entry in $changes){
        $folder=Split-Path $entry.Target -Parent
        New-Item -ItemType Directory -Path $folder -Force | Out-Null
        $stage=$entry.Target+'.r100-stage-'+[guid]::NewGuid().ToString('N')
        Copy-Item -LiteralPath $entry.Source -Destination $stage -ErrorAction Stop
        if((Get-FileHash $stage -Algorithm SHA256).Hash -ne $entry.NewSha){
            throw ('PI_R100_STAGE_HASH_MISMATCH='+$entry.Rel)
        }
        Add-Member -InputObject $entry -NotePropertyName Stage -NotePropertyValue $stage -Force
        $stages.Add($stage)
    }
    $started=$true
    $index=0
    foreach($entry in $changes){
        if($entry.Existed){
            if((Get-FileHash $entry.Target -Algorithm SHA256).Hash -ne $entry.OldSha){
                throw ('PI_R100_TARGET_CHANGED_DURING_RELEASE='+$entry.Rel)
            }
            [IO.File]::Replace([string]$entry.Stage,[string]$entry.Target,
                [string](Join-Path $backup ('atomic-'+$index)))
        } else {
            if(Test-Path -LiteralPath $entry.Target){throw ('PI_R100_ADD_COLLISION='+$entry.Rel)}
            Move-Item -LiteralPath $entry.Stage -Destination $entry.Target -ErrorAction Stop
        }
        $index++
        if((Get-FileHash $entry.Target -Algorithm SHA256).Hash -ne $entry.NewSha){
            throw ('PI_R100_APPLIED_SHA_MISMATCH='+$entry.Rel)
        }
        if($CiFailAfterFirstSwap -and $index -eq 1){
            throw 'PI_R100_CI_INJECTED_FIRST_SWAP_FAILURE'
        }
    }
    foreach($rel in $protected){
        if((Get-FileHash (Join-Path $root $rel) -Algorithm SHA256).Hash -ne $protectedSha[$rel]){
            throw ('PI_R100_PROTECTED_ASSET_CHANGED='+$rel)
        }
    }
    if(-not $ci){
        $listenerAfter=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
        if($listenerAfter.Count -ne 1 -or [int]$listenerAfter[0].OwningProcess -ne $serverPid){
            throw 'PI_R100_PHP_PID_CHANGED'
        }
        $status=0
        try {
            $response=Invoke-WebRequest -Uri 'http://127.0.0.1:8820/api/qa-superior-r100/official-sources' -Headers @{Accept='application/json'} -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop
            $status=[int]$response.StatusCode
        } catch [System.Net.WebException] {
            if($null -eq $_.Exception.Response){throw}
            $status=[int]$_.Exception.Response.StatusCode
        }
        if($status -ne 401){throw ('PI_R100_AUTH_GUARD_HTTP_NOT_401='+$status)}
        $hasher=[Security.Cryptography.SHA256]::Create()
        try {
            foreach($name in @('development-case-r85.html','official-sources-r100.js','official-sources-r100.css')){
                $response=Invoke-WebRequest -Uri ('http://127.0.0.1:8820/'+$name+'?r100='+[guid]::NewGuid().ToString('N')) -Headers @{'Cache-Control'='no-cache'} -UseBasicParsing -TimeoutSec 10
                if([int]$response.StatusCode -ne 200){throw ('PI_R100_HTTP_ASSET_NOT_200='+$name)}
                $hash=[BitConverter]::ToString($hasher.ComputeHash($response.RawContentStream.ToArray())).Replace('-','')
                $matching=@($records|Where-Object {$_.Rel -eq ('public\'+$name)})
                if($matching.Count -ne 1 -or $matching[0].NewSha -ne $hash){
                    throw ('PI_R100_HTTP_ASSET_HASH_MISMATCH='+$name)
                }
            }
        } finally {$hasher.Dispose()}
        Write-Output 'PI_R100_HTTP_ANONYMOUS_API_401_AND_ASSET_SHA=PASS'
        Write-Output ('PI_R100_ORIGINAL_PHP_PID_PRESERVED='+$serverPid)
    }
    Write-Output 'PI_R100_SQLITE_ENV_APPKEY_LAUNCHER_R88=UNCHANGED'
    Write-Output ('PI_R100_BACKUP='+$backup)
    Write-Output 'PI_R100_OFFICIAL_SOURCE_CUTOVER=APPLIED_VERIFIED'
} catch {
    $reason=$_.Exception.Message
    if($started){
        foreach($entry in $records){
            if($entry.Existed){
                $backupFile=Join-Path (Join-Path $backup 'before') $entry.Rel
                Copy-Item -LiteralPath $backupFile -Destination $entry.Target -Force -ErrorAction Stop
                if((Get-FileHash $entry.Target -Algorithm SHA256).Hash -ne $entry.OldSha){
                    throw ('PI_R100_ROLLBACK_HASH_MISMATCH='+$entry.Rel)
                }
            } elseif(Test-Path -LiteralPath $entry.Target -PathType Leaf){
                Remove-Item -LiteralPath $entry.Target -Force -ErrorAction Stop
            }
        }
        Write-Output 'PI_R100_ROLLBACK=ALL_ORIGINAL_HASHES_RESTORED'
    }
    throw ('PI_R100_RELEASE_ABORTED='+$reason)
} finally {
    foreach($stage in $stages){
        if(Test-Path -LiteralPath $stage -PathType Leaf){
            Remove-Item -LiteralPath $stage -Force -ErrorAction SilentlyContinue
        }
    }
}

#requires -Version 5.1
<#
R94 code-only, fail-closed single-origin R88/R89 cutover candidate.
DEFAULT=READ_ONLY. Explicit -Apply only after isolated CI E2E.
Never copies original .env, APP_KEY, SQLite, sessions, evidence PDFs,
storage, user records, shortcut/VBS/CMD or 28 main-only assets.
No migrations, PHP restarts, Composer, key generation or network egress.
CI-only synthetic fixture can validate real Apply and injected rollback.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$CandidateRepo,
    [Parameter(Mandatory=$true)][ValidatePattern('^[0-9a-fA-F]{40}$')][string]$ExpectedCommit,
    [switch]$Apply,
    [string]$CiFixtureRoot,
    [switch]$CiFailAfterFirstSwap
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$ciMode=-not [string]::IsNullOrWhiteSpace($CiFixtureRoot)
if($ciMode) {
    if($env:GITHUB_ACTIONS -ne 'true' -or [string]::IsNullOrWhiteSpace($env:RUNNER_TEMP)) {
        throw 'PI_R94_CI_FIXTURE_ONLY_ON_GITHUB_ACTIONS'
    }
    $runnerFull=[IO.Path]::GetFullPath($env:RUNNER_TEMP).TrimEnd([char]92,[char]47)
    $fixtureFull=[IO.Path]::GetFullPath($CiFixtureRoot).TrimEnd([char]92,[char]47)
    if(-not $fixtureFull.StartsWith(($runnerFull+[IO.Path]::DirectorySeparatorChar),
        [StringComparison]::OrdinalIgnoreCase)) {
        throw 'PI_R94_CI_FIXTURE_OUTSIDE_RUNNER_TEMP'
    }
    $root=Join-Path $fixtureFull 'laravel'
    $launcher=Join-Path $fixtureFull 'launcher'
    Write-Output 'PI_R94_TARGET=DISPOSABLE_WINDOWS_CI_FIXTURE'
} elseif ($CiFailAfterFirstSwap) {
    throw 'PI_R94_FAULT_INJECTION_FORBIDDEN_ON_MAIN'
} else {
    Write-Output 'PI_R94_TARGET=PROTECTED_ORIGINAL_LARAVEL_8820'
}
$root=[IO.Path]::GetFullPath($root).TrimEnd([char]92,[char]47)
$rootPrefix=$root+[IO.Path]::DirectorySeparatorChar
$files=@(
    'app\Providers\AppServiceProvider.php',
    'config\pi.php',
    'routes\web.php',
    'public\development-case-r85.css',
    'public\development-case-r85.html',
    'public\regulatory-laravel-r70g.html',
    'public\regulatory-lifecycle-r86.css',
    'public\regulatory-lifecycle-r86.js',
    'public\pi-auth-shell.js',
    'app\Http\Controllers\Api\R89SemanticRegulatoryController.php',
    'app\Http\Controllers\Api\RegulatoryPlanningCrosswalkController.php',
    'app\Services\Pi\Development\CloudSemanticCandidateProvider.php',
    'app\Services\Pi\Development\HybridSemanticReasoner.php',
    'app\Services\Pi\Development\R89SemanticRegulatoryAnalysis.php',
    'app\Services\Pi\Development\RegulatoryPlanningCrosswalk.php',
    'app\Services\Pi\Development\SemanticCandidateProvider.php',
    'public\regulatory-planning-r88.js'
)
if($files.Count -ne 17 -or @($files|Select-Object -Unique).Count -ne 17) {
    throw 'PI_R94_ALLOWED_CODE_MANIFEST_INVALID'
}
$guarded=@(
    'artisan','.env','database\database.sqlite',
    'public\favicon.ico','public\overview-r69c.json',
    'public\robots.txt','public\uu12-2011-r70a.pdf'
)
foreach($relative in $guarded) {
    if(-not (Test-Path -LiteralPath (Join-Path $root $relative) -PathType Leaf)) {
        throw ('PI_R94_PROTECTED_ORIGINAL_SENTINEL_MISSING='+$relative)
    }
}
if(-not (Test-Path -LiteralPath (Join-Path $launcher 'BUKA-PI-HIDDEN.vbs') -PathType Leaf)) {
    throw 'PI_R94_ORIGINAL_LAUNCHER_MISSING'
}
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $CandidateRepo rev-parse HEAD)).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $ExpectedCommit.ToLowerInvariant()) {
    throw 'PI_R94_SOURCE_COMMIT_PIN_MISMATCH'
}
$dirty=@(& $git -C $CandidateRepo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -ne 0) {
    throw 'PI_R94_SOURCE_WORKTREE_DIRTY'
}
# Do not use Git hooks, native scripts, Composer setup or migration commands.
# The candidate is only a source tree with exact pin and clean status.
$records=New-Object 'System.Collections.Generic.List[object]'
foreach($relative in $files) {
    $candidate=[IO.Path]::GetFullPath((Join-Path (Join-Path $CandidateRepo 'laravel') $relative))
    $dest=[IO.Path]::GetFullPath((Join-Path $root $relative))
    if(-not $dest.StartsWith($rootPrefix,[StringComparison]::OrdinalIgnoreCase) -or
        -not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        throw ('PI_R94_INVALID_CANDIDATE_OR_DEST='+$relative)
    }
    foreach($folder in @((Split-Path $dest -Parent),(Split-Path $candidate -Parent))) {
        if(Test-Path -LiteralPath $folder -PathType Container) {
            $item=Get-Item -LiteralPath $folder -Force
            if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
                throw ('PI_R94_REPARSE_PATH_FORBIDDEN='+$relative)
            }
        }
    }
    if(Test-Path -LiteralPath $dest) {
        $item=Get-Item -LiteralPath $dest -Force
        if(-not $item.PSIsContainer -and
            ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -eq 0) {
            $oldHash=(Get-FileHash -LiteralPath $dest -Algorithm SHA256).Hash
            $exists=$true
        } else {throw ('PI_R94_ORIGINAL_FILE_UNSAFE='+$relative)}
    } else {
        $exists=$false
        $oldHash=$null
    }
    $newHash=(Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash
    $records.Add([pscustomobject]@{
        Name=$relative; Source=$candidate; Destination=$dest
        Existed=$exists; OldHash=$oldHash; NewHash=$newHash
        Change=((-not $exists) -or $oldHash -ne $newHash)
    })
}
$changed=@($records | Where-Object {$_.Change})
if($changed.Count -eq 0) {throw 'PI_R94_NO_SOURCE_DIFFERENCE_HOLD'}
# Validate required new Laravel routes and explicit CSRF/privacy boundaries.
$route=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\routes\web.php') -Raw
$ui=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\public\regulatory-planning-r88.js') -Raw
$auth=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\public\pi-auth-shell.js') -Raw
$config=Get-Content -LiteralPath (Join-Path $CandidateRepo 'laravel\config\pi.php') -Raw
foreach($required in @('qa-superior-r88/planning-crosswalk',
    'qa-superior-r89/semantic-preview','qa-superior-r89/semantic-candidates',
    'auth/login','auth/logout', 'pi.tenant')) {
    if(-not $route.Contains($required)){throw ('PI_R94_CANDIDATE_ROUTE_GUARD_MISSING='+$required)}
}
if($ui.Contains('X-PI-CSRF') -or $auth.Contains('X-PI-CSRF') -or
    -not $ui.Contains('X-CSRF-TOKEN') -or -not $auth.Contains('X-CSRF-TOKEN') -or
    -not $ui.Contains('approved_provider_charges:true') -or
    -not $ui.Contains('approved_context_sha256:semanticPreview.preview.sha256') -or
    -not $config.Contains("env('PI_R89_CLOUD_ENABLED', false)")) {
    throw 'PI_R94_CANDIDATE_CSRF_OR_CLOUD_FAIL_CLOSED_GUARD_MISSING'
}
Write-Output ('PI_R94_CANDIDATE_PIN='+$head)
Write-Output ('PI_R94_ALLOWLIST_COUNT='+$files.Count)
Write-Output ('PI_R94_CHANGED_OR_ADDED_COUNT='+$changed.Count)
Write-Output 'PI_R94_PROTECTED_DATABASE_ENV_LAUNCHER_ASSETS=VERIFIED'
Write-Output 'PI_R94_R89_CLOUD_DEFAULT=DISABLED'
Write-Output 'PI_R94_RUNTIME_CHANGE_SCOPE=CODE_ONLY'
foreach($record in $changed) {
    Write-Output ('PI_R94_CODE_CHANGE='+$record.Name+':'+
        ($(if($record.Existed){'REPLACE'}else{'ADD'})))
}
if(-not $Apply) {
    Write-Output 'PI_R94_CODE_CUTOVER=READ_ONLY_PREFLIGHT_PASS'
    return
}
# Main app must already be listening on the single original port. No launch.
if(-not $ciMode) {
    $listeners=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
    if($listeners.Count -ne 1) {throw 'PI_R94_ORIGINAL_8820_NOT_SINGLE_LISTENER'}
    $ownedPid=[int]$listeners[0].OwningProcess
    $process=Get-CimInstance Win32_Process -Filter "ProcessId=$ownedPid" -ErrorAction Stop
    if($null -eq $process -or $process.Name -notmatch '^(php|php-cgi)\.exe$') {
        throw 'PI_R94_8820_NOT_OWNED_BY_PHP'
    }
    # Any Laravel cached route version or cached config can hide new R88/R89
    # registrations. Hold rather than clearing production caches in-place.
    $cacheDir=Join-Path $root 'bootstrap\cache'
    $cached=@(Get-ChildItem -LiteralPath $cacheDir -File -ErrorAction SilentlyContinue |
      Where-Object { $_.Name -eq 'config.php' -or $_.Name -like 'routes-*.php' })
    if($cached.Count -gt 0) {
        throw ('PI_R94_ORIGINAL_CACHED_ROUTE_OR_CONFIG_HOLD='+$cached[0].Name)
    }
}
$time=Get-Date -Format 'yyyyMMdd-HHmmss'
$backup=Join-Path $launcher ('backups\R94_CODE_BEFORE_ONE_ORIGIN_'+$time)
if(Test-Path -LiteralPath $backup){throw 'PI_R94_BACKUP_COLLISION'}
New-Item -ItemType Directory -Path (Join-Path $backup 'before') -Force -ErrorAction Stop | Out-Null
$sentinelHashes=@{}
foreach($relative in $guarded) {
    $sentinelHashes[$relative]=(Get-FileHash -LiteralPath (Join-Path $root $relative) -Algorithm SHA256).Hash
}
$stages=New-Object 'System.Collections.Generic.List[string]'
$started=$false
try {
    # Snapshot every original target before ANY replacement.
    foreach($r in $records) {
        if($r.Existed) {
            $copy=Join-Path (Join-Path $backup 'before') $r.Name
            New-Item -ItemType Directory -Path (Split-Path $copy -Parent) -Force | Out-Null
            Copy-Item -LiteralPath $r.Destination -Destination $copy -ErrorAction Stop
            if((Get-FileHash $copy -Algorithm SHA256).Hash -ne $r.OldHash) {
                throw ('PI_R94_ORIGINAL_BACKUP_HASH_FAILED='+$r.Name)
            }
        }
    }
    $records | Select-Object Name,Existed,OldHash,NewHash,Change |
      ConvertTo-Json -Depth 3 |
      Out-File -LiteralPath (Join-Path $backup 'CODE_MANIFEST.json') -Encoding UTF8
    foreach($r in $changed) {
        $staged=$r.Destination+'.r94-stage-'+[guid]::NewGuid().ToString('N')
        $targetDir=Split-Path $staged -Parent
        New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        Copy-Item -LiteralPath $r.Source -Destination $staged -ErrorAction Stop
        if((Get-FileHash $staged -Algorithm SHA256).Hash -ne $r.NewHash) {
            throw ('PI_R94_STAGE_SHA_MISMATCH='+$r.Name)
        }
        Add-Member -InputObject $r -NotePropertyName Staged -NotePropertyValue $staged -Force
        $stages.Add($staged)
    }
    $swaps=0
    $started=$true
    foreach($r in $changed) {
        if($r.Existed) {
            if((Get-FileHash $r.Destination -Algorithm SHA256).Hash -ne $r.OldHash) {
                throw ('PI_R94_LIVE_SOURCE_DRIFT_DURING_APPLY='+$r.Name)
            }
            $sourcePath=[string]$r.Staged
            $destinationPath=[string]$r.Destination
            $atomicBefore=[string](Join-Path $backup ('atomic-before-'+$swaps))
            [IO.File]::Replace($sourcePath,$destinationPath,$atomicBefore)
        } else {
            if(Test-Path -LiteralPath $r.Destination){throw ('PI_R94_NEW_SOURCE_COLLISION='+$r.Name)}
            Move-Item -LiteralPath $r.Staged -Destination $r.Destination -ErrorAction Stop
        }
        $swaps++
        if((Get-FileHash $r.Destination -Algorithm SHA256).Hash -ne $r.NewHash) {
            throw ('PI_R94_APPLIED_SOURCE_SHA_MISMATCH='+$r.Name)
        }
        if($CiFailAfterFirstSwap -and $swaps -eq 1) {
            throw 'PI_R94_CI_INJECTED_FIRST_SWAP_FAILURE'
        }
    }
    # Hash all protected owned local files. Never open their contents.
    foreach($relative in $guarded) {
        if((Get-FileHash (Join-Path $root $relative) -Algorithm SHA256).Hash -ne $sentinelHashes[$relative]) {
            throw ('PI_R94_PROTECTED_SENTINEL_HASH_CHANGED='+$relative)
        }
    }
    if(-not $ciMode) {
        $after=@(Get-NetTCPConnection -LocalPort 8820 -State Listen -ErrorAction SilentlyContinue)
        if($after.Count -ne 1 -or [int]$after[0].OwningProcess -ne $ownedPid) {
            throw 'PI_R94_ORIGINAL_SERVER_PID_CHANGED'
        }
        # A new R88/R89 API route must return JSON 401 for an anonymous request
        # (404 means stale routes; 200 means broken auth; 419 means POST/CSRF).
        foreach($path in @('api/qa-superior-r88/planning-crosswalk',
            'api/qa-superior-r89/semantic-preview')) {
            $status=0
            try {
                $resp=Invoke-WebRequest -Uri ('http://127.0.0.1:8820/'+$path) -UseBasicParsing -Headers @{Accept='application/json'} -TimeoutSec 10 -ErrorAction Stop
                $status=[int]$resp.StatusCode
            } catch [System.Net.WebException] {
                if($null -eq $_.Exception.Response){throw}
                $status=[int]$_.Exception.Response.StatusCode
            }
            if($status -ne 401){throw ('PI_R94_HTTP_NEW_ROUTE_NOT_AUTH_GUARDED='+$path+':'+$status)}
        }
        $hasher=[Security.Cryptography.SHA256]::Create()
        try {
            foreach($rel in @('public\development-case-r85.html',
                'public\regulatory-planning-r88.js','public\pi-auth-shell.js')) {
                $publicRelative=$rel.Substring('public\'.Length).Replace([char]92,[char]47)
                $url='http://127.0.0.1:8820/'+$publicRelative+'?r94='+[guid]::NewGuid().ToString('N')
                $http=Invoke-WebRequest -Uri $url -UseBasicParsing -Headers @{'Cache-Control'='no-cache'} -TimeoutSec 10
                if($http.StatusCode -ne 200){throw ('PI_R94_HTTP_ASSET_NOT_200='+$rel)}
                $hash=[BitConverter]::ToString($hasher.ComputeHash($http.RawContentStream.ToArray())).Replace('-','')
                $rec=@($records|Where-Object {$_.Name -eq $rel})
                if($rec.Count -ne 1 -or $hash -ne $rec[0].NewHash) {
                    throw ('PI_R94_HTTP_ASSET_HASH_NOT_NEW='+$rel)
                }
            }
        } finally {$hasher.Dispose()}
        Write-Output 'PI_R94_ORIGINAL_HTTP_PROTECTED_ROUTES_AND_ASSETS=VERIFIED'
        Write-Output ('PI_R94_ORIGINAL_PHP_PID_PRESERVED='+$ownedPid)
    } else {
        Write-Output 'PI_R94_HTTP_VERIFICATION=SKIPPED_IN_DISPOSABLE_CI_FIXTURE'
    }
    Write-Output 'PI_R94_PROTECTED_DATA_ASSET_SHA=UNCHANGED'
    Write-Output ('PI_R94_CODE_BACKUP='+$backup)
    Write-Output 'PI_R94_CODE_CUTOVER=APPLIED_VERIFIED'
} catch {
    $failure=$_.Exception.Message
    if($started) {
        foreach($r in $records) {
            if($r.Existed) {
                $backupFile=Join-Path (Join-Path $backup 'before') $r.Name
                Copy-Item -LiteralPath $backupFile -Destination $r.Destination -Force -ErrorAction Stop
                if((Get-FileHash $r.Destination -Algorithm SHA256).Hash -ne $r.OldHash) {
                    throw ('PI_R94_ROLLBACK_ORIGINAL_SHA_FAILED='+$r.Name)
                }
            } elseif(Test-Path -LiteralPath $r.Destination -PathType Leaf) {
                # Only a known new file from the 17-item allowlist can be removed.
                Remove-Item -LiteralPath $r.Destination -Force -ErrorAction Stop
            }
        }
        Write-Output 'PI_R94_CODE_ROLLBACK_ALL_TARGETS=VERIFIED'
    }
    throw ('PI_R94_APPLY_ABORTED='+$failure)
} finally {
    foreach($p in $stages) {
        if(Test-Path -LiteralPath $p -PathType Leaf) {
            Remove-Item -LiteralPath $p -Force -ErrorAction SilentlyContinue
        }
    }
}

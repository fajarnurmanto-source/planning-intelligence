#requires -Version 5.1
<#
Guarded in-place rollout of ONLY original PI R74 login.html + login.js.
Default Audit does not write anything. Apply is explicit. No DB, .env, APP_KEY,
user, server process, shortcut, launcher, login CSS or QA port changes.
Existing baseline pinned by actual Git blob SHA1 of pre-fix R69E QA assets.
#>
[CmdletBinding()]
param([switch]$Apply)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$root='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
$launcher='D:\Research\PlanningIntelligence\PI_UNIFIED_LAUNCHER_R74_20261111'
$expected=@{
    'login.html'='70cac498ee5033d28a1f12898d11975a7379077d'
    'login.js'='bd687917ef6274e5099089040535e88b4b4757cb'
}
$git=Get-Command git.exe -ErrorAction Stop
if (-not (Test-Path -LiteralPath (Join-Path $root 'artisan') -PathType Leaf)) { throw 'PI_R74_LOGIN_MAIN_LARAVEL_MISSING' }
if (-not (Test-Path -LiteralPath (Join-Path $launcher 'BUKA-PI-HIDDEN.vbs') -PathType Leaf)) { throw 'PI_R74_LOGIN_EXISTING_SHORTCUT_CHAIN_MISSING' }
$files=@{}
foreach($name in @('login.html','login.js')){
    $live=Join-Path (Join-Path $root 'public') $name
    $candidate=Join-Path (Join-Path (Join-Path $PSScriptRoot '..') 'laravel\public') $name
    if (-not (Test-Path -LiteralPath $live -PathType Leaf) -or -not (Test-Path -LiteralPath $candidate -PathType Leaf)) {
        throw ('PI_R74_LOGIN_FILE_MISSING=' + $name)
    }
    # Git's raw blob hash pins the exact original source, independent of filename.
    $blob=(& $git.Source hash-object --no-filters $live)
    if ($LASTEXITCODE -ne 0 -or [string]$blob -ne $expected[$name]) {
        throw ('PI_R74_LOGIN_ORIGINAL_SOURCE_DRIFT=' + $name + '_STOP')
    }
    $files[$name]=[pscustomobject]@{
        Original=$live; Candidate=$candidate
        SourceSha256=(Get-FileHash -LiteralPath $live -Algorithm SHA256).Hash
        CandidateSha256=(Get-FileHash -LiteralPath $candidate -Algorithm SHA256).Hash
    }
    if ($files[$name].SourceSha256 -eq $files[$name].CandidateSha256) {
        throw ('PI_R74_LOGIN_CANDIDATE_IDENTICAL_TO_ORIGINAL=' + $name)
    }
    Write-Output ('PI_R74_LOGIN_BASELINE_GIT_BLOB=VERIFIED_' + $name)
}
$html=Get-Content -LiteralPath $files['login.html'].Candidate -Raw
$js=Get-Content -LiteralPath $files['login.js'].Candidate -Raw
if ($html -notmatch 'id="password"[^>]*autocomplete="current-password"' -or
    $html -notmatch 'id="toggle-password"' -or
    $js -notmatch "fetch\('/api/auth/session'" -or
    $js -notmatch "el\('password'\)\.value = ''") {
    throw 'PI_R74_LOGIN_CANDIDATE_MISSING_SAFETY_GUARDS'
}
$node=Get-Command node.exe -ErrorAction SilentlyContinue
if ($null -ne $node) {
    & $node.Source --check $files['login.js'].Candidate
    if ($LASTEXITCODE -ne 0) { throw 'PI_R74_LOGIN_JS_PARSE_FAILED' }
}
Write-Output 'PI_R74_LOGIN_FRONTEND_SOURCE=READY'
Write-Output 'PI_R74_AUTH_ACCOUNT_DB_APPKEY_SHORTCUT_LAUNCHER=PROTECTED'
if (-not $Apply) {
    Write-Output 'PI_R74_LOGIN_FRONTEND_ROLLOUT=READ_ONLY_PREFLIGHT_PASS'
    Write-Output 'PI_R74_LOGIN_FRONTEND_APPLY=NOT_REQUESTED'
    return
}
# Only after exact preflight: copy immutable originals and a manifest before
# modifying either public asset. No need to stop the server to update static files.
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
$backup=Join-Path $launcher ('backups\R74_LOGIN_UI_BEFORE_'+$stamp)
if (Test-Path -LiteralPath $backup) {throw 'PI_R74_LOGIN_BACKUP_COLLISION'}
New-Item -ItemType Directory -Path $backup -Force -ErrorAction Stop | Out-Null
$manifest=@()
foreach ($name in @('login.html','login.js')) {
    $copy=Join-Path $backup $name
    Copy-Item -LiteralPath $files[$name].Original -Destination $copy -ErrorAction Stop
    $digest=(Get-FileHash -LiteralPath $copy -Algorithm SHA256).Hash
    if ($digest -ne $files[$name].SourceSha256) {
        throw ('PI_R74_LOGIN_BACKUP_INTEGRITY_FAILED=' + $name)
    }
    $manifest += [pscustomobject]@{File=$name;SHA256=$digest;GitBlob=$expected[$name]}
}
$manifest | ConvertTo-Json | Out-File -LiteralPath (Join-Path $backup 'ORIGINAL_SHA256.json') -Encoding UTF8
$stages=@{}
try {
    foreach($name in @('login.html','login.js')){
        $dest=$files[$name].Original
        $temp=$dest+'.r74-candidate-'+[guid]::NewGuid().ToString('N')
        Copy-Item -LiteralPath $files[$name].Candidate -Destination $temp -ErrorAction Stop
        if ((Get-FileHash -LiteralPath $temp -Algorithm SHA256).Hash -ne $files[$name].CandidateSha256) {
            throw ('PI_R74_LOGIN_STAGED_HASH_MISMATCH=' + $name)
        }
        $stages[$name]=$temp
    }
    foreach($name in @('login.html','login.js')){
        [IO.File]::Replace([string]$stages[$name],[string]$files[$name].Original,$null)
        $stages.Remove($name)
        if ((Get-FileHash -LiteralPath $files[$name].Original -Algorithm SHA256).Hash -ne $files[$name].CandidateSha256) {
            throw ('PI_R74_LOGIN_DEPLOYED_HASH_MISMATCH=' + $name)
        }
    }
    Write-Output 'PI_R74_LOGIN_FRONTEND_ROLLOUT=APPLIED'
    Write-Output ('PI_R74_LOGIN_FRONTEND_BACKUP='+$backup)
    Write-Output 'PI_R74_LOGIN_NEXT=EDGE_INPRIVATE_HARD_RELOAD_AND_MANUAL_LOGIN'
} catch {
    foreach($name in @('login.html','login.js')){
        $old=Join-Path $backup $name
        if (Test-Path -LiteralPath $old -PathType Leaf) {
            Copy-Item -LiteralPath $old -Destination $files[$name].Original -Force -ErrorAction Stop
        }
    }
    throw
} finally {
    foreach($item in @($stages.Values)){
        if (Test-Path -LiteralPath $item -PathType Leaf) { Remove-Item -LiteralPath $item -ErrorAction SilentlyContinue }
    }
}

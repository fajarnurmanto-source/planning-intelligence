#requires -Version 5.1
<#
PI R104 local owner-assisted BPK PDF intake.
Automatic exact-filename discovery in <drive>:\Download and user Downloads.
Read-only by default. Explicit -Archive writes immutable byte candidates into
a separate LOCALAPPDATA quarantine (NEVER into original PI Laravel).

This verifies local PDF header/tail and SHA256 only; a signed and correct
original legal PDF identity and full annexes still need reviewer inspection.
#>
[CmdletBinding()]
param(
    [string]$DownloadDirectory = '',
    [string]$QuarantineDirectory = '',
    [switch]$Archive,
    [Parameter(Mandatory=$true)][ValidatePattern('^[a-fA-F0-9]{40}$')]
    [string]$ExpectedCommit
)
Set-StrictMode -Version 2
$ErrorActionPreference='Stop'
$repo=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$expected=$ExpectedCommit.ToLowerInvariant()
$git=(Get-Command git.exe -ErrorAction Stop).Source
$head=([string](& $git -C $repo rev-parse HEAD)).Trim()
if($LASTEXITCODE -ne 0 -or $head -cne $expected){
    throw 'PI_R104_PINNED_QA_HEAD_MISMATCH'
}
$dirty=@(& $git -C $repo status --porcelain)
if($LASTEXITCODE -ne 0 -or $dirty.Count -gt 0) {
    throw 'PI_R104_QA_WORKTREE_NOT_CLEAN'
}
$cli=Join-Path $repo 'scripts\pi-r102-stage-primary-pdf.php'
$service=Join-Path $repo 'laravel\app\Services\Pi\Development\PrimaryPdfCandidateIntake.php'
$manifest=Join-Path $repo 'laravel\resources\pi\official-bpk-pdf-mirrors-r103.json'
if(-not (Test-Path -LiteralPath $cli -PathType Leaf) -or
   -not (Test-Path -LiteralPath $service -PathType Leaf) -or
   -not (Test-Path -LiteralPath $manifest -PathType Leaf)) {
    throw 'PI_R104_R103_OFFLINE_INTAKE_NOT_PINNED'
}
$caseList=@(
    [pscustomobject]@{
        Id='YOG-APBD-DETAIL-2026'
        Name='Perwal Jogja Nomor 74 Tahun 2025.pdf'
        Url='https://peraturan.bpk.go.id/Download/409706/Perwal%20Jogja%20Nomor%2074%20Tahun%202025.pdf'
    },
    [pscustomobject]@{
        Id='YOG-APBD-DETAIL-CHANGE3-2026'
        Name='perwalkot No.12 thn 2026.pdf'
        Url='https://peraturan.bpk.go.id/Download/414066/perwalkot%20No.12%20thn%202026.pdf'
    }
)
$dirs=New-Object 'System.Collections.Generic.List[string]'
if($DownloadDirectory -ne ''){
    $dirs.Add($DownloadDirectory)
} else {
    foreach($drive in @(Get-PSDrive -PSProvider FileSystem)) {
        if($drive.Root -match '^[A-Za-z]:\\$') {
            $dirs.Add((Join-Path $drive.Root 'Download'))
            $dirs.Add((Join-Path $drive.Root 'Downloads'))
        }
    }
    if(-not [string]::IsNullOrWhiteSpace($env:USERPROFILE)){
        $dirs.Add((Join-Path $env:USERPROFILE 'Download'))
        $dirs.Add((Join-Path $env:USERPROFILE 'Downloads'))
    }
}
$found=New-Object 'System.Collections.Generic.List[object]'
foreach($dir in @($dirs|Select-Object -Unique)) {
    if(-not (Test-Path -LiteralPath $dir -PathType Container)){continue}
    $physical=(Resolve-Path -LiteralPath $dir).Path
    $attrs=(Get-Item -LiteralPath $physical -Force).Attributes
    if(($attrs -band [IO.FileAttributes]::ReparsePoint) -ne 0){continue}
    $both=$true
    foreach($case in $caseList){
        $file=Join-Path $physical $case.Name
        if(-not (Test-Path -LiteralPath $file -PathType Leaf)){
            $both=$false
            break
        }
        $info=Get-Item -LiteralPath $file -Force
        if(($info.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){
            throw 'PI_R104_PDF_SYMLINK_REJECTED'
        }
    }
    if($both){$found.Add($physical)}
}
$foundPaths=@($found|Select-Object -Unique)
if($foundPaths.Count -ne 1){
    Write-Output ('PI_R104_COMPLETE_SOURCE_FOLDER_MATCHES='+$foundPaths.Count)
    foreach($item in $foundPaths){Write-Output ('PI_R104_MATCH='+$item)}
    throw 'PI_R104_DOWNLOAD_FOLDER_NOT_UNAMBIGUOUS_HOLD'
}
$folder=[string]$foundPaths[0]
Write-Output ('PI_R104_BPK_DOWNLOAD_FOLDER='+$folder)
$results=New-Object 'System.Collections.Generic.List[object]'
foreach($case in $caseList){
    $file=Join-Path $folder $case.Name
    $info=Get-Item -LiteralPath $file -Force
    if($info.Length -lt 128 -or $info.Length -gt (80*1024*1024)){
        throw ('PI_R104_PDF_SIZE_INVALID='+$case.Id)
    }
    $handle=[IO.File]::Open($file,[IO.FileMode]::Open,[IO.FileAccess]::Read,[IO.FileShare]::Read)
    try {
        $header=New-Object byte[] 8
        if($handle.Read($header,0,$header.Length) -ne 8){
            throw ('PI_R104_PDF_HEADER_UNREADABLE='+$case.Id)
        }
        $magic=[Text.Encoding]::ASCII.GetString($header)
        if(-not ($magic.StartsWith('%PDF-1.') -or $magic.StartsWith('%PDF-2.'))) {
            throw ('PI_R104_NON_PDF_OR_HTML_REJECTED='+$case.Id)
        }
        $tailSize=[Math]::Min([int]$handle.Length,4096)
        $null=$handle.Seek(-$tailSize,[IO.SeekOrigin]::End)
        $tail=New-Object byte[] $tailSize
        $tailRead=$handle.Read($tail,0,$tailSize)
        if(-not [Text.Encoding]::ASCII.GetString($tail,0,$tailRead).Contains('%%EOF')){
            throw ('PI_R104_PDF_EOF_MISSING='+$case.Id)
        }
    } finally {$handle.Dispose()}
    $hash=(Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash
    $results.Add([pscustomobject]@{Id=$case.Id;File=$file;Url=$case.Url;Bytes=$info.Length;Sha=$hash})
    Write-Output ('PI_R104_SOURCE='+$case.Id)
    Write-Output ('PI_R104_SOURCE_BYTES='+$info.Length)
    Write-Output ('PI_R104_SOURCE_SHA256='+$hash)
}
Write-Output 'PI_R104_TWO_LOCAL_PDF_HEADER_EOF_SHA256=PASS'
Write-Output 'PI_R104_ORIGINAL_FILES=READ_ONLY'
Write-Output 'PI_R104_LEGAL_IDENTITY_AND_BPK_ORIGIN_BYTES=REVIEW_REQUIRED'
Write-Output 'PI_R104_FISCAL_VALUE_ADMISSION=ZERO'

if(-not $Archive){
    Write-Output 'PI_R104_MODE=READ_ONLY_NO_ARCHIVE'
    return
}
$php=(Get-Command php.exe -ErrorAction Stop).Source
if([string]::IsNullOrWhiteSpace($QuarantineDirectory)){
    if([string]::IsNullOrWhiteSpace($env:LOCALAPPDATA)){
        throw 'PI_R104_LOCALAPPDATA_UNAVAILABLE'
    }
    $QuarantineDirectory=Join-Path $env:LOCALAPPDATA 'PlanningIntelligence\R104_BPK_PDF_QUARANTINE'
}
$normalizedPath={
    param([string]$x)
    [IO.Path]::GetFullPath($x).TrimEnd([char]92,[char]47).Replace('\','/').ToLowerInvariant()
}
$archivePath=& $normalizedPath $QuarantineDirectory
$qaPath=& $normalizedPath $repo
$downloadPath=& $normalizedPath $folder
$originalPath='d:/research/planningintelligence/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel'
foreach($forbidden in @($qaPath,$originalPath,$downloadPath)){
    if($archivePath -eq $forbidden -or $archivePath.StartsWith($forbidden+'/')){
        throw 'PI_R104_ARCHIVE_INSIDE_SOURCE_OR_LARAVEL_FORBIDDEN'
    }
}
if($archivePath -match '(^|/)public(/|$)' -or
   $archivePath -match '(^|/)(onedrive)(/|$)') {
    throw 'PI_R104_ARCHIVE_WEB_OR_SYNC_LOCATION_FORBIDDEN'
}
if(-not (Test-Path -LiteralPath $QuarantineDirectory)){
    New-Item -ItemType Directory -Path $QuarantineDirectory -Force -ErrorAction Stop | Out-Null
}
$quarantine=(Resolve-Path -LiteralPath $QuarantineDirectory).Path
$attrs=(Get-Item -LiteralPath $quarantine -Force).Attributes
if(($attrs -band [IO.FileAttributes]::ReparsePoint) -ne 0){
    throw 'PI_R104_QUARANTINE_SYMLINK_FORBIDDEN'
}
$at=(Get-Date).ToString('yyyy-MM-ddTHH:mm:sszzz')
$staged=0
foreach($result in $results){
    if((Get-FileHash -LiteralPath $result.File -Algorithm SHA256).Hash -cne $result.Sha){
        throw ('PI_R104_LOCAL_SOURCE_CHANGED_AFTER_PREFLIGHT='+$result.Id)
    }
    $argv=@(
        $cli,('--id='+$result.Id),('--file='+$result.File),
        ('--url='+$result.Url),('--captured-at='+$at),
        ('--staging-root='+$quarantine),'--commit'
    )
    $output=@(& $php @argv)
    if($LASTEXITCODE -ne 0 -or
       $output -notcontains 'PI_R102_LOCAL_PDF_CANDIDATE_STAGED=PASS'){
        throw ('PI_R104_R102_QUARANTINE_HOLD='+$result.Id)
    }
    $output|Where-Object {$_ -match '^PI_R102_(QUARANTINED_PDF|IMMUTABLE_RECEIPT)='}|
        ForEach-Object {Write-Output $_}
    $staged++
    Write-Output ('PI_R104_CANDIDATE_STAGED='+$result.Id)
}
if($staged -ne 2){throw 'PI_R104_BPK_CANDIDATES_INCOMPLETE'}
Write-Output 'PI_R104_BOTH_BPK_PDF_CANDIDATES_SHA_ARCHIVED=PASS'
Write-Output 'PI_R104_ORIGINAL_PI_8820_DATABASE_LAUNCHER=UNTOUCHED'
Write-Output 'PI_R104_REVIEWER_AND_NUMERIC_ADMISSION=NOT_GRANTED'

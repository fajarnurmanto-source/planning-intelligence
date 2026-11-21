#requires -Version 5.1
<#
READ-ONLY discovery of the ALREADY EXISTING Planning Intelligence desktop
shortcut. Do not install, change, remove, start or stop anything.
Target/working directory are reported; shortcut Arguments are intentionally
not returned (they may contain secrets). Loopback URL origins are sanitized.
#>
[CmdletBinding()]
param()
Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'

$folders = New-Object 'System.Collections.Generic.List[string]'
$desktop = [Environment]::GetFolderPath('DesktopDirectory')
$startMenu = [Environment]::GetFolderPath('Programs')
foreach ($dir in @($desktop,
    (Join-Path $env:USERPROFILE 'Desktop'),
    (Join-Path $env:PUBLIC 'Desktop'),
    (Join-Path $env:USERPROFILE 'OneDrive\Desktop'),
    $startMenu,
    (Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs'))) {
    if ($dir -and (Test-Path -LiteralPath $dir -PathType Container) -and
        -not $folders.Contains($dir)) { $folders.Add($dir) }
}

function Sanitized-LoopbackAddress([string] $value) {
    $match = [regex]::Match($value, '(?i)https?://(localhost|127\.0\.0\.1)(:\d{1,5})?')
    if ($match.Success) { return $match.Value }
    return '(not disclosed or not localhost)'
}

$shell = New-Object -ComObject WScript.Shell
$results = New-Object 'System.Collections.Generic.List[object]'
foreach ($folder in $folders) {
    foreach ($file in (Get-ChildItem -LiteralPath $folder -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '(?i)planning.?intelligence|^PI(\.|\s)' -and
            $_.Extension -in @('.lnk','.url','.appref-ms') })) {
        $kind = $file.Extension
        $target = ''
        $working = ''
        $origin = '(not disclosed or not localhost)'
        $argumentHint = 'NOT_DISCLOSED'
        $targetExists = $false
        try {
            if ($kind -eq '.lnk') {
                $link = $shell.CreateShortcut($file.FullName)
                $target = [string]$link.TargetPath
                $working = [string]$link.WorkingDirectory
                $origin = Sanitized-LoopbackAddress ([string]$link.Arguments)
                $argumentHint = if ($link.Arguments -match '(?i)(-File|-ExecutionPolicy|powershell)') {
                    'POWERSHELL'
                } elseif ($link.Arguments -match '(?i)--app=') {
                    'BROWSER_APP_MODE'
                } elseif ($link.Arguments -match '(?i)https?://') {
                    'URL_ARGUMENT'
                } elseif ($link.Arguments) {
                    'OTHER_ARGUMENTS_REDACTED'
                } else { 'NONE' }
                $targetExists = Test-Path -LiteralPath $target -PathType Leaf
            } elseif ($kind -eq '.url') {
                $line = Get-Content -LiteralPath $file.FullName |
                    Where-Object { $_ -match '^URL=' } | Select-Object -First 1
                $origin = Sanitized-LoopbackAddress ([string]$line)
                $target = 'INTERNET_SHORTCUT'
                $targetExists = $true
            } else {
                $target = 'APPREF_MS_NOT_EXECUTED'
                $targetExists = $true
            }
        } catch {
            $target = 'UNREADABLE_SHORTCUT'
        }
        $results.Add([pscustomobject]@{
            Shortcut      = $file.FullName
            Kind          = $kind
            Target        = $target
            TargetExists  = $targetExists
            WorkingDir    = $working
            ArgsKind      = $argumentHint
            LocalOrigin   = $origin
            ShortcutSHA256 = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash
        })
    }
}
if ($results.Count -eq 0) {
    Write-Host 'PI_EXISTING_SHORTCUT=NOT_FOUND_IN_STANDARD_DIRECTORIES'
    Write-Host 'PI_NEXT=OPEN_SHORTCUT_PROPERTIES_READ_ONLY'
} else {
    Write-Host ('PI_EXISTING_SHORTCUT_COUNT=' + $results.Count)
    $results | Format-List
}
# PowerShell 5.1 does not pipe a foreach block; collect into variable.
$status = foreach ($port in @(8080,8101,8820,8822,8823)) {
    $listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue |
        Select-Object -First 1
    [pscustomobject]@{
        Port = $port
        Listening = [bool]$listener
        PID = if ($listener) { $listener.OwningProcess } else { '-' }
    }
}
Write-Host 'PI_PORT_INVENTORY=READ_ONLY'
$status | Format-Table -AutoSize
Write-Host 'PI_DISCOVERY_MUTATION=NONE'

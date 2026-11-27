#requires -Version 5.1
# Shared Tinker producer/consumer contract; no raw console output or secrets.
function Assert-PiAprTinkerResult([int]$ExitCode,[object[]]$Lines){
 $marker='PI_R108_W3C_TINKER_CASE_SIX_RELATIONS_GATES=PASS'
 $passLines=@($Lines|Where-Object {([string]$_).Trim() -ceq $marker})
  $specific=@($Lines|ForEach-Object {[regex]::Matches([string]$_,'PI_R108_W3C_TINKER_HOLD_(?:CASE|EDGE_E06|EDGE_E08|EDGE_E09|EDGE_E10|EDGE_E11|EDGE_E12|INDICATOR|OUT_OF_SCOPE|GATE_legal_harmonization|GATE_numeric_comparison|GATE_review_decision|GATE_production_admission)(?![A-Za-z0-9_])')}|ForEach-Object {$_.Value}|Select-Object -Unique)
 if($specific.Count -eq 1){throw ('PI_APR_TINKER_DOMAIN_HOLD='+$specific[0])}
 if($specific.Count -gt 1){throw 'PI_APR_TINKER_MULTIPLE_DOMAIN_HOLD'}
 if($ExitCode -ne 0){throw 'PI_APR_TINKER_PROCESS_EXIT_HOLD'}
 if($passLines.Count -ne 1){throw 'PI_APR_TINKER_SUCCESS_MARKER_HOLD'}
 Write-Output 'PI_APR_CUTOVER_TINKER_CASE_SIX_RELATIONS_GATES=PASS'
}
function Invoke-PiAprTinkerProbe([string]$Php,[string]$AppRoot,[string]$Probe){
 if(-not (Test-Path -LiteralPath $Php -PathType Leaf) -or -not (Test-Path -LiteralPath $Probe -PathType Leaf)){throw 'PI_APR_TINKER_PREREQUISITE_HOLD'}
 $encoded=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Probe))
 $code="require base64_decode('$encoded');"
 Push-Location -LiteralPath $AppRoot
 try{
  $ErrorActionPreference='Continue'
  $lines=@(& $Php artisan tinker "--execute=$code" 2>&1)
  $rc=$LASTEXITCODE
 }finally{Pop-Location}
 Assert-PiAprTinkerResult -ExitCode $rc -Lines $lines
}

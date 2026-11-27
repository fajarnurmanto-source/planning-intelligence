#requires -Version 5.1
# APR bounded Laravel work-directory resolver. Never installs packages, edits ACLs,
# .env, databases or PHP configuration. Repair is restricted to isolated roots.
function Get-PiAprLaravelDirectoryNames {
 @('bootstrap\cache','storage\framework\cache','storage\framework\cache\data','storage\framework\sessions','storage\framework\views','storage\logs')
}
function Assert-PiAprLaravelReceipt($Receipt){
 $names=@(Get-PiAprLaravelDirectoryNames)
 if($null -eq $Receipt -or $Receipt.Schema -cne 'PI_APR_LARAVEL_PREREQUISITES_V1' -or
    $Receipt.Status -cne 'PASS' -or @($Receipt.Directories).Count -ne $names.Count){throw 'PI_APR_RUNTIME_RECEIPT_HOLD'}
 foreach($name in $names){
  $rows=@($Receipt.Directories|Where-Object {$_.Name -ceq $name})
  if($rows.Count -ne 1 -or $rows[0].State -cne 'PRESENT' -or
    $rows[0].Writable -cne 'PROBED_PASS'){throw 'PI_APR_RUNTIME_RECEIPT_HOLD'}
 }
}
function Invoke-PiAprLaravelPrerequisites {
 [CmdletBinding()]
 param([Parameter(Mandatory=$true)][string]$AppRoot,[string]$IsolatedRoot,[switch]$Repair)
 $root=[IO.Path]::GetFullPath($AppRoot).TrimEnd('\','/')
 $owner='D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel'
 $isolated= -not [string]::IsNullOrWhiteSpace($IsolatedRoot)
 if($Repair -and -not $isolated){throw 'PI_APR_RUNTIME_REPAIR_REQUIRES_ISOLATION'}
 if($isolated){
  $scope=[IO.Path]::GetFullPath($IsolatedRoot).TrimEnd('\','/')
  if($root -eq $owner -or -not $root.StartsWith($scope+'\',[StringComparison]::OrdinalIgnoreCase)){
   throw 'PI_APR_RUNTIME_ISOLATION_HOLD'
  }
 }
 if(-not (Test-Path -LiteralPath $root -PathType Container)){throw 'PI_APR_RUNTIME_ROOT_MISSING'}
 $rows=New-Object 'System.Collections.Generic.List[object]'
 foreach($name in @(Get-PiAprLaravelDirectoryNames)){
  $target=Join-Path $root $name
  $parent=$target
  while($parent){
   if(Test-Path -LiteralPath $parent){
    $item=Get-Item -LiteralPath $parent -Force
    if(($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'PI_APR_RUNTIME_REPARSE_HOLD'}
   }
   $parent=Split-Path $parent -Parent
  }
  $created=$false
  if(-not (Test-Path -LiteralPath $target -PathType Container)){
   if(Test-Path -LiteralPath $target){throw ('PI_APR_RUNTIME_DIRECTORY_COLLISION='+$name)}
   if(-not $Repair){throw ('PI_APR_RUNTIME_DIRECTORY_MISSING='+$name)}
   try{New-Item -ItemType Directory -Path $target -Force -ErrorAction Stop|Out-Null;$created=$true}
   catch{throw ('PI_APR_RUNTIME_DIRECTORY_CREATE_HOLD='+$name)}
  }
  $writable='NOT_TESTED_READ_ONLY'
  if($isolated){
   $probe=Join-Path $target ('.apr-write-probe-'+[guid]::NewGuid().ToString('N'))
   $stream=$null
   try{
    $stream=[IO.File]::Open($probe,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    $stream.WriteByte(0);$stream.Dispose();$stream=$null
    $writable='PROBED_PASS'
   }catch{throw ('PI_APR_RUNTIME_DIRECTORY_NOT_WRITABLE='+$name)}
   finally{if($null -ne $stream){$stream.Dispose()};if(Test-Path -LiteralPath $probe -PathType Leaf){[IO.File]::Delete($probe)}}
  }
  $rows.Add([pscustomobject]@{Name=$name;State='PRESENT';Writable=$writable;Created=$created})
 }
 $receipt=[pscustomobject]@{Schema='PI_APR_LARAVEL_PREREQUISITES_V1';Status=$(if($isolated){'PASS'}else{'READ_ONLY_PRESENT'});Directories=@($rows.ToArray());ProtectedMutation='NONE'}
 if($isolated){Assert-PiAprLaravelReceipt $receipt}
 $receipt
}

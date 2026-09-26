$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
Set-Location $root
$zip=Read-Host "Masukkan path lengkap file backup .zip"
if(-not(Test-Path $zip)){throw "File tidak ditemukan."}
$confirm=Read-Host "RESTORE akan mengganti database/file kerja saat ini. Ketik RESTORE untuk lanjut"
if($confirm -ne 'RESTORE'){Write-Host 'Dibatalkan.';exit}
$envMap=@{}; Get-Content .env | ForEach-Object { if($_ -match '^([^#=]+)=(.*)$'){$envMap[$matches[1]]=$matches[2]} }
$temp=Join-Path $root 'backups\restore_temp'
Remove-Item $temp -Recurse -Force -ErrorAction SilentlyContinue
Expand-Archive -Path $zip -DestinationPath $temp -Force
# Safety copy of current raw files before destructive restore
$safety=Join-Path $root ("backups\pre_restore_files_"+(Get-Date -Format 'yyyyMMdd_HHmmss'))
New-Item -ItemType Directory -Force -Path $safety | Out-Null
Copy-Item data\files $safety\files -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item data\regulations $safety\regulations -Recurse -Force -ErrorAction SilentlyContinue
Write-Host 'Mengosongkan schema publik...'
"DROP SCHEMA public CASCADE; CREATE SCHEMA public;" | docker compose exec -T db psql -U $envMap['POSTGRES_USER'] -d $envMap['POSTGRES_DB'] -v ON_ERROR_STOP=1
Write-Host 'Memulihkan database dump...'
Get-Content (Join-Path $temp 'database.sql') | docker compose exec -T db psql -U $envMap['POSTGRES_USER'] -d $envMap['POSTGRES_DB'] -v ON_ERROR_STOP=1
Remove-Item data\files -Recurse -Force -ErrorAction SilentlyContinue; New-Item -ItemType Directory -Force data\files|Out-Null
Remove-Item data\regulations -Recurse -Force -ErrorAction SilentlyContinue; New-Item -ItemType Directory -Force data\regulations|Out-Null
if(Test-Path "$temp\files"){Copy-Item "$temp\files\*" data\files -Recurse -Force}
if(Test-Path "$temp\regulations"){Copy-Item "$temp\regulations\*" data\regulations -Recurse -Force}
Remove-Item $temp -Recurse -Force
Write-Host 'Restore selesai. Jalankan 01_START.bat bila perlu.' -ForegroundColor Green

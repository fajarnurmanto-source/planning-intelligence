$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
Set-Location $root
Write-Host "=== Planning Intelligence V0.0 Setup ===" -ForegroundColor Cyan
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { Write-Host "Docker belum ditemukan. Install Docker Desktop lalu jalankan ulang." -ForegroundColor Red; exit 1 }
if (-not (Test-Path .env)) { Copy-Item .env.example .env; Write-Host "File .env dibuat." }
$dirs=@('data\files','data\regulations','data\postgres','backups','exports','logs')
foreach($d in $dirs){New-Item -ItemType Directory -Force -Path $d | Out-Null}
Write-Host "Folder siap. Berikutnya jalankan 01_START.bat" -ForegroundColor Green

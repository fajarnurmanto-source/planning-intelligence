$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
Set-Location $root
if(-not(Test-Path .env)){throw "Jalankan 00_SETUP.bat dulu."}
$envMap=@{}; Get-Content .env | ForEach-Object { if($_ -match '^([^#=]+)=(.*)$'){$envMap[$matches[1]]=$matches[2]} }
$stamp=Get-Date -Format 'yyyyMMdd_HHmmss'
$stage=Join-Path $root "backups\stage_$stamp"
New-Item -ItemType Directory -Force -Path $stage | Out-Null
Write-Host "Membuat database dump..."
docker compose exec -T db pg_dump -U $envMap['POSTGRES_USER'] -d $envMap['POSTGRES_DB'] | Out-File -Encoding utf8 (Join-Path $stage 'database.sql')
Copy-Item data\files $stage\files -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item data\regulations $stage\regulations -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item prompts $stage\prompts -Recurse -Force
Copy-Item rules $stage\rules -Recurse -Force
Copy-Item docs $stage\docs -Recurse -Force
Copy-Item .env $stage\.env -Force
$manifest=@{created_at=(Get-Date).ToString('o');app_version='0.0.1';contains=@('database.sql','files','regulations','prompts','rules','docs','.env')} | ConvertTo-Json -Depth 4
$manifest | Out-File -Encoding utf8 (Join-Path $stage 'manifest.json')
$zip=Join-Path $root "backups\planning_backup_$stamp.zip"
Compress-Archive -Path "$stage\*" -DestinationPath $zip -Force
$hash=(Get-FileHash $zip -Algorithm SHA256).Hash
"$hash  $(Split-Path $zip -Leaf)" | Out-File -Encoding ascii "$zip.sha256.txt"
Remove-Item $stage -Recurse -Force
Write-Host "Backup selesai:" -ForegroundColor Green
Write-Host $zip
Write-Host "SHA256: $hash"

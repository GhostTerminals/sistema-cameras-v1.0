param(
    [string]$BackupDir = ".\backups",
    [string]$DbHost = $env:DB_HOST,
    [string]$DbName = $env:DB_NAME,
    [string]$DbUser = $env:DB_USER,
    [string]$DbPass = $env:DB_PASS,
    [int]$RetentionDays = 30,
    [switch]$Compress
)

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupPath = Join-Path -Path $BackupDir -ChildPath $DbName
$null = New-Item -ItemType Directory -Path $backupPath -Force

$filename = "$DbName-$timestamp.sql"
$filePath = Join-Path -Path $backupPath -ChildPath $filename

Write-Host "[Backup] Starting backup of $DbName..." -ForegroundColor Cyan

$env:MYSQL_PWD = $DbPass
mysqldump --host=$DbHost --user=$DbUser --single-transaction --routines --triggers --events --databases $DbName | Out-File -FilePath $filePath -Encoding utf8

if ($LASTEXITCODE -ne 0) {
    Write-Error "[Backup] FAILED: mysqldump exited with code $LASTEXITCODE"
    exit 1
}

Write-Host "[Backup] Saved: $filePath ($((Get-Item $filePath).Length / 1MB, 2) MB)" -ForegroundColor Green

if ($Compress) {
    $zipPath = "$filePath.gz"
    if (Get-Command gzip -ErrorAction SilentlyContinue) {
        gzip -f $filePath
        Write-Host "[Backup] Compressed: $zipPath" -ForegroundColor Green
    } else {
        Write-Warning "[Backup] gzip not found, skipping compression"
    }
}

$cutoff = (Get-Date).AddDays(-$RetentionDays)
Get-ChildItem -Path $backupPath -Filter "*.sql" | Where-Object { $_.LastWriteTime -lt $cutoff } | Remove-Item -Force
Get-ChildItem -Path $backupPath -Filter "*.sql.gz" | Where-Object { $_.LastWriteTime -lt $cutoff } | Remove-Item -Force

Write-Host "[Backup] Old backups (>${RetentionDays}d) cleaned up" -ForegroundColor Yellow
Write-Host "[Backup] Done!" -ForegroundColor Green

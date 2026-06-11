# One-time local setup: directories, MySQL config, and default dev admin account.
param(
    [string]$Username = "admin",
    [string]$Password = "local-dev-only",
    [switch]$UseDocker
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

foreach ($dir in @("cache", "data", "data\admin")) {
    $path = Join-Path $Root $dir
    if (-not (Test-Path $path)) {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
    }
}

$dbPath = Join-Path $Root "data\database.php"
if ($UseDocker -and -not (Test-Path $dbPath)) {
    @"
<?php
return [
    'host' => 'db',
    'port' => 3306,
    'database' => 'self_schedule',
    'username' => 'self_schedule',
    'password' => 'self_schedule',
    'charset' => 'utf8mb4',
];
"@ | Set-Content -Path $dbPath -Encoding UTF8
    Write-Host "Created data/database.php for Docker MySQL." -ForegroundColor Green
}

if ($UseDocker) {
    Set-Location $Root
    docker compose up -d db
    docker compose run --rm web php scripts/setup-database.php
    docker compose run --rm web php scripts/migrate-to-mysql.php
    docker compose run --rm web php scripts/reset-admin.php $Password $Username
} else {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    $php = if ($phpCmd) { $phpCmd.Source } else { $null }
    if (-not $php) {
        Write-Host "PHP not found. Run with -UseDocker or install PHP with pdo_mysql first." -ForegroundColor Yellow
        exit 1
    }
    if (Test-Path $dbPath) {
        & $php (Join-Path $Root "scripts\setup-database.php")
        & $php (Join-Path $Root "scripts\migrate-to-mysql.php")
    }
    & $php (Join-Path $Root "scripts\reset-admin.php") $Password $Username
}

Write-Host ""
Write-Host "Local admin credentials" -ForegroundColor Cyan
Write-Host "  Username: $Username"
Write-Host "  Password: $Password"
Write-Host ""
Write-Host "Start the server: .\serve.bat   (or: docker compose up)" -ForegroundColor Green

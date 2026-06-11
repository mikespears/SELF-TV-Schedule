# One-time local setup: directories + default dev admin account.
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

if ($UseDocker) {
    Set-Location $Root
    docker compose run --rm web php scripts/reset-admin.php $Password $Username
} else {
    $php = (Get-Command php -ErrorAction SilentlyContinue)?.Source
    if (-not $php) {
        Write-Host "PHP not found. Run with -UseDocker or install PHP first." -ForegroundColor Yellow
        exit 1
    }
    & $php (Join-Path $Root "scripts\reset-admin.php") $Password $Username
}

Write-Host ""
Write-Host "Local admin credentials" -ForegroundColor Cyan
Write-Host "  Username: $Username"
Write-Host "  Password: $Password"
Write-Host ""
Write-Host "Start the server: .\serve.bat   (or: docker compose up)" -ForegroundColor Green

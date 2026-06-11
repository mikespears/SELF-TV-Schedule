# Start a local PHP dev server for SELF Talk Schedule Display.
param(
    [int]$Port = 8080
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot

function Find-PhpExecutable {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    $candidates = @(
        "C:\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\wamp64\bin\php\php8.3.0\php.exe",
        "$env:LOCALAPPDATA\Programs\PHP\php.exe"
    )

    if (Test-Path "C:\laragon\bin\php") {
        $laragonPhp = Get-ChildItem "C:\laragon\bin\php" -Recurse -Filter "php.exe" -ErrorAction SilentlyContinue |
            Select-Object -First 1 -ExpandProperty FullName
        if ($laragonPhp) {
            $candidates = @($laragonPhp) + $candidates
        }
    }

    foreach ($path in $candidates) {
        if ($path -and (Test-Path $path)) {
            return $path
        }
    }

    return $null
}

function Ensure-LocalDirs {
    param([string]$ProjectRoot)

    foreach ($dir in @("cache", "data", "data\admin")) {
        $path = Join-Path $ProjectRoot $dir
        if (-not (Test-Path $path)) {
            New-Item -ItemType Directory -Path $path -Force | Out-Null
        }
    }
}

Ensure-LocalDirs -ProjectRoot $Root

$php = Find-PhpExecutable
if (-not $php) {
    Write-Host ""
    Write-Host "PHP was not found on this machine." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Options:" -ForegroundColor Cyan
    Write-Host "  1. Docker (recommended):  docker compose up"
    Write-Host "  2. Install PHP:           winget install PHP.PHP.8.3"
    Write-Host "     Then re-run:           .\serve.bat"
    Write-Host ""
    exit 1
}

Write-Host ""
Write-Host "SELF Schedule Display — local dev server" -ForegroundColor Cyan
Write-Host "  PHP:     $php"
Write-Host "  Root:    $Root"
Write-Host "  URL:     http://localhost:$Port/"
Write-Host "  Admin:   http://localhost:$Port/admin/login.php"
Write-Host "  Room:    http://localhost:$Port/room.php?room=salon-a"
Write-Host ""
Write-Host "Reset admin password:" -ForegroundColor DarkGray
Write-Host "  php scripts\reset-admin.php `"YourNewPassword`" admin"
Write-Host ""
Write-Host "Press Ctrl+C to stop."
Write-Host ""

Set-Location $Root
& $php -S "localhost:$Port" -t $Root

# ==============================================================
# Apotek POS Pro - Packaging & Installer Builder Script
# ==============================================================

$rootDir = Split-Path -Parent $PSScriptRoot
$distDir = Join-Path $rootDir "dist"
if (!(Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  APOTEK POS PRO - PEMBUATAN FULL INSTALLER / PACKAGING" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Compile ApotekPOS.exe terbaru
Write-Host "[1/4] Mengompilasi ApotekPOS.exe desktop..." -ForegroundColor Yellow
$cscPath = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
$icoPath = Join-Path $rootDir "ApotekPOS.ico"
$srcPath = Join-Path $rootDir "desktop\ApotekPOS.cs"
$outExe  = Join-Path $rootDir "ApotekPOS.exe"

& $cscPath /target:winexe /win32icon:$icoPath /out:$outExe $srcPath
if ($LASTEXITCODE -eq 0) {
    Write-Host " -> ApotekPOS.exe berhasil dikompilasi!" -ForegroundColor Green
} else {
    Write-Host " -> Gagal kompilasi ApotekPOS.exe" -ForegroundColor Red
}

# 2. Siapkan Portable Runtime PHP jika belum ada
Write-Host "[2/4] Memeriksa Portable PHP Runtime..." -ForegroundColor Yellow
$runtimePhpDir = Join-Path $rootDir "runtime\php"
if (!(Test-Path $runtimePhpDir)) {
    New-Item -ItemType Directory -Path $runtimePhpDir -Force | Out-Null
    $laragonPhp = "C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64"
    if (Test-Path $laragonPhp) {
        Write-Host " -> Menyalin portable PHP 8.4 engine ke ./runtime/php..." -ForegroundColor Gray
        Copy-Item -Path "$laragonPhp\*" -Destination $runtimePhpDir -Recurse -Force
        Write-Host " -> Portable PHP siap!" -ForegroundColor Green
    }
} else {
    Write-Host " -> Portable PHP runtime sudah ada." -ForegroundColor Green
}

# 3. Cek Inno Setup Compiler (iscc.exe)
Write-Host "[3/4] Memeriksa Inno Setup Compiler..." -ForegroundColor Yellow
$isccCandidates = @(
    "iscc",
    "C:\Program Files (x86)\Inno Setup 6\ISCC.exe",
    "C:\Program Files\Inno Setup 6\ISCC.exe",
    "C:\Program Files (x86)\Inno Setup 5\ISCC.exe"
)

$isccPath = $null
foreach ($c in $isccCandidates) {
    if (Get-Command $c -ErrorAction SilentlyContinue) {
        $isccPath = $c
        break
    } elseif (Test-Path $c) {
        $isccPath = $c
        break
    }
}

if ($isccPath) {
    Write-Host " -> Inno Setup ditemukan di: $isccPath" -ForegroundColor Green
    Write-Host " -> Mengompilasi ApotekPOS_Setup_v2.0.exe..." -ForegroundColor Yellow
    $issFile = Join-Path $rootDir "setup\ApotekPOS_Setup.iss"
    & $isccPath $issFile
    if ($LASTEXITCODE -eq 0) {
        Write-Host " -> INSTALLER EXE BERHASIL DIBUAT di: $distDir\ApotekPOS_Setup_v2.0.exe" -ForegroundColor Green
    }
} else {
    Write-Host " -> Inno Setup compiler tidak terdeteksi di sistem." -ForegroundColor Gray
    Write-Host " -> Menghasilkan paket Standalone Portable Zip..." -ForegroundColor Yellow

    $zipOutput = Join-Path $distDir "ApotekPOS_Standalone_Server_v2.0.zip"
    if (Test-Path $zipOutput) { Remove-Item $zipOutput -Force }

    # Buat portable zip dengan pengecualian file log dan cache
    Compress-Archive -Path @(
        "$rootDir\ApotekPOS.exe",
        "$rootDir\ApotekPOS.ico",
        "$rootDir\apotek-pos-local",
        "$rootDir\hardware-bridge",
        "$rootDir\runtime",
        "$rootDir\scripts"
    ) -DestinationPath $zipOutput -Force

    Write-Host " -> PAKET STANDALONE ZIP BERHASIL DIBUAT di: $zipOutput" -ForegroundColor Green
}

Write-Host ""
Write-Host "[4/4] Selesai! Paket installer/portable siap didistribusikan ke server baru." -ForegroundColor Cyan

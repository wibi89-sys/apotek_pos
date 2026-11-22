@echo off
title Apotek POS Pro - Server & Client Auto Setup
color 0A
cd /d "%~dp0\.."

echo ==============================================================
echo        APOTEK POS PRO - PEMASANGAN OTOMATIS SERVER
echo ==============================================================
echo.

:: 1. Cek / Atur Hak Akses Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [INFO] Menjalankan izin Administrator untuk firewall...
)

:: 2. Deteksi PHP Executable
set "PHP_CMD=php"
if exist "%CD%\runtime\php\php.exe" (
    set "PHP_CMD=%CD%\runtime\php\php.exe"
) else if exist "C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe" (
    set "PHP_CMD=C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe"
)
echo [1/5] Menggunakan PHP Engine: %PHP_CMD%

:: 3. Konfigurasi File Lingkungan (.env)
cd /d "%CD%\apotek-pos-local"
if not exist ".env" (
    echo [2/5] Menyiapkan file .env baru...
    copy ".env.example" ".env" >nul
    "%PHP_CMD%" artisan key:generate --force
) else (
    echo [2/5] File .env sudah tersedia.
)

:: 4. Migrasi Struktur Database & Master Data
echo [3/5] Memeriksa dan memigrasikan struktur database...
"%PHP_CMD%" artisan migrate --force

:: 5. Konfigurasi Windows Firewall (Akses Kasir LAN)
echo [4/5] Membuka port LAN kasir (8001) dan hardware bridge (8765)...
netsh advfirewall firewall add rule name="Apotek POS Server" dir=in action=allow protocol=TCP localport=8001 >nul 2>&1
netsh advfirewall firewall add rule name="Apotek POS Bridge" dir=in action=allow protocol=TCP localport=8765 >nul 2>&1

:: 6. Daftarkan Auto-Start Windows Boot
cd /d "%~dp0\.."
echo [5/5] Mendaftarkan auto-start saat Windows booting...
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "ApotekPOS" /t REG_SZ /d "\"%CD%\ApotekPOS.exe\"" /f >nul 2>&1

echo.
echo ==============================================================
echo   PEMASANGAN SERVER SELESAI! APLIKASI AKAN DIBUKA OTOMATIS...
echo ==============================================================
echo.

start "" "%CD%\ApotekPOS.exe"
exit

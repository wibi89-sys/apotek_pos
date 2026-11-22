@echo off
title Apotek POS Desktop - System Launcher
echo =======================================================
echo          APOTEK POS DESKTOP - SYSTEM LAUNCHER
echo =======================================================
echo.

REM 0. Check & Start MySQL (Port 3306) if not running
netstat -ano | findstr :3306 >nul
if %errorlevel% neq 0 (
    echo [*] Menjalankan MySQL Database Server (Port 3306)...
    if exist "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" (
        start /min "MySQL Daemon" "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" --defaults-file="C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini" --console
        timeout /t 3 /nobreak >nul
    ) else (
        echo [!] Perhatian: Pastikan MySQL aktif di Laragon.
    )
) else (
    echo [OK] MySQL Database aktif di port 3306.
)

REM 1. Start Hardware Bridge (Port 8765) if not running
netstat -ano | findstr :8765 >nul
if %errorlevel% neq 0 (
    echo [*] Menjalankan Hardware Bridge (Port 8765 - U.are.U & Printer)...
    start /min "Hardware Bridge" cmd /c "cd /d C:\laragon\www\apotek_pos\hardware-bridge && .venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8765"
    timeout /t 2 /nobreak >nul
) else (
    echo [OK] Hardware Bridge aktif di port 8765.
)

REM 2. Start POS Local Server (Port 8001) if not running
netstat -ano | findstr :8001 >nul
if %errorlevel% neq 0 (
    echo [*] Menjalankan Server POS Apotek Lokal (Port 8001)...
    start /min "Apotek POS Local" cmd /c "cd /d C:\laragon\www\apotek_pos\apotek-pos-local && php artisan serve --host=127.0.0.1 --port=8001"
    timeout /t 2 /nobreak >nul
) else (
    echo [OK] Server POS Lokal aktif di port 8001.
)

REM 3. Launch Cashier POS in Desktop Executable
echo [*] Membuka Aplikasi Desktop Apotek POS...
if exist "C:\laragon\www\apotek_pos\ApotekPOS.exe" (
    start "" "C:\laragon\www\apotek_pos\ApotekPOS.exe"
) else (
    start http://127.0.0.1:8001/pos
)

echo.
echo =======================================================
echo [BERHASIL] Aplikasi Apotek POS Desktop siap digunakan!
echo =======================================================
timeout /t 3
exit

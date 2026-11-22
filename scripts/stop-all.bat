@echo off
title Apotek POS - Stop Services
echo [*] Menghentikan service lokal Apotek POS...

taskkill /f /im ApotekPOS.exe 2>nul
taskkill /f /im php.exe /fi "WINDOWTITLE eq Apotek POS Local*" 2>nul
taskkill /f /im python.exe /fi "WINDOWTITLE eq Hardware Bridge*" 2>nul

echo [OK] Seluruh proses latar belakang telah dihentikan.
timeout /t 2
exit

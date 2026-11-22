@echo off
title Apotek POS - Kiosk Mode Launcher
echo [*] Meluncurkan Apotek POS dalam Kiosk Fullscreen Mode...

if exist "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" (
    start "" "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --kiosk "http://127.0.0.1:8001/pos" --edge-kiosk-type=fullscreen --no-first-run
) else if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk "http://127.0.0.1:8001/pos" --incognito --disable-pinch
) else (
    start http://127.0.0.1:8001/pos
)

exit

@echo off
title SENTRA SHIELD - matikan lab
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0matikan-lab.ps1"
echo.
pause

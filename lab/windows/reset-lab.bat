@echo off
title SENTRA SHIELD - reset lab
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0reset-lab.ps1"
echo.
pause

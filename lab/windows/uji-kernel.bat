@echo off
title SENTRA SHIELD - uji kernel
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0uji-kernel.ps1"
echo.
pause

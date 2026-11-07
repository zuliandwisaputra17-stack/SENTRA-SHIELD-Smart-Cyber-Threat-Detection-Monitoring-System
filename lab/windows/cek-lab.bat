@echo off
title SENTRA SHIELD - cek lab
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0cek-lab.ps1"
echo.
pause

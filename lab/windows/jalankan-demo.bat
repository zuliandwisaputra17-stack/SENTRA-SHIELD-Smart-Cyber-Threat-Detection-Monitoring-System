@echo off
title SENTRA SHIELD - jalankan demo
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0jalankan-demo.ps1"
echo.
pause

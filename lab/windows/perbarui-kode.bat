@echo off
title SENTRA SHIELD - perbarui kode
rem Klik dua kali file ini. Jendela akan tetap terbuka sampai Anda menekan tombol.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0perbarui-kode.ps1"
echo.
pause

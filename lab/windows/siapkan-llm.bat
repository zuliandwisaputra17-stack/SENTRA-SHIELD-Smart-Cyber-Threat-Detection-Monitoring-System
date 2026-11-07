@echo off
title SENTRA SHIELD - siapkan LLM lokal
rem OPSIONAL. Klik dua kali file ini sekali saja (butuh internet).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0siapkan-llm.ps1"
echo.
pause

@echo off
REM ============================================================
REM  Varredura de saude do ERP Cozinca (duplo-clique para rodar)
REM  Precisa do Apache e do MySQL do XAMPP LIGADOS.
REM ============================================================
title Healthcheck ERP Cozinca
cd /d "%~dp0.."

REM localiza o bash do Git (ajuste se seu Git estiver noutro caminho)
set "BASH=C:\Program Files\Git\bin\bash.exe"
if not exist "%BASH%" set "BASH=C:\Program Files\Git\usr\bin\bash.exe"

if not exist "%BASH%" (
  echo Nao encontrei o Git Bash. Instale o Git para Windows ou rode:
  echo   bash tools/healthcheck.sh
  pause
  exit /b 1
)

"%BASH%" tools/healthcheck.sh
echo.
pause

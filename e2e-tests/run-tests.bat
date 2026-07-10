@echo off
echo Instalando dependencias do Playwright...
cd /d "C:\xampp\htdocs\e2e-tests"
call "C:\Program Files\nodejs\npm.cmd" install
echo.
echo Instalando navegadores do Playwright...
call "C:\Program Files\nodejs\npx.cmd" playwright install
echo.
echo Executando testes...
call "C:\Program Files\nodejs\npx.cmd" playwright test
pause

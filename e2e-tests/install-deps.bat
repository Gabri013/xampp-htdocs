@echo off
cd /d "C:\xampp\htdocs\e2e-tests"
"C:\Program Files\nodejs\npm.exe" install
"C:\Program Files\nodejs\npx.cmd" playwright install
pause

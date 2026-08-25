@echo off
REM Script para ejecutar el cron de revelación cada 3 segundos
REM Ejecutar este archivo para iniciar el proceso de revelación automática

echo Iniciando cron de revelacion de tapazos...
echo Presiona Ctrl+C para detener

:loop
php "%~dp0revelar_siguiente.php"
timeout /t 3 /nobreak >nul
goto loop

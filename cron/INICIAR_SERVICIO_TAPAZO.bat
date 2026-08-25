@echo off
title Servicio de Tapazo - Revelacion Automatica
color 0A

echo.
echo ========================================
echo   SERVICIO DE TAPAZO - TIEMPO REAL
echo ========================================
echo.
echo Este script controla la revelacion
echo automatica de los tapazos cada 3 segundos
echo.
echo IMPORTANTE: Mantener esta ventana ABIERTA
echo mientras haya tapazos activos
echo.
echo Para detener: Presiona Ctrl+C
echo ========================================
echo.
echo Iniciando servicio...
echo.

:loop
php "%~dp0revelar_siguiente.php"
if errorlevel 1 (
    echo [ERROR] Fallo al ejecutar PHP
    echo Verifica que XAMPP este instalado
    pause
    exit /b 1
)
timeout /t 3 /nobreak >nul
goto loop

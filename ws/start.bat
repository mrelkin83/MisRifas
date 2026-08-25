@echo off
echo ======================================
echo  MisRifas WebSocket Server
echo ======================================
echo.
echo  Iniciando servidor WebSocket...
echo  - WebSocket: ws://localhost:8081
echo  - TCP Bridge: tcp://127.0.0.1:8082
echo.
php ws/server.php
pause

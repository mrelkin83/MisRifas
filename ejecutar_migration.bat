@echo off
Ejecutar manualmente:
C:\xampp\mysql\bin\mysql.exe -uroot -p u862006659_misrifas --default-character-set=utf8mb4 --execute "SET NAMES utf8mb4; SOURCE C:/xampp/htdocs/MisRifas/database/migrations/v3.1_pagos_transaccionales.sql"

O desde XAMPP MySQL Shell:
USE u862006659_misrifas;
SOURCE C:/xampp/htdocs/MisRifas/database/migrations/v3.1_pagos_transaccionales.sql;

Verificar tablas creadas:
SHOW TABLES LIKE '%numero%';
SHOW TABLES LIKE '%payment_intents%';
SHOW TABLES LIKE '%webhook_logs%';
PAUSE

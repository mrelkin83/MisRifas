# 🍺 Instrucciones: Sistema de Tapazo en Tiempo Real con Sonido

## ✅ Problemas Resueltos

### 1. ✅ Sonido de Botella.mp3
- **Problema anterior**: El sonido no se reproducía en cada destapada
- **Solución implementada**:
  - Mejorado el sistema de reproducción de audio
  - Agregados logs de depuración en la consola del navegador
  - El sonido se reproduce 2 veces por cada destape:
    - Al iniciar la animación (playPopSound)
    - Al revelar el número (playRevealSound)

### 2. ✅ Sincronización en Tiempo Real
- **Problema anterior**: Cada navegador mostraba resultados diferentes
- **Solución implementada**:
  - Nuevo endpoint `/api/tapazo/estado_revelaciones.php` que sirve el estado sincronizado
  - Todos los clientes consultan el mismo servidor cada 2 segundos
  - El servidor controla el ritmo de revelación (un jugador cada 3 segundos)
  - Todos los navegadores ven exactamente lo mismo al mismo tiempo

---

## 🚀 Cómo Activar el Sistema

### Opción 1: Ejecución Manual (Recomendada para Pruebas)

1. Abre una terminal CMD o PowerShell
2. Navega a la carpeta del proyecto:
   ```bash
   cd C:\xampp\htdocs\MisRifas\cron
   ```
3. Ejecuta el script de revelación:
   ```bash
   ejecutar_revelar_siguiente.bat
   ```
4. **Deja esta ventana abierta** mientras haya tapazos activos
5. Presiona `Ctrl+C` para detener cuando termines

### Opción 2: Servicio en Segundo Plano (Para Producción)

#### En Windows con Task Scheduler:

1. Abre el **Programador de tareas** (Task Scheduler)
2. Crear tarea básica:
   - Nombre: "Tapazo - Revelación Automática"
   - Desencadenador: Al iniciar
   - Acción: Iniciar un programa
   - Programa: `C:\xampp\php\php.exe`
   - Argumentos: `C:\xampp\htdocs\MisRifas\cron\revelar_siguiente.php`
   - Configuración avanzada: Repetir cada 3 segundos durante 24 horas

#### En Linux con Cron:

Agrega al crontab:
```bash
* * * * * cd /path/to/MisRifas && php cron/revelar_siguiente.php
* * * * * sleep 3 && cd /path/to/MisRifas && php cron/revelar_siguiente.php
* * * * * sleep 6 && cd /path/to/MisRifas && php cron/revelar_siguiente.php
* * * * * sleep 9 && cd /path/to/MisRifas && php cron/revelar_siguiente.php
```

---

## 🔊 Verificación del Sonido

### 1. Verifica que el archivo existe:
```bash
C:\xampp\htdocs\MisRifas\recursos\Botella.mp3
```

### 2. Abre la consola del navegador (F12)
Deberías ver mensajes como:
```
🔊 Reproduciendo sonido POP: /recursos/Botella.mp3
✅ Sonido POP reproducido correctamente
🔊 Reproduciendo sonido REVEAL: /recursos/Botella.mp3
✅ Sonido REVEAL reproducido correctamente
```

### 3. Si el sonido no funciona:
- **Permiso del navegador**: Algunos navegadores requieren interacción del usuario antes de permitir audio automático
- **Solución**: Haz clic en el botón "¡Iniciar Destape Ahora!" o cualquier parte de la página antes del destape
- **Verificar volumen**: El botón 🔊 en la esquina inferior derecha controla si el sonido está activado

---

## 🎯 Flujo de Funcionamiento

### Antes del Destape:
1. Los jugadores se unen y eligen sus cervezas
2. Cuando todos se unen, aparece el contador regresivo
3. Se puede iniciar manualmente o esperar al tiempo programado

### Durante el Destape:
1. El servidor inicia el destape y asigna números aleatorios
2. **El cron revela un jugador cada 3 segundos** (ejecutado por el servidor)
3. Todos los navegadores consultan el estado cada 2 segundos
4. Cada navegador:
   - Obtiene la lista de jugadores revelados
   - Muestra solo los nuevos que faltan
   - **Reproduce el sonido para cada revelación nueva**
   - Muestra la animación de destape

### Al Finalizar:
1. Cuando se revelan todos los jugadores, el estado cambia a "finalizado"
2. Se muestran los resultados finales con el ganador destacado
3. Se detiene el polling automáticamente

---

## 📊 Archivos Modificados/Creados

### Nuevos Archivos:
- ✅ `api/tapazo/estado_revelaciones.php` - Endpoint de sincronización
- ✅ `cron/revelar_siguiente.php` - Cron para revelar jugadores
- ✅ `cron/ejecutar_revelar_siguiente.bat` - Script de ejecución Windows

### Archivos Modificados:
- ✅ `tapazo/index.php` - Mejorado el sistema de polling y sonido
  - Nueva función `startDestapePolling()` que usa el endpoint sincronizado
  - Mejoradas las funciones `playPopSound()` y `playRevealSound()` con logs
  - Mejorada la función `revealPlayer()` con depuración

---

## 🧪 Pruebas

### Para probar el sistema completo:

1. **Inicia el cron de revelación**:
   ```bash
   cd C:\xampp\htdocs\MisRifas\cron
   ejecutar_revelar_siguiente.bat
   ```

2. **Crea un tapazo de prueba**:
   - Ve a: `http://localhost/MisRifas/tapazo/`
   - Crea un tapazo con 3-4 jugadores
   - Fecha de destape: dentro de 2 minutos

3. **Abre múltiples navegadores** (o pestañas en modo incógnito):
   - Copia el link del tapazo
   - Únete como diferentes jugadores desde diferentes navegadores
   - Elige diferentes números de cerveza

4. **Observa la sincronización**:
   - Espera al destape automático o inicia manualmente
   - Todos los navegadores deben mostrar lo mismo al mismo tiempo
   - El sonido debe reproducirse en cada destape
   - Las animaciones deben ser sincronizadas

### Verificación en la Consola:
Abre F12 en cada navegador y verifica que veas:
```
🎯 Revelando jugador: {nombre}
🔊 Llamando a playPopSound()...
🔊 Reproduciendo sonido POP: /recursos/Botella.mp3
✅ Sonido POP reproducido correctamente
```

---

## 🐛 Solución de Problemas

### El sonido no se reproduce:
1. Verifica que `Botella.mp3` exista en `recursos/`
2. Abre la consola (F12) y busca errores de audio
3. Verifica que el botón 🔊 esté activado
4. Interactúa con la página antes del destape (click en cualquier lado)

### Los navegadores muestran diferentes resultados:
1. Verifica que el cron esté ejecutándose: `ejecutar_revelar_siguiente.bat`
2. Verifica en la base de datos que el campo `ultimo_revelado` se actualice
3. Abre la consola de red (F12 > Network) y verifica que se llame a `estado_revelaciones.php`

### El destape no inicia automáticamente:
1. Verifica que el cron `cron/destape.php` esté configurado (inicia el destape)
2. Verifica que la fecha/hora del tapazo sea correcta
3. Usa el botón "¡Iniciar Destape Ahora!" para forzar el inicio

---

## 📞 Soporte

Si encuentras algún problema:
1. Revisa los logs en la consola del navegador (F12)
2. Revisa los logs del cron en la terminal donde se ejecuta
3. Verifica el estado en la base de datos:
   ```sql
   SELECT id, estado, ultimo_revelado FROM tapazos WHERE codigo_unico = 'XXX';
   SELECT * FROM tapazo_jugadores WHERE tapazo_id = X;
   ```

---

## ✅ Checklist de Implementación

- [✅] Endpoint de sincronización creado
- [✅] Sistema de polling actualizado
- [✅] Sonido implementado con logs
- [✅] Cron de revelación creado
- [✅] Script de ejecución Windows creado
- [✅] Documentación completa
- [ ] **Ejecutar el cron**: `cron/ejecutar_revelar_siguiente.bat`
- [ ] **Probar con múltiples navegadores**

---

¡Disfruta del nuevo sistema de Tapazo en tiempo real! 🍺🎉

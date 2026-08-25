# 🔍 Guía de Verificación: Por Qué No Se Efectúa el Destape

## ❌ Síntomas

- Los jugadores pueden unirse ✅
- El contador regresivo funciona ✅
- El tiempo se cumple ✅
- **PERO el destape NO se efectúa** ❌
- La pantalla se queda "esperando" ❌

---

## 🎯 Causa Principal

**El servicio de revelación NO está ejecutándose.**

El sistema funciona así:
1. ✅ El tapazo inicia el destape (cambia estado a "destapando")
2. ✅ Los números se asignan a cada jugador
3. ❌ **FALTA**: Un servicio que revele jugador por jugador cada 3 segundos
4. ❌ Sin este servicio, nadie ve los resultados

---

## ✅ Solución: Iniciar el Servicio

### Paso 1: Verificar si está corriendo

**En Windows:**

1. Abre el **Administrador de Tareas** (Ctrl+Shift+Esc)
2. Ve a la pestaña "Detalles"
3. Busca `php.exe` ejecutándose
4. **SI NO HAY** ningún `php.exe` extra corriendo → El servicio NO está activo

**Alternativa - Ver si hay una ventana CMD abierta**:
- ¿Hay una ventana negra con el título "Servicio de Tapazo"?
- Si NO → El servicio NO está corriendo

---

### Paso 2: Iniciar el Servicio

1. **Abre una terminal CMD o PowerShell NUEVA**

2. Navega a la carpeta del proyecto:
   ```bash
   cd C:\xampp\htdocs\MisRifas\cron
   ```

3. **Ejecuta el servicio**:
   ```bash
   INICIAR_SERVICIO_TAPAZO.bat
   ```

4. Deberías ver:
   ```
   ========================================
     SERVICIO DE TAPAZO - TIEMPO REAL
   ========================================
   Iniciando servicio...
   ```

5. **IMPORTANTE**:
   - ✅ Mantén esta ventana **ABIERTA**
   - ✅ Minimízala pero NO la cierres
   - ✅ Mientras haya tapazos activos, debe estar corriendo

---

### Paso 3: Verificar que Funciona

Una vez el servicio esté corriendo, deberías ver en la ventana:

```
Revelado: Juan (#1) = 523 en tapazo 1
Revelado: María (#2) = 789 en tapazo 1
Revelado: Pedro (#3) = 156 en tapazo 1
Tapazo 1 completado y finalizado.
```

Si ves estos mensajes → ✅ **El servicio está funcionando correctamente**

Si NO ves nada → ⚠️ **No hay tapazos en estado "destapando"**

---

## 🔄 Si el Tapazo Ya Inició Pero Se Trabó

Si ya iniciaste el destape pero se quedó trabado:

### Opción 1: Recarga la Página

1. Asegúrate de que el servicio esté corriendo (Paso 2)
2. **Recarga la página del tapazo** (F5 o Ctrl+R)
3. El sistema debería continuar revelando automáticamente

---

### Opción 2: Reiniciar el Servicio

1. **Detén el servicio** (Ctrl+C en la ventana CMD)
2. **Vuelve a ejecutarlo**:
   ```bash
   INICIAR_SERVICIO_TAPAZO.bat
   ```
3. **Recarga la página del tapazo**

---

### Opción 3: Resetear el Tapazo (Solo si es necesario)

**⚠️ ADVERTENCIA**: Esto borrará todos los números revelados.

```sql
-- Conectar a MySQL
C:\xampp\mysql\bin\mysql.exe -u root misrifas

-- Ver estado actual
SELECT id, codigo_unico, estado, ultimo_revelado
FROM tapazos
WHERE codigo_unico = 'TU_CODIGO_AQUI';

-- Resetear solo si es absolutamente necesario
UPDATE tapazos
SET estado = 'lleno', ultimo_revelado = ''
WHERE codigo_unico = 'TU_CODIGO_AQUI';

-- Salir
exit
```

Luego recarga la página y vuelve a iniciar el destape.

---

## 📊 Checklist Completo

Antes de iniciar un tapazo, verifica:

- [ ] XAMPP Apache está corriendo
- [ ] XAMPP MySQL está corriendo
- [ ] Al menos 1 jugador se ha unido
- [ ] **El servicio de revelación está corriendo** ⚠️ CRÍTICO
- [ ] Accedes con la ruta completa: `http://localhost/MisRifas/tapazo/`
- [ ] Has hecho clic en la página (para habilitar audio)

---

## 🐛 Otros Problemas Comunes

### Error 400 en unirse.php

**Causa**: El tapazo ya cambió de estado o está lleno
**Solución**:
- Verifica cuántos jugadores hay vs. el total permitido
- Intenta unirte con otro nombre
- Recarga la página

### SSE Connection Lost

**Causa**: La conexión SSE se pierde (normal, se reconecta automáticamente)
**Solución**: No hacer nada, el sistema se reconecta solo cada 3 segundos

### Audio no se reproduce

**Causa**: El navegador bloquea audio automático
**Solución**: Haz clic en cualquier parte de la página antes del destape

---

## ✅ Flujo Correcto Completo

### 1. **ANTES de crear el tapazo**:
```bash
cd C:\xampp\htdocs\MisRifas\cron
INICIAR_SERVICIO_TAPAZO.bat
```
✅ Servicio corriendo

### 2. **Crear el tapazo**:
```
http://localhost/MisRifas/tapazo/
```
- Configura jugadores, fecha, regla
- Comparte el link

### 3. **Jugadores se unen**:
- Cada jugador elige su cerveza
- Cuando todos están → aparece contador

### 4. **Destape**:
- Automático al cumplirse el tiempo
- O manual con "¡Iniciar Destape Ahora!"

### 5. **Revelación Automática**:
- El servicio revela un jugador cada 3 segundos
- Todos los navegadores se sincronizan
- Animaciones y sonidos

### 6. **Resultado**:
- Se muestra el ganador
- Estado cambia a "finalizado"

---

## 🚨 Recordatorio Importante

**EL SERVICIO DEBE ESTAR CORRIENDO SIEMPRE QUE HAYA TAPAZOS ACTIVOS**

```bash
# Iniciar servicio
cd C:\xampp\htdocs\MisRifas\cron
INICIAR_SERVICIO_TAPAZO.bat

# Mantener ventana abierta
# Minimizar pero NO cerrar
```

---

## 📞 ¿Aún No Funciona?

1. Verifica el log de errores de Apache: `C:\xampp\apache\logs\error.log`
2. Verifica el log de PHP errors
3. Abre la consola del navegador (F12) y busca errores
4. Asegúrate de que la tabla `tapazos` y `tapazo_jugadores` existan en la base de datos

---

**¡Siguiendo esta guía, el destape debería funcionar perfectamente!** 🍺🎉

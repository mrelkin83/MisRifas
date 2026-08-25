# 🔧 Solución Rápida: BASE_PATH Vacío (Error 500)

## ❌ Problema

Al intentar crear un tapazo desde `http://localhost/MisRifas/tapazo/`:

```
BASE_PATH: (vacío)
API URL: /api/tapazo/crear.php
Error 500
```

---

## ✅ Solución Inmediata

### Opción 1: Acceder con la Ruta Completa

**En lugar de**:
```
http://localhost/tapazo/
```

**Usar**:
```
http://localhost/MisRifas/tapazo/
```

Esto asegura que el sistema detecte correctamente `/MisRifas` como BASE_PATH.

---

### Opción 2: Verificar el .htaccess

Asegúrate de tener un `.htaccess` en la carpeta `tapazo/`:

**Archivo**: `C:\xampp\htdocs\MisRifas\tapazo\.htaccess`

```apache
# No hacer nada especial, solo heredar del padre
```

O simplemente elimina el `.htaccess` de la carpeta `tapazo` si existe.

---

### Opción 3: Debug Manual

1. Abre en el navegador:
   ```
   http://localhost/MisRifas/tapazo/debug_basepath.php
   ```

2. Verifica que muestre:
   ```
   BASE_PATH (constante): /MisRifas
   BASE_PATH en .env: /MisRifas
   ```

3. Si muestra vacío, hay un problema con la carga del `.env`

---

## 🔍 Diagnóstico

### Verificar .env

1. Abre: `C:\xampp\htdocs\MisRifas\.env`

2. Verifica que tenga:
   ```
   BASE_PATH=/MisRifas
   ```

3. NO debe tener espacios extras ni comillas:
   ```
   ❌ BASE_PATH = /MisRifas
   ❌ BASE_PATH="/MisRifas"
   ✅ BASE_PATH=/MisRifas
   ```

---

### Verificar Permisos

En Windows, asegúrate de que XAMPP tenga permisos para leer el archivo `.env`:

1. Click derecho en `C:\xampp\htdocs\MisRifas\.env`
2. Propiedades → Seguridad
3. Asegúrate de que "Usuarios" tenga permiso de lectura

---

## 🎯 Solución Definitiva

**Archivos modificados**:
- ✅ `config/paths.php` - Ahora detecta `/MisRifas/tapazo/` correctamente
- ✅ Agregado patrón específico para `/tapazo/`

**Cambios realizados**:
```php
// Antes (NO detectaba /tapazo/)
if ($matches[1] !== '/public' && $matches[1] !== '/tapazo') {
    $basePath = $matches[1];
}

// Ahora (SÍ detecta /tapazo/)
elseif (preg_match('#^(/[^/]+)/tapazo/#', $scriptName, $matches)) {
    $basePath = $matches[1]; // /MisRifas
}
```

---

## 🧪 Prueba Final

1. **Limpia el caché del navegador** (Ctrl+F5)

2. Accede a:
   ```
   http://localhost/MisRifas/tapazo/
   ```

3. Abre la **Consola del navegador** (F12)

4. Deberías ver:
   ```
   BASE_PATH: /MisRifas
   API URL: /MisRifas/api/tapazo/crear.php
   ```

5. Intenta crear un tapazo de prueba

---

## ⚠️ Si Aún No Funciona

### 1. Reinicia XAMPP
```
Panel de Control XAMPP → Stop Apache → Start Apache
```

### 2. Limpia opcache de PHP

Crea un archivo `clear_cache.php`:
```php
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Cache limpiado";
} else {
    echo "opcache no está habilitado";
}
```

Accede a: `http://localhost/MisRifas/clear_cache.php`

### 3. Verifica el log de errores de PHP

Mira: `C:\xampp\apache\logs\error.log`

Busca errores relacionados con paths.php o .env

---

## 📝 Acceso Correcto

**Siempre usa la ruta completa**:

✅ `http://localhost/MisRifas/tapazo/`
✅ `http://localhost/MisRifas/public/index.php`
✅ `http://localhost/MisRifas/public/admin/`

❌ `http://localhost/tapazo/` (faltará el prefijo)
❌ `http://localhost/public/` (faltará el prefijo)

---

## ✅ Verificación Final

Ejecuta estos comandos en PowerShell:

```powershell
# Verificar que el .env existe
Test-Path "C:\xampp\htdocs\MisRifas\.env"
# Debe mostrar: True

# Ver contenido del .env
Get-Content "C:\xampp\htdocs\MisRifas\.env" | Select-String "BASE_PATH"
# Debe mostrar: BASE_PATH=/MisRifas
```

---

**¡Ahora el BASE_PATH debería detectarse correctamente!** 🚀

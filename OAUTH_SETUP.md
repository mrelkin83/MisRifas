# 🔐 Configuración de Login Social - MisRifas

Este documento explica cómo configurar el login con Google y Facebook en MisRifas.

## 📋 Requisitos Previos

- Proyecto funcionando en `http://localhost/MisRifas/`
- Acceso a [Google Cloud Console](https://console.cloud.google.com/)
- Acceso a [Facebook Developers](https://developers.facebook.com/)

---

## 🔵 Configurar Google OAuth

### Paso 1: Crear Proyecto en Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la **Google+ API** en "APIs y servicios"

### Paso 2: Crear Credenciales OAuth 2.0

1. Ve a **"APIs y servicios" → "Credenciales"**
2. Click en **"Crear credenciales" → "ID de cliente de OAuth 2.0"**
3. Selecciona **"Aplicación web"**
4. Configura:
   - **Nombre**: MisRifas Local
   - **Orígenes autorizados de JavaScript**:
     ```
     http://localhost
     http://localhost:80
     ```
   - **URIs de redireccionamiento autorizados**:
     ```
     http://localhost/MisRifas/api/auth/google-callback.php
     ```

5. Click en **"Crear"**
6. **COPIA** tu `Client ID` y `Client Secret`

### Paso 3: Configurar Credenciales en el Proyecto

**Opción A: Variables de entorno (Recomendado para producción)**
```bash
# En Windows (cmd)
set GOOGLE_CLIENT_ID=tu_client_id_aqui
set GOOGLE_CLIENT_SECRET=tu_client_secret_aqui

# En Linux/Mac
export GOOGLE_CLIENT_ID=tu_client_id_aqui
export GOOGLE_CLIENT_SECRET=tu_client_secret_aqui
```

**Opción B: Editar directamente los archivos**

Edita estos archivos:
- `api/auth/google.php` (líneas 10-11)
- `api/auth/google-callback.php` (líneas 10-11)

Reemplaza:
```php
$GOOGLE_CLIENT_ID = 'TU_GOOGLE_CLIENT_ID_AQUI';
$GOOGLE_CLIENT_SECRET = 'TU_GOOGLE_CLIENT_SECRET_AQUI';
```

---

## 🔵 Configurar Facebook OAuth

### Paso 1: Crear Aplicación en Facebook Developers

1. Ve a [Facebook Developers](https://developers.facebook.com/)
2. Click en **"Mis aplicaciones" → "Crear aplicación"**
3. Selecciona **"Consumidor"** como tipo de aplicación
4. Completa el formulario:
   - **Nombre de la aplicación**: MisRifas
   - **Correo electrónico de contacto**: tu@email.com

### Paso 2: Configurar Facebook Login

1. En el panel de tu aplicación, ve a **"Productos" → "Agregar producto"**
2. Selecciona **"Facebook Login" → "Configurar"**
3. Selecciona **"Web"**
4. Ingresa la URL del sitio:
   ```
   http://localhost/MisRifas/
   ```

### Paso 3: Configurar URIs de Redirección

1. Ve a **"Facebook Login" → "Configuración"**
2. En **"URI de redireccionamiento OAuth válidos"** agrega:
   ```
   http://localhost/MisRifas/api/auth/facebook-callback.php
   ```
3. Guarda los cambios

### Paso 4: Obtener Credenciales

1. Ve a **"Configuración" → "Básica"**
2. **COPIA**:
   - **Identificador de la aplicación** (App ID)
   - **Clave secreta de la aplicación** (App Secret)

### Paso 5: Configurar Credenciales en el Proyecto

**Opción A: Variables de entorno**
```bash
# En Windows (cmd)
set FACEBOOK_APP_ID=tu_app_id_aqui
set FACEBOOK_APP_SECRET=tu_app_secret_aqui

# En Linux/Mac
export FACEBOOK_APP_ID=tu_app_id_aqui
export FACEBOOK_APP_SECRET=tu_app_secret_aqui
```

**Opción B: Editar directamente los archivos**

Edita estos archivos:
- `api/auth/facebook.php` (líneas 10-11)
- `api/auth/facebook-callback.php` (líneas 10-11)

Reemplaza:
```php
$FACEBOOK_APP_ID = 'TU_FACEBOOK_APP_ID_AQUI';
$FACEBOOK_APP_SECRET = 'TU_FACEBOOK_APP_SECRET_AQUI';
```

---

## ✅ Verificar Instalación

### 1. Probar Google Login

1. Ve a `http://localhost/MisRifas/public/admin/index.php?auth=login`
2. Click en **"Continuar con Google"**
3. Deberías ser redirigido a Google
4. Acepta los permisos
5. Deberías ser redirigido de vuelta y logeado automáticamente

### 2. Probar Facebook Login

1. Ve a `http://localhost/MisRifas/public/admin/index.php?auth=login`
2. Click en **"Continuar con Facebook"**
3. Deberías ser redirigido a Facebook
4. Acepta los permisos
5. Deberías ser redirigido de vuelta y logeado automáticamente

---

## 🔧 Solución de Problemas

### Error: "redirect_uri_mismatch" (Google)
- Verifica que la URI en Google Cloud Console sea **exactamente**:
  ```
  http://localhost/MisRifas/api/auth/google-callback.php
  ```
- Nota: NO uses `127.0.0.1`, usa `localhost`

### Error: "URL Blocked" (Facebook)
- Verifica que agregaste la URI en Facebook Developers
- Asegúrate de que la aplicación esté en **Modo de desarrollo**
- Para producción, deberás pasar la revisión de Facebook

### El usuario se crea pero no inicia sesión
- Verifica que la tabla `admin_users` tenga las columnas:
  - `oauth_provider` (VARCHAR)
  - `oauth_id` (VARCHAR)

### Errores de SSL/HTTPS
- En desarrollo local (HTTP), ambos proveedores funcionan
- En producción, **DEBES usar HTTPS**

---

## 📊 Base de Datos

Asegúrate de que la tabla `admin_users` tenga estas columnas:

```sql
ALTER TABLE admin_users ADD COLUMN oauth_provider VARCHAR(20) DEFAULT NULL;
ALTER TABLE admin_users ADD COLUMN oauth_id VARCHAR(255) DEFAULT NULL;
```

Si ya existen, puedes omitir este paso.

---

## 🚀 Producción

### Para Google:
1. Cambia las URIs a tu dominio real:
   ```
   https://tudominio.com/api/auth/google-callback.php
   ```
2. Actualiza `GOOGLE_REDIRECT_URI` en los archivos PHP

### Para Facebook:
1. Cambia el modo de la app a **"En producción"**
2. Completa la revisión de Facebook para "Facebook Login"
3. Actualiza las URIs a tu dominio real:
   ```
   https://tudominio.com/api/auth/facebook-callback.php
   ```

---

## 📝 Notas Importantes

- **Google** requiere email verificado
- **Facebook** puede no proporcionar email si el usuario no lo permite
  - En ese caso, se crea un email temporal: `facebook_[ID]@misrifas.local`
- Ambos sistemas crean usuarios automáticamente en la primera autenticación
- Los usuarios OAuth tienen `password_hash` vacío (no pueden hacer login tradicional)

---

## 🎯 ¿Necesitas Ayuda?

Si tienes problemas, revisa:
- Los logs de Apache: `C:/xampp/apache/logs/error.log`
- Los logs de PHP en el navegador (Consola del desarrollador)
- Verifica que CURL esté habilitado en PHP (`php.ini`)

---

**✅ Una vez configurado, los usuarios podrán registrarse e iniciar sesión con un solo click!**

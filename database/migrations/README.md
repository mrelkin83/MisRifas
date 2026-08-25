# Base de Datos - MisRifas

## ✅ Estado Actual

La base de datos está **completamente configurada y lista para usar**. No necesitas ejecutar ningún script SQL adicional.

---

## 📁 Archivo Disponible

### `setup_completo.sql` ⭐

**¿Qué contiene?**
- Creación de todas las 15 tablas del sistema
- Triggers y procedimientos almacenados
- 15 Loterías Colombianas precargadas
- Usuario admin predeterminado
- Configuración inicial del sistema

**¿Cuándo usarlo?**

**SOLO** si necesitas **recrear la base de datos desde cero**.

**NO lo ejecutes si la base de datos ya está funcionando.**

**Cómo usarlo (si es necesario):**

1. Abre phpMyAdmin
2. Selecciona la base de datos `misrifas`
3. Ve a la pestaña SQL
4. Copia y pega el contenido de `setup_completo.sql`
5. Haz clic en "Continuar"

---

## ✅ Verificación Rápida

Para verificar que tu base de datos está correctamente configurada, ejecuta en phpMyAdmin:

```sql
-- Ver todas las tablas (debe mostrar 15 tablas)
SHOW TABLES;

-- Ver loterías (debe mostrar 15 loterías)
SELECT COUNT(*) as total FROM lotteries;

-- Ver estructura de admin_users (debe tener campo 'department')
DESCRIBE admin_users;
```

**Resultados esperados:**
- ✅ 15 tablas creadas
- ✅ 15 loterías cargadas
- ✅ Campo `department` presente en `admin_users`

---

## 🚫 Archivos Eliminados

Los siguientes archivos fueron eliminados porque **NO son necesarios** y pueden causar errores:

- ❌ `update_lotteries.sql` - Las loterías ya están cargadas
- ❌ `tapazos.sql` - Ya incluido en setup_completo.sql
- ❌ `tapazo_module.sql` - Ya incluido en setup_completo.sql
- ❌ `seed_data.sql` - Datos de prueba innecesarios
- ❌ `schema.sql` - Reemplazado por setup_completo.sql

---

## 📊 Loterías Precargadas

Tu base de datos incluye las 15 loterías oficiales de Colombia:

| # | Lotería | Día | Hora |
|---|---------|-----|------|
| 1 | Lotería de Cundinamarca | Lunes | 22:30 |
| 2 | Lotería de Tolima | Lunes | 23:00 |
| 3 | Lotería Cruz Roja | Martes | 22:30 |
| 4 | Lotería de Huila | Martes | 22:30 |
| 5 | Lotería de Manizales | Miércoles | 22:30 |
| 6 | Lotería del Meta | Miércoles | 22:30 |
| 7 | Lotería del Valle | Miércoles | 22:30 |
| 8 | Lotería Quindío | Jueves | 22:30 |
| 9 | Lotería de Bogotá | Jueves | 22:30 |
| 10 | Lotería de Santander | Viernes | 23:00 |
| 11 | Lotería de Medellín | Viernes | 23:00 |
| 12 | Lotería Risaralda | Viernes | 23:00 |
| 13 | Lotería de Boyacá | Sábado | 22:40 |
| 14 | Lotería de Cauca | Sábado | 21:40 |
| 15 | Extra de Colombia | Sábado | 23:00 |

---

## 👤 Usuario Administrador

Tu base de datos incluye un usuario administrador predeterminado:

```
Email: admin@misrifas.com
Password: password123
Rol: super_admin
```

**Para iniciar sesión:**
1. Ve a: `http://localhost/MisRifas/public/admin/index.php?auth=login`
2. Usa las credenciales anteriores

---

## 🔧 Mantenimiento

### Migración de Datos

Si necesitas agregar nuevas funcionalidades, crea archivos de migración en la carpeta `database/migrations/`.

### Respaldo de Base de Datos

Para hacer un respaldo de tu base de datos:

```bash
mysqldump -u root -p misrifas > backup_$(date +%Y%m%d).sql
```

O usa phpMyAdmin:
1. Selecciona la base de datos `misrifas`
2. Ve a "Exportar"
3. Haz clic en "Continuar"

---

## ❓ Solución de Problemas

### Error: Cannot truncate a table referenced in a foreign key

**Causa:** Intentaste ejecutar un script SQL que usa `TRUNCATE` en una tabla con foreign keys.

**Solución:** **No necesitas ejecutar ese script**. Tu base de datos ya está correctamente configurada.

### Error: Table already exists

**Causa:** Intentaste ejecutar `setup_completo.sql` pero las tablas ya existen.

**Solución:**
- Si quieres mantener tus datos: **No ejecutes el script**
- Si quieres empezar desde cero: Elimina todas las tablas primero

---

## ✅ ¡Base de Datos Lista!

Tu base de datos está **100% funcional**. Puedes:

1. ✅ Registrar nuevos usuarios
2. ✅ Crear rifas
3. ✅ Gestionar boletos
4. ✅ Procesar pagos
5. ✅ Ver resultados

**No necesitas ejecutar ningún script SQL adicional.**

---

Para más información sobre el uso del sistema, consulta `GUIA_COMPLETA.md` en la raíz del proyecto.

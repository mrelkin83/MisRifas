# MisRifas SaaS Multi-Vendedor — Plan de Arquitectura Completo

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rediseñar MisRifas como un SaaS multi-vendedor donde cada vendedor opera rifas aisladas, con scraping único por lotería/fecha, evaluación de ganadores por rifa, y cola de mensajes pre-generada.

**Architecture:** PHP nativo (sin framework) + MySQL/MariaDB + TailwindCSS. Patrón Repository-Service. Todo aislado por `raffle_id`. El número ganador es global por lotería/fecha; todo lo demás es por rifa. El admin panel monolítico se splitea en módulos independientes.

**Tech Stack:** PHP 8.0+, MySQL 10.4+ (MariaDB), Apache + mod_rewrite, TailwindCSS (CDN), vanilla JS, EvolutionAPI (WhatsApp), Brevo (email). Fase 2: Gammu SMSD (SMS físico).

---

## Chunk 1: Schema — Nuevo modelo de datos

### Task 1.1: Crear tabla `vendors` (vendedores)

**Files:**
- Create: `database/migrations/v3.0_saas_redesign.sql`

La tabla `vendors` separa completamente al vendedor del `admin_users`. Un vendor NO es un admin.

```sql
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL COMMENT 'URL-friendly identifier',
  `business_name` VARCHAR(255) NOT NULL COMMENT 'Nombre del negocio',
  `legal_name` VARCHAR(255) NULL COMMENT 'Razón social',
  `document_type` ENUM('CC', 'NIT', 'CE', 'PP') NULL,
  `document_number` VARCHAR(20) NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NOT NULL COMMENT 'WhatsApp principal',
  `city` VARCHAR(100) NULL,
  `department` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `logo_url` VARCHAR(500) NULL,
  `auth_token` VARCHAR(255) NULL,
  `auth_token_expires` TIMESTAMP NULL,
  `reset_token` VARCHAR(255) NULL,
  `reset_token_expires` TIMESTAMP NULL,
  `role` ENUM('vendor', 'super_admin') NOT NULL DEFAULT 'vendor',
  `status` ENUM('active', 'suspended', 'pending_verification') NOT NULL DEFAULT 'pending_verification',
  `wa_config` JSON NULL COMMENT 'EvolutionAPI credentials per-vendor',
  `payment_config` JSON NULL COMMENT '{"mode":"manual"} o {"mode":"centralized"}',
  `commission_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo a dispersar',
  `total_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `last_login` TIMESTAMP NULL,
  `email_verified_at` TIMESTAMP NULL,
  `phone_verified_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  UNIQUE KEY `uk_email` (`email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 1: Write the migration SQL** — incluye CREATE TABLE vendors como arriba
- [ ] **Step 2: Run the migration** — `mysql -u root misrifas < database/migrations/v3.0_saas_redesign.sql`

### Task 1.2: Modificar tabla `raffles` para vincular a `vendors`

```sql
ALTER TABLE `raffles`
  ADD COLUMN `vendor_id` INT UNSIGNED NULL AFTER `id`,
  ADD INDEX `idx_vendor` (`vendor_id`),
  ADD CONSTRAINT `fk_raffles_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT;

-- Migrar datos existentes: copiar created_by (admin_users) a vendor_id
-- Primero crear vendors a partir de admin_users con role != 'super_admin'
INSERT INTO `vendors` (`id`, `business_name`, `email`, `password_hash`, `phone`, `auth_token`, `role`, `status`, `created_at`)
SELECT
  au.id,
  COALESCE(au.full_name, au.username),
  au.email,
  au.password_hash,
  COALESCE(au.phone, ''),
  au.auth_token,
  CASE WHEN au.role = 'super_admin' THEN 'super_admin' ELSE 'vendor' END,
  CASE WHEN au.active = 1 THEN 'active' ELSE 'suspended' END,
  au.created_at
FROM admin_users au;

UPDATE raffles SET vendor_id = created_by WHERE vendor_id IS NULL;
```

- [ ] **Step 3: Add vendor_id column and migrate data**

### Task 1.3: Crear tabla `draw_results` (reemplaza `lottery_results` con semántica explícita)

```sql
-- Renombrar lottery_results para claridad (opcional, o usar la existente)
-- La tabla lottery_results YA tiene UNIQUE KEY (lottery_id, draw_date)
-- Solo necesitamos asegurar que es la fuente única de verdad
ALTER TABLE `lottery_results`
  ADD COLUMN `scraped_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `verified`,
  ADD COLUMN `scrape_source` VARCHAR(50) NULL DEFAULT 'colombia.com' AFTER `scraped_at`,
  ADD COLUMN `scrape_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `scrape_source`;
```

- [ ] **Step 4: Add scrape tracking columns to lottery_results**

### Task 1.4: Crear tabla `message_queue` (reemplaza `notifications` con diseño anti-cruce)

```sql
CREATE TABLE IF NOT EXISTS `message_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL COMMENT 'OBLIGATORIO - aislamiento por rifa',
  `vendor_id` INT UNSIGNED NOT NULL COMMENT 'Para routing de credenciales WA',
  `recipient_user_id` BIGINT UNSIGNED NULL COMMENT 'FK a users (comprador)',
  `recipient_phone` VARCHAR(20) NULL COMMENT 'WhatsApp del comprador',
  `recipient_email` VARCHAR(255) NULL COMMENT 'Email del comprador',
  `channel` ENUM('whatsapp', 'email', 'sms') NOT NULL,
  `message_type` ENUM('reservation', 'payment_confirmed', 'winner', 'no_winner', 'draw_reminder', 'payment_reminder') NOT NULL,
  `subject` VARCHAR(500) NULL,
  `body_text` TEXT NOT NULL COMMENT 'Mensaje ya renderizado con variables reemplazadas',
  `body_html` TEXT NULL COMMENT 'Version HTML para email',
  `variables` JSON NULL COMMENT 'Snapshot de variables usadas para auditoria',
  `status` ENUM('pending', 'processing', 'sent', 'failed', 'bounced') NOT NULL DEFAULT 'pending',
  `scheduled_at` TIMESTAMP NOT NULL COMMENT 'Cuando enviar (ej: 05:00 AM del dia siguiente)',
  `sent_at` TIMESTAMP NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` TEXT NULL,
  `external_id` VARCHAR(255) NULL COMMENT 'ID del proveedor (Evolution msg ID, Brevo msg ID)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_raffle_status` (`raffle_id`, `status`),
  INDEX `idx_scheduled` (`status`, `scheduled_at`),
  INDEX `idx_vendor` (`vendor_id`),
  INDEX `idx_recipient` (`recipient_user_id`),
  CONSTRAINT `fk_mq_raffle` FOREIGN KEY (`raffle_id`) REFERENCES `raffles`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mq_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 5: Create message_queue table**

### Task 1.5: Modificar tabla `notifications` existente para compatibilidad

```sql
-- Agregar columnas faltantes a notifications para la transición
ALTER TABLE `notifications`
  ADD COLUMN `vendor_id` INT UNSIGNED NULL AFTER `raffle_id`,
  ADD COLUMN `scheduled_at` TIMESTAMP NULL AFTER `attempts`),
  ADD INDEX `idx_vendor` (`vendor_id`);
```

- [ ] **Step 6: Add vendor_id to notifications for transition**

### Task 1.7: Seed data — vendor de prueba + migración de admin existente

```sql
-- El INSERT del Task 1.2 ya migra los admin_users a vendors
-- Agregar super_admin desde la cuenta existente
UPDATE vendors SET role = 'super_admin', status = 'active'
WHERE email = 'admin@misrifas.com';
```

- [ ] **Step 7: Migrate existing admin to super_admin in vendors**

---

## Chunk 2: Auth — Sistema de autenticación por roles

### Task 2.1: Reescribir `api/utils/Auth.php`

**Files:**
- Modify: `api/utils/Auth.php`

Nuevo Auth con 3 capas claras:

```php
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Response.php';

class Auth
{
    public static function requireVendor(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token requerido', 401);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM vendors
            WHERE auth_token = ? AND status = 'active'
            AND (auth_token_expires IS NULL OR auth_token_expires > NOW())
        ");
        $stmt->execute([$token]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vendor) {
            Response::error('Sesion expirada o invalida', 401);
        }

        return $vendor;
    }

    public static function requireSuperAdmin(): array
    {
        $vendor = self::requireVendor();
        if ($vendor['role'] !== 'super_admin') {
            Response::error('Acceso restringido a super administradores', 403);
        }
        return $vendor;
    }

    public static function requireBuyer(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token requerido', 401);
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE auth_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error('Sesion expirada', 401);
        }

        return $user;
    }

    public static function requireLogin(): array
    {
        $token = self::extractToken();
        if (!$token) {
            Response::error('Token requerido', 401);
        }

        $db = Database::getInstance()->getConnection();

        // Intentar vendors primero
        $stmt = $db->prepare("
            SELECT *, 'vendor' as auth_type FROM vendors
            WHERE auth_token = ? AND status = 'active'
            AND (auth_token_expires IS NULL OR auth_token_expires > NOW())
        ");
        $stmt->execute([$token]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($vendor) return $vendor;

        // Luego users (buyers)
        $stmt = $db->prepare("SELECT *, 'buyer' as auth_type FROM users WHERE auth_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) return $user;

        Response::error('Sesion expirada', 401);
    }

    private static function extractToken(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        return $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
```

- [ ] **Step 1: Rewrite Auth.php with vendor/buyer/superAdmin separation**
- [ ] **Step 2: Add auth_token column to users if missing** — `ALTER TABLE users ADD COLUMN auth_token VARCHAR(255) NULL;`

### Task 2.2: Reescribir `api/auth/login.php`

**Files:**
- Modify: `api/auth/login.php`

Login unificado que busca en `vendors` primero, luego en `users`.

```php
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../api/utils/Response.php';
require_once __DIR__ . '/../../api/utils/Logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metodo no permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$identifier = trim($input['identifier'] ?? '');
$password = $input['password'] ?? '';

if (!$identifier || !$password) {
    Response::error('Email/telefono y contrasena son requeridos', 400);
}

$db = Database::getInstance()->getConnection();

// 1. Buscar en vendors (vendedores + super_admin)
$stmt = $db->prepare("
    SELECT * FROM vendors
    WHERE (email = ? OR phone = ?) AND status = 'active'
");
$stmt->execute([$identifier, $identifier]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if ($vendor && password_verify($password, $vendor['password_hash'])) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

    $stmt = $db->prepare("UPDATE vendors SET auth_token = ?, auth_token_expires = ?, last_login = NOW() WHERE id = ?");
    $stmt->execute([$token, $expires, $vendor['id']]);

    Logger::info('Vendor login', ['vendor_id' => $vendor['id'], 'role' => $vendor['role']]);

    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'type' => 'vendor',
            'role' => $vendor['role'],
            'id' => $vendor['id'],
            'name' => $vendor['business_name'],
            'email' => $vendor['email'],
            'slug' => $vendor['slug'],
        ]
    ]);
    exit;
}

// 2. Buscar en users (compradores)
$stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR phone_whatsapp = ?)");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
    $stmt->execute([$token, $user['id']]);

    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token,
            'type' => 'buyer',
            'role' => 'buyer',
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ]
    ]);
    exit;
}

// Comprador sin contraseña — auto-crear si viene de reserva
$stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR phone_whatsapp = ?)");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && empty($user['password_hash'])) {
    Response::error('Esta cuenta no tiene contrasena. Registra una desde el enlace de recuperacion.', 403);
}

Response::error('Credenciales incorrectas', 401);
```

- [ ] **Step 3: Rewrite login.php for vendors + users**
- [ ] **Step 4: Add password_hash column to users** — `ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email;`

---

## Chunk 3: Scraping Inteligente — 1 scraping por lotería/fecha

### Task 3.1: Reescribir `cron/fetch_lottery_results.php`

**Files:**
- Modify: `cron/fetch_lottery_results.php`

Algoritmo: buscar combinaciones únicas de rifas activas que necesitan resultados, scrapear UNA SOLA VEZ por (lottery_id, draw_date).

```
Flujo:
1. SELECT DISTINCT r.lottery_id, DATE(r.draw_date) as target_date
   FROM raffles r
   WHERE r.status = 'active'
   AND DATE(r.draw_date) <= CURDATE()
   AND NOT EXISTS (
     SELECT 1 FROM lottery_results lr
     WHERE lr.lottery_id = r.lottery_id
     AND lr.draw_date = DATE(r.draw_date)
   )

2. Para cada combinación → 1 scraping

3. INSERT INTO lottery_results (lottery_id, draw_date, winning_number, scraped_at, scrape_source)
   VALUES (?, ?, ?, NOW(), 'colombia.com')
   ON DUPLICATE KEY UPDATE
     winning_number = VALUES(winning_number),
     scraped_at = NOW(),
     scrape_attempts = scrape_attempts + 1
```

- [ ] **Step 1: Rewrite fetch_lottery_results.php with smart dedup**
- [ ] **Step 2: Test with multiple raffles on same lottery/date** — crear 2 rifas de Boyacá para la misma fecha, verificar que solo hace 1 scraping

---

## Chunk 4: Evaluación de Ganadores por Rifa

### Task 4.1: Reescribir `cron/process_draws.php`

**Files:**
- Modify: `cron/process_draws.php`

Flujo correcto:
1. Buscar rifas `active` con `draw_date <= NOW()` que tengan resultado en `lottery_results`
2. Para CADA rifa (aislada):
   a. Obtener el número ganador de `lottery_results` (global por lotería/fecha)
   b. Aplicar `winning_mode` de ESA rifa (first_2, last_2, etc.)
   c. Buscar tickets `paid` de ESA rifa que coincidan
   d. Si hay ganador → registrar en `raffle_winners`, rifa → `completed`
   e. Si no hay ganador → `scheduleResorteo()`
3. Para CADA rifa procesada, construir mensajes en `message_queue`:
   - Ganador: "Felicitaciones, ganaste la rifa [X] con el número [Y]"
   - No ganadores pagados: "La rifa [X] ya tuvo sorteo. El número ganador fue [Y]. Sigue participando."
   - Vendedor: "Tu rifa [X] tuvo ganador: [nombre]. Contacta al comprador."

- [ ] **Step 1: Rewrite process_draws.php with per-raffle evaluation**
- [ ] **Step 2: Add message_queue insertion for all participants**
- [ ] **Step 3: Fix WhatsAppService::notifyWinner() argument order**
- [ ] **Step 4: Fix EmailService::notifyRaffleResults() argument count**

### Task 4.2: Crear `api/services/MessageBuilderService.php`

**Files:**
- Create: `api/services/MessageBuilderService.php`

Responsabilidad: tomar un evento (ganador, no-ganador, reserva, pago) y generar el mensaje final con variables reemplazadas. Los mensajes se guardan en `message_queue` YA RENDERIZADOS.

```php
class MessageBuilderService
{
    public static function buildWinnerMessage(array $raffle, array $ticket, array $winner, string $winningDigits): array
    {
        return [
            'channel' => 'whatsapp',
            'message_type' => 'winner',
            'subject' => 'Felicitaciones - Ganaste!',
            'body_text' => "Felicitaciones {nombre}! Ganaste la rifa *{raffle_name}* con el numero *{ticket_number}*. El numero ganador de la {lottery_name} fue *{winning_number}*. Te contactaremos pronto para la entrega del premio.",
            'variables' => [
                'nombre' => $winner['name'],
                'raffle_name' => $raffle['name'],
                'ticket_number' => str_pad($ticket['ticket_number'], 4, '0', STR_PAD_LEFT),
                'lottery_name' => $lottery['name'] ?? '',
                'winning_number' => $winningDigits,
            ],
        ];
    }
    // + buildNoWinnerMessage(), buildReservationMessage(), buildPaymentConfirmedMessage()
}
```

- [ ] **Step 5: Create MessageBuilderService with all message templates**
- [ ] **Step 6: Add template variable replacement method**

---

## Chunk 5: Worker de Cola de Notificaciones

### Task 5.1: Reescribir `cron/process_notifications.php`

**Files:**
- Modify: `cron/process_notifications.php`

Flujo correcto:
1. `SELECT * FROM message_queue WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts < 3 ORDER BY raffle_id, scheduled_at LIMIT 100`
2. Agrupar por `vendor_id` (para usar credenciales correctas de EvolutionAPI)
3. Para cada mensaje:
   - WhatsApp: cargar `wa_config` del vendor, enviar via EvolutionAPI
   - Email: enviar via Brevo o SMTP
4. Marcar como `sent` o `failed` con logging

- [ ] **Step 1: Rewrite process_notifications.php with message_queue**
- [ ] **Step 2: Fix WhatsAppService::sendTemplate() → use real methods**
- [ ] **Step 3: Add vendor credential loading per message**
- [ ] **Step 4: Add proper retry with exponential backoff**

### Task 5.2: Corregir `api/services/WhatsAppService.php`

**Files:**
- Modify: `api/services/WhatsAppService.php`

Cambios:
- Cargar credenciales desde `vendors.wa_config` en vez de `admin_users`
- Agregar método `sendMessage($vendorId, $phone, $text)` genérico
- Eliminar `sendTemplate()` fantasma

- [ ] **Step 5: Update WhatsAppService to use vendors table**
- [ ] **Step 6: Add generic sendMessage() method**

---

## Chunk 6: Cronología del Sistema (Cron Jobs)

### Task 6.1: Configurar crons con timing exacto

| Hora | Cron | Accion |
|------|------|--------|
| 04:00 AM | `fetch_lottery_results.php` | Scraping UNICO por lottery+date |
| 04:10 AM | `process_draws.php` | Evaluar TODAS las rifas de ese lottery+date |
| 04:20 AM | *(dentro de process_draws)* | Construir message_queue por rifa |
| 05:00 AM | `process_notifications.php` | Enviar mensajes masivos (WhatsApp + Email) |
| */15 min | `release_reservations.php` | Liberar reservas expiradas |
| 04:00 AM | `check_commissions.php` | Bloquear rifas sin comisión |

**Files:**
- Modify: `cron/fetch_lottery_results.php` — agregar check de hora
- Modify: `cron/process_draws.php` — agregar check de hora
- Modify: `cron/process_notifications.php` — agregar check de hora

- [ ] **Step 1: Add time-gating to each cron** — que solo corran en su ventana
- [ ] **Step 2: Create a master cron orchestrator** — `cron/master_daily.php` que ejecuta los 3 pasos en secuencia (scrape → evaluate → notify) con delays

---

## Chunk 7: API de Vendedores (Vendor API)

### Task 7.1: Crear `api/vendor/` endpoints

**Files:**
- Create: `api/vendor/dashboard.php` — stats del vendedor
- Create: `api/vendor/raffles.php` — CRUD de rifas del vendedor
- Create: `api/vendor/raffles/create.php` — crear rifa
- Create: `api/vendor/raffles/update.php` — actualizar rifa
- Create: `api/vendor/raffles/delete.php` — eliminar rifa
- Create: `api/vendor/payments.php` — gestionar pagos manuales
- Create: `api/vendor/profile.php` — perfil del vendedor
- Create: `api/vendor/settings.php` — config WA, modo de pago

Todos usan `Auth::requireVendor()` y filtran por `vendor_id`.

- [ ] **Step 1: Create api/vendor/dashboard.php**
- [ ] **Step 2: Create api/vendor/raffles.php (list)**
- [ ] **Step 3: Create api/vendor/raffles/create.php**
- [ ] **Step 4: Create api/vendor/payments.php**
- [ ] **Step 5: Create api/vendor/profile.php**
- [ ] **Step 6: Create api/vendor/settings.php**

### Task 7.2: Modificar `api/raffles/create.php` para usar vendor_id

**Files:**
- Modify: `api/raffles/create.php`

- [ ] **Step 7: Add vendor_id to raffle creation**
- [ ] **Step 8: Add vendor_id filter to raffle listing**

---

## Chunk 8: Frontend — Split del Admin Panel

### Task 8.1: Crear `public/vendor/` — Panel del Vendedor (reemplaza admin)

**Files:**
- Create: `public/vendor/index.php` — Login + dashboard del vendedor
- Create: `public/vendor/raffles.php` — Gestión de rifas
- Create: `public/vendor/raffle-create.php` — Crear rifa
- Create: `public/vendor/raffle-edit.php` — Editar rifa
- Create: `public/vendor/payments.php` — Gestión de pagos
- Create: `public/vendor/settings.php` — Configuración
- Create: `public/vendor/partials/header.php` — Header del vendor
- Create: `public/vendor/partials/sidebar.php` — Sidebar del vendor
- Create: `public/vendor/partials/footer.php` — Footer del vendor

- [ ] **Step 1: Create vendor panel layout (header + sidebar + footer)**
- [ ] **Step 2: Create vendor dashboard page**
- [ ] **Step 3: Create vendor raffles list page**
- [ ] **Step 4: Create vendor raffle create page**
- [ ] **Step 5: Create vendor payments page**
- [ ] **Step 6: Create vendor settings page**

### Task 8.2: Corregir pantalla oscura del admin actual

**Files:**
- Modify: `public/admin/index.php`

El problema probable: un overlay/modal de login que no se cierra correctamente, o un z-index que tapa todo. Solución:

- [ ] **Step 7: Debug the dark overlay issue** — buscar elementos con `position:fixed`, `z-index` alto, o clases `hidden` que no se aplican
- [ ] **Step 8: Fix the overlay z-index or remove blocking element**

### Task 8.3: Actualizar `public/dashboard.php` para compradores

**Files:**
- Modify: `public/dashboard.php`

- [ ] **Step 9: Fix dashboard.php to use users.auth_token**
- [ ] **Step 10: Add password registration flow for buyers without password**

---

## Chunk 9: Pagos — Manual + Centralizado

### Task 9.1: Modelo de pago manual por rifa

**Files:**
- Modify: `api/tickets/confirm-payment.php`

Cuando el vendedor aprueba un comprobante manualmente:
1. Verificar que el ticket pertenece a una rifa de ESE vendedor (`vendor_id`)
2. Cambiar estado a `paid`
3. Encolar mensaje de confirmación en `message_queue`

- [ ] **Step 1: Add vendor_id validation to confirm-payment.php**
- [ ] **Step 2: Add message_queue insertion on payment confirmed**

### Task 9.2: Modelo de pago centralizado (futuro)

En `vendors.payment_config` se almacenará `{"mode": "centralized", "bank_account": "..."}`.
El comprador paga a la plataforma y la plataforma dispersa al vendedor.

- [ ] **Step 3: Design centralized payment flow** (no implementar aún, solo dejar la interfaz)

---

## Chunk 10: Limpieza y Configuración

### Task 10.1: Consolidar .env loader

**Files:**
- Modify: `config/database.php` — usar un solo `loadEnv()`
- Modify: `config/app.php` — eliminar loadEnv duplicado
- Modify: `config/paths.php` — eliminar loadEnv duplicado
- Modify: `index.php` — eliminar loadEnv duplicado

- [ ] **Step 1: Create config/env.php as single env loader**
- [ ] **Step 2: Update all files to require env.php once**

### Task 10.2: Eliminar PUBLIC_PATH collision

**Files:**
- Modify: `config/paths.php` — rename to `PUBLIC_URL_PATH`
- Modify: `config/constants.php` — rename `PUBLIC_PATH` to `PUBLIC_DIR`

- [ ] **Step 3: Fix PUBLIC_PATH naming collision**
- [ ] **Step 4: Update all references**

### Task 10.3: Eliminar archivos muertos

- [ ] **Step 5: Remove dead code from fetch_lottery_results.php** (lines 94-174)
- [ ] **Step 6: Remove .bak files**
- [ ] **Step 7: Remove duplicate tapazo directories**
- [ ] **Step 8: Remove duplicate JS directories (keep js2/)**
- [ ] **Step 9: Remove zip archives from root**

---

## Cronología de Implementación (orden de ejecución)

| Fase | Semana | Tasks | Descripcion |
|------|--------|-------|-------------|
| **Fase 1** | Semana 1 | 1.1-1.7, 2.1-2.2 | Schema + Auth |
| **Fase 2** | Semana 1-2 | 3.1, 4.1-4.2, 5.1-5.2 | Scraping + Evaluación + Notificaciones |
| **Fase 3** | Semana 2 | 6.1 | Cronología |
| **Fase 4** | Semana 2-3 | 7.1-7.2 | API de Vendedores |
| **Fase 5** | Semana 3-4 | 8.1-8.3 | Frontend Vendor Panel |
| **Fase 6** | Semana 4 | 9.1-9.2 | Pagos |
| **Fase 7** | Semana 4 | 10.1-10.3 | Limpieza |

---

## Principio arquitectónico innegociable

```
El sistema:
  NO notifica por lotería
  NO notifica por vendedor
  SIEMPRE notifica por raffle_id

Aunque 100 rifas usen la misma lotería el mismo día:
  1 scraping → 100 evaluaciones → 100 colas de mensajes aisladas
```

Verificación: cada mensaje en `message_queue` tiene `raffle_id` OBLIGATORIO (NOT NULL). Es imposible cruzar mensajes entre rifas a nivel de schema.

---

## Archivos a crear/modificar (resumen)

### Nuevos:
- `database/migrations/v3.0_saas_redesign.sql`
- `api/services/MessageBuilderService.php`
- `api/vendor/dashboard.php`
- `api/vendor/raffles.php`
- `api/vendor/raffles/create.php`
- `api/vendor/raffles/update.php`
- `api/vendor/raffles/delete.php`
- `api/vendor/payments.php`
- `api/vendor/profile.php`
- `api/vendor/settings.php`
- `public/vendor/index.php`
- `public/vendor/raffles.php`
- `public/vendor/raffle-create.php`
- `public/vendor/raffle-edit.php`
- `public/vendor/payments.php`
- `public/vendor/settings.php`
- `public/vendor/partials/header.php`
- `public/vendor/partials/sidebar.php`
- `public/vendor/partials/footer.php`
- `cron/master_daily.php`
- `config/env.php`

### Modificar:
- `api/utils/Auth.php` — 3 roles separados
- `api/auth/login.php` — vendors + users
- `api/auth/register.php` — registro de vendors
- `cron/fetch_lottery_results.php` — scraping inteligente
- `cron/process_draws.php` — evaluación por rifa + message_queue
- `cron/process_notifications.php` — worker con message_queue
- `api/services/WhatsAppService.php` — usar vendors.wa_config
- `api/services/EmailService.php` — corregir argumentos
- `api/raffles/create.php` — agregar vendor_id
- `api/tickets/confirm-payment.php` — validar vendor_id
- `public/admin/index.php` — corregir pantalla oscura
- `public/dashboard.php` — fix auth flow
- `public/partials/header.php` — links actualizados
- `config/database.php` — usar env.php
- `config/app.php` — usar env.php
- `config/paths.php` — fix PUBLIC_PATH
- `config/constants.php` — fix PUBLIC_PATH
- `.htaccess` — agregar rutas vendor/
- `index.php` — agregar rutas vendor/

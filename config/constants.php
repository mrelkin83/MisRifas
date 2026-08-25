<?php
/**
 * Constantes de la Aplicación
 */

// Directorios
define('ROOT_PATH', dirname(__DIR__, 2));
define('BACKEND_PATH', ROOT_PATH . '/backend');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Estados de Rifas
define('RAFFLE_STATUS_DRAFT', 'draft');
define('RAFFLE_STATUS_ACTIVE', 'active');
define('RAFFLE_STATUS_BLOCKED', 'blocked');
define('RAFFLE_STATUS_COMPLETED', 'completed');
define('RAFFLE_STATUS_CANCELLED', 'cancelled');
define('RAFFLE_STATUS_DELETED', 'deleted');

// Estados de Boletos
define('TICKET_STATUS_AVAILABLE', 'available');
define('TICKET_STATUS_RESERVED', 'reserved');
define('TICKET_STATUS_PAID', 'paid');

// Estados de Pagos
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_CONFIRMED', 'confirmed');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

// Estados de Comisiones
define('COMMISSION_STATUS_PENDING', 'pending');
define('COMMISSION_STATUS_PAID', 'paid');
define('COMMISSION_STATUS_OVERDUE', 'overdue');
define('COMMISSION_STATUS_WAIVED', 'waived');

// Métodos de Pago
define('PAYMENT_METHOD_NEQUI', 'nequi');
define('PAYMENT_METHOD_BANCOLOMBIA', 'bancolombia');
define('PAYMENT_METHOD_DAVIPLATA', 'daviplata');
define('PAYMENT_METHOD_EFECTY', 'efecty');
define('PAYMENT_METHOD_MANUAL', 'manual');

// Modalidades de Ganar
define('WINNING_MODE_LAST_2', 'last_2');
define('WINNING_MODE_FIRST_2', 'first_2');
define('WINNING_MODE_LAST_3', 'last_3');
define('WINNING_MODE_FIRST_3', 'first_3');
define('WINNING_MODE_LAST_4', 'last_4');

// Tipos de Notificación
define('NOTIFICATION_TYPE_RESERVATION', 'reservation');
define('NOTIFICATION_TYPE_PURCHASE_CONFIRMED', 'purchase_confirmed');
define('NOTIFICATION_TYPE_PAYMENT_CONFIRMED', 'payment_confirmed');
define('NOTIFICATION_TYPE_PAYMENT_REMINDER', 'payment_reminder');
define('NOTIFICATION_TYPE_WINNER', 'winner');
define('NOTIFICATION_TYPE_REMINDER', 'reminder');
define('NOTIFICATION_TYPE_DRAW_REMINDER', 'draw_reminder');
define('NOTIFICATION_TYPE_COMMISSION_DUE', 'commission_due');
define('NOTIFICATION_TYPE_RAFFLE_BLOCKED', 'raffle_blocked');

// Canales de Notificación
define('NOTIFICATION_CHANNEL_WHATSAPP', 'whatsapp');
define('NOTIFICATION_CHANNEL_EMAIL', 'email');
define('NOTIFICATION_CHANNEL_SMS', 'sms');
define('NOTIFICATION_CHANNEL_PUSH', 'push');

// Estados de Notificación
define('NOTIFICATION_STATUS_PENDING', 'pending');
define('NOTIFICATION_STATUS_SENT', 'sent');
define('NOTIFICATION_STATUS_FAILED', 'failed');
define('NOTIFICATION_STATUS_BOUNCED', 'bounced');

// Configuración de Notificaciones
define('MAX_NOTIFICATION_ATTEMPTS', 3);

// Roles de Admin
define('ADMIN_ROLE_SUPER_ADMIN', 'super_admin');
define('ADMIN_ROLE_ADMIN', 'admin');
define('ADMIN_ROLE_MODERATOR', 'moderator');

// Días de la Semana
define('DAY_MONDAY', 'monday');
define('DAY_TUESDAY', 'tuesday');
define('DAY_WEDNESDAY', 'wednesday');
define('DAY_THURSDAY', 'thursday');
define('DAY_FRIDAY', 'friday');
define('DAY_SATURDAY', 'saturday');
define('DAY_SUNDAY', 'sunday');

// Comisiones
define('COMMISSION_DAYS_BEFORE_DRAW', 8);
define('DEFAULT_COMMISSION_PERCENTAGE', 1.0);

// Validación
define('MIN_DIGITS', 2);
define('MAX_DIGITS', 4);
define('ALLOWED_OPPORTUNITIES', [1, 2, 4, 5]);
define('ALLOWED_RESERVATION_HOURS', [2, 4, 6]);

// HTTP Status Codes
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_CONFLICT', 409);
define('HTTP_UNPROCESSABLE_ENTITY', 422);
define('HTTP_INTERNAL_SERVER_ERROR', 500);

// Regex Patterns
define('REGEX_PHONE_COLOMBIA', '/^(\+?57)?3\d{9}$/');
define('REGEX_EMAIL', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

// Ciudades Principales de Colombia
define('COLOMBIA_CITIES', [
    'Bogotá',
    'Medellín',
    'Cali',
    'Barranquilla',
    'Cartagena',
    'Cúcuta',
    'Bucaramanga',
    'Pereira',
    'Santa Marta',
    'Ibagué',
    'Pasto',
    'Manizales',
    'Neiva',
    'Villavicencio',
    'Armenia',
    'Valledupar',
    'Montería',
    'Popayán',
    'Sincelejo',
    'Tunja',
]);

// Códigos de Error
define('ERROR_DATABASE', 'DB_ERROR');
define('ERROR_VALIDATION', 'VALIDATION_ERROR');
define('ERROR_AUTHENTICATION', 'AUTH_ERROR');
define('ERROR_AUTHORIZATION', 'AUTHZ_ERROR');
define('ERROR_NOT_FOUND', 'NOT_FOUND');
define('ERROR_CONFLICT', 'CONFLICT');
define('ERROR_PAYMENT', 'PAYMENT_ERROR');
define('ERROR_EXTERNAL_API', 'EXTERNAL_API_ERROR');
define('ERROR_RATE_LIMIT', 'RATE_LIMIT_EXCEEDED');

// Mensajes de Error en Español
define('ERROR_MESSAGES', [
    ERROR_DATABASE => 'Error en la base de datos. Intente nuevamente.',
    ERROR_VALIDATION => 'Los datos proporcionados no son válidos.',
    ERROR_AUTHENTICATION => 'Credenciales inválidas.',
    ERROR_AUTHORIZATION => 'No tiene permisos para realizar esta acción.',
    ERROR_NOT_FOUND => 'El recurso solicitado no existe.',
    ERROR_CONFLICT => 'El recurso ya existe o está en conflicto.',
    ERROR_PAYMENT => 'Error al procesar el pago.',
    ERROR_EXTERNAL_API => 'Error al comunicarse con servicio externo.',
    ERROR_RATE_LIMIT => 'Demasiadas solicitudes. Intente más tarde.',
]);

// Timezone
date_default_timezone_set('America/Bogota');

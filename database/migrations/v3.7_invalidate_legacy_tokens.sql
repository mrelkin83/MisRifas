-- ============================================================
-- MIGRACION v3.7: Invalidar tokens de autenticacion sin expiracion
-- ============================================================
-- Hallazgo (auditoria Fase 2): el login/registro de compradores no seteaba
-- auth_token_expires (a diferencia de vendors), y Auth::requireBuyer /
-- requireLogin / me.php no comprobaban la expiracion. Resultado: los tokens
-- de comprador emitidos antes del fix quedaron con auth_token_expires = NULL
-- y son validos para siempre (un token filtrado seguiria sirviendo, incluso
-- tras desactivar la cuenta).
--
-- El codigo ya se corrigio (los tokens nuevos expiran a 30 dias y la
-- validacion exige active=1 AND expiracion), pero la clausula
-- "auth_token_expires IS NULL OR ... > NOW()" mantiene validos los tokens
-- legacy hasta el proximo login. Esta migracion los invalida de forma
-- decisiva: pone auth_token = NULL, forzando un nuevo login (que ya emite
-- un token con expiracion correcta).
--
-- Cubre compradores (users) y, defensivamente, cualquier vendor legacy en la
-- misma situacion. Idempotente: correrla de nuevo no afecta a nadie porque
-- todos los tokens vigentes ya tienen expiracion.
-- ============================================================

UPDATE `users`
SET `auth_token` = NULL,
    `auth_token_expires` = NULL
WHERE `auth_token` IS NOT NULL
  AND `auth_token_expires` IS NULL;

UPDATE `vendors`
SET `auth_token` = NULL,
    `auth_token_expires` = NULL
WHERE `auth_token` IS NOT NULL
  AND `auth_token_expires` IS NULL;

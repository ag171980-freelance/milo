-- Conexión de Mercado Pago con OAuth («Conectar con Mercado Pago»).
-- Para bases creadas antes de esta fecha. schema.sql ya lo incluye.
USE copiafacil;

ALTER TABLE tenant_settings
  ADD COLUMN mp_refresh_token_enc TEXT NULL AFTER mp_access_token_enc,
  ADD COLUMN mp_token_expires_at  DATETIME NULL AFTER mp_refresh_token_enc,
  ADD COLUMN mp_connected_via     ENUM('oauth','manual') NULL AFTER mp_token_expires_at;

UPDATE tenant_settings SET mp_connected_via = 'manual' WHERE mp_access_token_enc IS NOT NULL;

-- Solicitudes de demo y período de prueba de los locales.
-- Para bases creadas antes de esta fecha. schema.sql ya lo incluye.
USE copiafacil;

ALTER TABLE tenants ADD COLUMN trial_ends_at DATETIME NULL AFTER is_active;

CREATE TABLE IF NOT EXISTS demo_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  shop_name   VARCHAR(120) NOT NULL,
  address     VARCHAR(200) NOT NULL DEFAULT '',
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  email       VARCHAR(160) NOT NULL,
  message     VARCHAR(500) NOT NULL DEFAULT '',
  status      ENUM('nueva','contactada','activada','descartada') NOT NULL DEFAULT 'nueva',
  ip_hash     CHAR(64)     NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_demo_status (status, created_at)
) ENGINE=InnoDB;

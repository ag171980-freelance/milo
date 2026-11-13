-- Copiá Fácil · esquema completo
--
-- Una sola base para todos los locales. Toda tabla que pertenece a un
-- local lleva tenant_id y TODA consulta filtra por él.
--
-- Importar desde phpMyAdmin o con:
--   mysql -u root < backend/database/schema.sql

CREATE DATABASE IF NOT EXISTS copiafacil
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE copiafacil;

-- Locales -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tenants (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(64)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  address       VARCHAR(200) NOT NULL DEFAULT '',
  is_open       TINYINT(1)   NOT NULL DEFAULT 1,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  -- Fin de la semana de prueba. NULL: cuenta paga, sin vencimiento.
  trial_ends_at DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB;

-- Configuración del local: precios, datos legales y Mercado Pago.
-- Los precios van en DECIMAL y se leen a centavos en PHP (Support\Money).
CREATE TABLE IF NOT EXISTS tenant_settings (
  tenant_id            INT UNSIGNED PRIMARY KEY,
  price_bn_simple      DECIMAL(12,2) NOT NULL DEFAULT 150.00,
  price_bn_doble       DECIMAL(12,2) NOT NULL DEFAULT 120.00,
  price_color_simple   DECIMAL(12,2) NOT NULL DEFAULT 600.00,
  price_color_doble    DECIMAL(12,2) NOT NULL DEFAULT 550.00,
  price_abrochado      DECIMAL(12,2) NOT NULL DEFAULT 200.00,
  price_anillado       DECIMAL(12,2) NOT NULL DEFAULT 2500.00,
  offers_color         TINYINT(1)    NOT NULL DEFAULT 1,
  offers_oficio        TINYINT(1)    NOT NULL DEFAULT 1,
  offers_anillado      TINYINT(1)    NOT NULL DEFAULT 1,
  -- Datos que la Ley 24.240 pide mostrar a quien compra.
  legal_name           VARCHAR(160)  NOT NULL DEFAULT '',
  cuit                 VARCHAR(13)   NOT NULL DEFAULT '',
  contact_email        VARCHAR(160)  NOT NULL DEFAULT '',
  contact_phone        VARCHAR(40)   NOT NULL DEFAULT '',
  -- Mercado Pago. El token se guarda cifrado con APP_KEY (Support\Crypto):
  -- un volcado de la base sin el .env no sirve para cobrar ni devolver.
  mp_access_token_enc  TEXT          NULL,
  -- Con OAuth: el refresh token (cifrado) y cuándo vence el access token
  -- (180 días). tools/mantenimiento.php lo renueva antes de que venza.
  mp_refresh_token_enc TEXT          NULL,
  mp_token_expires_at  DATETIME      NULL,
  mp_connected_via     ENUM('oauth','manual') NULL,
  mp_user_id           VARCHAR(32)   NULL,
  mp_nickname          VARCHAR(120)  NULL,
  mp_is_test           TINYINT(1)    NOT NULL DEFAULT 0,
  mp_connected_at      DATETIME      NULL,
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Panel -------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id      INT UNSIGNED NOT NULL,
  email          VARCHAR(160) NOT NULL,
  name           VARCHAR(120) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_tenant (tenant_id),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- En la base queda el SHA-256 del token, nunca el token.
CREATE TABLE IF NOT EXISTS sessions (
  token_hash    CHAR(64)     PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  expires_at    DATETIME     NOT NULL,
  last_seen_at  DATETIME     NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_sessions_user (user_id),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pedidos -----------------------------------------------------------------

CREATE TABLE IF NOT EXISTS order_counters (
  tenant_id    INT UNSIGNED PRIMARY KEY,
  last_number  INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_counters_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id          INT UNSIGNED NOT NULL,
  -- Id público aleatorio: quien tenga el link ve el pedido sin login.
  public_id          CHAR(20)     NOT NULL,
  number             INT UNSIGNED NOT NULL,
  customer_name      VARCHAR(60)  NOT NULL,
  -- El archivo. file_path es relativo a STORAGE_DIR y se vacía al borrarlo.
  file_name          VARCHAR(200) NOT NULL,
  file_path          VARCHAR(255) NULL,
  file_mime          VARCHAR(100) NOT NULL,
  file_size          INT UNSIGNED NOT NULL,
  file_deleted_at    DATETIME     NULL,
  pages              SMALLINT UNSIGNED NOT NULL,
  pages_counted      TINYINT(1)   NOT NULL DEFAULT 0, -- 1: contadas por el servidor; 0: declaradas por el cliente
  copies             TINYINT UNSIGNED NOT NULL,
  color              ENUM('bn','color')                       NOT NULL,
  sides              ENUM('simple','doble')                   NOT NULL,
  paper              ENUM('A4','Oficio')                      NOT NULL DEFAULT 'A4',
  finish             ENUM('ninguna','abrochado','anillado')   NOT NULL DEFAULT 'ninguna',
  note               VARCHAR(200) NOT NULL DEFAULT '',
  -- Precios congelados al momento del pedido.
  unit_price         DECIMAL(12,2) NOT NULL,
  print_total        DECIMAL(12,2) NOT NULL,
  finish_total       DECIMAL(12,2) NOT NULL,
  total              DECIMAL(12,2) NOT NULL,
  currency           CHAR(3)       NOT NULL DEFAULT 'ARS',
  status             ENUM('pendiente_pago','nuevo','imprimiendo','listo','entregado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente_pago',
  payment_status     ENUM('pendiente','aprobado','rechazado','devuelto','devolucion_pendiente','devuelto_efectivo') NOT NULL DEFAULT 'pendiente',
  reject_reason      VARCHAR(200) NOT NULL DEFAULT '',
  terms_version      VARCHAR(20)  NOT NULL,
  terms_accepted_at  DATETIME     NOT NULL,
  ip_hash            CHAR(64)     NOT NULL DEFAULT '',
  paid_at            DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_orders_public (public_id),
  UNIQUE KEY uq_orders_number (tenant_id, number),
  KEY ix_orders_tenant_status (tenant_id, status, created_at),
  CONSTRAINT fk_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  status      VARCHAR(30)  NOT NULL,
  message     VARCHAR(300) NOT NULL,
  actor       ENUM('cliente','local','pasarela','sistema') NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_events_order (order_id),
  CONSTRAINT fk_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cada pago que informa la pasarela, una sola vez (clave única).
CREATE TABLE IF NOT EXISTS payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  provider      VARCHAR(20)  NOT NULL,
  provider_ref  VARCHAR(64)  NOT NULL,
  status        VARCHAR(20)  NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  payload       JSON         NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_ref (provider, provider_ref),
  KEY ix_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refunds (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  method        ENUM('mercadopago','efectivo','simulado') NOT NULL,
  provider_ref  VARCHAR(64)  NULL,
  amount        DECIMAL(12,2) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_refunds_order (order_id),
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Solicitudes del botón de arrepentimiento (Res. SCI 424/2020).
-- Se guardan todas, se hayan podido cumplir o no.
CREATE TABLE IF NOT EXISTS withdrawal_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        CHAR(10)     NOT NULL,
  order_id    INT UNSIGNED NULL,
  tenant_id   INT UNSIGNED NULL,
  contact     VARCHAR(160) NOT NULL DEFAULT '',
  detail      VARCHAR(500) NOT NULL DEFAULT '',
  outcome     ENUM('cancelado_y_devuelto','registrado') NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_withdrawal_code (code)
) ENGINE=InnoDB;

-- Límite de pedidos por IP. La IP no se guarda: va su hash con APP_KEY.
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket        CHAR(64)  PRIMARY KEY,
  window_start  DATETIME  NOT NULL,
  hits          INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

-- Comercios que piden la demo de una semana desde la página principal.
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

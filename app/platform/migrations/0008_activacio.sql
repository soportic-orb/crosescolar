-- El pagament per tenir el web publicat.
--
-- Donar-se d'alta i preparar el cros no costa res: el pagament arriba el dia
-- que el client vol que el seu web es vegi. És un pagament únic per instància
-- i el cobra la plataforma amb el seu Stripe, no el del client.
--
-- Compte de no confondre-ho amb el que cobra cada cros als seus participants:
-- allò va a la base de dades del client, amb les seves dades fiscals i la seva
-- numeració, i aquí no n'hi ha ni un rastre.

CREATE TABLE IF NOT EXISTS platform_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  token VARCHAR(64) NOT NULL,
  instance_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  slug VARCHAR(40) NULL,
  concept VARCHAR(30) NOT NULL DEFAULT 'activation',
  description VARCHAR(190) NOT NULL,
  payer_name VARCHAR(190) NOT NULL,
  payer_email VARCHAR(190) NOT NULL,
  payer_nif VARCHAR(30) NULL,
  payer_address VARCHAR(255) NULL,
  payer_postcode VARCHAR(20) NULL,
  payer_town VARCHAR(120) NULL,
  subtotal_cents INT NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  total_cents INT NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  stripe_session_id VARCHAR(255) NULL,
  stripe_payment_intent VARCHAR(255) NULL,
  detail VARCHAR(255) NULL,
  refunded_cents INT NOT NULL DEFAULT 0,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_payments_code (code),
  UNIQUE KEY uniq_platform_payments_token (token),
  KEY idx_platform_payments_instance (instance_id, status),
  KEY idx_platform_payments_session (stripe_session_id),
  KEY idx_platform_payments_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT UNSIGNED NOT NULL,
  series VARCHAR(10) NOT NULL DEFAULT 'A',
  number INT UNSIGNED NOT NULL,
  full_number VARCHAR(40) NOT NULL,
  issued_on DATE NOT NULL,
  issuer LONGTEXT NULL,
  customer_name VARCHAR(190) NOT NULL,
  customer_nif VARCHAR(30) NULL,
  customer_address VARCHAR(255) NULL,
  customer_email VARCHAR(190) NULL,
  subtotal_cents INT NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  total_cents INT NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_invoice_number (full_number),
  KEY idx_platform_invoice_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_invoice_counters (
  series VARCHAR(10) NOT NULL,
  year INT NOT NULL,
  next_number INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (series, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE instances ADD COLUMN activated_at DATETIME NULL;

-- Cobraments i facturació del cros.
--
-- Fins ara l'únic cobrament era el dels tiquets del punt de recàrrega i anava
-- directe a Stripe des del controlador. Ara hi ha una taula de pagaments que
-- val per a tot el que es cobra —tiquets i inscripcions—, la passarel·la és un
-- mòdul que es tria (Stripe, PayPal o el TPV de Redsys) i de cada cobrament
-- se'n pot emetre un rebut o una factura a nom de l'entitat organitzadora.

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  token VARCHAR(64) NOT NULL,
  -- Què es cobra: 'order' (tiquets) o 'registration' (inscripció).
  concept VARCHAR(20) NOT NULL DEFAULT 'order',
  reference_id INT UNSIGNED NULL,
  payer_name VARCHAR(190) NOT NULL,
  payer_email VARCHAR(190) NOT NULL,
  payer_phone VARCHAR(40) NULL,
  payer_nif VARCHAR(30) NULL,
  payer_address VARCHAR(255) NULL,
  subtotal_cents INT NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  total_cents INT NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
  gateway VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  -- Identificadors que dona la passarel·la: el de la sessió o comanda, i el
  -- del cobrament en si (payment_intent, captura o autorització).
  gateway_ref VARCHAR(190) NULL,
  gateway_payment VARCHAR(190) NULL,
  gateway_detail VARCHAR(255) NULL,
  refunded_cents INT NOT NULL DEFAULT 0,
  document_type VARCHAR(20) NULL,
  paid_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_payments_code (code),
  UNIQUE KEY uniq_payments_token (token),
  KEY idx_payments_status (status, created_at),
  KEY idx_payments_email (payer_email),
  KEY idx_payments_concept (concept, reference_id),
  KEY idx_payments_ref (gateway_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT UNSIGNED NOT NULL,
  description VARCHAR(190) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  unit_price_cents INT NOT NULL DEFAULT 0,
  subtotal_cents INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_payment_items (payment_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Els tipus d'inscripció: què val inscriure's i a qui s'aplica.
CREATE TABLE IF NOT EXISTS fee_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(400) NULL,
  price_cents INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_fee_types_order (active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rebuts i factures. El número no es calcula al vol: es desa, perquè un cop
-- emès un document ja no pot canviar mai més.
CREATE TABLE IF NOT EXISTS billing_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id INT UNSIGNED NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'receipt',
  series VARCHAR(10) NOT NULL DEFAULT 'R',
  number INT UNSIGNED NOT NULL,
  full_number VARCHAR(40) NOT NULL,
  issued_on DATE NOT NULL,
  -- Com estaven les dades fiscals de l'entitat el dia que es va emetre: si
  -- després canvien, el document que ja s'havia lliurat no s'ha de moure.
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
  UNIQUE KEY uniq_documents_number (full_number),
  KEY idx_documents_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El comptador de cada sèrie i any. Serveix per no deixar forats a la
-- numeració, que és el que primer mira qui revisa una comptabilitat.
CREATE TABLE IF NOT EXISTS billing_counters (
  series VARCHAR(10) NOT NULL,
  year INT NOT NULL,
  next_number INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (series, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les columnes noves de «orders» i «registrations» van a 0018, que sap
-- mirar si ja hi són: una migració es pot tornar a aplicar en restaurar una
-- còpia, i un ALTER pelat petaria.

-- Enviaments de correu a les persones inscrites.
CREATE TABLE IF NOT EXISTS mailings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject VARCHAR(190) NOT NULL,
  body MEDIUMTEXT NULL,
  audience VARCHAR(20) NOT NULL DEFAULT 'all',
  categories VARCHAR(255) NULL,
  reg_status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
  manual_emails TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  total INT NOT NULL DEFAULT 0,
  sent INT NOT NULL DEFAULT 0,
  failed INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_mailings_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mailing_recipients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mailing_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190) NULL,
  participants VARCHAR(500) NULL,
  bibs VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_mailing_email (mailing_id, email),
  KEY idx_mailing_pending (mailing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

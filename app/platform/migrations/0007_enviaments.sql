-- Enviaments de correu de la plataforma: als clients, a les administradores
-- dels webs o a llistes fetes a mà (una associació de mestres, per exemple,
-- que encara no és clienta de res).
--
-- És el mateix mecanisme que ja fan servir els cros per escriure a les famílies
-- —es prepara la llista de destinataris i s'envia per tandes— però amb la gent
-- de la plataforma i amb una plantilla pròpia.

CREATE TABLE IF NOT EXISTS mail_lists (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_lists_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(150) NULL,
  entity VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_contact_email (list_id, email),
  KEY idx_contacts_list (list_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_mailings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject VARCHAR(190) NOT NULL,
  body MEDIUMTEXT NULL,
  audience VARCHAR(20) NOT NULL DEFAULT 'clients',
  list_id INT UNSIGNED NULL,
  instance_status VARCHAR(20) NOT NULL DEFAULT 'active',
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
  KEY idx_platform_mailings_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_mailing_recipients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mailing_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190) NULL,
  entity VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error VARCHAR(500) NULL,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_mailing_email (mailing_id, email),
  KEY idx_platform_mailing_pending (mailing_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

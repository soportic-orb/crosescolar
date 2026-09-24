-- Base de dades de la plataforma: qui són els clients, quines instàncies
-- tenen i qui n'ha demanat una. Les dades de cada cros són a la seva pròpia
-- base de dades i aquí no n'hi ha cap.

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  nif VARCHAR(30) NULL,
  town VARCHAR(120) NULL,
  website VARCHAR(190) NULL,
  contact_name VARCHAR(150) NOT NULL,
  contact_role VARCHAR(120) NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NULL,
  notes TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_clients_email (contact_email),
  KEY idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id INT UNSIGNED NULL,
  slug VARCHAR(40) NOT NULL,
  site_name VARCHAR(190) NOT NULL,
  town VARCHAR(120) NULL,
  language VARCHAR(5) NOT NULL DEFAULT 'ca',
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  db_name VARCHAR(80) NOT NULL,
  db_user VARCHAR(80) NULL,
  admin_email VARCHAR(190) NULL,
  event_date DATE NULL,
  version VARCHAR(20) NULL,
  registrations INT UNSIGNED NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 0,
  listed TINYINT(1) NOT NULL DEFAULT 1,
  installed_at DATETIME NULL,
  synced_at DATETIME NULL,
  suspended_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  purge_at DATE NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_instances_slug (slug),
  KEY idx_instances_client (client_id),
  KEY idx_instances_status (status),
  KEY idx_instances_directory (published, listed, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instance_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  entity VARCHAR(190) NOT NULL,
  nif VARCHAR(30) NULL,
  town VARCHAR(120) NULL,
  website VARCHAR(190) NULL,
  contact_name VARCHAR(150) NOT NULL,
  contact_role VARCHAR(120) NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NULL,
  slug VARCHAR(40) NULL,
  language VARCHAR(5) NOT NULL DEFAULT 'ca',
  event_date DATE NULL,
  participants INT UNSIGNED NULL,
  referral VARCHAR(190) NULL,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  reason TEXT NULL,
  instance_id INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decided_by INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_requests_code (code),
  KEY idx_requests_status (status, created_at),
  KEY idx_requests_email (contact_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'superadmin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_activity (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  subject VARCHAR(40) NULL,
  subject_id INT UNSIGNED NULL,
  context TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_activity_when (created_at),
  KEY idx_activity_subject (subject, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

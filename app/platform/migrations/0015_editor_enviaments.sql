-- L'editor visual dels enviaments: el correu es fa amb blocs (doc, en JSON),
-- té versions, es pot programar per a un dia i una hora, i surt des del
-- domini que es tria. Les plantilles base són enviaments amb kind = 'template'.
-- Els enviaments fets abans es queden amb el seu cos en HTML (body).
ALTER TABLE platform_mailings ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'mail';
ALTER TABLE platform_mailings ADD COLUMN preheader VARCHAR(255) NULL;
ALTER TABLE platform_mailings ADD COLUMN doc MEDIUMTEXT NULL;
ALTER TABLE platform_mailings ADD COLUMN domain VARCHAR(190) NULL;
ALTER TABLE platform_mailings ADD COLUMN all_domains TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE platform_mailings ADD COLUMN scheduled_at DATETIME NULL;
ALTER TABLE platform_mailings ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE platform_mailings ADD COLUMN template_id INT UNSIGNED NULL;
ALTER TABLE platform_mailings ADD COLUMN template_name VARCHAR(190) NULL;
ALTER TABLE platform_mailings ADD COLUMN paused_reason VARCHAR(500) NULL;
ALTER TABLE platform_mailings ADD COLUMN updated_by INT UNSIGNED NULL;
ALTER TABLE platform_mailings ADD COLUMN archived_at DATETIME NULL;
ALTER TABLE platform_mailings ADD KEY idx_platform_mailings_due (kind, status, scheduled_at);

-- Cada «Desa» guarda una versió: es poden mirar i recuperar.
CREATE TABLE IF NOT EXISTS platform_mailing_versions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mailing_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  subject VARCHAR(190) NOT NULL,
  preheader VARCHAR(255) NULL,
  doc MEDIUMTEXT NULL,
  saved_by INT UNSIGNED NULL,
  saved_by_name VARCHAR(150) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_mailing_version (mailing_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les imatges pujades a l'editor (es desen a uploads/correus/).
CREATE TABLE IF NOT EXISTS platform_mailing_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(40) NOT NULL,
  file VARCHAR(190) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  original_name VARCHAR(190) NULL,
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_mailing_image (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qui s'ha donat de baixa dels enviaments: no en rep cap més, sigui quina
-- sigui la llista o l'audiència.
CREATE TABLE IF NOT EXISTS platform_unsubscribes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  domain VARCHAR(190) NULL,
  mailing_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_unsubscribe (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

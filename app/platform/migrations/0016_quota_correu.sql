-- Quants correus massius ha enviat cada domini, i qui: el panell o el web
-- d'un client. Tots surten pel mateix servidor de correu del domini, i el
-- límit per hora que admet el proveïdor és de tots plegats.
CREATE TABLE IF NOT EXISTS platform_mail_quota (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain VARCHAR(190) NOT NULL,
  source VARCHAR(60) NOT NULL,
  count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_platform_mail_quota (domain, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

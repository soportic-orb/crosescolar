-- Els missatges que arriben pel formulari de contacte de les pàgines públiques
-- de la plataforma (esportweb.cat, crosescolar.cat…). Es llegeixen al Panell de
-- Superadministració, a l'apartat «Contacte».
CREATE TABLE IF NOT EXISTS platform_contacts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain VARCHAR(190) NULL,
  name VARCHAR(150) NOT NULL,
  entity VARCHAR(190) NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NULL,
  message TEXT NOT NULL,
  privacy_at DATETIME NOT NULL,
  news TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  ip VARCHAR(45) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_platform_contacts_status (status, created_at),
  KEY idx_platform_contacts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

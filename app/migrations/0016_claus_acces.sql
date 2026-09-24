-- Enllaços d'accés d'un sol ús per al panell: serveixen per donar les claus a
-- qui gestionarà un cros nou, per recuperar-les si es perden i perquè qui
-- manté la plataforma hi pugui entrar a donar suport deixant-ne constància.

CREATE TABLE IF NOT EXISTS login_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  purpose VARCHAR(20) NOT NULL DEFAULT 'reset',
  note VARCHAR(190) NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  used_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_login_links_token (token_hash),
  KEY idx_login_links_user (user_id, created_at),
  KEY idx_login_links_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

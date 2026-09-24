-- La plataforma passa a tenir configuració pròpia (nom, logotip, colors,
-- correu, vigilància i actualitzacions), desada igual que la d'un cros.
-- El que continua al fitxer tenants/platform.php són els dominis i les bases
-- de dades: coses que han de funcionar abans que hi hagi res a punt.

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) NOT NULL,
  v LONGTEXT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

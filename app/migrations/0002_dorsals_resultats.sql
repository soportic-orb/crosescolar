-- Dorsals dels participants i resultats de la cursa
ALTER TABLE registrations ADD COLUMN bib_number INT NULL;

ALTER TABLE registrations ADD COLUMN token VARCHAR(64) NULL;

CREATE UNIQUE INDEX uniq_registrations_bib ON registrations (bib_number);

CREATE UNIQUE INDEX uniq_registrations_token ON registrations (token);

CREATE TABLE IF NOT EXISTS results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  position INT NOT NULL DEFAULT 0,
  arrival_seq INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'finished',
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_results_registration (registration_id),
  KEY idx_results_category (category_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

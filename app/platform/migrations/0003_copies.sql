-- Còpies de seguretat: quan es va fer l'última de cada instància i què ocupa.

ALTER TABLE instances ADD COLUMN backup_at DATETIME NULL AFTER health_since;
ALTER TABLE instances ADD COLUMN backup_size BIGINT UNSIGNED NULL AFTER backup_at;

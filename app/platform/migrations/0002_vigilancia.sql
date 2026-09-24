-- Vigilància de les instàncies: si el web d'un client deixa de respondre, val
-- més assabentar-se'n abans que no ens ho digui ell.

ALTER TABLE instances ADD COLUMN health VARCHAR(20) NULL AFTER version;
ALTER TABLE instances ADD COLUMN health_error VARCHAR(255) NULL AFTER health;
ALTER TABLE instances ADD COLUMN health_checked_at DATETIME NULL AFTER health_error;
ALTER TABLE instances ADD COLUMN health_since DATETIME NULL AFTER health_checked_at;

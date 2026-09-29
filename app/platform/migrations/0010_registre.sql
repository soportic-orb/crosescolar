-- El registre lliure: qui s'ha donat d'alta tot sol i si ha validat el correu.
ALTER TABLE instances ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'console';
ALTER TABLE instances ADD COLUMN verified_at DATETIME NULL;

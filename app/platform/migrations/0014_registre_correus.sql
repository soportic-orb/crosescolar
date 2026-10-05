-- Els correus que envia la plataforma: la benvinguda d'un web nou, els
-- enllaços d'accés, els avisos de suport i de contacte. Es fa servir la mateixa
-- taula que té cada web, i el Mailer ja hi escriu tot sol: fins ara, a la
-- plataforma, no hi havia on, i un correu que no sortia no deixava cap rastre.
CREATE TABLE IF NOT EXISTS email_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sent',
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_email_created (created_at),
  KEY idx_email_recipient (recipient, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

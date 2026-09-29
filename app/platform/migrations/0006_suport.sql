-- El servei de suport: els clients obren tiquets des del seu panell i la
-- plataforma els contesta des del seu.
--
-- Els tiquets viuen aquí i no a la base de dades de cada client a posta: qui
-- els ha d'atendre els vol tots en una safata, i no anar-los a buscar web per
-- web. El web d'un client hi escriu obrint la connexió de la plataforma, que
-- és la mateixa via que ja fa servir el panell per mirar dins d'una instància.

CREATE TABLE IF NOT EXISTS support_departments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_departments_order (active, sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(20) NOT NULL,
  department_id INT UNSIGNED NULL,
  instance_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NULL,
  slug VARCHAR(40) NULL,
  site_name VARCHAR(190) NULL,
  subject VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  priority VARCHAR(10) NOT NULL DEFAULT 'normal',
  author_name VARCHAR(150) NULL,
  author_email VARCHAR(190) NOT NULL,
  messages INT UNSIGNED NOT NULL DEFAULT 0,
  last_sender VARCHAR(10) NOT NULL DEFAULT 'client',
  last_message_at DATETIME NULL,
  -- Quan el client ha llegit l'última resposta, per posar-li el distintiu al
  -- seu panell sense haver de comptar missatges cada vegada.
  client_read_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tickets_reference (reference),
  KEY idx_tickets_status (status, last_message_at),
  KEY idx_tickets_instance (instance_id, status),
  KEY idx_tickets_department (department_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id INT UNSIGNED NOT NULL,
  sender VARCHAR(10) NOT NULL DEFAULT 'client',
  author_name VARCHAR(150) NULL,
  author_email VARCHAR(190) NULL,
  body MEDIUMTEXT NOT NULL,
  -- Una nota que només veu qui atén el tiquet: el client no la rep mai.
  internal TINYINT(1) NOT NULL DEFAULT 0,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_messages_ticket (ticket_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un departament de partida, perquè el primer tiquet no hagi d'esperar que
-- algú en creï un.
INSERT IGNORE INTO support_departments (id, name, description, sort_order, active, created_at)
VALUES (1, 'Suport general', 'Dubtes sobre el web, les inscripcions i el dia de la cursa.', 10, 1, NOW());

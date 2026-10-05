-- La imatge de fons de la portada de cada web, per ensenyar-la al llistat de
-- curses de la plataforma sense haver d'obrir la base de dades de cadascú a
-- cada visita. És el camí del fitxer dins de la carpeta uploads del client.
ALTER TABLE instances ADD COLUMN hero_image VARCHAR(255) NULL;

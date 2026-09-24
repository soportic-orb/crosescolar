-- La plataforma pot tenir més d'un domini (.cat i .com): cada client tria on
-- vol el seu web i cada sol·licitud diu quin demana.

ALTER TABLE instances ADD COLUMN domain VARCHAR(190) NULL AFTER slug;
ALTER TABLE instance_requests ADD COLUMN domain VARCHAR(190) NULL AFTER slug;

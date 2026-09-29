-- La retenció d'IRPF als pagaments de la plataforma.
--
-- Fins ara el preu s'entenia amb l'impost inclòs i només hi havia un tipus.
-- Ara el preu configurat és la base imposable, l'IVA se suma i l'IRPF es
-- resta: és una retenció que el client no ens paga a nosaltres sinó a Hisenda
-- en nom nostre, de manera que el que es cobra amb targeta és més petit del
-- que diu la factura de base + IVA.

ALTER TABLE platform_payments ADD COLUMN irpf_rate DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE platform_payments ADD COLUMN irpf_cents INT NOT NULL DEFAULT 0;
ALTER TABLE platform_invoices ADD COLUMN irpf_rate DECIMAL(5,2) NOT NULL DEFAULT 0;
ALTER TABLE platform_invoices ADD COLUMN irpf_cents INT NOT NULL DEFAULT 0;

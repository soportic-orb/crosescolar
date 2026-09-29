-- Quina mena de client és cadascú.
--
-- Serveix per decidir la retenció d'IRPF: qui reté és qui paga, i només
-- retenen les persones jurídiques (entitats, clubs, AFA, empreses) i els
-- professionals. A un particular no se li reté mai, de manera que la seva
-- factura és només base + IVA.
--
-- Els que ja hi són neixen com a entitat, que és el que s'ha estat aplicant
-- fins ara i el que són gairebé tots: escoles, AFA i clubs.
ALTER TABLE clients ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'company';

-- I a cada cobrament s'hi apunta quina mena de client era en aquell moment,
-- que una factura emesa no ha de canviar mai encara que després el client es
-- corregeixi la fitxa.
ALTER TABLE platform_payments ADD COLUMN payer_kind VARCHAR(20) NOT NULL DEFAULT 'company';

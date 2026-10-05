<?php
/**
 * Els webs d'una plataforma envien pel servidor de correu d'ella.
 *
 * Fins ara un web nou naixia enviant amb la funció mail() del servidor i
 * sense cap SMTP posat, i en molts servidors això vol dir que els correus del
 * web (l'enllaç per entrar, les confirmacions d'inscripció…) no arriben o van
 * a parar al correu brossa. Ara n'hi ha prou de fer servir el de la
 * plataforma, que és el que ja envia bé.
 *
 * Només es canvia qui encara té el que hi havia de sèrie: mail() i cap
 * servidor SMTP escrit. Qui s'hagi posat el seu servidor el conserva, i una
 * instal·lació que no és en cap plataforma no en té cap altre i es queda com
 * estava. Es pot tornar a aplicar sense fer mal: la segona vegada ja no troba
 * res per canviar.
 */

use Cros\Core\Db;

return static function (PDO $pdo): void {
    if (!class_exists(\Cros\Platform\Bridge::class) || !\Cros\Platform\Bridge::available()) {
        return;
    }
    $transport = (string) Db::val("SELECT v FROM settings WHERE k = 'mail_transport'", [], '');
    $host = trim((string) Db::val("SELECT v FROM settings WHERE k = 'smtp_host'", [], ''));
    if (!in_array($transport, ['', 'mail'], true) || $host !== '') {
        return;
    }
    $updated = Db::q("UPDATE settings SET v = 'platform' WHERE k = 'mail_transport'")->rowCount();
    if ($updated === 0) {
        Db::insert('settings', ['k' => 'mail_transport', 'v' => 'platform', 'updated_at' => date('Y-m-d H:i:s')]);
    }
};

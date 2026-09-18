<?php
/**
 * El web públic passa a ser informatiu per a l'esmorzar i el botó principal
 * porta a les inscripcions. Només es canvien els textos que encara tenien el
 * valor per defecte de les versions anteriors.
 */

use Cros\Core\Settings;

return static function (PDO $pdo): void {
    $replacements = [
        'hero_cta_label' => ['Compra els tiquets de l\'esmorzar', 'Inscripcions al cros'],
        'hero_cta_url' => ['/esmorzar', '/inscripcio'],
    ];
    foreach ($replacements as $key => [$old, $new]) {
        $current = (string) Settings::get($key, '');
        if ($current === '' || $current === $old) {
            Settings::set($key, $new);
        }
    }
    // El text d'informació pràctica ja no parla de codis QR comprats en línia.
    $oldInfo = '<ul><li>Els tiquets es poden recollir el mateix dia a la carpa de l\'AFA.</li>'
        . '<li>Cal presentar el codi QR des del mòbil o imprès.</li>'
        . '<li>Hi haurà opcions vegetarianes i sense gluten.</li></ul>';
    if ((string) Settings::get('tickets_info', '') === $oldInfo) {
        Settings::set('tickets_info', '<ul><li>Els tiquets es venen el mateix dia a la carpa de l\'AFA.</li>'
            . '<li>Es poden pagar en efectiu o amb targeta.</li>'
            . '<li>Hi haurà opcions vegetarianes i sense gluten.</li></ul>');
    }

    // Per defecte, el web només informa de l'esmorzar.
    if ((string) Settings::get('tickets_public_mode', '') === '') {
        Settings::set('tickets_public_mode', 'info');
    }
};

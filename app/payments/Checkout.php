<?php
declare(strict_types=1);

namespace Cros\Payments;

use Cros\Models\Payment;
use RuntimeException;

/**
 * El camí d'un cobrament: començar-lo, tornar-ne i confirmar-lo.
 *
 * Aquí no hi ha res de cap passarel·la concreta. Això és a posta: el dia que
 * se'n canviï una, o se n'afegeixi una altra, aquest fitxer no s'ha de tocar.
 */
class Checkout
{
    /** Comença el cobrament amb la passarel·la que hi hagi activa. */
    public static function begin(array $payment): array
    {
        $key = Gateways::active();
        if ($key === null) {
            throw new RuntimeException('Ara mateix no es pot pagar en línia. Poseu-vos en contacte amb l\'organització.');
        }
        $module = Gateways::module($key);
        $instruction = $module::begin($payment, Payment::items((int) $payment['id']));
        Payment::attach((int) $payment['id'], $key, (string) ($instruction['ref'] ?? ''));

        if (($instruction['mode'] ?? '') === 'redirect' && trim((string) ($instruction['url'] ?? '')) === '') {
            throw new RuntimeException('La passarel·la no ha dit on s\'ha d\'anar a pagar.');
        }

        return $instruction + ['mode' => 'redirect', 'url' => '', 'fields' => [], 'ref' => ''];
    }

    /**
     * Mira com ha acabat i ho desa.
     *
     * @param array<string,mixed> $input  el que ha arribat pel navegador o per l'avís del servidor
     * @return array{status:string,payment:array<string,mixed>}
     */
    public static function finish(array $payment, array $input): array
    {
        $key = (string) ($payment['gateway'] ?? '') ?: (string) Gateways::active();
        if ($key === '' || !isset(Gateways::MODULES[$key])) {
            return ['status' => 'pending', 'payment' => $payment];
        }
        $result = Gateways::module($key)::check($payment, $input);

        if ($result['status'] === 'paid') {
            return ['status' => 'paid', 'payment' => Payment::markPaid($payment, $result['payment'], $result['detail'])];
        }
        if ($result['status'] === 'failed') {
            Payment::markFailed($payment, $result['detail']);
        }

        return ['status' => $result['status'], 'payment' => Payment::find((int) $payment['id']) ?? $payment];
    }

    /** On torna el navegador quan s'ha pagat. */
    public static function returnUrl(array $payment): string
    {
        return url('/pagament/' . $payment['token'] . '/tornada');
    }

    /** On torna si s'ha fet enrere. */
    public static function cancelUrl(array $payment): string
    {
        return url('/pagament/' . $payment['token'] . '/anullat');
    }

    /** On avisa el servidor de la passarel·la, sense navegador pel mig. */
    public static function notifyUrl(string $gateway): string
    {
        return url('/pagament/avis/' . $gateway);
    }

    /** Com se'n diu del que s'està cobrant. */
    public static function concept(array $payment): string
    {
        $site = (string) setting('site_name', 'Cros Escolar');
        $what = (string) $payment['concept'] === 'registration'
            ? 'Inscripció'
            : 'Tiquets del punt de recàrrega';

        return $what . ' · ' . $site;
    }
}

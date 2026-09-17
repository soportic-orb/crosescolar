<?php
declare(strict_types=1);

namespace Cros\Core;

/** Excepció que es tradueix en una resposta HTTP d'error. */
class HttpException extends \RuntimeException
{
    public function __construct(private int $status = 404, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Sol·licitud incorrecta',
            403 => 'Accés no autoritzat',
            404 => 'Pàgina no trobada',
            419 => 'La sessió ha caducat',
            429 => 'Massa intents, torneu-ho a provar més tard',
            500 => 'Error intern del servidor',
            default => 'Error',
        };
    }
}

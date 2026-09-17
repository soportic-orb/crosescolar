<?php
declare(strict_types=1);

namespace Cros\Core;

/** Converteix excepcions en pàgines d'error llegibles. */
class ErrorHandler
{
    public static function handle(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status() : 500;
        $isHttp = $e instanceof HttpException;

        if (!$isHttp) {
            log_line('error', $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())[0] ?? '',
            ]);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . $status . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        $isAdmin = str_starts_with(Router::currentPath(), '/admin');
        $message = $isHttp ? $e->getMessage() : HttpException::defaultMessage(500);
        $details = (!$isHttp && config('debug', false))
            ? $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
            : '';

        try {
            View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'details' => $details,
                'title' => 'Error ' . $status,
            ], $isAdmin ? 'layouts/minimal' : 'layouts/public');
        } catch (\Throwable $inner) {
            echo '<!doctype html><meta charset="utf-8"><title>Error ' . $status . '</title>'
                . '<div style="font-family:system-ui;margin:4rem auto;max-width:36rem;text-align:center">'
                . '<h1>Error ' . $status . '</h1><p>' . e($message) . '</p>'
                . ($details !== '' ? '<pre style="text-align:left;white-space:pre-wrap">' . e($details) . '</pre>' : '')
                . '</div>';
        }
        exit;
    }
}

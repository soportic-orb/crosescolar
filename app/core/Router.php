<?php
declare(strict_types=1);

namespace Cros\Core;

/** Encaminador senzill amb suport per a paràmetres {nom}. */
class Router
{
    private array $routes = [];

    public function get(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '/' . trim($pattern, '/'),
            'handler' => $handler,
        ];
    }

    /** Camí sol·licitat, normalitzat i relatiu a la carpeta d'instal·lació. */
    public static function currentPath(): string
    {
        if (isset($_GET['_p']) && is_string($_GET['_p'])) {
            return '/' . trim($_GET['_p'], '/');
        }
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    /** Resol i executa la ruta corresponent. */
    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $allowed = [];
        foreach ($this->routes as $route) {
            $regex = $this->toRegex($route['pattern']);
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }
            $this->call($route['handler'], $params);
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(400, 'Mètode no permès per a aquesta adreça.');
        }
        throw new HttpException(404);
    }

    private function toRegex(string $pattern): string
    {
        $regex = preg_replace_callback('/\{([a-z_][a-z0-9_]*)(:([^}]+))?\}/i', function (array $m): string {
            $name = $m[1];
            $expr = $m[3] ?? '[^/]+';
            return '(?P<' . $name . '>' . $expr . ')';
        }, $pattern);
        return '#^' . $regex . '$#u';
    }

    private function call($handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler($params);
            return;
        }
        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method($params);
    }
}

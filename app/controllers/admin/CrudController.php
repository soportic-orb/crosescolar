<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Html;
use Cros\Core\Uploader;
use Cros\Models\Content;

/**
 * Gestió genèrica dels continguts definits a app/resources.php
 * (patrocinadors, recorreguts, categories, premis, galeria...).
 */
class CrudController extends Controller
{
    /** Definició d'un recurs. */
    private function resource(string $key): array
    {
        static $resources = null;
        if ($resources === null) {
            $resources = require CROS_APP . '/resources.php';
        }
        if (!isset($resources[$key])) {
            abort(404, 'Aquest contingut no existeix.');
        }
        return $resources[$key] + ['key' => $key];
    }

    public function index(array $params): void
    {
        Auth::requireLogin();
        $resource = $this->resource((string) $params['resource']);
        $search = trim((string) input('q'));

        $sql = 'SELECT * FROM `' . $resource['table'] . '`';
        $bindings = [];
        if ($search !== '' && !empty($resource['search'])) {
            $conditions = [];
            foreach ($resource['search'] as $index => $column) {
                $conditions[] = '`' . $column . '` LIKE :s' . $index;
                $bindings['s' . $index] = '%' . $search . '%';
            }
            $sql .= ' WHERE (' . implode(' OR ', $conditions) . ')';
        }
        $sql .= ' ORDER BY ' . ($resource['order'] ?? 'id DESC');
        $rows = Db::all($sql, $bindings);

        if ($resource['table'] === 'ticket_types') {
            foreach ($rows as &$row) {
                $row['sold'] = \Cros\Models\TicketType::sold((int) $row['id']);
            }
            unset($row);
        }

        $this->adminView('crud/index', [
            'title' => $resource['title'],
            'resource' => $resource,
            'rows' => $rows,
            'search' => $search,
        ]);
    }

    public function create(array $params): void
    {
        Auth::requireLogin();
        $resource = $this->resource((string) $params['resource']);
        $this->adminView('crud/form', [
            'title' => 'Afegir ' . $resource['singular'],
            'resource' => $resource,
            'row' => $this->defaults($resource),
            'errors' => [],
            'isNew' => true,
        ]);
    }

    public function store(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $resource = $this->resource((string) $params['resource']);
        [$data, $errors] = $this->collect($resource, null);

        if ($errors) {
            flash('error', 'Reviseu els camps marcats.');
            $this->adminView('crud/form', [
                'title' => 'Afegir ' . $resource['singular'],
                'resource' => $resource,
                'row' => array_merge($this->defaults($resource), $data, $_POST),
                'errors' => $errors,
                'isNew' => true,
            ]);
            return;
        }

        if ($this->hasColumn($resource, 'created_at')) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        $id = Db::insert($resource['table'], $data);
        $this->saveRelations($resource, $id);
        Auth::logActivity('create', $resource['table'], $id);
        flash('success', ucfirst($resource['singular']) . ' afegit correctament.');
        redirect('/admin/contingut/' . $resource['key']);
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();
        $resource = $this->resource((string) $params['resource']);
        $row = $this->withRelations($resource, $this->findRow($resource, (int) $params['id']));
        $this->adminView('crud/form', [
            'title' => 'Editar ' . $resource['singular'],
            'resource' => $resource,
            'row' => $row,
            'errors' => [],
            'isNew' => false,
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $resource = $this->resource((string) $params['resource']);
        $row = $this->findRow($resource, (int) $params['id']);
        [$data, $errors] = $this->collect($resource, $row);

        if ($errors) {
            flash('error', 'Reviseu els camps marcats.');
            $this->adminView('crud/form', [
                'title' => 'Editar ' . $resource['singular'],
                'resource' => $resource,
                'row' => array_merge($row, $data),
                'errors' => $errors,
                'isNew' => false,
            ]);
            return;
        }

        Db::update($resource['table'], $data, 'id = :id', ['id' => $row['id']]);
        $this->saveRelations($resource, (int) $row['id']);
        Auth::logActivity('update', $resource['table'], (int) $row['id']);
        flash('success', 'Canvis desats.');
        redirect('/admin/contingut/' . $resource['key'] . '/' . $row['id']);
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $resource = $this->resource((string) $params['resource']);
        $row = $this->findRow($resource, (int) $params['id']);

        foreach ($resource['fields'] as $name => $field) {
            if (in_array($field['type'] ?? '', ['image', 'file'], true) && !empty($row[$name])) {
                Uploader::delete((string) $row[$name]);
            }
        }
        Db::delete($resource['table'], 'id = :id', ['id' => $row['id']]);
        Auth::logActivity('delete', $resource['table'], (int) $row['id']);
        flash('success', ucfirst($resource['singular']) . ' esborrat.');
        redirect('/admin/contingut/' . $resource['key']);
    }

    public function duplicate(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $resource = $this->resource((string) $params['resource']);
        $row = $this->findRow($resource, (int) $params['id']);
        unset($row['id']);

        foreach ($resource['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'slug' && isset($row[$name])) {
                $row[$name] = $this->uniqueSlug($resource, $row[$name] . '-copia', null);
            }
        }
        foreach (['name', 'title', 'question'] as $labelField) {
            if (array_key_exists($labelField, $row)) {
                $row[$labelField] = mb_substr($row[$labelField] . ' (còpia)', 0, 150);
                break;
            }
        }
        if ($this->hasColumn($resource, 'created_at')) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        $original = (int) ($params['id'] ?? 0);
        $id = Db::insert($resource['table'], $row);
        $this->copyRelations($resource, $original, $id);
        flash('success', 'S\'ha creat una còpia.');
        redirect('/admin/contingut/' . $resource['key'] . '/' . $id);
    }

    /** Afegeix a la fila els recorreguts desats, per dibuixar el formulari. */
    private function withRelations(array $resource, array $row): array
    {
        foreach ($resource['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'course_laps') {
                $row[$name] = Content::categoryCourses((int) $row['id'])[(int) $row['id']] ?? [];
            }
        }
        return $row;
    }

    /** Desa els recorreguts i les voltes que ha enviat el formulari. */
    private function saveRelations(array $resource, int $id): void
    {
        foreach ($resource['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'course_laps') {
                Content::saveCategoryCourses(
                    $id,
                    (array) ($_POST[$name] ?? []),
                    (array) ($_POST[$name . '_laps'] ?? [])
                );
            }
        }
    }

    /** En duplicar, la còpia es queda els mateixos recorreguts. */
    private function copyRelations(array $resource, int $from, int $to): void
    {
        foreach ($resource['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'course_laps' || $from <= 0) {
                continue;
            }
            $courses = Content::categoryCourses($from)[$from] ?? [];
            Content::saveCategoryCourses(
                $to,
                array_column($courses, 'course_id'),
                array_column($courses, 'laps')
            );
        }
    }

    /** Desa l'ordre dels elements (arrossegar i deixar anar). */
    public function reorder(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $resource = $this->resource((string) $params['resource']);
        $order = (array) ($_POST['order'] ?? []);
        foreach (array_values($order) as $position => $id) {
            Db::update($resource['table'], ['sort_order' => $position + 1], 'id = :id', ['id' => (int) $id]);
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            json_out(['ok' => true]);
        }
        flash('success', 'Ordre actualitzat.');
        redirect('/admin/contingut/' . $resource['key']);
    }

    /* ------------------------------------------------------------------ */

    private function findRow(array $resource, int $id): array
    {
        $row = Db::one('SELECT * FROM `' . $resource['table'] . '` WHERE id = :id', ['id' => $id]);
        if (!$row) {
            abort(404, 'Element no trobat.');
        }
        return $row;
    }

    private function defaults(array $resource): array
    {
        $row = ['id' => 0];
        foreach ($resource['fields'] as $name => $field) {
            $row[$name] = $field['default'] ?? '';
        }
        return $row;
    }

    private function hasColumn(array $resource, string $column): bool
    {
        static $cache = [];
        $table = $resource['table'];
        if (!isset($cache[$table])) {
            $cache[$table] = [];
            try {
                foreach (Db::all('SELECT * FROM `' . $table . '` LIMIT 1') as $row) {
                    $cache[$table] = array_keys($row);
                }
                if (!$cache[$table]) {
                    $stmt = Db::q('SELECT * FROM `' . $table . '` LIMIT 0');
                    for ($i = 0; $i < $stmt->columnCount(); $i++) {
                        $cache[$table][] = (string) ($stmt->getColumnMeta($i)['name'] ?? '');
                    }
                }
            } catch (\Throwable $e) {
                $cache[$table] = [];
            }
        }
        return in_array($column, $cache[$table], true);
    }

    /**
     * Recull i valida les dades del formulari.
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function collect(array $resource, ?array $existing): array
    {
        $data = [];
        $errors = [];

        foreach ($resource['fields'] as $name => $field) {
            $type = $field['type'] ?? 'text';
            $rules = $field['rules'] ?? '';

            if (in_array($type, ['image', 'file'], true)) {
                $current = (string) ($existing[$name] ?? '');
                if (input_bool($name . '_remove') === 1 && $current !== '') {
                    Uploader::delete($current);
                    $data[$name] = null;
                    $current = '';
                }
                if (Uploader::has($name)) {
                    try {
                        $path = $type === 'image'
                            ? Uploader::image($_FILES[$name], $field['folder'] ?? 'media', (int) ($field['max_width'] ?? 1600), (int) ($field['max_height'] ?? 1600))
                            : $this->uploadFile($_FILES[$name], $field);
                        $data[$name] = $path;
                        if ($current !== '' && $current !== $path) {
                            Uploader::delete($current);
                        }
                    } catch (\RuntimeException $e) {
                        $errors[$name] = $e->getMessage();
                    }
                } elseif (str_contains($rules, 'required') && $current === '' && !isset($data[$name])) {
                    $errors[$name] = 'Cal pujar un fitxer.';
                }
                continue;
            }

            if ($type === 'course_laps') {
                // No és cap columna: es desa a part, un cop es coneix l'identificador.
                continue;
            }

            $raw = $_POST[$name] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;

            switch ($type) {
                case 'bool':
                    $data[$name] = input_bool($name);
                    break;
                case 'money':
                    $data[$name] = to_cents((string) $value);
                    break;
                case 'number':
                    $data[$name] = ($value === '' || $value === null) ? null : (int) $value;
                    break;
                case 'relation':
                    $data[$name] = ($value === '' || $value === null || (int) $value === 0) ? null : (int) $value;
                    break;
                case 'html':
                    $data[$name] = Html::clean((string) $value);
                    break;
                case 'slug':
                    $source = (string) ($_POST[$field['source'] ?? 'name'] ?? '');
                    $slug = slugify(((string) $value) !== '' ? (string) $value : $source);
                    $data[$name] = $this->uniqueSlug($resource, $slug, $existing['id'] ?? null);
                    break;
                case 'select':
                    $options = $field['options'] ?? [];
                    $data[$name] = array_key_exists((string) $value, $options) ? (string) $value : ($field['default'] ?? '');
                    break;
                case 'icon':
                    $data[$name] = \Cros\Core\Icons::exists((string) $value) ? (string) $value : ($field['default'] ?? 'info');
                    break;
                default:
                    $data[$name] = (string) ($value ?? '');
            }

            if ($rules !== '') {
                $check = $this->validate([$name => $rules], [$name => (string) ($data[$name] ?? '')]);
                if ($check) {
                    $errors[$name] = $check[$name];
                }
            }
        }

        // Neteja especial per a Wikiloc: accepta l'URL sencer
        if (isset($data['wikiloc_id']) && !ctype_digit((string) $data['wikiloc_id'])) {
            if (preg_match('/(\d{5,})/', (string) $data['wikiloc_id'], $m)) {
                $data['wikiloc_id'] = $m[1];
            } elseif (trim((string) $data['wikiloc_id']) !== '') {
                $errors['wikiloc_id'] = 'Introduïu el número de la ruta o l\'URL completa de Wikiloc.';
            }
        }

        return [$data, $errors];
    }

    private function uploadFile(array $file, array $field): string
    {
        $accept = strtolower((string) ($field['accept'] ?? '.pdf'));
        if (str_contains($accept, 'gpx')) {
            $name = strtolower((string) ($file['name'] ?? ''));
            if (!str_ends_with($name, '.gpx')) {
                throw new \RuntimeException('El fitxer ha de tenir extensió .gpx');
            }
            $content = (string) file_get_contents($file['tmp_name']);
            if (!str_contains($content, '<gpx') && !str_contains($content, '<trk')) {
                throw new \RuntimeException('El fitxer no sembla un GPX vàlid.');
            }
            $target = ($field['folder'] ?? 'documents') . '/' . slugify(pathinfo($name, PATHINFO_FILENAME)) . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.gpx';
            $dir = dirname(upload_path($target));
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents(upload_path($target), $content);
            return $target;
        }
        return Uploader::document($file, $field['folder'] ?? 'documents');
    }

    private function uniqueSlug(array $resource, string $slug, $ignoreId): string
    {
        $slug = $slug !== '' ? $slug : 'element';
        $base = $slug;
        $suffix = 2;
        while (true) {
            $sql = 'SELECT 1 FROM `' . $resource['table'] . '` WHERE slug = :slug';
            $params = ['slug' => $slug];
            if ($ignoreId) {
                $sql .= ' AND id <> :id';
                $params['id'] = (int) $ignoreId;
            }
            if (!Db::val($sql, $params)) {
                return $slug;
            }
            $slug = $base . '-' . $suffix++;
        }
    }
}

<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * AJAX dispatch and save orchestration for the quick-create opcional flow.
 * Business logic lives here so the plugin PHPUnit <source>include> covers it;
 * controller/tpvmod.php only requires this file and dispatches.
 */

declare(strict_types=1);

require_once __DIR__ . '/tpvmod_opcionales.php';

/**
 * Normalize an opcional name for exact, whitespace-insensitive comparison.
 */
function tpvmod_normalize_opcional_nombre(string $nombre): string
{
    $nombre = preg_replace('/\s+/u', ' ', trim($nombre));

    return mb_strtolower((string) $nombre, 'UTF-8');
}

/**
 * Whether a catalog opcional belongs to any optional group.
 *
 * Sourced from the `catalogo_opcional_grupo_rel` bridge through
 * `catalogo_opcional::is_grouped()` (AD-13); a plain array candidate carries
 * the already-normalized `grouped` flag.
 *
 * @param mixed $candidate
 */
function tpvmod_opcional_is_grouped($candidate): bool
{
    if (is_array($candidate)) {
        return !empty($candidate['grouped']);
    }

    if (!is_object($candidate)) {
        return false;
    }

    if (method_exists($candidate, 'is_grouped')) {
        return (bool) $candidate->is_grouped();
    }

    return !empty($candidate->grouped);
}

/**
 * Convert a catalog opcional (model or array) into a plain candidate array.
 *
 * @param mixed $candidate
 * @return array<string, mixed>|null
 */
function tpvmod_opcional_candidate_array($candidate): ?array
{
    if (is_array($candidate)) {
        return $candidate;
    }

    if (!is_object($candidate)) {
        return null;
    }

    return [
        'id' => (int) ($candidate->id ?? 0),
        'codigo' => (string) ($candidate->codigo ?? ''),
        'nombre' => (string) ($candidate->nombre ?? ''),
        'descripcion' => (string) ($candidate->descripcion ?? ''),
        'precio' => (float) ($candidate->precio ?? 0),
        'tipo_precio' => (string) ($candidate->tipo_precio ?? 'fijo'),
        'porcentaje' => $candidate->porcentaje ?? null,
        'grouped' => tpvmod_opcional_is_grouped($candidate),
    ];
}

/**
 * Reuse an existing opcional whose normalized nombre equals the input (OD-8).
 *
 * @param array<int, mixed> $candidates
 * @return array<string, mixed>|null
 */
function tpvmod_match_opcional_by_nombre(array $candidates, string $nombre): ?array
{
    $target = tpvmod_normalize_opcional_nombre($nombre);
    if ($target === '') {
        return null;
    }

    foreach ($candidates as $candidate) {
        $item = tpvmod_opcional_candidate_array($candidate);
        if ($item === null) {
            continue;
        }

        /// Grouped opcionales are not reusable (OD-5/OD-8): a grouped match
        /// would later be rejected by catalogo_articulo_opcional::add(), so skip
        /// it and let the flow create a new ungrouped opcional instead.
        if (!empty($item['grouped'])) {
            continue;
        }

        if (tpvmod_normalize_opcional_nombre((string) ($item['nombre'] ?? '')) === $target) {
            return $item;
        }
    }

    return null;
}

/**
 * Increment the numeric suffix of an `OPC####` codigo while keeping its shape.
 */
function tpvmod_bump_opcional_codigo(string $codigo, int $attempt): string
{
    $codigo = trim($codigo);
    if (preg_match('/^(.*?)(\d+)$/', $codigo, $matches) === 1) {
        $prefix = $matches[1];
        $digits = $matches[2];
        $number = (int) $digits + max(0, $attempt);

        return $prefix . str_pad((string) $number, strlen($digits), '0', STR_PAD_LEFT);
    }

    return $attempt > 0 ? $codigo . $attempt : $codigo;
}

/**
 * First non-existing codigo candidate, or null after bounded exhaustion (OD-9).
 */
function tpvmod_next_opcional_codigo(string $base, callable $exists, int $maxAttempts = 5): ?string
{
    $maxAttempts = max(1, $maxAttempts);

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $candidate = tpvmod_bump_opcional_codigo($base, $attempt);
        if (!$exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Resolve the association target from the submitted form (OD-3).
 *
 * Familia is only reachable when the server resolved a non-empty codfamilia.
 *
 * @param array<string, mixed> $post
 * @return array{ok: bool, errors: list<string>, target: ?string}
 */
function tpvmod_resolve_asociacion_target(array $post, string $codfamilia): array
{
    $target = ((string) ($post['asociacion'] ?? '')) === 'familia' ? 'familia' : 'producto';

    if ($target === 'familia' && trim($codfamilia) === '') {
        return [
            'ok' => false,
            'errors' => ['El producto no tiene familia asignada.'],
            'target' => null,
        ];
    }

    return ['ok' => true, 'errors' => [], 'target' => $target];
}

/**
 * Build the JSON payload returned for a persisted opcional.
 *
 * @param object|array<string, mixed> $opcional
 * @return array<string, mixed>
 */
function tpvmod_opcionales_ajax_opcional_payload(object|array $opcional): array
{
    $read = static function (string $key, mixed $default = null) use ($opcional): mixed {
        if (is_array($opcional)) {
            return $opcional[$key] ?? $default;
        }

        return $opcional->{$key} ?? $default;
    };

    $porcentaje = $read('porcentaje');

    return [
        'id' => (int) $read('id', 0),
        'codigo' => (string) $read('codigo', ''),
        'nombre' => (string) $read('nombre', ''),
        'descripcion' => (string) $read('descripcion', ''),
        'precio' => (float) $read('precio', 0),
        'tipo_precio' => (string) $read('tipo_precio', 'fijo'),
        'porcentaje' => ($porcentaje === null || $porcentaje === '') ? null : (float) $porcentaje,
        /// Quick-create/reuse opcionales are always loose (AD-13): the group
        /// payload never comes from the model.
        'grupo_id' => null,
    ];
}

/**
 * Build the live catalog models consumed by the save orchestration.
 *
 * @return array<string, mixed>
 */
function tpvmod_opcionales_ajax_default_models(string $referencia, string $nombre): array
{
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional_familia.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional_precio.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_articulo_opcional.php';

    $opcional = new \FSFramework\model\catalogo_opcional();

    $candidates = [];
    foreach ($opcional->search($nombre) as $candidate) {
        $item = tpvmod_opcional_candidate_array($candidate);
        if ($item !== null) {
            $candidates[] = $item;
        }
    }

    return [
        'opcional' => $opcional,
        'relation' => new \FSFramework\model\catalogo_articulo_opcional(),
        'familyRelation' => new \FSFramework\model\catalogo_opcional_familia(),
        'candidates' => $candidates,
        'lista' => tpvmod_default_lista_precio(),
    ];
}

/**
 * Persist (reuse or create), apply lista parity and associate the opcional.
 *
 * Receives already-normalized `$data`. `$models` is the orchestrator seam: a
 * callable returning the model doubles/factory for DB-free tests.
 *
 * @param array<string, mixed> $data
 * @return array{ok: bool, errors: list<string>, opcional: ?array<string, mixed>}
 */
function tpvmod_opcionales_ajax_persist(
    array $data,
    string $referencia,
    string $target,
    string $codfamilia,
    ?callable $models = null
): array {
    $factory = $models ?? static fn (): array => tpvmod_opcionales_ajax_default_models(
        $referencia,
        (string) ($data['nombre'] ?? '')
    );
    $resolved = (array) $factory();

    $opcional = $resolved['opcional'] ?? null;
    $relation = $resolved['relation'] ?? null;
    /** @var array<int, mixed> $candidates */
    $candidates = (array) ($resolved['candidates'] ?? []);
    $lista = array_key_exists('lista', $resolved) ? $resolved['lista'] : null;

    if (!is_object($opcional) || !is_object($relation)) {
        return [
            'ok' => false,
            'errors' => ['No se pudo inicializar el catálogo de opcionales.'],
            'opcional' => null,
        ];
    }

    $match = tpvmod_match_opcional_by_nombre($candidates, (string) ($data['nombre'] ?? ''));

    if ($match !== null) {
        /// Reuse: never call save() (OD-8); copy the matched values for the payload.
        $opcional->id = (int) ($match['id'] ?? 0);
        $opcional->codigo = (string) ($match['codigo'] ?? '');
        $opcional->nombre = (string) ($match['nombre'] ?? ($data['nombre'] ?? ''));
        $opcional->descripcion = (string) ($match['descripcion'] ?? '');
        $opcional->precio = (float) ($match['precio'] ?? 0);
        $opcional->tipo_precio = (string) ($match['tipo_precio'] ?? 'fijo');
        $opcional->porcentaje = ($match['porcentaje'] ?? null) !== null ? (float) $match['porcentaje'] : null;
    } else {
        $opcional->nombre = (string) $data['nombre'];
        $opcional->descripcion = (string) $data['descripcion'];
        $opcional->tipo_precio = (string) $data['tipo_precio'];
        $opcional->precio = (float) $data['precio'];
        $opcional->porcentaje = $data['porcentaje'] !== null ? (float) $data['porcentaje'] : null;
        $opcional->activo = true;

        $base = (string) $opcional->get_new_codigo();
        $exists = static fn (string $codigo): bool => (bool) $opcional->get_by_codigo($codigo);
        $codigo = tpvmod_next_opcional_codigo($base, $exists);
        if ($codigo === null) {
            return [
                'ok' => false,
                'errors' => ['No se pudo generar un código único para el opcional. Vuelve a intentarlo.'],
                'opcional' => null,
            ];
        }

        $opcional->codigo = $codigo;

        if (!$opcional->save()) {
            $errors = method_exists($opcional, 'get_errors') ? $opcional->get_errors() : [];
            if (!is_array($errors) || $errors === []) {
                $errors = ['No se pudo guardar el opcional.'];
            }

            return ['ok' => false, 'errors' => array_values($errors), 'opcional' => null];
        }

        /// Catalog parity on the plugin default lista (F7): catalogo_opcional
        /// does not load the price model, so require it explicitly first.
        $lista = ($lista === null || $lista === '') ? tpvmod_default_lista_precio() : (string) $lista;
        require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional_precio.php';

        $parityOk = (string) $data['tipo_precio'] === 'porcentaje'
            ? $opcional->set_porcentaje_lista($lista, (float) $data['porcentaje'])
            : $opcional->set_precio_lista($lista, (float) $data['precio']);

        if ($parityOk === false) {
            return [
                'ok' => false,
                'errors' => ['El opcional se guardó, pero no se pudo guardar su precio de lista.'],
                'opcional' => null,
            ];
        }
    }

    if ($target === 'familia') {
        /// Link the family only, never propagate to its articles (OD-7).
        $associated = $opcional->add_familia_only($codfamilia);
        if ($associated === false) {
            return [
                'ok' => false,
                'errors' => ['El opcional se guardó, pero no se pudo asociar a la familia.'],
                'opcional' => null,
            ];
        }
    } else {
        $associated = $relation->add($referencia, (int) $opcional->id, false);
        if ($associated === false) {
            return [
                'ok' => false,
                'errors' => ['El opcional se guardó, pero no se pudo asociar al producto.'],
                'opcional' => null,
            ];
        }
    }

    return [
        'ok' => true,
        'errors' => [],
        'opcional' => tpvmod_opcionales_ajax_opcional_payload($opcional),
    ];
}

/**
 * Emit a JSON response without exiting, so the controller keeps its contract.
 *
 * @param array<string, mixed> $payload
 */
function tpvmod_opcionales_ajax_emit_json(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}

/**
 * Handle the quick-create write endpoint. Returns true when handled.
 */
function tpvmod_opcionales_ajax_dispatch(fs_controller $ctrl): bool
{
    if (!isset($_POST['guardar_opcional_tpv'])) {
        return false;
    }

    tpvmod_opcionales_ajax_save($ctrl);

    return true;
}

/**
 * CSRF-gated save handler: normalize input, resolve the target and persist.
 *
 * `$models` is the optional DB-free test seam forwarded to `persist()`; when
 * omitted, `persist()` resolves its production default factory.
 */
function tpvmod_opcionales_ajax_save(fs_controller $ctrl, ?callable $models = null): void
{
    $ctrl->template = false;

    if (!$ctrl->isCsrfValid()) {
        tpvmod_opcionales_ajax_emit_json(['ok' => false, 'errors' => ['Token CSRF inválido.']]);

        return;
    }

    $post = $_POST;
    $normalized = tpvmod_normalize_opcional_input($post);
    if (!$normalized['ok']) {
        tpvmod_opcionales_ajax_emit_json(['ok' => false, 'errors' => $normalized['errors']]);

        return;
    }

    $referencia = trim((string) ($post['referencia'] ?? ''));
    if ($referencia === '') {
        tpvmod_opcionales_ajax_emit_json(['ok' => false, 'errors' => ['Producto no especificado.']]);

        return;
    }

    $codfamilia = tpvmod_articulo_codfamilia($referencia);

    $target = tpvmod_resolve_asociacion_target($post, $codfamilia);
    if (!$target['ok']) {
        tpvmod_opcionales_ajax_emit_json(['ok' => false, 'errors' => $target['errors']]);

        return;
    }

    $result = tpvmod_opcionales_ajax_persist(
        $normalized['data'],
        $referencia,
        (string) $target['target'],
        $codfamilia,
        $models
    );

    if (!$result['ok']) {
        tpvmod_opcionales_ajax_emit_json(['ok' => false, 'errors' => $result['errors']]);

        return;
    }

    tpvmod_opcionales_ajax_emit_json([
        'ok' => true,
        'opcional' => $result['opcional'],
        'codfamilia' => $codfamilia,
    ]);
}

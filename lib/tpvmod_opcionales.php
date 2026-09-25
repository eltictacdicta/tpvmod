<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Helpers for catalogo_core opcionales on TPV line items.
 */

require_once __DIR__ . '/tpvmod_modules.php';

/**
 * Prefix stored in line descripcion for optional product lines.
 */
function tpvmod_opcional_line_prefix(): string
{
    return 'Opcional: ';
}

/**
 * Whether a saved line description represents an optional line.
 */
function tpvmod_is_opcional_line_description(?string $descripcion): bool
{
    $descripcion = (string) $descripcion;

    return str_starts_with($descripcion, tpvmod_opcional_line_prefix());
}

/**
 * Build the canonical optional line description for TPV and printing.
 */
function tpvmod_format_opcional_line_description(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return tpvmod_opcional_line_prefix();
    }

    if (tpvmod_is_opcional_line_description($texto)) {
        return $texto;
    }

    return tpvmod_opcional_line_prefix() . $texto;
}

/**
 * Whether a submitted line carries the ad-hoc opcional marker.
 *
 * Quick-create ad-hoc rows submit `tpvmod_opcional_ad_hoc_N=1`; the marker
 * keeps the line inert for obligatorios/groups on the server (OD-6/F2).
 *
 * @param array<string, mixed> $post
 */
function tpvmod_opcional_is_ad_hoc_post(array $post, int $n): bool
{
    $value = $post['tpvmod_opcional_ad_hoc_' . $n] ?? null;
    if ($value === null) {
        return false;
    }

    $value = (string) $value;

    return $value !== '' && $value !== '0';
}

/**
 * Normalize the quick-create form input into catalog opcional fields.
 *
 * Pure contract (OD-5): the resulting data is always ungrouped and active.
 * Model-side sanitization (`no_html()`) is verified at the catalog model
 * boundary, not here.
 *
 * @param array<string, mixed> $post
 * @return array{ok: bool, errors: list<string>, data: ?array<string, mixed>}
 */
function tpvmod_normalize_opcional_input(array $post): array
{
    $errors = [];
    $nombre = trim((string) ($post['nombre'] ?? ''));
    $descripcion = trim((string) ($post['descripcion'] ?? ''));

    if ($nombre === '') {
        $errors[] = 'El nombre del opcional es obligatorio.';
    } elseif (mb_strlen($nombre) > 100) {
        $errors[] = 'El nombre del opcional no puede superar los 100 caracteres.';
    }

    $valorRaw = str_replace(',', '.', trim((string) ($post['valor'] ?? '')));
    if ($valorRaw === '' || !is_numeric($valorRaw) || (float) $valorRaw < 0) {
        $errors[] = 'El valor del opcional no es válido.';
    }

    $tipo = ((string) ($post['tipo_precio'] ?? '')) === 'porcentaje' ? 'porcentaje' : 'fijo';

    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors, 'data' => null];
    }

    $valor = (float) $valorRaw;

    return [
        'ok' => true,
        'errors' => [],
        'data' => [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_precio' => $tipo,
            'precio' => $tipo === 'porcentaje' ? 0.0 : $valor,
            'porcentaje' => $tipo === 'porcentaje' ? $valor : null,
            'activo' => true,
            'grouped' => false,
        ],
    ];
}

/**
 * Build a session-only (ad-hoc) opcional from composed form input.
 *
 * `porcentaje` resolves over the parent line PVP at insert time (OD-2); the
 * computed price is frozen afterwards. Invalid input yields `{ok:false}`.
 *
 * @param array<string, mixed> $input
 * @return array{ok: bool, errors: list<string>, opcional: ?array<string, mixed>}
 */
function tpvmod_build_ad_hoc_opcional(array $input, float $pvpBase): array
{
    $normalized = tpvmod_normalize_opcional_input($input);
    if (!$normalized['ok']) {
        return ['ok' => false, 'errors' => $normalized['errors'], 'opcional' => null];
    }

    $data = $normalized['data'];
    $precio = $data['tipo_precio'] === 'porcentaje'
        ? bround($pvpBase * ((float) $data['porcentaje'] / 100))
        : (float) $data['precio'];

    return [
        'ok' => true,
        'errors' => [],
        'opcional' => [
            'id' => null,
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] !== '' ? $data['descripcion'] : $data['nombre'],
            'precio' => $precio,
            'tipo_precio' => $data['tipo_precio'],
            'porcentaje' => $data['porcentaje'],
            'grupo_id' => null,
            'ad_hoc' => true,
        ],
    ];
}

/**
 * Resolve `articulo->codfamilia` for a reference (authoritative server read).
 */
function tpvmod_articulo_codfamilia(string $referencia): string
{
    $referencia = trim($referencia);
    if ($referencia === '' || !tpvmod_has_catalogo_core()) {
        return '';
    }

    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/articulo.php';

    $articulo = new \FSFramework\model\articulo();
    $art = $articulo->get($referencia);
    if (!$art) {
        return '';
    }

    return trim((string) $art->codfamilia);
}

/**
 * Whether catalogo_core is active and opcionales can be resolved.
 */
function tpvmod_has_catalogo_core(?array $plugins = null): bool
{
    return in_array('catalogo_core', tpvmod_active_plugins($plugins), true);
}

/**
 * Resolve default price list code from catalogo_core.
 */
function tpvmod_default_lista_precio(): string
{
    if (!tpvmod_has_catalogo_core()) {
        return 'DEF';
    }

    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_lista_precio.php';

    $lista = new \FSFramework\model\catalogo_lista_precio();
    $lista->ensure_defaults();
    $default = $lista->get_default();

    return $default
        ? (string) $default->codlista
        : \FSFramework\model\catalogo_lista_precio::DEFAULT_CODE;
}

/**
 * @return array{id: int, codigo: string, descripcion: string, precio: float, grupo_id: ?int, grupo_nombre: ?string, grupo_exclusivo: bool, obligatorio: bool}
 */
function tpvmod_build_opcional_item(object $opcional, float $pvpArticulo, string $codlista, ?object $grupo = null, bool $obligatorio = false): array
{
    $texto = trim((string) $opcional->descripcion);
    if ($texto === '') {
        $texto = trim((string) $opcional->nombre);
    }

    $item = [
        'id' => (int) $opcional->id,
        'codigo' => (string) $opcional->codigo,
        'descripcion' => $texto,
        'precio' => (float) $opcional->precio_para_articulo($pvpArticulo, $codlista),
        'grupo_id' => null,
        'grupo_nombre' => null,
        'grupo_exclusivo' => false,
        'obligatorio' => $obligatorio,
    ];

    if ($grupo && !empty($grupo->id)) {
        $item['grupo_id'] = (int) $grupo->id;
        $item['grupo_nombre'] = (string) $grupo->nombre;
        $item['grupo_exclusivo'] = (bool) ($grupo->exclusivo ?? true);
    }

    return $item;
}

/**
 * Fetch active opcionales for an article grouped for TPV selection.
 *
 * @return array{
 *   grupos: list<array{id: int, nombre: string, exclusivo: bool, obligatorio: bool, opcionales: list<array<string, mixed>>}>,
 *   sueltos: list<array<string, mixed>>,
 *   codfamilia: string
 * }
 */
function tpvmod_opcionales_for_articulo(string $referencia, float $pvpArticulo, ?string $codlista = null, ?array $plugins = null): array
{
    if (!tpvmod_has_catalogo_core($plugins) || trim($referencia) === '') {
        return ['grupos' => [], 'sueltos' => [], 'codfamilia' => ''];
    }

    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_articulo_opcional.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_articulo_opcional_grupo.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional.php';
    require_once FS_FOLDER . '/plugins/catalogo_core/model/core/catalogo_opcional_grupo.php';

    if ($codlista === null || $codlista === '') {
        $codlista = tpvmod_default_lista_precio();
    }

    $grupoRel = new \FSFramework\model\catalogo_articulo_opcional_grupo();
    $rel = new \FSFramework\model\catalogo_articulo_opcional();
    $grupos = [];
    $sueltos = [];

    foreach ($grupoRel->get_grupos_from_articulo($referencia) as $grupo) {
        $variantes = [];
        foreach ($grupo->get_opcionales_activos() as $opcional) {
            $variantes[] = tpvmod_build_opcional_item($opcional, $pvpArticulo, $codlista, $grupo, false);
        }

        if ($variantes === []) {
            continue;
        }

        $grupos[] = [
            'id' => (int) $grupo->id,
            'nombre' => (string) $grupo->nombre,
            'exclusivo' => (bool) $grupo->exclusivo,
            'obligatorio' => (bool) $grupo->obligatorio_en_articulo,
            'opcionales' => $variantes,
        ];
    }

    foreach ($rel->get_opcionales_sueltos_from_articulo($referencia) as $opcional) {
        if (!$opcional->activo) {
            continue;
        }

        $sueltos[] = tpvmod_build_opcional_item(
            $opcional,
            $pvpArticulo,
            $codlista,
            null,
            (bool) $opcional->obligatorio_en_articulo
        );
    }

    usort($grupos, static function (array $a, array $b): int {
        return strcmp((string) $a['nombre'], (string) $b['nombre']);
    });

    return [
        'grupos' => array_values($grupos),
        'sueltos' => $sueltos,
        'codfamilia' => tpvmod_articulo_codfamilia($referencia),
    ];
}

/**
 * Flat list of opcionales (all groups + sueltos) for lookups.
 *
 * @param array{grupos?: list<array<string, mixed>>, sueltos?: list<array<string, mixed>>} $payload
 * @return list<array<string, mixed>>
 */
function tpvmod_opcionales_flat_list(array $payload): array
{
    $flat = [];

    foreach ($payload['grupos'] ?? [] as $grupo) {
        foreach ($grupo['opcionales'] ?? [] as $item) {
            $flat[] = $item;
        }
    }

    foreach ($payload['sueltos'] ?? [] as $item) {
        $flat[] = $item;
    }

    return $flat;
}

/**
 * Text after the "Opcional: " prefix stored in line descriptions.
 */
function tpvmod_opcional_line_text(string $descripcion): string
{
    if (!tpvmod_is_opcional_line_description($descripcion)) {
        return '';
    }

    return trim(substr($descripcion, strlen(tpvmod_opcional_line_prefix())));
}

/**
 * Match a saved optional line description against catalog opcionales payload.
 *
 * @param array{grupos?: list<array<string, mixed>>, sueltos?: list<array<string, mixed>>} $payload
 * @return array{opcional_id: int, grupo_id: int, parent_ref: string}|null
 */
function tpvmod_match_opcional_line_in_payload(string $parentRef, string $descripcion, array $payload): ?array
{
    $text = tpvmod_opcional_line_text($descripcion);
    $parentRef = trim($parentRef);
    if ($text === '' || $parentRef === '') {
        return null;
    }

    foreach ($payload['grupos'] ?? [] as $grupo) {
        $grupoId = (int) ($grupo['id'] ?? 0);
        $grupoNombre = trim((string) ($grupo['nombre'] ?? ''));
        foreach ($grupo['opcionales'] ?? [] as $opcional) {
            $opcDesc = trim((string) ($opcional['descripcion'] ?? ''));
            $candidates = [$opcDesc];
            if ($grupoNombre !== '') {
                $candidates[] = $grupoNombre . ': ' . $opcDesc;
            }

            if (in_array($text, $candidates, true)) {
                return [
                    'opcional_id' => (int) ($opcional['id'] ?? 0),
                    'grupo_id' => $grupoId,
                    'parent_ref' => $parentRef,
                ];
            }
        }
    }

    foreach ($payload['sueltos'] ?? [] as $suelto) {
        $opcDesc = trim((string) ($suelto['descripcion'] ?? ''));
        if ($text === $opcDesc) {
            return [
                'opcional_id' => (int) ($suelto['id'] ?? 0),
                'grupo_id' => 0,
                'parent_ref' => $parentRef,
            ];
        }
    }

    return null;
}

/**
 * Resolve optional-line metadata for edit/re-save flows (hidden POST fields).
 *
 * @return array{opcional_id: int, grupo_id: int, parent_ref: string}|null
 */
function tpvmod_resolve_opcional_line_metadata(
    string $parentRef,
    string $descripcion,
    float $pvpArticulo = 0.0,
    ?array $plugins = null
): ?array {
    if (!tpvmod_has_catalogo_core($plugins)) {
        return null;
    }

    $payload = tpvmod_opcionales_for_articulo($parentRef, $pvpArticulo, null, $plugins);

    return tpvmod_match_opcional_line_in_payload($parentRef, $descripcion, $payload);
}

/**
 * Valida que cada artículo del TPV cumpla sus opcionales/grupos obligatorios.
 *
 * @return list<string>
 */
function tpvmod_validate_obligatorios_post(array $post): array
{
    if (!tpvmod_has_catalogo_core()) {
        return [];
    }

    $numlineas = (int) ($post['numlineas'] ?? 0);
    if ($numlineas <= 0) {
        return [];
    }

    $productRefs = [];
    $selections = [];
    $currentRef = null;

    for ($i = 1; $i <= $numlineas; $i++) {
        $ref = trim((string) ($post['referencia_' . $i] ?? ''));
        if ($ref !== '') {
            $currentRef = $ref;
            if (!isset($productRefs[$ref])) {
                $productRefs[$ref] = true;
                $selections[$ref] = ['grupos' => [], 'sueltos' => []];
            }
            continue;
        }

        $desc = (string) ($post['desc_' . $i] ?? '');
        if (!tpvmod_is_opcional_line_description($desc)) {
            continue;
        }

        /// Ad-hoc quick-create lines are inert: skip description resolution
        /// and obligation counting even when the text matches a catalog opcional.
        if (tpvmod_opcional_is_ad_hoc_post($post, $i)) {
            continue;
        }

        $parentRef = trim((string) ($post['tpvmod_parent_ref_' . $i] ?? $currentRef ?? ''));
        $opcionalId = (int) ($post['tpvmod_opcional_id_' . $i] ?? 0);
        $grupoId = (int) ($post['tpvmod_opcional_grupo_id_' . $i] ?? 0);

        if ($opcionalId <= 0 && $parentRef !== '') {
            $resolved = tpvmod_resolve_opcional_line_metadata(
                $parentRef,
                $desc,
                (float) ($post['pvp_' . $i] ?? 0)
            );
            if ($resolved !== null) {
                $opcionalId = $resolved['opcional_id'];
                $grupoId = $resolved['grupo_id'];
            }
        }

        if ($parentRef === '' || $opcionalId <= 0) {
            continue;
        }

        if (!isset($selections[$parentRef])) {
            $selections[$parentRef] = ['grupos' => [], 'sueltos' => []];
            $productRefs[$parentRef] = true;
        }

        if ($grupoId > 0) {
            $selections[$parentRef]['grupos'][$grupoId] = true;
        } else {
            $selections[$parentRef]['sueltos'][$opcionalId] = true;
        }
    }

    $errors = [];
    foreach (array_keys($productRefs) as $referencia) {
        $payload = tpvmod_opcionales_for_articulo($referencia, 0.0);
        $selected = $selections[$referencia] ?? ['grupos' => [], 'sueltos' => []];

        foreach ($payload['grupos'] as $grupo) {
            if (empty($grupo['obligatorio'])) {
                continue;
            }

            $grupoId = (int) ($grupo['id'] ?? 0);
            if ($grupoId <= 0 || !empty($selected['grupos'][$grupoId])) {
                continue;
            }

            $errors[] = 'Artículo ' . $referencia . ': debes elegir el grupo "' . ($grupo['nombre'] ?? '') . '".';
        }

        foreach ($payload['sueltos'] as $suelto) {
            if (empty($suelto['obligatorio'])) {
                continue;
            }

            $opcionalId = (int) ($suelto['id'] ?? 0);
            if ($opcionalId <= 0 || !empty($selected['sueltos'][$opcionalId])) {
                continue;
            }

            $label = trim((string) ($suelto['descripcion'] ?? $suelto['codigo'] ?? ''));
            $errors[] = 'Artículo ' . $referencia . ': debes elegir el opcional "' . $label . '".';
        }
    }

    return $errors;
}

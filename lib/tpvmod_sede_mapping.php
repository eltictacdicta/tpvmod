<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Pure helpers for the sede -> document-type mapping section of the TPVMOD
 * settings page. They are extracted here so the mapping semantics can be unit
 * tested without instantiating `fs_controller` (which requires a DB and a
 * session); the plugin PHPUnit `<source>` covers `lib/`.
 *
 * The four `empresa_sede_*` settings keys are PRIVATE to business_data's
 * `empresa_sede` model. This plugin must never read or write a raw
 * `empresa_sede_*` key: it always goes through
 * `empresa_sede::mapping()` / `empresa_sede::setMappingFor()`.
 */

/** Canonical document-type literals, in the order the UI renders them. */
const TPVMOD_SEDE_MAPPING_TIPOS = ['presupuesto', 'albaran', 'pedido', 'factura'];

/**
 * POST marker owned by the sede-mapping form. It exists so a mapping POST is
 * never mistaken for a terminal-mode POST (and vice versa).
 */
const TPVMOD_SEDE_MAPPING_POST_MARKER = 'save_sede_mapping';

/**
 * Whether the request carries the sede-mapping marker.
 *
 * @param array<string, mixed> $post
 * @param string $marker Overridable so callers can pass their own canonical
 *                       marker constant; defaults to the form's marker.
 */
function tpvmod_sede_mapping_submitted(array $post, string $marker = TPVMOD_SEDE_MAPPING_POST_MARKER): bool
{
    return array_key_exists($marker, $post);
}

/**
 * Normalizes the four posted selectors into the canonical mapping shape.
 * Absent or empty values normalize to `null` (no override).
 *
 * @param array<string, mixed> $post
 * @return array<string, ?string>
 */
function tpvmod_normalize_sede_mapping(array $post): array
{
    $mapping = [];

    foreach (TPVMOD_SEDE_MAPPING_TIPOS as $tipo) {
        $value = trim((string) ($post['sede_' . $tipo] ?? ''));
        $mapping[$tipo] = ($value === '') ? null : $value;
    }

    return $mapping;
}

/**
 * Validates and persists the four mappings, all-or-nothing on validation:
 * if any non-empty code does not resolve to a real sede, nothing is written.
 *
 * The injected callables are the only test seams; production callers omit them
 * and the defaults delegate to business_data's public API.
 *
 * @param array<string, mixed> $post
 * @param callable(string, ?string): bool|null $setMappingFor
 * @param callable(string): bool|null $sedeExists
 * @return array{ok: bool, errors: list<string>, saved: list<string>}
 */
function tpvmod_save_sede_mapping(
    array $post,
    ?callable $setMappingFor = null,
    ?callable $sedeExists = null
): array {
    $setMappingFor = $setMappingFor ?? static function (string $tipo, ?string $codsede): bool {
        return \empresa_sede::setMappingFor($tipo, $codsede);
    };

    $sedeExists = $sedeExists ?? static function (string $codsede): bool {
        $sede = (new \empresa_sede())->get($codsede);

        return $sede instanceof \empresa_sede;
    };

    $normalized = tpvmod_normalize_sede_mapping($post);

    foreach ($normalized as $codsede) {
        if ($codsede !== null && !$sedeExists($codsede)) {
            return [
                'ok' => false,
                'errors' => ['La sede seleccionada no existe.'],
                'saved' => [],
            ];
        }
    }

    $saved = [];
    foreach ($normalized as $tipo => $codsede) {
        if (!$setMappingFor($tipo, $codsede)) {
            return [
                'ok' => false,
                'errors' => ['No se pudo guardar el mapeo de sedes.'],
                'saved' => $saved,
            ];
        }

        $saved[] = $tipo;
    }

    return ['ok' => true, 'errors' => [], 'saved' => $saved];
}

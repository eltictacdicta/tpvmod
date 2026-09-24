<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Pure helpers for TPV client search and modal CRUD (testable without HTTP).
 */

declare(strict_types=1);

require_once __DIR__ . '/tpvmod_modules.php';

/**
 * Label shown in the TPV client input (nombre + phones).
 */
function tpvmod_cliente_campo_display(object $cliente): string
{
    $label = trim((string) ($cliente->nombre ?? ''));
    $tel = trim((string) ($cliente->telefono1 ?? ''));
    $tel2 = trim((string) ($cliente->telefono2 ?? ''));

    if ($tel !== '') {
        $label .= ' Tlf:' . $tel;
    }
    if ($tel2 !== '') {
        $label .= ' Tlf2:' . $tel2;
    }

    return $label;
}

/**
 * Primary phone for search result tables.
 */
function tpvmod_cliente_telefono_display(object $cliente): string
{
    $tel = trim((string) ($cliente->telefono1 ?? ''));
    if ($tel !== '') {
        return $tel;
    }

    return trim((string) ($cliente->telefono2 ?? ''));
}

/**
 * @param array<string, mixed> $post
 * @param (callable(string): ?object)|null $groupResolver Resolves a discount
 *        group by code; defaults to the shared grupo_descuentos model. Injected
 *        by tests to avoid the DB-backed model.
 */
function tpvmod_cliente_apply_from_post(object $cliente, array $post, ?callable $groupResolver = null): void
{
    if (array_key_exists('nombre', $post)) {
        $cliente->nombre = (string) $post['nombre'];
    }
    if (array_key_exists('razonsocial', $post)) {
        $cliente->razonsocial = (string) $post['razonsocial'];
    }
    if (array_key_exists('tipoidfiscal', $post)) {
        $cliente->tipoidfiscal = (string) $post['tipoidfiscal'];
    }
    if (array_key_exists('cifnif', $post)) {
        $cliente->cifnif = (string) $post['cifnif'];
    }
    if (array_key_exists('telefono1', $post)) {
        $cliente->telefono1 = (string) $post['telefono1'];
    }
    if (array_key_exists('telefono2', $post)) {
        $cliente->telefono2 = (string) $post['telefono2'];
    }
    if (array_key_exists('fax', $post)) {
        $cliente->fax = (string) $post['fax'];
    }
    if (array_key_exists('email', $post)) {
        $cliente->email = (string) $post['email'];
    }
    if (array_key_exists('web', $post)) {
        $cliente->web = (string) $post['web'];
    }
    if (array_key_exists('coddivisa', $post)) {
        $cliente->coddivisa = ($post['coddivisa'] ?? '') !== '' ? (string) $post['coddivisa'] : null;
    }
    if (array_key_exists('codgrupo', $post)) {
        // No silent fallback: an empty selection stays null so cliente::test()
        // rejects it. Writing '000000' here (the discount-group default code)
        // conflated both group concepts and violated the gruposclientes FK.
        $cliente->codgrupo = ($post['codgrupo'] ?? '') !== '' ? (string) $post['codgrupo'] : null;
    }
    if (array_key_exists('regimeniva', $post)) {
        $cliente->regimeniva = (string) $post['regimeniva'];
    }
    if (array_key_exists('recargo', $post)) {
        $cliente->recargo = ($post['recargo'] ?? '') === '1';
    }
    if (array_key_exists('personafisica', $post)) {
        $cliente->personafisica = ($post['personafisica'] ?? '') === '1';
    }
    if (array_key_exists('diaspago', $post)) {
        $cliente->diaspago = (string) $post['diaspago'];
    }
    if (array_key_exists('observaciones', $post)) {
        $cliente->observaciones = (string) $post['observaciones'];
    }
    if (array_key_exists('debaja', $post)) {
        $cliente->debaja = ($post['debaja'] ?? '') === '1';
    }
    $previousDiscountGroup = property_exists($cliente, 'codgrupo_descuento')
        ? ($cliente->codgrupo_descuento ?? null)
        : null;

    foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
        if (array_key_exists($field, $post)) {
            $cliente->{$field} = (float) $post[$field];
        }
    }

    $codgrupoDescuento = $post['codgrupo_descuento'] ?? null;
    $currentDiscountGroup = ($codgrupoDescuento !== null && $codgrupoDescuento !== '')
        ? (string) $codgrupoDescuento
        : null;
    $cliente->codgrupo_descuento = $currentDiscountGroup;

    tpvmod_cliente_apply_group_defaults_on_change(
        $cliente,
        $previousDiscountGroup,
        $currentDiscountGroup,
        $groupResolver
    );

    tpvmod_cliente_sync_descuentos_modified_flag($cliente, $groupResolver);
}

/**
 * True when the discount-group assignment moved (first assignment included).
 * An unchanged group keeps the submitted D1-D4 as a per-client override.
 */
function tpvmod_cliente_group_changed(?string $previous, ?string $current): bool
{
    return $current !== $previous;
}

/**
 * Overwrite D1-D4 with the new group's defaults whenever the discount group
 * changed. Selecting a group must apply its defaults immediately; only the
 * per-client override of an unchanged group survives. Removing the group
 * clears the discounts to 0.
 *
 * @param (callable(string): ?object)|null $groupResolver
 */
function tpvmod_cliente_apply_group_defaults_on_change(
    object $cliente,
    ?string $previous,
    ?string $current,
    ?callable $groupResolver = null
): void {
    if (!tpvmod_cliente_group_changed($previous, $current)) {
        return;
    }

    if ($current === null) {
        foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
            $cliente->{$field} = 0.0;
        }
        return;
    }

    $resolve = $groupResolver ?? tpvmod_cliente_default_group_resolver();

    $grupo = $resolve($current);
    if ($grupo === null) {
        return;
    }

    foreach (tpvmod_cliente_discount_values($grupo) as $field => $value) {
        $cliente->{$field} = $value;
    }
}

/**
 * Default discount-group resolver: the shared grupo_descuentos model when it
 * is loaded. Returns null when the model or the group is unavailable.
 *
 * @return callable(string): ?object
 */
function tpvmod_cliente_default_group_resolver(): callable
{
    return static function (string $cod): ?object {
        if (!class_exists('grupo_descuentos')) {
            return null;
        }

        $grupo = (new grupo_descuentos())->get($cod);

        return $grupo ?: null;
    };
}

/**
 * Normalized D1-D4 values: an unset (NULL) discount means no discount (0.00),
 * so NULL and 0 compare equal in the modified diff.
 *
 * @return array{d1: float, d2: float, d3: float, d4: float}
 */
function tpvmod_cliente_discount_values(object $source): array
{
    $values = [];
    foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
        $values[$field] = (float) ($source->{$field} ?? 0);
    }

    return $values;
}

/**
 * Mark descuentos_modified when D1–D4 differ from the selected discount group.
 * NULL and 0.00 mean the same thing, so they compare equal.
 *
 * @param (callable(string): ?object)|null $groupResolver
 */
function tpvmod_cliente_sync_descuentos_modified_flag(
    object $cliente,
    ?callable $groupResolver = null
): void {
    if (empty($cliente->codgrupo_descuento)) {
        return;
    }

    $resolve = $groupResolver ?? tpvmod_cliente_default_group_resolver();
    $grupoDesc = $resolve((string) $cliente->codgrupo_descuento);
    if ($grupoDesc === null) {
        return;
    }

    $groupValues = tpvmod_cliente_discount_values($grupoDesc);
    $modified = false;
    foreach (tpvmod_cliente_discount_values($cliente) as $field => $clientVal) {
        if (round($clientVal, 2) !== round($groupValues[$field], 2)) {
            $modified = true;
            break;
        }
    }

    $cliente->descuentos_modified = $modified;
}

/**
 * @param array<string, mixed> $post
 */
function tpvmod_direccion_apply_from_post(object $direccion, array $post, string $codcliente): void
{
    $direccion->codcliente = $codcliente;
    if (array_key_exists('descripcion', $post)) {
        $direccion->descripcion = (string) $post['descripcion'];
    }
    if (array_key_exists('direccion', $post)) {
        $direccion->direccion = (string) $post['direccion'];
    }
    if (array_key_exists('ciudad', $post)) {
        $direccion->ciudad = (string) $post['ciudad'];
    }
    if (array_key_exists('provincia', $post)) {
        $direccion->provincia = (string) $post['provincia'];
    }
    if (array_key_exists('codpostal', $post)) {
        $direccion->codpostal = (string) $post['codpostal'];
    }
    if (array_key_exists('codpais', $post)) {
        $direccion->codpais = (string) $post['codpais'];
    }
    if (array_key_exists('apartado', $post)) {
        $direccion->apartado = (string) $post['apartado'];
    }
    $direccion->domenvio = ($post['domenvio'] ?? '') === '1';
    $direccion->domfacturacion = ($post['domfacturacion'] ?? '') === '1';
}

/**
 * @return array<string, mixed>
 */
function tpvmod_cliente_json_response(object $cliente): array
{
    return [
        'ok' => true,
        'codcliente' => $cliente->codcliente,
        'label' => tpvmod_cliente_campo_display($cliente),
        'cliente' => tpvmod_datos_cliente_payload($cliente),
    ];
}

/**
 * @return array<string, mixed>
 */
function tpvmod_cliente_error_response(array $errors): array
{
    return [
        'ok' => false,
        'errors' => array_values($errors),
    ];
}

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
 */
function tpvmod_cliente_apply_from_post(object $cliente, array $post): void
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
        $cliente->codgrupo = ($post['codgrupo'] ?? '') !== '' ? (string) $post['codgrupo'] : '000000';
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
    foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
        if (array_key_exists($field, $post)) {
            $cliente->{$field} = (float) $post[$field];
        }
    }

    $codgrupoDescuento = $post['codgrupo_descuento'] ?? null;
    $cliente->codgrupo_descuento = ($codgrupoDescuento !== null && $codgrupoDescuento !== '')
        ? (string) $codgrupoDescuento
        : null;

    tpvmod_cliente_sync_descuentos_modified_flag($cliente);
}

/**
 * Mark descuentos_modified when D1–D4 differ from the selected discount group.
 */
function tpvmod_cliente_sync_descuentos_modified_flag(object $cliente): void
{
    if (empty($cliente->codgrupo_descuento) || !class_exists('grupo_descuentos')) {
        return;
    }

    $grupoDescModel = new grupo_descuentos();
    $grupoDesc = $grupoDescModel->get($cliente->codgrupo_descuento);
    if (!$grupoDesc) {
        return;
    }

    $modified = false;
    foreach (['d1', 'd2', 'd3', 'd4'] as $field) {
        $clientVal = $cliente->{$field} !== null ? round((float) $cliente->{$field}, 2) : null;
        $groupVal = $grupoDesc->{$field} !== null ? round((float) $grupoDesc->{$field}, 2) : null;
        if ($clientVal !== $groupVal) {
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

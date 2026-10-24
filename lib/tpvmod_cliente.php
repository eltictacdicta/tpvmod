<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Pure helpers for TPV client search and modal CRUD (testable without HTTP).
 *
 * Client-form processing is NOT implemented here: `clientes_core\ClienteForm`
 * is the single authority for mapping a submission, applying the discount
 * group defaults and computing the `descuentos_modified` flag. This file keeps
 * one thin seam (`tpvmod_cliente_apply()`) plus the display and response
 * helpers the TPV needs for its own markup.
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
 * Whether the shared client-form authority (`clientes_core\ClienteForm`) is
 * available in this installation. Consumers must refuse to persist a client
 * when it is not.
 */
function tpvmod_cliente_form_authority_available(): bool
{
    return class_exists(\FSFramework\Plugins\clientes_core\ClienteForm::class);
}

/**
 * Map a client-form submission onto a cliente through the shared authority.
 *
 * `ClienteForm::apply()` is selective (only keys present in `$post` are
 * touched), maps empty group codes to NULL without inventing a fallback,
 * applies the discount group defaults when the group changes and recomputes
 * `descuentos_modified`.
 *
 * Without the authority the object is returned untouched so the caller can
 * answer with an explicit error instead of persisting a half-mapped client.
 *
 * @param array<string, mixed> $post
 */
function tpvmod_cliente_apply(object $cliente, array $post): object
{
    if (!tpvmod_cliente_form_authority_available()) {
        return $cliente;
    }

    return \FSFramework\Plugins\clientes_core\ClienteForm::apply($cliente, $post);
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

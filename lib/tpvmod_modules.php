<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Helpers to detect optional facturacion_base integration (caja, terminal,
 * ventas_imprimir). Kept in a standalone file for PHPUnit coverage without
 * bootstrapping fs_controller.
 */

/**
 * @param array<string>|null $plugins Active plugin list; defaults to $GLOBALS['plugins'].
 * @return array<string>
 */
function tpvmod_active_plugins(?array $plugins = null): array
{
    if ($plugins !== null) {
        return $plugins;
    }

    return $GLOBALS['plugins'] ?? [];
}

/**
 * Whether facturacion_base is among the active plugins.
 */
function tpvmod_has_facturacion_base(?array $plugins = null): bool
{
    return in_array('facturacion_base', tpvmod_active_plugins($plugins), true);
}

/**
 * Caja / terminal / tpv_caja / ventas_imprimir features require facturacion_base.
 */
function tpvmod_caja_module_enabled(?array $plugins = null): bool
{
    return tpvmod_has_facturacion_base($plugins);
}

/**
 * Effective terminal mode. When caja module is off, terminal selection is N/A
 * and the stored setting MUST NOT affect runtime behaviour.
 */
function tpvmod_terminal_mode_effective(string $storedMode, ?array $plugins = null): string
{
    if (!tpvmod_caja_module_enabled($plugins)) {
        return 'with_terminal';
    }

    return ($storedMode === 'without_terminal') ? 'without_terminal' : 'with_terminal';
}

/**
 * Whether the admin may edit tpvmod_terminal_mode (requires facturacion_base).
 */
function tpvmod_terminal_settings_available(?array $plugins = null): bool
{
    return tpvmod_caja_module_enabled($plugins);
}

/**
 * Base print URL for a sales document type, or null when printing is unavailable.
 *
 * @param 'albaran'|'pedido'|'presupuesto'|'factura' $documentType
 */
function tpvmod_imprimir_url(string $documentType, ?array $plugins = null): ?string
{
    if (!tpvmod_has_facturacion_base($plugins)) {
        return null;
    }

    return match ($documentType) {
        'albaran' => './index.php?page=ventas_imprimir&albaran=TRUE&id=',
        'pedido' => './index.php?page=ventas_imprimir&pedido=TRUE&id=',
        'presupuesto' => './index.php?page=ventas_imprimir&presupuesto=TRUE&id=',
        'factura' => './index.php?page=ventas_imprimir&factura=TRUE&id=',
        default => null,
    };
}

/**
 * Whether clientes_facturacion is among the active plugins.
 */
function tpvmod_has_clientes_facturacion(?array $plugins = null): bool
{
    return in_array('clientes_facturacion', tpvmod_active_plugins($plugins), true);
}

/**
 * Resolve sales-document header codes (almacén, serie, divisa, forma de pago).
 *
 * Priority per field:
 * 1. POST (form / modal)
 * 2. TPV terminal (optional — only when facturacion_base terminal is active)
 * 3. User session defaults (fs_default_items / cookies)
 * 4. empresa row (business_data)
 *
 * Caller should load models and fall back to is_default() / first row when null.
 *
 * @param array<string,mixed> $post
 * @return array{almacen:?string,serie:?string,divisa:?string,forma_pago:?string}
 */
function tpvmod_resolve_documento_codigos(
    array $post,
    ?object $terminal = null,
    ?\fs_default_items $defaultItems = null,
    ?object $empresa = null
): array {
    $pick = static function (string $postKey, ?string $terminalVal, ?string $sessionVal, ?string $empresaVal) use ($post): ?string {
        $posted = $post[$postKey] ?? null;
        if ($posted !== null && $posted !== '') {
            return (string) $posted;
        }
        if ($terminalVal !== null && $terminalVal !== '') {
            return (string) $terminalVal;
        }
        if ($sessionVal !== null && $sessionVal !== '') {
            return (string) $sessionVal;
        }
        if ($empresaVal !== null && $empresaVal !== '') {
            return (string) $empresaVal;
        }

        return null;
    };

    return [
        'almacen' => $pick(
            'almacen',
            $terminal->codalmacen ?? null,
            $defaultItems?->codalmacen(),
            $empresa->codalmacen ?? null
        ),
        'serie' => $pick(
            'serie',
            $terminal->codserie ?? null,
            $defaultItems?->codserie(),
            $empresa->codserie ?? null
        ),
        'divisa' => $pick(
            'divisa',
            null,
            $defaultItems?->coddivisa(),
            $empresa->coddivisa ?? null
        ),
        'forma_pago' => $pick(
            'forma_pago',
            null,
            $defaultItems?->codpago(),
            $empresa->codpago ?? null
        ),
    ];
}

/**
 * Detect missing master data required to save TPV documents.
 *
 * @return list<array{key: string, label: string, url: string, plugin: string}>
 */
function tpvmod_master_data_gaps(
    int $almacenesCount,
    int $seriesCount,
    int $formaspagoCount,
    int $divisasCount,
    int $ejerciciosCount
): array {
    $gaps = [];

    if ($almacenesCount === 0) {
        $gaps[] = [
            'key' => 'almacen',
            'label' => 'Almacén',
            'url' => 'index.php?page=admin_almacenes',
            'plugin' => 'catalogo_core',
        ];
    }
    if ($seriesCount === 0) {
        $gaps[] = [
            'key' => 'serie',
            'label' => 'Serie',
            'url' => 'index.php?page=contabilidad_series',
            'plugin' => 'business_data',
        ];
    }
    if ($formaspagoCount === 0) {
        $gaps[] = [
            'key' => 'forma_pago',
            'label' => 'Forma de pago',
            'url' => 'index.php?page=contabilidad_formas_pago',
            'plugin' => 'business_data',
        ];
    }
    if ($divisasCount === 0) {
        $gaps[] = [
            'key' => 'divisa',
            'label' => 'Divisa',
            'url' => 'index.php?page=admin_divisas',
            'plugin' => 'catalogo_core',
        ];
    }
    if ($ejerciciosCount === 0) {
        $gaps[] = [
            'key' => 'ejercicio',
            'label' => 'Ejercicio contable',
            'url' => 'index.php?page=contabilidad_ejercicios',
            'plugin' => 'business_data',
        ];
    }

    return $gaps;
}

/**
 * Document types offered in the TPV save modal.
 *
 * Legacy facturacion_base exposed ventas_* menu pages for permissions; in this
 * fork sales documents live in clientes_facturacion and tpvmod owns the save
 * flow. When clientes_facturacion is active, all four types are available.
 *
 * @return list<array{tipo: string, nombre: string}>
 */
function tpvmod_tipos_a_guardar(?array $plugins = null): array
{
    if (!tpvmod_has_clientes_facturacion($plugins)) {
        return [];
    }

    $presupuesto = defined('FS_PRESUPUESTO') ? FS_PRESUPUESTO : 'presupuesto';
    $pedido = defined('FS_PEDIDO') ? FS_PEDIDO : 'pedido';
    $albaran = defined('FS_ALBARAN') ? FS_ALBARAN : 'albarán';

    return [
        ['tipo' => 'presupuesto', 'nombre' => ucfirst($presupuesto) . ' para cliente'],
        ['tipo' => 'pedido', 'nombre' => ucfirst($pedido) . ' de cliente'],
        ['tipo' => 'albaran', 'nombre' => ucfirst($albaran) . ' de cliente'],
        ['tipo' => 'factura', 'nombre' => 'Factura de cliente'],
    ];
}

/**
 * HTML fragment for an optional print link appended to success messages.
 */
function tpvmod_imprimir_link(string $documentType, int|string $id, ?array $plugins = null): string
{
    $url = tpvmod_imprimir_url($documentType, $plugins);
    if ($url === null) {
        return '';
    }

    return " <a href='" . $url . $id . "'>Imprimir</a>";
}

/**
 * Copy client header fields onto a sales document (presupuesto, pedido, albarán, factura).
 *
 * Same behaviour as ventas_* screens: codcliente is always set; billing address is applied
 * when the client has one marked domfacturacion. TPV/mostrador must not block a sale when
 * the client has no address (e.g. "Cliente por defecto").
 *
 * @param object $documento Sales document model (mutable header properties).
 * @param object $cliente Client model with codcliente, cifnif, razonsocial and get_direcciones().
 */
function tpvmod_aplicar_cliente_a_documento(object $documento, object $cliente): void
{
    $documento->codcliente = $cliente->codcliente;
    $documento->cifnif = $cliente->cifnif;
    $documento->nombrecliente = $cliente->razonsocial;

    foreach ($cliente->get_direcciones() as $d) {
        if ($d->domfacturacion) {
            $documento->apartado = $d->apartado;
            $documento->ciudad = $d->ciudad;
            $documento->coddir = $d->id;
            $documento->codpais = $d->codpais;
            $documento->codpostal = $d->codpostal;
            $documento->direccion = $d->direccion;
            $documento->provincia = $d->provincia;
            return;
        }
    }
}

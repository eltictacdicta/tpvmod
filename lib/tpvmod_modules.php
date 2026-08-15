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
 * Whether factura_pdf1 is among the active plugins.
 *
 * Fallback print path when facturacion_base is unavailable: the
 * factura_pdf1 plugin exposes a generic factura_detallada page that
 * prints all four document types via a &tipo= query parameter.
 */
function tpvmod_has_factura_pdf1(?array $plugins = null): bool
{
    return in_array('factura_pdf1', tpvmod_active_plugins($plugins), true);
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
 * Priority: facturacion_base (ventas_imprimir) → factura_pdf1 (factura_detallada
 * with explicit &tipo=) → null. facturacion_base is preserved as the canonical
 * path; factura_pdf1 is a fallback that works when the legacy plugin is gone.
 *
 * @param 'albaran'|'pedido'|'presupuesto'|'factura' $documentType
 */
function tpvmod_imprimir_url(string $documentType, ?array $plugins = null): ?string
{
    if (tpvmod_has_facturacion_base($plugins)) {
        return match ($documentType) {
            'albaran' => './index.php?page=ventas_imprimir&albaran=TRUE&id=',
            'pedido' => './index.php?page=ventas_imprimir&pedido=TRUE&id=',
            'presupuesto' => './index.php?page=ventas_imprimir&presupuesto=TRUE&id=',
            'factura' => './index.php?page=ventas_imprimir&factura=TRUE&id=',
            default => null,
        };
    }

    if (tpvmod_has_factura_pdf1($plugins)) {
        return match ($documentType) {
            'albaran' => './index.php?page=factura_detallada&tipo=albaran&id=',
            'pedido' => './index.php?page=factura_detallada&tipo=pedido&id=',
            'presupuesto' => './index.php?page=factura_detallada&tipo=presupuesto&id=',
            'factura' => './index.php?page=factura_detallada&tipo=factura&id=',
            default => null,
        };
    }

    return null;
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

/**
 * Effective D1–D4 for a client (group + per-client overrides via clientes_core).
 *
 * @return array{d1: float, d2: float, d3: float, d4: float}
 */
function tpvmod_resolve_cliente_descuentos(object $cliente): array
{
    if (method_exists($cliente, 'getEffectiveDiscounts')) {
        $raw = $cliente->getEffectiveDiscounts();
        return [
            'd1' => (float) ($raw['d1'] ?? 0.0),
            'd2' => (float) ($raw['d2'] ?? 0.0),
            'd3' => (float) ($raw['d3'] ?? 0.0),
            'd4' => (float) ($raw['d4'] ?? 0.0),
        ];
    }

    return ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
}

/**
 * Multiplier after cascading line discounts (same semantics as fbase_calc_due).
 *
 * @param array{d1: float, d2: float, d3: float, d4: float} $discounts
 */
function tpvmod_calc_due_multiplier(array $discounts): float
{
    $factor = 1.0;
    foreach (['d1', 'd2', 'd3', 'd4'] as $key) {
        $factor *= (1.0 - ((float) $discounts[$key]) / 100.0);
    }

    return $factor;
}

/**
 * @param array{d1: float, d2: float, d3: float, d4: float} $discounts
 */
function tpvmod_calc_pvptotal(float $cantidad, float $pvpunitario, array $discounts): float
{
    return $cantidad * $pvpunitario * tpvmod_calc_due_multiplier($discounts);
}

/**
 * PVP unitario a partir de neto de línea (inversa de tpvmod_calc_pvptotal).
 *
 * @param array{d1: float, d2: float, d3: float, d4: float} $discounts
 */
function tpvmod_calc_pvp_from_neto(float $neto, float $cantidad, array $discounts): float
{
    $due = tpvmod_calc_due_multiplier($discounts);
    if ($cantidad == 0.0 || $due == 0.0) {
        return 0.0;
    }

    return $neto / ($cantidad * $due);
}

/**
 * Total de línea con impuestos: neto * (1 + (iva - irpf + recargo) / 100).
 */
function tpvmod_calc_line_total(float $neto, float $iva, float $irpf, float $recargo): float
{
    return $neto + ($neto * ($iva - $irpf + $recargo) / 100.0);
}

/**
 * Neto de línea a partir del total con impuestos (inversa de tpvmod_calc_line_total).
 */
function tpvmod_calc_neto_from_total(float $total, float $iva, float $irpf, float $recargo): float
{
    $taxFactor = 100.0 + $iva - $irpf + $recargo;

    return $taxFactor != 0.0 ? (100.0 * $total / $taxFactor) : 0.0;
}

/**
 * orden en BD: ORDER BY orden DESC (la primera línea visible lleva el valor más alto).
 */
function tpvmod_line_orden_from_position(int $positionOneBased, int $totalLines): int
{
    if ($totalLines <= 0 || $positionOneBased <= 0) {
        return 0;
    }

    return $totalLines - $positionOneBased + 1;
}

function tpvmod_apply_line_orden(object $linea, int $positionOneBased, int $totalLines): void
{
    $linea->orden = tpvmod_line_orden_from_position($positionOneBased, $totalLines);
}

/**
 * @param array{d1: float, d2: float, d3: float, d4: float} $discounts
 */
function tpvmod_apply_descuentos_a_linea(object $linea, array $discounts): void
{
    $linea->dtopor = (float) $discounts['d1'];
    $linea->dtopor2 = (float) $discounts['d2'];
    $linea->dtopor3 = (float) $discounts['d3'];
    $linea->dtopor4 = (float) $discounts['d4'];
}

/**
 * Apply client effective discounts and derived amounts to a sales line.
 */
function tpvmod_populate_linea_descuentos(
    object $linea,
    object $cliente,
    float $cantidad,
    float $pvpunitario
): void {
    $discounts = tpvmod_resolve_cliente_descuentos($cliente);
    tpvmod_apply_descuentos_a_linea($linea, $discounts);
    $linea->cantidad = $cantidad;
    $linea->pvpunitario = $pvpunitario;
    $linea->pvpsindto = $cantidad * $pvpunitario;
    $linea->pvptotal = tpvmod_calc_pvptotal($cantidad, $pvpunitario, $discounts);
}

/**
 * Impuestos en JSON plano para JavaScript del TPV (iva + recargo).
 *
 * @param iterable<object> $impuestos
 * @return list<array{codimpuesto: string, descripcion: string, iva: float, recargo: float}>
 */
function tpvmod_impuestos_tpv_json(iterable $impuestos): array
{
    $result = [];

    foreach ($impuestos as $imp) {
        if (!$imp) {
            continue;
        }

        $result[] = [
            'codimpuesto' => (string) ($imp->codimpuesto ?? ''),
            'descripcion' => (string) ($imp->descripcion ?? ''),
            'iva' => (float) ($imp->iva ?? 0),
            'recargo' => (float) ($imp->recargo ?? 0),
        ];
    }

    return $result;
}

/**
 * JSON payload for the datoscliente AJAX endpoint.
 *
 * @return array<string, mixed>
 */
function tpvmod_datos_cliente_payload(object $cliente): array
{
    $discounts = tpvmod_resolve_cliente_descuentos($cliente);

    return [
        'codcliente' => $cliente->codcliente,
        'regimeniva' => $cliente->regimeniva,
        'recargo' => (bool) $cliente->recargo,
        'd1' => $discounts['d1'],
        'd2' => $discounts['d2'],
        'd3' => $discounts['d3'],
        'd4' => $discounts['d4'],
    ];
}

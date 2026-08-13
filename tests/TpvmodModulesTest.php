<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Unit tests for optional facturacion_base integration helpers.
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

class TpvmodModulesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
    }

    public function testHasFacturacionBaseWhenPluginActive(): void
    {
        $this->assertTrue(tpvmod_has_facturacion_base(['facturacion_base', 'tpvmod']));
    }

    public function testHasFacturacionBaseWhenPluginAbsent(): void
    {
        $this->assertFalse(tpvmod_has_facturacion_base(['tpvmod', 'clientes_facturacion']));
    }

    public function testCajaModuleEnabledOnlyWithFacturacionBase(): void
    {
        $this->assertTrue(tpvmod_caja_module_enabled(['facturacion_base']));
        $this->assertFalse(tpvmod_caja_module_enabled(['clientes_facturacion']));
    }

    public function testTerminalModeEffectiveIgnoresWithoutTerminalWhenCajaModuleOff(): void
    {
        $plugins = ['clientes_facturacion', 'tpvmod'];

        $this->assertSame(
            'with_terminal',
            tpvmod_terminal_mode_effective('without_terminal', $plugins)
        );
    }

    public function testTerminalModeEffectiveHonoursWithoutTerminalWhenCajaModuleOn(): void
    {
        $plugins = ['facturacion_base', 'tpvmod'];

        $this->assertSame(
            'without_terminal',
            tpvmod_terminal_mode_effective('without_terminal', $plugins)
        );
    }

    public function testTerminalModeEffectiveFallsBackOnMalformedValue(): void
    {
        $plugins = ['facturacion_base'];

        $this->assertSame('with_terminal', tpvmod_terminal_mode_effective('maybe', $plugins));
    }

    public function testTerminalSettingsAvailableOnlyWithFacturacionBase(): void
    {
        $this->assertTrue(tpvmod_terminal_settings_available(['facturacion_base']));
        $this->assertFalse(tpvmod_terminal_settings_available(['clientes_facturacion']));
    }

    public function testImprimirUrlReturnsNullWithoutFacturacionBase(): void
    {
        $this->assertNull(tpvmod_imprimir_url('albaran', ['clientes_facturacion']));
    }

    public function testImprimirUrlReturnsVentasImprimirWithFacturacionBase(): void
    {
        $plugins = ['facturacion_base'];

        $this->assertSame(
            './index.php?page=ventas_imprimir&albaran=TRUE&id=',
            tpvmod_imprimir_url('albaran', $plugins)
        );
        $this->assertSame(
            './index.php?page=ventas_imprimir&pedido=TRUE&id=',
            tpvmod_imprimir_url('pedido', $plugins)
        );
    }

    public function testImprimirLinkEmptyWhenModuleUnavailable(): void
    {
        $this->assertSame('', tpvmod_imprimir_link('albaran', 42, ['tpvmod']));
    }

    public function testImprimirLinkRenderedWhenModuleAvailable(): void
    {
        $link = tpvmod_imprimir_link('albaran', 99, ['facturacion_base']);

        $this->assertStringContainsString('ventas_imprimir', $link);
        $this->assertStringContainsString('id=99', $link);
    }

    public function testHasFacturaPdf1WhenPluginActive(): void
    {
        $this->assertTrue(tpvmod_has_factura_pdf1(['factura_pdf1', 'tpvmod']));
    }

    public function testHasFacturaPdf1WhenPluginAbsent(): void
    {
        $this->assertFalse(tpvmod_has_factura_pdf1(['tpvmod', 'clientes_facturacion']));
    }

    public function testImprimirUrlReturnsNullWithEmptyPluginList(): void
    {
        $this->assertNull(tpvmod_imprimir_url('albaran', []));
        $this->assertNull(tpvmod_imprimir_url('factura', []));
    }

    public function testImprimirUrlReturnsFacturaDetalladaWithFacturaPdf1ForAlbaran(): void
    {
        $this->assertSame(
            './index.php?page=factura_detallada&tipo=albaran&id=',
            tpvmod_imprimir_url('albaran', ['factura_pdf1'])
        );
    }

    public function testImprimirUrlReturnsFacturaDetalladaWithFacturaPdf1ForPresupuesto(): void
    {
        $this->assertSame(
            './index.php?page=factura_detallada&tipo=presupuesto&id=',
            tpvmod_imprimir_url('presupuesto', ['factura_pdf1'])
        );
    }

    public function testImprimirUrlReturnsFacturaDetalladaWithFacturaPdf1ForPedido(): void
    {
        $this->assertSame(
            './index.php?page=factura_detallada&tipo=pedido&id=',
            tpvmod_imprimir_url('pedido', ['factura_pdf1'])
        );
    }

    public function testImprimirUrlReturnsFacturaDetalladaWithFacturaPdf1ForFactura(): void
    {
        $this->assertSame(
            './index.php?page=factura_detallada&tipo=factura&id=',
            tpvmod_imprimir_url('factura', ['factura_pdf1'])
        );
    }

    public function testImprimirUrlPrefersFacturacionBaseOverFacturaPdf1(): void
    {
        $plugins = ['facturacion_base', 'factura_pdf1'];

        $this->assertSame(
            './index.php?page=ventas_imprimir&albaran=TRUE&id=',
            tpvmod_imprimir_url('albaran', $plugins)
        );
        $this->assertSame(
            './index.php?page=ventas_imprimir&factura=TRUE&id=',
            tpvmod_imprimir_url('factura', $plugins)
        );
    }

    public function testImprimirLinkRendersFacturaDetalladaWithFacturaPdf1(): void
    {
        $link = tpvmod_imprimir_link('albaran', 123, ['factura_pdf1']);

        $this->assertStringContainsString('factura_detallada', $link);
        $this->assertStringContainsString('tipo=albaran', $link);
        $this->assertStringContainsString('id=123', $link);
    }

    public function testTiposAGuardarEmptyWithoutClientesFacturacion(): void
    {
        $this->assertSame([], tpvmod_tipos_a_guardar(['tpvmod', 'facturacion_base']));
    }

    public function testTiposAGuardarReturnsFourDocumentTypesWithClientesFacturacion(): void
    {
        $tipos = tpvmod_tipos_a_guardar(['clientes_facturacion', 'tpvmod']);

        $this->assertCount(4, $tipos);
        $this->assertSame(
            ['presupuesto', 'pedido', 'albaran', 'factura'],
            array_column($tipos, 'tipo')
        );
    }

    public function testResolveDocumentoCodigosPrefersPostOverDefaults(): void
    {
        $defaults = new class extends \fs_default_items {
            public function codalmacen() { return 'ALM1'; }
            public function codserie() { return 'S1'; }
            public function coddivisa() { return 'EUR'; }
            public function codpago() { return 'CONT'; }
        };

        $empresa = (object) [
            'codalmacen' => 'EMP_ALM',
            'codserie' => 'EMP_S',
            'coddivisa' => 'USD',
            'codpago' => 'TRANS',
        ];

        $codigos = tpvmod_resolve_documento_codigos(
            ['almacen' => 'POST_ALM', 'serie' => 'POST_S', 'divisa' => 'GBP', 'forma_pago' => 'TARJ'],
            null,
            $defaults,
            $empresa
        );

        $this->assertSame([
            'almacen' => 'POST_ALM',
            'serie' => 'POST_S',
            'divisa' => 'GBP',
            'forma_pago' => 'TARJ',
        ], $codigos);
    }

    public function testResolveDocumentoCodigosUsesTerminalThenEmpresa(): void
    {
        $terminal = (object) ['codalmacen' => 'T_ALM', 'codserie' => 'T_S'];
        $empresa = (object) [
            'codalmacen' => 'EMP_ALM',
            'codserie' => 'EMP_S',
            'coddivisa' => 'EUR',
            'codpago' => 'CONT',
        ];

        $codigos = tpvmod_resolve_documento_codigos([], $terminal, null, $empresa);

        $this->assertSame('T_ALM', $codigos['almacen']);
        $this->assertSame('T_S', $codigos['serie']);
        $this->assertSame('EUR', $codigos['divisa']);
        $this->assertSame('CONT', $codigos['forma_pago']);
    }

    public function testResolveDocumentoCodigosDoesNotRequireFacturacionBase(): void
    {
        $empresa = (object) [
            'codalmacen' => 'A1',
            'codserie' => 'F',
            'coddivisa' => 'EUR',
            'codpago' => 'CONT',
        ];

        $codigos = tpvmod_resolve_documento_codigos([], null, null, $empresa);

        $this->assertSame($empresa->codalmacen, $codigos['almacen']);
        $this->assertSame($empresa->codserie, $codigos['serie']);
    }

    public function testMasterDataGapsEmptyWhenAllPresent(): void
    {
        $this->assertSame([], tpvmod_master_data_gaps(1, 1, 1, 1, 1));
    }

    public function testMasterDataGapsListsMissingTables(): void
    {
        $gaps = tpvmod_master_data_gaps(0, 0, 0, 0, 0);

        $this->assertCount(5, $gaps);
        $this->assertSame('almacen', $gaps[0]['key']);
        $this->assertStringContainsString('catalogo_core', $gaps[0]['plugin']);
    }

    public function testAplicarClienteSinDireccionNoBloquea(): void
    {
        $documento = new \stdClass();
        $cliente = new class {
            public string $codcliente = '000001';
            public string $cifnif = '';
            public string $razonsocial = 'Cliente por defecto';

            public function get_direcciones(): array
            {
                return [];
            }
        };

        tpvmod_aplicar_cliente_a_documento($documento, $cliente);

        $this->assertSame('000001', $documento->codcliente);
        $this->assertSame('Cliente por defecto', $documento->nombrecliente);
        $this->assertObjectNotHasProperty('coddir', $documento);
    }

    public function testAplicarClienteUsaDireccionFacturacion(): void
    {
        $documento = new \stdClass();
        $direccion = (object) [
            'domfacturacion' => true,
            'apartado' => '1234',
            'ciudad' => 'Málaga',
            'id' => 7,
            'codpais' => 'ESP',
            'codpostal' => '29001',
            'direccion' => 'Calle Test 1',
            'provincia' => 'Málaga',
        ];
        $cliente = new class ($direccion) {
            public function __construct(private object $direccion) {}

            public string $codcliente = '000002';
            public string $cifnif = '12345678Z';
            public string $razonsocial = 'Cliente con dirección';

            public function get_direcciones(): array
            {
                return [$this->direccion];
            }
        };

        tpvmod_aplicar_cliente_a_documento($documento, $cliente);

        $this->assertSame('000002', $documento->codcliente);
        $this->assertSame(7, $documento->coddir);
        $this->assertSame('Calle Test 1', $documento->direccion);
        $this->assertSame('Málaga', $documento->ciudad);
    }
}

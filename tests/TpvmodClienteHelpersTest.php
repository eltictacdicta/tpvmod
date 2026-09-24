<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_cliente.php';

class TpvmodClienteHelpersTest extends TestCase
{
    public function testCampoDisplayIncludesPhones(): void
    {
        $cliente = (object) [
            'nombre' => 'Acme SL',
            'telefono1' => '600111222',
            'telefono2' => '900333444',
        ];

        $this->assertSame(
            'Acme SL Tlf:600111222 Tlf2:900333444',
            tpvmod_cliente_campo_display($cliente)
        );
    }

    public function testTelefonoDisplayPrefersPrimary(): void
    {
        $cliente = (object) ['telefono1' => '111', 'telefono2' => '222'];
        $this->assertSame('111', tpvmod_cliente_telefono_display($cliente));

        $soloSecundario = (object) ['telefono1' => '', 'telefono2' => '222'];
        $this->assertSame('222', tpvmod_cliente_telefono_display($soloSecundario));
    }

    public function testApplyFromPostMapsDiscountFields(): void
    {
        $cliente = new class () {
            public string $nombre = '';
            public ?float $d1 = null;
            public ?float $d2 = null;
            public ?float $d3 = null;
            public ?float $d4 = null;
            public ?string $codgrupo_descuento = null;
            public bool $descuentos_modified = false;
        };

        tpvmod_cliente_apply_from_post($cliente, [
            'nombre' => 'Cliente TPV',
            'd1' => '5.5',
            'd2' => '0',
            'd3' => '10',
            'd4' => '0',
            'codgrupo_descuento' => '',
        ]);

        $this->assertSame('Cliente TPV', $cliente->nombre);
        $this->assertSame(5.5, $cliente->d1);
        $this->assertSame(10.0, $cliente->d3);
        $this->assertNull($cliente->codgrupo_descuento);
    }

    public function testErrorResponseShape(): void
    {
        $payload = tpvmod_cliente_error_response(['Campo obligatorio']);

        $this->assertFalse($payload['ok']);
        $this->assertSame(['Campo obligatorio'], $payload['errors']);
    }

    public function testGroupChangedDetectsFirstAssignmentAndRemoval(): void
    {
        $this->assertTrue(tpvmod_cliente_group_changed(null, '000001'));
        $this->assertTrue(tpvmod_cliente_group_changed('000001', '000002'));
        $this->assertTrue(tpvmod_cliente_group_changed('000001', null));
        $this->assertFalse(tpvmod_cliente_group_changed('000001', '000001'));
        $this->assertFalse(tpvmod_cliente_group_changed(null, null));
    }

    public function testDiscountValuesNormalizeNullToZero(): void
    {
        $source = (object) ['d1' => null, 'd2' => 5.5, 'd3' => '10', 'd4' => null];

        $this->assertSame(
            ['d1' => 0.0, 'd2' => 5.5, 'd3' => 10.0, 'd4' => 0.0],
            tpvmod_cliente_discount_values($source)
        );
    }

    public function testFirstAssignmentAppliesGroupDefaults(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = null;

        tpvmod_cliente_apply_from_post(
            $cliente,
            [
                'nombre' => 'Nuevo',
                'codgrupo_descuento' => '000001',
                'd1' => '0',
                'd2' => '0',
                'd3' => '0',
                'd4' => '0',
            ],
            $this->groupResolver(['000001' => ['d1' => 30.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0]])
        );

        $this->assertSame('000001', $cliente->codgrupo_descuento);
        $this->assertSame(30.0, $cliente->d1);
        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testGroupChangeOverwritesSubmittedDiscounts(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 15.0;
        $cliente->d2 = 8.0;

        tpvmod_cliente_apply_from_post(
            $cliente,
            [
                'nombre' => 'Editado',
                'codgrupo_descuento' => '000002',
                // Stale values from the previous group: must be discarded.
                'd1' => '15',
                'd2' => '8',
                'd3' => '0',
                'd4' => '0',
            ],
            $this->groupResolver(['000002' => ['d1' => 20.0, 'd2' => 10.0, 'd3' => 5.0, 'd4' => 0.0]])
        );

        $this->assertSame('000002', $cliente->codgrupo_descuento);
        $this->assertSame(20.0, $cliente->d1);
        $this->assertSame(10.0, $cliente->d2);
        $this->assertSame(5.0, $cliente->d3);
        $this->assertSame(0.0, $cliente->d4);
        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testUnchangedGroupKeepsSubmittedOverrides(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 10.0;

        tpvmod_cliente_apply_from_post(
            $cliente,
            [
                'nombre' => 'Editado',
                'codgrupo_descuento' => '000001',
                'd1' => '15',
                'd2' => '0',
                'd3' => '0',
                'd4' => '0',
            ],
            $this->groupResolver(['000001' => ['d1' => 10.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0]])
        );

        $this->assertSame(15.0, $cliente->d1);
        $this->assertTrue($cliente->descuentos_modified);
    }

    public function testRemovingGroupClearsDiscounts(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 15.0;
        $cliente->d2 = 8.0;

        tpvmod_cliente_apply_from_post(
            $cliente,
            [
                'nombre' => 'Sin grupo',
                'codgrupo_descuento' => '',
                'd1' => '15',
                'd2' => '8',
                'd3' => '0',
                'd4' => '0',
            ],
            $this->groupResolver([])
        );

        $this->assertNull($cliente->codgrupo_descuento);
        $this->assertSame(0.0, $cliente->d1);
        $this->assertSame(0.0, $cliente->d2);
    }

    public function testNullGroupDiscountsMatchZeroClientDiscounts(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = '000000';
        $cliente->d1 = 0.0;
        $cliente->d2 = 0.0;
        $cliente->d3 = 0.0;
        $cliente->d4 = 0.0;

        // The "Personalizado" group stores NULL d1-d4: it must not flag the
        // client as modified.
        tpvmod_cliente_sync_descuentos_modified_flag(
            $cliente,
            $this->groupResolver(['000000' => ['d1' => null, 'd2' => null, 'd3' => null, 'd4' => null]])
        );

        $this->assertFalse($cliente->descuentos_modified);
    }

    public function testDifferentGroupDiscountMarksModified(): void
    {
        $cliente = $this->discountCliente();
        $cliente->codgrupo_descuento = '000001';
        $cliente->d1 = 25.0;
        $cliente->d2 = 0.0;
        $cliente->d3 = 0.0;
        $cliente->d4 = 0.0;

        tpvmod_cliente_sync_descuentos_modified_flag(
            $cliente,
            $this->groupResolver(['000001' => ['d1' => 30.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0]])
        );

        $this->assertTrue($cliente->descuentos_modified);
    }

    public function testEmptyClientGroupStaysNullWithoutFallback(): void
    {
        $cliente = $this->discountCliente();

        tpvmod_cliente_apply_from_post($cliente, [
            'nombre' => 'Sin grupo',
            'codgrupo' => '',
            'codgrupo_descuento' => '',
        ]);

        $this->assertNull($cliente->codgrupo);
        $this->assertNotSame('000000', $cliente->codgrupo);
    }

    public function testClientGroupIsMappedVerbatim(): void
    {
        $cliente = $this->discountCliente();

        tpvmod_cliente_apply_from_post($cliente, [
            'codgrupo' => '000001',
            'codgrupo_descuento' => '',
        ]);

        $this->assertSame('000001', $cliente->codgrupo);
    }

    /**
     * @return object{nombre: string, codgrupo: ?string, d1: ?float, d2: ?float, d3: ?float, d4: ?float, codgrupo_descuento: ?string, descuentos_modified: bool}
     */
    private function discountCliente(): object
    {
        return new class () {
            public string $nombre = '';
            public ?string $codgrupo = null;
            public ?float $d1 = null;
            public ?float $d2 = null;
            public ?float $d3 = null;
            public ?float $d4 = null;
            public ?string $codgrupo_descuento = null;
            public bool $descuentos_modified = false;
        };
    }

    /**
     * @param array<string, array{d1: float|null, d2: float|null, d3: float|null, d4: float|null}> $groups
     * @return callable(string): ?object
     */
    private function groupResolver(array $groups): callable
    {
        return static function (string $cod) use ($groups): ?object {
            if (!isset($groups[$cod])) {
                return null;
            }

            return (object) $groups[$cod];
        };
    }
}

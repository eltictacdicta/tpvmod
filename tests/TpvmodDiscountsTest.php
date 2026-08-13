<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodDiscountsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
    }

    public function testResolveClienteDescuentosUsesGetEffectiveDiscounts(): void
    {
        $cliente = new class {
            public function getEffectiveDiscounts(): array
            {
                return ['d1' => 10.0, 'd2' => 5.0, 'd3' => 0.0, 'd4' => 2.0];
            }
        };

        $this->assertSame(
            ['d1' => 10.0, 'd2' => 5.0, 'd3' => 0.0, 'd4' => 2.0],
            tpvmod_resolve_cliente_descuentos($cliente)
        );
    }

    public function testResolveClienteDescuentosDefaultsToZeroWithoutMethod(): void
    {
        $cliente = new \stdClass();

        $this->assertSame(
            ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0],
            tpvmod_resolve_cliente_descuentos($cliente)
        );
    }

    public function testCalcPvptotalAppliesCascadingDiscounts(): void
    {
        $discounts = ['d1' => 10.0, 'd2' => 10.0, 'd3' => 0.0, 'd4' => 0.0];

        $this->assertSame(162.0, tpvmod_calc_pvptotal(2.0, 100.0, $discounts));
    }

    public function testApplyDescuentosALineaSetsDtoporFields(): void
    {
        $linea = new \stdClass();
        $discounts = ['d1' => 12.5, 'd2' => 3.0, 'd3' => 1.0, 'd4' => 0.5];

        tpvmod_apply_descuentos_a_linea($linea, $discounts);

        $this->assertSame(12.5, $linea->dtopor);
        $this->assertSame(3.0, $linea->dtopor2);
        $this->assertSame(1.0, $linea->dtopor3);
        $this->assertSame(0.5, $linea->dtopor4);
    }

    public function testPopulateLineaFromClienteAppliesDiscountsAndPvptotal(): void
    {
        $linea = new \stdClass();
        $cliente = new class {
            public function getEffectiveDiscounts(): array
            {
                return ['d1' => 10.0, 'd2' => 10.0, 'd3' => 0.0, 'd4' => 0.0];
            }
        };

        tpvmod_populate_linea_descuentos($linea, $cliente, 2.0, 100.0);

        $this->assertSame(10.0, $linea->dtopor);
        $this->assertSame(10.0, $linea->dtopor2);
        $this->assertSame(162.0, $linea->pvptotal);
        $this->assertSame(200.0, $linea->pvpsindto);
    }

    public function testDatosClientePayloadIncludesDiscounts(): void
    {
        $payload = tpvmod_datos_cliente_payload(new class {
            public string $codcliente = '000002';
            public string $regimeniva = 'General';
            public bool $recargo = false;

            public function getEffectiveDiscounts(): array
            {
                return ['d1' => 20.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
            }
        });

        $this->assertSame('000002', $payload['codcliente']);
        $this->assertSame(20.0, $payload['d1']);
        $this->assertSame(0.0, $payload['d4']);
    }
}

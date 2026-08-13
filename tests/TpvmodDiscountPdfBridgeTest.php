<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Bridge smoke: line populated by tpvmod helpers matches the shape
 * expected by factura_pdf1 discount rendering.
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodDiscountPdfBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
    }

    public function testPopulateLineaProducesPdfDiscountFields(): void
    {
        $linea = new \stdClass();
        $cliente = new class {
            public function getEffectiveDiscounts(): array
            {
                return ['d1' => 10.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
            }
        };

        tpvmod_populate_linea_descuentos($linea, $cliente, 1.0, 100.0);

        $this->assertSame(10.0, $linea->dtopor);
        $this->assertSame(0.0, $linea->dtopor2);
        $this->assertSame(0.0, $linea->dtopor3);
        $this->assertSame(0.0, $linea->dtopor4);
        $this->assertSame(100.0, $linea->pvpsindto);
        $this->assertSame(90.0, $linea->pvptotal);
        $this->assertSame(100.0, $linea->pvpunitario);
    }
}

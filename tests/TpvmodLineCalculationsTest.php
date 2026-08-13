<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodLineCalculationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
    }

    public function testNetoFromPvpWithCascadingDiscounts(): void
    {
        $discounts = ['d1' => 10.0, 'd2' => 10.0, 'd3' => 0.0, 'd4' => 0.0];

        $this->assertSame(81.0, tpvmod_calc_pvptotal(1.0, 100.0, $discounts));
    }

    public function testPvpFromNetoInvertsDiscountMultiplier(): void
    {
        $discounts = ['d1' => 10.0, 'd2' => 10.0, 'd3' => 0.0, 'd4' => 0.0];
        $neto = tpvmod_calc_pvptotal(2.0, 50.0, $discounts);

        $this->assertSame(50.0, tpvmod_calc_pvp_from_neto($neto, 2.0, $discounts));
    }

    public function testPvpFromNetoWithFortyPercentEffectiveDiscount(): void
    {
        // Caso del TPV: total 300 con 21% IVA => neto ~247.9339, pvp ~413.2231 con 40% dto.
        $discounts = ['d1' => 40.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
        $neto = tpvmod_calc_neto_from_total(300.0, 21.0, 0.0, 0.0);

        $this->assertEqualsWithDelta(247.9339, $neto, 0.0001);

        $pvp = tpvmod_calc_pvp_from_neto($neto, 1.0, $discounts);
        $this->assertEqualsWithDelta(413.2231, $pvp, 0.0001);
        $this->assertEqualsWithDelta($neto, tpvmod_calc_pvptotal(1.0, $pvp, $discounts), 0.0001);
    }

    public function testLineTotalAndInverseNeto(): void
    {
        $neto = 180.0;
        $total = tpvmod_calc_line_total($neto, 21.0, 0.0, 0.0);

        $this->assertSame(217.8, $total);
        $this->assertSame($neto, tpvmod_calc_neto_from_total($total, 21.0, 0.0, 0.0));
    }

    public function testManualLineWithoutDiscounts(): void
    {
        $discounts = ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];
        $neto = tpvmod_calc_pvptotal(1.0, 180.0, $discounts);

        $this->assertSame(180.0, $neto);
        $this->assertSame(217.8, tpvmod_calc_line_total($neto, 21.0, 0.0, 0.0));
        $this->assertSame(180.0, tpvmod_calc_pvp_from_neto($neto, 1.0, $discounts));
    }

    public function testPvpFromNetoReturnsZeroWhenCantidadIsZero(): void
    {
        $discounts = ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0];

        $this->assertSame(0.0, tpvmod_calc_pvp_from_neto(100.0, 0.0, $discounts));
    }
}

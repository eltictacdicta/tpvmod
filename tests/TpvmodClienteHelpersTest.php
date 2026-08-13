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
}

<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodOpcionalesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_opcionales.php';
    }

    public function testFormatOpcionalLineDescriptionAddsPrefix(): void
    {
        $this->assertSame('Opcional: Toallero', tpvmod_format_opcional_line_description('Toallero'));
    }

    public function testFormatOpcionalLineDescriptionKeepsExistingPrefix(): void
    {
        $this->assertSame(
            'Opcional: Toallero',
            tpvmod_format_opcional_line_description('Opcional: Toallero')
        );
    }

    public function testIsOpcionalLineDescriptionDetectsPrefix(): void
    {
        $this->assertTrue(tpvmod_is_opcional_line_description('Opcional: Percha'));
        $this->assertFalse(tpvmod_is_opcional_line_description('OLIMPO Percha'));
    }

    public function testOpcionalesForArticuloReturnsEmptyWithoutCatalogoCore(): void
    {
        $this->assertSame(['grupos' => [], 'sueltos' => []], tpvmod_opcionales_for_articulo('REF001', 100.0, null, []));
    }

    public function testValidateObligatoriosPostReturnsEmptyWithoutCatalogoCore(): void
    {
        $this->assertSame([], tpvmod_validate_obligatorios_post([
            'numlineas' => 1,
            'referencia_1' => 'ART001',
        ]));
    }

    public function testOpcionalLineTextStripsPrefix(): void
    {
        $this->assertSame('Colores: Rojo', tpvmod_opcional_line_text('Opcional: Colores: Rojo'));
    }

    public function testMatchOpcionalLineInPayloadFindsGroupedSelection(): void
    {
        $payload = [
            'grupos' => [[
                'id' => 5,
                'nombre' => 'Colores',
                'obligatorio' => true,
                'opcionales' => [[
                    'id' => 21,
                    'descripcion' => 'Rojo',
                ]],
            ]],
            'sueltos' => [],
        ];

        $match = tpvmod_match_opcional_line_in_payload('0021', 'Opcional: Colores: Rojo', $payload);

        $this->assertNotNull($match);
        $this->assertSame(21, $match['opcional_id']);
        $this->assertSame(5, $match['grupo_id']);
        $this->assertSame('0021', $match['parent_ref']);
    }

    public function testJsIncludesObligatorioValidation(): void
    {
        $js = file_get_contents(FS_FOLDER . '/plugins/tpvmod/view/js/tpvmod.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('tpvmod_validate_obligatorios_before_save', $js);
        $this->assertStringContainsString('tpvmod_obligatorios_by_ref', $js);
        $this->assertStringContainsString('tpvmod_parent_ref_', $js);
    }

    public function testJsIncludesOpcionalHelpers(): void
    {
        $js = file_get_contents(FS_FOLDER . '/plugins/tpvmod/view/js/tpvmod.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('tpvmod_add_opcional_linea', $js);
        $this->assertStringContainsString('tpvmod_reorder_opcionales', $js);
        $this->assertStringContainsString('tpvmod_show_opcionales_modal', $js);
        $this->assertStringContainsString('tpvmod_pick_opcional', $js);
        $this->assertStringContainsString(':not(.tpvmod-line-opcional)', $js);
        $this->assertStringContainsString('data-parent-uid', $js);
        $this->assertStringContainsString('data-line-uid', $js);
        $this->assertStringContainsString('tpvmod_get_parent_line_context_by_uid', $js);
        $this->assertStringContainsString('tpvmod_format_precio', $js);
        $this->assertStringContainsString('tpvmod_normalize_opcionales_payload', $js);
        $this->assertStringContainsString('tpvmod_remove_opcional_in_grupo', $js);
        $this->assertStringContainsString('data-grupo-id', $js);
    }
}

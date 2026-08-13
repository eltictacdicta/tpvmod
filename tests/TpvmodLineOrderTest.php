<?php
declare(strict_types=1);
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 */

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodLineOrderTest extends TestCase
{
    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
    }

    public function testFirstLineGetsHighestOrdenValue(): void
    {
        $this->assertSame(3, tpvmod_line_orden_from_position(1, 3));
        $this->assertSame(2, tpvmod_line_orden_from_position(2, 3));
        $this->assertSame(1, tpvmod_line_orden_from_position(3, 3));
    }

    public function testApplyLineOrdenSetsModelProperty(): void
    {
        $linea = new \stdClass();
        tpvmod_apply_line_orden($linea, 2, 4);

        $this->assertSame(3, $linea->orden);
    }

    public function testInvalidPositionReturnsZero(): void
    {
        $this->assertSame(0, tpvmod_line_orden_from_position(0, 3));
        $this->assertSame(0, tpvmod_line_orden_from_position(1, 0));
    }

    public function testLineEditorIncludesSortableAndDeleteHelpers(): void
    {
        $js = file_get_contents(FS_FOLDER . '/plugins/tpvmod/view/js/tpvmod.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('tpvmod_init_lineas_sortable', $js);
        $this->assertStringContainsString('tpvmod_eliminar_linea', $js);
        $this->assertStringContainsString('tpvmod_renumber_lineas', $js);
        $this->assertStringContainsString('.sortable', $js);
    }

    public function testEditTemplateIncludesDragHandle(): void
    {
        $twig = file_get_contents(FS_FOLDER . '/plugins/tpvmod/view/tpvmodedita.html.twig');
        $this->assertIsString($twig);
        $this->assertStringContainsString('tpvmod-line-handle', $twig);
        $this->assertStringContainsString('tpvmod_eliminar_linea', $twig);
    }
}

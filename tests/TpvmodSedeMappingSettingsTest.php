<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * WU-3 (design cases 36-44): the sede -> document-type mapping section on the
 * tpvmod settings page. The persistence helpers live in
 * `plugins/tpvmod/lib/tpvmod_sede_mapping.php` so they are unit-testable
 * without instantiating `fs_controller` (which requires a DB and a session).
 *
 * The controller and view contracts are verified as source contracts because
 * `tpvmod_settings` cannot be instantiated DB-free (AD-14); the DB-free
 * substitutes are these source assertions plus the injected spies below.
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

final class TpvmodSedeMappingSettingsTest extends TestCase
{
    private string $pluginDir;

    private string $viewPath;

    private string $controllerPath;

    /** @var array<string>|null */
    private ?array $pluginsBefore = null;

    /** @var array<string, mixed>|null */
    private ?array $config2Before = null;

    protected function setUp(): void
    {
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_modules.php';
        require_once FS_FOLDER . '/plugins/tpvmod/lib/tpvmod_sede_mapping.php';

        $this->pluginDir = FS_FOLDER . '/plugins/tpvmod';
        $this->viewPath = $this->pluginDir . '/view/tpvmod_settings.html.twig';
        $this->controllerPath = $this->pluginDir . '/controller/tpvmod_settings.php';

        $this->pluginsBefore = $GLOBALS['plugins'] ?? null;
        $this->config2Before = $GLOBALS['config2'] ?? null;

        $GLOBALS['config2'] = [];
        $GLOBALS['plugins'] = [];
    }

    protected function tearDown(): void
    {
        if ($this->pluginsBefore === null) {
            unset($GLOBALS['plugins']);
        } else {
            $GLOBALS['plugins'] = $this->pluginsBefore;
        }

        if ($this->config2Before === null) {
            unset($GLOBALS['config2']);
        } else {
            $GLOBALS['config2'] = $this->config2Before;
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Pure helpers (design cases 36-41)
    // ------------------------------------------------------------------

    public function testSedeMappingSubmittedDetectsItsOwnMarker(): void
    {
        $this->assertTrue(tpvmod_sede_mapping_submitted(['save_sede_mapping' => '1']));
        $this->assertFalse(tpvmod_sede_mapping_submitted([]));
        $this->assertFalse(
            tpvmod_sede_mapping_submitted(['tpvmod_terminal_mode' => 'with_terminal']),
            'a terminal-mode POST must never be mistaken for a mapping POST'
        );
    }

    public function testNormalizeSedeMappingUsesCanonicalOrderAndNullsEmptyValues(): void
    {
        $this->assertSame(
            ['presupuesto' => null, 'albaran' => null, 'pedido' => null, 'factura' => 'S1'],
            tpvmod_normalize_sede_mapping(['sede_factura' => 'S1'])
        );

        $this->assertSame(
            ['presupuesto' => null, 'albaran' => null, 'pedido' => null, 'factura' => null],
            tpvmod_normalize_sede_mapping(['sede_factura' => ''])
        );

        $this->assertSame(
            'S1',
            tpvmod_normalize_sede_mapping(['sede_factura' => ' S1 '])['factura']
        );
    }

    public function testRoundTripPersistsTheFourCanonicalPairs(): void
    {
        $calls = [];
        $existsCalls = [];

        $result = tpvmod_save_sede_mapping(
            [
                'save_sede_mapping' => '1',
                'sede_presupuesto' => 'P1',
                'sede_albaran' => '',
                'sede_pedido' => 'PE2',
                'sede_factura' => 'F3',
            ],
            function (string $tipo, ?string $codsede) use (&$calls): bool {
                $calls[] = [$tipo, $codsede];

                return true;
            },
            function (string $codsede) use (&$existsCalls): bool {
                $existsCalls[] = $codsede;

                return true;
            }
        );

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(
            [
                ['presupuesto', 'P1'],
                ['albaran', null],
                ['pedido', 'PE2'],
                ['factura', 'F3'],
            ],
            $calls,
            'exactly one setMappingFor call per canonical type, in canonical order'
        );
        $this->assertSame(['presupuesto', 'albaran', 'pedido', 'factura'], $result['saved']);
        $this->assertSame(['P1', 'PE2', 'F3'], $existsCalls, 'empty values are never probed');
    }

    public function testOnlyCanonicalTiposArePersisted(): void
    {
        $calls = [];

        $result = tpvmod_save_sede_mapping(
            [
                'save_sede_mapping' => '1',
                'sede_presupuesto' => 'P1',
                'sede_desconocido' => 'X9',
                'sede_factura' => 'F1',
            ],
            function (string $tipo, ?string $codsede) use (&$calls): bool {
                $calls[] = [$tipo, $codsede];

                return true;
            },
            static fn (string $codsede): bool => true
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(
            ['presupuesto', 'albaran', 'pedido', 'factura'],
            array_column($calls, 0),
            'an unknown tipo must never reach setMappingFor'
        );
    }

    public function testInvalidCodsedeIsRejectedWithoutWriting(): void
    {
        $calls = [];

        $result = tpvmod_save_sede_mapping(
            ['save_sede_mapping' => '1', 'sede_factura' => 'NOPE'],
            function (string $tipo, ?string $codsede) use (&$calls): bool {
                $calls[] = [$tipo, $codsede];

                return true;
            },
            static fn (string $codsede): bool => false
        );

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['saved']);
        $this->assertSame([], $calls, 'a rejected codsede must write nothing at all');
        $this->assertNotEmpty($result['errors']);
    }

    public function testEmptySelectionClearsTheOverride(): void
    {
        $calls = [];

        $result = tpvmod_save_sede_mapping(
            ['save_sede_mapping' => '1', 'sede_factura' => ''],
            function (string $tipo, ?string $codsede) use (&$calls): bool {
                $calls[] = [$tipo, $codsede];

                return true;
            },
            static fn (string $codsede): bool => true
        );

        $this->assertTrue($result['ok']);
        $this->assertContains(['factura', null], $calls);
    }

    public function testPersistenceFailureAbortsWithAnError(): void
    {
        $calls = [];

        $result = tpvmod_save_sede_mapping(
            ['save_sede_mapping' => '1', 'sede_factura' => 'F1'],
            function (string $tipo, ?string $codsede) use (&$calls): bool {
                $calls[] = [$tipo, $codsede];

                return count($calls) < 3;
            },
            static fn (string $codsede): bool => true
        );

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(
            [['presupuesto', null], ['albaran', null], ['pedido', null]],
            $calls,
            'the write loop must abort on the first failure'
        );
        $this->assertSame(['presupuesto', 'albaran'], $result['saved']);
    }

    // ------------------------------------------------------------------
    // Regression: the mapping section must not inherit the terminal gate
    // ------------------------------------------------------------------

    public function testMappingRendersAndPersistsWithFacturacionBaseInactive(): void
    {
        $GLOBALS['plugins'] = ['tpvmod', 'clientes_facturacion', 'catalogo_core'];

        $this->assertFalse(
            tpvmod_terminal_settings_available(),
            'precondition: the terminal gate is closed without facturacion_base'
        );

        $view = (string) file_get_contents($this->viewPath);
        $terminalGate = strpos($view, '{% endif %}');
        $mappingForm = strpos($view, 'name="save_sede_mapping"');
        $this->assertNotFalse($terminalGate);
        $this->assertNotFalse($mappingForm);
        $this->assertGreaterThan(
            $terminalGate,
            $mappingForm,
            'the mapping form must sit outside the terminal gate so it renders without facturacion_base'
        );

        $written = [];
        $result = tpvmod_save_sede_mapping(
            ['save_sede_mapping' => '1', 'sede_factura' => 'S1'],
            function (string $tipo, ?string $codsede) use (&$written): bool {
                $written[$tipo] = $codsede;

                return true;
            },
            static fn (string $codsede): bool => true
        );

        $this->assertTrue($result['ok'], 'persistence must not depend on plugin availability');
        $this->assertSame('S1', $written['factura']);
    }

    // ------------------------------------------------------------------
    // View contract (design cases 42 + 44)
    // ------------------------------------------------------------------

    public function testSettingsViewRendersTheMappingForm(): void
    {
        $view = (string) file_get_contents($this->viewPath);

        $this->assertStringContainsString('{{ csrf_field() }}', $view);
        $this->assertStringContainsString('name="save_sede_mapping"', $view);

        foreach (['sede_presupuesto', 'sede_albaran', 'sede_pedido', 'sede_factura'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $view);
        }

        $this->assertStringContainsString(
            '<option value="">',
            $view,
            'each selector needs an explicit no-override / base-company option'
        );
    }

    public function testMappingOptionsRenderEscapedSedeLabels(): void
    {
        $view = (string) file_get_contents($this->viewPath);

        $this->assertStringContainsString('{{ sede.descripcion ?: sede.nombre }}', $view);
        $this->assertStringNotContainsString('sede.descripcion|raw', $view);
        $this->assertStringNotContainsString('sede.nombre|raw', $view);

        $mappingStart = strpos($view, 'name="save_sede_mapping"');
        $this->assertNotFalse($mappingStart);

        $mappingBlock = substr($view, $mappingStart);
        $this->assertStringNotContainsString('|raw', $mappingBlock, 'user text must never be rendered raw');
    }

    // ------------------------------------------------------------------
    // Controller contract (design case 43) + terminal-mode unchanged
    // ------------------------------------------------------------------

    public function testControllerRunsTheMappingBranchBeforeTheTerminalGate(): void
    {
        $src = (string) file_get_contents($this->controllerPath);

        $marker = strpos($src, 'save_sede_mapping');
        $gateCall = strpos($src, 'tpvmod_terminal_settings_available(');

        $this->assertNotFalse($marker, 'the controller must own the mapping POST marker');
        $this->assertNotFalse($gateCall);
        $this->assertLessThan(
            $gateCall,
            $marker,
            'the mapping branch must run before the facturacion_base terminal gate'
        );
        $this->assertSame(
            1,
            substr_count($src, 'tpvmod_terminal_settings_available('),
            'the terminal gate must be consulted exactly once (a docblock cannot satisfy the ordering)'
        );
    }

    public function testControllerValidatesCsrfInsideTheMappingPath(): void
    {
        $src = (string) file_get_contents($this->controllerPath);

        $methodPos = strpos($src, 'function saveSedeMapping');
        $this->assertNotFalse($methodPos, 'the mapping POST must be handled by saveSedeMapping()');

        $methodBody = substr($src, $methodPos);
        $this->assertStringContainsString('isCsrfValid()', $methodBody);
    }

    public function testTerminalModeFlowStaysUnchanged(): void
    {
        $src = (string) file_get_contents($this->controllerPath);

        $this->assertStringContainsString('if (!$this->terminal_settings_available)', $src);
        $this->assertStringContainsString(
            "'La configuración de terminal requiere el plugin facturacion_base activo.'",
            $src
        );
        $this->assertStringContainsString("['with_terminal', 'without_terminal']", $src);
        $this->assertStringContainsString('tpvmod_terminal_mode', $src);

        $view = (string) file_get_contents($this->viewPath);
        $this->assertStringContainsString('name="tpvmod_terminal_mode"', $view);
        $this->assertStringContainsString('value="with_terminal"', $view);
        $this->assertStringContainsString('value="without_terminal"', $view);
    }
}

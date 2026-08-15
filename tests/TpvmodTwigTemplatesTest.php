<?php
/**
 * This file is part of tpvmod.
 * Copyright (C) 2026 Javier Trujillo <mistertekcom@gmail.com>
 *
 * Unit tests for the Twig view layer inventory and controller CSRF cleanup.
 */

declare(strict_types=1);

namespace Tests\Tpvmod;

use PHPUnit\Framework\TestCase;

class TpvmodTwigTemplatesTest extends TestCase
{
    private string $pluginDir;

    private string $viewDir;

    protected function setUp(): void
    {
        $this->pluginDir = FS_FOLDER . '/plugins/tpvmod';
        $this->viewDir = $this->pluginDir . '/view';
    }

    public function testNoLegacyHtmlFilesRemain(): void
    {
        $found = glob($this->viewDir . '/**/*.html', GLOB_BRACE)
            ?: glob($this->viewDir . '/*.html')
            ?: [];

        if ($found === []) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->viewDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.html')) {
                    $found[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $found, 'no RainTPL .html files should remain');
    }

    public function testAllExpectedTwigTemplatesExist(): void
    {
        $expected = [
            'tpvmod.html.twig',
            'tpvmod2.html.twig',
            'tpvmod_settings.html.twig',
            'tpvmodedita.html.twig',
            'tpvmod_facturas.html.twig',
            'tpvmod_albaranes.html.twig',
            'tpvmod_pedidos.html.twig',
            'tpvmod_presupuestos.html.twig',
            'parts/modalguardar.html.twig',
            'partials/modal_clientes.html.twig',
            'ajax/tpv_recambios.html.twig',
            'ajax/tpv_cambios_precios.html.twig',
            'ajax/tpv_clientes.html.twig',
            'ajax/tpv_cliente_form.html.twig',
            'ajax/ventas_lineas_facturas.html.twig',
            'ajax/ventas_lineas_albaranes.html.twig',
            'ajax/ventas_lineas_pedidos.html.twig',
            'ajax/ventas_lineas_presupuestos.html.twig',
            'extension/ventas_facturas_articulo.html.twig',
            'extension/ventas_albaranes_articulo.html.twig',
            'extension/ventas_pedidos_articulo.html.twig',
            'extension/ventas_presupuestos_articulo.html.twig',
        ];

        foreach ($expected as $relativePath) {
            $path = $this->viewDir . '/' . $relativePath;
            $this->assertFileExists($path, 'missing template: ' . $relativePath);
            $this->assertFileIsReadable($path, 'unreadable template: ' . $relativePath);
            $this->assertGreaterThan(0, filesize($path), 'empty template: ' . $relativePath);
        }
    }

    public function testViewsNoLongerUseClienteAutocomplete(): void
    {
        $views = [
            'tpvmod2.html.twig',
            'tpvmodedita.html.twig',
            'tpvmod_albaranes.html.twig',
            'tpvmod_pedidos.html.twig',
            'tpvmod_presupuestos.html.twig',
            'tpvmod_facturas.html.twig',
        ];

        foreach ($views as $view) {
            $content = file_get_contents($this->viewDir . '/' . $view);
            $this->assertIsString($content);
            $this->assertStringNotContainsString('devbridgeAutocomplete', $content, $view);
            $this->assertStringContainsString('tpvmod-b-buscar-cliente', $content, $view);
        }
    }

    public function testControllersDropCsrfWorkaround(): void
    {
        foreach (['controller/tpvmod.php', 'controller/tpvmod_albaranes.php', 'controller/tpvmod_pedidos.php'] as $relativePath) {
            $content = file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertIsString($content);
            $this->assertStringContainsString('tpvmod_cliente_ajax_dispatch', $content, $relativePath);
            $this->assertStringNotContainsString('function buscar_cliente', $content, $relativePath);
        }
    }

    public function testLineEditorTableIncludesDynamicTaxColumns(): void
    {
        foreach (['tpvmod2.html.twig', 'tpvmodedita.html.twig'] as $view) {
            $content = file_get_contents($this->viewDir . '/' . $view);
            $this->assertIsString($content);
            $this->assertStringContainsString('class="text-right recargo"', $content, $view);
            $this->assertStringContainsString('id="are"', $content, $view);
            $this->assertStringContainsString('R.E.', $content, $view);
        }
    }

    public function testLegacyControllersDropCsrfWorkaround(): void
    {
        foreach (['controller/tpvmod.php', 'controller/tpvmod_settings.php'] as $relativePath) {
            $content = file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertIsString($content);
            $this->assertStringNotContainsString('csrf_field', $content, $relativePath);
            $this->assertStringNotContainsString('fs_session_manager', $content, $relativePath);
        }
    }

    public function testTpvmodJsIncludesUnsavedChangesGuard(): void
    {
        $content = file_get_contents($this->pluginDir . '/view/js/tpvmod.js');
        $this->assertIsString($content);
        $this->assertStringContainsString('function tpvmod_has_unsaved_changes()', $content);
        $this->assertStringContainsString('beforeunload.tpvmod', $content);
        $this->assertStringContainsString('function tpvmod_mark_submitted()', $content);
    }
}

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
    /** @var list<string> */
    private const LISTING_CONTROLLERS = [
        'controller/tpvmod_presupuestos.php',
        'controller/tpvmod_facturas.php',
        'controller/tpvmod_albaranes.php',
        'controller/tpvmod_pedidos.php',
    ];

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
            'partials/modal_opcionales.html.twig',
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

    public function testDiscountGroupSelectExposesDefaultsAndHandler(): void
    {
        $content = file_get_contents($this->viewDir . '/ajax/tpv_cliente_form.html.twig');
        $this->assertIsString($content);
        $this->assertStringContainsString('onchange="tpvmodClienteGrupoDescuentoChange(this)"', $content);
        $this->assertStringContainsString('data-d1=', $content);
        $this->assertStringContainsString('data-d4=', $content);

        $js = file_get_contents($this->pluginDir . '/view/js/tpvmod-cliente.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('function tpvmodClienteGrupoDescuentoChange(select)', $js);
    }

    public function testGroupSelectsAreMandatoryWithoutSinGrupoOption(): void
    {
        $content = file_get_contents($this->viewDir . '/ajax/tpv_cliente_form.html.twig');
        $this->assertIsString($content);

        foreach (['codgrupo', 'codgrupo_descuento'] as $name) {
            $this->assertMatchesRegularExpression(
                '/<select name="' . $name . '"[^>]*required/',
                $content,
                $name . ' must be a mandatory select'
            );
        }

        // Neither group may offer a selectable "Sin grupo" option: the
        // mandatory group gate lives in cliente::test() and an empty option
        // would only produce a confusing save error.
        $this->assertSame(
            0,
            substr_count($content, 'Sin grupo'),
            'no group selector must offer a selectable "Sin grupo" option'
        );
    }

    public function testGuardarClienteRunsNativeConstraintValidation(): void
    {
        $js = file_get_contents($this->pluginDir . '/view/js/tpvmod-cliente.js');
        $this->assertIsString($js);
        $this->assertStringContainsString('document.f_cliente_tpv.reportValidity()', $js);
    }

    public function testTpvmodJsIncludesUnsavedChangesGuard(): void
    {
        $content = file_get_contents($this->pluginDir . '/view/js/tpvmod.js');
        $this->assertIsString($content);
        $this->assertStringContainsString('function tpvmod_has_unsaved_changes()', $content);
        $this->assertStringContainsString('beforeunload.tpvmod', $content);
        $this->assertStringContainsString('function tpvmod_mark_submitted()', $content);
    }

    public function testOpcionalesModalIsSharedByBothScreens(): void
    {
        $partialPath = $this->viewDir . '/partials/modal_opcionales.html.twig';
        $this->assertFileExists($partialPath);

        foreach (['tpvmod2.html.twig', 'tpvmodedita.html.twig'] as $view) {
            $content = file_get_contents($this->viewDir . '/' . $view);
            $this->assertIsString($content);
            $this->assertStringContainsString(
                "{% include 'partials/modal_opcionales.html.twig' %}",
                $content,
                $view
            );
            $this->assertStringNotContainsString('id="modal_opcionales"', $content, $view);
        }
    }

    public function testOpcionalesPartialHasTabsListAndSiblingForm(): void
    {
        $content = file_get_contents($this->viewDir . '/partials/modal_opcionales.html.twig');
        $this->assertIsString($content);

        $this->assertStringContainsString('id="modal_opcionales"', $content);
        $this->assertStringContainsString('data-toggle="tab"', $content);
        $this->assertStringContainsString('tab-pane', $content);
        $this->assertStringContainsString('nav nav-tabs', $content);
        $this->assertStringContainsString('{{ csrf_field() }}', $content);
        $this->assertStringContainsString('name="guardar_opcional_tpv"', $content);
        $this->assertStringContainsString('name="nombre"', $content);
        $this->assertStringContainsString('name="descripcion"', $content);
        $this->assertStringContainsString('name="tipo_precio"', $content);
        $this->assertStringContainsString('name="valor"', $content);
        $this->assertStringContainsString('id="tpvmod_opcionales_list"', $content);
        $this->assertStringContainsString('id="tpvmod_opcional_nuevo_form"', $content);
        $this->assertStringNotContainsString('data-toggle="collapse"', $content);
        $this->assertStringNotContainsString('|raw', $content);

        $listPos = strpos($content, 'id="tpvmod_opcionales_list"');
        $formPos = strpos($content, 'id="tpvmod_opcional_nuevo_form"');
        $this->assertNotFalse($listPos);
        $this->assertNotFalse($formPos);
        $this->assertLessThan($formPos, $listPos, 'the form container must be a sibling after the list');
    }

    public function testListingControllersUseSharedSearchHelper(): void
    {
        foreach (self::LISTING_CONTROLLERS as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);

            $this->assertStringContainsString('tpvmod_search_term(', $content, $relativePath);
            $this->assertStringContainsString('tpvmod_build_search_predicate(', $content, $relativePath);
            $this->assertStringContainsString('tpvmod_normalize_date(', $content, $relativePath);
            $this->assertMatchesRegularExpression(
                '/\$this->desde\s*=\s*tpvmod_normalize_date\(/',
                $content,
                $relativePath . ' must normalize desde'
            );
            $this->assertMatchesRegularExpression(
                '/\$this->hasta\s*=\s*tpvmod_normalize_date\(/',
                $content,
                $relativePath . ' must normalize hasta'
            );

            $buscar = $this->controllerMethodBody($content, 'buscar');
            $this->assertNotSame('', $buscar, $relativePath . '::buscar not found');
            $this->assertStringContainsString('tpvmod_build_search_predicate(', $buscar, $relativePath . '::buscar');
            $this->assertStringContainsString('$this->var2str(', $buscar, $relativePath . '::buscar');
            $this->assertStringNotContainsString('no_html(', $buscar, $relativePath . '::buscar must not HTML-escape the SQL predicate');
        }
    }

    public function testListingControllersGateCronAndBuildUrls(): void
    {
        foreach (self::LISTING_CONTROLLERS as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);

            $this->assertStringContainsString('list_url(', $content, $relativePath);

            $paginas = $this->controllerMethodBody($content, 'paginas');
            $this->assertNotSame('', $paginas, $relativePath . '::paginas not found');
            $this->assertStringContainsString('tpvmod_pager_links(', $paginas, $relativePath);
            $this->assertStringNotContainsString('"&mostrar="', $paginas, $relativePath);
            $this->assertStringNotContainsString('"&query="', $paginas, $relativePath);
            $this->assertStringNotContainsString('"&offset="', $paginas, $relativePath);
        }

        // Only presupuestos and pedidos invoke a model cron_job(); both must
        // gate it on isHtmxRequest() so a swap does not run its UPDATEs.
        foreach (['controller/tpvmod_presupuestos.php', 'controller/tpvmod_pedidos.php'] as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertStringContainsString('cron_job(', $content, $relativePath);
            $this->assertMatchesRegularExpression(
                '/isHtmxRequest\(\)\s*\)\s*\{?\s*\$[A-Za-z_]+->cron_job\(/s',
                $content,
                $relativePath . ' must gate cron_job() on isHtmxRequest()'
            );
        }

        foreach (['controller/tpvmod_facturas.php', 'controller/tpvmod_albaranes.php'] as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertStringNotContainsString(
                'cron_job(',
                $content,
                $relativePath . ' must not gate a cron_job() it never calls'
            );
        }
    }

    public function testNoLocalHtmxDetectionHelper(): void
    {
        $scanned = 0;
        foreach ($this->pluginSourceFiles() as $path) {
            $content = (string) file_get_contents($path);
            $scanned++;
            $this->assertStringNotContainsString('is_htmx_request', $content, $path);
            $this->assertStringNotContainsString('tpvmod_is_htmx_request', $content, $path);
        }

        $this->assertGreaterThan(0, $scanned, 'the plugin source scan must cover at least one file');

        // The htmx gate delegates to fs_controller::isHtmxRequest().
        foreach (['controller/tpvmod_presupuestos.php', 'controller/tpvmod_pedidos.php'] as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertStringContainsString('isHtmxRequest()', $content, $relativePath);
        }
    }

    public function testListingControllersUseBatchedPhoneLookup(): void
    {
        foreach (self::LISTING_CONTROLLERS as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);

            $this->assertStringContainsString('public function telefono_cliente(', $content, $relativePath);

            $lookup = $this->controllerMethodBody($content, 'telefonos_pagina');
            $this->assertNotSame('', $lookup, $relativePath . '::telefonos_pagina not found');
            $this->assertStringContainsString('tpvmod_phone_map(', $lookup, $relativePath);
            $this->assertStringContainsString('FROM clientes', $lookup, $relativePath);
            $this->assertStringContainsString('codcliente IN (', $lookup, $relativePath);
            $this->assertSame(
                1,
                substr_count($lookup, 'FROM clientes'),
                $relativePath . ' must run exactly one batched clientes lookup'
            );
        }
    }

    public function testFacturasLineSearchPassesOffset(): void
    {
        $content = (string) file_get_contents($this->pluginDir . '/controller/tpvmod_facturas.php');

        $buscarLineas = $this->controllerMethodBody($content, 'buscar_lineas');
        $this->assertNotSame('', $buscarLineas, 'tpvmod_facturas::buscar_lineas not found');
        $this->assertStringContainsString(
            "'ajax/ventas_lineas_facturas'",
            $buscarLineas,
            'the line search keeps its legacy fragment template'
        );
        $this->assertStringContainsString(
            "search_from_cliente2(\$_POST['codcliente'], \$this->buscar_lineas, \$_POST['buscar_lineas_o'], \$this->offset)",
            $buscarLineas,
            'the client-scoped branch must pass the offset'
        );
        $this->assertStringContainsString(
            'search($this->buscar_lineas, $this->offset)',
            $buscarLineas,
            'the global branch must pass the offset'
        );
    }

    public function testEveryPostFormCarriesCsrfField(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->viewDir, \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }

            $path = $file->getPathname();
            $content = (string) file_get_contents($path);
            if (!str_contains($content, 'method="post"')) {
                continue;
            }

            $checked++;
            $this->assertStringContainsString('{{ csrf_field() }}', $content, $path);
            $this->assertStringNotContainsString('{$fsc->csrf_field', $content, $path);
            $this->assertStringNotContainsString('csrf_field|raw', $content, $path);
        }

        $this->assertGreaterThan(0, $checked, 'at least one POST form must be checked');
    }

    /**
     * Every plugin .php/.twig source file except tests/, vendor/ and the
     * openspec/ prose artifacts.
     *
     * @return list<string>
     */
    private function pluginSourceFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->pluginDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $excluded = ['/tests/', '/vendor/', '/.git/', '/openspec/'];
            foreach ($excluded as $segment) {
                if (str_contains($path, $segment)) {
                    continue 2;
                }
            }

            if (str_ends_with($file->getFilename(), '.php') || str_ends_with($file->getFilename(), '.twig')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Extract a controller method body from its declaration up to the next
     * method declaration. Returns '' when the method is absent. Only visibility
     * declarations terminate a body, so nested closures are kept.
     */
    private function controllerMethodBody(string $source, string $method): string
    {
        $start = strpos($source, 'function ' . $method . '(');
        if ($start === false) {
            return '';
        }

        $tail = substr($source, $start);
        if (preg_match('/\n[ \t]*(?:public|private|protected)\s+function\s+/', $tail, $matches, PREG_OFFSET_CAPTURE) === 1) {
            return substr($tail, 0, (int) $matches[0][1]);
        }

        return $tail;
    }
}

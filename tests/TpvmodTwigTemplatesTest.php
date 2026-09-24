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

    /** @var array<string, string> module key => listing template file */
    private const LISTING_TEMPLATES = [
        'presupuestos' => 'tpvmod_presupuestos.html.twig',
        'facturas' => 'tpvmod_facturas.html.twig',
        'albaranes' => 'tpvmod_albaranes.html.twig',
        'pedidos' => 'tpvmod_pedidos.html.twig',
    ];

    /** @var array<string, string> module key => line-search fragment file */
    private const LINE_FRAGMENTS = [
        'presupuestos' => 'ajax/ventas_lineas_presupuestos.html.twig',
        'facturas' => 'ajax/ventas_lineas_facturas.html.twig',
        'albaranes' => 'ajax/ventas_lineas_albaranes.html.twig',
        'pedidos' => 'ajax/ventas_lineas_pedidos.html.twig',
    ];

    /** @var array<string, list<string>> module key => tab tokens (LHT-04) */
    private const MODULE_TABS = [
        'presupuestos' => ['todo', 'pendientes', 'rechazados', 'buscar'],
        'facturas' => ['todo', 'sinpagar', 'buscar'],
        'albaranes' => ['todo', 'pendientes', 'buscar'],
        'pedidos' => ['todo', 'pendientes', 'rechazados', 'buscar'],
    ];

    /** @var array<string, list<string>> module key => order tokens (LHT-04) */
    private const MODULE_ORDER_TOKENS = [
        'presupuestos' => ['fecha_desc', 'fecha_asc', 'codigo_desc', 'codigo_asc'],
        'facturas' => ['fecha_desc', 'fecha_asc', 'vencimiento_desc', 'vencimiento_asc'],
        'albaranes' => ['fecha_desc', 'fecha_asc', 'codigo_desc', 'codigo_asc'],
        'pedidos' => ['fecha_desc', 'fecha_asc', 'codigo_desc', 'codigo_asc'],
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

    public function testListingsImportHtmxAndAlpineOnce(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            $this->assertSame(
                1,
                substr_count($content, "{% import 'Macro/Htmx.html.twig' as htmx %}"),
                $view . ' must import the htmx macro exactly once'
            );
            $this->assertSame(
                1,
                substr_count($content, "{% import 'Macro/Alpine.html.twig' as alpine %}"),
                $view . ' must import the Alpine macro exactly once'
            );
            $this->assertStringContainsString(
                "htmx.boot({'allowScriptTags': false})",
                $content,
                $view . ' must boot htmx with the fragment scrubber posture'
            );
            $this->assertStringContainsString('alpine.boot()', $content, $view);
            $this->assertStringContainsString('window.__tpvmodListadoRegistered', $content, $view);
            $this->assertStringContainsString('window.__tpvmodListadoSwapBound', $content, $view);
            $this->assertStringContainsString('Alpine.initTree(', $content, $view);
            $this->assertSame(
                1,
                substr_count($content, "'htmx:after:swap'"),
                $view . ' must bind exactly one htmx:after:swap listener'
            );
            $this->assertStringNotContainsString('HtmxCrud.html.twig', $content, $view);
        }

        // The global chrome must stay free of the opt-in assets.
        foreach (['themes/AdminLTE/view/header.html.twig', 'themes/AdminLTE/view/footer.html.twig'] as $relativePath) {
            $content = (string) file_get_contents(FS_FOLDER . '/' . $relativePath);
            $this->assertStringNotContainsString('htmx', $content, $relativePath);
            $this->assertStringNotContainsString('Alpine', $content, $relativePath);
        }
    }

    public function testListingsDeclareStableSwapRegion(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);
            $regionId = $this->regionId($tipo);
            $region = '#' . $regionId;

            $this->assertSame(
                1,
                substr_count($content, 'id="' . $regionId . '"'),
                $view . ' must declare exactly one #' . $regionId
            );
            $this->assertStringContainsString('x-data="tpvmodListado"', $content, $view);

            // LHT-01 control half: every mapped control points at the region
            // through the same four attributes, so no control can drift and
            // replace the whole page instead of the region.
            $targets = substr_count($content, 'hx-target="' . $region . '"');
            $this->assertGreaterThan(0, $targets, $view . ' must map at least one control onto the region');
            $this->assertSame($targets, substr_count($content, 'hx-select="' . $region . '"'), $view . ' hx-select must mirror hx-target');
            $this->assertSame($targets, substr_count($content, 'hx-swap="outerHTML"'), $view . ' every region swap must be outerHTML');
            $this->assertSame($targets, substr_count($content, 'hx-push-url="true"'), $view . ' every region swap must push the URL');
        }
    }

    public function testControlToUrlMapping(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // The filter form posts no token and carries the non-form-derived
            // state as hidden fields (AD-7, §3.2).
            $this->assertStringContainsString('method="get"', $content, $view);
            $this->assertStringContainsString('hx-get="{{ fsc.url() }}"', $content, $view);
            $this->assertStringContainsString('hx-trigger="submit"', $content, $view);
            $this->assertStringContainsString('<input type="hidden" name="mostrar" value="buscar"/>', $content, $view);
            $this->assertStringContainsString('<input type="hidden" name="order"', $content, $view);

            // Each filter owns its key in the base URL; htmx appends the
            // element's own value (AD-4), so the query key stays single.
            foreach (['codserie', 'codagente', 'desde', 'hasta'] as $key) {
                $this->assertStringContainsString(
                    "hx-get=\"{{ fsc.list_url({'mostrar': 'buscar', 'offset': 0}, ['" . $key . "']) }}\"",
                    $content,
                    $view . ' must map the ' . $key . ' filter with its own key omitted'
                );
            }
            $this->assertSame(
                4,
                substr_count($content, 'hx-trigger="change"'),
                $view . ' must bind the four filter change triggers'
            );

            foreach (self::MODULE_TABS[$tipo] as $tab) {
                $this->assertStringContainsString(
                    "hx-get=\"{{ fsc.list_url({'mostrar': '" . $tab . "', 'offset': 0}) }}\"",
                    $content,
                    $view . ' must map the ' . $tab . ' tab'
                );
            }

            foreach (self::MODULE_ORDER_TOKENS[$tipo] as $token) {
                $this->assertStringContainsString(
                    "hx-get=\"{{ fsc.list_url({'order': '" . $token . "', 'offset': 0}) }}\"",
                    $content,
                    $view . ' must map the ' . $token . ' order token'
                );
            }

            // Pagination reuses the server-built pager URL for href and hx-get.
            $this->assertStringContainsString('hx-get="{{ value[\'url\'] }}"', $content, $view);

            // Forbidden htmx surface: hx-params does not exist in the vendored
            // htmx 4, and hx-include would duplicate keys (AD-4).
            $this->assertStringNotContainsString('hx-params', $content, $view);
            $this->assertStringNotContainsString('hx-include', $content, $view);
        }

        // Repo-wide guard: hx-params must not appear anywhere in the plugin.
        foreach ($this->pluginSourceFiles() as $path) {
            $content = (string) file_get_contents($path);
            $this->assertStringNotContainsString('hx-params', $content, $path);
        }
    }

    public function testRegionContainsMappedControls(): void
    {
        $moduleExtras = [
            'presupuestos' => 'id="modal_rechazar"',
            'facturas' => 'id="modal_huecos"',
        ];

        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);
            $region = 'id="' . $this->regionId($tipo) . '"';

            $regionPos = strpos($content, $region);
            $toolbarPos = strpos($content, 'tpvmod-list-toolbar');
            $tabsPos = strpos($content, 'nav nav-tabs');
            $tablePos = strpos($content, 'class="table-responsive"');
            $paginasPos = strpos($content, 'fsc.paginas()');
            $filterPos = strpos($content, 'name="f_custom_search"');
            $lineFormPos = strpos($content, 'id="f_buscar_lineas"');

            foreach ([$regionPos, $toolbarPos, $tabsPos, $tablePos, $paginasPos, $filterPos, $lineFormPos] as $pos) {
                $this->assertNotFalse($pos, $view . ' is missing one of the region anchors');
            }

            $this->assertLessThan($toolbarPos, $regionPos, $view . ' the order toolbar must live inside the region');
            $this->assertLessThan($tabsPos, $toolbarPos, $view . ' the tabs must follow the order toolbar');
            $this->assertLessThan($tablePos, $tabsPos, $view . ' the results table must follow the tabs');
            $this->assertLessThan($paginasPos, $tablePos, $view . ' the pager must follow the results table');
            $this->assertLessThan($filterPos, $paginasPos, $view . ' the filter form must stay outside and below the region');
            $this->assertLessThan($lineFormPos, $filterPos, $view . ' the line-search form must stay outside the region');
            $this->assertLessThan((int) strpos($content, 'id="modal_buscar_lineas"'), $regionPos, $view . ' the line-search modal must live outside the region');

            // The first mapped control must sit inside the region (the order
            // toolbar), never in the filter form below it.
            $firstHxGet = strpos($content, 'hx-get=');
            $this->assertNotFalse($firstHxGet, $view . ' must map at least one control');
            $this->assertGreaterThan($regionPos, $firstHxGet, $view . ' the mapped controls must live inside the region');
            $this->assertLessThan($filterPos, $firstHxGet, $view . ' the mapped controls must not start in the filter form');

            if (isset($moduleExtras[$tipo])) {
                $extraPos = strpos($content, $moduleExtras[$tipo]);
                $this->assertNotFalse($extraPos, $view . ' is missing ' . $moduleExtras[$tipo]);
                $this->assertLessThan($extraPos, $regionPos, $view . ' ' . $moduleExtras[$tipo] . ' must stay outside the region');
            }
        }
    }

    public function testLineSearchFragmentContract(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // Listing half of the line-search contract: htmx POST to the same
            // endpoint, replacing #search_results, with a debounced + synced
            // input (LHT-10, §5.1).
            $this->assertStringContainsString('hx-post="{{ fsc.url() }}"', $content, $view);
            $this->assertStringContainsString('hx-target="#search_results"', $content, $view);
            $this->assertStringContainsString('hx-swap="innerHTML"', $content, $view);
            $this->assertStringContainsString('hx-trigger="submit"', $content, $view);
            $this->assertStringContainsString('hx-sync="this:replace"', $content, $view);
            $this->assertStringContainsString('hx-trigger="keyup changed delay:300ms"', $content, $view);
            $this->assertStringContainsString('hx-sync="closest form:replace"', $content, $view);

            // No-JS fallback and the server-side offset contract stay intact.
            $this->assertStringContainsString('method="post"', $content, $view);
            $this->assertStringContainsString('{{ csrf_field() }}', $content, $view);
            $this->assertStringContainsString('<input type="hidden" name="offset" value="0"/>', $content, $view);

            // The legacy client-side fetch path is gone (TCP-01).
            $this->assertStringNotContainsString('$.ajax', $content, $view);
            $this->assertStringNotContainsString('mas_resultados(', $content, $view);
            $this->assertStringNotContainsString('function buscar_lineas(', $content, $view);
        }
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

    private function regionId(string $tipo): string
    {
        return 'tpvmod-' . $tipo . '-region';
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

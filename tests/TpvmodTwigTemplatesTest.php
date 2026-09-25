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
        $pickerViews = ['tpvmod2.html.twig', 'tpvmodedita.html.twig'];
        $listingViews = array_values(self::LISTING_TEMPLATES);

        // All six drop the legacy devbridge autocomplete.
        foreach (array_merge($pickerViews, $listingViews) as $view) {
            $content = file_get_contents($this->viewDir . '/' . $view);
            $this->assertIsString($content);
            $this->assertStringNotContainsString('devbridgeAutocomplete', $content, $view);
        }

        // The client modal survives only on the two TPV line editors.
        foreach ($pickerViews as $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);
            $this->assertStringContainsString('tpvmod-b-buscar-cliente', $content, $view);
        }

        // The four listings no longer open the client modal.
        foreach ($listingViews as $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);
            $this->assertStringNotContainsString('tpvmod-b-buscar-cliente', $content, $view);
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

    public function testGuardarClienteValidatesOnlyItsOwnControls(): void
    {
        $js = file_get_contents($this->pluginDir . '/view/js/tpvmod-cliente.js');
        $this->assertIsString($js);

        // Mandatory client controls (name and both group selects) are still
        // validated natively before the AJAX save.
        $this->assertStringContainsString('function tpvmodClienteFormularioValido()', $js);
        $this->assertStringContainsString('if (!tpvmodClienteFormularioValido())', $js);
        $this->assertStringContainsString('element.reportValidity()', $js);

        // An invalid control inside an inactive tab pane is not focusable: its
        // tab must be activated before reporting so the message stays visible.
        $this->assertStringContainsString("closest('.tab-pane')", $js);
        $this->assertStringContainsString(".tab('show')", $js);

        // The address sub-form has its own save button: its required fields
        // must never gate the client save.
        $this->assertStringContainsString("getElementById('f_direccion_tpv')", $js);
        $this->assertStringContainsString('direccionForm.contains(element)', $js);
    }

    public function testGuardarDireccionValidatesItsRequiredAddress(): void
    {
        $js = file_get_contents($this->pluginDir . '/view/js/tpvmod-cliente.js');
        $this->assertIsString($js);

        $this->assertStringContainsString("form.querySelector('[name=\"direccion\"]')", $js);
        $this->assertStringContainsString('direccion.reportValidity()', $js);
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

    public function testListingsRenderPhoneColumn(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // LHT-06: every row resolves its phone through the controller's
            // batched accessor; the document row never carries a phone column.
            $this->assertStringContainsString(
                'fsc.telefono_cliente(value.codcliente)',
                $content,
                $view . ' must render the batched phone accessor per row'
            );
            $this->assertStringNotContainsString(
                'value.telefono1',
                $content,
                $view . ' must not read telefono1 from the document row'
            );
            $this->assertStringNotContainsString(
                'value.telefono2',
                $content,
                $view . ' must not read telefono2 from the document row'
            );
        }
    }

    public function testListingsRenderCitySnapshot(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // LHT-07: the city is the document billing snapshot, rendered
            // read-only; no address lookup is added to the listing path.
            $this->assertStringContainsString(
                '{{ value.ciudad }}',
                $content,
                $view . ' must render the billing-city snapshot'
            );
        }

        foreach (self::LISTING_CONTROLLERS as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertStringNotContainsString('dirclientes', $content, $relativePath);
            $this->assertStringNotContainsString('domfacturacion', $content, $relativePath);
        }
    }

    public function testListingViewsExcludeClientPicker(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // LHT-08: the client picker is gone from the listings, while the
            // &codcliente= filter survives as read-only text + a clear control.
            foreach (['ac_cliente', 'tpvmod-b-buscar-cliente', 'partials/modal_clientes.html.twig', 'tpvmod-cliente.js', 'clean_cliente'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $content, $view . ' must not carry ' . $forbidden);
            }

            $this->assertStringContainsString(
                'id="tpvmod-cliente-activo"',
                $content,
                $view . ' must show the active client as read-only text'
            );
            $this->assertMatchesRegularExpression(
                '/id="tpvmod-cliente-activo"[^>]*readonly="readonly"/',
                $content,
                $view . ' the active-client field must be read-only'
            );
            $this->assertStringContainsString(
                "fsc.list_url({'codcliente': '', 'mostrar': 'buscar', 'offset': 0})",
                $content,
                $view . ' must offer a clear control that drops the client filter'
            );
        }
    }

    public function testNoDatepickerAndNativeDates(): void
    {
        // LHT-09: every date surface is a native <input type="date">. The
        // jQuery UI `.datepicker` class is gone (it kept the legacy widget
        // coupling and forced a re-init after every htmx swap). The motivation
        // is the removed dependency, not a range-filter bug.
        $dateViews = array_merge(array_values(self::LISTING_TEMPLATES), ['tpvmodedita.html.twig']);

        $nativeDates = 0;
        foreach ($dateViews as $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            $this->assertStringNotContainsString('datepicker', $content, $view . ' must not use the datepicker class');
            $nativeDates += substr_count($content, 'type="date"');
        }

        // 4 listings x (desde + hasta) + the Rechazar field + tpvmodedita fecha.
        $this->assertSame(10, $nativeDates, 'exactly ten native date inputs must replace the ten datepicker fields');

        // Listings: desde/hasta are ISO-prefilled through the core `date_iso`
        // filter (no new filter) and keep their htmx change trigger.
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            foreach (['desde', 'hasta'] as $name) {
                $tag = $this->inputTagFor($content, $name);
                $this->assertStringContainsString('type="date"', $tag, $view . ' ' . $name . ' must be a native date input');
                $this->assertStringContainsString(
                    'value="{{ fsc.' . $name . '|date_iso }}"',
                    $tag,
                    $view . ' ' . $name . ' must be prefilled through date_iso'
                );
                $this->assertStringContainsString('hx-trigger="change"', $tag, $view . ' ' . $name . ' must keep its htmx change trigger');
            }
        }

        // Rechazar modal: today's ISO date (a native input only carries ISO).
        $presupuestos = (string) file_get_contents($this->viewDir . '/tpvmod_presupuestos.html.twig');
        $rechazar = $this->inputTagFor($presupuestos, 'rechazar');
        $this->assertStringContainsString('type="date"', $rechazar, 'the Rechazar input must be a native date input');
        $this->assertStringContainsString('value="{{ \'now\'|date(\'Y-m-d\') }}"', $rechazar);

        // tpvmodedita: the document date is converted from the model format.
        $edita = (string) file_get_contents($this->viewDir . '/tpvmodedita.html.twig');
        $fecha = $this->inputTagFor($edita, 'fecha');
        $this->assertStringContainsString('type="date"', $fecha, 'the tpvmodedita fecha input must be a native date input');
        $this->assertStringContainsString('value="{{ fsc.documento.fecha|date_iso }}"', $fecha);

        // Controller side (U6) keeps the ISO normalization, and the tpvmod
        // finoferta math reads the native ISO value unchanged: the old
        // d-m-Y datepicker value and the new Y-m-d one both parse, so this is
        // a no-op assertion pinning the read contract.
        foreach (self::LISTING_CONTROLLERS as $relativePath) {
            $content = (string) file_get_contents($this->pluginDir . '/' . $relativePath);
            $this->assertStringContainsString('tpvmod_normalize_date(', $content, $relativePath);
        }
        $tpvmod = (string) file_get_contents($this->pluginDir . '/controller/tpvmod.php');
        $this->assertMatchesRegularExpression(
            '/date\(\s*"Y-m-d"\s*,\s*strtotime\(\s*\$_POST\[\'fecha\'\]\s*\.\s*" \+30 days"\s*\)\s*\)/',
            $tpvmod,
            'the finoferta math must read the native ISO date'
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

    public function testRegionBoundaryAndOrder(): void
    {
        $moduleExtras = [
            'presupuestos' => 'id="modal_rechazar"',
            'facturas' => 'id="modal_huecos"',
        ];

        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);
            $region = 'id="' . $this->regionId($tipo) . '"';

            $filterPos = strpos($content, 'name="f_custom_search"');
            $regionPos = strpos($content, $region);
            $toolbarPos = strpos($content, 'tpvmod-list-toolbar');
            $tabsPos = strpos($content, 'nav nav-tabs');
            $tablePos = strpos($content, 'class="table-responsive"');
            $paginasPos = strpos($content, 'fsc.paginas()');
            $lineFormPos = strpos($content, 'id="f_buscar_lineas"');

            foreach ([$filterPos, $regionPos, $toolbarPos, $tabsPos, $tablePos, $paginasPos, $lineFormPos] as $pos) {
                $this->assertNotFalse($pos, $view . ' is missing one of the region anchors');
            }

            // LHT-03 final order: the filter form precedes the region, so a
            // swap never replaces the filter inputs; the region owns the order
            // toolbar, the tabs, the table and the pager.
            $this->assertLessThan($regionPos, $filterPos, $view . ' the filter form must precede the region');
            $this->assertLessThan($toolbarPos, $regionPos, $view . ' the order toolbar must live inside the region');
            $this->assertLessThan($tabsPos, $toolbarPos, $view . ' the tabs must follow the order toolbar');
            $this->assertLessThan($tablePos, $tabsPos, $view . ' the results table must follow the tabs');
            $this->assertLessThan($paginasPos, $tablePos, $view . ' the pager must follow the results table');

            // Modals stay outside the region (TCP-06): the line-search form
            // and the module extras close after the region.
            $this->assertLessThan($lineFormPos, $regionPos, $view . ' the line-search form must stay outside the region');
            $this->assertLessThan((int) strpos($content, 'id="modal_buscar_lineas"'), $regionPos, $view . ' the line-search modal must live outside the region');

            if (isset($moduleExtras[$tipo])) {
                $extraPos = strpos($content, $moduleExtras[$tipo]);
                $this->assertNotFalse($extraPos, $view . ' is missing ' . $moduleExtras[$tipo]);
                $this->assertLessThan($extraPos, $regionPos, $view . ' ' . $moduleExtras[$tipo] . ' must stay outside the region');
            }
        }
    }

    public function testFilterBarRendersInEveryListingState(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            $formPos = strpos($content, 'name="f_custom_search"');
            $this->assertNotFalse($formPos, $view . ' must render the f_custom_search bar');
            $formPos = (int) $formPos;

            // LHT-13: the filter bar renders in every `mostrar` state, so the
            // form block must not sit inside the inherited
            // `{% if fsc.mostrar == 'buscar' %}` guard. Structural check: no
            // buscar-guard may still be open at the form's byte offset, so
            // this cannot drift with an occurrence count.
            $this->assertFalse(
                $this->isInsideMostrarBuscarGuard($content, $formPos),
                $view . ' must not wrap the filter form in a mostrar == buscar guard'
            );

            // The reorder contract survives: the bar stays above the region
            // and therefore outside the swapped tree (LHT-03).
            $regionPos = strpos($content, 'id="' . $this->regionId($tipo) . '"');
            $this->assertNotFalse($regionPos, $view . ' is missing its region wrapper');
            $this->assertLessThan((int) $regionPos, $formPos, $view . ' the filter form must stay above the region');

            // Only the two documented buscar guards remain: the autofocus
            // script and the buscar tab's active class. The form guard was the
            // third and is the one removed here.
            $this->assertSame(
                2,
                substr_count($content, "{% if fsc.mostrar == 'buscar' %}"),
                $view . ' must keep exactly the autofocus and active-tab buscar guards'
            );

            $focusPos = strpos($content, 'document.f_custom_search.query.focus();');
            $this->assertNotFalse($focusPos, $view . ' must keep the autofocus script');
            $this->assertTrue(
                $this->isInsideMostrarBuscarGuard($content, (int) $focusPos),
                $view . ' the autofocus script must stay behind the buscar guard'
            );
        }
    }

    /**
     * LHT-14 (AD-15, §4.1): the bar lives outside the swapped region and is
     * never re-rendered, so after every swap the single existing
     * `htmx:after:swap` listener must re-synchronize the label, the hidden
     * `codcliente` and the non-text controls' `hx-get` in place from the URL
     * htmx already pushed (`location.search`). A second listener is forbidden
     * and the call must run outside the Alpine guard (no-Alpine safety).
     */
    public function testFilterBarResyncsAfterSwap(): void
    {
        foreach (self::LISTING_TEMPLATES as $tipo => $view) {
            $content = (string) file_get_contents($this->viewDir . '/' . $view);

            // One swap listener only, and it owns exactly one re-sync call.
            $this->assertSame(
                1,
                substr_count($content, "'htmx:after:swap'"),
                $view . ' must keep exactly one htmx:after:swap listener'
            );
            $this->assertSame(
                1,
                substr_count($content, 'tpvmodResyncFilterBar();'),
                $view . ' must call the filter-bar re-sync exactly once'
            );
            $this->assertStringContainsString(
                'function tpvmodResyncFilterBar()',
                $content,
                $view . ' must define the filter-bar re-sync'
            );

            // It must run before and outside the Alpine guard, so the bar is
            // re-synchronized even when Alpine is absent.
            $resyncPos = strpos($content, 'tpvmodResyncFilterBar();');
            $alpineGuardPos = strpos($content, 'if (!window.Alpine');
            $alpineInitPos = strpos($content, 'window.Alpine.initTree(');
            $this->assertNotFalse($resyncPos, $view . ' must call the re-sync');
            $this->assertNotFalse($alpineGuardPos, $view . ' must keep the Alpine guard');
            $this->assertNotFalse($alpineInitPos, $view . ' must re-init Alpine after a swap');
            $this->assertLessThan(
                (int) $alpineGuardPos,
                (int) $resyncPos,
                $view . ' the re-sync must run before the Alpine guard'
            );
            $this->assertLessThan(
                (int) $alpineInitPos,
                (int) $resyncPos,
                $view . ' the re-sync must run before Alpine.initTree'
            );

            // State source: the URL htmx pushed before `after:swap`.
            $this->assertStringContainsString(
                'window.location.search',
                $content,
                $view . ' the re-sync must read the current listing URL'
            );

            // Label and hidden field follow the URL's codcliente, in place.
            $this->assertStringContainsString("#tpvmod-cliente-activo'", $content, $view);
            $this->assertStringContainsString("'input[name=\"codcliente\"]'", $content, $view);
            $this->assertStringContainsString("label.value = ''", $content, $view);
            $this->assertStringContainsString('hidden.value = codcliente', $content, $view);

            // Every non-text control is rebuilt from its own server hx-get
            // prefix, and the clear control is addressable by a stable hook.
            foreach (['select[name="codserie"]', 'select[name="codagente"]', 'input[name="desde"]', 'input[name="hasta"]'] as $selector) {
                $this->assertStringContainsString(
                    "'" . $selector . "'",
                    $content,
                    $view . ' must address ' . $selector . ' in the re-sync'
                );
            }
            $this->assertStringContainsString(
                'data-tpvmod-role="cliente-clear"',
                $content,
                $view . ' must expose a stable client-clear hook'
            );
            $this->assertStringContainsString(
                '[data-tpvmod-role="cliente-clear"]',
                $content,
                $view . ' the re-sync must address the client-clear hook'
            );

            // Own-key algebra: each non-text control drops its own key, while
            // the clear control drops codcliente (LHT-14/§4.1: it must keep
            // omitting the client, not set it to an empty value).
            $ownKeys = [
                ['select[name="codserie"]', 'codserie'],
                ['select[name="codagente"]', 'codagente'],
                ['input[name="desde"]', 'desde'],
                ['input[name="hasta"]', 'hasta'],
            ];
            foreach ($ownKeys as [$selector, $key]) {
                $this->assertStringContainsString(
                    "['" . $selector . "', '" . $key . "']",
                    $content,
                    $view . ' must drop ' . $key . ' when rebuilding ' . $selector
                );
            }
            $this->assertStringContainsString(
                "tpvmodSwapRebuild(clear.getAttribute('hx-get'), 'codcliente')",
                $content,
                $view . ' the clear control must keep omitting codcliente'
            );

            // The URL rebuild reuses key-level URLSearchParams algebra over
            // each control's own server-rendered prefix — no raw concatenation.
            $this->assertStringContainsString('new URLSearchParams(', $content, $view);
            $this->assertStringContainsString('params.delete(', $content, $view);
            $this->assertStringContainsString("params.set('mostrar', 'buscar')", $content, $view);
            $this->assertStringContainsString("params.set('offset', '0')", $content, $view);
            $this->assertStringContainsString("getAttribute('hx-get')", $content, $view);
            $this->assertStringContainsString("setAttribute('hx-get'", $content, $view);
            $this->assertStringContainsString("setAttribute('href'", $content, $view);

            // Text inputs are never replaced: the inline script only assigns
            // values/attributes, it must not re-render the bar.
            $script = $this->inlineScriptBlock($content);
            $this->assertNotSame('', $script, $view . ' must keep the nonce script block');
            $this->assertStringNotContainsString('outerHTML', $script, $view);
            $this->assertStringNotContainsString('replaceWith', $script, $view);
            $this->assertStringNotContainsString('innerHTML', $script, $view);
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

        // Fragment half: the four line-search fragments drop the stale-response
        // marker, keep the endpoint/params and page with a server-computed
        // offset so prev/next survive the htmx swap (LHT-10, §5.3, TCP-05).
        foreach (self::LINE_FRAGMENTS as $fragment) {
            $content = (string) file_get_contents($this->viewDir . '/' . $fragment);

            // The marker and the client-side pager arithmetic are gone.
            $this->assertStringNotContainsString('<!--{{ fsc.buscar_lineas }}-->', $content, $fragment);
            $this->assertStringNotContainsString('mas_resultados(', $content, $fragment);
            $this->assertStringNotContainsString('onclick=', $content, $fragment);

            // Well-formed alerts: a <div> per message, never a bare <li>.
            $this->assertStringContainsString('<div>{{ value }}</div>', $content, $fragment);
            $this->assertStringNotContainsString('<li>{{ value }}</li>', $content, $fragment);

            // The pager posts the server-computed offset to the same endpoint,
            // replacing the fragment; facturas gains it here (U14).
            $this->assertStringContainsString('hx-post="{{ fsc.url() }}"', $content, $fragment);
            $this->assertStringContainsString('hx-target="#search_results"', $content, $fragment);
            $this->assertStringContainsString('hx-swap="innerHTML"', $content, $fragment);
            $this->assertStringContainsString('hx-sync="closest form:replace"', $content, $fragment);
            $this->assertStringContainsString('hx-vals=\'{"offset":', $content, $fragment);
            $this->assertStringContainsString('max(0, fsc.offset - fsc.lineas|length)', $content, $fragment);
            $this->assertStringContainsString('fsc.offset + fsc.lineas|length', $content, $fragment);
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
     * Reports whether the byte at $position sits inside an unclosed
     * `{% if fsc.mostrar == 'buscar' %}` block. It walks the if/endif tags
     * before the position keeping the still-open condition stack, so the
     * assertion is structural and survives reformatting or an extra guard.
     */
    private function isInsideMostrarBuscarGuard(string $content, int $position): bool
    {
        preg_match_all(
            '/\{%-?\s*(if\b.*?|endif\b.*?)-?%\}/s',
            substr($content, 0, $position),
            $matches,
            PREG_SET_ORDER
        );

        $open = [];
        foreach ($matches as $match) {
            $tag = trim($match[1]);
            if (str_starts_with($tag, 'endif')) {
                array_pop($open);
                continue;
            }
            $open[] = $tag;
        }

        foreach ($open as $condition) {
            if (str_contains($condition, 'fsc.mostrar') && str_contains($condition, "'buscar'")) {
                return true;
            }
        }

        return false;
    }

    /**
     * The nonce'd classic script block that owns the Alpine registration and
     * the swap listener. Returned without the <script> wrapper so assertions
     * can scope to the JS and ignore the markup's hx-swap="outerHTML".
     */
    private function inlineScriptBlock(string $content): string
    {
        $start = strpos($content, '<script {{ csp_nonce_attr() }}>');
        if ($start === false) {
            return '';
        }

        $end = strpos($content, '</script>', $start);
        if ($end === false) {
            return substr($content, $start);
        }

        return substr($content, $start, $end - $start);
    }

    /**
     * Extract the first <input …> tag carrying the given name attribute, or ''
     * when absent. The negated class matches across newlines, so multi-line
     * tags are captured whole.
     */
    private function inputTagFor(string $content, string $name): string
    {
        $pattern = '/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/';
        if (preg_match($pattern, $content, $matches) === 1) {
            return $matches[0];
        }

        return '';
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

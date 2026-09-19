# Spec: `views` (tpvmod plugin)

> **Canonical source of truth**: `plugins/tpvmod/openspec/specs/views/spec.md`
>
> Established by change `modernize-m3` (2026-07-18).
> Extended by change `tpvmod-opcional-rapido` (2026-09-19).

## ADDED Requirements

### Requirement: All view templates are native Twig 3

The plugin MUST NOT contain any RainTPL `.html` in `view/`. All
view templates MUST be `.html.twig` using idiomatic Twig 3
(`{{ fsc.prop }}`, `{% for %}`, `{% if %}`, `{% include '...html.twig' %}`).

#### Scenario: No legacy .html files remain

- GIVEN the plugin's `view/` directory
- WHEN `find view -name "*.html"` runs
- THEN the result is empty

#### Scenario: All 19 expected Twig templates exist and are non-empty

- GIVEN 19 paths (8 main + 1 part + 1 ajax rewrite + 9 debt-fill;
  full list in proposal §"Success criteria")
- WHEN each is checked with `test -s`
- THEN all 19 exist with non-zero size

### Requirement: CSRF rendered via Twig function only

Every Twig template with `<form method="post">` MUST include
`{{ csrf_field() }}` inside the form. No template MUST contain
`{$fsc->csrf_field}` (RainTPL) or `{{ fsc.csrf_field|raw }}`. This includes the
quick-create form inside the shared opcionales modal partial.
(Previously: applied to every POST form under `view/`; now explicitly names the
shared opcionales modal partial and its quick-create form.)

#### Scenario: No legacy CSRF workaround anywhere in view/

- GIVEN every `.twig` file under `view/`
- WHEN grepped for `fsc.csrf_field` or `csrf_field|raw` or `{$fsc->csrf_field`
- THEN no matches

#### Scenario: Every POST form contains csrf_field

- GIVEN a Twig template with `<form method="post"`
- WHEN the file is parsed
- THEN `{{ csrf_field() }}` appears before the form's `</form>`

#### Scenario: Quick-create form carries the token

- GIVEN `view/partials/modal_opcionales.html.twig`
- WHEN the quick-create form is parsed
- THEN `{{ csrf_field() }}` appears inside its `<form method="post">`

### Requirement: Nine debt-fill templates are created with the right data shape

The 9 templates referenced by controllers that did not exist on
disk MUST exist as `.html.twig` under `view/`. The data fields
each template depends on are pinned below (derived from the
controller call sites and from the upstream twins in
`plugins/facturacion_base/view/`).

| Template | Call site | Populates | Required fields (template body MUST reference) |
|---|---|---|---|
| `view/ajax/tpv_cambios_precios.html.twig` | `tpvmod.php:668` | `fsc.articulo` | `fsc.articulo.{referencia,descripcion,stockfis,pvp,pvp_iva,codimpuesto,imagen_url}`; `fsc.get_tarifas_articulo(referencia)` → `tarifa_nombre`, `pvp`, `dtopor`, `get_iva()` |
| `view/ajax/ventas_lineas_facturas.html.twig` | `tpvmod_facturas.php:308` | `fsc.buscar_lineas`, `fsc.lineas` (linea_factura_cliente) | `fsc.buscar_lineas`; `{% for l in fsc.lineas %}` → `l.{url,show_codigo,cantidad,articulo_url,referencia,descripcion,total_iva,show_fecha}`; `fsc.show_precio(...)`; header `{#FS_FACTURA#}` |
| `view/ajax/ventas_lineas_albaranes.html.twig` | `tpvmod_albaranes.php:305` | same for albaranes | same fields, header `{#FS_ALBARAN#}` |
| `view/ajax/ventas_lineas_pedidos.html.twig` | `tpvmod_pedidos.php:246` | same for pedidos | same fields, header `{#FS_PEDIDO#}` |
| `view/ajax/ventas_lineas_presupuestos.html.twig` | `tpvmod_presupuestos.php:331` | same for presupuestos | same fields, header `{#FS_PRESUPUESTO#}` |
| `view/extension/ventas_facturas_articulo.html.twig` | `tpvmod_facturas.php:114` | `fsc.articulo`, `fsc.resultados` (linea_factura_cliente `all_from_articulo()`) | `fsc.articulo.{referencia,url()}`; `{% for r in fsc.resultados %}` → `r.{url,show_codigo,cantidad,articulo_url,referencia,descripcion,total_iva,show_fecha}`; pagination `{{ fsc.url() }}&ref={{ fsc.articulo.referencia }}&offset=...` |
| `view/extension/ventas_albaranes_articulo.html.twig` | `tpvmod_albaranes.php:111` | same for albaranes | same fields, header `{#FS_ALBARANES#}` |
| `view/extension/ventas_pedidos_articulo.html.twig` | `tpvmod_pedidos.php:112` | same for pedidos | same fields, header `{#FS_PEDIDOS#}` |
| `view/extension/ventas_presupuestos_articulo.html.twig` | `tpvmod_presupuestos.php:115` | same for presupuestos | same fields, header `{#FS_PRESUPUESTOS#}` |

#### Scenario: ajax/tpv_cambios_precios renders article + tariffs

- GIVEN `tpvmod.php:668` sets `$this->template = 'ajax/tpv_cambios_precios'`
- AND the controller populates only `$this->articulo`
- WHEN the template renders
- THEN it shows the article + a tariffs table via
  `{{ fsc.get_tarifas_articulo(fsc.articulo.referencia) }}`
- (Scope: best-effort — controller provides only `$this->articulo`;
  no `equivalentes`/`familia`/`fabricante` data, so those tabs are
  omitted.)

#### Scenario: 8 ventas templates reference the documented fields

- GIVEN the 4 `buscar_lineas()` branches and the 4 `$_GET['ref']`
  branches per the table above
- WHEN `TpvmodTwigTemplatesTest` greps each template body
- THEN every field listed in the table for that row is referenced

### Requirement: Controllers drop RainTPL CSRF workaround

`controller/tpvmod.php` and `controller/tpvmod_settings.php` MUST
NOT contain `public $csrf_field;`,
`$this->csrf_field = \fs_session_manager::csrfField();`, or any
`require_once` of `base/fs_session_manager.php`. The
`fs_settings.php` require is allowed to stay.

#### Scenario: Grep audit of the two modified controllers

- GIVEN `controller/tpvmod.php` and `controller/tpvmod_settings.php`
- WHEN grepped for `csrf_field` and `fs_session_manager`
- THEN no matches in the modified portions

### Requirement: Plugin Composer infrastructure is present and versioned

The plugin MUST have `Init.php` (namespace
`FSFramework\Plugins\tpvmod`, `declare(strict_types=1)`),
`composer.json`, `composer.lock`, `composer_autoload.php`, and a
versioned `vendor/`. `composer_autoload.php` MUST
`require_once __DIR__ . '/vendor/autoload.php'` and MUST write an
`error_log` directive when vendor is missing.

#### Scenario: Init.php namespace and autoload wiring

- GIVEN the plugin is activated
- WHEN the framework loads `Init::init()`
- THEN the namespace is registered
- AND `vendor/autoload.php` is loaded without errors when present
- AND `Init::init()` is a no-op (no listeners, no extensions)

#### Scenario: vendor/ is versioned, lockfile matches

- GIVEN a fresh git clone of the plugin (no `composer install`)
- WHEN any class in the namespace is autoloaded
- THEN autoloading succeeds
- AND `.gitignore` does NOT contain `/vendor/`
- AND `ddev exec composer install --dry-run --no-interaction` reports
  no changes against the committed `composer.lock` and `vendor/`

### Requirement: fsframework.ini description reflects Twig view layer

`fsframework.ini` `description` MUST be updated to mention the
Twig 3 view engine so plugin managers discover the new layer.

#### Scenario: description mentions Twig

- GIVEN `plugins/tpvmod/fsframework.ini`
- WHEN the file is read
- THEN `description` contains the substring `Twig` (case-insensitive)

### Requirement: Test suite covers template inventory and controller cleanup

`tests/TpvmodTwigTemplatesTest.php` MUST exist with at least:
(a) zero `.html` files left under `view/`,
(b) all 19 expected `.html.twig` files exist and are non-empty,
(c) the two modified controllers no longer contain `csrf_field` or
`fs_session_manager` require/population. No DB, no fixtures.

#### Scenario: All 3 assertions pass

- GIVEN the plugin in its post-`modernize-m3` state
- WHEN `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`
  runs the new test class
- THEN the 3 checks all pass
- AND `TpvmodModulesTest` (pre-existing suite) still passes

### Requirement: Shared opcionales modal partial (OD-10, F9)

The `#modal_opcionales` shell MUST live in a single shared partial
(`view/partials/modal_opcionales.html.twig`) included by both
`tpvmod2.html.twig` and `tpvmodedita.html.twig`, mirroring the existing
`partials/modal_clientes.html.twig` precedent. Both screens MUST render the same
modal id and structure so the flow cannot drift.

#### Scenario: Both screens include the partial

- GIVEN `view/tpvmod2.html.twig` and `view/tpvmodedita.html.twig`
- WHEN each is parsed
- THEN both include `partials/modal_opcionales.html.twig`
- AND neither redefines the `#modal_opcionales` shell inline

#### Scenario: Flow is available on both screens

- GIVEN the agent is on `tpvmod2` (new ticket) or `tpvmodedita` (document edit)
- WHEN the agent opens the "Añadir opcional" modal
- THEN the same existing-selection list and quick-create form are available on both

### Requirement: Quick-create form is a sibling of the render target (F1)

Inside the modal, `#tpvmod_opcionales_list` MUST remain the render target whose
inner HTML the list renderer replaces. The quick-create form MUST live in a
sibling container (`#tpvmod_opcional_nuevo_form`), never inside
`#tpvmod_opcionales_list`, so a re-render of the list cannot destroy the form.

#### Scenario: Form survives a list re-render

- GIVEN the agent typed data into the quick-create form
- WHEN the list renderer replaces the inner HTML of `#tpvmod_opcionales_list`
- THEN the form container and its values are still present in the DOM

#### Scenario: Renderer targets only the list

- GIVEN the shared modal partial is rendered
- WHEN the list renderer runs
- THEN it replaces only `#tpvmod_opcionales_list`
- AND it does not remove `#tpvmod_opcional_nuevo_form`

### Requirement: Quick-create form uses the proven BS3 tab pattern (F10)

The modal MUST expose the existing catalog selection and the quick-create form
using the Bootstrap 3 tab pattern proven in-repo
(`nav nav-tabs` + `data-toggle="tab"` + `.tab-pane`), as used by
`view/ajax/tpv_cliente_form.html.twig`. The modal MUST NOT introduce
`data-toggle="collapse"`, which has no in-repo precedent.

#### Scenario: Tabs render for existing and new opcionales

- GIVEN the shared modal partial
- WHEN it renders
- THEN it contains a tab for existing opcionales and a tab for a new opcional
- AND no `data-toggle="collapse"` appears in the partial

#### Scenario: Form fields are present

- GIVEN the quick-create tab is active
- WHEN the form renders
- THEN it contains `nombre` (required), `descripcion`, `tipo_precio` (`fijo`/`porcentaje`), and `valor` fields
- AND it offers **Añadir** and **Añadir y guardar opcional** actions

### Requirement: Twig escaping conventions for user text (F13)

User-provided text (`nombre`, `descripcion`) MUST be rendered through Twig
`{{ }}` and MUST NOT use the `|raw` filter. User text MUST NOT be placed in
single-quoted HTML attributes.

#### Scenario: No raw output of user text

- GIVEN the shared modal partial and both screens
- WHEN grepped for `|raw`
- THEN no `|raw` is applied to `nombre` or `descripcion`

#### Scenario: No user text in single-quoted attributes

- GIVEN the shared modal partial is parsed
- WHEN it emits a user-provided value
- THEN the value is placed as escaped element content
- AND not inside a single-quoted attribute

## REMOVED Requirements

### Requirement: RainTPL as the view engine (REMOVED)

(Reason: Twig 3 migration eliminates every RainTPL artifact from
`view/`. The framework's `themes/AdminLTE` registers Twig globally
and is the only supported theme, so RainTPL no longer renders
anything in this plugin.)
(Migration: every former `.html` has a `.html.twig` counterpart.
No runtime fallback is needed because controllers and the active
theme already speak Twig.)

### Requirement: `public $csrf_field` controller property as CSRF mechanism (REMOVED)

(Reason: existed only because RainTPL rendered `{csrf_field()}`
as literal text. With Twig 3 the `{{ csrf_field() }}` function
(registered by AdminLTE) emits a proper hidden input, so the
controller no longer pre-computes the HTML.)
(Migration: the property and the `fs_session_manager::csrfField()`
call are deleted from `controller/tpvmod.php` and
`controller/tpvmod_settings.php`. Forms now use `{{ csrf_field() }}`
directly. The framework autoloader resolves `fs_session_manager`
for callers that still need it; the modified controllers do not.)

## Cross-references

- Proposal: `plugins/tpvmod/openspec/changes/modernize-m3/proposal.md`
- Related specs (unchanged): `plugins/tpvmod/openspec/specs/tpv-flow/spec.md`,
  `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md`
- Data-shape references for the 9 debt-fill templates: see
  `plugins/facturacion_base/view/extension/ventas_{facturas,albaranes}_articulo.html`
  and `plugins/facturacion_base/view/ajax/ventas_lineas_{facturas,albaranes}.html`.

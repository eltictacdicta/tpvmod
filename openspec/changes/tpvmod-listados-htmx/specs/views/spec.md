# Delta: `views` — tpvmod-listados-htmx

> Delta to the canonical source of truth
> `plugins/tpvmod/openspec/specs/views/spec.md` (established by `modernize-m3`,
> extended by `tpvmod-opcional-rapido`).
> Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Plugin-local SDD: the core `openspec/` is intentionally NOT touched.
>
> This delta changes view-layer conventions for the four tpvmod document
> listings and `tpvmodedita.html.twig`: filter-form placement, the stable swap
> region, native date inputs, the absence of the client picker, the htmx-driven
> line-search fragments, and the pinned template-contract assertions.
> Traceability: `proposal.md` §Capabilities/§Approach; `exploration.md` F1–F9;
> `decisions-pending.md` D4/D5/D7.

## MODIFIED Requirements

### Requirement: CSRF rendered via Twig function only

Every Twig template with `<form method="post">` MUST include
`{{ csrf_field() }}` inside the form. No template MUST contain
`{$fsc->csrf_field}` (RainTPL) or `{{ fsc.csrf_field|raw }}`. This includes the
quick-create form inside the shared opcionales modal partial. The htmx-driven
line-search forms in the four listings MUST keep
`<form method="post">` plus `{{ csrf_field() }}` as the no-JS fallback, while
their htmx POSTs authenticate through the inherited `X-CSRF-TOKEN` header and the
read-only listing GET swaps require no token (HCS-07/HCS-08). No new token
mechanism MUST be introduced.

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

#### Scenario: Line-search forms keep the fallback token

- GIVEN the four listing templates
- WHEN the `#f_buscar_lineas` form is parsed
- THEN it remains `<form method="post">` with `{{ csrf_field() }}`
- AND no extra hidden token input is added for htmx (the header is the htmx token source)

### Requirement: Nine debt-fill templates are created with the right data shape

The 9 templates referenced by controllers that did not exist on
disk MUST exist as `.html.twig` under `view/`. The data fields
each template depends on are pinned below (derived from the
controller call sites and from the upstream twins in
`plugins/facturacion_base/view/`).

| Template | Call site | Populates | Required fields (template body MUST reference) |
|---|---|---|---|
| `view/ajax/tpv_cambios_precios.html.twig` | `tpvmod.php` | `fsc.articulo` | `fsc.articulo.{referencia,descripcion,stockfis,pvp,pvp_iva,codimpuesto,imagen_url}`; `fsc.get_tarifas_articulo(referencia)` → `tarifa_nombre`, `pvp`, `dtopor`, `get_iva()` |
| `view/ajax/ventas_lineas_facturas.html.twig` | `tpvmod_facturas.php` | `fsc.lineas` (linea_factura_cliente), `fsc.offset` | `{% for l in fsc.lineas %}` → `l.{url,show_codigo,cantidad,articulo_url,referencia,descripcion,total_iva,show_fecha}`; `fsc.show_precio(...)`; server-side offset pager (`hx-post` + `hx-vals` `offset`); header `{#FS_FACTURA#}` |
| `view/ajax/ventas_lineas_albaranes.html.twig` | `tpvmod_albaranes.php` | `fsc.lineas` (linea_albaran_cliente), `fsc.offset` | same fields as facturas, server-side offset pager, header `{#FS_ALBARAN#}` |
| `view/ajax/ventas_lineas_pedidos.html.twig` | `tpvmod_pedidos.php` | `fsc.lineas` (linea_pedido_cliente), `fsc.offset` | same fields as facturas, server-side offset pager, header `{#FS_PEDIDO#}` |
| `view/ajax/ventas_lineas_presupuestos.html.twig` | `tpvmod_presupuestos.php` | `fsc.lineas` (linea_presupuesto_cliente), `fsc.offset` | same fields as facturas, server-side offset pager, header `{#FS_PRESUPUESTO#}` |
| `view/extension/ventas_facturas_articulo.html.twig` | `tpvmod_facturas.php` | `fsc.articulo`, `fsc.resultados` (linea_factura_cliente `all_from_articulo()`) | `fsc.articulo.{referencia,url()}`; `{% for r in fsc.resultados %}` → `r.{url,show_codigo,cantidad,articulo_url,referencia,descripcion,total_iva,show_fecha}`; pagination `{{ fsc.url() }}&ref={{ fsc.articulo.referencia }}&offset=...` |
| `view/extension/ventas_albaranes_articulo.html.twig` | `tpvmod_albaranes.php` | same for albaranes | same fields, header `{#FS_ALBARANES#}` |
| `view/extension/ventas_pedidos_articulo.html.twig` | `tpvmod_pedidos.php` | same for pedidos | same fields, header `{#FS_PEDIDOS#}` |
| `view/extension/ventas_presupuestos_articulo.html.twig` | `tpvmod_presupuestos.php` | same for presupuestos | same fields, header `{#FS_PRESUPUESTOS#}` |

The four `ventas_lineas_*` fragments MUST NOT render the
`<!--{{ fsc.buscar_lineas }}-->` marker: the search term is only an input to the
controller's model call, not a view field. Their pager MUST be the htmx
server-side offset contract (no client-side `mas_resultados()` arithmetic), and
`ventas_lineas_facturas.html.twig` MUST gain the pager it previously lacked.

#### Scenario: ajax/tpv_cambios_precios renders article + tariffs

- GIVEN `tpvmod.php` sets `$this->template = 'ajax/tpv_cambios_precios'`
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

#### Scenario: Line-search fragments drop the echo marker and gain the pager

- GIVEN the four `view/ajax/ventas_lineas_*.html.twig` fragments
- WHEN each is parsed
- THEN the `<!--{{ fsc.buscar_lineas }}-->` marker is absent
- AND each renders an htmx offset pager (`hx-post` + `hx-vals` `offset`)
- AND `ventas_lineas_facturas.html.twig` has the same pager as the other three

### Requirement: Test suite covers template inventory and controller cleanup

`tests/TpvmodTwigTemplatesTest.php` MUST exist with at least:
(a) zero `.html` files left under `view/`,
(b) all 19 expected `.html.twig` files exist and are non-empty,
(c) the two modified controllers no longer contain `csrf_field` or
`fs_session_manager` require/population,
(d) the `tpvmod-b-buscar-cliente` presence assertion applies only to the views
that keep the client picker (`tpvmod2.html.twig`, `tpvmodedita.html.twig`) and
MUST NOT be asserted for the four listings (which MUST also not contain
`devbridgeAutocomplete`),
(e) htmx/native-date contract assertions for the four listings (presence of the
stable region id and `hx-get`/`hx-select`/`hx-target`/`hx-swap`/`hx-push-url` on
the mapped controls, and absence of `datepicker`).
No DB, no fixtures.

#### Scenario: All assertions pass

- GIVEN the plugin in its post-`tpvmod-listados-htmx` state
- WHEN `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`
  runs the test class
- THEN assertions (a)–(e) all pass
- AND `TpvmodModulesTest` (pre-existing suite) still passes

#### Scenario: Client-button assertion is narrowed, not dropped

- GIVEN the client-button assertion for `tpvmod-b-buscar-cliente`
- WHEN the test runs
- THEN `tpvmod2.html.twig` and `tpvmodedita.html.twig` still assert its presence
- AND the four listing views are not part of that assertion
- AND each of the six views still asserts the absence of `devbridgeAutocomplete`

## ADDED Requirements

### Requirement: Filter form precedes the listing region

In the four listing views the filter form MUST appear before the tabs in document
order, and the swapped region MUST be a single wrapper element with the module's
stable id (`#tpvmod-presupuestos-region`, `#tpvmod-facturas-region`,
`#tpvmod-albaranes-region`, `#tpvmod-pedidos-region`). The order toolbar (with
the order dropdown), the tabs, the results table and the pagination MUST live
inside that wrapper; the filter form MUST live outside it.

The filter form MUST be rendered unconditionally: the `#f_custom_search` block
MUST NOT be wrapped in a `{% if fsc.mostrar == 'buscar' %}` guard, so the bar is
present in the markup for every listing state. This is the markup half of
`listados-htmx` LHT-13; the runtime behavior is owned by that delta and is not
duplicated here.

#### Scenario: Markup order is filter form, then region

- GIVEN a listing template is parsed
- WHEN the byte offsets of the filter form and the region wrapper are compared
- THEN the filter form appears first
- AND the tabs are inside the region wrapper

#### Scenario: The filter form carries no `mostrar` guard

- GIVEN each of the four listing templates
- WHEN the `f_custom_search` form block is inspected
- THEN it is not wrapped in `{% if fsc.mostrar == 'buscar' %}`
- AND the other two `fsc.mostrar == 'buscar'` uses per template (the autofocus
  script and the tab `active` class) are untouched

#### Scenario: Stable region id per module

- GIVEN the four listing templates
- WHEN each is grepped
- THEN each contains exactly one wrapper with its module's `#tpvmod-<tipo>-region` id

### Requirement: Native date inputs replace the datepicker class

All date inputs in the four listing views and in `tpvmodedita.html.twig` MUST be
native `<input type="date">` with values prefilled through the core `date_iso`
filter, and MUST NOT carry the `datepicker` class. The ten affected inputs are:
`desde`/`hasta` in each of the four listings, the `rechazar` input in
`tpvmod_presupuestos`, and the `fecha` input in `tpvmodedita`.

#### Scenario: No datepicker class remains

- GIVEN the four listing templates and `tpvmodedita.html.twig`
- WHEN grepped for `datepicker`
- THEN no occurrence is found

#### Scenario: Date values are prefilled with date_iso

- GIVEN a stored `d-m-Y` date value
- WHEN a date input renders
- THEN its `value` is produced through `|date_iso`
- AND the input type is `date`

### Requirement: Client picker elements are absent from the four listing views

The four listing views MUST NOT contain the `ac_cliente` field, the
`tpvmod-b-buscar-cliente` button, the
`{% include 'partials/modal_clientes.html.twig' %}` directive, or the
`tpvmod-cliente.js` load. The client-selection modal and its JavaScript MUST
remain available to the screens that keep the picker (`tpvmod2`, `tpvmodedita`)
and MUST NOT be deleted.

#### Scenario: Listing views exclude the picker

- GIVEN the four listing templates
- WHEN each is parsed
- THEN none references `ac_cliente`, `tpvmod-b-buscar-cliente`,
  `partials/modal_clientes.html.twig` or `tpvmod-cliente.js`

#### Scenario: The picker survives on the screens that keep it

- GIVEN `tpvmod2.html.twig`, `tpvmodedita.html.twig` and
  `view/partials/modal_clientes.html.twig`
- WHEN they are inspected
- THEN the modal include and the `tpvmod-cliente.js` load are still present
- AND `view/js/tpvmod-cliente.js` is not deleted

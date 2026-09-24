# Exploration: tpvmod-listados-htmx

> Plugin-local SDD. Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` is intentionally NOT touched.
> Status: exploration only. No proposal/spec/design/tasks artifacts created.

## Intent

Migrate the four tpvmod document listings to htmx 4 + Alpine.js 3 (CSP build),
enrich each row with the customer phone and the billing-address city, turn the
listing search into a multi-field search that also covers the company name and
the phone, and move the "Buscar en las líneas" modal off jQuery `$.ajax` into
htmx while preserving the server-side offset pagination.

Verbatim user request (Spanish):

> 1. Pasar el listado de cada uno de los 4 módulos a **htmx 4 + Alpine.js**.
> 2. Añadir al listado dos campos nuevos: **teléfono** del cliente y **ciudad** de la dirección de facturación (si la tiene).
> 3. Hacer un **buscador "full text"** que cubra **nombre de empresa** y **teléfono**, además de lo que ya busca hoy.
> 4. Adaptar **la misma tecnología htmx** al **buscador de líneas** (el modal "Buscar en las líneas"), que hoy usa jQuery `$.ajax` + `mas_resultados()` por offset.

Scope target: `tpvmod_presupuestos`, `tpvmod_facturas`, `tpvmod_albaranes`,
`tpvmod_pedidos` (four near-identical clones).

---

## Scope

### In scope

- The four listing templates under `plugins/tpvmod/view/tpvmod_{presupuestos,facturas,albaranes,pedidos}.html.twig`.
- The four listing controllers `plugins/tpvmod/controller/tpvmod_{presupuestos,facturas,albaranes,pedidos}.php`.
- The four line-search fragments `plugins/tpvmod/view/ajax/ventas_lineas_*.html.twig`.
- New pure helpers under `plugins/tpvmod/lib/` (unit-testable, DB-free).
- New plugin-local specs / tasks / verify artifacts.

### Out of scope (explicit)

- Any model under `plugins/clientes_facturacion/` or `plugins/clientes_core/`.
  Cross-plugin model changes are forbidden by this change; all cross-plugin data
  is resolved in the tpvmod controller (subquery / batched lookup).
- The TPV sale screens `tpvmod.html.twig` / `tpvmod2.html.twig` / `tpvmodedita.html.twig`
  and `view/js/tpvmod.js` (1,745 lines). These already are the "edita" flow, not the listings.
- The client-search modal (`view/js/tpvmod-cliente.js` + `partials/modal_clientes.html.twig`).
  It is included by the listings but is a separate AJAX flow; see Open questions.
- Core `openspec/`, core `src/`, `base/`, `themes/`, `view/js/`.
- The document snapshot column set (no schema migration).

---

## Current state

### 1. Controllers

All four are legacy (no PSR-4 namespace) and extend `fs_controller` directly.
They are near-clones; differences are tab names, order options, and extra
columns (facturas only).

| Aspect | presupuestos | albaranes | pedidos | facturas |
|---|---|---|---|---|
| Class file | `controller/tpvmod_presupuestos.php:28` | `.../tpvmod_albaranes.php:28` | `.../tpvmod_pedidos.php:28` | `.../tpvmod_facturas.php` |
| Tabs `$mostrar` | `todo`, `pendientes`, `rechazados`, `buscar` (`.php:193-228`) | `todo`, `pendientes`, `buscar` (`:185-206`) | `todo`, `pendientes`, `rechazados`, `buscar` (`:189-220`) | `todo`, `sinpagar`, `buscar` (`:190-210`) |
| Order options | `fecha_desc/asc`, `codigo_desc/asc` (`:79-94`) | same (`:79-94`) | same | `fecha_*`, `vencimiento_*` (`:90-96`) |
| Extra props | — | — | — | `$factura`, `$huecos`, `$total_resultados_comision` (`:36-47`) |
| `cron_job()` on load | yes (`:191`) | no | no | no |
| Line-search fragment | `ajax/ventas_lineas_presupuestos` (`:316`) | `ajax/ventas_lineas_albaranes` (`:290`) | `ajax/ventas_lineas_pedidos` | `ajax/ventas_lineas_facturas` |
| Line-search offset | yes (`:323,327`) | yes (`:297,301`) | yes | **no** (search calls omit offset) |

Shared mechanics in every controller:

- `$mostrar` from `$_GET['mostrar']` persisted to a cookie
  (`tpvmod_presupuestos.php:59-68`).
- `$offset` from `$_REQUEST['offset']` (`:70-74`).
- `$order` from `$_GET['order']` persisted to a cookie (`:76-101`).
- `buscar_lineas()` swaps `$this->template` to the ajax fragment
  (`:313-329`).
- `buscar($order2)` builds raw SQL (`:419-493`):
  `FROM <table>` + `WHERE (codigo LIKE ... OR numero2 LIKE ... OR observaciones LIKE ...)`
  + optional `codagente`, `codcliente`, `codserie`, `fecha >=`, `fecha <=`,
  then `COUNT`, `SELECT * ... ORDER BY <order>` and `SUM(total)`.
- `paginas()` builds page URLs by raw string concatenation
  (`:240-246`), e.g. `... "&query=".$this->query ...` with no `urlencode`.
- `total_*()` counters use raw SQL (`:387-417`).

### 2. Listings templates (structure is identical modulo tab labels)

`plugins/tpvmod/view/tpvmod_presupuestos.html.twig` (434 lines) is the reference:

| Region | Lines | Notes |
|---|---|---|
| Inline `<script>`: `buscar_lineas()`, `mas_resultados()`, `clean_cliente()`, `$(document).ready` | `13-74` | jQuery + `$.ajax` POST; stale-response comment trick (`:28-33`) |
| Toolbar (reload, default page, Nuevo, Rechazar modal link, extensions) | `76-153` | |
| Order dropdown (`data-toggle="dropdown"`, links with `&order=...`) | `116-150` | |
| Tabs `ul.nav.nav-tabs` (`todo`/`pendientes`/`rechazados`/`buscar`) | `155-183` | Plain `<a href>` |
| Filter form `f_custom_search` | `185-270` | `method="post"`, `{{ csrf_field() }}`; fields `query`, `codserie`, `codagente` (select `onchange="this.form.submit()"`), `ac_cliente` (readonly) + hidden `codcliente`, `desde`, `hasta` (`onchange="this.form.submit()"`) |
| Table `div.table-responsive > table.table-hover` | `272-333` | `<tr class="clickableRow" href="...">` rows (`:289`); `[+]` link `.cancel_clickable` (`:304`); `fsc.show_precio(...)` (`:307`) |
| Pagination `ul.pagination` from `fsc.paginas()` | `335-347` | Plain `<a href>` |
| Line-search modal `form#f_buscar_lineas > div#modal_buscar_lineas` | `349-403` | POST; hidden `offset` (`:351`); `#search_results` (`:399`) |
| Rechazar modal (POST `name="rechazar"`) | `405-431` | presupuestos/pedidos only |
| `{% include 'partials/modal_clientes.html.twig' %}` | `433` | Loads `tpvmod-cliente.js` (`partials/modal_clientes.html.twig:8`) |

Facturas diverges: `#modal_huecos` (`tpvmod_facturas.html.twig:356-389`), the
Vencimiento/Comisión columns (`:263-272`), the line-search form has **no hidden
`offset`** (`:393-...`), and its template defines `buscar_lineas`/`clean_cliente`
but **no `mas_resultados`** (head block `13-40`).

### 3. Line-search fragment + JS

- Fragment `plugins/tpvmod/view/ajax/ventas_lineas_presupuestos.html.twig`:
  - `:2` `<!--{{ fsc.buscar_lineas }}-->` echo marker.
  - `:15-38` results table.
  - `:40-56` pager calling inline `mas_resultados('±N')` (offset arithmetic in JS).
- `ventas_lineas_facturas.html.twig` (38 lines) has **no pager** (offset-less).
- Model side: `linea_*_cliente::search($query,$offset)` and
  `search_from_cliente2($codcliente,$ref,$obs,$offset)`
  (`plugins/clientes_facturacion/model/core/linea_presupuesto_cliente.php:253,278`;
  same in `linea_factura_cliente.php:390,423`, `linea_albaran_cliente.php:314,347`,
  `linea_pedido_cliente.php:275,300`). `search_from_cliente2` filters with a
  subquery `idpresupuesto IN (SELECT idpresupuesto FROM presupuestoscli WHERE
  codcliente = ... AND lower(observaciones) LIKE '<obs>%')` (`:283-285`) —
  note the unescaped `$obs` concatenation, same pattern as the controller.

### 4. htmx / Alpine infrastructure (already in core)

- Assets: `view/js/htmx.min.js` (`version="4.0.0"`), `view/js/alpine-csp.min.js`.
- Boot macros (opt-in only): `themes/AdminLTE/view/Macro/Htmx.html.twig:45-87`
  (`boot()` / `boot({'allowScriptTags': false})`; emits CSRF inherited headers
  and, in config form, the `htmx:before:swap` scrubber) and
  `themes/AdminLTE/view/Macro/Alpine.html.twig:31-33`.
- Legacy controller already exposes the contract:
  `$this->request` (`base/fs_controller.php:195`), `isHtmxRequest()` (`:485-488`),
  `validateCsrf()` reading `X-CSRF-TOKEN` (`:382-425`), auto CSRF validation in
  `pre_private_core()` (`:981-990`), `$this->template` (`:175`).
- Render pipeline: `index.php:353-366` renders the full page unless
  `$fsc->template` is falsy; `isAjax()` is true only for `X-Requested-With`/`ajax`
  (`base/fs_controller.php:200-202`) and is **false for htmx** → an htmx GET
  receives the complete page.
- Canonical pattern: `plugins/catalogo_core/View/ventas_articulos.html.twig`
  and `plugins/catalogo_core/Controller/VentasArticulos.php`. Its entry point is
  still a legacy-named controller (`plugins/catalogo_core/controller/ventas_articulos.php:23`)
  whose body is a thin shim delegating to the modern PSR-4 class.
- Contracts documented in `openspec/specs/htmx-core-support/spec.md`
  (HCS-01..HCS-16, ABS-01..ABS-03).

### 5. Data model (what already exists, no schema change needed)

- Document snapshot trait `plugins/clientes_facturacion/extras/documento_venta.php`:
  `public $ciudad` (`:117`), `public $direccion`, `$codpostal`, `$provincia`,
  `$nombrecliente` (`:99`); `load_data_trait()` assigns only known keys
  (`:330-397`), so a `SELECT *` with extra JOIN columns is silently ignored.
- `presupuesto_cliente::__construct($data)` calls `load_data_trait($data)`
  (`model/core/presupuesto_cliente.php:66-104`).
- `nombrecliente` is the **company name snapshot**: the TPV sets
  `$documento->nombrecliente = $cliente->razonsocial`
  (`plugins/tpvmod/lib/tpvmod_modules.php:355`), and the billing city snapshot is
  set from the `domfacturacion` address (`tpvmod_modules.php:357-366`).
- Customer master `plugins/clientes_core/model/core/cliente.php`:
  `telefono1`/`telefono2` (`:76-77`), `nombre` (`:56`), `razonsocial` (`:62`);
  `search()` already searches name/razón social/cif/phone with `LIKE`
  (`:876-903`).
- Billing address `plugins/clientes_core/model/core/direccion_cliente.php`:
  `$ciudad` (`:47`), `$domfacturacion` (`:61`); save() de-duplicates
  `domfacturacion` on write (`:186-192`). Columns in
  `plugins/clientes_core/model/table/dirclientes.xml` (`ciudad`,
  `domfacturacion`; PK `id`; FK `codcliente`).
- Document tables already carry their own `ciudad` column
  (`plugins/clientes_facturacion/model/table/presupuestoscli.xml:19`) and
  `nombrecliente` (`:147`).

---

## Findings

### F1 — htmx fragment contract for a legacy tpvmod controller

Two contracts already coexist in the repo:

1. **Full-page re-render + client-side extraction** (`catalogo_core`).
   The controller keeps rendering the whole page
   (`Controller/VentasArticulos.php:72` `setTemplate('ventas_articulos')`); htmx
   fetches the same URL and extracts a stable region with
   `hx-select="#articulos-list"` + `hx-swap="outerHTML"` + `hx-push-url="true"`
   (`View/ventas_articulos.html.twig:61-68,132,213-214,244-256`). Filter controls
   each carry their own `hx-get`, re-sending the other filters inline so the URL
   stays canonical (`:9-16`).
2. **Server-side fragment** (`$this->template = 'ajax/...'` or `template = false`
   + echo). tpvmod already uses this for the line search
   (`tpvmod_presupuestos.php:316`); htmx POST needs only `hx-target`, no
   `hx-select`, because the response body *is* the fragment. HCS-14
   (`htmx-core-support/spec.md:237-239`) documents the modern variant for
   `HtmxCrudController`, but the legacy `$this->template = 'ajax/...'` produces
   the same HTML body.

**"Fragment vs full page" is decided client-side** by `hx-select`. No
`isHtmxRequest()` branch is required for the read-only listing updates, and the
`hx-select` + `outerHTML` over a stable id **is sufficient** for
tabs/order/pagination/filters. Progressive enhancement holds because the plain
`href`/`action` still returns the full page: reloading a pushed URL renders the
page fully, and a JS-disabled browser uses the native links.

Evidence the pattern applies to legacy controllers without a namespace:

- `fs_controller` already has the whole surface the pattern needs:
  `$this->request` (`base/fs_controller.php:195`), `isHtmxRequest()` (`:485`),
  `url()` (`:742`), `$template` (`:175`).
- The macros and `csp_nonce_attr()`/`csrf_token()` are theme/global, not
  controller-level: `themes/AdminLTE/view/Macro/Htmx.html.twig:46-86`,
  `src/Core/Html.php:309`.
- `catalogo_core`'s own entry point is a legacy-named controller
  (`controller/ventas_articulos.php:23`); the only difference is that its body
  delegates to `PageController`. tpvmod does not have `PageController`'s
  `buildListUrl()`/`validateFormToken()`, but has `url()` and
  `isCsrfValid()`/`validateCsrf()` equivalents.

**Conclusion:** the pattern applies; tpvmod must build the canonical URLs
itself (its `paginas()` currently concatenates unencoded params, F9).

### F2 — Mapping every listing control to htmx

| Control | Today (evidence) | Proposed htmx | URL effect |
|---|---|---|---|
| Search submit / query | `form method="post"` (`presupuestos:187-206`) | `form hx-get` + `hx-trigger="submit"` (or per-input `keydown[key==='Enter']`), `hx-target="#tpvmod-<t>-panel" hx-select=... hx-swap="outerHTML" hx-push-url="true"` | adds `query=` |
| `codserie` select | `onchange="this.form.submit()"` (`:210`) | `hx-get` + `hx-trigger="change"` | adds `codserie=` |
| `codagente` select | `onchange="this.form.submit()"` (`:225`) | `hx-get` + `hx-trigger="change"` | adds `codagente=` |
| `ac_cliente` + client modal | readonly input + hidden `codcliente` (`:190,242`); JS submit on select (`tpvmod-cliente.js:122-128`) | after selection, `htmx.trigger(form,'submit')` (or set `codcliente` then `htmx.ajax`) | adds `codcliente=` |
| `desde` / `hasta` | `onchange="this.form.submit()"` (`:259,264`) | `hx-get` + `hx-trigger="change"` | adds `desde=`/`hasta=` |
| Tabs `mostrar` | `<a href>` (`:155-183`) | `hx-get` + `hx-push-url="true"` | adds `mostrar=` |
| Order dropdown links | `<a href>` (`:116-150`) | `hx-get` + `hx-push-url="true"` | adds `order=` |
| Pagination | `<a href>` from `fsc.paginas()` (`:339-343`) | `hx-get` + `hx-push-url="true"` | adds `offset=` |
| Rechazar submit | POST form (`:405-431`) | `hx-post` + `hx-confirm` (or keep native POST) | none |
| Line search (modal) | `$.ajax` POST (`:14-48`) | `hx-post` + `hx-target="#search_results"` | POST body |

Server side, the controllers already read everything through `$_GET`/`$_REQUEST`
(`mostrar`, `offset`, `order`, `codserie`, `codagente`, `codcliente`, `desde`,
`hasta`, `query`), so no parameter renaming is needed.

Open design point: tabs and the order dropdown must visually update after a swap.
Placing them **inside** the swapped region keeps `class="active"`/the checkmark
correct with zero JS; placing them outside requires an Alpine re-sync from
`location.search` on `htmx:after:swap` (F9-R6).

### F3 — Legacy JS that can break (premise partially incorrect)

- `plugins/tpvmod/view/js/tpvmod.js` is **not loaded by the four listings**; it
  is included only by `tpvmod2.html.twig:12` and `tpvmodedita.html.twig:13`.
  So nothing in that 1,745-line file affects the listings.
- `clickableRow` is bound **delegated on `document`**:
  `view/js/base.js:72` `$(document).on('click', 'tr.clickableRow[href]', ...)`.
  Delegation survives htmx swaps: no re-bind needed, and rows keep working even
  though the region is replaced.
- The line-search functions are **inline** in each listing template
  (`presupuestos:13-74`). They live outside the swapped region if the region is
  limited to the table+pagination, so they survive; but they are the code being
  replaced by htmx anyway.
- `partials/modal_clientes.html.twig:8` loads `view/js/tpvmod-cliente.js`, which
  binds `$('.tpvmod-b-buscar-cliente').on('click')` **non-delegated** at
  `$(document).ready` (`tpvmod-cliente.js:12-40`). Because the button and the
  modal are part of the initial full page (outside the swapped listing region),
  those handlers survive. **If** any future refactor moves them inside a swapped
  region they would need `htmx:after:swap` re-binding — that is the only
  re-bind risk found, and it is avoidable by region placement.

### F4 — Exposing `telefono` and `ciudad_facturacion` without touching the document model

`new presupuesto_cliente($row)` → `load_data_trait($data)` reads a fixed key list
(`documento_venta.php:330-397`), so extra `SELECT`/JOIN columns never become
properties; adding `telefono` to the document would require editing
`clientes_facturacion` (forbidden). Options and precedent:

- **Precedent (recommended): controller-side side-table + public accessor.**
  `catalogo_core` loads extra per-row data with one batched query and exposes it
  through public methods: `load_articulo_tarifa_columns()`
  (`VentasArticulosListTrait.php:299-312`) plus `get_precio_articulo_tarifa()`
  (`:327`) / `caracteristica_cell()` (`:451`), called from the template as
  `fsc.caracteristica_cell(...)` (`View/ventas_articulos.html.twig:206`).
  For tpvmod: collect the page's `codcliente` set, run one
  `SELECT codcliente, telefono1, telefono2 FROM clientes WHERE codcliente IN (...)`,
  build a map, and expose `fsc.telefono_cliente($codcliente)` /
  `fsc.ciudad_facturacion($codcliente)`.
- **City needs no query at all**: `value.ciudad` is already a document property
  (`documento_venta.php:117,348`) populated from the `domfacturacion` address
  (`tpvmod_modules.php:357-366`). See F6 for the snapshot-vs-live decision.
- Rejected: dynamic property (`$doc->telefono = ...`) — PHP 8.2 deprecates
  dynamic properties on classes without `#[AllowDynamicProperties]`, which
  `documento_venta` users do not declare.
- Rejected: modifying `documento_venta`/document models — cross-plugin coupling.
- Acceptable alternative: a per-row view-model array/DTO built in the controller.

### F5 — "Full text": multi-field `LIKE` is the portable, repo-consistent choice

- **No `FULLTEXT`/`MATCH ... AGAINST`/`tsvector` precedent exists** in the repo
  (grep over `base/`, `src/`, `plugins/`, `model/` returns none). The schema
  format (`model/table/*.xml`) has no full-text index concept — only PK/FK
  `<restriccion>` entries.
- `fs_db2` abstracts MySQL vs PostgreSQL only at the connection/driver level
  (`base/fs_db2.php:50`), not full-text syntax. `MATCH ... AGAINST` (MySQL) and
  `tsvector`/`to_tsquery` (PostgreSQL) are engine-specific and would need a
  branching query builder — out of proportion for these row counts.
- The established pattern is case-folded multi-field `LIKE`:
  `cliente::search()` lowercases in PHP and uses `lower(col) LIKE
  $this->var2str($pattern)` (`cliente.php:876-903`); `linea_*::search()` does the
  same (`linea_presupuesto_cliente.php:253-268`).

**Recommendation:** extend the existing `LIKE` search with additional fields,
portable across both engines:
`codigo`, `numero2`, `observaciones`, `nombrecliente` (company-name snapshot),
and the customer phone/name through a `codcliente IN (SELECT codcliente FROM
clientes WHERE ... OR telefono1 LIKE ... OR telefono2 LIKE ... OR lower(nombre)
LIKE ... OR lower(razonsocial) LIKE ...)` predicate — a subquery (not a JOIN)
keeps `SELECT *` single-table and cannot duplicate rows.

Quirks to handle:
- The current `is_numeric($query)` branch (`tpvmod_presupuestos.php:430-438`)
  treats all-digits input as numeric; phone numbers typed as digits work, but
  phones stored with spaces/`+`/dashes won't match a digits-only query. Normalize
  or search both forms.
- `nombrecliente` is the *snapshot* of `razonsocial`; searching it matches
  historical documents. Searching `clientes.nombre` matches the live record.

**Security (verified, must fix while extending):** `buscar()` concatenates
`$query` directly into SQL (`tpvmod_presupuestos.php:432,436-437`) after only
`no_html(strtolower(...))` (`:423`). `no_html` HTML-encodes `'` → `&#39;`
(`base/fs_model.php:214-230`) and `$this->query` was already run through
`fs_filter_input_req` → `FILTER_SANITIZE_FULL_SPECIAL_CHARS`
(`base/fs_functions.php:670-694`). Quote-breaking injection is therefore blocked
in practice, but this is HTML-escaping used as SQL defense — fragile and
non-obvious. The extension MUST switch to `var2str()`-parameterized patterns
like `cliente::search()` (`cliente.php:884-897`) and wrap the operator with
`$this->db->var2str(...)`. `linea_*::search_from_cliente2` has the same
unescaped-`$obs` pattern (`linea_presupuesto_cliente.php:285`) and deserves the
same treatment if touched.

### F6 — Where the city comes from: snapshot vs live billing address

Option A — **document snapshot** `value.ciudad`:

- Source: `tpvmod_modules.php:357-366` selects the first
  `get_direcciones()` entry with `domfacturacion` and copies `ciudad` into the
  document.
- Zero extra queries; historically accurate (what the document says).
- `NULL`/empty for documents created without a billing address; template renders
  `-` via `{% if value.ciudad %}`.
- Portable trivially (no SQL change).

Option B — **live `dirclientes` with `domfacturacion = TRUE`**:

- Requires a join/subquery. The model de-duplicates on write
  (`direccion_cliente.php:186-192`), but legacy/inconsistent data can still hold
  multiple `domfacturacion = TRUE` rows → a naive JOIN duplicates document rows.
  Use a batched lookup keyed by `codcliente` (`ORDER BY domfacturacion DESC`,
  take first) with a `LEFT JOIN`-style fallback, never a raw JOIN.
- Reflects the *current* address, which may differ from what is printed on the
  document.
- "No billing address" → `NULL` (LEFT JOIN / map miss).

**Recommendation:** Option A (snapshot) as the default — it is the document's
own billing city, needs no query, and cannot duplicate rows. Offer Option B only
if the product explicitly wants live addresses.

### F7 — Converting the line search to htmx

- The form `#f_buscar_lineas` is already a self-contained POST producing a
  fragment via `$this->template = 'ajax/ventas_lineas_*'`
  (`tpvmod_presupuestos.php:313-329`). htmx can POST to the same URL and target
  `#search_results` directly (`hx-target="#search_results" hx-swap="innerHTML"`),
  no `hx-select` needed.
- Debounce + race handling replaces the stale-response trick:
  `hx-trigger="submit, keyup changed delay:300ms from:input[name=buscar_lineas]"`
  plus `hx-sync="this:replace"`. The `<!--{{ fsc.buscar_lineas }}-->` marker
  (`ventas_lineas_presupuestos.html.twig:2`) and the JS comparison
  (`tpvmod_presupuestos.html.twig:28-33`) become dead code.
- Offset pagination stays server-side. The controller already reads `offset`
  (`:70-74`) and passes it to `search`/`search_from_cliente2` (`:323,327`). The
  fragment can render server-computed prev/next links, e.g.
  `hx-post="{{ fsc.url() }}" hx-vals='{"offset":N}' hx-include="#f_buscar_lineas"
  hx-target="#search_results" hx-swap="innerHTML"`, replacing the inline
  `mas_resultados('±N')` arithmetic (`ventas_lineas_presupuestos.html.twig:40-56`).
- CSRF: the form keeps `{{ csrf_field() }}` (`:350`) for the no-JS fallback, and
  htmx POSTs also carry `X-CSRF-TOKEN` through the inherited header
  (`Htmx.html.twig:80-85`); `validateCsrf()` reads POST first, header second
  (`base/fs_controller.php:391-397`), so both paths pass.
- **Facturas is inconsistent**: no `offset`, no pager, and `search()` calls omit
  the offset (`tpvmod_facturas.php` `buscar_lineas()`), while the model supports
  it (`linea_factura_cliente.php:390,423`). Either add parity or explicitly scope
  it out.

### F8 — Line search filtered by customer (`search_from_cliente2`)

- The modal carries a hidden `codcliente` when a client filter is active
  (`tpvmod_presupuestos.html.twig:368`) and the controller branches on
  `isset($_POST['codcliente'])` (`tpvmod_presupuestos.php:321-328`).
- With htmx, `hx-include="#f_buscar_lineas"` includes `codcliente`,
  `buscar_lineas` and `buscar_lineas_o`; the offset travels via `hx-vals`. The
  server flow and the model signature are unchanged, so the client-scoped
  filter is preserved verbatim.
- If the modal is opened without a client filter, `codcliente` is absent and the
  global `search()` branch runs — unchanged.

### F9 — Risks, traps and footguns

1. **SQL injection surface while extending the search** — see F5; must convert
   raw concatenation to `var2str`.
2. **`cron_job()` write amplification**: `presupuesto_cliente::cron_job()` runs
   five `UPDATE`s (`model/core/presupuesto_cliente.php:577-598`) on every
   `tpvmod_presupuestos` load (`controller:191`). The full-page-render htmx
   strategy re-runs `private_core()` on every fragment request, so paging/tab
   clicks multiply writes. Mitigate by skipping it when `isHtmxRequest()` is
   true, or gating it to `$offset === 0` / a time-based flag.
3. **Unencoded pagination URLs**: `paginas()` concatenates `&query=".$this->query`
   (`tpvmod_presupuestos.php:240-246`). Feeding those into `hx-get`/`hx-push-url`
   breaks on `&`, `#`, spaces. Rebuild with `http_build_query` (cf. catalogo
   `buildListUrl()` `Controller/VentasArticulos.php:777-787`; template
   `|url_encode` `View/ventas_articulos.html.twig:11-16`).
4. **Flash messages lost on GET swaps**: `header.html.twig` renders
   errors/messages; an htmx swap of only the list region does not re-render them.
   `new_error_msg()` from `buscar()` (e.g. CSRF) would be invisible. Options:
   render an alerts block inside the swapped region, emit an `HX-Trigger`
   `fs:flash` header (only `HtmxCrudController` supports it today, `HCS-13`), or
   accept no messages on read-only GETs.
5. **CSP / nonce**: effective policy still allows `'unsafe-inline'`
   (`src/Security/SecurityHeaders.php:23`), so legacy `onclick=` keeps working,
   but new logic should follow the nonce'd + `Alpine.data()` pattern
   (`View/ventas_articulos.html.twig:411-481`) so a future strict policy holds.
6. **Alpine `data()` idempotency on swapped scripts**: htmx 4 re-creates and
   executes scripts in swapped content unless scrubbed. Boot with
   `boot({'allowScriptTags': false})` (scrubber) and register components behind a
   `window.__marker` guard (catalogo `:413,471`); re-init swapped trees from a
   single `htmx:after:swap` listener calling `Alpine.initTree(target)`
   (catalogo `:470-480`).
7. **Bootstrap 3 dropdowns/modals**: BS3 delegates `data-toggle="dropdown"` and
   `data-toggle="modal"` on `document`, so they survive swaps. Keep swapped
   regions free of plugin-initialized widgets where practical.
8. **jQuery datepicker**: `.datepicker` is initialized on document-ready over
   existing elements. If a `.datepicker` input ends up inside the swapped region
   it loses its picker after the first swap → keep `f_desde`/`h_desde` and the
   filter form **outside** the swapped region. Also, programmatic value setting by
   the picker may not fire `change`; verify the htmx `change` trigger behaves.
9. **Tabs/order active-state**: see F2 — decide inside-region vs Alpine re-sync.
10. **Four-way clone divergence**: facturas differs in tabs, order options,
    extra columns, the `#modal_huecos` modal and the offset-less line search.
    A shared partial/helper reduces drift but must not silently change facturas'
    behaviour.
11. **Client modal still jQuery**: `tpvmod-cliente.js` uses `$.ajax` +
    comment-trick (`:89-101`). It is outside the requested scope; leaving it
    means the listings mix htmx and jQuery. Mixing is supported (both are plain
    client libs) but is a consistency debt.
12. **`hx-push-url` + already-encoded params**: pushing a URL that htmx built by
    string concat can double-encode or corrupt filters. Always generate the
    hx URL through the same `http_build_query` helper used for `href`.

### F10 — Verification strategy

Existing coverage (must stay green):

- `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php`:
  - `:47-81` template inventory (adding partials is fine; removing/moving breaks it).
  - `:186-211` **every** `.twig` containing `method="post"` must contain
    `{{ csrf_field() }}`. New htmx POST elements that are not `<form method="post">`
    escape this assertion, so CSRF for them must be asserted elsewhere.
  - `:83-100` `tpvmod-b-buscar-cliente` must remain present and
    `devbridgeAutocomplete` absent — relevant if the client filter is touched.
- `plugins/tpvmod/tests/TpvmodModulesTest.php` covers the pure helpers in
  `lib/tpvmod_modules.php` DB-free (fail/pass fixtures) — the model to follow for
  new pure helpers.
- `plugins/tpvmod/phpunit.xml` (bootstrap `../../tests/bootstrap.php`, no DB).

What can be tested without a DB:

- Pure helpers extracted to `lib/` (recommended): the search SQL/field set
  builder, the canonical list URL builder (`http_build_query`), phone/ciudad
  resolution from a supplied row set, and offset pager arithmetic. Assert with
  fixtures; no DB, no Twig.
- Template contract assertions (grep-style, like the existing tests): presence of
  `hx-get`/`hx-select`/`hx-target`/`hx-push-url` on the mapped controls; the
  stable region id; `hx-post` on the line search; the absence of the
  `<!--{{ fsc.buscar_lineas }}-->` marker; `{{ csrf_field() }}` still present in
  POST forms; the htmx/Alpine macros imported once.

Not testable without a browser: actual swap behaviour, Alpine init, datepicker,
Bootstrap dropdowns — cover with the manual smoke checklist.

Commands: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` (or
the root `Plugins` suite), `ddev exec composer phpstan` (root `phpstan.neon`).
The plugin's `strict_tdd: true` (`openspec/config.yaml`) makes tests-with-code a
hard requirement.

---

## Options & tradeoffs

### O1 — Listing fragment contract

| Option | Pros | Cons |
|---|---|---|
| **A. Full-page render + `hx-select` + `outerHTML`** (catalogo pattern) | No server fragment mode; progressive enhancement free; one template per module; matches the established spec | Full page rendered per swap (heavier); `cron_job()` runs each time (F9-R2); needs a stable region id and URL builder |
| B. Add `isHtmxRequest()` branch rendering only the region | Smaller payloads | Two render paths per module; must keep them in sync; `index.php` still renders header/footer unless `template` is swapped |
| C. Dedicated fragment action/template (`template='ajax/list_*'`) | Smallest payload | Duplicate table markup; GET fragment needs its own route; most divergence across 4 clones |

**Recommendation: A**, with `cron_job()` guarded (F9-R2).

### O2 — Swap region boundaries

| Option | Pros | Cons |
|---|---|---|
| **A. Region = order-toolbar + tabs + table + pagination; filter form above/outside** | Tabs/order active state update with no JS; filter inputs keep focus | Requires a small DOM reorder of the template (filter form moved above tabs) |
| B. Region = table + pagination only; tabs/order outside | Minimal DOM change | Tabs `active` class and order checkmark go stale → needs Alpine re-sync from `location.search` |
| C. Region = everything including the filter form | Simplest markup | Filter input replaced (focus loss) on every swap; datepicker re-init needed |

**Recommendation: A**. Fall back to B only if the DOM reorder is rejected.

### O3 — City source

| Option | Pros | Cons |
|---|---|---|
| **A. `value.ciudad` snapshot** | Zero query; no row duplication; historical truth; "si la tiene" is a simple null check | May be empty for legacy docs; not the current address |
| B. Live `dirclientes.ciudad` (`domfacturacion`) | Current address | Extra batched query; duplicate-row risk on bad data; must handle "no billing address" |

**Recommendation: A**, add B only on explicit product request.

### O4 — Phone exposure

| Option | Pros | Cons |
|---|---|---|
| **A. Batched `clientes` lookup + `fsc.telefono_cliente($cod)` accessor** | One query per page; no N+1; matches `caracteristica_cell` precedent; DB-free testable | Controller helper per module (or a shared trait/helper) |
| B. JOIN `clientes` into the listing query | Phone available in `$row` | JOIN with `SELECT *` needs explicit aliasing; document constructor ignores it anyway, so still needs a side map; risk of duplicates if mis-joined |
| C. Dynamic property on the document object | Looks direct | PHP 8.2 dynamic-property deprecation; hidden coupling |

**Recommendation: A.**

### O5 — Search implementation

| Option | Pros | Cons |
|---|---|---|
| **A. Multi-field `LIKE` via `codcliente IN (subquery)` + `var2str`** | Portable MySQL/Postgres; no schema change; matches repo | Not indexed; fine at this scale |
| B. MySQL `FULLTEXT` + `MATCH ... AGAINST` | Indexed | Not portable (no Postgres path); schema format has no FULLTEXT concept; asymmetric branches |
| C. Postgres `tsvector` | Indexed | Same portability problem, opposite engine |

**Recommendation: A.**

### O6 — Line-search race/stale handling

| Option | Pros | Cons |
|---|---|---|
| **A. `hx-trigger` debounce + `hx-sync="this:replace"`** | Declarative; deletes the comment marker + JS comparison | Behaviour change if any code depended on the marker |
| B. Keep the JS comment-trick and only swap the transport | Minimal change | Keeps jQuery; the marker is an awkward contract |

**Recommendation: A.**

---

## Open questions

Only product-level decisions; each needs a human answer before spec.

1. **City semantics** — is the listing city the document's billing-city *snapshot*
   (`value.ciudad`) or the customer's *current* billing address? (O3)
2. **Phone priority** — when both `telefono1` and `telefono2` exist, show one
   (which?) or both (`600… / 900…`)?
3. **Search scope confirmation** — should the multi-field search also cover the
   customer's live `nombre`/`razonsocial` (requires the `clientes` subquery) in
   addition to the document `nombrecliente` snapshot? And should it cover the
   customer `cifnif` (as `cliente::search` does)?
4. **Swap region reorder** — is moving the filter form above the tabs acceptable
   for layout, so tabs/order active state lives inside the swapped region? (O2)
5. **Client modal** — should the client-search modal (`tpvmod-cliente.js`) also
   migrate to htmx in this change, or stay jQuery (out of scope)?
6. **Facturas line search** — does facturas need offset/pager parity with the
   other three, or is the offset-less behaviour accepted?

---

## Risks

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| R1 | Raw SQL concatenation in `buscar()` while extending fields | High | Convert to `var2str` (F5) |
| R2 | `cron_job()` (5 UPDATEs) on every htmx fragment request | High | Skip when `isHtmxRequest()` / gate by offset |
| R3 | Unencoded `paginas()` URLs fed to `hx-get`/`hx-push-url` | Medium | Build hx URLs with `http_build_query` (F9-R3) |
| R4 | Flash messages invisible after GET swaps | Medium | Alerts inside region, flash header, or accept |
| R5 | Alpine double-registration / broken init after swaps | Medium | `window.__marker` guard + `htmx:after:swap` `initTree` (F9-R6) |
| R6 | Tabs/order active state stale | Medium | Region boundaries (O2-A) |
| R7 | Datepicker lost if inside swapped region | Medium | Keep filter form outside (F9-R8) |
| R8 | Four-way clone drift (facturas extras) | Medium | Shared partial/helper + per-module assertions |
| R9 | CSP regression if inline `onclick` is replaced incorrectly | Low | Nonce + `Alpine.data`; `'unsafe-inline'` still present |
| R10 | Existing Twig tests break (inventory / csrf assertions) | Low | Update only where the change legitimately moves markup |

---

## Verification strategy

1. **Unit (DB-free, strict_tdd):** new pure helpers in `plugins/tpvmod/lib/`
   (search predicate builder, canonical URL builder, phone/ciudad resolution,
   offset pager) with `Tpvmod*Test.php`; keep `TpvmodModulesTest` green.
2. **Unit (template contract):** extend `TpvmodTwigTemplatesTest` (or a sibling)
   with assertions for the hx attributes, the stable region id, `hx-post` on the
   line search, the absence of the `<!--{{ fsc.buscar_lineas }}-->` marker, the
   macros imported once, and `{{ csrf_field() }}` preserved on every POST form.
3. **Static analysis:** `ddev exec composer phpstan`.
4. **Smoke (manual, browser):** for each of the four modules — tabs, order
   dropdown, pagination, each filter field (incl. client selection and dates),
   reload of a pushed URL, JS-disabled fallback (full page), the line search
   (typing, debounce, next/previous offset, client-scoped filter), and the
   Rechazar action. Verify no console errors, no duplicate network requests, and
   that `Alpine` components initialise after swaps.

---

## Recommendation

Adopt the `catalogo_core` htmx contract as-is (full-page render + `hx-select` +
`outerHTML` + `hx-push-url`) — it is the only approach that needs no server
fragment branch and keeps progressive enhancement for the four legacy
controllers. Keep the existing `$this->template = 'ajax/...'` fragment contract
for the line search, and drive it with `hx-post` + debounce + `hx-sync`, deleting
the stale-response comment trick. Expose phone via a batched
`clientes` lookup and a public controller accessor (catalogo `caracteristica_cell`
precedent); take the city from the document snapshot `value.ciudad`. Extend the
search with multi-field `LIKE` plus a `clientes` subquery for name/phone, and
convert the raw concatenation to `var2str` while doing so. Guard `cron_job()` on
htmx requests and rebuild list URLs with `http_build_query`. Keep the four
modules on a shared helper to avoid clone drift, with per-module assertions for
the facturas extras.

# Design: tpvmod-listados-htmx

> Plugin-local SDD. Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` intentionally NOT touched.
> Inputs: `proposal.md`; `specs/{listados-htmx,views,tpv-cliente-modales}/spec.md`
> (LHT-01..LHT-14); `exploration.md` (F1–F10); `decisions-pending.md` (D1–D7, confirmed).
> LHT-13/LHT-14 are post-verify amendments: the authenticated smoke first found the
> filter bar hidden on non-`buscar` states (LHT-13), then stale after a swap (LHT-14).
> This document carries their design half in AD-15 and §4.1; the production change is
> task **U22**.
> Core contracts traced: `htmx-core-support` (HCS-04/05/06/07/08/12/14/15), ABS-01..03,
> `list-search-security` (LSS-01/02), `tarifario-catalog-htmx-pilot` (TCP-01..TCP-10).
> Verified against the codebase; two premises of the earlier draft are **corrected here**
> (see AD-4 and AD-6). This is an architecture document; it writes no production code.

## Technical Approach

The four listings keep rendering the **full page** through the normal `index.php`
pipeline. Every interactive control is an `hx-get` whose URL is a server-built canonical
listing URL, targeting a stable region (`hx-select` + `hx-swap="outerHTML"` +
`hx-push-url`), so there is no server-side fragment branch (catalogo_core precedent).
The line search keeps the legacy `$this->template = 'ajax/…'` fragment but is driven by
`hx-post`. Search security is fixed by moving the predicate to `var2str()`-escaped
patterns. Shared pure logic lives in `lib/tpvmod_listados.php`.

## Architecture Decisions

| # | Choice | Rejected | Why |
|---|---|---|---|
| AD-1 | One pure helper file `lib/tpvmod_listados.php`, free `tpvmod_*` functions. | Trait/abstract base shared by the 4 controllers. | Matches the existing `lib/tpvmod_cliente_ajax.php` pattern; one `require_once`, no hierarchy/page-discovery change. |
| AD-2 | Full-page render + `hx-select` + `outerHTML` + `hx-push-url`. | `isHtmxRequest()` partial-render branch. | One render path; progressive enhancement free; no duplicate table markup (HCS-14). §2. |
| AD-3 | Region ids verbatim from LHT-01: `#tpvmod-{presupuestos,facturas,albaranes,pedidos}-region`. | Renamed/shorter ids. | Pinned by spec; a rename churns three artifacts for no gain. |
| AD-4 | **Control state travels in the `hx-get` URL; the triggering element's own value is appended by htmx. No `hx-params`.** | `hx-params` include-lists. | **`hx-params` does not exist in the vendored htmx 4** (`view/js/htmx.min.js` has zero occurrences; the full attribute set is `hx-action`, `hx-boost`, `hx-config`, `hx-confirm`, `hx-delete`, `hx-disable`, `hx-encoding`, `hx-get`, `hx-headers`, `hx-history-elt`, `hx-ignore`, `hx-include`, `hx-indicator`, `hx-method`, `hx-morph-skip`, `hx-morph-skip-children`, `hx-on`, `hx-patch`, `hx-post`, `hx-preserve`, `hx-push-url`, `hx-put`, `hx-query`, `hx-replace-url`, `hx-select`, `hx-select-oob`, `hx-status`, `hx-swap`, `hx-swap-oob`, `hx-sync`, `hx-target`, `hx-trigger`, `hx-validate`, `hx-vals`). htmx 4 gathers: GET/DELETE → the element's own value only (enclosing form **only** when the trigger *is* the form); POST/PUT/PATCH → the enclosing form. Verified in `view/js/htmx.min.js`. §3. |
| AD-5 | Alpine = one `Alpine.data('tpvmodListado')` + one `htmx:after:swap` → `Alpine.initTree(evt.detail.ctx.target)`; **no** `Macro/HtmxCrud.html.twig`. | Import HtmxCrud for flash/sortable. | HtmxCrud loads `view/js/htmx-crud.js`, which binds its own global `htmx:after:swap` (`view/js/htmx-crud.js:386`) plus `Sortable.min.js`/`fs-dialogs.js` — an unneeded second swap listener. `catalogo_core` imports only Htmx+Alpine; tpvmod follows. |
| AD-6 | **Search term is normalized once: `tpvmod_search_term()` HTML-decodes then lowercases/trims; `var2str()` builds the SQL literal.** | `no_html()` as SQL defense (today), or `var2str()` on the still-escaped `$this->query`. | **`$this->query` is already HTML-escaped**: `fs_controller.php:981` sets it via `fs_filter_input_req()`, which applies `FILTER_SANITIZE_FULL_SPECIAL_CHARS` (`base/fs_functions.php:686`). Building `var2str('%'.$this->query.'%')` would search for `O&#039;Brien` and silently never match a quote-bearing term. LSS-02 requires dropping `htmlspecialchars` from the SQL path, which includes reversing the framework's input escape. §1.4/§7. |
| AD-7 | `f_custom_search` → `method="get"`; `f_buscar_lineas` and Rechazar stay `method="post"` + `{{ csrf_field() }}`. | Keep the filter form POST. | HCS-08: read-only listing GETs need no token. The line search is a POST (HCS-07); Rechazar is a mutation. |
| AD-8 | Rechazar stays a native POST submit. | `hx-post` + `hx-confirm`. | LHT-04 only requires a CSRF-protected POST; converting a cross-page mass mutation adds risk with no LHT benefit. |
| AD-9 | Line pager = server-computed `offset` posted with `hx-vals` (htmx `FormData.set` overrides the hidden input). | `mas_resultados()` client arithmetic, or `beforeend` append. | LHT-10 pins server-side offset and replacement of `#search_results`. |
| AD-10 | `cron_job()` gated on `isHtmxRequest()` in **presupuestos and pedidos** (both call it; `tpvmod_presupuestos.php:191`, `tpvmod_pedidos.php:187`). | Gate only presupuestos (LHT-11's literal text). | Same write amplification otherwise (R2). Gap G5. |
| AD-11 | Dates: view `|date_iso` (existing core filter); controller `tpvmod_normalize_date()` before `var2str()`. | New Twig filter; rely on `var2str()` alone. | `date_iso` already exists (`src/Core/Html.php:239`, `dateIsoValue()` at `:254`). `var2str()` maps an invalid dash date to SQL `NULL` (`base/fs_db2.php:202-212`), so invalid input is dropped in PHP first. |
| AD-12 | Full text = portable `lower(col) LIKE '<escaped>'` + `codcliente IN (subquery)`. | MySQL `FULLTEXT`/`MATCH`, PostgreSQL `tsvector`. | No repo precedent, no index concept in `model/table/*.xml`, needs an engine branch, and these listings are small. §7. |
| AD-13 | Filter form keeps its `{% if fsc.mostrar == 'buscar' %}` guard, moved above the tabs. | Always-visible form. | D4 mandates position, not visibility; `[+]`/`?codcliente=` still auto-switch the controller to `mostrar=buscar`. |
| AD-14 | Top bar splits: the order dropdown moves inside the region toolbar; reload/home/Nuevo/Rechazar/extensions and `b_buscar_lineas` stay outside. | Whole top bar inside. | The order checkmark must be inside to stay correct after a swap (LHT-03); action buttons must not be re-emitted per swap. |
| AD-15 | After every swap, the **single** existing `htmx:after:swap` listener also calls a plain-DOM `tpvmodResyncFilterBar()` that re-derives the bar from the current `location.search` (htmx pushed it before `after:swap`): empty/fill `#tpvmod-cliente-activo` and the hidden `codcliente`, and rebuild the non-text controls' `hx-get`/`href` by key-level `set`/`delete` over the server-rendered `hx-get` prefix (the `list_url()` base). Text inputs (`query`, `desde`, `hasta`) are never replaced. | A second `htmx:after:swap` binding; a server-emitted URL map; re-rendering the bar. | `testListingsImportHtmxAndAlpineOnce` pins **exactly one** `'htmx:after:swap'` occurrence per view, and the bar is outside the region and never re-rendered, so the fix belongs in the existing listener. Reusing each control's own server-rendered `hx-get` prefix keeps the canonical `list_url()` base and encoding and forbids raw concatenation (LHT-11). §4.1. |

## 1. Shared helpers — `plugins/tpvmod/lib/tpvmod_listados.php`

`declare(strict_types=1)`, no namespace, `tpvmod_` prefix, no DB/Twig/controller reference
(every function is pure or takes an injected callable). Each of the four controllers adds
`require_once dirname(__DIR__) . '/lib/tpvmod_listados.php';`. New DB-free test:
`plugins/tpvmod/tests/TpvmodListadosHelpersTest.php` (namespace `Tests\Tpvmod`).

```php
/** Decode the framework's input HTML-escape, then lowercase + trim. '' stays ''. */
function tpvmod_search_term(string $raw): string;            // htmlspecialchars_decode(ENT_QUOTES) → strtolower → trim

/**
 * Parenthesised SQL fragment WITHOUT a leading WHERE ('' when $term === '').
 * $sqlString converts a full literal (wildcards included) to a quoted SQL literal;
 * production passes fn(string $v): string => $ctrl->var2str($v).
 */
function tpvmod_build_search_predicate(string $term, callable $sqlString): string;

/**
 * Canonical listing URL. Drops null and '' values (keeps 0/'0'), preserves key order,
 * http_build_query($params, '', '&', PHP_QUERY_RFC3986), appended with '&' because
 * $base already carries "?page=…".
 * @param array<string, scalar|null> $params
 */
function tpvmod_build_list_url(string $base, array $params): string;

/** telefono1 when non-blank, else telefono2 when non-blank, else ''. */
function tpvmod_resolve_phone(?string $telefono1, ?string $telefono2): string;

/**
 * Deduplicate + drop empty codes BEFORE $fetch; an empty set returns [] without
 * calling $fetch (zero queries). $fetch returns rows keyed by codcliente.
 * @param list<string> $codclientes
 * @param callable(list<string>): array<string, array<string, mixed>> $fetch
 * @return array<string, string> codcliente => phone
 */
function tpvmod_phone_map(array $codclientes, callable $fetch): array;

/**
 * Byte-for-byte the legacy paginas() loop: pages of $limit, first/last/middle/current ±5
 * kept, [] when ≤1 page survives; $actual keeps the legacy initial value 1.
 * @param callable(int): string $urlForOffset
 * @return list<array{url: string, num: int, actual: bool}>
 */
function tpvmod_pager_links(int $offset, int $total, int $limit, callable $urlForOffset): array;

/** 'Y-m-d' (native input) or 'd-m-Y' (formatted value) validated with checkdate(); '' otherwise. */
function tpvmod_normalize_date(?string $value): string;

/** SQL order ('fecha DESC') → URL token ('fecha_desc'); unknown → 'fecha_desc'. */
function tpvmod_order_token_for(string $sqlOrder): string;
```

### 1.3 Predicate shape (concrete SQL)

`tpvmod_build_search_predicate('acme', fn($v) => "'" . $v . "'")` emits:

```sql
((lower(codigo) LIKE '%acme%'
  OR lower(numero2) LIKE '%acme%'
  OR lower(observaciones) LIKE '%acme%')
 OR codcliente IN (SELECT codcliente FROM clientes
                   WHERE lower(nombre) LIKE '%acme%'
                      OR lower(razonsocial) LIKE '%acme%'
                      OR lower(telefono1) LIKE '%acme%'
                      OR lower(telefono2) LIKE '%acme%'))
```

| Variable | Value | Used on |
|---|---|---|
| `$term` | `tpvmod_search_term($raw)` | gate (`''` ⇒ no predicate) |
| `$raw` | `'%' . $term . '%'` | `codigo`, `numero2` |
| `$space` | `'%' . str_replace(' ', '%', $term) . '%'` | `observaciones`, `nombre`, `razonsocial`, `telefono1`, `telefono2` |

- The legacy `is_numeric()` branch is removed as redundant (digits are case-invariant).
- `nombrecliente` (the document's `razonsocial` snapshot) is **not** in the predicate; LHT-05
  pins the field set to the live `clientes` columns.
- `cifnif` is absent by construction. `%`/`_` inside the term are not escaped (same as today).
- Columns verified present: `plugins/clientes_core/model/table/clientes.xml`
  (`codcliente`, `nombre`, `razonsocial`, `telefono1`, `telefono2`).

### 1.4 Escaping contract (LSS-01/LSS-02)

- Production passes `fn(string $v): string => $this->var2str($v)` (driver-aware
  `fs_db2::escape_string()` inside quotes).
- The helper **never** calls `htmlspecialchars`/`no_html()`. The line
  `$query = $this->agente->no_html(strtolower($this->query));` in all four `buscar()` is
  **deleted**; `$this->query` goes through `tpvmod_search_term()` (AD-6).
- Test escaper: `fn(string $v): string => "'" . str_replace("'", "''", $v) . "'"` so the
  emitted SQL for `O'Brien` is asserted directly.

### 1.5 Controller façade (shared shape; per-module extras in §1.10)

```php
/** @return array<string, scalar|null> */
private function list_params(): array {
    return ['mostrar' => $this->mostrar, 'query' => $this->query,
            'codserie' => $this->codserie, 'codagente' => $this->codagente,
            'codcliente' => $this->cliente ? $this->cliente->codcliente : '',
            'desde' => $this->desde, 'hasta' => $this->hasta,
            'order' => tpvmod_order_token_for($this->order), 'offset' => (int) $this->offset];
}

/** @param array<string, scalar|null> $overrides  @param list<string> $omit */
public function list_url(array $overrides = [], array $omit = []): string {
    $params = array_merge($this->list_params(), $overrides);
    foreach ($omit as $key) { unset($params[$key]); }
    return tpvmod_build_list_url($this->url(), $params);   // fsc.url() = 'index.php?page=<name><extra>'
}

private function list_total(): int;   // extracted verbatim from each paginas() switch
public function paginas(): array {
    return tpvmod_pager_links((int) $this->offset, $this->list_total(), FS_ITEM_LIMIT,
                              fn(int $offset): string => $this->list_url(['offset' => $offset]));
}

/** @return array<string, string> memoized; one batched query per rendered page */
private function telefonos_pagina(): array {
    if ($this->telefonos_map === null) {
        $codes = array_map(fn($d) => (string) $d->codcliente, $this->resultados ?: []);
        $this->telefonos_map = tpvmod_phone_map($codes, function (array $set): array {
            $escaped = array_map(fn(string $c): string => $this->var2str($c), $set);
            $sql = 'SELECT codcliente, telefono1, telefono2 FROM clientes'
                 . ' WHERE codcliente IN (' . implode(',', $escaped) . ');';
            $map = [];
            foreach ($this->db->select($sql) ?: [] as $row) { $map[(string) $row['codcliente']] = $row; }
            return $map;
        });
    }
    return $this->telefonos_map;
}
public function telefono_cliente(string $codcliente): string { return $this->telefonos_pagina()[$codcliente] ?? ''; }
```

`buscar()` text block becomes:

```php
$term = tpvmod_search_term((string) $this->query);
$predicate = tpvmod_build_search_predicate($term, fn(string $v): string => $this->var2str($v));
if ($predicate !== '') { $sql .= $where . $predicate; $where = ' AND '; }
```
`codagente`/`codcliente`/`codserie`/`desde`/`hasta`, `COUNT`, `select_limit`, `SELECT *` and
the `SUM(total)` query shape (and the facturas `SUM(neto*porcomision/100)`) are unchanged.

### 1.6 City

No helper, no query: `<td>{{ value.ciudad }}</td>`. `ciudad` is the document snapshot column
(present on all four tables, `plugins/clientes_facturacion/model/table/*cli.xml`) populated at
creation by `tpvmod_aplicar_cliente_a_documento()` from the `domfacturacion` address
(`plugins/tpvmod/lib/tpvmod_modules.php`). Empty ⇒ empty cell.

### 1.10 Shared vs module-specific

| Concern | Shared | Module-specific |
|---|---|---|
| Predicate / term | `tpvmod_search_term`, `tpvmod_build_search_predicate` | table in `FROM`, count column |
| URL | `tpvmod_build_list_url`, `list_params()`, `list_url()`, `tpvmod_order_token_for` | — |
| Pager | `tpvmod_pager_links` | `list_total()` switch (existing totals) |
| Phone | `tpvmod_phone_map`, `tpvmod_resolve_phone`, `telefonos_pagina()`, `telefono_cliente()` | — |
| Dates | `tpvmod_normalize_date` | `tpvmod.php` `vencimiento` read |
| facturas only | — | Vencimiento/Comisión columns, comisión SUM, `#modal_huecos`, `sinpagar` tab, `vencimiento_*` order tokens, `, hora, numero` order2 |
| presupuestos only | — | Rechazar modal + `rechazar()` (also gates `cron_job()`) |
| pedidos only | — | gates `cron_job()` (no Rechazar surface exists) |
| all four | — | line-search model class (`linea_*_cliente`) |

## 2. Fragment and region contract

Every listing control carries, besides its `hx-get` (AD-4):

| Attribute | Value |
|---|---|
| `hx-target` | `#tpvmod-<tipo>-region` |
| `hx-select` | `#tpvmod-<tipo>-region` |
| `hx-swap` | `outerHTML` |
| `hx-push-url` | `true` |

Region ids: `#tpvmod-presupuestos-region`, `#tpvmod-facturas-region`,
`#tpvmod-albaranes-region`, `#tpvmod-pedidos-region` (hard-coded once per template, asserted).

### Why no `isHtmxRequest()` render-partial branch

1. `hx-select` decouples "what to keep" from "what the server returns": one template per module.
2. `isAjax()` is already false for htmx — `is_ajax` is set from `isXmlHttpRequest()` or an
   `ajax` request param only (`base/fs_controller.php:200-202`); htmx sends neither. The
   pipeline therefore already returns the full page to an htmx GET; a branch would add a
   second condition and a duplicate render path.
3. A partial path would need four `ajax/list_*` templates or a `template=false` echo,
   doubling near-clone markup (R8) and bypassing `header`/`footer`.
4. HCS-14's `renderFragment`/`buildFragment`/`requireHtmx()` belong to `HtmxCrudController`;
   tpvmod's listing controllers do not extend it and MUST NOT start. HCS-15's `X-FS-*`
   headers are likewise not emitted — accepted, since this path never emitted them.
5. Cost: a full page per swap. Mitigated by the `cron_job()` gate (AD-10). facturas
   additionally re-runs `factura_cliente::huecos()` per swap — a read, not write amplification.

**Flash messages**: the region does not carry the global alerts block, so a read-only GET
swap does not re-render messages. This matches today's GET-navigation behavior; no
`HX-Trigger`/OOB flash is introduced (that needs the HtmxCrud infrastructure AD-5 rejects).

## 3. Region boundary and control → `hx-*` table

### 3.1 Document order

```
<div id="tpvmod-<tipo>-topbar">                 OUTSIDE — reload | default-page | Nuevo | Rechazar… | extensions | [right] b_buscar_lineas
<form id="f_custom_search" method="get" …>      OUTSIDE, ABOVE the tabs (D4) — query | codserie | codagente | [codcliente read-only + clear] | desde | hasta
                                                 + hidden mostrar / order
<div id="tpvmod-<tipo>-region">                 THE SWAP REGION
    <div class="tpvmod-list-toolbar">           order dropdown (moved here)
    <ul class="nav nav-tabs">                   tabs
    <div class="table-responsive">              results table
    <nav class="tpvmod-pagination">             fsc.paginas()
</div>
f_buscar_lineas modal | modal_huecos (facturas) | modal_rechazar (presupuestos) | footer   OUTSIDE
```

Modals stay outside the region so Bootstrap 3's document-delegated `data-toggle` handlers
and the jQuery client-modal flow (on `tpvmod2`/`tpvmodedita`) are untouched (TCP-06).

### 3.2 Control → attributes

Notation: `R = #tpvmod-<tipo>-region`; every row also carries
`hx-target="R" hx-select="R" hx-swap="outerHTML" hx-push-url="true"`. The `hx-get` values
below are the **complete URL**; the triggering element's own value is appended by htmx (AD-4),
which is why each control omits its own key (`$omit`).

| Control | Element / trigger | `hx-get` | URL effect |
|---|---|---|---|
| full text | `form#f_custom_search`, `submit` | `{{ fsc.url() }}` (+ hidden `mostrar=buscar`, hidden `order`) | form fields |
| serie | `select[name=codserie]`, `change` | `fsc.list_url({'mostrar':'buscar','offset':0}, ['codserie'])` | `codserie=` |
| empleado | `select[name=codagente]`, `change` | `fsc.list_url({'mostrar':'buscar','offset':0}, ['codagente'])` | `codagente=` |
| desde / hasta | `input[type=date]`, `change` | `fsc.list_url({'mostrar':'buscar','offset':0}, ['desde'|'hasta'])` | `desde=` / `hasta=` |
| tabs | `a`, click | `fsc.list_url({'mostrar':'<tab>','offset':0})` | `mostrar=` |
| order option | `a`, click | `fsc.list_url({'order':'<token>','offset':0})` | `order=` |
| pagination | `a`, click | `{{ value['url'] }}` (already `list_url`-built) | `offset=` |
| client clear | `a`, click | `fsc.list_url({'codcliente':'','mostrar':'buscar','offset':0})` | drops `codcliente` |
| Rechazar | `form`, native POST | — | none |
| line search | `form#f_buscar_lineas` + 2 inputs | — (`hx-post="{{ fsc.url() }}"`), §5 | POST body |

Tabs: presupuestos/pedidos `todo|pendientes|rechazados|buscar`; facturas `todo|sinpagar|buscar`;
albaranes `todo|pendientes|buscar`. Order tokens: presupuestos/albaranes/pedidos
`fecha_desc|fecha_asc|codigo_desc|codigo_asc`; facturas `fecha_desc|fecha_asc|vencimiento_desc|vencimiento_asc`.
Tabs/order/filters reset `offset` to 0; only pagination sets a non-zero offset.

**Known limitation (accepted, same as catalogo):** a typed-but-unsubmitted `query` is not
carried when the user then changes a `select`; the URL reflects the server-side state. Submit
or Enter commits the text first. No `hx-include` is used, to avoid duplicate query keys.

### 3.3 Server-side state reading

No parameter renaming: the controllers already read `mostrar`, `offset`, `order`, `codserie`,
`codagente`, `codcliente`, `desde`, `hasta`, and `$this->query` from `fs_filter_input_req`.
`desde`/`hasta` now arrive ISO and are normalized (§6).

## 4. Alpine

```twig
{% import 'Macro/Htmx.html.twig' as htmx %}
{% import 'Macro/Alpine.html.twig' as alpine %}
{{ include('header.html.twig') }}
{{ htmx.boot({'allowScriptTags': false}) }}
… <div id="tpvmod-presupuestos-region" x-data="tpvmodListado" x-bind:class="regionClass()"> …
<script {{ csp_nonce_attr() }}>
(function () {                                   // registration, marker-guarded
    if (window.__tpvmodListadoRegistered === true) { return; }
    window.__tpvmodListadoRegistered = true;
    Alpine.data('tpvmodListado', function () {
        return { loading: false,
                 regionClass: function () { return 'tpvmod-region' + (this.loading ? ' is-loading' : ''); } };
    });
})();
(function () {                                   // exactly one swap listener
    if (window.__tpvmodListadoSwapBound === true) { return; }
    window.__tpvmodListadoSwapBound = true;
    document.addEventListener('htmx:after:swap', function (evt) {
        tpvmodResyncFilterBar();                     // AD-15, §4.1 — runs even without Alpine
        if (!window.Alpine || typeof window.Alpine.initTree !== 'function') { return; }
        var ctx = evt && evt.detail ? evt.detail.ctx : null;
        window.Alpine.initTree(ctx && ctx.target ? ctx.target : document.body);
    });
})();
</script>
{{ alpine.boot() }}
```

- **Component + swap listener**: `tpvmodListado`, one name for all four modules; swap-scoped
  presentation only (`loading` → region class). It owns **no** list state: `mostrar`/`order`/
  `offset`/filters/rows are server-rendered inside the region, so `Alpine.initTree(target)` after
  a swap re-reads server state. The single swap listener now also owns the **bar
  re-synchronization** (AD-15, §4.1): it calls `tpvmodResyncFilterBar()` before
  `Alpine.initTree`, and that call is plain DOM — no Alpine state, no second listener.
- **Idempotency**: `window.__tpvmodListadoRegistered` and `window.__tpvmodListadoSwapBound`
  (catalogo precedent, `ventas_articulos.html.twig:413,471`).
- **Same seam as tarifario (TCP-07)**: colon-style `'htmx:after:swap'` and
  `evt.detail.ctx.target`, identical to `catalogo-main.js:541-578`; the bar re-sync runs on the
  same single seam (§4.1).
- CSP: classic nonce'd script, no inline expression beyond `regionClass()`. Macro boot order is
  irrelevant (both assets are `defer`).
- `Macro/HtmxCrud.html.twig` is **not** imported (AD-5); `htmx-crud.js` is inert without its
  `data-fs-crud-config` script (`view/js/htmx-crud.js:11-14`) and is not loaded here.

### 4.1 Post-swap filter-bar re-synchronization (LHT-14, AD-15)

The bar is outside the region (LHT-03/LHT-13) and is never re-rendered by a swap, so its label,
hidden `codcliente` and non-text controls' `hx-get` go stale: clearing the client left the label,
the hidden field and the other controls' URLs pointing at the cleared customer, so the next filter
change re-applied it. The fix is a plain-DOM `tpvmodResyncFilterBar()` invoked from the single
listener above, **before** `Alpine.initTree` and **outside** that call's Alpine guard, so it runs
even if Alpine is absent. It is defensive on entry (returns when `#f_custom_search` is missing) and
idempotent by construction (it only sets values/attributes from the URL).

- **State source.** `window.location.search`. With `hx-push-url="true"` htmx has already pushed the
  request URL before `htmx:after:swap` fires, so the parsed `URLSearchParams` is the authoritative
  filter state and the re-sync never reads the stale bar to decide state.
- **Base URL (no duplication of the builder).** Each bar control already carries a server-built
  `hx-get` whose prefix is `fsc.url()` (`index.php?page=<name>`); the re-sync takes that prefix
  (text before `?`) and replaces **only** the query string with the transformed `location.search`.
  The base and its encoding stay the helper's output (`tpvmod_build_list_url`), so there is no
  `&key=value` concatenation and no second encoder (LHT-11).
- **Parameter algebra = `list_url($overrides, $omit)`.** The transformation applies exactly the
  helper's semantics at key level: start from the current URL params, apply `overrides`, then
  `delete` the `$omit` keys. `URLSearchParams.set`/`delete` is used, never string building.

| Target | In-place update |
|---|---|
| `#tpvmod-cliente-activo` | `value` emptied when the URL carries no `codcliente`; otherwise set from the active-client name hook (table below) |
| `#f_custom_search input[name="codcliente"]` | `value` = the URL's `codcliente`, or `''` |
| `select[name="codserie"]`, `select[name="codagente"]`, `input[name="desde"]`, `input[name="hasta"]` | `hx-get` rebuilt as `delete` own `name`, `set mostrar=buscar`, `set offset=0` — the §3.2 `list_url({'mostrar':'buscar','offset':0}, [ownKey])` form |
| client-clear control | `hx-get` **and** `href` rebuilt as `delete codcliente`, `set mostrar=buscar`, `set offset=0` — the §3.2 `list_url({'codcliente':'','mostrar':'buscar','offset':0})` form, so it keeps **omitting** `codcliente` |

- **Never replaced / never rewritten.** `input[name="query"]`, `input[name="desde"]` and
  `input[name="hasta"]` elements: no `outerHTML`/`replaceWith`/`innerHTML` over the bar; only the
  label/hidden `value` and the attributes above change. `desde`/`hasta` keep their value and focus
  while their `hx-get` is refreshed (LHT-14 bullet 3, LHT-03 preserved).
- **Order, tabs and pagination are NOT touched by the re-sync.** They live **inside** the swapped
  region (§3.1, AD-14), so the server re-renders them from the same state that produced
  `location.search`. Their scope is the region, not the bar; duplicating their URLs in JS would be
  redundant and is explicitly avoided. The re-sync's scope is exactly the controls that are
  *outside* the region.
- **Controls that must keep omitting a parameter.** The client-clear control means "no client": its
  rebuilt URL **deletes** `codcliente` (rather than setting an empty value), preserving the
  builder's drop-empty semantics; likewise each select/date deletes its own key, because htmx
  appends the triggering element's value (AD-4).

**Hooks the `views` delta provides — and the gaps.**

| Need | Stable hook | Status |
|---|---|---|
| active-client label | `id="tpvmod-cliente-activo"` | present (`views` delta) |
| client code | `input[name="codcliente"]` (hidden) | present |
| serie/agente/dates addressing + own-key omit | the control `name`s (`codserie`, `codagente`, `desde`, `hasta`) | present |
| **active-client display name** | none | **missing from the `views` delta as written.** The URL carries only the code, so the label's human name has no source on the client. Recommended addition: a `data-tpvmod-cliente-nombre` attribute on the region root, server-rendered from `$this->cliente`, read after the swap. Without it the re-sync can only **empty** the label; it MUST leave a non-empty label untouched rather than replace a name with a bare code. |
| **client-clear control identity** | none (only `title="Quitar filtro"`, and it is rendered `{% if fsc.cliente %}`) | **missing.** Recommended addition: a stable `id="tpvmod-cliente-clear"` (or `data-tpvmod-role="cliente-clear"`) on that control. |

**Caveat — the clear control is conditionally rendered (out of U22 scope).** `views` renders the
clear control only when `fsc.cliente` is set, and the bar is not re-rendered during a swap, so the
control cannot appear on a swap that *adds* a client filter, nor disappear on a swap that clears
one. The only htmx control that changes `codcliente` today is the clear control (removal): the `[+]`
row link and deep links are **native navigations** (§3.2 lists no `hx-*` for `[+]`) and re-render the
whole bar server-side. The re-sync therefore covers what a swap can produce — emptied label/hidden
and `codcliente`-free control URLs after a clear. Dynamic show/hide of the clear control needs a
stable hook plus an always-rendered element and is **a separate design decision**: not asserted by
LHT-14 and not in U22. Residual, spec-silent: after a clear swap the now-inert clear button stays
visible until the next full load — cosmetic only, accepted.

**Residual, spec-silent — hidden `mostrar`/`order`.** LHT-14 enumerates the label, the hidden
`codcliente` and the non-text control URLs; it does not cover the search form's hidden `mostrar`/
`order`. `mostrar` is a constant `buscar` for that form, so it cannot drift; `order` can drift if
the user changes the order inside the region and then submits the bar (the hidden field keeps its
render-time value). Recorded for a possible follow-up rather than widened into U22.

## 5. htmx line search

### 5.1 Form

```twig
<form id="f_buscar_lineas" name="f_buscar_lineas" action="{{ fsc.url() }}" method="post"
      hx-post="{{ fsc.url() }}" hx-target="#search_results" hx-swap="innerHTML"
      hx-trigger="submit" hx-sync="this:replace">
   {{ csrf_field() }}
   <input type="hidden" name="offset" value="0"/>
   … <input name="buscar_lineas"   hx-post="{{ fsc.url() }}" hx-target="#search_results"
            hx-swap="innerHTML" hx-trigger="keyup changed delay:300ms"
            hx-sync="closest form:replace"/> …
   … <input name="buscar_lineas_o" (same attributes) /> …
</form>
```

- POST semantics: htmx includes the **enclosing form** for a POST, so both inputs and the
  form's submit post the whole form (term, observations, `codcliente`, `offset`, `_csrf_token`).
  No `hx-include`, no `hx-params`.
- `method="post"` + `{{ csrf_field() }}` are retained for the no-JS fallback and pinned by the
  `views` delta. On htmx requests the inherited `X-CSRF-TOKEN` header is present too;
  `validateCsrf()` accepts either source (`base/fs_controller.php:391-397`).
- `hx-sync="closest form:replace"` (extended selector verified in `view/js/htmx.min.js`) makes a
  newer request abort the in-flight one, replacing the `<!--{{ fsc.buscar_lineas }}-->` marker
  and the JS comparison (`tpvmod_presupuestos.html.twig:28-33`). `delay:300ms` debounces.
- `buscar_lineas`/`buscar_lineas_o` use `changed` so key presses that do not change the value do
  not fire (the modifier is implemented in the vendored asset).

### 5.2 Deleted legacy JS

From each listing template delete the inline `buscar_lineas()`, `mas_resultados()` and
`clean_cliente()` functions and the `$(document).ready` block that bound `#b_buscar_lineas`
(modal show) and `#f_buscar_lineas` keyup/submit. The modal opens via the existing
`data-toggle="modal" data-target="#modal_buscar_lineas"`. This removes the last `$.ajax` in the
four listings.

### 5.3 Fragment templates

For all four `view/ajax/ventas_lineas_*.html.twig`:

- Remove line 2 `<!--{{ fsc.buscar_lineas }}-->`; change the alerts' bare `<li>` to `<div>`
  (well-formed fragment hygiene; the fragments do not swap into `<tr>` — see G4).
- Replace the inline `mas_resultados('±N')` pager with server-computed offset controls:

```twig
{% if fsc.lineas %}
<ul class="pager">
   {% if fsc.offset > 0 %}
   <li class="previous"><a href="#" hx-post="{{ fsc.url() }}"
        hx-vals='{"offset": {{ max(0, fsc.offset - fsc.lineas|length) }}}'
        hx-target="#search_results" hx-swap="innerHTML"
        hx-sync="closest form:replace">Anteriores</a></li>
   {% endif %}
   {% if fsc.lineas|length == constant('FS_ITEM_LIMIT') %}
   <li class="next"><a href="#" hx-post="{{ fsc.url() }}"
        hx-vals='{"offset": {{ fsc.offset + fsc.lineas|length }}}'
        hx-target="#search_results" hx-swap="innerHTML"
        hx-sync="closest form:replace">Siguientes</a></li>
   {% endif %}
</ul>
{% endif %}
```

`hx-vals` overrides the hidden `offset` (htmx uses `FormData.set`); the anchors are inside
`#f_buscar_lineas`, so the enclosing form supplies the term and `codcliente`. The step
`fsc.lineas|length` reproduces the legacy arithmetic exactly.

### 5.4 Controller parity

`tpvmod_facturas::buscar_lineas()` gains `$this->offset` in both calls
(`search($term, $this->offset)`, `search_from_cliente2($codcliente, $term, $obs, $this->offset)`)
and its template gains the pager — parity with the other three (signatures verified in
`plugins/clientes_facturacion/model/core/linea_*_cliente.php`). The client-scoped branch
(`isset($_POST['codcliente'])` ⇒ `search_from_cliente2`) is preserved verbatim in all four.
The line models' own SQL is in `clientes_facturacion` and is **out of scope** (LSS applies to
the listing `buscar()` predicate only).

## 6. Native dates

| Surface | Change |
|---|---|
| 4 listings `desde`/`hasta` | `<input type="date" name="desde" value="{{ fsc.desde|date_iso }}" …>`, `class="datepicker"` and `onchange="this.form.submit()"` removed (htmx `change` replaces the submit) |
| `tpvmod_presupuestos` Rechazar input | `<input type="date" name="rechazar" value="{{ 'now'|date('Y-m-d') }}" …>` (display format changes from `j-n-Y` because a native input only carries ISO) |
| `tpvmodedita.html.twig` `fecha` | `<input type="date" name="fecha" value="{{ fsc.documento.fecha|date_iso }}" …>` |

Controller side, in each listing: `$this->desde = tpvmod_normalize_date($_REQUEST['desde'] ?? '');`
(existing `if (isset($_REQUEST['desde'])) {…}` block shape preserved; only the value is
normalized), same for `hasta`. Empty/invalid ⇒ `''` ⇒ no date predicate. `Y-m-d` does not match
`var2str()`'s `d-m-Y` regex, so it is quoted as a plain literal matching the `date` column.

**Explicit non-bug statement**: the range filter was **not** broken. `fs_db2::var2str()` already
normalizes `d-m-Y` through `parseDateValue()` (`base/fs_db2.php:189-213`) and PHP parses
dash-separated `dd-mm-yyyy` as European. The motivation is the **jQuery UI dependency**: the
`.datepicker` class is auto-initialized by `plugins/legacy_support/view/js/legacy-init.js:26-33`,
keeping a legacy coupling and a widget that would need re-init after every swap; native inputs
need none. `tpvmod.php:679` (`date("Y-m-d", strtotime($_POST['fecha'] . " +30 days"))`) reads the
ISO value once the input is native — **no logic change**; the document saves
(`tpvmod.php:1262,1507,1737,1973`) store the ISO string that matches the `date` column.

## 7. Full-text SQL per engine, portability, LSS verification

The §1.3 predicate is valid unchanged on MySQL and PostgreSQL: `lower(col) LIKE '<literal>'`,
`col IN (SELECT … FROM clientes …)`, driver-aware quoting via `var2str()`, `fecha >= '<Y-m-d>'`.
`ILIKE` is **not** emitted (PostgreSQL-only); case-insensitivity comes from `lower()` on both
sides plus `strtolower` in PHP.

Rejected (for the record): MySQL `MATCH (codigo, numero2, observaciones) AGAINST (…)` and
PostgreSQL `to_tsvector('spanish', …) @@ to_tsquery(…)` — `model/table/*.xml` has no
FULLTEXT/`tsvector` index concept (only PK/FK `<restriccion>`), no repo precedent, and an engine
branch would be needed. `cliente::search()` uses the same multi-field `LIKE` shape.

LSS verification: LSS-01 — every pattern literal goes through `$sqlString` →
`$this->var2str()` → `fs_db2::escape_string()`; no raw interpolation. LSS-02 —
`no_html()`/`htmlspecialchars` is removed from the SQL path, and `tpvmod_search_term()` reverses
the framework's input escape. The helper test asserts: `O'Brien` is emitted as an escaped SQL
literal; the emitted SQL contains no `&#39;`/`&lt;`/`&gt;`/`&amp;`; `<script>` appears raw
inside the quoted literal (SQL-escaped, not HTML-encoded). Output escaping stays the view's
responsibility (Twig auto-escape).

## 8. Client-dispatch disposition

**(c) The four listing controllers keep the `tpvmod_cliente_ajax_dispatch($this)` call**
(`tpvmod_presupuestos.php:111`, `tpvmod_facturas.php:110`, `tpvmod_albaranes.php:107`,
`tpvmod_pedidos.php:108`). `lib/tpvmod_cliente_ajax.php:27-51` reacts only to POST keys the
listings no longer emit (`buscar_cliente_modal`, `cliente_form`, `save_cliente_tpv`,
`save_direccion_tpv`, `delete_direccion_tpv`), so it is inert for listing traffic but keeps the
pinned assertions green and preserves the `buscar_cliente_modal` path for external callers.

**(a) `testViewsNoLongerUseClienteAutocomplete` (`TpvmodTwigTemplatesTest.php:83-100`).** Today
it iterates six views asserting `devbridgeAutocomplete` absent **and**
`tpvmod-b-buscar-cliente` present. Split into:

```php
$pickerViews  = ['tpvmod2.html.twig', 'tpvmodedita.html.twig'];
$listingViews = ['tpvmod_albaranes.html.twig', 'tpvmod_pedidos.html.twig',
                 'tpvmod_presupuestos.html.twig', 'tpvmod_facturas.html.twig'];
// all six: assertStringNotContainsString('devbridgeAutocomplete', …)
// $pickerViews:  assertStringContainsString('tpvmod-b-buscar-cliente', …)
// $listingViews: assertStringNotContainsString('tpvmod-b-buscar-cliente', …)
```
This is the "narrowed, not dropped" assertion the `views` delta requires.

**(b) `testControllersDropCsrfWorkaround` (`TpvmodTwigTemplatesTest.php:102-110`).** Unmodified:
it iterates `tpvmod.php`, `tpvmod_albaranes.php`, `tpvmod_pedidos.php` and asserts the dispatch
call is present and `function buscar_cliente` is absent. §8(c) keeps it green; no new listing
controller is added to the list. `TpvmodOpcionalRapidoTest.php:637-647` (dispatch ordering in
`tpvmod.php`) is likewise unmodified — `tpvmod.php` is not a listing controller.

## Compatibility with the tarifario pilot

Both plugins opt in through the same core seams (`fs_controller::isHtmxRequest()`,
`Macro/Htmx.html.twig`, htmx 4 colon-style events, inherited `X-CSRF-TOKEN`). No page or
template is shared, so parity means parity of mechanics, not of markup.

| TCP | Pilot requirement | How tpvmod satisfies it | Covering LHT-* |
|---|---|---|---|
| TCP-01 | Lazy-load rows via hx attributes; remove the `$.ajax` fetch path. | No lazy rows. Analogue: the line search's `$.ajax`/`mas_resultados()` is deleted and replaced by `hx-post` + `hx-target="#search_results"`. | LHT-10 |
| TCP-02 | View-mode/sort refetch via `hx-get` with the same params. | Order options and tabs refetch via `hx-get="{{ fsc.list_url({'order': token}) }}"` / `{{ …{'mostrar': tab} }}`. | LHT-04 |
| TCP-03 | Load-more appends via `hx-swap="beforeend"`. | **Not exercised**: the line pager replaces `#search_results` (`innerHTML`) with a server-computed offset. No LHT requires append. | none — **G1** |
| TCP-04 | Full-page filters push the URL. | `f_custom_search` + `codserie`/`codagente`/`desde`/`hasta` + tabs + order carry `hx-push-url="true"` over the region. | LHT-01, LHT-04 |
| TCP-05 | Response parity with `$.ajax` consumers (same endpoint, params, template). | The line search still POSTs to `{{ fsc.url() }}` with `buscar_lineas`/`buscar_lineas_o`/`codcliente`(+`offset`) and still sets `$this->template = 'ajax/ventas_lineas_<tipo>'`. No core fragment API. | LHT-10 |
| TCP-06 | jQuery flows preserved. | Rows keep `tr.clickableRow[href]` (delegated in `view/js/base.js:72`, survives swaps); Bootstrap 3 `data-toggle` dropdown/modal is document-delegated; the `tpvmod2`/`tpvmodedita` jQuery client modal is untouched. No JSON toggles/sortable/exports in the listings. | LHT-01/LHT-03 + `views`/`tpv-cliente-modales` — **partial, G2** |
| TCP-07 | `htmx:after:swap` re-init shim, htmx 4 colon-style. | One listener on `'htmx:after:swap'` resolving `evt.detail.ctx.target` and calling `Alpine.initTree` — same event spelling and ctx resolution as `catalogo-main.js:541-578`. | LHT-02 |
| TCP-08 | Dead plugin `is_htmx_request()` removed; delegate to `isHtmxRequest()`. | tpvmod never had one and adds none; `cron_job()` is gated with `$this->isHtmxRequest()`. A test asserts no `is_htmx_request`/`tpvmod_is_htmx_request` symbol exists in the plugin. | LHT-11 (intent) — **G3** |
| TCP-09 | Plugin regression suite green. | New `TpvmodListadosHelpersTest` + extended `TpvmodTwigTemplatesTest` under `plugins/tpvmod/phpunit.xml`; existing suites stay green. | LHT-12 |
| TCP-10 | Error rows swapped into `<tr>` are well-formed. | No tpvmod surface swaps into a `<tr>`: the line-search target is `<div id="search_results" class="table-responsive">` and listing swaps are region-level `outerHTML`. Fragments are still made well-formed (`<li>`-in-`<div>` alerts → `<div>`). | none — **G4** |

**Why tarifario not using Alpine does not break compatibility.** Alpine loads only through
`Macro/Alpine.html.twig`, opted in per view (ABS-02). tarifario uses a plain jQuery shim on the
same colon-style `htmx:after:swap` event; tpvmod uses it plus a marker-guarded `Alpine.data` +
`Alpine.initTree`. Both are valid per-view choices of the same core contract. The two plugins
share no route, template or script set, so the two swap listeners never coexist. Shared
invariants are core-level and both satisfy them: htmx loaded once via the macro (HCS-04), CSRF
via the inherited header (HCS-07), colon-style event names. tpvmod's extra
`boot({'allowScriptTags': false})` only adds the `htmx:before:swap` scrubber (HCS-12) and cannot
affect tarifario.

### Spec gaps for the spec phase

Gaps are declared here and are **not** turned into new requirements; the spec phase decides
whether to widen an existing `LHT-*` or accept the gap.

- **G1 — TCP-03 (`beforeend` load-more) is not exercised by tpvmod.** LHT-10 pins replace
  semantics. Recommendation: note in LHT-10 that append-style load-more is out of contract.
- **G2 — TCP-06 is only partially covered.** LHT-01/03 keep the full page and the
  `tr.clickableRow[href]` markup, and `views`/`tpv-cliente-modales` keep the jQuery modal on
  `tpvmod2`/`tpvmodedita`, but no `LHT-*` explicitly requires the delegated `clickableRow`
  handler (`view/js/base.js:72`) and the delegated `data-toggle` dropdown/modal to keep working
  after a swap. Recommendation: add an explicit preservation clause (or mark it smoke-only).
- **G3 — TCP-08's "no local HX helper" rule has no literal `LHT-*`.** LHT-11 requires the
  `cron_job()` gate to use `isHtmxRequest()` but does not forbid a plugin-local
  `is_htmx_request`. tpvmod complies by construction and the design adds a test assertion.
  Recommendation: fold the TCP-08 wording into LHT-11.
- **G4 — TCP-10 has no tpvmod surface.** The line-search target is a `<div>`; listing swaps are
  region-level `outerHTML`; tpvmod never swaps into a `<tr>`. Recommendation: note in LHT-10 that
  the fragment root is not a `<tr>` and TCP-10 is out of scope here.
- **G5 — LHT-11 names only `tpvmod_presupuestos` for `cron_job()`, but `tpvmod_pedidos` also
  calls it** (`tpvmod_pedidos.php:187`). The design gates both (AD-10). Recommendation: widen
  LHT-11 to "each listing controller that invokes a model `cron_job()`".

## Verification map

`strict_tdd: true` (`plugins/tpvmod/openspec/config.yaml`). **H** = new
`plugins/tpvmod/tests/TpvmodListadosHelpersTest.php` (DB-free). **T** = extended
`plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (DB-free source/structural assertions).
**S** = manual smoke step recorded in `verify-report.md`.

### `listados-htmx` (LHT-01..LHT-14)

| ID | H (helper test) | T (Twig/contract assertion) | S (smoke) |
|---|---|---|---|
| LHT-01 | — | `testListingsDeclareStableSwapRegion`: exactly one `id="tpvmod-<tipo>-region"` per template; every mapped control carries `hx-target`/`hx-select` equal to it and `hx-swap="outerHTML"`; no `isHtmxRequest` in the four controllers' listing path | direct load = full page; JS-disabled tab/order/page returns the full page; a pushed URL reloads identically |
| LHT-02 | — | `testListingsImportHtmxAndAlpineOnce`: one `{% import 'Macro/Htmx.html.twig' %}`, one `{% import 'Macro/Alpine.html.twig' %}`, `htmx.boot({'allowScriptTags': false})`, `alpine.boot()`, the two `window.__tpvmodListado*` markers, exactly one `'htmx:after:swap'` binding, **no** `HtmxCrud.html.twig`; `header`/`footer` free of `htmx`/`Alpine` | tab switch re-inits Alpine once (no duplicate-registration console warning) |
| LHT-03 | — | `testRegionBoundaryAndOrder`: `id="f_custom_search"` byte offset < region offset; `nav-tabs` and `fsc.paginas()` inside the region; order dropdown inside the region; `f_buscar_lineas`/`modal_huecos`/`modal_rechazar` after the region close | active tab + order checkmark correct after a swap; filter input keeps focus/value |
| LHT-04 | `testListUrlCarriesEveryFilterAndEncodesSpecialChars` | `testControlToUrlMapping`: `hx-get` = `fsc.list_url(...)` form with the own-key omitted, `hx-trigger` (`submit`/`change`), `hx-push-url="true"`; tab and order tokens per module; **no `hx-params` anywhere** | each filter/tab/order/page click updates region + URL; Rechazar still POSTs |
| LHT-05 | `testSearchTermDecodesFrameworkEscape`, `testSearchPredicateMatchesDocumentAndClientFields`, `testSearchPredicateEscapesQuotes`, `testSearchPredicateHasNoCifnif`, `testSearchPredicateEmptyTerm` | `testListingControllersUseSharedSearchHelper`: each controller contains `tpvmod_search_term(`/`tpvmod_build_search_predicate(` and no `no_html` in `buscar()` | search by company name, by phone, by code, and `O'Brien` |
| LHT-06 | `testPhoneMapPrefersTelefono1`, `testPhoneMapFallsBackToTelefono2`, `testPhoneMapEmptyPhones`, `testPhoneMapSkipsFetchForEmptySet` | `testListingsRenderPhoneColumn`: each template calls `fsc.telefono_cliente(value.codcliente)`; controllers contain the batched `FROM clientes` lookup | a customer with only `telefono2` shows it; with neither shows blank |
| LHT-07 | — | `testListingsRenderCitySnapshot`: each template renders `{{ value.ciudad }}`; no `dirclientes`/`domfacturacion` lookup in the listing controllers | document with a billing city shows it; one without shows blank |
| LHT-08 | — | `testListingViewsExcludeClientPicker`: none of `ac_cliente`, `tpvmod-b-buscar-cliente`, `partials/modal_clientes.html.twig`, `tpvmod-cliente.js`; read-only client text + clear control carrying `fsc.list_url({'codcliente': ''})` | `&codcliente=CLI001` filters and shows read-only text; clear removes it; `[+]` filters |
| LHT-09 | `testNormalizeDateAcceptsIsoAndDmY`, `testNormalizeDateRejectsEmptyAndInvalid`, `testNormalizeDateParityWithHtmlFilter` | `testNoDatepickerAndNativeDates`: `datepicker` absent from the five views; `type="date"` + `|date_iso` on `desde`/`hasta`; Rechazar input; `tpvmodedita` `fecha`; `tpvmod_normalize_date(` in each controller | range bounds results; all 10 inputs open the native picker |
| LHT-10 | — | `testLineSearchFragmentContract`: `hx-post="{{ fsc.url() }}"`, `hx-target="#search_results"`, `hx-swap="innerHTML"`, `hx-trigger` with `delay`, `hx-sync`; marker absent from the four fragments; no `mas_resultados(`; each fragment has `hx-post` + `hx-vals` `offset` (facturas included); alerts use `<div>` not bare `<li>` | typing debounces and swaps; pager prev/next; client-scoped search; invalid token follows the POST rejection path |
| LHT-11 | `testBuildListUrlEncodesAndDropsEmpty`, `testBuildListUrlKeepsZero`, `testPagerLinksBoundsAndPrunes`, `testPagerLinksEmptyWhenSinglePage`, `testOrderTokenFor` | `testListingControllersGateCronAndBuildUrls`: presupuestos + pedidos have `isHtmxRequest` adjacent to `cron_job()`; each controller uses `list_url(` and has no `"&query="`/`"&mostrar="` concatenation in `paginas()` | htmx paging does not execute the cron updates (DB/log observation) |
| LHT-12 | the H suite itself runs with no DB via `plugins/tpvmod/phpunit.xml` | suite green | `ddev exec composer phpstan` |
| LHT-13 | — | `testFilterBarRendersInEveryListingState` (U21): the `f_custom_search` block is not wrapped in a `{% if fsc.mostrar == 'buscar' %}` guard; the form still precedes the region; the autofocus guard stays | bar visible on the default and intermediate tabs; submitting jumps to `buscar` |
| LHT-14 | — | `testFilterBarResyncsAfterSwap` (U22, §4.1): the single `'htmx:after:swap'` handler reads `location.search`, targets `id="tpvmod-cliente-activo"` and `input[name="codcliente"]`, rebuilds the `hx-get` of `select[name=codserie]`/`select[name=codagente]`/`input[name=desde]`/`input[name=hasta]` and the client-clear control, and never replaces the `query`/`desde`/`hasta` inputs (no `outerHTML`/`replaceWith` over the bar); `testListingsImportHtmxAndAlpineOnce` still counts exactly one `'htmx:after:swap'` | clear the client, then change a non-text filter → the request carries no `codcliente`; label + hidden empty after clearing (correct after a native `[+]`/deep-link reload); `query`/date inputs keep focus across a swap; name/phone search unchanged |

### `views` (delta)

| Requirement | Assertion |
|---|---|
| CSRF via Twig function only | existing `testEveryPostFormCarriesCsrfField` (`:229-254`) stays green; `f_custom_search` is now GET and needs none |
| Nine debt-fill templates data shape | existing `testAllExpectedTwigTemplatesExist` unchanged; the 4 `ventas_lineas_*` rows gain the pager + drop the marker (T) |
| Template inventory + controller cleanup | `testViewsNoLongerUseClienteAutocomplete` narrowed (§8a); others unchanged |
| Filter form precedes the region | T `testRegionBoundaryAndOrder` |
| Native date inputs | T `testNoDatepickerAndNativeDates` |
| Client picker absent from the four listings | T `testListingViewsExcludeClientPicker` |
| Filter bar exposes stable re-synchronization hooks | T `testFilterBarResyncsAfterSwap`: `id="tpvmod-cliente-activo"`, hidden `input[name="codcliente"]` and the control `name`s are addressable without re-rendering the bar; §4.1 gaps: the active-client name and the clear-control identity need `data-*`/id hooks (recommended) |

### `tpv-cliente-modales` (delta)

| Requirement | Assertion |
|---|---|
| Modal stays on `tpvmod2`/`tpvmodedita` | T still asserts `tpvmod-b-buscar-cliente` present there; `view/js/tpvmod-cliente.js` and `view/partials/modal_clientes.html.twig` still exist and are included |
| Modal not part of the four listings | T `testListingViewsExcludeClientPicker` |
| `&codcliente=` filter without modal | T read-only client text + clear control; controller keeps the `codcliente` branch in `buscar()`; S deep link filters and clear removes |

### `TCP-*` (pilot compatibility)

TCP-01/TCP-02/TCP-04/TCP-05/TCP-07/TCP-08 have the `T` assertions in the LHT-04/LHT-10/LHT-11
rows above plus: TCP-01 `T` no `$.ajax`/`mas_resultados(`/`buscar_lineas()` definition in the
four templates; TCP-05 `T` the four controllers keep `$this->template = 'ajax/ventas_lineas_<tipo>'`
and the forms post to `{{ fsc.url() }}`; TCP-07 `T` exactly one `'htmx:after:swap'` binding and
no `htmx:afterSwap`/`htmx:afterRequest` (v2 spelling). TCP-03/TCP-10 = declared gaps G1/G4.
TCP-09 = **S** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

## Codebase interface summary

Non-obvious attributes relied upon (verified in `view/js/htmx.min.js`): `hx-get`, `hx-post`,
`hx-target`, `hx-select`, `hx-swap`, `hx-push-url`, `hx-trigger`, `hx-sync`, `hx-vals`.
Trigger modifiers `changed`, `delay:<ms>`, `once`, `prevent`, `throttle`; `hx-sync` strategies
`drop|abort|replace|queue`, including the `closest <sel>:<strategy>` extended selector.
`hx-params` is **NOT** available (AD-4).

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit (DB-free) | Search term + predicate + escaping, list URL encoding, phone map/fallback, pager arithmetic, date normalization | new `TpvmodListadosHelpersTest` with injected escapers/fetchers; no DB, no Twig |
| Contract (DB-free) | htmx/region/date/picker/fragment markup of the four listings + `tpvmodedita` | extend `TpvmodTwigTemplatesTest` with source/structural assertions |
| Integration/smoke | tabs/order/pagination/filters, line search + pager, CSRF rejection, cron gate, JS-disabled degradation | manual checklist per module recorded in `verify-report.md`; `ddev exec composer phpstan` |

## File Changes

| File | Action | Description |
|---|---|---|
| `plugins/tpvmod/lib/tpvmod_listados.php` | Create | 8 pure helpers (§1) |
| `plugins/tpvmod/tests/TpvmodListadosHelpersTest.php` | Create | DB-free helper coverage |
| `controller/tpvmod_{presupuestos,facturas,albaranes,pedidos}.php` | Modify | `require_once` helper, predicate via `var2str`, `list_params`/`list_url`/`paginas` façade, phone map + accessor, date normalization, `cron_job()` gate (presupuestos/pedidos), facturas line-search offset |
| `view/tpvmod_{presupuestos,facturas,albaranes,pedidos}.html.twig` | Modify | Macro imports + boot, region + control `hx-*`, filter form above the tabs (GET + hidden `mostrar`/`order`), phone/ciudad columns, picker removal, native dates, htmx line-search form, legacy JS deleted |
| `view/ajax/ventas_lineas_*.html.twig` | Modify | Marker removed, server-computed offset pager, well-formed alerts; facturas pager added |
| `view/tpvmodedita.html.twig` | Modify | `fecha` → native date |
| `controller/tpvmod.php` | Modify | Only the `vencimiento` ISO read contract (§6); no logic change |
| `tests/TpvmodTwigTemplatesTest.php` | Modify | Narrow the client-button assertion; add htmx/date/fragment assertions |
| `plugins/{clientes_core,clientes_facturacion,facturacion_base}/**` | No change | Out of scope |

## Data Flow

```
browser ──hx-get(list_url)──▶ index.php ──▶ fs_controller ──▶ tpvmod_<tipo>
                                                  │  read state (mostrar/order/filters/offset)
                                                  │  tpvmod_search_term → tpvmod_build_search_predicate
                                                  │  model query + tpvmod_phone_map (1 batched SELECT)
                                                  ▼
                                       full page HTML (header + form + region + modals + footer)
                                                  │
                            htmx extracts #tpvmod-<tipo>-region (hx-select)
                                                  ▼
                            region replaced (outerHTML) · URL pushed · Alpine.initTree(target)

line search: hx-post(fsc.url()) ─▶ buscar_lineas() ─▶ $this->template='ajax/ventas_lineas_<tipo>'
                                                  └─▶ response body = fragment ─▶ replaces #search_results
```

## Rollback and implementation order

**Rollback.** Confined to `plugins/tpvmod/` (nested git repo): no core file, no schema
migration, no new Composer/npm dependency, no `vendor/` change. Revert the three PRs (or the
merged commits), or restore the previous plugin tag. No DB rollback: the search extension is
query-only, the date inputs write the same `date` column, and `ciudad`/`telefono` are read-only
projections. Reverting restores the `.datepicker` inputs and the jQuery line search, which
again require `plugins/legacy_support/view/js/legacy-init.js` (untouched).

**Chained PRs** (~1,050–1,450 lines, over the 800-line review budget):

| PR | Scope | Verify |
|---|---|---|
| 1 — helpers + controller hardening | New helper file + tests; four controllers: `require_once`, predicate via `var2str` + `tpvmod_search_term`, `paginas()` façade + `list_total()`, `list_params()`/`list_url()`, phone map + accessor, date normalization, `cron_job()` gate (presupuestos/pedidos), facturas line-search offset | plugin suite + `phpstan` |
| 2 — htmx/Alpine + fragments | Four templates: imports + boot, region + top-bar split, `hx-*` per §3.2, filter form GET, line-search `hx-post`, Alpine registration + swap listener, legacy JS deleted; four fragments: marker removed, offset pager, well-formed alerts; extend `TpvmodTwigTemplatesTest` | plugin suite + smoke per module |
| 3 — columns, reorder, picker removal, dates | Four templates: phone + ciudad columns, filter form above tabs, picker → read-only text + clear, native dates; `tpvmodedita` date; narrow the client-button assertion + picker/date assertions | plugin suite + full smoke + `phpstan` |

PR 1 is behavior-preserving at the UI level (URLs/predicates/pager arithmetic identical plus new
accessors) → mergeable with no visual regression risk; PR 2 introduces transport; PR 3 is
presentational, so PR 2's smoke runs before the layout churn.

## Core traceability

| Contract | How this design respects it |
|---|---|
| HCS-03 | `header`/`footer` untouched; asserted in T. |
| HCS-04 | htmx only via `Macro/Htmx.html.twig`, once per view; no global load. |
| HCS-05 | The macro emits the nonce'd asset + inherited `X-CSRF-TOKEN` bootstrap; the header serves every htmx POST. |
| HCS-06 | `isHtmxRequest()` gates `cron_job()` in presupuestos and pedidos; no other server-side htmx branch. |
| HCS-07 | The line-search `hx-post` validates through `validateCsrf()` via `X-CSRF-TOKEN` (or the retained fallback field); `CsrfManager` unchanged. |
| HCS-08 | Listing GET swaps need no token; the filter form is a GET. |
| HCS-12 | `boot({'allowScriptTags': false})` emits the scrubber; marker guards prevent double Alpine registration. |
| HCS-14 | No core fragment API; the legacy `$this->template` path is preserved; `HtmxCrudController`/`index.php` untouched; no listing `isHtmxRequest()` branch (§2). |
| HCS-15 | Not emitted by this path; `HtmxCrudController`/`buildFragment()` unmodified for their own consumers. |
| ABS-01/02/03 | Alpine only via `Macro/Alpine.html.twig`, once per view; CSP build; no global load; header/footer clean. |
| LSS-01 | Every pattern literal goes through `var2str()`/`escape_string()` via the injected `$sqlString`. |
| LSS-02 | `no_html()` removed from the four `buscar()`; `tpvmod_search_term()` reverses the framework's input escape; the helper never HTML-escapes for SQL. |

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or
process-integration boundary. The change touches HTML templates, legacy controllers and pure
PHP helpers; the security-relevant work is SQL escaping (LSS-01/02) and CSRF (HCS-07/08), both
covered above and in the verification map.

## Open Questions

None that block the design. All seven decisions are confirmed in `decisions-pending.md`; the
dispatch disposition and the date non-bug are resolved here (§6/§8). Residual items are tracked
as spec gaps G1–G5 and residual risks below.

## Residual risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | Raw concatenation replaced but pattern semantics change (numeric branch dropped) | helper tests pin the emitted SQL for text and numeric terms |
| R2 | `cron_job()` (5 UPDATEs) per htmx fragment | gated on `isHtmxRequest()` in both modules |
| R3 | Unencoded `paginas()` URLs fed to `hx-get`/`hx-push-url` | single `http_build_query` builder feeds both `href` and `hx-get` |
| R4 | Flash messages not re-rendered after a GET swap | accepted (exploration F9-R4); no HX-Trigger/OOB flash |
| R5/R6 | Alpine double-registration; stale tab/order state | marker guards; controls inside the region |
| R8 | Four-way clone drift (facturas extras) | shared helpers + per-module template assertions |
| R11 | Line-model SQL in `clientes_facturacion` still uses `no_html()` interpolation | out of scope; documented, pre-existing, not regressed by this change |
| R12 | Bar stays stale after a swap (cleared client re-applied by the next filter change) | AD-15/§4.1 re-syncs label, hidden and non-text control URLs from `location.search` inside the single listener; U22 + `testFilterBarResyncsAfterSwap`; clear-control show/hide and hidden `order` recorded as out-of-scope residuals (§4.1) |

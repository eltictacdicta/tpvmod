# Proposal: tpvmod-listados-htmx

> Plugin-local SDD. Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` is intentionally NOT touched.
> Artifact store: OpenSpec (plugin-local). Inputs: `exploration.md`,
> `decisions-pending.md` (all decisions confirmed), and the core/plugin specs.

## Intent

Migrate the four tpvmod document listings (`tpvmod_presupuestos`,
`tpvmod_facturas`, `tpvmod_albaranes`, `tpvmod_pedidos`) to htmx 4 + Alpine.js 3
(CSP build), enrich each row with the customer phone and the billing-address
city, **adapt** the existing listing search into a multi-field full-text search
(company name + phone alongside today's fields), move the line search ("Buscar
en las líneas") from jQuery `$.ajax` to htmx while keeping server-side offset
pagination, and replace the jQuery UI date inputs with native ones.

Verbatim user request (Spanish): *"tpvmod_presupuestos y lo mismo para facturas,
presupuestos y albaranes: quiero pasar el listado a htmx 4 con alpine.js, añadir
los campos en el listado de teléfono y la ciudad de la dirección si la tiene (la
que esté establecida como dirección de facturación) y hacer un buscador full text
con los campos nombre de empresa, teléfono; y también quiero adaptar esta
tecnología al buscador de líneas."*

Clarifications: the search already exists and is **adapted**, not rebuilt; the
date selector **must not depend on jQuery UI**; the listings must **not** carry a
client modal (full text + dates + employee + serie instead).

## Scope

### In Scope

- htmx 4 + Alpine CSP on the 4 listings: tabs, order dropdown, pagination and
  every filter become `hx-get` with `hx-push-url="true"` over a stable swapped
  region (catalogo pattern, HCS-04/05).
- Row enrichment: `telefono1` (fallback `telefono2`, one value per row) and the
  **document billing-city snapshot** `value.ciudad`.
- Adapted listing search (existing `f_custom_search`): match live `clientes.nombre`
  + `razonsocial` + `telefono1/2` via `codcliente IN (subquery)`, **keeping**
  `codigo` / `numero2` / `observaciones`. No `cifnif`. Portable multi-field
  `LIKE`/`ILIKE`; no schema change.
- Filter form moved **above the tabs** so tabs + order + table + pagination live
  inside the swapped region.
- Client picker removed from the 4 listings (`ac_cliente`, the
  `tpvmod-b-buscar-cliente` button, the `partials/modal_clientes.html.twig`
  include and the `tpvmod-cliente.js` load). The `&codcliente=` URL filter is
  **retained**; the active client renders as read-only text.
- Line search on htmx (`hx-post` + `hx-target="#search_results"`) with debounce +
  `hx-sync`, deleting the stale-response comment trick. `tpvmod_facturas` gains
  offset/pager parity with the other three.
- All 10 `.datepicker` inputs (4 listings + `tpvmodedita`) → native
  `<input type="date">` + the existing `date_iso` filter; `class="datepicker"`
  removed.
- Shared pure helpers under `plugins/tpvmod/lib/` to prevent four-way clone drift.
- Plugin tests + plugin-local delta specs updated.

### Out of Scope

- Migrating the client-search modal to htmx (it stays jQuery for `tpvmod2` and
  `tpvmodedita`, which keep using it; the modal partial and its JS are **not**
  deleted).
- The `rechazar()` bug that ignores the modal date and rejects every pending
  document (surfaced during scoping, deferred).
- Any change under `plugins/clientes_facturacion/`, `plugins/clientes_core/`, the
  core `src/`, `base/`, `controller/`, `model/`, or the core `openspec/`.
- PSR-4 refactor of the legacy tpvmod controllers.
- A schema migration of the document snapshot columns.

## Capabilities

### New Capabilities

- `listados-htmx`: the htmx 4 + Alpine CSP contract for the four document
  listings — swapped region, mapped controls, phone/ciudad row enrichment,
  adapted multi-field search, native date filters, and the htmx line-search
  fragment with offset pager.

### Modified Capabilities

- `views`: view-layer conventions change — filter form above tabs; stable swap
  region; native `<input type="date">` + `date_iso` replacing `.datepicker`; the
  client picker/include/JS-load removed from the four listings; POST forms keep
  `{{ csrf_field() }}`; pinned template-contract assertions updated.
- `tpv-cliente-modales`: the client-search modal scope narrows — retained on
  `tpvmod2`/`tpvmodedita`, no longer included by the four listings; the
  `codcliente` deep-link filter is retained as read-only text.

## Approach

- **Listing contract (catalogo_core pattern).** Controllers keep rendering the
  full page; htmx fetches the same URL and selects a stable region
  (`hx-select` + `hx-swap="outerHTML"` + `hx-push-url="true"`), so no server
  fragment branch is added and progressive enhancement holds (plain `href`/
  `action` still returns the full page). Reference:
  `plugins/catalogo_core/View/ventas_articulos.html.twig`.
- **Swapped region** = order toolbar + tabs + table + pagination; the filter form
  sits above/outside it (keeps focus and never re-inits widgets).
- **Asset boot** via `Macro/Htmx.html.twig` and `Macro/Alpine.html.twig`,
  imported once per listing view (HCS-04/05, ABS-02). Boot htmx with
  `allowScriptTags: false`; register Alpine components behind a `window.__marker`
  guard and re-init swapped trees from one `htmx:after:swap` → `Alpine.initTree`.
- **Line-search fragment contract.** Keep the legacy `$this->template = 'ajax/…'`
  fragment (the response body *is* the fragment), driven by `hx-post`;
  `hx-target="#search_results"`, `hx-swap="innerHTML"`, offset via `hx-vals`.
  This is the legacy equivalent of HCS-14's "body is the response" semantics for
  a non-`HtmxCrudController`; no core fragment API is called.
- **Search security.** Replace raw concatenation in `buscar()` with `var2str`
  patterns (the current `no_html()`-as-SQL-defense path is fragile) — LSS-01/02.
- **Helper extraction.** One set of pure, DB-free helpers in
  `plugins/tpvmod/lib/` for the search predicate, the canonical list URL
  (`http_build_query`), the phone lookup (one batched
  `SELECT … FROM clientes WHERE codcliente IN (…)`) and offset pager arithmetic;
  the four modules call the same helpers.
- **`cron_job()` gate.** `tpvmod_presupuestos` runs 5 UPDATEs per load; skip when
  `isHtmxRequest()` (HCS-06) to avoid write amplification on every swap.
- **Dates.** The `date_iso` Twig filter **already exists in core**
  (`src/Core/Html.php:239`, already used by
  `themes/AdminLTE/view/partials/agentes/edit_modal.html.twig`) — no new filter is
  introduced. Native `<input type="date">` submits `Y-m-d`, which matches the
  `date` column and fixes the latent `dd-mm-yyyy` mismatch; `tpvmod.php`'s
  `strtotime(... '+30 days')` vencimiento computation reads the ISO value.
- **Carried to spec/tasks:** D5 removes only the field, button, include and JS
  load from the listings. The listing controllers keep
  `tpvmod_cliente_ajax_dispatch()` (pinned by `TpvmodTwigTemplatesTest.php:107`
  and `TpvmodOpcionalRapidoTest.php:641`, harmless when the modal is absent).

## Affected Surfaces

| Area | Impact | Description |
|------|--------|-------------|
| `view/tpvmod_presupuestos.html.twig` | Modified | htmx controls, region, phone/ciudad columns, adapted search form, native dates, picker removed |
| `view/tpvmod_facturas.html.twig` | Modified | Same; keeps Vencimiento/Comisión columns and `#modal_huecos` |
| `view/tpvmod_albaranes.html.twig` | Modified | Same |
| `view/tpvmod_pedidos.html.twig` | Modified | Same |
| `view/ajax/ventas_lineas_{presupuestos,facturas,albaranes,pedidos}.html.twig` | Modified | htmx offset pager; facturas gains the pager it lacks |
| `view/tpvmodedita.html.twig` | Modified | 1 `.datepicker` → native date |
| `controller/tpvmod_{presupuestos,facturas,albaranes,pedidos}.php` | Modified | `var2str` search, `http_build_query` URLs, phone lookup, `cron_job()` gate, date normalization, facturas offset parity |
| `controller/tpvmod.php` | Modified | Renders `tpvmodedita`; ISO date normalization for the vencimiento computation |
| `lib/tpvmod_*` (new pure helpers) | New | Search predicate, canonical URL builder, phone map, pager arithmetic |
| `tests/TpvmodTwigTemplatesTest.php` | Modified | Drop the 4 listings from the `tpvmod-b-buscar-cliente` list; add htmx/native-date contract assertions |
| `tests/` (new helper test) | New | DB-free coverage for the new `lib/` helpers (strict TDD) |
| `openspec/specs/{listados-htmx,views,tpv-cliente-modales}/` | Modified/New | Plugin-local delta specs (via sdd-spec) |

## Risks

| # | Risk | Severity | Mitigation |
|---|------|----------|------------|
| R1 | Raw SQL concatenation in `buscar()` while extending fields | High | Build patterns with `var2str` / `escape_string` (LSS-01/02) |
| R2 | `cron_job()` (5 UPDATEs) on every htmx fragment request | High | Skip when `isHtmxRequest()` |
| R3 | Unencoded `paginas()` URLs fed to `hx-get`/`hx-push-url` | Medium | Single canonical URL builder on `http_build_query` |
| R4 | Flash messages invisible after GET swaps | Medium | Alerts block inside the swapped region, or accept no messages on read-only GETs |
| R5 | Alpine double-registration / broken init after swaps | Medium | `window.__marker` guard + one `htmx:after:swap` → `Alpine.initTree` |
| R6 | Tabs/order active state stale | Medium | Region boundaries (controls inside the swapped region) |
| R7 | jQuery UI datepicker lost inside a swapped region | Low | Native `<input type="date">` needs no re-init (removed by D7) |
| R8 | Four-way clone drift (facturas extras) | Medium | Shared `lib/` helpers + per-module template assertions |
| R9 | CSP regression from replacing inline `onclick` | Low | Nonce'd macro boot + `Alpine.data()`; `'unsafe-inline'` still present |
| R10 | Existing Twig tests break on inventory/CSRF/marker assertions | Low | Update only where the change legitimately moves markup |

## Open Questions

None — all seven decisions are confirmed in `decisions-pending.md`; the dispatch
disposition and the latent date bug are carried to spec/tasks, not open.

## Review Workload Forecast

| Bucket | Files | Est. changed lines |
|--------|-------|--------------------|
| Listing templates (4) | 4 | 400–520 |
| Line-search fragments (4) | 4 | 120–180 |
| Controllers (4 + `tpvmod.php`) | 5 | 200–280 |
| `tpvmodedita.html.twig` | 1 | ~5 |
| New `lib/` helpers | 2–3 | 120–180 |
| Tests (update 1 + new 1–2) | 2–3 | 200–280 |
| **Total (code + tests)** | **~18–20** | **~1,050–1,450** |

Estimate **exceeds the 800-line review budget** → chained PRs recommended:
1. Shared helpers + controller hardening (`var2str`, URL builder, phone lookup,
   `cron_job()` gate) + helper tests.
2. htmx/Alpine on the 4 listings + line-search fragments + template-contract tests.
3. Phone/ciudad columns, filter-form reorder, client-picker removal, native dates
   + remaining assertions. Spec/tasks/verify artifacts are prose, not PR budget.

## Rollback Plan

Revert the change commit(s) on the nested `plugins/tpvmod` repo (`master`) — the
change is confined to `plugins/tpvmod/` with no core edits and no schema
migration, so no DB rollback is required. Alternatively restore the previous
plugin tag.

## Dependencies

- htmx 4 asset (`view/js/htmx.min.js`, HCS-01) and Alpine CSP asset
  (`view/js/alpine-csp.min.js`, ABS-01) already vendored in core.
- The `date_iso` Twig filter already in core (`src/Core/Html.php:239`).
- **No new Composer/npm dependency → no `vendor/` change and no dependency-commit
  step.**

## Spec Traceability

| Spec / ID | How respected |
|-----------|---------------|
| HCS-04 | htmx imports only via `Macro/Htmx.html.twig`, once per listing view; no global load |
| HCS-05 | Macro emits the nonce'd script + inherited `hx-headers` `X-CSRF-TOKEN` |
| HCS-06 | `isHtmxRequest()` gates `cron_job()` and any server-side htmx branch |
| HCS-07 | Line-search `hx-post` validates through `validateCsrf()` via `X-CSRF-TOKEN`; no new token mechanism |
| HCS-08 | Listing GET swaps need no CSRF token |
| HCS-14 | Legacy `$this->template='ajax/…'` fragment keeps "body is the response" semantics; core fragment API untouched |
| HCS-15 | Not emitted by the legacy fragment path; `HtmxCrudController` and the core fragment machinery are unmodified, so HCS-15 stays satisfied |
| ABS-02 | Alpine imports only via `Macro/Alpine.html.twig`, once per listing view; `header.html.twig`/`footer.html.twig` untouched |
| LSS-01 / LSS-02 | Search patterns built with `var2str`/`escape_string`; no `htmlspecialchars` for SQL |

## Success Criteria

- [ ] Tabs, order, pagination and all filters update via htmx with a correct,
      shareable URL, and the full page still renders on direct load / JS disabled.
- [ ] Each row shows the customer phone (`telefono1` → `telefono2` fallback) and
      the document's billing city when present.
- [ ] The search matches company name + phone + `codigo`/`numero2`/`observaciones`,
      with `var2str`-escaped patterns and no `cifnif`; no schema change.
- [ ] The four listings contain no client picker/include/JS-load; `&codcliente=`
      still filters and renders as read-only text.
- [ ] The line search runs on htmx with server-side offset paging; facturas has
      pager parity.
- [ ] Zero `.datepicker` inputs remain; dates use native inputs + `date_iso`, and
      the range filter works.
- [ ] `cron_job()` is skipped on htmx requests; list URLs are `http_build_query`-built.
- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` and
      `ddev exec composer phpstan` pass.

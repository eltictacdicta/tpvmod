# Delta Spec: `listados-htmx` — tpvmod-listados-htmx

> **New capability.** Post-archive source of truth:
> `plugins/tpvmod/openspec/specs/listados-htmx/spec.md`.
> Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Plugin-local SDD: the core `openspec/` is intentionally NOT touched.
>
> Scope: the four tpvmod document listings — `tpvmod_presupuestos`,
> `tpvmod_facturas`, `tpvmod_albaranes`, `tpvmod_pedidos` — plus their four line
> search fragments. The capability covers the htmx 4 + Alpine.js 3 (CSP) contract,
> row enrichment (phone, billing city), the adapted multi-field listing search,
> native date filters, and the htmx line-search fragment with server-side offset
> pagination.
>
> Traceability: `proposal.md` §Capabilities / §Approach / §Success criteria;
> `exploration.md` F1–F10; `decisions-pending.md` D1–D7. Core contracts
> referenced: `htmx-core-support` (HCS-04/05/06/07/08/12/14), `list-search-security`
> (LSS-01/02), `catalog-page-views` (CPV-06/CPV-08).

## ADDED Requirements

### Requirement: LHT-01 — Full-page render with client-side region selection

The four listing controllers MUST keep rendering the complete page through the
normal `index.php` pipeline for every listing URL, regardless of the request
source. Each interactive listing control MUST issue `hx-get` against the
listing's own URL with:

- `hx-target` and `hx-select` both set to the module's stable region selector,
- `hx-swap="outerHTML"`,
- `hx-push-url="true"`.

The four stable region selectors MUST be `#tpvmod-presupuestos-region`,
`#tpvmod-facturas-region`, `#tpvmod-albaranes-region` and
`#tpvmod-pedidos-region`. No server-side fragment branch (`isHtmxRequest()`) MAY
be added for the read-only listing updates. A plain `href`/`action` navigation to
the same URL MUST return the same full page, so the listings degrade to normal
navigation with JavaScript disabled (HCS-08, HCS-14 legacy-equivalent).

Region swaps MUST preserve the delegated client-side behaviors the listing rows
and chrome depend on, without re-binding them. After an htmx swap replaces a
region: the delegated `tr.clickableRow[href]` handler (`view/js/base.js:72`)
MUST still navigate on row click, and the Bootstrap 3 `data-toggle` behaviors
(the order dropdown and the modals) MUST still open. The `tpvmod2`/`tpvmodedita`
jQuery client-selection modal is untouched by this change (TCP-06; its
preservation on those screens is owned by the `tpv-cliente-modales` delta).

#### Scenario: Direct navigation renders the full page

- **GIVEN** an authenticated agent opens a listing URL directly in the browser
- **WHEN** the page renders
- **THEN** the response is the complete page (header, filter form, region, modals, footer)
- **AND** no request-time `isHtmxRequest()` branch changes the response for a non-htmx GET

#### Scenario: htmx GET swaps only the region and pushes the URL

- **GIVEN** the listing page is loaded with htmx booted
- **WHEN** the agent activates a mapped control (tab, order option, pagination or filter)
- **THEN** the browser issues an `hx-get` to the listing URL
- **AND** only the module's `#tpvmod-<tipo>-region` element is replaced via `outerHTML`
- **AND** the address bar is updated with the resulting query string (`hx-push-url="true"`)

#### Scenario: Direct reload of a pushed URL reproduces the same view

- **GIVEN** the agent triggered a swap that pushed a URL with filters
- **WHEN** the pushed URL is reloaded as a full navigation
- **THEN** the full page renders with the same filter state and the same result rows

#### Scenario: JavaScript-disabled navigation works

- **GIVEN** JavaScript is disabled in the browser
- **WHEN** the agent clicks a tab, an order option, a pagination link or submits the filter form
- **THEN** the native `href`/`action` navigation returns the full page with the requested state

#### Scenario: Delegated jQuery and Bootstrap 3 behaviors survive a region swap

- **GIVEN** a listing page with htmx booted, containing a `tr.clickableRow[href]` row, a `data-toggle="dropdown"` order control and a `data-toggle="modal"` control inside the region
- **WHEN** an htmx swap replaces the region
- **THEN** clicking a row still navigates to its `href` (the document-level delegated handler at `view/js/base.js:72` still fires)
- **AND** the Bootstrap 3 `data-toggle` dropdown and modal controls still open
- **AND** the swap does not re-bind or duplicate those delegated handlers
- **AND** the `tpvmod2`/`tpvmodedita` client-selection modal and `view/js/tpvmod-cliente.js` remain untouched by this change

#### Scenario: Listing GET swaps require no CSRF token

- **GIVEN** an htmx GET issued by a listing control
- **WHEN** the controller runs
- **THEN** no CSRF validation is required for the read-only listing response (HCS-08)

### Requirement: LHT-02 — Opt-in htmx and Alpine boot over a stable swapped tree

Each of the four listing views MUST import `Macro/Htmx.html.twig` and
`Macro/Alpine.html.twig` exactly once, and MUST boot htmx through
`htmx.boot({'allowScriptTags': false})` so the macro emits the nonce'd asset
plus the `htmx:before:swap` scrubber (HCS-04/05/12, ABS-02). Alpine components
MUST be registered behind a `window.__marker` guard and re-initialized from a
single `htmx:after:swap` listener that calls `Alpine.initTree(target)` for the
swapped region. `header.html.twig` and `footer.html.twig` MUST NOT reference
htmx or Alpine.

#### Scenario: One opt-in import per listing view

- **GIVEN** each of the four listing templates
- **WHEN** the template body is parsed
- **THEN** it imports `Macro/Htmx.html.twig` and `Macro/Alpine.html.twig` exactly once
- **AND** the htmx boot is called with `{'allowScriptTags': false}`

#### Scenario: Global header and footer stay free of the assets

- **GIVEN** `themes/AdminLTE/view/header.html.twig` and `footer.html.twig`
- **WHEN** they are inspected after the change
- **THEN** neither references htmx or Alpine

#### Scenario: Swapped tree re-initializes Alpine exactly once per swap

- **GIVEN** a listing page with a registered Alpine component inside the region
- **WHEN** an htmx swap replaces the region
- **THEN** one `htmx:after:swap` handler runs `Alpine.initTree(target)`
- **AND** no duplicate component registration occurs (the `window.__marker` guard holds)

### Requirement: LHT-03 — Swapped region boundaries and filter-form placement

The swapped region of each listing MUST wrap, in document order: the order
toolbar (which hosts the order dropdown), the tabs, the results table and the
pagination. The filter form MUST sit above the tabs and outside the region, so a
swap never replaces it (D4). Consequence: the active tab (`class="active"`), the
order checkmark and the pagination active state MUST be correct after every swap
without extra JavaScript, and the filter inputs MUST keep their focus and values.

#### Scenario: Active tab and order state are correct after a swap

- **GIVEN** the agent switches from the `todo` tab to `pendientes` via htmx
- **WHEN** the region is swapped
- **THEN** the `pendientes` tab carries the active state
- **AND** the `todo` tab no longer does

#### Scenario: Filter inputs are not replaced by a swap

- **GIVEN** the agent typed text into the `query` filter or selected a date
- **WHEN** any control triggers a region swap
- **THEN** the filter form element is not replaced
- **AND** the entered value and focus are retained

#### Scenario: Region contains the mapped controls

- **GIVEN** a listing template is parsed
- **WHEN** the `#tpvmod-<tipo>-region` element is inspected
- **THEN** it contains the order toolbar (with the order dropdown), the tabs, the results table and the pagination
- **AND** the filter form appears before the tabs and outside the region element

### Requirement: LHT-04 — Control-to-URL mapping

Every listing control MUST be mapped to a declarative htmx request that preserves
the other filters and produces the documented URL parameter. The mapping is:

| Control | Trigger | URL parameter |
|---|---|---|
| `query` (full text) | form submit and Enter key | `query=` |
| `codserie` select | `change` | `codserie=` |
| `codagente` select | `change` | `codagente=` |
| `desde` / `hasta` native date inputs | `change` | `desde=` / `hasta=` |
| tabs | click | `mostrar=` (presupuestos/pedidos: `todo`, `pendientes`, `rechazados`, `buscar`; facturas: `todo`, `sinpagar`, `buscar`; albaranes: `todo`, `pendientes`, `buscar`) |
| order dropdown | click | `order=` (presupuestos/albaranes/pedidos: `fecha_desc`, `fecha_asc`, `codigo_desc`, `codigo_asc`; facturas: `fecha_desc`, `fecha_asc`, `vencimiento_desc`, `vencimiento_asc`) |
| pagination | click | `offset=` |
| Rechazar | submit | none (mutating POST) |

Each non-mutating control MUST use `hx-push-url="true"` and MUST NOT drop the
other active filters from the request.

#### Scenario: Filter selects and dates update the listing

- **GIVEN** the listing is loaded
- **WHEN** the agent changes `codserie`, `codagente`, `desde` or `hasta`
- **THEN** an htmx GET fires on `change`
- **AND** the region is swapped and the URL carries the changed parameter
- **AND** the other active filters are preserved

#### Scenario: Tabs, order and pagination update the listing

- **GIVEN** the listing is loaded
- **WHEN** the agent clicks a tab, an order option or a pagination link
- **THEN** the region is swapped
- **AND** the URL carries `mostrar=`, `order=` or `offset=` respectively
- **AND** the previous filters are preserved

#### Scenario: Full-text submit updates the listing

- **GIVEN** the agent typed a search term in `query`
- **WHEN** the filter form is submitted or Enter is pressed
- **THEN** an htmx GET fires with `query=` in the URL
- **AND** the region is swapped with the matching rows

#### Scenario: Rechazar remains a CSRF-protected POST

- **GIVEN** the agent confirms the Rechazar modal
- **WHEN** the request is sent
- **THEN** it is a POST carrying a valid CSRF token
- **AND** it is not converted into a read-only GET swap

### Requirement: LHT-05 — Adapted multi-field full-text listing search

The listing `buscar()` predicate MUST match, in addition to the current fields,
the live customer name and phone. The matched field set MUST be: document
`codigo`, `numero2`, `observaciones`; live `clientes.nombre`, `clientes.razonsocial`,
`clientes.telefono1`, `clientes.telefono2` resolved through
`codcliente IN (SELECT codcliente FROM clientes WHERE ...)`. The predicate MUST
NOT include `cifnif`. Every pattern MUST be built with `var2str()` /
`escape_string()` (never `htmlspecialchars` for SQL), must remain portable
(`LIKE`/`ILIKE`, no `FULLTEXT`/`tsvector`), and MUST NOT require a schema change
(D3, LSS-01/02). The subquery form MUST be used so a document is never duplicated.

#### Scenario: Query matches the live company name

- **GIVEN** a document whose customer `razonsocial` contains "ACME" but whose `codigo`, `numero2` and `observaciones` do not
- **WHEN** the agent searches "acme"
- **THEN** the document is returned through the `clientes` subquery

#### Scenario: Query matches a customer phone

- **GIVEN** a customer with `telefono1` set
- **WHEN** the agent searches a fragment of that phone
- **THEN** the customer's documents are returned

#### Scenario: Query still matches document fields

- **GIVEN** a document whose `codigo` (or `numero2`, or `observaciones`) contains the term
- **WHEN** the agent searches that term
- **THEN** the document is returned by the document-field branch of the predicate

#### Scenario: Quote-bearing query is safely escaped

- **GIVEN** the agent submits a query such as `O'Brien`
- **WHEN** the predicate is built
- **THEN** the term is escaped with `var2str()`/`escape_string()` (not `htmlspecialchars`)
- **AND** the query executes without error and without injection

#### Scenario: No cifnif and no schema change

- **GIVEN** the search predicate and the document tables
- **WHEN** the predicate is inspected and the schema compared
- **THEN** `cifnif` is not part of the predicate
- **AND** no new table, column or index is added

### Requirement: LHT-06 — Phone column resolved by one batched lookup

Each result row MUST show the customer phone as `telefono1`, falling back to
`telefono2` (one value per row, D2). The value MUST be resolved with a single
batched lookup per rendered page over the page's `codcliente` set
(`SELECT codcliente, telefono1, telefono2 FROM clientes WHERE codcliente IN (...)`)
and exposed to the view through a public controller accessor. The rendering MUST
NOT issue one query per row (no N+1), and a customer with neither phone MUST
render an empty value.

#### Scenario: telefono1 is shown when present

- **GIVEN** a document whose customer has `telefono1` and `telefono2`
- **WHEN** the row renders
- **THEN** the phone cell shows `telefono1`

#### Scenario: telefono2 is used as fallback

- **GIVEN** a document whose customer has only `telefono2`
- **WHEN** the row renders
- **THEN** the phone cell shows `telefono2`

#### Scenario: No phone renders empty

- **GIVEN** a document whose customer has neither `telefono1` nor `telefono2`
- **WHEN** the row renders
- **THEN** the phone cell is empty

#### Scenario: One batched query per page, no N+1

- **GIVEN** a page with N result rows sharing M distinct customers
- **WHEN** the page renders
- **THEN** exactly one `clientes` lookup runs for the page's `codcliente` set
- **AND** the rows read the phone from the resulting map through the controller accessor

### Requirement: LHT-07 — City column from the document billing snapshot

Each result row MUST show the document's billing city taken from the document
snapshot (`value.ciudad`, captured from the `domfacturacion` address at document
creation). The rendering MUST NOT resolve the customer's current address and MUST
NOT run an extra query (D1). When the snapshot is empty the cell MUST render
empty.

#### Scenario: Document with a city snapshot shows it

- **GIVEN** a document whose stored `ciudad` field is populated
- **WHEN** the row renders
- **THEN** the city cell shows the snapshot value

#### Scenario: Document without a city snapshot renders empty

- **GIVEN** a document created without a billing address (`ciudad` empty)
- **WHEN** the row renders
- **THEN** the city cell is empty
- **AND** no address lookup query is issued for it

### Requirement: LHT-08 — Client filter without a picker

The four listing views MUST NOT include the client picker: no `ac_cliente`
field, no `tpvmod-b-buscar-cliente` button, no
`{% include 'partials/modal_clientes.html.twig' %}` and no `tpvmod-cliente.js`
load. The `&codcliente=` URL parameter MUST still filter the listing, and the
active client MUST be rendered as read-only text in the filter area. The `[+]`
row link MUST keep filtering by that customer's `codcliente`. A control MUST
exist to clear the client filter without the modal.

#### Scenario: No picker markup or JS in the four listings

- **GIVEN** the four listing templates
- **WHEN** each is parsed
- **THEN** none contains `ac_cliente`, `tpvmod-b-buscar-cliente`, `partials/modal_clientes.html.twig` or `tpvmod-cliente.js`

#### Scenario: codcliente filters and renders as read-only text

- **GIVEN** a listing URL carrying `&codcliente=CLI001`
- **WHEN** the page renders
- **THEN** the results are limited to that customer's documents
- **AND** the active customer is shown as read-only text (not an editable input)

#### Scenario: The [+] link keeps filtering

- **GIVEN** a result row for customer `CLI001`
- **WHEN** the agent follows the row's `[+]` link
- **THEN** the listing reloads filtered by `&codcliente=CLI001`

#### Scenario: The client filter can be cleared

- **GIVEN** a listing filtered by `&codcliente=CLI001`
- **WHEN** the agent activates the clear control
- **THEN** the listing reloads without `codcliente` and shows the unfiltered results

### Requirement: LHT-09 — Native date inputs and date normalization

All ten `.datepicker` inputs (two per listing = 8, the Rechazar modal input in
`tpvmod_presupuestos` = 1, and the `fecha` input in `tpvmodedita` = 1) MUST
become native
`<input type="date">` with the `class="datepicker"` attribute removed and values
prefilled through the core `date_iso` filter. The controllers MUST normalize the
incoming native `Y-m-d` value before passing it to `var2str()`, matching the
`date` column, and the date-range filter MUST bound the results correctly. The
`tpvmodedita` date submission MUST also store the `Y-m-d` value consistently, and
the `tpvmod.php` `vencimiento` computation (`strtotime(... '+30 days')`) MUST
read the ISO value (D7).

#### Scenario: No datepicker remains in the five views

- **GIVEN** the four listing templates and `tpvmodedita.html.twig`
- **WHEN** each is grepped
- **THEN** `datepicker` does not appear in any of them
- **AND** each date input is `<input type="date">`

#### Scenario: Date filters are prefilled with the ISO value

- **GIVEN** a stored date value in the framework's `d-m-Y` format
- **WHEN** `desde` or `hasta` renders
- **THEN** its `value` is produced through the `|date_iso` filter
- **AND** the input is a native `type="date"`

#### Scenario: Incoming date is normalized before var2str

- **GIVEN** a native `Y-m-d` date submitted by the filter form
- **WHEN** the controller builds the predicate
- **THEN** the value is normalized to the canonical `Y-m-d` form used by the `date` column before `var2str()`
- **AND** an empty or invalid value yields no date predicate

#### Scenario: Date range bounds the results

- **GIVEN** documents with dates inside and outside a range
- **WHEN** the agent submits `desde` and `hasta`
- **THEN** only documents whose `fecha` falls within the inclusive range are returned

#### Scenario: tpvmodedita stores a consistent date

- **GIVEN** the agent edits a document date via the native input
- **WHEN** the document is saved
- **THEN** the stored `fecha` matches the submitted `Y-m-d` value
- **AND** the `vencimiento` computation reads that ISO value

### Requirement: LHT-10 — htmx line search with server-side offset pager

The four line-search fragments (`view/ajax/ventas_lineas_{presupuestos,facturas,albaranes,pedidos}.html.twig`)
MUST keep being served through the legacy fragment contract
(`$this->template = 'ajax/…'`, the response body is the fragment; no core
fragment API is invoked — HCS-14 legacy-equivalent). The search form MUST issue
`hx-post` to the listing URL with `hx-target="#search_results"` and
`hx-swap="innerHTML"`, and MUST debounce input (`delay`) with `hx-sync` set to
discard stale responses. The `<!--{{ fsc.buscar_lineas }}-->` marker and the
`mas_resultados()` JavaScript offset arithmetic MUST be removed. Pagination MUST
stay server-side: the fragment renders previous/next controls carrying the
server-computed `offset` (via `hx-post` + `hx-vals`), and the controller keeps
passing `offset` to the model. `tpvmod_facturas` MUST gain offset/pager parity
with the other three (D6). The client-scoped branch (`search_from_cliente2` by
`codcliente`) MUST be preserved. htmx POSTs MUST validate CSRF through
`X-CSRF-TOKEN`, with the form retaining `{{ csrf_field() }}` for the no-JS
fallback (HCS-07).

The fragment swaps into a region-level container, not into a table row: the
target is the listing's `<div id="search_results" class="table-responsive">`
(verified in `view/tpvmod_presupuestos.html.twig:399`), so TCP-10's
well-formed-error-rows-inside-`<tr>` concern has no surface here and is out of
scope. Append-style load-more is likewise NOT part of this contract: TCP-03's
`hx-swap="beforeend"` is not used; the pager replaces the `#search_results`
inner HTML with a server-computed offset (replace semantics, not append).

#### Scenario: Debounced typing swaps the results

- **GIVEN** the line-search modal is open
- **WHEN** the agent types in the `buscar_lineas` input
- **THEN** an `hx-post` fires after the configured debounce delay
- **AND** the response replaces `#search_results` inner HTML
- **AND** stale responses do not overwrite newer ones (`hx-sync`)

#### Scenario: Marker and JS offset arithmetic are gone

- **GIVEN** the four fragment templates and the four listing templates
- **WHEN** they are inspected
- **THEN** the `<!--{{ fsc.buscar_lineas }}-->` marker is absent from all fragments
- **AND** no `mas_resultados(` offset arithmetic remains in the listing templates

#### Scenario: Server-side offset pager works on all four modules

- **GIVEN** a search returning more than one page of lines
- **WHEN** the agent uses the pager
- **THEN** the previous/next controls post the server-computed `offset`
- **AND** the controller passes that offset to the model
- **AND** `tpvmod_facturas` renders the same pager as the other three modules

#### Scenario: Client-scoped line search is preserved

- **GIVEN** the listing is filtered by `codcliente`
- **WHEN** the agent runs the line search
- **THEN** the controller calls `search_from_cliente2` with that `codcliente`
- **AND** only that customer's document lines are returned

#### Scenario: CSRF is validated on the line-search POST

- **GIVEN** an htmx POST to the line-search form
- **WHEN** the request carries a valid `X-CSRF-TOKEN` header
- **THEN** `validateCsrf()` passes and the fragment is returned
- **AND** an invalid or missing token follows the existing POST rejection path with no data returned

### Requirement: LHT-11 — Controller hygiene: cron gate, htmx detection, encoded URLs, shared helpers

Every listing controller that invokes a model `cron_job()` MUST NOT execute it
when `isHtmxRequest()` is true (HCS-06), avoiding five UPDATE statements per
swap. In the current code that set is `tpvmod_presupuestos` (`controller/tpvmod_presupuestos.php:191`)
and `tpvmod_pedidos` (`controller/tpvmod_pedidos.php:187`); `tpvmod_facturas`
and `tpvmod_albaranes` do not invoke it and therefore have nothing to gate. htmx
detection MUST always delegate to the core `fs_controller::isHtmxRequest()`: the
plugin MUST NOT introduce, keep or delegate to a local HX-detection helper such
as `is_htmx_request()` or `tpvmod_is_htmx_request()` (TCP-08). All
listing and pagination URLs MUST be built with `http_build_query` (no raw string
concatenation), and the same builder MUST feed both the rendered `href` and the
`hx-get`/`hx-push-url` values. The four modules MUST delegate to a single set of
pure, database-free helpers under `plugins/tpvmod/lib/` for at least: the search
predicate, the canonical listing URL, the phone map, the offset pager arithmetic
and the date normalization, so the four near-clone implementations cannot drift.

#### Scenario: cron_job is skipped on htmx requests

- **GIVEN** a listing controller that invokes a model `cron_job()` (`tpvmod_presupuestos` or `tpvmod_pedidos`) receives an htmx request
- **WHEN** `private_core()` runs
- **THEN** `cron_job()` is not executed
- **AND** a full non-htmx page load still executes it

#### Scenario: No local HX-detection helper exists

- **GIVEN** the plugin source
- **WHEN** it is grepped for `is_htmx_request` or `tpvmod_is_htmx_request`
- **THEN** no such symbol is defined or called
- **AND** the htmx gate reads `fs_controller::isHtmxRequest()`

#### Scenario: URLs are encoded through http_build_query

- **GIVEN** filter values containing characters such as `&`, `#` or spaces
- **WHEN** a listing or pagination URL is built
- **THEN** the value is encoded by `http_build_query`
- **AND** the same encoded URL is used for the `href` and for the htmx attributes

#### Scenario: The four modules share the helpers

- **GIVEN** the four listing controllers
- **WHEN** the predicate, URL, phone-map, pager and date-normalization logic is inspected
- **THEN** each delegates to the shared `lib/` helpers
- **AND** no module carries an independent copy of that logic

### Requirement: LHT-12 — Verification contract for the new helpers and template contract

The change MUST ship database-free unit tests for every new pure helper (search
predicate, canonical URL, phone map, pager arithmetic, date normalization) under
the plugin's PHPUnit suite, and template-contract assertions for the htmx and
native-date markup of the four listings. The plugin's `strict_tdd` setting makes
tests-with-code mandatory. The existing controller dispatch assertion
(`tpvmod_cliente_ajax_dispatch` in the listing controllers) MUST remain satisfied.

#### Scenario: Helper tests run without a database

- **GIVEN** the plugin test suite runs with `plugins/tpvmod/phpunit.xml`
- **WHEN** the new helper tests execute
- **THEN** they pass without a database connection
- **AND** they cover the predicate, URL, phone map, pager and date-normalization helpers

#### Scenario: htmx and native-date template assertions exist

- **GIVEN** the four listing templates
- **WHEN** the template-contract test runs
- **THEN** it asserts `hx-get`, `hx-select`, `hx-target`, `hx-swap="outerHTML"` and `hx-push-url` on the mapped controls
- **AND** it asserts the stable region id and the absence of `datepicker`

#### Scenario: Dispatch assertion stays green

- **GIVEN** the existing assertion that the listing controllers keep `tpvmod_cliente_ajax_dispatch`
- **WHEN** the suite runs after the change
- **THEN** the assertion still passes (the dispatch call is retained, harmless when the modal is absent)

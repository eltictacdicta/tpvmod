# Tasks: tpvmod-listados-htmx

> **Plugin-local SDD.** Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` is intentionally NOT touched.
>
> **Sources (do not re-derive):** `proposal.md` (incl. `## Review Workload
> Forecast`), `design.md` (AD-1..AD-14, §1 helper signatures, §3 control→`hx-*`
> table, §5 line search, §6 dates, §8 client dispatch, `## Verification map`,
> `## File Changes`, `## Rollback and implementation order`), the three delta
> specs (`listados-htmx` LHT-01..LHT-13, `views`, `tpv-cliente-modales`),
> `exploration.md` (F1–F10) and `decisions-pending.md` (D1–D7, confirmed).
>
> **Status:** PR1 (U1–U10) applied on branch `feat/tpvmod-listados-htmx-pr1`;
> see `apply-progress.md`. PR2 lives on `feat/tpvmod-listados-htmx-pr2`:
> **U11–U15 are applied** (`61bfae1`, `7843685`, `37ac3ee`, `66b6cb1`).
> PR3 lives on `feat/tpvmod-listados-htmx-pr3`: **U16–U18 applied**
> (`c0fdc08`, `f0cb1a7`, `47c24dc`) and **U19–U20 applied** (`4f5cedb` + the
> U19–U20 docs commit). The PR3 checkboxes below are reconciled in
> `apply-progress.md` → "PR3 batch". The U10/PR2/PR3 authenticated browser
> smoke stays pending for `sdd-verify`. **Post-verify amendment:** that smoke
> found the filter bar hidden on every non-`buscar` state; **U21 (LHT-13)** was
> added to remove the inherited guard. **U21 is applied** (see
> `apply-progress.md` → "PR3 follow-up — U21"); the filter bar now renders in
> every listing state.

## Execution rules (mandatory)

- **`strict_tdd: true`** (`plugins/tpvmod/openspec/config.yaml`). Every
  production change has a RED step (a test that fails first) → GREEN → REFACTOR.
  Tests land in the same work unit as the code they verify.
- **Test files:** `plugins/tpvmod/tests/TpvmodListadosHelpersTest.php` (new,
  namespace `Tests\Tpvmod`, DB-free) for `lib/` helpers; **and**
  `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (extended, DB-free
  source/structural assertions) for the template contract.
- **Verification runner:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.
- **Static analysis:** `ddev exec composer phpstan`.
- **Delivery:** `auto-chain`; the forecast exceeds the **800-line review budget**
  → **3 chained PRs**, in the order fixed by `design.md` `## Rollback and
  implementation order` (PR1 → PR2 → PR3). Each PR must be independently green.

### Prohibitions (a task that violates any of these is wrong)

- **PROHIBITED — `hx-params`.** The vendored htmx 4 (`view/js/htmx.min.js`) has
  zero occurrences; it does not exist. Control state travels **in the `hx-get`
  URL**, and htmx appends the triggering element's own value. **No
  `hx-include`** either (duplicate `query` keys).
- **PROHIBITED — `no_html()` / `htmlspecialchars()` in the SQL predicate.**
  Delete the `$query = $this->agente->no_html(strtolower($this->query));` line in
  all four `buscar()` (`controller/tpvmod_presupuestos.php:423`,
  `tpvmod_facturas.php:373`, `tpvmod_albaranes.php:403`, `tpvmod_pedidos.php:420`).
  The search term goes through **`tpvmod_search_term()`** (which reverses the
  framework's input HTML-escape) and every literal through **`var2str()`**
  (LSS-01/LSS-02, AD-6).
- **PROHIBITED — claiming the date range was broken.** `fs_db2::var2str()`
  normalizes `d-m-Y` via `parseDateValue()` (`base/fs_db2.php:189-213`). The
  motivation for native inputs is the **jQuery UI `.datepicker` dependency**
  (`plugins/legacy_support/view/js/legacy-init.js:26-33`) and its re-init cost
  after swaps — **not** a functional bug.
- **PROHIBITED — a local HX-detection helper.** No `is_htmx_request()` /
  `tpvmod_is_htmx_request()` may be defined or called; detection always delegates
  to **`fs_controller::isHtmxRequest()`** (TCP-08, LHT-11).
- **PROHIBITED — deleting the client modal.** `view/partials/modal_clientes.html.twig`
  and `view/js/tpvmod-cliente.js` stay; only the four listings stop including them.

### Cross-PR assertion splits (read before slicing)

The `design.md` verification map names some T assertions that span more than one
PR. To keep every chained PR independently green, each composite assertion is
introduced in the earliest PR where it can pass and extended later:

| Design assertion | PR1 part | PR2 part | PR3 part |
|---|---|---|---|
| LHT-06 T `testListingsRenderPhoneColumn` | controller batched `FROM clientes` lookup + accessor (`testListingControllersUseBatchedPhoneLookup`) | — | template `fsc.telefono_cliente(value.codcliente)` (`testListingsRenderPhoneColumn`) |
| LHT-09 T `testNoDatepickerAndNativeDates` | controller `tpvmod_normalize_date(` wiring (`testListingControllersUseSharedSearchHelper`) | — | view `type="date"` + `\|date_iso`, no `datepicker` (`testNoDatepickerAndNativeDates`) |
| LHT-03 T `testRegionBoundaryAndOrder` | — | region content + controls inside, form outside region (`testRegionContainsMappedControls`) | final byte order form `<` region (`testRegionBoundaryAndOrder`) |
| LHT-11 T `testListingControllersGateCronAndBuildUrls` | cron gate + `list_url(` + no raw `"&query="` concat | — | — |

**Intermediate PR2 layout note.** PR2 introduces the region and MUST move the
filter form outside it (otherwise a swap replaces the form and LHT-03 fails). In
PR2 the form sits **outside and below** the region; the D4 reorder to **above the
tabs** (LHT-03 final / `views` delta) is the PR3 reorder unit (U15).

**`clean_cliente()` coupling note.** Design §5.2 lists `clean_cliente()` among the
deleted inline JS. It is bound only to the picker clear button
(`view/tpvmod_presupuestos.html.twig:250`), which PR3 removes. Deleting it in PR2
would break a still-present control. Refinement: **PR2 deletes `buscar_lineas()`,
`mas_resultados()` and the `#b_buscar_lineas`/`#f_buscar_lineas` jQuery bindings;
PR3 deletes `clean_cliente()` together with the picker clear control.** This keeps
each PR green and is recorded as an accepted deviation in `verify-report.md`.

## Review Workload Forecast

Source: `proposal.md` `## Review Workload Forecast`, re-sliced to the 3 chained
PRs. Review budget = **800 changed lines** (`additions + deletions`).

| PR | Files | Est. changed lines | Within budget |
|---|---|---|---|
| **PR1** — shared helpers + controller hardening | 10 (2 new, 8 modified) | ~450–600 | yes |
| **PR2** — htmx/Alpine on the 4 listings + fragments | 9 (4 templates, 4 fragments, 1 test) | ~450–650 | yes |
| **PR3** — columns, reorder, picker removal, dates | 6 (5 templates, 1 test) | ~230–360 | yes |
| **Total (code + tests)** | ~18–20 | **~1,050–1,450** | no → **chained PRs required** |

Per-unit forecasts are in each unit's `Est.` row. Line counts are estimates, not
a code-golf target: never shrink a diff by deleting tests, docs, comments or
blank lines to fit the budget (`work-unit-commits` / `chained-pr`).

## Chained PR plan

| PR | Scope | Verify | Rollback boundary |
|---|---|---|---|
| **1 — helpers + controller hardening** | New `lib/tpvmod_listados.php` + `TpvmodListadosHelpersTest`; 4 controllers: `require_once`, predicate via `tpvmod_search_term` + `var2str`, `list_params()`/`list_url()`/`list_total()`/`paginas()` façade, phone map + accessor, `tpvmod_normalize_date()`, `cron_job()` gate (presupuestos **and** pedidos); facturas line-search offset | plugin suite + `phpstan` | revert `lib/tpvmod_listados.php`, `tests/TpvmodListadosHelpersTest.php` + the controller edits; UI/URL/predicate output is behavior-preserving |
| **2 — htmx/Alpine + fragments** | 4 templates: macro imports + boot, region + top-bar split, control `hx-*` per design §3.2, filter form GET, line-search `hx-post`, Alpine registration + single swap listener, legacy line-search JS deleted; 4 fragments: marker removed, server-side offset pager, well-formed alerts, facturas pager; extend `TpvmodTwigTemplatesTest` | plugin suite + per-module smoke | revert the 4 listing templates + 4 fragments + the test additions; controllers untouched |
| **3 — columns, reorder, picker, dates** | 4 templates: phone + ciudad columns, filter form above tabs, picker → read-only text + clear, native dates; `tpvmodedita` native date; narrow `testViewsNoLongerUseClienteAutocomplete` + picker/date assertions | plugin suite + full smoke + `phpstan` | revert the 4 listing templates + `tpvmodedita.html.twig` + the test narrowing/additions; columns/dates are presentation-only |

Dependency diagram (each PR body carries it, marking itself with `📍`):

```
main
 └─▶ PR1 helpers + controller hardening
      └─▶ PR2 htmx/Alpine + fragments   ← depends on PR1 helpers
           └─▶ PR3 columns/reorder/picker/dates   ← depends on PR2 region
```

PR1 is behavior-preserving at the UI level; PR2 introduces transport; PR3 is
presentational. PR2's smoke runs before the PR3 layout churn.

---

# PR 1 — Shared helpers + controller hardening

> Behavior-preserving at the UI level: URLs, predicates and pager arithmetic stay
> identical (plus new accessors). No template is touched. No new Composer/npm
> dependency → **no `vendor/` commit step**.

### U1 — Search term + predicate helpers (LHT-05)

| Campo | Valor |
|---|---|
| **Objetivo** | Pin `tpvmod_search_term()` (HTML-decode the framework escape → `strtolower` → `trim`; `''` stays `''`) and `tpvmod_build_search_predicate()` (document `codigo`/`numero2`/`observaciones` + `clientes` subquery on `nombre`/`razonsocial`/`telefono1`/`telefono2`; `''` when term is empty; no `cifnif`). |
| **Archivos** | create `plugins/tpvmod/tests/TpvmodListadosHelpersTest.php`; create `plugins/tpvmod/lib/tpvmod_listados.php` |
| **Dependencias** | — |
| **Cubre** | LHT-05 (H); controller half in U6 |
| **Est.** | 2 files · ~70 prod + ~90 test |

**TDD — RED first**
- [x] RED — write `testSearchTermDecodesFrameworkEscape` (`O&#039;Brien` → `o'brien`), `testSearchPredicateMatchesDocumentAndClientFields` (emits `lower(codigo)/lower(numero2)/lower(observaciones)` + `codcliente IN (SELECT codcliente FROM clientes …)` on the 4 live columns), `testSearchPredicateEscapesQuotes` (with escaper `fn($v) => "'".str_replace("'","''",$v)."'"`, `O'Brien` emits an escaped literal; **no** `&#39;`/`&lt;`/`&gt;`/`&amp;`; `<script>` appears raw inside the quoted literal), `testSearchPredicateHasNoCifnif`, `testSearchPredicateEmptyTerm` (`''` ⇒ `''`). Run → fail (functions missing). |
- [x] GREEN — create `lib/tpvmod_listados.php` (`declare(strict_types=1)`, no namespace, no DB/Twig refs) with the two functions per design §1.3/§1.4. The `is_numeric()` branch is **not** re-added. |
- [x] REFACTOR — extract the test escaper factory; keep signatures byte-identical to `design.md` §1. |

**Verificación:** emitted SQL is asserted directly for a quote-bearing term; empty term emits no predicate; `cifnif` absent.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest`

### U2 — Canonical list URL + order token helpers (LHT-04, LHT-11)

| Campo | Valor |
|---|---|
| **Objetivo** | Pin `tpvmod_build_list_url()` (`http_build_query($params, '', '&', PHP_QUERY_RFC3986)`, drops `null`/`''`, keeps `0`/`'0'`, preserves key order, appends with `&`) and `tpvmod_order_token_for()` (SQL order → URL token; unknown → `fecha_desc`). |
| **Archivos** | `plugins/tpvmod/lib/tpvmod_listados.php` (extend); `plugins/tpvmod/tests/TpvmodListadosHelpersTest.php` (extend) |
| **Dependencias** | U1 |
| **Cubre** | LHT-04 (H), LHT-11 (H) |
| **Est.** | 2 files · ~35 prod + ~60 test |

**TDD — RED first**
- [x] RED — `testListUrlCarriesEveryFilterAndEncodesSpecialChars` (a `query`/`codcliente` value with `&`, `#`, spaces is encoded, the other filters survive), `testBuildListUrlEncodesAndDropsEmpty`, `testBuildListUrlKeepsZero` (`0`/`'0'` are not dropped; only `null`/`''` are), `testOrderTokenFor` (`fecha_desc`↔`fecha DESC`, facturas `vencimiento_*`, unknown → `fecha_desc`). Run → fail. |
- [x] GREEN — implement both helpers. |
- [x] REFACTOR — assert key order explicitly. |

**Verificación:** encoded URL is the single source for both `href` and `hx-get`/`hx-push-url` (LHT-11).
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest`

### U3 — Offset pager arithmetic helper (LHT-11)

| Campo | Valor |
|---|---|
| **Objetivo** | Pin `tpvmod_pager_links()` byte-for-byte to the legacy `paginas()` loop: pages of `$limit`, first/last/middle/current ±5 kept, `[]` when ≤1 page survives, `actual` initial value `1`. |
| **Archivos** | `lib/tpvmod_listados.php` (extend); `tests/TpvmodListadosHelpersTest.php` (extend) |
| **Dependencias** | U2 |
| **Cubre** | LHT-11 (H) |
| **Est.** | 2 files · ~25 prod + ~45 test |

**TDD — RED first**
- [x] RED — `testPagerLinksBoundsAndPrunes` (first/last/middle/current ±5; URLs come from the injected `$urlForOffset` callable), `testPagerLinksEmptyWhenSinglePage`. Run → fail. |
- [x] GREEN — implement `tpvmod_pager_links()`. |
- [x] REFACTOR — no behavior change; keep the callback signature `callable(int): string`. |

**Verificación:** page list and count identical to the legacy loop; empty on a single page.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest`

### U4 — Phone helpers: fallback + batched map (LHT-06)

| Campo | Valor |
|---|---|
| **Objetivo** | Pin `tpvmod_resolve_phone()` (`telefono1` non-blank → else `telefono2` non-blank → else `''`) and `tpvmod_phone_map()` (deduplicate + drop empty codes **before** `$fetch`; empty set returns `[]` **without** calling `$fetch`; `$fetch` rows keyed by `codcliente`; returns `codcliente => phone`). |
| **Archivos** | `lib/tpvmod_listados.php` (extend); `tests/TpvmodListadosHelpersTest.php` (extend) |
| **Dependencias** | U1 |
| **Cubre** | LHT-06 (H) |
| **Est.** | 2 files · ~30 prod + ~60 test |

**TDD — RED first**
- [x] RED — `testPhoneMapPrefersTelefono1`, `testPhoneMapFallsBackToTelefono2`, `testPhoneMapEmptyPhones`, `testPhoneMapSkipsFetchForEmptySet` (assert the injected fetcher is **never** called on an all-empty/all-duplicate set — covers the "no N+1 / zero query" clause). Run → fail. |
- [x] GREEN — implement both helpers with the injected `callable(list<string>): array`. |
- [x] REFACTOR — assert the dedupe ordering. |

**Verificación:** exactly one fetch call for a page set; one value per row; blank when neither phone is set.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest`

### U5 — Date normalization helper (LHT-09)

| Campo | Valor |
|---|---|
| **Objetivo** | Pin `tpvmod_normalize_date()`: accepts ISO `Y-m-d` and formatted `d-m-Y` validated with `checkdate()`; returns `''` otherwise. |
| **Archivos** | `lib/tpvmod_listados.php` (extend); `tests/TpvmodListadosHelpersTest.php` (extend) |
| **Dependencias** | U1 |
| **Cubre** | LHT-09 (H) |
| **Est.** | 2 files · ~20 prod + ~40 test |

**TDD — RED first**
- [x] RED — `testNormalizeDateAcceptsIsoAndDmY`, `testNormalizeDateRejectsEmptyAndInvalid` (`''`, garbage, `99-99-9999`), `testNormalizeDateParityWithHtmlFilter` (`FE::date_iso`/`dateIsoValue()` parity for the same stored `d-m-Y` value, no new Twig filter). Run → fail. |
- [x] GREEN — implement `tpvmod_normalize_date()`. |
- [x] REFACTOR — do **not** add any `var2str`-only path; the helper returns the canonical `Y-m-d`. |

**Verificación:** invalid input yields `''` (⇒ no date predicate); ISO output matches the `date` column.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest`

### U6 — Wire search / URL / date helpers into the four listing controllers (LHT-05, LHT-11, LHT-09)

| Campo | Valor |
|---|---|
| **Objetivo** | Each of the four controllers: `require_once dirname(__DIR__) . '/lib/tpvmod_listados.php';`; add the `list_params()`/`list_url()` façade and the `list_total()` extraction + `paginas()` via `tpvmod_pager_links()`; replace the text block of `buscar()` with `tpvmod_search_term()` + `tpvmod_build_search_predicate()` and **delete `no_html()`**; normalize `desde`/`hasta` with `tpvmod_normalize_date()`. |
| **Archivos** | `controller/tpvmod_presupuestos.php`, `controller/tpvmod_facturas.php`, `controller/tpvmod_albaranes.php`, `controller/tpvmod_pedidos.php`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U1, U2, U3, U5 |
| **Cubre** | LHT-05 (T `testListingControllersUseSharedSearchHelper`), LHT-11 (T URL half of `testListingControllersGateCronAndBuildUrls`), LHT-09 (controller half) |
| **Est.** | 5 files · ~90–130 prod + ~40 test |

**TDD — RED first**
- [x] RED — add `testListingControllersUseSharedSearchHelper`: each controller contains `tpvmod_search_term(` **and** `tpvmod_build_search_predicate(`, **no** `no_html(` inside `buscar()`, and `tpvmod_normalize_date(` for `desde`/`hasta`. Add/extend `testListingControllersGateCronAndBuildUrls` with: each controller uses `list_url(` and `paginas()` carries no raw `"&query="`/`"&mostrar="` concatenation. Extend `testControlToUrlMapping`-adjacent assertions only where they pass in PR1 (there are none — URL mapping is PR2). Run → fail. |
| [x] GREEN — implement the façade and the `buscar()` text block exactly per design §1.5/§1.6; leave `codagente`/`codcliente`/`codserie`/`desde`/`hasta`, `COUNT`, `select_limit`, `SELECT *`, `SUM(total)` and the facturas `SUM(neto*porcomision/100)` query shape **unchanged**. |
- [x] REFACTOR — keep the four `list_total()` switches module-local; only the arithmetic is shared. |

**Verificación:** the four controllers delegate to the shared search/URL/pager/date helpers; no module carries an independent copy (LHT-11 "share the helpers").
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U7 — Controller phone map + public accessor (LHT-06)

| Campo | Valor |
|---|---|
| **Objetivo** | Each controller: memoized `telefonos_pagina()` running **one batched** `SELECT codcliente, telefono1, telefono2 FROM clientes WHERE codcliente IN (…)` per rendered page, plus `public function telefono_cliente(string $codcliente): string`. |
| **Archivos** | the four `controller/tpvmod_*.php`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U4, U6 |
| **Cubre** | LHT-06 (T controller half `testListingControllersUseBatchedPhoneLookup`) |
| **Est.** | 5 files · ~40–60 prod + ~20 test |

**TDD — RED first**
- [x] RED — `testListingControllersUseBatchedPhoneLookup`: each controller contains `FROM clientes` inside a single `IN (` lookup and exposes `telefono_cliente(`. Run → fail. |
| [x] GREEN — implement `telefonos_pagina()` on top of `tpvmod_phone_map()` with `fn($v) => $this->var2str($v)` for the `IN` list. |
| [x] REFACTOR — memoize per request (`$telefonos_map === null`); no query when `resultados` is empty. |

**Verificación:** one lookup per page; accessor returns `''` on a map miss.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U8 — **CRITICAL** `cron_job()` gate on htmx requests — presupuestos **AND** pedidos (LHT-11)

| Campo | Valor |
|---|---|
| **Objetivo** | Skip the model `cron_job()` (5 UPDATEs) when `$this->isHtmxRequest()` is true, in **both** controllers that invoke it: `controller/tpvmod_presupuestos.php:191` and `controller/tpvmod_pedidos.php:187`. `tpvmod_facturas` and `tpvmod_albaranes` do **not** invoke `cron_job()` and therefore get **no** gate (do not add one). Detection MUST use `fs_controller::isHtmxRequest()`; **no** local `is_htmx_request`/`tpvmod_is_htmx_request`. |
| **Archivos** | `controller/tpvmod_presupuestos.php`, `controller/tpvmod_pedidos.php`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U6 |
| **Cubre** | LHT-11 (T cron half of `testListingControllersGateCronAndBuildUrls` + `testNoLocalHtmxDetectionHelper`) |
| **Est.** | 3 files · ~10–14 prod + ~20 test |

**TDD — RED first**
- [x] RED — extend `testListingControllersGateCronAndBuildUrls` to assert, for **both** `tpvmod_presupuestos.php` and `tpvmod_pedidos.php`, that `isHtmxRequest` appears adjacent to the `cron_job()` call; assert `tpvmod_facturas.php`/`tpvmod_albaranes.php` contain no `cron_job(`. Add `testNoLocalHtmxDetectionHelper`: grep the plugin (`.php`/`.twig`) for `is_htmx_request`/`tpvmod_is_htmx_request` → absent, and the gate reads `isHtmxRequest()`. Run → fail. |
- [x] GREEN — wrap each `cron_job()` call in `if (!$this->isHtmxRequest()) { … }`. |
- [x] REFACTOR — none. |

**Verificación:** non-htmx full page load still runs `cron_job()`; htmx swap does not. **This is the highest-risk gate (R2)** — the smoke step in PR1's verify records the DB/log observation.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U9 — facturas line-search offset parity (LHT-10)

| Campo | Valor |
|---|---|
| **Objetivo** | `tpvmod_facturas::buscar_lineas()` passes `$this->offset` to both `search($term, $this->offset)` and `search_from_cliente2($codcliente, $term, $obs, $this->offset)` — parity with the other three. The client-scoped branch (`isset($_POST['codcliente'])`) is preserved verbatim. |
| **Archivos** | `controller/tpvmod_facturas.php`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U6 |
| **Cubre** | LHT-10 (controller half; fragment/listing half in PR2) |
| **Est.** | 2 files · ~8–12 prod + ~15 test |

**TDD — RED first**
- [x] RED — `testFacturasLineSearchPassesOffset`: the controller keeps `$this->template = 'ajax/ventas_lineas_facturas'` and calls both model searches with `$this->offset`. Run → fail. |
| [x] GREEN — add the offset argument to both calls. |
| [x] REFACTOR — none. |

**Verificación:** facturas has offset/pager parity; the model signatures (`linea_factura_cliente.php:390,423`) stay untouched (cross-plugin out of scope).
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U10 — PR1 gate: helpers + controllers behavior-preserving

| Campo | Valor |
|---|---|
| **Objetivo** | Prove PR1 is green and behavior-preserving before opening PR2. |
| **Archivos** | — |
| **Dependencias** | U1–U9 |
| **Cubre** | LHT-12 (T: the H suite + existing suites stay green) |
| **Est.** | 0 files |

- [x] Run the full plugin suite: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → all green, incl. `TpvmodModulesTest`, `TpvmodOpcionalRapidoTest` (dispatch ordering in `tpvmod.php` untouched) and the retained `tpvmod_cliente_ajax_dispatch`. **PR1 result: OK (167 tests, 859 assertions).**
- [x] Run `ddev exec composer phpstan` → clean. **PR1 result: no NEW errors. One error remains, pre-existing and unrelated (`tests/Core/PluginEnableAjaxSafetyTest.php:308`, present on `master` before PR1); PHPStan only analyses `src` + `tests`, not `plugins/tpvmod`.**
- [ ] Smoke (recorded in `verify-report.md`): a full non-htmx load still executes `cron_job()` (DB/log observation); pagination/filter URLs are byte-identical to the pre-change output for the same state. **Delegated to `sdd-verify`: the browser smoke needs an authenticated agent session (`config.yaml` smoke flow). PR1's runtime evidence is a DB-backed CLI harness (see `apply-progress.md`): the real `fs_controller::isHtmxRequest()` returns false without `HX-Request` (cron runs) and true with it (cron skipped); the shared URL builder now drops empty filters and adds `order`, so the rendered pagination URL is NOT byte-identical to the legacy concatenation — same state resolves to the same result set (documented deviation).**

**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml && ddev exec composer phpstan`

---

# PR 2 — htmx/Alpine on the four listings + line-search fragments

> Introduces transport only. The 4 controllers are frozen (PR1 done). The filter
> form is moved **outside and below** the region here; the D4 "above the tabs"
> reorder is PR3.

### U11 — htmx/Alpine opt-in boot + stable region scaffold (LHT-01, LHT-02)

| Campo | Valor |
|---|---|
| **Objetivo** | In each of the four listing views: import `Macro/Htmx.html.twig` and `Macro/Alpine.html.twig` **once**, call `htmx.boot({'allowScriptTags': false})`, register the `tpvmodListado` Alpine component behind `window.__tpvmodListadoRegistered`, bind **exactly one** `'htmx:after:swap'` listener (`window.__tpvmodListadoSwapBound`) calling `Alpine.initTree(evt.detail.ctx.target)`, and wrap the top-bar/order toolbar + tabs + table + pagination in `<div id="tpvmod-<tipo>-region">`. **No `Macro/HtmxCrud.html.twig`.** `header.html.twig`/`footer.html.twig` untouched. |
| **Archivos** | `view/tpvmod_{presupuestos,facturas,albaranes,pedidos}.html.twig`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | PR1 |
| **Cubre** | LHT-01 (T `testListingsDeclareStableSwapRegion`, region-id half), LHT-02 (T `testListingsImportHtmxAndAlpineOnce`) |
| **Est.** | 5 files · ~120–160 prod + ~50 test |

**TDD — RED first**
- [x] RED — `testListingsImportHtmxAndAlpineOnce`: exactly one `{% import 'Macro/Htmx.html.twig' %}`, one `{% import 'Macro/Alpine.html.twig' %}`, `htmx.boot({'allowScriptTags': false})`, `alpine.boot()`, the two `window.__tpvmodListado*` markers, exactly one `'htmx:after:swap'` binding, **no** `HtmxCrud.html.twig`; `header`/`footer` free of `htmx`/`Alpine`. `testListingsDeclareStableSwapRegion`: exactly one `id="tpvmod-<tipo>-region"` per template. Run → fail. |
- [x] GREEN — implement the macro imports, boot calls and the region wrapper for all four; **delete the inline `buscar_lineas()` and `mas_resultados()`** and the `#b_buscar_lineas`/`#f_buscar_lineas` jQuery bindings (keep `clean_cliente()` and the `query` focus for PR3). Keep modals **outside** the region. |
- [x] REFACTOR — one Alpine component name (`tpvmodListado`) for all four; no list state in Alpine. |
- [x] Verified — Twig compile harness: all 4 listings + 4 fragments `OK`; plugin suite `OK (169 tests, 907 assertions)`. **U11 committed as `61bfae1` (1370 changed lines).** |
- [x] **U12–U13 applied** (`7843685`, `37ac3ee`; 507 changed lines, within the 800 budget). **U14–U15 applied** (`66b6cb1`; 101 changed lines). See `apply-progress.md` → "PR2 batch". |

**Verificación:** one opt-in import per view; exactly one swap listener per view; marker guards present.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U12 — Control → `hx-*` mapping, filter form GET, top-bar split (LHT-01, LHT-03, LHT-04)

| Campo | Valor |
|---|---|
| **Objetivo** | Every mapped control carries `hx-target`/`hx-select` = the region, `hx-swap="outerHTML"`, `hx-push-url="true"`, and an `hx-get` = the `fsc.list_url(...)` form with its **own key omitted** (htmx appends it). Tabs/order/filters reset `offset=0`; only pagination sets a non-zero offset. `f_custom_search` → `method="get"` + hidden `mostrar`/`order`; the order dropdown moves inside the region toolbar; reload/home/Nuevo/Rechazar/extensions/`b_buscar_lineas` stay outside (AD-14). |
| **Archivos** | the four listing templates; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U11 |
| **Cubre** | LHT-04 (T `testControlToUrlMapping`), LHT-01 (T control half of `testListingsDeclareStableSwapRegion`), LHT-03 (T `testRegionContainsMappedControls`, PR2 subset) |
| **Est.** | 5 files · ~140–190 prod + ~50 test |

**TDD — RED first**
- [x] RED — `testControlToUrlMapping`: each `hx-get` equals the `fsc.list_url(...)` form with the own key omitted, correct `hx-trigger` (`submit`/`change`/click), `hx-push-url="true"`; tab and order tokens per module; **`hx-params` absent everywhere** (repo-wide guard); **no `hx-include`**. `testRegionContainsMappedControls`: the region contains order toolbar + tabs + table + `fsc.paginas()`; `f_buscar_lineas`/`modal_huecos`/`modal_rechazar` after the region. Run → fail. |
- [x] GREEN — apply the design §3.2 table control by control; keep the four `fsc.paginas()` URLs feeding both `href` and `hx-get`. |
| [x] REFACTOR — no duplicated query keys; the filter form stays outside the region (below it, per the cross-PR note). |
- [x] Verified — plugin suite `OK (171 tests, 1115 assertions)` at the U12-only commit; Twig compile harness over the 4 listings + 4 fragments `OK`. Commit `7843685`. |

**Verificación:** each control updates the URL by exactly its documented parameter and preserves the others; no `hx-params`/`hx-include`.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U13 — htmx line-search form + legacy JS/`$.ajax` removal (LHT-10, TCP-01)

| Campo | Valor |
|---|---|
| **Objetivo** | `#f_buscar_lineas` becomes `hx-post="{{ fsc.url() }}" hx-target="#search_results" hx-swap="innerHTML" hx-trigger="submit" hx-sync="this:replace"`, keeping `method="post"` + `{{ csrf_field() }}` for the no-JS fallback. The two inputs (`buscar_lineas`, `buscar_lineas_o`) get `hx-post`, `hx-trigger="keyup changed delay:300ms"`, `hx-sync="closest form:replace"`. The `<!--{{ fsc.buscar_lineas }}-->` marker and the `$.ajax`/stale-response trick are gone. |
| **Archivos** | the four listing templates |
| **Dependencias** | U11 |
| **Cubre** | LHT-10 (T listing half of `testLineSearchFragmentContract`), TCP-01 (T: no `$.ajax`/`mas_resultados(`/`buscar_lineas()` definition) |
| **Est.** | 4 files · ~80–110 prod (mostly edits) |

**TDD — RED first**
- [x] RED — `testLineSearchFragmentContract` (listing half): `hx-post="{{ fsc.url() }}"`, `hx-target="#search_results"`, `hx-swap="innerHTML"`, `hx-trigger` with `delay`, `hx-sync`; no `mas_resultados(`; no inline `$.ajax`. Run → fail. |
- [x] GREEN — rewrite the modal form; the modal opens via the existing `data-toggle="modal" data-target="#modal_buscar_lineas"`. |
| [x] REFACTOR — remove the now-dead stale-response comment; do **not** add `hx-include`/`hx-params`. |
| [x] Verified — plugin suite `OK (172 tests, 1167 assertions)`; the legacy `$.ajax`/`mas_resultados()`/`buscar_lineas()` bindings were already deleted under U11 (`61bfae1`), so U13 adds the htmx POST wiring and pins their absence, plus the missing facturas hidden `offset`. Commit `37ac3ee`. |

**Verificación:** typing debounces; the form still POSTs with the fallback token; no `$.ajax` remains.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U14 — Line-search fragments: marker removed, offset pager, well-formed alerts (LHT-10, views delta)

| Campo | Valor |
|---|---|
| **Objetivo** | In all four `view/ajax/ventas_lineas_*.html.twig`: remove line 2 `<!--{{ fsc.buscar_lineas }}-->`; change the alerts' bare `<li>` to `<div>`; replace the inline `mas_resultados('±N')` pager with the server-computed offset controls (`hx-post` + `hx-vals` `offset`, `hx-target="#search_results"`, `hx-swap="innerHTML"`, `hx-sync="closest form:replace"`); `ventas_lineas_facturas.html.twig` **gains** the pager it lacks. |
| **Archivos** | `view/ajax/ventas_lineas_{presupuestos,facturas,albaranes,pedidos}.html.twig`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U9 (facturas offset), U13 |
| **Cubre** | LHT-10 (T fragment half of `testLineSearchFragmentContract`), views delta "Nine debt-fill templates" fragment clause |
| **Est.** | 5 files · ~120–180 prod + ~40 test |

**TDD — RED first**
- [x] RED — extended `testLineSearchFragmentContract` with the fragment half: each fragment has `hx-post` + `hx-vals` `offset` (facturas included); the marker is absent from all four; alerts use `<div>` not bare `<li>`; no `mas_resultados(`/`onclick=`. Run → failed: the marker was still present in all four fragments.
- [x] GREEN — implemented the four fragments per design §5.3; the step is `fsc.lineas|length` (reproduces the legacy arithmetic exactly). Results: `--filter testLineSearchFragmentContract` → `OK (1 test, 100 assertions)`; plugin suite `OK (172 tests, 1215 assertions)`; throwaway Twig compile harness over the four fragments → `TWIG LINT OK`; throwaway render harness with a stub `fsc` → `U14 RENDER HARNESS OK` (8/8: offset 0 → next `{"offset": 8}` no previous; offset 24 → prev 16 / next 32; partial page → prev 1, no next; alerts as `<div>`; marker absent).
- [x] REFACTOR — no `beforeend` append (TCP-03 gap G1: replace semantics only); the pager replaces `#search_results` through `hx-swap="innerHTML"`.

**Verificación:** prev/next post the server-computed offset; the four fragments are well-formed and marker-free.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U15 — PR2 gate: plugin suite + per-module smoke

| Campo | Valor |
|---|---|
| **Objetivo** | Prove PR2 is green and the transport works per module before the layout churn. |
| **Archivos** | — |
| **Dependencias** | U11–U14 |
| **Cubre** | LHT-12; TCP-09 |
| **Est.** | 0 files |

- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → green. **PR2 result: `OK (172 tests, 1215 assertions)`** (fragment half added 48 assertions; no test count change).
- [x] `ddev exec composer phpstan` → clean. **PR2 result: no NEW errors. The one remaining error is pre-existing and byte-identical to the baseline (`tests/Core/PluginEnableAjaxSafetyTest.php:308`); PHPStan only analyses `src` + `tests`, never `plugins/tpvmod`.**
- [ ] Smoke per module (recorded in `verify-report.md`): tabs/order/pagination/filters swap only the region and push the URL; JS disabled returns the full page; tab switch re-inits Alpine exactly once (no duplicate-registration warning); delegated `tr.clickableRow[href]` and Bootstrap `data-toggle` still work after a swap; the line search debounces, pages prev/next and rejects an invalid token; `cron_job()` does not run on swaps (DB/log observation). **Delegated to `sdd-verify`: the browser smoke needs an authenticated agent session (`config.yaml` smoke flow). The assertable parts are covered here: the U14 render harness proves the server-computed prev/next offsets; the static audit confirms `clickableRow` (1×/listing), `data-toggle` (modals) and `fsc.paginas()` (1×/listing, inside the region) survive; `method="get"`/`method="post"` + `{{ csrf_field() }}` preserve the no-JS path (pinned by tests).**

**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml && ddev exec composer phpstan`

---

# PR 3 — Columns, filter reorder, picker removal, native dates

> Presentational. Controllers are frozen (PR1); the region/transport is frozen
> (PR2).

### U16 — Phone + billing-city columns (LHT-06, LHT-07)

| Campo | Valor |
|---|---|
| **Objetivo** | Each listing table gains a phone column rendering `fsc.telefono_cliente(value.codcliente)` (`telefono1` → `telefono2` fallback, one value per row) and a city column rendering `{{ value.ciudad }}` (document billing snapshot, no lookup). Empty ⇒ empty cell. |
| **Archivos** | the four listing templates; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U7 (accessor), U12 |
| **Cubre** | LHT-06 (T `testListingsRenderPhoneColumn`), LHT-07 (T `testListingsRenderCitySnapshot`) |
| **Est.** | 5 files · ~40–60 prod + ~40 test |

**TDD — RED first**
- [x] RED — `testListingsRenderPhoneColumn`: each template calls `fsc.telefono_cliente(value.codcliente)` (the controller batched lookup is already asserted in U7). `testListingsRenderCitySnapshot`: each template renders `{{ value.ciudad }}` and the controller has **no** `dirclientes`/`domfacturacion` lookup in the listing path. Run → fail. |
- [x] GREEN — add both `<td>` cells; facturas keeps Vencimiento/Comisión and `#modal_huecos`. |
| [x] REFACTOR — no `|raw` on either value (Twig auto-escape). |

**Verificación:** a customer with only `telefono2` shows it; with neither → blank; a document with a city snapshot shows it; with none → blank and no query.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U17 — Filter form reorder above the tabs (LHT-03, views delta)

| Campo | Valor |
|---|---|
| **Objetivo** | Move `#f_custom_search` (and the `{% if fsc.mostrar == 'buscar' %}` guard) **above** the region/tabs, so the document order is form → region (order toolbar, tabs, table, pagination) → modals. The `[+]`/`?codcliente=` auto-switch to `mostrar=buscar` stays. |
| **Archivos** | the four listing templates; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U15 |
| **Cubre** | LHT-03 (T `testRegionBoundaryAndOrder`, final byte order), views delta "Filter form precedes the listing region" |
| **Est.** | 5 files · ~60–100 prod (move diff) + ~20 test |

**TDD — RED first**
- [x] RED — `testRegionBoundaryAndOrder`: `id="f_custom_search"` byte offset **<** region offset; `nav-tabs` and `fsc.paginas()` inside the region; the order dropdown inside the region; `f_buscar_lineas`/`modal_huecos`/`modal_rechazar` after the region close. Run → fail (form still below). |
- [x] GREEN — relocate the form block above the region in all four templates. |
| [x] REFACTOR — no behavior change; the form keeps its fields, `method="get"` and the hidden `mostrar`/`order`. |

**Verificación:** active tab + order checkmark correct after a swap; filter inputs keep focus/values.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U18 — Client picker removal + read-only client + clear control (LHT-08, views + tpv-cliente-modales deltas)

| Campo | Valor |
|---|---|
| **Objetivo** | Remove from the four listings: the `ac_cliente` field, the `tpvmod-b-buscar-cliente` button, `{% include 'partials/modal_clientes.html.twig' %}` and the `tpvmod-cliente.js` load. Render the active client as **read-only text**; add a **clear** control carrying `fsc.list_url({'codcliente': '', 'mostrar': 'buscar', 'offset': 0})`; delete `clean_cliente()`. Keep `tpvmod_cliente_ajax_dispatch($this)` in the controllers (AD-8 §8c). **Do not delete** the modal partial or `view/js/tpvmod-cliente.js` (they stay for `tpvmod2`/`tpvmodedita`). |
| **Archivos** | the four listing templates; `tests/TpvmodTwigTemplatesTest.php` (narrow `testViewsNoLongerUseClienteAutocomplete`) |
| **Dependencias** | U17 |
| **Cubre** | LHT-08 (T `testListingViewsExcludeClientPicker`), views delta, tpv-cliente-modales delta, `## Client-dispatch disposition` §8(a)/(b) |
| **Est.** | 6 files · ~80–120 prod + ~30 test |

**TDD — RED first**
- [x] RED — `testListingViewsExcludeClientPicker`: none of `ac_cliente`, `tpvmod-b-buscar-cliente`, `partials/modal_clientes.html.twig`, `tpvmod-cliente.js`; read-only client text + a clear control carrying `fsc.list_url({'codcliente': ''})`. **Narrow** `testViewsNoLongerUseClienteAutocomplete` per design §8(a): split into `$pickerViews = ['tpvmod2.html.twig', 'tpvmodedita.html.twig']` (assert `tpvmod-b-buscar-cliente` **present**) and `$listingViews = [the four listings]` (assert **absent**); all six still assert `devbridgeAutocomplete` absent. Run → fail. |
| [x] GREEN — remove the picker markup/include/JS-load from the four listings; add the read-only text + clear control; delete `clean_cliente()`. |
| [x] REFACTOR — **keep `testControllersDropCsrfWorkaround` unchanged and green**: `tpvmod.php`/`tpvmod_albaranes.php`/`tpvmod_pedidos.php` still contain `tpvmod_cliente_ajax_dispatch` and no `function buscar_cliente` (no new listing controller is added to its iterated list). `TpvmodOpcionalRapidoTest.php:637-647` stays unmodified. |

**Verificación:** `&codcliente=CLI001` filters and shows read-only text; the clear control drops it; the modal survives on `tpvmod2`/`tpvmodedita`.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U19 — Native date inputs (all 10) (LHT-09, views delta)

| Campo | Valor |
|---|---|
| **Objetivo** | Replace every `.datepicker` input with native `<input type="date">` prefilled via the core `date_iso` filter: `desde`/`hasta` in the four listings (remove `class="datepicker"` and `onchange="this.form.submit()"`), the Rechazar input in `tpvmod_presupuestos` (`value="{{ 'now'|date('Y-m-d') }}"`), and the `fecha` input in `tpvmodedita.html.twig` (`value="{{ fsc.documento.fecha|date_iso }}"`). The controller side was done in U6. |
| **Archivos** | the four listing templates, `view/tpvmodedita.html.twig`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U17; U6 (controller normalization) |
| **Cubre** | LHT-09 (T `testNoDatepickerAndNativeDates`, view half), views delta "Native date inputs replace the datepicker class" |
| **Est.** | 6 files · ~40–60 prod + ~20 test |

**TDD — RED first**
- [x] RED — `testNoDatepickerAndNativeDates`: `datepicker` absent from the five views; `type="date"` + `|date_iso` on `desde`/`hasta`; the Rechazar input; the `tpvmodedita` `fecha`; `tpvmod_normalize_date(` in each controller (already in U6). Run → fail. |
- [x] GREEN — convert the 10 inputs; **no new Twig filter** (`date_iso` already exists, `src/Core/Html.php:239`). |
| [x] REFACTOR — verify the `controller/tpvmod.php` `vencimiento` read (`date("Y-m-d", strtotime($_POST['fecha'] . " +30 days"))`) needs **no logic change** (ISO input) — assert/record only. |

**Verificación:** zero `.datepicker` remains; the range filter bounds results; all 10 inputs open the native picker. **No claim that the range was broken** (see Prohibitions).
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

### U20 — PR3 gate: full suite + phpstan + full smoke

| Campo | Valor |
|---|---|
| **Objetivo** | Final acceptance for the change. |
| **Archivos** | — |
| **Dependencias** | U16–U19 |
| **Cubre** | LHT-12, TCP-09, `views` delta "All assertions pass" |
| **Est.** | 0 files |

- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → all green (incl. `TpvmodModulesTest`, `TpvmodOpcionalRapidoTest`, the narrowed `testViewsNoLongerUseClienteAutocomplete` and the unchanged `testControllersDropCsrfWorkaround`).
- [x] `ddev exec composer phpstan` → clean.
- [ ] Full manual smoke per module (recorded in `verify-report.md`): direct load = full page; pushed URL reloads identically; JS-disabled navigation; tabs/order/pagination/filters; Rechazar POST with CSRF; line search (typing, debounce, prev/next offset, client-scoped, invalid token); phone/ciudad columns; `&codcliente=` deep link + clear; the 10 native date inputs; no console errors; no duplicate queries; Alpine re-inits after swaps.
- [x] Grep audit: no `hx-params`, no `hx-include`, no `no_html(` in the four `buscar()`, no `is_htmx_request`/`tpvmod_is_htmx_request`, no `datepicker` in the five views, no `$.ajax`/`mas_resultados(` in the listings.

**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml && ddev exec composer phpstan`

---

# PR 3 follow-up — post-verify amendment

> Added after the authenticated browser smoke found the filter bar hidden on every
> non-`buscar` state. Markup-only: no controller, helper, schema or dependency
> change.

### U21 — Always-visible filter bar (LHT-13, views delta)

| Campo | Valor |
|---|---|
| **Objetivo** | Remove the `{% if fsc.mostrar == 'buscar' %}` guard that wraps the `#f_custom_search` form block in the four listing views, so the bar renders in every listing state (`todo`, the intermediate tabs, `buscar`). The form stays above the tabs and outside the region (U17); the other two `mostrar == 'buscar'` uses per template (the autofocus script guard and the tab `active` class) stay untouched. No controller or helper change. |
| **Archivos** | `view/tpvmod_{presupuestos,facturas,albaranes,pedidos}.html.twig`; extend `tests/TpvmodTwigTemplatesTest.php` |
| **Dependencias** | U17 (reorder); applied after the U20 gate |
| **Cubre** | LHT-13 (T `testFilterBarRendersInEveryListingState`), views delta "Filter form precedes the listing region" |
| **Est.** | 5 files · ~8–12 prod (guard removal) + ~30 test |

**TDD — RED first**
- [x] RED — `testFilterBarRendersInEveryListingState`: for each of the four templates assert the `f_custom_search` form block is **not** wrapped in a `{% if fsc.mostrar == 'buscar' %}` guard. The assertion is **structural** (an unclosed buscar-guard at the form's byte offset, via an if/endif stack walker) — the occurrence count (2, not 3) is only a supplementary guard. Also asserts the form still precedes the region and that the autofocus script stays behind its buscar guard. Run → **fail** (`Failed asserting that true is false` at the form-guard check, first template).
- [x] GREEN — deleted only the guard line that opens the form block and its matching `{% endif %}` in the four templates; the autofocus guard and the tab `active` guard were left intact.
- [x] REFACTOR — none; no controller/helper change, no new Twig filter. Slice: **85 changed lines** (`+77 / −8`: test `+77`, four templates `−2` each) — within the 800-line budget. Focused `--filter TpvmodTwigTemplatesTest` green; full suite `OK (181 tests, 1337 assertions)`.

**Verificación:** `form[name="f_custom_search"]` is present for `todo`/intermediate/`buscar` in the four listings; the form stays above the region and outside it; the autofocus and tab `active` guards still count 2 per template.
**Comando:** `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest`

---

## Design verification map → task placement

`H` = `TpvmodListadosHelpersTest` (DB-free); `T` = `TpvmodTwigTemplatesTest`
extensions (DB-free); `S` = manual smoke in `verify-report.md`.

| ID | H (unit) | T (unit) | S (unit) |
|---|---|---|---|
| LHT-01 | — | `testListingsDeclareStableSwapRegion` (U11 region half, U12 control half) | U15, U20 |
| LHT-02 | — | `testListingsImportHtmxAndAlpineOnce` (U11) | U15, U20 |
| LHT-03 | — | `testRegionContainsMappedControls` (U12) → `testRegionBoundaryAndOrder` (U17) | U15, U20 |
| LHT-04 | `testListUrlCarriesEveryFilterAndEncodesSpecialChars` (U2) | `testControlToUrlMapping` (U12; incl. no `hx-params`) | U15, U20 |
| LHT-05 | `testSearchTermDecodesFrameworkEscape`, `testSearchPredicateMatchesDocumentAndClientFields`, `testSearchPredicateEscapesQuotes`, `testSearchPredicateHasNoCifnif`, `testSearchPredicateEmptyTerm` (U1) | `testListingControllersUseSharedSearchHelper` (U6) | U20 |
| LHT-06 | `testPhoneMapPrefersTelefono1`, `testPhoneMapFallsBackToTelefono2`, `testPhoneMapEmptyPhones`, `testPhoneMapSkipsFetchForEmptySet` (U4) | `testListingControllersUseBatchedPhoneLookup` (U7) → `testListingsRenderPhoneColumn` (U16) | U20 |
| LHT-07 | — | `testListingsRenderCitySnapshot` (U16) | U20 |
| LHT-08 | — | `testListingViewsExcludeClientPicker` (U18) | U20 |
| LHT-09 | `testNormalizeDateAcceptsIsoAndDmY`, `testNormalizeDateRejectsEmptyAndInvalid`, `testNormalizeDateParityWithHtmlFilter` (U5) | `testListingControllersUseSharedSearchHelper` (U6, controller) → `testNoDatepickerAndNativeDates` (U19, views) | U20 |
| LHT-10 | — | `testLineSearchFragmentContract` (U13 listing half, U14 fragment half); `testFacturasLineSearchPassesOffset` (U9) | U15, U20 |
| LHT-11 | `testBuildListUrlEncodesAndDropsEmpty`, `testBuildListUrlKeepsZero`, `testPagerLinksBoundsAndPrunes`, `testPagerLinksEmptyWhenSinglePage`, `testOrderTokenFor` (U2/U3) | `testListingControllersGateCronAndBuildUrls` (U6 URL half, U8 cron half) + `testNoLocalHtmxDetectionHelper` (U8) | U10, U15, U20 |
| LHT-12 | H suite green (U1–U5) | suite green (U10, U15, U20) | `phpstan` (U10, U15, U20) |
| LHT-13 | — | `testFilterBarRendersInEveryListingState` (U21) | U21: bar visible on the default and intermediate tabs; submit jumps to `buscar` |
| `views` delta | — | U14 (fragment pager), U17 (form precedes region), U18 (picker absent, narrowed assertion), U19 (native dates), existing `testEveryPostFormCarriesCsrfField` stays green (U13) | U20 |
| `tpv-cliente-modales` delta | — | U18 (modal stays on `tpvmod2`/`tpvmodedita`; absent from listings; `&codcliente=` read-only + clear) | U20 |
| TCP-01 | — | U13 (no `$.ajax`/`mas_resultados(`) | U15 |
| TCP-02/TCP-04 | — | U12 (`hx-get` + `hx-push-url`) | U15 |
| TCP-05 | — | U9 + U13/U14 (endpoint, params, `$this->template = 'ajax/ventas_lineas_<tipo>'` retained) | U15 |
| TCP-06 | — | U11/U12 (`clickableRow`, `data-toggle`) + U18 | U15, U20 |
| TCP-07 | — | U11 (exactly one `'htmx:after:swap'`; no v2 spelling) | U15 |
| TCP-08 | — | `testNoLocalHtmxDetectionHelper` (U8) | — |
| TCP-09 | U1–U5 | U10/U15/U20 | U20 (`phpstan`) |
| TCP-03 / TCP-10 | — | declared gaps G1/G4 — no surface (U14) | — |
| G2 | — | delegated `clickableRow`/`data-toggle` preserved (U11/U12) | U15 |

## Dependency summary

```
U1 ─┬─▶ U2 ─▶ U3 ─┐
    ├─▶ U4 ───────┼─▶ U6 ─┬─▶ U7 ─┐
    └─▶ U5 ───────┘       ├─▶ U8  │
                          └─▶ U9 ─┴─▶ U10 (PR1 gate)
                                        └─▶ U11 ─┬─▶ U12 ─┐
                                                 └─▶ U13 ─┼─▶ U14 ─▶ U15 (PR2 gate)
                                                          │        └─▶ U16 ─▶ ...
                                                           └─▶ U17 ─▶ U18 ─▶ U19 ─▶ U20 (PR3 gate) ─▶ U21 (post-verify amendment)
```

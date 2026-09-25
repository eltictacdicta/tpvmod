```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:8c592dd93d845de472f91b57228871a1726d50ed3ce0f47a18075eeb6f921770
verdict: fail
blockers: 3
critical_findings: 0
requirements: 11/20
scenarios: 52/70
test_command: ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
test_exit_code: 0
test_output_hash: sha256:43ac8c7534fa9a8cedceb45d6e5f0200cc09797c36ab2282899fa766dd680bf4
build_command: ddev exec composer phpstan
build_exit_code: 1
build_output_hash: sha256:a8e284d45da245b4205054af68c8f076ef02374034347db5f04479b126afdca0
```

# Verification Report

**Change**: tpvmod-listados-htmx
**Version**: plugin-local delta, spec set LHT-01..LHT-12 + `views` + `tpv-cliente-modales`
**Mode**: Strict TDD (`strict_tdd: true`, runner available)
**Worktree**: `plugins/tpvmod` on `master` @ `32d7c89a66d74b2fb75bfb766ddfa22d1db9319c`, clean tree
**Scope verified**: the merged PR1 (U1–U10) + PR2 (U11–U15) + PR3 (U16–U20) slices at the merge tip.

## Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 20 |
| Tasks complete (implementation) | 17 |
| Tasks incomplete | 3 (U10, U15, U20 browser-smoke checkboxes) |
| Units with code + tests | U1–U19 |
| Delta requirements (3 specs) | 20 |
| Delta scenarios (3 specs) | 70 |
| Requirements fully compliant | 11/20 |
| Scenarios compliant | 52/70 |
| Scenarios partial (static evidence only) | 18 |
| Scenarios failing / untested | 0 / 0 |

The three open checkboxes are the authenticated browser-smoke items of the gates
U10/U15/U20, explicitly delegated by `tasks.md` to `sdd-verify`. They are
verification activities, not un-implemented production work. The authenticated
session is not available to this verifier (see "Smoke execution"), so they stay
unchecked and are reported as WARNING, not silently closed.

## Build & Tests Execution

**Tests**: ✅ 176 passed / 0 failed / 0 skipped

```text
$ ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 176 ( 35%)
............................................................... 126 / 176 ( 71%)
..................................................              176 / 176 (100%)

Time: 00:00.046, Memory: 8.00 MB

OK (176 tests, 1298 assertions)
exit code: 0
```

**Build / static analysis**: ⚠️ 1 error, pre-existing baseline only, no new errors

```text
$ ddev exec composer phpstan
------ -----------------------------------------------------------------------
 Line   tests/Core/PluginEnableAjaxSafetyTest.php
------ -----------------------------------------------------------------------
 308    Method Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates()
        should return array{success: bool, changes: list<string>, errors: list<string>}
        but returns array{success: true, errors: array{}}.
        🪪  return.type
------ -----------------------------------------------------------------------
[ERROR] Found 1 error
exit code: 1
```

Reading: the only error is `tests/Core/PluginEnableAjaxSafetyTest.php:308`, identical in
file/line/rule to the `master` baseline recorded in `apply-progress.md`. `phpstan.neon`
analyses `paths: [src, tests]` only; `plugins/tpvmod/**` is outside the analysed set, so
this plugin-local change cannot introduce a PHPStan finding. The non-zero build exit is
the pre-existing core error, not a regression. Because the recorded build command exits
non-zero, the verdict is held below a clean `pass`.

**Coverage**: ➖ Not available — no coverage driver detected for the plugin suite.

## Smoke execution (config.yaml `testing.smoke`)

**Status: UNAVAILABLE** — the smoke flow requires an authenticated agent session
with permissions and seeded TPV data; the verifier has neither credentials nor a
session, and the flow mutates state (generate tickets, close cash, reprint) that a
read-only verification pass must not create.

What was actually attempted (real output):

```text
$ curl -sk -o /dev/null -w "%{http_code}" "https://panel-ab.ddev.site/index.php?page=<p>"
tpvmod            -> 302   (redirect to login)
tpvmod_presupuestos -> 302
tpvmod_facturas   -> 302
tpvmod_albaranes  -> 302
tpvmod_pedidos    -> 302
tpvmod2           -> 302
tpvmodedita       -> 302
$ curl -sk "https://panel-ab.ddev.site/index.php?page=login" -> 200 (login page rendered)
```

This is partial evidence only: it proves the routes exist and the authentication gate
redirects unauthenticated requests to login. It does **not** exercise the listing render,
the htmx swaps, the Alpine re-init, the line-search POST/CSRF path, the `cron_job()` gate
writes, the deep-link client filter, or the native date range.

What would be required to close the smoke: an authenticated `agente` session (login
credentials + role with access to the four listings) on a seeded database, plus a
non-read-only test window, following `config.yaml` →
"abrir tpvmod como agente con permisos, elegir 'no terminal' o 'continuar sin terminal',
generar tickets, cerrar caja, reimprimir".

## Spec Compliance Matrix

Statuses: ✅ COMPLIANT = passing covering test; ⚠️ PARTIAL = test/static evidence covers
only part of the scenario; ❌ UNTESTED = no evidence.

### `listados-htmx` — LHT-01..LHT-12 (48 scenarios)

| Req | Scenario | Evidence | Result |
|-----|----------|----------|--------|
| LHT-01 | Direct navigation renders the full page | routes 302→login (curl); no render branch confirmed (only `isHtmxRequest` use is the cron gate) | ⚠️ PARTIAL |
| LHT-01 | htmx GET swaps only the region and pushes the URL | `testListingsDeclareStableSwapRegion` (hx-target=hx-select=hx-swap=hx-push-url counts) | ⚠️ PARTIAL |
| LHT-01 | Direct reload of a pushed URL reproduces the same view | none (runtime smoke U15/U20 unavailable) | ⚠️ PARTIAL |
| LHT-01 | JavaScript-disabled navigation works | plain `href` on every mapped control: tabs L227-263, order L182-219, pager `value['url']` L340, form `method="get"` L75 | ⚠️ PARTIAL |
| LHT-01 | Delegated jQuery and Bootstrap 3 behaviors survive a swap | `clickableRow` 1×/listing (L286), `data-toggle` 2–3×/listing; document-delegated | ⚠️ PARTIAL |
| LHT-01 | Listing GET swaps require no CSRF token | filter form is `method="get"` with no token (L75) | ⚠️ PARTIAL |
| LHT-02 | One opt-in import per listing view | `testListingsImportHtmxAndAlpineOnce` | ✅ COMPLIANT |
| LHT-02 | Global header and footer stay free of the assets | `testListingsImportHtmxAndAlpineOnce` (header/footer scan) | ✅ COMPLIANT |
| LHT-02 | Swapped tree re-initializes Alpine exactly once per swap | `testListingsImportHtmxAndAlpineOnce` (exactly one `'htmx:after:swap'`, `Alpine.initTree(`) | ✅ COMPLIANT |
| LHT-03 | Active tab and order state are correct after a swap | server-rendered `class="active"` inside region (L226/L234/L244/L254); runtime swap unverified | ⚠️ PARTIAL |
| LHT-03 | Filter inputs are not replaced by a swap | `testRegionBoundaryAndOrder` (filter form byte-offset < region) | ✅ COMPLIANT |
| LHT-03 | Region contains the mapped controls | `testRegionBoundaryAndOrder` (toolbar/tabs/table/pager inside region) | ✅ COMPLIANT |
| LHT-04 | Filter selects and dates update the listing | `testControlToUrlMapping` (own-key omission, `hx-trigger="change"` ×4, `hx-push-url`) | ✅ COMPLIANT |
| LHT-04 | Tabs, order and pagination update the listing | `testControlToUrlMapping` (MODULE_TABS + MODULE_ORDER_TOKENS + pager url) | ✅ COMPLIANT |
| LHT-04 | Full-text submit updates the listing | `testControlToUrlMapping` (`hx-get="{{ fsc.url() }}"`, `hx-trigger="submit"`, hidden mostrar/order) | ✅ COMPLIANT |
| LHT-04 | Rechazar remains a CSRF-protected POST | `testEveryPostFormCarriesCsrfField`; form `method="post"` + `{{ csrf_field() }}` (presupuestos L416-417) | ✅ COMPLIANT |
| LHT-05 | Query matches the live company name | `testSearchPredicateMatchesDocumentAndClientFields` (`nombre`/`razonsocial` subquery) | ✅ COMPLIANT |
| LHT-05 | Query matches a customer phone | same test (`telefono1`/`telefono2` in subquery) | ✅ COMPLIANT |
| LHT-05 | Query still matches document fields | same test (`codigo`/`numero2`/`observaciones`) | ✅ COMPLIANT |
| LHT-05 | Quote-bearing query is safely escaped | `testSearchPredicateEscapesQuotes` (`'%o''brien%'`, no entities) | ✅ COMPLIANT |
| LHT-05 | No cifnif and no schema change | `testSearchPredicateHasNoCifnif`; no `model/table/*.xml` change in the diff | ✅ COMPLIANT |
| LHT-06 | telefono1 is shown when present | `testPhoneMapPrefersTelefono1`, `testResolvePhonePrefersPrimary` | ✅ COMPLIANT |
| LHT-06 | telefono2 is used as fallback | `testPhoneMapFallsBackToTelefono2` | ✅ COMPLIANT |
| LHT-06 | No phone renders empty | `testPhoneMapEmptyPhones` | ✅ COMPLIANT |
| LHT-06 | One batched query per page, no N+1 | `testPhoneMapSkipsFetchForEmptySet`, `testPhoneMapDeduplicatesCodesBeforeFetch`, `testListingControllersUseBatchedPhoneLookup` (exactly one `FROM clientes`) | ✅ COMPLIANT |
| LHT-07 | Document with a city snapshot shows it | `testListingsRenderCitySnapshot` (`{{ value.ciudad }}`) | ✅ COMPLIANT |
| LHT-07 | Document without a city snapshot renders empty | `testListingsRenderCitySnapshot` (no `dirclientes`/`domfacturacion` lookup) | ✅ COMPLIANT |
| LHT-08 | No picker markup or JS in the four listings | `testListingViewsExcludeClientPicker` | ✅ COMPLIANT |
| LHT-08 | codcliente filters and renders as read-only text | `testListingViewsExcludeClientPicker` (readonly field) + controller `if($this->cliente)` predicate (presupuestos L468); runtime filter not executed | ⚠️ PARTIAL |
| LHT-08 | The [+] link keeps filtering | static: `[+]` href carries `&amp;codcliente={{ value.codcliente }}` (all 4, e.g. presupuestos L301); no test | ⚠️ PARTIAL |
| LHT-08 | The client filter can be cleared | `testListingViewsExcludeClientPicker` (clear control via `list_url({'codcliente': ''})`) | ✅ COMPLIANT |
| LHT-09 | No datepicker remains in the five views | `testNoDatepickerAndNativeDates`; grep `datepicker` → 0 | ✅ COMPLIANT |
| LHT-09 | Date filters are prefilled with the ISO value | `testNoDatepickerAndNativeDates` (`type="date"` + `value="{{ fsc.desde\|date_iso }}"`) | ✅ COMPLIANT |
| LHT-09 | Incoming date is normalized before var2str | `testListingControllersUseSharedSearchHelper` + `testNormalizeDateAcceptsIsoAndDmY`, `testNormalizeDateRejectsEmptyAndInvalid` | ✅ COMPLIANT |
| LHT-09 | Date range bounds the results | predicate shape static (`fecha >= / <= var2str(...)`); runtime range unverified (smoke U20) | ⚠️ PARTIAL |
| LHT-09 | tpvmodedita stores a consistent date | `testNoDatepickerAndNativeDates` (ISO read regex on `tpvmod.php`); no runtime save | ⚠️ PARTIAL |
| LHT-10 | Debounced typing swaps the results | `testLineSearchFragmentContract` (`hx-trigger="keyup changed delay:300ms"`, both `hx-sync`) | ✅ COMPLIANT |
| LHT-10 | Marker and JS offset arithmetic are gone | `testLineSearchFragmentContract`; grep marker/`mas_resultados(` → 0 | ✅ COMPLIANT |
| LHT-10 | Server-side offset pager works on all four modules | `testLineSearchFragmentContract` (hx-vals offset prev/next, facturas included); apply render harness 8/8 (not committed) | ✅ COMPLIANT |
| LHT-10 | Client-scoped line search is preserved | `testFacturasLineSearchPassesOffset`; `search_from_cliente2` grep 1×/controller | ✅ COMPLIANT |
| LHT-10 | CSRF is validated on the line-search POST | static: form `method="post"`+`{{ csrf_field() }}` + macro-inherited `X-CSRF-TOKEN` (`Macro/Htmx.html.twig:81-84`, `validateCsrf()` accepts either); no runtime reject test | ⚠️ PARTIAL |
| LHT-11 | cron_job is skipped on htmx requests | `testListingControllersGateCronAndBuildUrls` (regex gate) + apply DB harness; runtime full-page cron unverified | ⚠️ PARTIAL |
| LHT-11 | No local HX-detection helper exists | `testNoLocalHtmxDetectionHelper`; grep `is_htmx_request\|tpvmod_is_htmx_request` → 0 in production source | ✅ COMPLIANT |
| LHT-11 | URLs are encoded through http_build_query | `testBuildListUrlEncodesAndDropsEmpty`, `testBuildListUrlKeepsZero`, `testListUrlCarriesEveryFilterAndEncodesSpecialChars`, `testListingControllersGateCronAndBuildUrls` | ✅ COMPLIANT |
| LHT-11 | The four modules share the helpers | `testListingControllersUseSharedSearchHelper`, `testListingControllersUseBatchedPhoneLookup`, `testListingControllersGateCronAndBuildUrls`, `testFacturasLineSearchPassesOffset` | ✅ COMPLIANT |
| LHT-12 | Helper tests run without a database | `TpvmodListadosHelpersTest` (20 methods) in the green DB-free suite | ✅ COMPLIANT |
| LHT-12 | htmx and native-date template assertions exist | `testListingsDeclareStableSwapRegion`, `testControlToUrlMapping`, `testNoDatepickerAndNativeDates` | ✅ COMPLIANT |
| LHT-12 | Dispatch assertion stays green | `testControllersDropCsrfWorkaround` in the green suite | ✅ COMPLIANT |

### `views` delta (15 scenarios)

| Req | Scenario | Evidence | Result |
|-----|----------|----------|--------|
| CSRF via Twig function only | No legacy CSRF workaround anywhere in view/ | `testEveryPostFormCarriesCsrfField` (`{$fsc->csrf_field`, `csrf_field\|raw` absent) | ✅ COMPLIANT |
| CSRF via Twig function only | Every POST form contains csrf_field | `testEveryPostFormCarriesCsrfField` | ✅ COMPLIANT |
| CSRF via Twig function only | Quick-create form carries the token | `testOpcionalesPartialHasTabsListAndSiblingForm`; `modal_opcionales.html.twig:32-33` | ✅ COMPLIANT |
| CSRF via Twig function only | Line-search forms keep the fallback token | `testLineSearchFragmentContract` (`method="post"` + `{{ csrf_field() }}`) | ✅ COMPLIANT |
| Nine debt-fill templates | ajax/tpv_cambios_precios renders article + tariffs | no covering test found in `TpvmodTwigTemplatesTest` (inherited from the parent `modernize-m3` capability; this change did not modify it) | ⚠️ PARTIAL |
| Nine debt-fill templates | 8 ventas templates reference the documented fields | `testAllExpectedTwigTemplatesExist` checks existence/non-emptiness only, not the field list; no field-grep test found | ⚠️ PARTIAL |
| Nine debt-fill templates | Line-search fragments drop the marker and gain the pager | `testLineSearchFragmentContract` (fragment half) | ✅ COMPLIANT |
| Test suite covers inventory/cleanup | All assertions pass | full plugin suite green (176/1298) | ✅ COMPLIANT |
| Test suite covers inventory/cleanup | Client-button assertion is narrowed, not dropped | `testViewsNoLongerUseClienteAutocomplete` (`$pickerViews` present / `$listingViews` absent) | ✅ COMPLIANT |
| Filter form precedes region | Markup order is filter form, then region | `testRegionBoundaryAndOrder` | ✅ COMPLIANT |
| Filter form precedes region | Stable region id per module | `testListingsDeclareStableSwapRegion`; 1 id per template (grep) | ✅ COMPLIANT |
| Native date inputs | No datepicker class remains | `testNoDatepickerAndNativeDates` | ✅ COMPLIANT |
| Native date inputs | Date values are prefilled with date_iso | `testNoDatepickerAndNativeDates` | ✅ COMPLIANT |
| Client picker absent | Listing views exclude the picker | `testListingViewsExcludeClientPicker` | ✅ COMPLIANT |
| Client picker absent | The picker survives on the screens that keep it | `testViewsNoLongerUseClienteAutocomplete` (button present) + grep (`modal_clientes` include at tpvmod2:381 / tpvmodedita:463; `tpvmod-cliente.js` load; file exists, 9440 B) | ✅ COMPLIANT |

### `tpv-cliente-modales` delta (7 scenarios)

| Req | Scenario | Evidence | Result |
|-----|----------|----------|--------|
| Modal de búsqueda de clientes | The modal opens on the screens that keep the picker | static: button + include present on `tpvmod2`/`tpvmodedita`; runtime open/search unverified (smoke U20) | ⚠️ PARTIAL |
| Modal de búsqueda de clientes | Selecting a row applies the client | none (runtime; unchanged pre-existing flow) | ⚠️ PARTIAL |
| Modal de búsqueda de clientes | The modal is not part of the four listings | `testListingViewsExcludeClientPicker` | ✅ COMPLIANT |
| Modal de búsqueda de clientes | The modal survives on the screens that keep it | grep (include + JS load) + `testViewsNoLongerUseClienteAutocomplete` (button) | ✅ COMPLIANT |
| Filtro profundo por cliente | Deep link keeps filtering without the picker | controller `codcliente` branch (presupuestos L154-161, L468); runtime filter unverified | ⚠️ PARTIAL |
| Filtro profundo por cliente | Active client is read-only | `testListingViewsExcludeClientPicker` (`readonly="readonly"`) | ✅ COMPLIANT |
| Filtro profundo por cliente | The client filter is reversible | `testListingViewsExcludeClientPicker` (clear control) | ✅ COMPLIANT |

## TCP-01..TCP-10 compatibility matrix (core pilot contract)

| TCP | Requirement | Evidence | Result |
|-----|-------------|----------|--------|
| TCP-01 | Lazy-load family rows via hx attributes; remove `$.ajax` fetch | Analogue: `testLineSearchFragmentContract` — `$.ajax`/`mas_resultados(`/`function buscar_lineas(` absent from the 4 listings; grep confirms 0 | ✅ COMPLIANT |
| TCP-02 | View-mode/sort refetch via `hx-get` with the same params | `testControlToUrlMapping` (tabs + order tokens per module) | ✅ COMPLIANT |
| TCP-03 | Load-more appends with `hx-swap="beforeend"` | Declared gap G1: no tpvmod surface; LHT-10 pins replace semantics (`innerHTML`), no `beforeend` | ➖ N/A (declared) |
| TCP-04 | Full-page filters push the URL | `testControlToUrlMapping` + `hx-push-url="true"` on the form/selects/dates | ✅ COMPLIANT |
| TCP-05 | Response parity with `$.ajax` consumers (endpoint, params, template) | fragments keep `hx-post="{{ fsc.url() }}"` + params; controllers keep `$this->template = 'ajax/ventas_lineas_<tipo>'` (grep 1×/controller) | ✅ COMPLIANT |
| TCP-06 | jQuery flows preserved | static: `clickableRow` 1×/listing, `data-toggle` 2–3×/listing, jQuery client modal intact on `tpvmod2`/`tpvmodedita`; runtime swap preservation unverified (G2) | ⚠️ PARTIAL |
| TCP-07 | `htmx:after:swap` re-init shim, htmx 4 colon-style | `testListingsImportHtmxAndAlpineOnce` (exactly one `'htmx:after:swap'`, no v2 spelling) | ✅ COMPLIANT |
| TCP-08 | Dead plugin `is_htmx_request()` removed; delegate to `isHtmxRequest()` | `testNoLocalHtmxDetectionHelper` | ✅ COMPLIANT |
| TCP-09 | Plugin regression suite green | `OK (176 tests, 1298 assertions)` | ✅ COMPLIANT |
| TCP-10 | Well-formed error rows inside `<tr>` | Declared gap G4: no tpvmod surface swaps into `<tr>` (target is `<div id="search_results">`); fragments made well-formed | ➖ N/A (declared) |

## Correctness (Static Evidence)

| Requirement | Status | Notes |
|-------------|--------|-------|
| Full-page render, no fragment branch | ✅ Implemented | `isHtmxRequest()` occurs only in the presupuestos/pedidos cron gate (L193/L189); no render branch |
| Stable swap region per module | ✅ Implemented | 1 `id="tpvmod-<tipo>-region"` per template (grep: presupuestos/facturas/albaranes/pedidos L174/L174/L169/L170) |
| Adapted search predicate | ✅ Implemented | `lib/tpvmod_listados.php:45-63`; `var2str` injected; no `cifnif`; subquery form |
| Batched phone lookup | ✅ Implemented | `telefonos_pagina()` 1×`FROM clientes` per controller; public `telefono_cliente()` |
| City snapshot | ✅ Implemented | `{{ value.ciudad }}` per row; no address lookup |
| Native dates | ✅ Implemented | 10 `type="date"`; controller `tpvmod_normalize_date()` |
| Line-search htmx contract | ✅ Implemented | `hx-post`/`hx-target="#search_results"`/`hx-swap="innerHTML"`/`hx-sync` + server offset pager |
| Shared helpers | ✅ Implemented | single `lib/tpvmod_listados.php` required by all 4 controllers |

## Coherence (Design)

| Decision | Followed? | Notes |
|----------|-----------|-------|
| AD-1 single pure helper file, free `tpvmod_*` functions | ✅ Yes | `lib/tpvmod_listados.php`, 8 functions |
| AD-2 full-page + `hx-select`/`outerHTML`/`hx-push-url`, no partial branch | ✅ Yes | grep: only cron-gate `isHtmxRequest()` |
| AD-3 region ids verbatim | ✅ Yes | `#tpvmod-<tipo>-region` |
| AD-4 no `hx-params`/`hx-include` | ✅ Yes | 0 in production source (matches only in test guard/prose) |
| AD-5 no `Macro/HtmxCrud.html.twig` | ✅ Yes | grep `HtmxCrud.html.twig` in `view/` → 0 |
| AD-6 `tpvmod_search_term()` reverses the input escape | ✅ Yes | `testSearchTermDecodesFrameworkEscape` |
| AD-7 `f_custom_search`→GET; line search/Rechazar stay POST | ✅ Yes | form `method="get"`; two POST forms with `csrf_field` |
| AD-8 Rechazar native POST | ✅ Yes | unchanged POST form |
| AD-9 server-computed offset via `hx-vals` | ✅ Yes | fragment pager |
| AD-10 `cron_job()` gated in presupuestos and pedidos | ✅ Yes | both gated; facturas/albaranes contain no `cron_job(` |
| AD-11 `date_iso` view + `tpvmod_normalize_date()` controller | ✅ Yes | `testNoDatepickerAndNativeDates`, `testNormalizeDate*` |
| AD-12 portable `lower(col) LIKE` + subquery | ✅ Yes | helper predicate; no FULLTEXT/tsvector |
| AD-13 filter form guard kept, moved above the tabs | ✅ Yes | `{% if fsc.mostrar == 'buscar' %}` still guards; form precedes region |
| AD-14 top-bar split | ✅ Yes | order dropdown inside region; action buttons/`b_buscar_lineas` outside |

**Design deviations found** (all already recorded in `apply-progress.md` "Deviations from
design"): D-1 `(string) $this->query`, D-2/D-3 URL serialization, D-4 predicate gate after
trim, D-6 `?? ''`, D-7 stray `$this->codserie` in presupuestos kept. None breaks a spec.

## Contract Audits (grep over `plugins/tpvmod`, `vendor/` and `.git/` excluded)

| Audit | Expected | Actual |
|-------|----------|--------|
| `hx-params` in production source | 0 | 0 (only test guard + openspec prose) |
| `hx-include` in production source | 0 | 0 |
| `no_html(` in the 4 listing controllers | 0 | 0 |
| local HX helper `is_htmx_request`/`tpvmod_is_htmx_request` in production source | 0 | 0 |
| `datepicker` in the 5 views | 0 | 0 |
| `$.ajax` / `mas_resultados(` in the 4 listings | 0 | 0 |
| `Macro/HtmxCrud.html.twig` load in `view/` | 0 | 0 |
| `htmx:after:swap` listener bindings per listing view | exactly 1 | 1 binding per view (a second textual hit is the comment) |
| `tpvmod_cliente_ajax_dispatch` in the 4 listing controllers | present | 1× each |
| `{{ csrf_field() }}` in each POST form | present | present (listings + `modal_opcionales`) |
| picker markers (`ac_cliente`, `tpvmod-b-buscar-cliente`, `modal_clientes` include, `tpvmod-cliente.js`) in the 4 listings | absent | absent |
| `&codcliente=` filter as read-only text | present | `id="tpvmod-cliente-activo" readonly` + `[+]` href with `&codcliente=` |
| `{{ csrf_field() }}`/legacy workaround in view/ | no `{$fsc->csrf_field}`/`csrf_field\|raw` | absent |

## Security Audit (`fsframework-security-review` lens)

- **SQL injection (LSS-01/LSS-02)**: OK. The four `buscar()` build the predicate through
  `tpvmod_build_search_predicate($term, fn($v) => $this->var2str($v))`; the batched phone
  lookup escapes each `codcliente` with `$this->var2str()` before `IN (...)`. No
  `htmlspecialchars`/`no_html()` remains in the SQL path. `testSearchPredicateEscapesQuotes`
  pins `O'Brien` → `'%o''brien%'`.
- **CSRF**: OK. Every POST form carries `{{ csrf_field() }}` (`testEveryPostFormCarriesCsrfField`);
  listing GET swaps need no token; the htmx POST inherits `X-CSRF-TOKEN` from
  `Macro/Htmx.html.twig:81-84`; no new token mechanism.
- **XSS**: OK. No new `|raw` over user input. The `|raw` hits in the touched views are
  `fsc.url()` (server-built), server-built pager `value['url']`, pre-existing extension
  `value.text|raw`, and `json_encode(...)|raw` JS bootstrap; user fields (`value.ciudad`,
  `fsc.telefono_cliente(...)`, `value.nombrecliente`) are Twig auto-escaped.
- **Input validation**: `desde`/`hasta` are validated with `checkdate()` via
  `tpvmod_normalize_date()`; invalid input yields no predicate.
- No password, file-upload, session, open-redirect, or admin-only surfaces were touched.

## TDD Compliance (Strict TDD)

| Check | Result | Details |
|-------|--------|---------|
| TDD Evidence reported | ✅ | `apply-progress.md` "TDD Cycle Evidence" tables for PR1, PR2 (two batches), PR3 |
| All tasks have tests | ✅ | 17/17 implemented units have test files; gates U10/U15/U20 are verification-only |
| RED confirmed (tests exist) | ✅ | `TpvmodListadosHelpersTest.php` (20 methods), `TpvmodTwigTemplatesTest.php` (29 methods) exist |
| GREEN confirmed (tests pass) | ✅ | full suite `OK (176 tests, 1298 assertions)` |
| Triangulation adequate | ✅ | e.g. pager bounds/prune/single-page/zero; phone primary/fallback/empty/zero-fetch/dedupe; date ISO/d-m-Y/invalid/leap |
| Safety Net for modified files | ✅ | baseline 142/599 → 176/1298; `phpstan` baseline preserved |

**TDD Compliance**: 6/6 checks passed.

### Test Layer Distribution

| Layer | Tests | Files | Tools |
|-------|-------|-------|-------|
| Unit (DB-free) | 20 | 1 (`TpvmodListadosHelpersTest.php`) | PHPUnit 11 |
| Contract/source (DB-free) | 29 | 1 (`TpvmodTwigTemplatesTest.php`) | PHPUnit 11 |
| Integration (DB-backed CLI harness) | not committed | throwaway (apply phase) | — |
| E2E | 0 | — | not installed |

### Changed File Coverage

Coverage analysis skipped — no coverage driver detected for the plugin suite.

### Assertion Quality

**Assertion quality**: ✅ All assertions verify real behavior. Reviewed both test files:
no tautologies, no ghost loops over possibly-empty collections, no type-only assertions
without value checks. Notable `assertSame` value assertions: predicate SQL (`'%o''brien%'`),
URL encoding (`query=a%26b%20%23c%20d`), pager key/num/url arrays, phone maps, ISO
normalization. `testPhoneMapSkipsFetchForEmptySet` binds a fetcher call counter to prove
zero queries (not a vacuous empty-array check).

### Quality Metrics

**Linter**: ➖ Not available (no plugin linter configured).
**Type Checker**: ⚠️ `phpstan` exit 1 — 1 pre-existing baseline error only, `plugins/tpvmod` outside the analysed paths.

## Issues Found

**CRITICAL**: None. No failing test, no new static-analysis error, and no spec
requirement is contradicted by the implementation.

**WARNING**:

- **W-1 — authenticated smoke unexecuted (U10/U15/U20).** `tasks.md` leaves the three
  browser-smoke checkboxes open and delegates them to `sdd-verify`; no authenticated
  session is available (unauthenticated GETs 302→login). Consequence: 18 runtime-only
  scenarios across LHT-01/03/08/09/10/11, `views`, `tpv-cliente-modales`, and TCP-06 have
  static or contract evidence but no executed browser proof. What is missing: an
  authenticated `agente` session on a seeded DB to run `config.yaml` `testing.smoke`.
- **W-2 — `tasks.md` U10 wording contradiction.** U10 states pagination/filter URLs are
  "byte-identical" to the pre-change output. That is false as written. Correct claim:
  *for the same state the result set and the offsets are identical, but the query-string
  serialization changed* — `tpvmod_build_list_url()` drops empty filters, RFC3986-encodes,
  and now carries `order` explicitly (design §1.5 `list_params()` includes `order`).
  The apply phase already annotated this inline in `tasks.md:305`; the header wording
  remains misleading.
- **W-3 — LHT-11 "no raw string concatenation" is not literally met for the `[+]`/chrome
  links.** `http_build_query` governs the builder, but in every listing the `[+]` row link
  (`{{ fsc.url()|raw }}&amp;codcliente={{ value.codcliente }}`), the `default_page` toggle,
  and the line-search modal's "filtrar por cliente" link concatenate `fsc.url()` raw.
  The values involved are trusted (DB `codcliente`, constants) and the risk R3 target
  (unencoded `paginas()` URLs) is satisfied, so this is a literal-scope gap, not an
  injection path.
- **W-4 — `apply-progress.md` references a non-existent test.** The PR2 entries cite
  `testRegionContainsMappedControls` (U12), which is not present in
  `TpvmodTwigTemplatesTest.php`; its assertions were folded into `testRegionBoundaryAndOrder`
  / `testControlToUrlMapping`. Documentation drift only; the behavior is covered.

**SUGGESTION**:

- **S-1 — the "modal survives" clause is only partly test-asserted.** The button
  (`tpvmod-b-buscar-cliente`) is test-pinned on `tpvmod2`/`tpvmodedita`, but the
  `{% include 'partials/modal_clientes.html.twig' %}` and the `tpvmod-cliente.js` load are
  only grep-verified. Add explicit assertions.
- **S-2 — `testAllExpectedTwigTemplatesExist` lists 23 templates while the `views` delta
  text says "19 expected".** The smaller list is a subset; the requirement wording and the
  test list should be reconciled.
- **S-3 — inherited debt-fill scenarios untested.** The `views` delta scenarios
  "ajax/tpv_cambios_precios renders article + tariffs" and "8 ventas templates reference
  the documented fields" have no covering test; they belong to the parent capability and
  were not modified by this change, but they are part of the re-verified requirement.

## Requirements Without Full Evidence

Fully compliant requirements: 11/20. The following requirements are not fully proven
(partial/static only); none is contradicted and none is failing:

| Requirement | Missing evidence |
|-------------|------------------|
| LHT-01 | Runtime full-page render, pushed-URL reload, JS-disabled navigation, delegated-handler survival, no-CSRF GET (smoke) |
| LHT-03 | Runtime active tab/order state after a swap |
| LHT-08 | Runtime `codcliente` filtering and `[+]` link result set |
| LHT-09 | Runtime date-range bounding; runtime `tpvmodedita` save |
| LHT-10 | Runtime CSRF rejection path on the line-search POST |
| LHT-11 | Runtime observation that the full-page load runs `cron_job()` and the swap skips it |
| `views` | Two inherited debt-fill scenarios (no covering test) |
| `tpv-cliente-modales` | Runtime modal open/search/select and deep-link filtering |
| TCP-06 | Runtime preservation of delegated jQuery/Bootstrap handlers after a swap |

## Verdict

**FAIL (not archive-ready)** — the envelope verdict is `fail` because the verification
evidence is **incomplete**, not because the implementation is broken:

- **3 blockers**: the authenticated browser-smoke gates U10/U15/U20 remain unchecked and
  cannot be executed in this environment (no authenticated agent session; 18 runtime-only
  scenarios across LHT-01/03/08/09/10/11, `views`, `tpv-cliente-modales` and TCP-06 have
  no executed proof).
- **Build command exits non-zero** (`phpstan` exit 1) on the pre-existing
  `tests/Core/PluginEnableAjaxSafetyTest.php:308` baseline error, unrelated to
  `plugins/tpvmod` (outside PHPStan's analysed paths) and identical to `master`.

The validator admits a `pass`/`pass_with_warnings` verdict only when both commands exit 0
and the completed counts equal the totals; neither condition holds on the honest evidence,
and a fabricated `0`/full-count envelope was not produced (`NUNCA inventes PASS`).

Substance: all 17 implementation units are present, the plugin suite is green
(176 tests / 1298 assertions, exit 0), no new static-analysis error exists, and no
delta-spec requirement is contradicted. **0 CRITICAL findings.** Archive is blocked until
the authenticated smoke is executed and the `tasks.md` U10 wording (W-2) is reconciled;
the remaining WARNING/SUGGESTION items are documentation/scope hygiene.

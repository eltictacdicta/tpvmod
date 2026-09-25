# Apply Progress: tpvmod-listados-htmx

> **Plugin-local SDD.** Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` is intentionally NOT touched.
> Artifact store: OpenSpec (plugin-local). Batches: **PR1 (U1–U10, complete)** and
> **PR2 (U11–U15 complete)** — see the PR1 section and the two PR2 sections at the end.

## Status

| Field | Value |
|---|---|
| Mode | **Strict TDD** (`strict_tdd: true` in `plugins/tpvmod/openspec/config.yaml`) |
| Batch | PR1 — shared helpers + controller hardening (U1–U10) |
| Branch | `feat/tpvmod-listados-htmx-pr1` (created from `master`, nested `plugins/tpvmod` repo) |
| Push / PR | **Not pushed, no PR opened** — delivery is the human's decision |
| Final plugin suite | `OK (167 tests, 859 assertions)` |
| `phpstan` | **No new errors** (1 pre-existing, unrelated — see below) |
| UI impact | **No template touched**; zero `view/` files in the diff |
| Stopped at | The **U10 gate**. U11 (PR2) and U16+ (PR3) not started |

### Commits (5 work units)

| Commit | Unit(s) | Summary |
|---|---|---|
| `601cd21` | U1–U5 | `feat(tpvmod): add shared pure helpers for the document listings` |
| `e4ff4a3` | U6 | `refactor(tpvmod): route listing search, URLs and paging through shared helpers` |
| `119339d` | U7 | `feat(tpvmod): resolve listing phones from one batched lookup per page` |
| `341e1f3` | U8 | `perf(tpvmod): skip cron_job on htmx requests in presupuestos and pedidos` |
| `152b1b8` | U9 | `fix(tpvmod): pass the offset to the facturas line search` |
| _(this file)_ | U10 | `docs(openspec): record PR1 apply progress` (tasks.md + apply-progress.md) |

## Units completed

| Unit | Goal | Status |
|---|---|---|
| U1 | `tpvmod_search_term()` + `tpvmod_build_search_predicate()` (LHT-05) | ✅ |
| U2 | `tpvmod_build_list_url()` + `tpvmod_order_token_for()` (LHT-04, LHT-11) | ✅ |
| U3 | `tpvmod_pager_links()` byte-for-byte legacy pager (LHT-11) | ✅ |
| U4 | `tpvmod_resolve_phone()` + `tpvmod_phone_map()` (LHT-06) | ✅ |
| U5 | `tpvmod_normalize_date()` (LHT-09) | ✅ |
| U6 | Wire search/URL/pager/date into the 4 controllers (LHT-05, LHT-09, LHT-11) | ✅ |
| U7 | Controller phone map + `telefono_cliente()` accessor (LHT-06) | ✅ |
| U8 | **CRITICAL** `cron_job()` gate on `isHtmxRequest()` — presupuestos **and** pedidos (LHT-11) | ✅ |
| U9 | facturas line-search offset parity (LHT-10) | ✅ |
| U10 | PR1 gate: plugin suite + phpstan | ✅ (manual browser smoke delegated to `sdd-verify`) |

## Files changed

| File | Action | What was done | Lines (authored) |
|---|---|---|---|
| `lib/tpvmod_listados.php` | **created** | 8 pure DB-free helpers (`tpvmod_search_term`, `tpvmod_build_search_predicate`, `tpvmod_build_list_url`, `tpvmod_order_token_for`, `tpvmod_pager_links`, `tpvmod_resolve_phone`, `tpvmod_phone_map`, `tpvmod_normalize_date`) | +236 |
| `tests/TpvmodListadosHelpersTest.php` | **created** | DB-free coverage for all 8 helpers | +314 |
| `controller/tpvmod_presupuestos.php` | modified | helper `require_once`; `list_params()`/`list_url()`/`list_total()`/`paginas()`; predicate via `tpvmod_search_term`+`tpvmod_build_search_predicate`+`var2str` (deleted `no_html()`); `desde`/`hasta` via `tpvmod_normalize_date()`; `telefonos_pagina()`+`telefono_cliente()`; `cron_job()` gated | +99 / -79 |
| `controller/tpvmod_facturas.php` | modified | same wiring; line-search offset on both model calls | +94 / -78 |
| `controller/tpvmod_albaranes.php` | modified | same wiring (no `cron_job()` — no gate added) | +92 / -76 |
| `controller/tpvmod_pedidos.php` | modified | same wiring; `cron_job()` gated | +99 / -79 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `testListingControllersUseSharedSearchHelper`, `testListingControllersGateCronAndBuildUrls` (extended), `testListingControllersUseBatchedPhoneLookup`, `testNoLocalHtmxDetectionHelper`, `testFacturasLineSearchPassesOffset` + 2 private helpers | +188 |
| `openspec/changes/tpvmod-listados-htmx/tasks.md` | modified | U1–U10 checkboxes marked, U10 outcomes recorded | +34 / -32 (prose, not PR budget) |

No `view/` file was touched. No Composer/npm dependency was added → no `vendor/` change and no dependency-commit step.

## TDD Cycle Evidence (Hard Gate — Strict TDD)

Safety-net baseline on `master` before any edit: **142 tests, 599 assertions, all green**.
`phpstan` baseline on `master`: 1 pre-existing error (`tests/Core/PluginEnableAjaxSafetyTest.php:308`).

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U1 | `TpvmodListadosHelpersTest.php` | Unit (DB-free) | N/A (new file) → suite 142/142 | ✅ Written; run failed (required lib file absent) | ✅ Passed (5/5) | ✅ 5 cases: happy, quote-bearing, `<script>`, `cifnif`-absent, empty-gate (escaper not called) | ✅ extracted `literalEscaper()` factory; signatures match design §1 |
| U2 | same | Unit | ✅ 142/142 | ✅ Written; run failed (`tpvmod_build_list_url` ×4 undefined) | ✅ Passed (9/9) | ✅ 4 cases: encodings, drop-empty + key order, zero kept, order tokens incl. unknown | ✅ key order asserted explicitly |
| U3 | same | Unit | ✅ 142/142 | ✅ Written; run failed (`tpvmod_pager_links` ×2 undefined) | ✅ Passed (11/11) | ✅ sparse keys, page nums, URL callable offsets, single `actual`, single-page/zero/`limit<1`/two-page | ✅ inline legacy comment translated to English; parity matrix run separately |
| U4 | same | Unit | ✅ 142/142 | ✅ Written; run failed (`tpvmod_phone_map`/`tpvmod_resolve_phone` ×6 undefined) | ✅ Passed (17/17) | ✅ 6 cases: primary, fallback, both-empty, zero-fetch, dedupe-before-fetch, resolve matrix | ✅ dedupe ordering asserted |
| U5 | same | Unit | ✅ 142/142 | ✅ Written; run failed (`tpvmod_normalize_date` ×3 undefined) | ✅ Passed (20/20) | ✅ 3 cases: ISO+d-m-Y+leap, invalid matrix, `Html::dateIsoValue` parity + no new Twig filter | ✅ no `var2str`-only path added |
| U6 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ 162/162 | ✅ Written; run failed (2 tests) | ✅ Passed (164) | ✅ all 4 controllers: helper calls, no `no_html()` in `buscar()`, `var2str` in predicate, `desde`/`hasta` normalized, `tpvmod_pager_links` in `paginas()`, no `&` concatenation | ✅ `list_total()` switches kept module-local |
| U7 | same | Contract | ✅ 164/164 | ✅ Written; run failed (1 test) | ✅ Passed (165) | ✅ all 4 controllers: exactly one `FROM clientes` + `codcliente IN (` + `telefono_cliente(` | ✅ memoized on `telefonos_map === null` |
| U8 | same | Contract | ✅ 165/165 | ✅ Written; run failed (2 tests) | ✅ Passed (166) | ✅ both gate owners (regex-guarded `isHtmxRequest()`→`cron_job()`) + both non-callers asserted `cron_job`-free + plugin-wide scan for local helpers | ➖ None needed |
| U9 | same | Contract | ✅ 166/166 | ✅ Written; run failed (1 test) | ✅ Passed (167) | ✅ both branches (`search` + `search_from_cliente2`) + reflected model arity | ➖ None needed |
| U10 | — | Gate | ✅ 167/167 | N/A (gate, not a behavior) | ✅ Passed | N/A | N/A |

## Work Unit Evidence (Hard Gate — all modes)

| Unit | Focused test command + exact result | Runtime harness command/scenario + exact result | Rollback boundary |
|---|---|---|---|
| U1–U5 (helpers) | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodListadosHelpersTest` → `OK (20 tests, 87 assertions)` | `ddev exec php <tmp harness>`: predicate executed against the real MySQL DB on all 4 tables for `''`, `a`, `O'Brien`, `<script>`, `acme` → no SQL error; quote term emitted as `'%o''brien%'`; `phone_map calls=1 requested=6 entries=4`; empty set → `calls=0` | Delete `lib/tpvmod_listados.php` + `tests/TpvmodListadosHelpersTest.php` (not referenced before U6) |
| U6 (search/URL/pager/date wiring) | `… --filter TpvmodTwigTemplatesTest` → `OK (14 tests, …)`; full suite `OK (164 tests, 746 assertions)` | DB harness: all 4 tables × 5 terms execute; date range predicate executes; `legacy d-m-Y var2str -> '2026-12-31'`; canonical URL sample `index.php?page=tpvmod_presupuestos&mostrar=buscar&query=O%27Brien&codagente=EMP1&desde=2026-01-01&order=fecha_desc&offset=0` | Revert the 4 controllers' `require_once`/`list_*`/`paginas()`/`buscar()`/date edits; helpers stay unused |
| U7 (batched phone) | `… --filter TpvmodTwigTemplatesTest` → `OK (15 tests, …)`; full suite `OK (165 tests, 770 assertions)` | DB harness: one `clientes` IN-lookup for 6 requested codes (4 distinct after dedupe/empty-drop), 1 fetch; empty set → 0 fetches | Revert `telefonos_pagina()`/`telefono_cliente()`/`$telefonos_map` in the 4 controllers (no view consumes the accessor yet) |
| U8 (**critical** cron gate) | `… --filter TpvmodTwigTemplatesTest` → `OK (17 tests, …)`; full suite `OK (166 tests, 855 assertions)` | Real `fs_controller::isHtmxRequest()` via an anonymous subclass: `HX-Request absent -> false -> cron_job() runs=true`; `HX-Request present -> true -> cron_job() runs=false`. DB/log observation on a real non-htmx page load is delegated to the U10 smoke in `verify-report.md` | Revert the two `if( !$this->isHtmxRequest() )` wrappers (presupuestos, pedidos) |
| U9 (facturas offset) | `… --filter TpvmodTwigTemplatesTest` → `OK (18 tests, …)`; full suite `OK (167 tests, 859 assertions)` | `ReflectionMethod` on `linea_factura_cliente`: `search` has ≥2 params, `search_from_cliente2` has ≥4 → both accept the offset (no `ArgumentCountError`) | Revert the two offset arguments in `tpvmod_facturas::buscar_lineas()` |
| U10 (gate) | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → `OK (167 tests, 859 assertions)` | Combined DB harness: **22/22 checks PASS → `U10 HARNESS OK`** | N/A (verification only) |

## Test Summary

- **Total tests written**: 25 new/extended test methods in the plugin suite (20 in the new helper test + 5 in the Twig/contract test), 167 total in the suite.
- **Total tests passing**: 167 (859 assertions), suite-wide; started at 142 (599 assertions).
- **Layers used**: Unit / Contract-source (DB-free) 25, Integration (DB-backed CLI harness, not committed) 1 harness / 22 checks, E2E 0.
- **Approval tests** (refactoring): parity oracle for `tpvmod_pager_links` — the legacy `paginas()` loop was re-implemented inside a throwaway script and compared for `limit ∈ {1,8,…,57} × total ∈ {0,37,…,1185} × offset ∈ {0,53,…}` → `PARITY OK` (0 mismatches).
- **Pure functions created**: 8 (`lib/tpvmod_listados.php`).

## Verification (U10 gate) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 167 ( 37%)
............................................................... 126 / 167 ( 75%)
.........................................                       167 / 167 (100%)

Time: 00:00.041, Memory: 8.00 MB

OK (167 tests, 859 assertions)
```

### 2. `ddev exec composer phpstan`

```
Note: Using configuration file /var/www/html/phpstan.neon.
   0/209 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]   0%[1G[2K 209/209 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ------ -----------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
 ------ -----------------------------------------------------------------------
  308    Method
         Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates()
         should return array{success: bool, changes: list<string>, errors: lis
         t<string>} but returns array{success: true, errors: array{}}.
         🪪  return.type
         💡  Array does not have offset 'changes'.
 ------ -----------------------------------------------------------------------


 [ERROR] Found 1 error
```

**Interpreting this**: the single error is **pre-existing and byte-identical to the
`master` baseline** captured before PR1 (same file, same line, same rule). PHPStan
analyses `paths: [src, tests]` only — `plugins/tpvmod/**` is not analysed at all, so
PR1 cannot introduce a PHPStan error. Gate reading: **no new errors.**

### 3. PR1-scoped grep audit (all pass)

| Check | Result |
|---|---|
| `hx-params` anywhere in the plugin (`controller/ lib/ view/ tests/ Init.php`) | **none** (PR1 adds none; PR2 owns the `hx-*` surface) |
| `no_html(` in `controller/tpvmod_{presupuestos,facturas,albaranes,pedidos}.php` | **0 occurrences in all four files** |
| `is_htmx_request` / `tpvmod_is_htmx_request` in plugin source | **none** |
| `tpvmod_cliente_ajax_dispatch` in the four listing controllers | **present, 1× each** (retained, AD-8 §8c) |
| `cron_job(` in facturas / albaranes | **absent** (no gate added, as required) |
| `view/` files in the PR1 diff | **0** |
| `php -l` on the 4 controllers + the test file | **no syntax errors** |

## Runtime harness evidence (DB-backed CLI, not committed)

A throwaway harness (deleted after the run) bootstrapped the real `config.php` +
`fs_db2` and exercised the shared helpers end to end:

```
PASS  db connect
PASS  predicate executes on presupuestoscli for '', a, O'Brien, <script>, acme
PASS  predicate executes on facturascli for '', a, O'Brien, <script>, acme
PASS  predicate executes on albaranescli for '', a, O'Brien, <script>, acme
PASS  predicate executes on pedidoscli for '', a, O'Brien, <script>, acme
PASS  normalize ISO
PASS  normalize d-m-Y -> ISO
PASS  normalize rejects impossible date
PASS  normalized ISO date range predicate executes
PASS  legacy d-m-Y var2str already normalizes (non-bug confirmed)
PASS  phone map issued exactly one batched query for 6 requested codes
PASS  phone map covers the deduplicated code set
PASS  phone map issues zero queries for an empty set
PASS  non-htmx load -> cron_job() runs
PASS  htmx swap -> cron_job() skipped
PASS  linea_factura_cliente::search accepts the offset argument
PASS  linea_factura_cliente::search_from_cliente2 accepts the offset argument
  url sample: index.php?page=tpvmod_presupuestos&mostrar=buscar&query=O%27Brien&codagente=EMP1&desde=2026-01-01&order=fecha_desc&offset=0
PASS  listing URL appends with & after ?page=
PASS  listing URL percent-encodes a quote-bearing term
PASS  listing URL carries order and offset
PASS  listing URL drops empty filters
U10 HARNESS OK
```

Two independent harnesses were also run: the pager parity oracle (`PARITY OK`) and the
`isHtmxRequest()` contract check (`U8 HTMX GATE HARNESS OK`).

**Not covered by the harness** (delegated to `sdd-verify`): the authenticated browser
smoke from `config.yaml` ("abrir tpvmod como agente con permisos, elegir no terminal /
continuar sin terminal, generar tickets, cerrar caja, reimprimir"). PR1 is
UI-behavior-preserving and adds no template change, but a real page load is the only
way to observe `cron_job()`'s DB writes directly.

## Decisions taken

| # | Decision | Why |
|---|---|---|
| A1 | `list_params()` casts `query` with `(string) $this->query` | The design snippet passes `$this->query` raw, but `fs_controller::pre_private_core()` sets it via `fs_filter_input_req('query')`, whose default is `false`. `http_build_query` would encode `false` as `query=0`, so pagination would silently filter by `0`. The cast keeps `tpvmod_build_list_url()`'s documented rule ("drops only `null`/`''`, keeps `0`/`'0'`") intact instead of extending it with a `false` case. |
| A2 | `tpvmod_build_list_url()` returns the base URL untouched when every parameter is dropped | Avoids a dangling `index.php?page=x&`. Unreachable in production (`mostrar`/`order`/`offset` are always kept), so it is a pure hygiene decision, pinned by a test. |
| A3 | `tpvmod_pager_links()` returns `[]` for `limit < 1` | The legacy loop has no guard and would spin forever. Behaviour for `limit >= 1` is unchanged (verified with the parity oracle). |
| A4 | `telefonos_pagina()`/`$telefonos_map` are `private`; `telefono_cliente()` is the public seam | LHT-06 asks for a public accessor; the map itself is an implementation detail. No `__get`/`__set` exists on `fs_controller`, so there is no magic-property interaction. |
| A5 | Translated the legacy Spanish inline comment inside the moved pager loop to English | The new `lib/` file is a new artifact and defaults to English per the artifact language contract; the executable logic is unchanged. Legacy Spanish comments in the controllers were left verbatim. |
| A6 | `TpvmodTwigTemplatesTest::pluginSourceFiles()` skips `tests/`, `vendor/`, `.git/` and `openspec/` | The scan asserts "no local HX-detection helper in production source". The test file itself and the openspec prose contain the literal `is_htmx_request`, so they must be excluded or the assertion would be self-defeating. |

## Deviations from design

| # | Design said | Implemented | Verdict |
|---|---|---|---|
| D-1 | `list_params()` passes `$this->query` verbatim | `(string) $this->query` | **Accepted deviation** (A1); required for correctness, documented for the verifier |
| D-2 | "PR1 ... URLs ... stay identical"; U10 smoke says "pagination/filter URLs are byte-identical to the pre-change output" | The pagination URL string **differs**: empty params are dropped and `order=` is now carried explicitly | **Accepted deviation, needs a verify-phase reconciliation.** This is design §1.5's own `list_params()` (it includes `order`), plus LHT-11's mandate to build URLs with `http_build_query` (which necessarily drops/encodes). Same state → same result set and same offsets; only the query-string serialisation changes. The literal "byte-identical" wording in tasks.md U10 is over-stated (annotated inline in tasks.md). |
| D-3 | Legacy `paginas()` URL omitted `order` (state came from a cookie) | `order` is now in the URL | **Improvement, deliberate**: required for shareable/pushable URLs in PR2; also removes cookie-dependence for pagination |
| D-4 | `tpvmod_build_search_predicate()` gate keyed on `$this->query != ''` | Gate keyed on `$predicate !== ''` (i.e. after trim) | A whitespace-only query no longer builds a `%   %` predicate. Negligible and strictly better; no test pins the old behaviour |
| D-5 | `is_numeric()` branch removed as redundant (design §1.3) | Removed as designed | As designed (R1 satisfied: `lower(col)` is case-invariant for digits) |
| D-6 | `desde`/`hasta` read as `$_REQUEST['desde']` | `tpvmod_normalize_date($_REQUEST['desde'] ?? '')` | Added `?? ''` to avoid an undefined-index warning when only `desde` is present. `null` and `''` behaved identically before (`null != ''` is false), so no behaviour change |
| D-7 | Presupuestos' `desde` block contains a stray `$this->codserie = $_REQUEST['codserie'];` | Kept verbatim | Deliberate: removing it would be an out-of-scope behaviour fix. Flagged here for a future SDD |

## Blockers / risks

| # | Item | Impact |
|---|---|---|
| B-1 | **PR1 exceeds the 800-line review budget.** Authored `additions + deletions` (controllers + lib + tests, excluding the tasks.md prose): **1434** (`+1156 / -344` over the whole branch incl. prose; `+810` net for code+tests). The tasks forecast was ~450–600. | The churn is inflated by mechanical replacement: the four `paginas()` rewrites (`+~95/-~78` each ≈ 690) and the four `buscar()` text blocks (≈ 200) are code moved into the shared helpers, not net new logic. Net code+tests is `+810`, essentially the forecast net. One honest slicing pass (already performed in `tasks.md`) does not produce a sub-800 cohesive split: helpers-only is 550, controllers-only is 884. **Recommendation: `size:exception` for PR1, or re-slice as PR1a = helpers + helper tests (550) / PR1b = controllers + contract tests (884).** No code was shrunk to chase the number. |
| B-2 | Pre-existing `phpstan` error at `tests/Core/PluginEnableAjaxSafetyTest.php:308` | Not introduced by PR1; unrelated to the plugin (PHPStan does not analyse `plugins/`). Do not fix inside this change. |
| B-3 | The authenticated browser smoke is unexecuted | Delegated to `sdd-verify` — it needs an authenticated agent session. The DB-backed harness covers the assertable parts (gate semantics, predicate execution, batched lookup, URL shape). |
| B-4 | `is_htmx_request` scan excludes `openspec/` and `tests/` | If a future commit adds a local helper under an excluded path it would not be caught; the scan is scoped to production source by design. |

## Remaining tasks

- [ ] U10 manual smoke (authenticated browser / DB-log observation) → `verify-report.md` (verify phase).
- [ ] U11–U15 (PR2 — htmx/Alpine + fragments).
- [ ] U16–U20 (PR3 — columns, reorder, picker removal, native dates).
- [ ] PR creation + delivery decision (human-owned; branch is local and unpublished).

## Workload / PR boundary

| Field | Value |
|---|---|
| Mode | Chained PR slice — **PR1 of 3**, `stacked-to-main` |
| Chain | `main → 📍 PR1 helpers + controller hardening → PR2 htmx/Alpine + fragments → PR3 columns/reorder/picker/dates` |
| Current work unit | PR1 — shared helpers + controller hardening (U1–U10) |
| Boundary (starts from) | `master` @ `8d84703` |
| Boundary (ends with) | U10 gate passed: plugin suite green + no new `phpstan` errors; no template touched; branch `feat/tpvmod-listados-htmx-pr1` |
| Reviewer note | Read the four controller diffs as *moves*: the legacy `paginas()` loop lives byte-for-byte in `tpvmod_pager_links()` (parity proven), and the legacy `buscar()` text block collapsed into two helper calls |
| Review budget | Authored 1434 (code+tests) vs an 800 budget → **over**; see B-1 for the `size:exception` recommendation |

## Status

**10/10 PR1 units complete** (U1–U9 implemented; U10 gate passed, its browser smoke
delegated to verify). **Ready for `sdd-verify`** on the PR1 slice. PR2 must not start
until this slice is verified/merged, because it depends on the helpers and the frozen
controllers.

---

# PR2 batch (U11–U15) — apply-progress

> Branch: `feat/tpvmod-listados-htmx-pr2`, created from the PR1 tip
> `c490800` (stacked-to-main). Nested `plugins/tpvmod` repo only; the core repo
> is untouched. Not pushed; no PR opened.

## Status

| Field | Value |
|---|---|
| Mode | **Strict TDD** (`strict_tdd: true`) |
| Batch | PR2 — htmx/Alpine on the 4 listings + line-search fragments (U11–U15) |
| Branch | `feat/tpvmod-listados-htmx-pr2` @ `37ac3ee` (from PR1 tip `c490800`) |
| Units completed | **U11–U13** (`61bfae1`, `7843685`, `37ac3ee`) |
| Units withheld | **U14, U15 — not started** (budget guard, see escalation) |
| Baseline before edits (U12–U13 attempt) | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → `OK (169 tests, 907 assertions)` at `e4f6bec` |
| After U12 | same command → `OK (171 tests, 1115 assertions)` (U12-only index state) |
| After U13 | same command → `OK (172 tests, 1167 assertions)` |
| `phpstan` after U13 | **no new errors** (1 pre-existing, identical to baseline: `tests/Core/PluginEnableAjaxSafetyTest.php:308`) |
| Twig compile harness | all 4 listings + 4 fragments compile → `TWIG LINT OK` |
| U12+U13 attempt size | **507 changed lines** (`+433 / −74`, measured `e4f6bec..37ac3ee`) — within the 800 budget |

## Units

| Unit | Goal | Status |
|---|---|---|
| U11 | htmx/Alpine opt-in boot + stable region scaffold (LHT-01/LHT-02) | ✅ `61bfae1` |
| U12 | Control → `hx-*` mapping, filter form GET, top-bar split (LHT-01/03/04) | ✅ `7843685` |
| U13 | htmx line-search form + legacy JS/`$.ajax` removal (LHT-10, TCP-01) | ✅ `37ac3ee` |
| U14 | Line-search fragments: marker removed, offset pager, well-formed alerts | ⛔ not started (withheld) |
| U15 | PR2 gate (suite + phpstan + per-module smoke) | ⛔ not started (withheld) |

## Files changed (U11)

| File | Action | What was done | Lines (authored) |
|---|---|---|---|
| `view/tpvmod_presupuestos.html.twig` | modified | 2 macro imports + `htmx.boot({'allowScriptTags': false})`; region `#tpvmod-presupuestos-region` wraps toolbar+tabs+table+pagination; order dropdown moved into the region toolbar; `b_buscar_lineas` opens the modal via `data-toggle`; filter form moved below the region; inline `buscar_lineas()`/`mas_resultados()` + jQuery bindings deleted; Alpine registration + single `htmx:after:swap` listener | +167 / −161 |
| `view/tpvmod_facturas.html.twig` | modified | same, region `#tpvmod-facturas-region`, facturas order tokens; `modal_huecos` kept outside | +174 / −168 |
| `view/tpvmod_albaranes.html.twig` | modified | same, region `#tpvmod-albaranes-region` | +157 / −151 |
| `view/tpvmod_pedidos.html.twig` | modified | same, region `#tpvmod-pedidos-region` | +165 / −151 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `LISTING_TEMPLATES` / `LINE_FRAGMENTS` consts, `regionId()` helper, `testListingsImportHtmxAndAlpineOnce`, `testListingsDeclareStableSwapRegion` | +76 |

`openspec/changes/tpvmod-listados-htmx/tasks.md` and this file are prose, not PR budget.

## Files changed (U12–U13)

| File | Action | What was done | Lines (authored) |
|---|---|---|---|
| `view/tpvmod_presupuestos.html.twig` | modified | U12: form `method="get"` (no CSRF) + `hx-get`/`hx-target`/`hx-select`/`hx-swap`/`hx-push-url`/`hx-trigger="submit"` + hidden `mostrar`/`order`; serie/codagente/desde/hasta each carry `hx-get="{{ fsc.list_url({'mostrar':'buscar','offset':0}, [own]) }}"` + `hx-trigger="change"`; 4 tabs + 4 order options + pager mapped with `fsc.list_url(...)`/`value['url']`. U13: `#f_buscar_lineas` + 3 inputs get `hx-post`/`hx-target="#search_results"`/`hx-swap="innerHTML"`/`hx-trigger`(delay)/`hx-sync` | +70 / −19 |
| `view/tpvmod_facturas.html.twig` | modified | same, facturas region/tabs (`todo`/`sinpagar`/`buscar`) and `vencimiento_*` order tokens; **adds the missing hidden `offset`** to the line-search form for parity | +67 / −18 |
| `view/tpvmod_albaranes.html.twig` | modified | same, albaranes region/tabs (`todo`/`pendientes`/`buscar`) and `codigo_*` order tokens | +66 / −18 |
| `view/tpvmod_pedidos.html.twig` | modified | same, pedidos region/tabs (`todo`/`pendientes`/`rechazados`/`buscar`) and `codigo_*` order tokens | +70 / −19 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `MODULE_TABS`/`MODULE_ORDER_TOKENS` consts; `testControlToUrlMapping`, `testRegionContainsMappedControls`, `testLineSearchFragmentContract`; `testListingsDeclareStableSwapRegion` extended with the hx-target/select/swap/push count equality | +160 |

No controller, `lib/`, fragment or Composer dependency was touched in this attempt.

## TDD Cycle Evidence (Hard Gate — Strict TDD)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U11 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ 167/167 | ✅ Written; run failed (2 tests: import + region) | ✅ Passed (169, 907 assertions) | ✅ all 4 templates: exactly-one imports, boot posture, both markers, single `'htmx:after:swap'`, no `HtmxCrud.html.twig`, one region id each, `x-data="tpvmodListado"`, theme header/footer free of htmx/Alpine | ✅ one component name for all four; no list state in Alpine; Twig compile harness run over all 8 templates |
| U12 | same | Contract (source, DB-free) | ✅ 169/169 @ `e4f6bec` | ✅ Written; run failed (3 tests: region control count, `testControlToUrlMapping`, `testLineSearchFragmentContract`) | ✅ Passed (171, 1115 assertions) at the U12-only index state | ✅ 4 modules: every filter own-key omission, per-module tab set, per-module order tokens, pager `value['url']`, repo-wide `hx-params` guard, region boundary/after-region modals, `hx-target`==`hx-select`==`hx-swap`==`hx-push-url` counts | ✅ filter form kept below the region (U17 owns the reorder); one URL builder feeds `href` and `hx-get` |
| U13 | same | Contract (source, DB-free) | ✅ 171/171 (U12 commit) | ✅ Written; run failed (`hx-post="{{ fsc.url() }}"` absent) | ✅ Passed (172, 1167 assertions) | ✅ 4 modules: form + both inputs, debounce + both `hx-sync` values, `method="post"`+CSRF fallback retained, hidden `offset` (facturas added), no `$.ajax`/`mas_resultados(`/inline `buscar_lineas()` | ✅ no `hx-include`/`hx-params`; no `beforeend` append |

### Work Unit Evidence (Hard Gate — all modes)

| Unit | Focused test command + exact result | Runtime harness command/scenario + exact result | Rollback boundary |
|---|---|---|---|
| U11 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → `OK (169 tests, 907 assertions)` | Throwaway Twig compile harness (`_twig_lint_throwaway.php`, deleted after the run): `$twig->load()` on the 4 listings + 4 fragments → `TWIG LINT OK`. Full authenticated browser smoke still needs an agent session → delegated to `sdd-verify` | Revert the 4 listing templates only (single commit `61bfae1`); controllers and fragments untouched |
| U12 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest` → `OK (23 tests, 687 assertions)`; full suite at the U12-only index state `OK (171 tests, 1115 assertions)` | Throwaway Twig compile harness over the 4 listings + 4 fragments → `TWIG LINT OK`. Browser smoke of tabs/order/pager/filters (JS-disabled fallback, pushed URL reload) needs an agent session → delegated to `sdd-verify` | Revert the 4 listing templates' `hx-*`/GET-form hunks (commit `7843685`); the line-search form and the fragments are untouched by U12 |
| U13 | `… --filter TpvmodTwigTemplatesTest` → `OK (23 tests, 687 assertions)`; full suite `OK (172 tests, 1167 assertions)` | Twig compile harness re-run → `TWIG LINT OK`. Live line-search debounce/pager/CSRF rejection needs an agent session → delegated to `sdd-verify` | Revert the `#f_buscar_lineas`/input `hx-post` hunks + the facturas hidden `offset` (commit `37ac3ee`); U12's region mapping is untouched |

## Test Summary

- **Tests added (U11)**: 2 (`testListingsImportHtmxAndAlpineOnce`, `testListingsDeclareStableSwapRegion`) + 2 consts + 1 helper. Suite 167 → 169.
- **Tests added (U12–U13)**: 3 methods (`testControlToUrlMapping`, `testRegionContainsMappedControls`, `testLineSearchFragmentContract`) + `MODULE_TABS`/`MODULE_ORDER_TOKENS` consts + the region count-equality extension. Suite 169 → 172.
- **Layers used**: Contract/source (DB-free) 5, Twig-compile harness 1 (not committed), Unit 0, E2E 0.
- **Pure functions created**: 0 (templates + test only).

## Verification (U12–U13 gate) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 172 ( 36%)
............................................................... 126 / 172 ( 73%)
..............................................                  172 / 172 (100%)

Time: 00:00.050, Memory: 8.00 MB

OK (172 tests, 1167 assertions)
```

### 2. `ddev exec composer phpstan`

```
Note: Using configuration file /var/www/html/phpstan.neon.
   0/209 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]   0%[1G[2K 209/209 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ------ -----------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
 ------ -----------------------------------------------------------------------
  308    Method
         Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates()
         should return array{success: bool, changes: list<string>, errors: lis
         t<string>} but returns array{success: true, errors: array{}}.
         🪪  return.type
         💡  Array does not have offset 'changes'.
 ------ -----------------------------------------------------------------------


 [ERROR] Found 1 error
```

**Interpreting this**: the single error is **pre-existing and byte-identical to the
baseline** (same file, same line, same rule). PHPStan analyses `paths: [src, tests]`
only — `plugins/tpvmod/**` is not analysed, so this change cannot introduce a PHPStan
error. Gate reading: **no new errors.**

### 3. U12–U13-scoped audit (all pass)

| Check | Result |
|---|---|
| `hx-params` anywhere in the plugin (`view/ controller/ lib/ tests/`) | **none** (test-enforced) |
| `hx-include` in the four listing templates | **none** |
| `onchange="this.form.submit()"` in the four listings | **none** (all four filter selects/date inputs are htmx-mapped) |
| `hx-target`/`hx-select`/`hx-swap="outerHTML"`/`hx-push-url="true"` occurrence counts | **equal** per template (LHT-01 control half) |
| `$.ajax` / `mas_resultados(` / `function buscar_lineas(` in the four listings | **none** (TCP-01) |
| Region boundary: the filter form (`name="f_custom_search"`) and the line-search form stay **outside/below** the region | **asserted** by `testRegionContainsMappedControls` |
| new `\|raw` on user-controlled output in the diff | **none** (only pre-existing `fsc.url()\|raw` / server-built pager `value['url']\|raw`) |
| Twig compile: 4 listings + 4 fragments | `TWIG LINT OK` |
| `php -l` on the extended test file | no syntax errors |

## PR2 budget tracking (U11 over budget; U12–U13 within)

The prompt's budget guard fired at U11: **PR2 must fit in 800 changed lines, and U11 alone
is 1370** (`+739 / −631`). That churn is *structural moves prescribed by the tasks*, not new
code: the filter form moves out of the region, the order dropdown moves from the top bar
into the region toolbar, the legacy line-search JS block is deleted, and the marker-guarded
Alpine registration is added. No comment, blank line or test was deleted and no code was
compressed to chase the number.

**U12–U13 landed at 507 changed lines** (`+433 / −74` = test `+160` + four templates
`+273 / −74`, measured `e4f6bec..37ac3ee`) — **within the 800 budget**. The estimate in the
U11 escalation (`U12 + U13 ≈ +370–510` plus tests) was therefore accurate.

Remaining projection (not implemented, approximate): U14 fragment pager/alerts/pager ≈
`+120–200`, U15 gate ≈ `+0`, plus ≈ `+40–80` of tests → **remaining PR2 ≈ +160–280**. With
U11's 1370, **PR2 total ≈ 2040–2160**, i.e. ~2.5–2.7× the 800 budget. The overage is still
concentrated in U11's structural moves.

The maintainer's decision is still required before U14–U15 resume:

1. **`size:exception` for PR2** (recommended): accept PR2 as one slice; reviewers read the
   four template diffs as moves (the tabs/table/pagination markup is byte-identical; only
   the wrapper, the toolbar move and the form move change).
2. **Orchestrated re-slice**: e.g. PR2a = region + boot (U11, 1370) / PR2b = `hx-*` mapping +
   line search + fragments + gate (U12–U15). "PR2a" alone still exceeds 800, so a re-slice
   is not a clean fix either — the structural churn is inherent to the change.

**Intermediate state resolved for the line search.** Between U11 and U13 the line-search
modal only had its native POST fallback; `37ac3ee` restores the transport via `hx-post`.
U14 still owns the fragment-side pager/marker/alerts, so the line-search **pager** is not
wired until U14.

## Status

**13/15 PR2-track units complete** (U11–U13 done; U1–U10 from PR1 unchanged). **U14–U15 remain
withheld** by the review-budget guard: `size:exception` or a re-slice decision is required
before this batch resumes. `phpstan`: no new errors. Suite green at `37ac3ee`
(`OK (172 tests, 1167 assertions)`).

> **Superseded by the U14–U15 section below.** The withheld U14–U15 units were resumed and
> applied on the same branch; the paragraph above is kept for the log history only.

---

# PR2 batch (U14–U15) — apply-progress

> Continues the PR2 batch on the same branch `feat/tpvmod-listados-htmx-pr2`, from the
> prior tip `5b55529`. Nested `plugins/tpvmod` repo only; the core repo is untouched.
> Not pushed; no PR opened. PR2 is now **complete** (U11–U15).

## Status

| Field | Value |
|---|---|
| Mode | **Strict TDD** (`strict_tdd: true`) |
| Batch | PR2 — U14 (line-search fragments) + U15 (gate) |
| Units completed | **U14–U15** (`66b6cb1`, plus this docs commit) |
| Branch | `feat/tpvmod-listados-htmx-pr2`; base tip before this slice `5b55529` |
| Slice size | **101 changed lines** (`+83 / −18`, measured `5b55529..66b6cb1`) — within the 800-line budget |
| Final plugin suite | `OK (172 tests, 1215 assertions)` |
| `phpstan` | **No new errors** (1 pre-existing, byte-identical to the baseline) |
| Twig compile harness | the 4 fragments → `TWIG LINT OK` |
| Render harness | `U14 RENDER HARNESS OK` (8/8) |
| Authenticated browser smoke | **delegated to `sdd-verify`** (needs an agent session) |

## Units

| Unit | Goal | Status |
|---|---|---|
| U14 | Line-search fragments: marker removed, server-computed offset pager, well-formed alerts; facturas gains the pager | ✅ `66b6cb1` |
| U15 | PR2 gate: plugin suite + `phpstan` + per-module smoke | ✅ suite + `phpstan` + static/harness evidence; authenticated browser smoke delegated to `sdd-verify` |

## Files changed (U14)

| File | Action | What was done | Lines |
|---|---|---|---|
| `view/ajax/ventas_lineas_presupuestos.html.twig` | modified | marker removed; alerts `<li>`→`<div>`; pager `onclick="mas_resultados(±N)"` → `hx-post` + `hx-vals` offset | +10 / −5 |
| `view/ajax/ventas_lineas_albaranes.html.twig` | modified | same | +10 / −5 |
| `view/ajax/ventas_lineas_pedidos.html.twig` | modified | same | +10 / −5 |
| `view/ajax/ventas_lineas_facturas.html.twig` | modified | same **plus the missing pager added** | +27 / −3 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `testLineSearchFragmentContract` extended with the fragment half | +26 |

No controller, `lib/`, listing template or Composer dependency was touched. The legacy fragment
contract is intact: `$this->template = 'ajax/ventas_lineas_<tipo>'`, the same endpoint
(`hx-post="{{ fsc.url() }}"`) and the same params (`buscar_lineas`, `buscar_lineas_o`,
`codcliente`, `offset`). `search_from_cliente2` by `codcliente` untouched. No `hx-params`.

## TDD Cycle Evidence (Hard Gate — Strict TDD)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U14 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ 172/172 @ `5b55529` | ✅ Written; run failed (marker present in all four fragments) | ✅ Passed (`--filter testLineSearchFragmentContract` → `OK (1 test, 100 assertions)`; full suite `OK (172 tests, 1215 assertions)`) | ✅ 8/8 render harness cases across 3 modules: offset 0, offset 24, partial last page; facturas included; alerts `<div>`; marker absent | ✅ dropped the client arithmetic for the design §5.3 `hx-vals` shape; no `beforeend` append |

### Work Unit Evidence (Hard Gate — all modes)

| Unit | Focused test command + exact result | Runtime harness command/scenario + exact result | Rollback boundary |
|---|---|---|---|
| U14 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter testLineSearchFragmentContract` → `OK (1 test, 100 assertions)`; full suite `OK (172 tests, 1215 assertions)` | Throwaway Twig harnesses (deleted after the run): compile of the 4 fragments → `TWIG LINT OK`; render with a stub `fsc` → `U14 RENDER HARNESS OK` (`offset 0 -> next 8 / no prev`; `offset 24 -> prev 16 / next 32`; `partial page -> prev 1 / no next`; `alerts <div>`; `marker absent`) | Revert the 4 `view/ajax/ventas_lineas_*.html.twig` plus the `testLineSearchFragmentContract` extension (commit `66b6cb1`); controllers and listings untouched by U14 |
| U15 | (gate) full suite `OK (172 tests, 1215 assertions)` | `ddev exec composer phpstan` → 1 pre-existing error only; static audit: `hx-params`/`hx-include`/`is_htmx_request`/`$.ajax`/`mas_resultados(`/marker → 0 in production source; `clickableRow` 1×/listing; `tpvmod_cliente_ajax_dispatch` 1×/controller | N/A (verification only) |

## Test Summary

- **Tests added**: 0 new methods; `testLineSearchFragmentContract` extended by 1 fragment loop (+48 assertions). Suite stays at 172 tests.
- **Total tests passing**: 172 (1215 assertions), started at 172 (1167 assertions).
- **Layers used**: Contract/source (DB-free) 1 extended method, Twig compile harness 1 (not committed), Twig render harness 1 (not committed), Unit 0, E2E 0.
- **Pure functions created**: 0.

## Verification (U15 gate) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 172 ( 36%)
............................................................... 126 / 172 ( 73%)
..............................................                  172 / 172 (100%)

Time: 00:00.045, Memory: 8.00 MB

OK (172 tests, 1215 assertions)
```

### 2. `ddev exec composer phpstan`

```
Note: Using configuration file /var/www/html/phpstan.neon.
   0/209 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]   0%[1G[2K 209/209 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 ------ -----------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
 ------ -----------------------------------------------------------------------
  308    Method
         Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates()
         should return array{success: bool, changes: list<string>, errors: lis
         t<string>} but returns array{success: true, errors: array{}}.
         🪪  return.type
         💡  Array does not have offset 'changes'.
 ------ -----------------------------------------------------------------------


 [ERROR] Found 1 error
```

**Interpreting this**: the single error is pre-existing and byte-identical to the baseline
(same file, same line, same rule). PHPStan analyses `paths: [src, tests]` only, so
`plugins/tpvmod/**` cannot introduce an error. Gate reading: **no new errors.**

### 3. Twig compile + render harness (throwaway, deleted)

```
PASS compile ajax/ventas_lineas_presupuestos.html.twig
PASS compile ajax/ventas_lineas_facturas.html.twig
PASS compile ajax/ventas_lineas_albaranes.html.twig
PASS compile ajax/ventas_lineas_pedidos.html.twig
TWIG LINT OK

PASS offset 0 -> no previous link
PASS offset 0 -> next posts offset 8
PASS offset 24 -> previous posts offset 16
PASS offset 24 -> next posts offset 32
PASS partial page -> previous posts offset 1
PASS partial page -> no next link
PASS alerts render as div, not li
PASS marker absent
U14 RENDER HARNESS OK
```

### 4. U14–U15-scoped audit (all pass)

| Check | Result |
|---|---|
| `hx-params` in production source (`view/ controller/ lib/ Init.php`) | **0** (only in the test's guard) |
| `hx-include` in production source | **0** |
| `mas_resultados(` in `view/` | **0** |
| `<!--{{ fsc.buscar_lineas }}-->` in `view/ajax/` | **0** |
| `$.ajax` in the four listings | **0** |
| `is_htmx_request` in production source | **0** |
| `tpvmod_cliente_ajax_dispatch` in the 4 listing controllers | **1× each** (retained, AD-8 §8c) |
| `clickableRow` in the four listings | **1× each** (G2 preserved) |
| `data-toggle` modals in the four listings | 2–3× each (Bootstrap delegated) |
| `fsc.paginas()` in the four listings | **1× each**, inside the region |

## Smoke per module — status

The **authenticated browser smoke cannot run in this apply phase** (it needs an agent session
with permissions; see `config.yaml` `testing.smoke`). Delegated to `sdd-verify`:

- tabs/order/pagination/filters swap the region and push the URL;
- JS-disabled navigation returns the full page;
- Alpine re-inits exactly once after a swap (no duplicate-registration warning);
- delegated `tr.clickableRow[href]` and Bootstrap `data-toggle` still work after a swap;
- line search debounces, pages prev/next and rejects an invalid token;
- `cron_job()` does not run on swaps (DB/log observation).

The parts assertable without a session are covered by the harnesses and the audit above.

## Status

**15/15 PR2-track units complete** (U11–U15 done; U1–U10 from PR1 unchanged). Suite green
(`OK (172 tests, 1215 assertions)`); `phpstan` no new errors. **Ready for `sdd-verify`** on the
PR2 slice. U16+ (PR3) remains out of scope for this execution.

---

# PR3 batch (U16–U20) — apply-progress

> Branch: `feat/tpvmod-listados-htmx-pr3`, stacked on the PR2 tip `9d64ce2`.
> Nested `plugins/tpvmod` repo only; the core repo is untouched. Not pushed; no
> PR opened.
>
> **Reconciliation note.** U16–U18 were committed (`c0fdc08`, `f0cb1a7`,
> `47c24dc`) without an `apply-progress` entry; this section records them from
> their commits and the passing tests. **U19–U20 were authored in this
> execution** (`4f5cedb` + this docs commit). All checkboxes in `tasks.md` are
> now reconciled; only the three authenticated-smoke items stay open for
> `sdd-verify`.

## Status

| Field | Value |
|---|---|
| Mode | **Strict TDD** (`strict_tdd: true`) |
| Batch | PR3 — columns, filter reorder, picker removal, native dates (U16–U20) |
| Units completed | **U16–U19** (`c0fdc08`, `f0cb1a7`, `47c24dc`, `4f5cedb`) + **U20 gate** |
| PR3 slice size | **1107 changed lines** (`9d64ce2..4f5cedb`: `+623 / −484`; U16–U18 = 1024, U19 = 99) — within the 2400-line session budget |
| Final plugin suite | `OK (176 tests, 1298 assertions)` |
| `phpstan` | **No new errors** (1 pre-existing, byte-identical to the baseline) |
| Twig compile harness | the 5 touched views → `TWIG LINT OK` |
| Render harness | `U19 RENDER HARNESS OK` (4/4) |
| Authenticated browser smoke | **delegated to `sdd-verify`** (needs an agent session) |

## Units

| Unit | Goal | Status |
|---|---|---|
| U16 | Phone + billing-city columns (LHT-06/LHT-07) | ✅ `c0fdc08` |
| U17 | Filter form reorder above the tabs/region (LHT-03) | ✅ `f0cb1a7` |
| U18 | Client picker removal + read-only client + clear control (LHT-08) | ✅ `47c24dc` |
| U19 | Native date inputs — all 10 (LHT-09) | ✅ `4f5cedb` |
| U20 | PR3 gate: full suite + `phpstan` + full smoke | ✅ suite/`phpstan`/audit; browser smoke delegated |

## U16–U18 (pre-existing commits, reconciled)

| Unit | What landed | Tests pinned |
|---|---|---|
| U16 | A phone `<td>` rendering `fsc.telefono_cliente(value.codcliente)` (one batched lookup per page from U7) and a `<td>` for the billing-city snapshot `{{ value.ciudad }}`; no `dirclientes`/`domfacturacion` lookup in the listing path | `testListingsRenderPhoneColumn`, `testListingsRenderCitySnapshot` |
| U17 | `#f_custom_search` moved **above** the region/tabs so the final byte order is form → region (toolbar, tabs, table, pager) → modals | `testRegionBoundaryAndOrder` |
| U18 | The `ac_cliente` picker, its button, the modal include and the `tpvmod-cliente.js` load are gone from the four listings; the active client renders as read-only text (`#tpvmod-cliente-activo`) with a clear control carrying `fsc.list_url({'codcliente': '', 'mostrar': 'buscar', 'offset': 0})`; `clean_cliente()` deleted; the four controllers **keep** `tpvmod_cliente_ajax_dispatch`; the modal partial and `tpvmod-cliente.js` stay for `tpvmod2`/`tpvmodedita` | `testListingViewsExcludeClientPicker`, narrowed `testViewsNoLongerUseClienteAutocomplete`, unchanged `testControllersDropCsrfWorkaround` |

## U19 — Native date inputs (all 10)

| File | Action | What was done | Lines |
|---|---|---|---|
| `view/tpvmod_presupuestos.html.twig` | modified | `desde`/`hasta` → `type="date"` + `fsc.{desde,hasta}|date_iso`, `class="datepicker"` removed; Rechazar → `type="date"` + `'now'|date('Y-m-d')` | +3 / −3 |
| `view/tpvmod_facturas.html.twig` | modified | same `desde`/`hasta` conversion | +2 / −2 |
| `view/tpvmod_albaranes.html.twig` | modified | same | +2 / −2 |
| `view/tpvmod_pedidos.html.twig` | modified | same | +2 / −2 |
| `view/tpvmodedita.html.twig` | modified | `fecha` → `type="date"` + `fsc.documento.fecha|date_iso` | +1 / −1 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `testNoDatepickerAndNativeDates` + `inputTagFor()` helper | +79 |

No new Twig filter: `date_iso` is the core filter registered at
`src/Core/Html.php:239`. No controller, `lib/` or fragment was touched.

## TDD Cycle Evidence (Hard Gate — Strict TDD)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U19 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ 175/175 (post-U16–U18 tip `47c24dc`) | ✅ Written; run failed (`tpvmod_presupuestos.html.twig` still contains `datepicker`) | ✅ Passed: `--filter testNoDatepickerAndNativeDates` → `OK (1 test, 39 assertions)`; full suite `OK (176 tests, 1298 assertions)` | ✅ five views: zero `datepicker`; exactly 10 `type="date"` (4×2 + Rechazar + fecha); per-input `desde`/`hasta` `date_iso` prefill + retained `hx-trigger="change"`; Rechazar ISO today; `tpvmodedita` `date_iso`; controller `tpvmod_normalize_date(` in the four; `finoferta` ISO read | ✅ kept the `placeholder` attributes (native inputs ignore them, harmless); no logic change in `controller/tpvmod.php` |

### Work Unit Evidence (Hard Gate — all modes)

| Unit | Focused test command + exact result | Runtime harness command/scenario + exact result | Rollback boundary |
|---|---|---|---|
| U19 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter testNoDatepickerAndNativeDates` → `OK (1 test, 39 assertions)`; full suite `OK (176 tests, 1298 assertions)` | Throwaway harness (deleted after the run): Twig `load()` of the 5 views → `TWIG LINT OK`; ArrayLoader render with a stub `fsc` → `31-01-2026 → 2026-01-31`, `'' → ''`, `5-1-2026 → 2026-01-05`, `now → today ISO` → `U19 RENDER HARNESS OK` | Revert the five views plus the one test method (`4f5cedb`); controllers, `lib/`, fragments untouched |

## Test Summary

- **Tests added**: 1 method + 1 private helper (`testNoDatepickerAndNativeDates`, `inputTagFor()`). Suite 175 → 176 (the U16–U18 methods were already present).
- **Total tests passing**: 176 (1298 assertions), started at 175 at the PR3 tip before this slice.
- **Layers used**: Contract/source (DB-free) 1, Twig compile harness 1 (not committed), Twig render harness 1 (not committed), Unit 0, E2E 0.
- **Pure functions created**: 0.

## Verification (U20 gate) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 176 ( 35%)
............................................................... 126 / 176 ( 71%)
..................................................              176 / 176 (100%)

Time: 00:00.057, Memory: 8.00 MB

OK (176 tests, 1298 assertions)
```

### 2. `ddev exec composer phpstan`

```
 ------ ---------------------------------------------------------------------------------------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
 ------ ---------------------------------------------------------------------------------------------------------------------------------------------------
  308    Method Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates() should return array{success: bool, changes: list<string>, errors: list<string>}
         but returns array{success: true, errors: array{}}.
         🪪  return.type
         💡  Array does not have offset 'changes'.
 ------ ---------------------------------------------------------------------------------------------------------------------------------------------------


 [ERROR] Found 1 error
```

**Interpreting this**: the single error is **pre-existing and byte-identical to the
baseline** (same file, same line, same rule). PHPStan analyses `paths: [src, tests]`
only, so `plugins/tpvmod/**` cannot introduce an error. Gate reading: **no new
errors.**

### 3. Twig compile + render harness (throwaway, deleted)

```
PASS compile tpvmod_presupuestos.html.twig
PASS compile tpvmod_facturas.html.twig
PASS compile tpvmod_albaranes.html.twig
PASS compile tpvmod_pedidos.html.twig
PASS compile tpvmodedita.html.twig
TWIG LINT OK
PASS desde d-m-Y -> ISO
PASS hasta empty stays empty
PASS documento fecha single digit -> ISO
PASS rechazar today ISO
U19 RENDER HARNESS OK
```

### 4. U20 grep audit (all pass)

| Check | Result |
|---|---|
| `hx-params` in production source (`view/ controller/ lib/ Init.php`) | **0** |
| `hx-include` in production source | **0** |
| `no_html(` in the four listing controllers | **0** |
| `is_htmx_request` / `tpvmod_is_htmx_request` in production source | **0** |
| `$.ajax` / `mas_resultados(` in the four listings | **0** |
| `HtmxCrud.html.twig` in `view/` | **0** |
| `datepicker` in `view/ controller/ lib/` | **0** |
| `tpvmod_cliente_ajax_dispatch` in the four listing controllers | **1× each** (retained, AD-8 §8c) |
| `htmx:after:swap` listener bound per listing | **exactly 1** (test-enforced; the second grep hit is the comment/reference, not a second binding) |

## Smoke — status

The **authenticated browser smoke cannot run in this apply phase** (it needs an
agent session with permissions; see `config.yaml` `testing.smoke`). Delegated to
`sdd-verify`:

- direct load = full page; pushed URL reloads identically; JS-disabled navigation;
- tabs/order/pagination/filters swap the region; Rechazar POST with CSRF;
- line search (typing, debounce, prev/next offset, client-scoped, invalid token);
- phone/ciudad columns; `&codcliente=` deep link + clear;
- the 10 native date inputs open the native picker and bind the range;
- no console errors; no duplicate queries; Alpine re-inits after swaps.

The parts assertable without a session are covered by the harnesses and the audit above.

## Decisions / deviations

| # | Decision | Why |
|---|---|---|
| U19-1 | Kept `placeholder="Desde"`/`"Hasta"` and `autocomplete="off"` on the native inputs | Harmless (browsers ignore them on `type="date"`) and it minimises the diff; no test depends on them |
| U19-2 | `|date_iso` on `fsc.desde`/`fsc.hasta`/`fsc.documento.fecha` is a no-op when the value is already ISO | The controller (`tpvmod_normalize_date`) and the model store ISO; `dateIsoValue()` passes ISO through unchanged and converts the model `d-m-Y` for `tpvmodedita`. It is the mandated core filter, not a new dependency |
| U19-3 | No logic change in `controller/tpvmod.php` (`finoferta`/document saves) | A native input yields `Y-m-d`; `strtotime('Y-m-d +30 days')` and the `date` column both accept it. Pinned by a regex assertion in the U19 test (record-only, per design §6) |
| U19-4 | Explicit non-bug statement | The range filter was never broken: `fs_db2::var2str()` normalizes `d-m-Y`. The motivation is dropping the jQuery UI `.datepicker` dependency (`legacy-init.js:26-33`), not a functional fix |

## Status

**20/20 units complete** (U1–U19 implemented; U20 gate passed, its browser smoke
delegated to verify). Suite green (`OK (176 tests, 1298 assertions)`); `phpstan`
no new errors; PR3 slice 1107 changed lines within the 2400 budget. **Ready for
`sdd-verify`** on the PR3 slice.

---

# PR3 follow-up — U21 (post-verify amendment) — apply-progress

> Continues the change after the authenticated browser smoke found the filter bar
> hidden on every non-`buscar` state. Branch: `master` (nested `plugins/tpvmod`
> repo; the executor was told NOT to create or switch branches). Markup-only: no
> controller, helper, schema or dependency change. The worktree carries unrelated
> human WIP (`lib/tpvmod_opcionales*.php`, `view/js/tpvmod.js`,
> `tests/TpvmodOpcional*`), left untouched and unstaged.

## Status

| Field | Value |
|---|---|
| Mode | **Strict TDD** (`strict_tdd: true`) |
| Unit | **U21** — always-visible filter bar (LHT-13, `views` delta) |
| Slice size | **85 changed lines** (`+77 / −8`: test `+77`, four templates `−2` each) — within the 800 budget |
| Baseline (Safety Net) | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → `OK (180 tests, 1309 assertions)` (includes unrelated human WIP tests) |
| Final plugin suite | `OK (181 tests, 1337 assertions)` |
| `phpstan` | **No new errors** (1 pre-existing, byte-identical: `tests/Core/PluginEnableAjaxSafetyTest.php:308`) |
| Twig compile harness | the 4 listings → `TWIG LINT OK` |
| Authenticated browser smoke | **delegated to `sdd-verify`** (needs an agent session) |

## Unit

| Unit | Goal | Status |
|---|---|---|
| U21 | Remove the `{% if fsc.mostrar == 'buscar' %}` guard that wraps `#f_custom_search` in the four listings, so the bar renders in every listing state | ✅ applied |

## Files changed (U21)

| File | Action | What was done | Lines |
|---|---|---|---|
| `view/tpvmod_presupuestos.html.twig` | modified | removed the form-block guard line + its matching `{% endif %}` | −2 |
| `view/tpvmod_facturas.html.twig` | modified | same | −2 |
| `view/tpvmod_albaranes.html.twig` | modified | same | −2 |
| `view/tpvmod_pedidos.html.twig` | modified | same | −2 |
| `tests/TpvmodTwigTemplatesTest.php` | modified | `testFilterBarRendersInEveryListingState` + `isInsideMostrarBuscarGuard()` structural helper | +77 |

No controller, `lib/`, fragment, theme or Composer dependency was touched. The
form keeps its `method="get"`, its `hx-get`/`hx-target`/`hx-select`/`hx-swap`/
`hx-push-url` and its hidden `mostrar`/`order` fields; the autofocus-script guard
and the tab `active`-class guard stay intact.

## TDD Cycle Evidence (Hard Gate — Strict TDD)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U21 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ 180/180 (worktree baseline, incl. human WIP) | ✅ Written; run failed (`tpvmod_presupuestos.html.twig must not wrap the filter form in a mostrar == buscar guard` — `Failed asserting that true is false`) | ✅ Passed: `--filter testFilterBarRendersInEveryListingState` → `OK (1 test, 28 assertions)`; full suite `OK (181 tests, 1337 assertions)` | ✅ 4 templates: structural no-open-buscar-guard at the form byte; form still precedes the region; exactly 2 remaining `{% if fsc.mostrar == 'buscar' %}` occurrences; autofocus script still behind its guard | ➖ None needed (guard removal only; no abstraction, no new filter) |

### Work Unit Evidence (Hard Gate — all modes)

| Unit | Focused test command + exact result | Runtime harness command/scenario + exact result | Rollback boundary |
|---|---|---|---|
| U21 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplatesTest` → green; full suite `OK (181 tests, 1337 assertions)` | Throwaway Twig compile harness (deleted after the run) over the 4 listings → `PASS compile …` ×4 → `TWIG LINT OK`. The browser render (form visible for `mostrar=todo`) needs an authenticated agent session → delegated to `sdd-verify` | Revert the four listing templates' guard hunks (the removed `{% if … %}` + `{% endif %}` pair) plus `testFilterBarRendersInEveryListingState` + `isInsideMostrarBuscarGuard()`; no other file depends on this change |

## Test Summary

- **Tests added**: 1 method + 1 private helper (`testFilterBarRendersInEveryListingState`, `isInsideMostrarBuscarGuard()`). Suite 180 → 181 (worktree baseline already included unrelated human WIP tests).
- **Total tests passing**: 181 (1337 assertions); started at 180 (1309 assertions).
- **Layers used**: Contract/source (DB-free) 1, Twig compile harness 1 (not committed), Unit 0, E2E 0.
- **Pure functions created**: 0.

## Verification (U21) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml

...............................................................  63 / 181 ( 34%)
............................................................... 126 / 181 ( 69%)
.......................................................         181 / 181 (100%)

Time: 00:00.056, Memory: 8.00 MB

OK (181 tests, 1337 assertions)
```

### 2. `ddev exec composer phpstan`

```
 ------ ----------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
 ------ ----------------------------------------------------------------------
  308    Method Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates()
         should return array{success: bool, changes: list<string>, errors:
         list<string>} but returns array{success: true, errors: array{}}.
         🪪  return.type
         💡  Array does not have offset 'changes'.
 ------ ----------------------------------------------------------------------


 [ERROR] Found 1 error
```

**Interpreting this**: the single error is pre-existing and byte-identical to the
baseline (same file, same line, same rule). PHPStan analyses `paths: [src, tests]`
only, so `plugins/tpvmod/**` cannot introduce an error. Gate reading: **no new
errors.**

### 3. Twig compile harness (throwaway, deleted)

```
PASS compile tpvmod_presupuestos.html.twig
PASS compile tpvmod_facturas.html.twig
PASS compile tpvmod_albaranes.html.twig
PASS compile tpvmod_pedidos.html.twig
TWIG LINT OK
```

## Decisions / deviations

| # | Decision | Why |
|---|---|---|
| U21-1 | The RED assertion is structural (an if/endif stack walker detecting an unclosed `fsc.mostrar == 'buscar'` condition at the form's byte offset), not only an occurrence count | An occurrence count is brittle (a new unrelated guard would drift it); the stack walker fails exactly when the form is genuinely inside the guard — the change under test |
| U21-2 | Kept a supplementary `assertSame(2, …)` on the exact guard string | Pins that only the form guard was removed and the two documented guards (autofocus script, `buscar` tab `active`) remain |
| U21-3 | No `hx-*`/field change to the form | The transport contract (LHT-04) is frozen and stays green in the full suite; U21 is markup-visibility only |
| U21-4 | Delegated the browser render to `sdd-verify` | It needs an authenticated agent session (`config.yaml` smoke flow); the compile harness plus the source contract prove the assertable half |

## Status

**21/21 units complete** (U1–U20 unchanged; U21 applied). Suite green
(`OK (181 tests, 1337 assertions)`); `phpstan` no new errors. **Ready for
`sdd-verify`** on the U21 amendment. Only the authenticated browser smoke items
stay delegated.

# PR3 follow-up — U22 (post-verify amendment) — apply-progress

> Appended after the U21 amendment. The authenticated smoke's second finding:
> the `#f_custom_search` bar lives outside the swapped region and is never
> re-rendered, so after a clear swap its label, hidden `codcliente` and
> non-text controls' `hx-get` stayed stale and the next filter change
> re-applied the cleared customer. View-layer only: no controller, helper,
> schema or dependency change. Supersedes nothing; merges on top of U21.

## Status

| Field | Value |
|---|---|
| Change | `tpvmod-listados-htmx` (plugin-local) |
| Unit | **U22** — post-swap filter-bar re-synchronization (LHT-14, `views` delta) |
| Mode | Strict TDD (`strict_tdd: true`) |
| State | ✅ applied |
| Next | `sdd-verify` (authenticated browser smoke stays delegated) |

## Unit

| Unit | Objective | Result |
|---|---|---|
| U22 | Extend the single existing `htmx:after:swap` listener with a plain-DOM `tpvmodResyncFilterBar()` that runs before/outside the Alpine guard and re-derives the bar from `location.search`: empty/fill `#tpvmod-cliente-activo` and the hidden `codcliente`, rebuild the `hx-get`/`href` of the non-text controls (serie, agente, dates, client-clear) by key-level `URLSearchParams` algebra over each control's own server `hx-get` prefix; never replace the text inputs | ✅ applied |

## Files changed (U22)

| File | Action | What |
|---|---|---|
| `tests/TpvmodTwigTemplatesTest.php` | Modified | +`testFilterBarResyncsAfterSwap` (contract over the 4 views) + `inlineScriptBlock()` helper |
| `view/tpvmod_presupuestos.html.twig` | Modified | re-sync script in the single listener; `data-tpvmod-role="cliente-clear"` hook on the clear anchor |
| `view/tpvmod_facturas.html.twig` | Modified | same |
| `view/tpvmod_albaranes.html.twig` | Modified | same |
| `view/tpvmod_pedidos.html.twig` | Modified | same |

## TDD Cycle Evidence (Hard Gate — Strict TDD)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|---|---|---|---|---|---|---|---|
| U22 | `TpvmodTwigTemplatesTest.php` | Contract (source, DB-free) | ✅ `OK (181 tests, 1337 assertions)` before edits | ✅ Written first; run failed `tpvmod_presupuestos.html.twig must call the filter-bar re-sync exactly once — Failed asserting that 0 is identical to 1` | ✅ Passed: `--filter testFilterBarResyncsAfterSwap` → `OK (1 test, 120 assertions)`; full suite `OK (182 tests, 1477 assertions)` | ✅ 4 template inputs × own-key/omit-key assertions (each control drops its own `name`; the clear control drops `codcliente` and never sets it empty) → `OK (1 test, 140 assertions)` | ✅ One shared implementation, byte-identical across the 4 listings (same Alpine seam, TCP-07); `node --check` clean; no `outerHTML`/`replaceWith`/`innerHTML` in the inline script |

### Work Unit Evidence (Hard Gate — all modes)

| Evidence | Value |
|---|---|
| Focused test command and result | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter testFilterBarResyncsAfterSwap` → `OK (1 test, 140 assertions)`; full suite `OK (182 tests, 1477 assertions)` (baseline `181/1337`) |
| Runtime harness (exact result) | Node 22 DOM-shim harness replaying the real extracted inline script: `HARNESS OK — 3 scenarios, resync algebra verified` — (1) clear: label+hidden emptied, all four control `hx-get` lose `codcliente`, clear keeps omitting it; (2) fill: hidden follows `codcliente`, label left untouched (server-owned name), control base reflects it; (3) `query` preserved via `URLSearchParams`, no text input touched. Harness kept outside the repo (`/tmp/opencode/`) |
| Rollback boundary | Revert the 5 files above only: the 4 templates' `tpvmodResyncFilterBar` block + `data-tpvmod-role` attribute, and `testFilterBarResyncsAfterSwap`/`inlineScriptBlock()`. No controller, helper, schema or dependency depends on this change |

## Test Summary

- **Total tests written**: 1 (`testFilterBarResyncsAfterSwap`, 140 assertions over the 4 listings)
- **Total tests passing**: 182 / 182 (suite), 140 assertions for the new test
- **Layers used**: Contract/source (1); Runtime JS harness (1, throwaway)
- **Triangulation cases**: 4 template inputs × own-key/omit-key checks
- **Pure functions created**: 0 (view-layer JS)

## Verification (U22) — real command output

### 1. `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`

```
...............................................................  63 / 182 ( 34%)
............................................................... 126 / 182 ( 69%)
........................................................        182 / 182 (100%)
OK (182 tests, 1477 assertions)
```

### 2. `ddev exec composer phpstan`

```
 ------ ---------------------------------------------------------------------------------
  Line   tests/Core/PluginEnableAjaxSafetyTest.php
  308    Method Tests\Core\AjaxGuardTestPluginManager::applyPluginSchemaUpdates() ...
 [ERROR] Found 1 error
```

Identical to the pre-existing baseline error (core `tests/Core/PluginEnableAjaxSafetyTest.php:308`); **no new errors** introduced under `plugins/tpvmod/`.

### 3. Node DOM-shim harness (throwaway, deleted)

```
HARNESS OK — 3 scenarios, resync algebra verified
```

### 4. Slice size (measured)

`git diff --numstat`: `+376 / −4` = **380 changed lines** (test `+144`; four templates `+58 / −1` each). Within the 800-line budget.

## Decisions / deviations

| ID | Decision | Why |
|---|---|---|
| U22-1 | Reused each control's own server-rendered `hx-get` prefix (`split('?')`) and rebuilt the query with `URLSearchParams` | Keeps the canonical `list_url()` base/encoding and forbids raw `&key=value` concatenation (LHT-11); `location.search` already carries `page=…` |
| U22-2 | Added `data-tpvmod-role="cliente-clear"` to the clear anchor (the stable hook the `views` delta flagged as missing) | The clear control's `hx-get`/`href` must be rebuilt and it is not addressable by a stable selector otherwise; minimal markup change |
| U22-3 | Label is only **emptied** when the URL has no `codcliente`; a non-empty name is left untouched | The URL carries only the code; the human name has no client-side source (design §4.1). The `[+]` fill path is a native navigation that re-renders the whole bar server-side |
| U22-4 | Did **not** touch tabs/order/pagination or the hidden `mostrar`/`order` | They live inside the swapped region and are server-rendered; the dynamic clear show/hide and hidden `order` drift are AD-15/R12 residuals, explicitly out of U22 scope |
| U22-5 | Text inputs (`query`, `desde`, `hasta`) are never re-rendered; only attributes/values are assigned | Preserves value and focus (LHT-03/LHT-14) |

## Status

**22/22 units complete** (U1–U20 unchanged; U21 and U22 applied). Suite green
(`OK (182 tests, 1477 assertions)`); `phpstan` no new errors. **Ready for
`sdd-verify`** on the U22 amendment. Only the authenticated browser smoke items
stay delegated.

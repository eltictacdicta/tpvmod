# Apply Progress: tpvmod-listados-htmx

> **Plugin-local SDD.** Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Core `openspec/` is intentionally NOT touched.
> Artifact store: OpenSpec (plugin-local). Batches: **PR1 (U1–U10, complete)** and
> **PR2 (U11–U13 done; U14–U15 withheld by the review-budget guard — see the PR2
> section at the end)**.

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

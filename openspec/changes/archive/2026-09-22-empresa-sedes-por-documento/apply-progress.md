# Apply Progress: empresa-sedes-por-documento

> Cross-plugin SDD owned by `plugins/tpvmod/openspec/` (main beneficiary).
> Execution mode: **Strict TDD** (`plugins/tpvmod/openspec/config.yaml` →
> `strict_tdd: true`).
> This artifact covers **WU-1** (`plugins/business_data`, PR-1), its corrective
> follow-up commit that closed four coverage defects found by the independent
> verifier, **WU-2** (`plugins/factura_pdf1`, PR-2), the print-integration slice,
> **WU-3** (`plugins/tpvmod`, PR-3), the sede mapping settings UI, and **WU-4**
> (`plugins/business_data`, PR-4, chained after PR-1 on a child branch), the
> `admin_empresa` sede panel.
> Date of the last verification recorded here: **2026-09-22**.

---

## 1. Work-unit status

| Work unit | Repo | Status | Evidence |
|---|---|---|---|
| **WU-1** | `plugins/business_data` | **Implemented** (initial commit + coverage hardening) | `f68afbb0`, `83be16bd` on `feat/empresa-sedes` |
| **WU-2** | `plugins/factura_pdf1` | **Implemented** (PR-2 gate not fully closable — see 5.5) | `e89efdf` on `feat/empresa-sedes` |
| **WU-3** | `plugins/tpvmod` | **Implemented** (PR-3 gate green; refactor task not separately evidenced — see 7.5) | `fa4dc92` on `feat/empresa-sedes` |
| **WU-4** | `plugins/business_data` | **Implemented** (PR-4 gate not fully closable — see 9.6) | `51eb6401` on `feat/empresa-sedes-panel` |

### WU-1 task status (ids from `tasks.md`)

| Task | Status | Note |
|---|---|---|
| 1.1 | `[x]` | `EmpresaSedeModelTest` created: resets + `fs_db2` spy with the exact `exec($sql, $transaction = null, $params = [], $batch = false)` signature |
| 1.2 | `[x]` | Case 1 hydration covered |
| 1.3 | `[x]` | Cases 2 + 3 covered (blank `nombre`, `no_html()` sanitization) |
| 1.4 | `[x]` | Case 4 XML inventory covered; **hardened** post-verify with the DB-free structural contract test |
| 1.5 | `[x]` | Case 5 `MAX+1` covered; **hardened** post-verify with sequential uniqueness |
| 1.6 | `[x]` | Case 6 UPDATE / DELETE covered |
| 1.7 | `[x]` | Cases 7 + 8 + 9 covered; case 7 **renamed** post-verify to state honestly what it proves |
| 1.8 | `[x]` | 12-field contract + `descripcion` round-trip; **hardened** post-verify into a real INSERT→hydrate round-trip |
| 2.1 | `[x]` | Cases 10–13, loader-call counters |
| 2.2 | `[x]` | Cases 14–17 |
| 2.3 | `[x]` | Cases 18–20 |
| 2.4 | `[x]` | Case 21; **corrected** post-verify (tautological assertion removed, test renamed) |
| 2.5 | `[ ]` | **NOT EVIDENCED** — see TDD Cycle Evidence below |
| 3.1 | `[x]` | `model/table/empresa_sedes.xml` (13 columns, PK `empresa_sedes_pkey`) |
| 3.2 | `[x]` | `model/empresa_sede.php` (entity + fusion + mapping API + resolver) |
| 3.3 | `[x]` | Focused filter green: `OK (33 tests, 340 assertions)` |
| 3.4 | `[x]` | No WU-1 regression. The root suite is **not fully green**: 20 pre-existing `Tests\OidcProvider\...` failures, reproduced with no WU-1 file loaded (section 4) |
| 3.5 | `[x]` | Refactor review, no behaviour change |
| 3.6 | `[x]` | `fsframework.ini` `1.0.4` → `1.1.0` |
| 3.7 | `[x]` | No new Composer dependency; `composer.json` / `composer.lock` / `vendor/` untouched |
| 3.8 | `[x]` | `f68afbb0` on `feat/empresa-sedes` (+ `83be16bd`, this batch) |
| 10.x | `[ ]` | Cross-repo verification, manual smoke and release guard (WU-3/WU-4 are recorded in their own tables below) |

### WU-2 task status (ids from `tasks.md`)

| Task | Status | Note |
|---|---|---|
| 4.1 | `[x]` | `tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` created (445 lines) |
| 4.2 | `[x]` | Case 27: `RuntimeException('Empresa no configurada.')` preserved, even with a mapping key present |
| 4.3 | `[x]` | Cases 28 + 29: unmapped / no-type call keeps base identity and the base phone |
| 4.4 | `[x]` | Cases 30 + 31: mapped sede wins; dangling mapping falls back to the base |
| 4.5 | `[x]` | Case 32: the sede `codpais` wins and the override precedes the `$codpais` derivation |
| 4.6 | `[x]` | Cases 33 + 34 + 35: reflection contract, `empresa_row_cache` untouched, no `INSERT`/`UPDATE` |
| 4.7 | `[x]` | RED captured: `ERRORS! Tests: 10, Assertions: 3, Errors: 8, Failures: 2` (section 2.3) |
| 5.1 | `[x]` | `load(object $document, ?string $documentType = null)` + `resolveEmpresa()` + guarded `empresa_sede` require |
| 5.2 | `[x]` | 4 call sites pass `'albaran'` / `'pedido'` / `'presupuesto'` / `'factura'` |
| 5.3 | `[x]` | `OK (10 tests, 32 assertions)` — reproduced by the orchestrator |
| 5.4 | `[x]` | `PortedPdfDocument.php` untouched (`git diff` empty); interface and `instanceof` guard unchanged |
| 5.5 | `[ ]` | **PR-2 gate not fully closable** — the root `Plugins` suite is environmentally flaky (section 4); the `factura_pdf1` suite half is green with an identical pre-existing baseline (section 3) |
| 5.6 | `[x]` | `fsframework.ini` `1.0.6` → `1.1.0` |
| 5.7 | `[x]` | No new Composer dependency; `composer.json` / `composer.lock` / `vendor/` untouched |
| 5.8 | `[x]` | `e89efdf` on `feat/empresa-sedes` (7 files, 510 insertions, 10 deletions) |

### WU-3 task status (ids from `tasks.md`)

| Task | Status | Note |
|---|---|---|
| 6.1 | `[x]` | `tests/TpvmodSedeMappingSettingsTest.php` created; case 36: the mapping marker is detected and a terminal-mode POST is never mistaken for it |
| 6.2 | `[x]` | Case 37: canonical order + `''`/absent → `null` |
| 6.3 | `[x]` | Cases 38 + 40: exactly 4 `(tipo, codsede)` pairs in canonical order; `''` → `setMappingFor('factura', null)` |
| 6.4 | `[x]` | Cases 39 + 41: an invalid code writes nothing; a failing `setMappingFor` aborts with an error |
| 6.5 | `[x]` | Case 42 view contract: `csrf_field()`, hidden `save_sede_mapping`, the 4 selects, an explicit empty option, block after the terminal gate |
| 6.6 | `[x]` | Cases 43 + 44 controller contract: prefixed ordering, gate consulted exactly once, `isCsrfValid()` inside the mapping path, escaped labels |
| 6.7 | `[x]` | RED captured: `ERRORS! Tests: 13, Assertions: 0, Errors: 13` (the required `lib/tpvmod_sede_mapping.php` did not exist) — section 2.4 |
| 7.1 | `[x]` | `lib/tpvmod_sede_mapping.php` created: `tpvmod_sede_mapping_submitted()` / `tpvmod_normalize_sede_mapping()` / `tpvmod_save_sede_mapping()` |
| 7.2 | `[x]` | `controller/tpvmod_settings.php`: mapping POST branch evaluated before the terminal gate, `saveSedeMapping()` with `isCsrfValid()`, `loadSedeMapping()`, `requireEmpresaSedeModel()` |
| 7.3 | `[x]` | `view/tpvmod_settings.html.twig`: mapping section outside the terminal gate, own `csrf_field()`, hidden `save_sede_mapping`, 4 selects |
| 7.4 | `[x]` | Focused filter green: `OK (13 tests, 54 assertions)` |
| 7.5 | `[ ]` | **NOT EVIDENCED** — the recorded facts document the RED→GREEN cycle only; no separate refactor pass. Left unchecked rather than back-filled |
| 7.6 | `[x]` | Isolated suite `OK (126 tests, 554 assertions)`; the gate-absent render is asserted by `testMappingRendersAndPersistsWithFacturacionBaseInactive` |
| 7.7 | `[x]` | `fsframework.ini` `2.1.0` → `2.2.0` |
| 7.8 | `[x]` | No new Composer dependency; `composer.json` / `composer.lock` / `vendor/` untouched |
| 7.9 | `[x]` | `fa4dc92` on `feat/empresa-sedes` (5 files, 642 insertions, 1 deletion) |

### WU-4 task status (ids from `tasks.md`)

| Task | Status | Note |
|---|---|---|
| 8.1 | `[x]` | `tests/AdminEmpresaDispatchActionTest.php` created (591 lines); `base/fs_controller.php` loaded before the controller (correction 6) |
| 8.2 | `[x]` | Case 22 (collision regression): `save_sede` wins over `nombre` |
| 8.3 | `[x]` | Case 23: `delete_sede` wins over `nombre` |
| 8.4 | `[x]` | Case 24: a full 12-field sede POST resolves to `sede_save` only; `handleEmpresaSave()` is unreachable |
| 8.5 | `[x]` | Case 25: every existing branch unchanged, including the `delete_cuenta` POST-dispatch / GET-handler asymmetry (correction 4) |
| 8.6 | `[x]` | Case 26 (correction 1): `sedeFieldsFromPost()` returns exactly the 12 editable fields and none of the 8 forbidden ones |
| 8.7 | `[x]` | View contracts: nav/panel/hash/JS branches anchored at the `:325` closure (correction 3); the 5 existing panels unchanged |
| 8.8 | `[x]` | Block contract: one CSRF field per form, both markers, no disabled/`onclick` submit (AD-10), no modal (AD-8), escaped labels |
| 8.9 | `[x]` | RED captured: `ERRORS! Tests: 11, Assertions: 3, Errors: 8, Failures: 3` (`Call to undefined method admin_empresa::resolveAction()`) — section 2.5 |
| 9.1 | `[x]` | `controller/admin_empresa.php`: guarded `empresa_sede` require, `resolveAction()` pure seam, `posted()` helper, sede handlers — `$this->empresa` is never written by a sede handler |
| 9.2 | `[x]` | `view/admin_empresa.html.twig`: the 4 exact edit sites |
| 9.3 | `[x]` | `view/block/admin_empresa_sedes.html.twig` created (one CSRF-protected form per sede + inline create form + empty state) |
| 9.4 | `[x]` | Focused filter green: `OK (14 tests, 131 assertions)` |
| 9.5 | `[ ]` | **NOT EVIDENCED** — the recorded facts document RED→GREEN plus mutation-RED probes only; no separate refactor pass. Left unchecked rather than back-filled |
| 9.6 | `[ ]` | **PR-4 gate not closable** — the root suite carries pre-existing `Tests\OidcProvider\...` failures; real-DB byte-identity belongs to manual smoke 10.6 (section 4.2) |
| 9.7 | `[x]` | `51eb6401` on `feat/empresa-sedes-panel` (4 files, 1028 insertions, 11 deletions) |

---

## 2. TDD Cycle Evidence

### 2.1 WU-1 initial commit `f68afbb0` — RED not separately evidenced

**Recorded plainly:** WU-1 originally landed as **one single commit**
(`f68afbb0`) containing the schema XML, the model and both test files together.
**No failing run was captured**, so there is no artifact proving the tests were
RED before the production code existed. Task 2.5 ("confirm RED") is therefore
left unchecked in `tasks.md`. The GREEN and REFACTOR halves are evidenced by the
commit and the passing suite:

| Test file | RED (test written first) | GREEN | REFACTOR |
|---|---|---|---|
| `tests/EmpresaSedeModelTest.php` | **NOT SEPARATELY EVIDENCED** — authored in the same commit as the model | `OK (31 tests, 153 assertions)` at `f68afbb0` | Task 3.5: duplication/naming review, no behaviour change |
| `tests/EmpresaSedeResolutionTest.php` | **NOT SEPARATELY EVIDENCED** — same single commit | same run | same |

### 2.2 This corrective batch — no production change, so RED = mutation-RED

This follow-up changes **test files only**: `model/empresa_sede.php` and
`model/table/empresa_sedes.xml` are byte-identical to `f68afbb0` (verified with
`git diff --stat -- model/empresa_sede.php model/table/empresa_sedes.xml` →
empty). There is no new production behaviour to drive a spec-first RED, so each
strengthened assertion was proven to **discriminate** by temporarily breaking the
production code (mutation-RED), observing the failure, and reverting immediately.

| # | Assertion added / strengthened | Test file | Mutation-RED probe (production temporarily broken) | Observed RED | Reverted → GREEN |
|---|---|---|---|---|---|
| FIX 1 | Schema XML structural contract: well-formed, framework shape, 13 columns with `empresa.xml` types/nullability, filename == table name | `EmpresaSedeModelTest` | `empresa_sedes.xml`: `telefono` type `character varying(20)` → `character varying(30)` | `telefono must use the design type` — `Tests: 1, Assertions: 59, Failures: 1` | yes → suite green |
| FIX 2 | Every null case returns `null`; the 3 early returns never reach the loader; the dangling case reaches it exactly once; base not mutated | `EmpresaSedeResolutionTest` | `resolveForDocumentType()`: forced `loadSede()` before the empty-key early return | `an absent mapping key must never reach the loader` (1 is identical to 0) — `Assertions: 4, Failures: 1` | yes → suite green |
| FIX 3a | Real round-trip: parse the emitted INSERT, re-hydrate from that row, all 13 fields verbatim | `EmpresaSedeModelTest` | `hydrate()` ignores `$data['descripcion']` | `the persisted row must round-trip descripcion verbatim` — `Assertions: 6, Failures: 1` | yes → suite green |
| FIX 3b | `all()` emits `ORDER BY descripcion ASC, codsede ASC` and hydrates every returned row in returned order | `EmpresaSedeModelTest` | `all()` drops the `ORDER BY` clause | `contains "ORDER BY descripcion ASC, codsede ASC"` failed — `Assertions: 2, Failures: 1` | yes → suite green |
| FIX 3c | Two successive saves produce two different non-empty codes | `EmpresaSedeModelTest` | `get_new_codigo()` hardcodes `return '1';` | `two successive saves must not reuse the same generated code` — `Assertions: 5, Failures: 1` | yes → suite green |

FIX 2 also removed an assertion that could never fail:
`assertSame($base, $adopt($unknownTipo))`, where
`$adopt = static fn ($sede) => $sede instanceof \empresa ? $sede : $base` always
returns `$base` when the resolver returns `null`. It was tautological **by
construction** — no mutation is needed to demonstrate that. Its test was renamed
to `testResolveForDocumentTypeReturnsNullForEveryNullCaseWithoutMutatingTheBase`,
and the base-identity (`assertSame`) contract is explicitly deferred to the WU-2
seam (`RelatedModelsLoader::resolveEmpresa()`), because
`resolveForDocumentType()` returns `?empresa` and never hands the base back.

No assertion from the WU-1 suite was weakened: the two pre-existing tests that
were replaced only gained strictness, and every surviving assertion still holds.

### 2.3 WU-2 — RED captured, GREEN and full-suite runs measured

WU-2 is the first slice whose strict-TDD cycle was captured end to end (WU-3 and
WU-4 follow in sections 2.4 and 2.5).

| Step | Command | Result |
|---|---|---|
| RED | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter RelatedModelsLoaderEmpresaSede` (test authored before the production edit) | `ERRORS! Tests: 10, Assertions: 3, Errors: 8, Failures: 2` — 8 errors from the missing `resolveEmpresa()` and 2 failures from the `load()` arity/contract |
| GREEN | same filter, after 5.1 + 5.2 | `OK (10 tests, 32 assertions)` |
| GREEN (independent re-verification) | same filter | `OK (10 tests, 32 assertions)` — reproduced by the orchestrator with no local edits |

The RED failure modes were exactly the two the design predicted: an undefined
`RelatedModelsLoader::resolveEmpresa()` call and a `load()` signature/size
mismatch. The RED run is **not** re-runnable now without reverting production
code (out of bounds); the recorded run is the TDD-cycle artifact.

Full plugin suite after the slice:

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` | `Tests: 228, Assertions: 679, Failures: 6, Warnings: 21, Skipped: 1` |

The 6 failures are **pre-existing** and identical to the pre-edit baseline of
`Tests: 218, …, Failures: 6`: `FacturaPdf1SettingsControllerTest` ×1 +
`SettingsCoverageTest` ×2 + `SettingsEffectCoverageTest` ×3 warehouse-signal
datasets (`mostraralmacen`, `tituloalmacen`, `mostraralmacentel`). The 10-test
delta (218 → 228) is exactly the new WU-2 file, and it is entirely green.

### 2.4 WU-3 — RED captured, GREEN measured, isolated-suite baseline corrected

WU-3's strict-TDD cycle was captured end to end, and the isolated `tpvmod` suite
was measured before and after.

| Step | Command | Result |
|---|---|---|
| RED | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodSedeMappingSettings` (test authored before `lib/tpvmod_sede_mapping.php` existed) | `ERRORS! Tests: 13, Assertions: 0, Errors: 13` — every test errored on the missing required file |
| GREEN | same filter, after task 7.1 | `OK (13 tests, 54 assertions)` |
| GREEN (isolated suite) | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` | `OK (126 tests, 554 assertions)` |

**Baseline correction (stale figure).** The WU-1-era record and the sdd-init
testing-capabilities cache cite `74 tests / 286 assertions` for the isolated
`tpvmod` suite. That figure is **STALE**. The orchestrator-measured current
baseline is `OK (113 tests, 500 assertions)` (confirmed via
`--exclude-filter TpvmodSedeMappingSettings`); WU-3 adds exactly
`+13 tests / +54 assertions`. The suite has **zero** failures.

### 2.5 WU-4 — RED captured, mutation-RED discrimination, runtime harness

| Step | Command | Result |
|---|---|---|
| RED | `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter AdminEmpresaDispatchAction` (test authored before `resolveAction()` existed) | `ERRORS! Tests: 11, Assertions: 3, Errors: 8, Failures: 3` (`Call to undefined method admin_empresa::resolveAction()`) |
| GREEN | same filter, after tasks 9.1–9.3 | `OK (14 tests, 131 assertions)` |

Mutation-RED evidence: each probe temporarily broke production code, the failure
was observed, and the probe was reverted (suite green afterwards). This proves
the assertions discriminate instead of passing vacuously.

| # | Mutation probe | Observed RED |
|---|---|---|
| M1 | move `nombre` BEFORE `save_sede` in `resolveAction()` | `Failures: 4` |
| M2 | remove `name="save_sede"` from the per-sede submit button (the payload would fall into `nombre`) | `Failed asserting that 1 is identical to 3` |
| M3 | remove `$("#panel_sedes").hide();` | JS-wiring test `Failures: 1` |
| M4 | inject `$this->empresa->nombre = 'MUTATION'` into `handleSaveSede()` | `handleSaveSede must never write the base company` |

All four reverted; the suite was green afterwards.

**Runtime harness.** A real `Twig\Environment` + `FilesystemLoader` over
`plugins/business_data/view` rendered the block (`renderSedesBlock()` in the
test): 2 sedes → 3 `<form>` / 3 CSRF fields / 3 `save_sede` / 2 `delete_sede`;
`Sede <b>Norte</b>` escaped as `Sede &lt;b&gt;Norte&lt;/b&gt;`; the
`descripcion ?: nombre` fallback; and the empty state
(`testSedesBlockShowsTheEmptyStateWithoutSedes`). Both the modified and the new
template compile under Twig.

---

## 3. Verification results (real output)

Command run via DDEV (never host PHP).

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/` | **OK (33 tests, 340 assertions)** |
| `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede` | **OK (26 tests, 322 assertions)** |

Baseline immediately before this corrective batch:

| Command | Before | After |
|---|---|---|
| `plugins/business_data/tests/` | `OK (31 tests, 153 assertions)` | `OK (33 tests, 340 assertions)` |
| `--testsuite Plugins --filter EmpresaSede` | `OK (24 tests, 135 assertions)` | `OK (26 tests, 322 assertions)` |

### WU-3 verification (tpvmod)

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` | **OK (126 tests, 554 assertions)** |
| same suite, WU-3 excluded (`--exclude-filter TpvmodSedeMappingSettings`) | `OK (113 tests, 500 assertions)` ⇒ WU-3 delta `+13 tests / +54 assertions` |

### WU-4 verification (business_data)

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/` | **OK (47 tests, 471 assertions)** |
| `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede` | **OK (36 tests, 354 assertions)** — identical to baseline, no regression |
| `ddev exec php vendor/bin/phpunit` (root) | `Failures: 7`, ALL `Tests\OidcProvider\...` (section 4.2); zero attributable to this change |

Baseline immediately before WU-4:

| Command | Before | After |
|---|---|---|
| `plugins/business_data/tests/` | `OK (33 tests, 340 assertions)` | `OK (47 tests, 471 assertions)` ⇒ `+14 / +131` |
| `--testsuite Plugins --filter EmpresaSede` | `OK (36 tests, 354 assertions)` | `OK (36 tests, 354 assertions)` |

### Work Unit Evidence (WU-1)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/` → `OK (33 tests, 340 assertions)` |
| Runtime harness command/scenario and exact result | **N/A for this corrective batch** — the only real runtime boundary of WU-1 is the lazy `CREATE TABLE` performed by `fs_model::check_table()` on first instantiation, which requires the live dev database. Executing it would create a real `empresa_sedes` table, explicitly out of bounds for this batch and for the test suite; it is deferred to the manual smoke step (task 10.6). The DB-free substitute is the schema structural contract test (FIX 1), which validates the XML the framework feeds into DDL generation. |
| Rollback boundary | The two test files only: `tests/EmpresaSedeModelTest.php` and `tests/EmpresaSedeResolutionTest.php`. Reverting `83be16bd` restores the `f68afbb0` coverage level and removes no production behaviour. Production files are untouched, so rollback cannot affect the model, schema or any consumer. |

### Work Unit Evidence (WU-2)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter RelatedModelsLoaderEmpresaSede` → `OK (10 tests, 32 assertions)` |
| Full-plugin-suite command and exact result | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` → `Tests: 228, Assertions: 679, Failures: 6, Warnings: 21, Skipped: 1` (6 pre-existing, identical to baseline) |
| Runtime harness command/scenario and exact result | **N/A — honest gap.** The real end-to-end boundary is `load()` against a live database (a mapped sede resolving through `empresa_sede`) plus a Cezpdf render of a real invoice; the base-phone change is only observable in rendered output. Both require the dev DB seed, so they are covered by the manual smoke step (task 10.6), not by an automated test. The DB-free substitutes are the `$sedeLoader` seam and the spy-DB assertions of `RelatedModelsLoaderEmpresaSedeTest`. |
| Rollback boundary | `Model/View/RelatedModelsLoader.php` + the 4 print views (`AlbaranPrintView.php`, `PedidoPrintView.php`, `PresupuestoPrintView.php`, `FacturaPrintView.php`) + `tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` + `fsframework.ini`. Reverting restores the previous output; `factura_pdf1` is the **only** slice that affects printed output (the base-company phone becoming printable is the single intentional change, AD-4). |

### Work Unit Evidence (WU-3)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodSedeMappingSettings` → `OK (13 tests, 54 assertions)` |
| Isolated-suite command and exact result | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → `OK (126 tests, 554 assertions)` |
| Runtime harness command/scenario and exact result | **DB-free substitute.** `tpvmod_settings` extends `fs_controller` and is not instantiable DB-free (AD-14), so the runtime boundary is asserted as a source contract plus injected spies. `testMappingRendersAndPersistsWithFacturacionBaseInactive` sets `$GLOBALS['plugins']` without `facturacion_base`, asserts `tpvmod_terminal_settings_available()` is `false` and that the mapping form sits after the terminal gate; the pure helpers run through injected `setMappingFor`/`sedeExists` spies. The real page load and read-back belong to manual smoke 10.6. |
| Rollback boundary | `lib/tpvmod_sede_mapping.php`, `controller/tpvmod_settings.php`, `view/tpvmod_settings.html.twig`, `tests/TpvmodSedeMappingSettingsTest.php` and the `fsframework.ini` bump. Reverting `fa4dc92` leaves the mapping keys inert — no consumer depends on them yet. |

### Work Unit Evidence (WU-4)

| Evidence | Value |
|---|---|
| Focused test command and exact result | `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter AdminEmpresaDispatchAction` → `OK (14 tests, 131 assertions)` |
| Full business_data suite and exact result | `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/` → `OK (47 tests, 471 assertions)` (pre-WU-4 `OK (33 tests, 340 assertions)` ⇒ `+14 / +131`) |
| Root suite and exact result | `ddev exec php vendor/bin/phpunit` → `Failures: 7`, all `Tests\OidcProvider\...` (section 4.2); zero attributable to this change |
| Runtime harness command/scenario and exact result | Real `Twig\Environment` render of `block/admin_empresa_sedes.html.twig` (section 2.5): 2 sedes → 3 forms / 3 CSRF fields / 3 `save_sede` / 2 `delete_sede`; escaped label; `descripcion ?: nombre` fallback; empty state. This is a template render, not a full application render. |
| Rollback boundary | `controller/admin_empresa.php`, `view/admin_empresa.html.twig`, `view/block/admin_empresa_sedes.html.twig` and `tests/AdminEmpresaDispatchActionTest.php`. Reverting `51eb6401` removes the 6th panel; the WU-1 `empresa_sede` model stays valid standalone. |

---

## 4. Pre-existing failures in the root suite (corrected count)

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit -c phpunit.xml` | `Tests: 2817, Assertions: 9762, Failures: 20, Warnings: 1, Skipped: 47` |
| `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/OidcProvider/tests/` | `Tests: 386, Assertions: 1355, Failures: 20, Warnings: 1, Skipped: 23` |

- **All 20 failures belong to `Tests\OidcProvider\...`** (controllers, role
  resolver integration, schema parity/contract and the `migration011` backfill
  tests). Filtering the full run for failure headers outside `Tests\OidcProvider`
  returns nothing.
- The isolated command scopes PHPUnit to `plugins/OidcProvider/tests/` only, so
  **no WU-1 file is loaded** in that run and the 20 failures still reproduce —
  they are unrelated to this change.
- **Correction:** the earlier WU-1 apply report stated **8** pre-existing
  failures. That specific number was not stable. This snapshot measured **20**,
  while the WU-2 snapshot (section 4.1) measured **8** for the same environmental
  failures of `OidcProvider`. See section 4.1 for the honest framing: the count
  varies **8–20** between runs.

### 4.1 WU-2 measurement — the root `Plugins` suite is FLAKY and not ours

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit` (root `Plugins` suite) | `Failures: 8` |
| `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/OidcProvider/tests/` (alone, **no** `empresa_sede` file loaded) | **the same 8 failures** |
| same isolated run, twice in a row | `1410 assertions / 23 skipped` then `1395 assertions / 26 skipped` |

- The `OidcProvider` suite is **DB-state dependent and non-deterministic**: two
  consecutive isolated runs produced different assertion/skip counts.
- Root cause evidence: `Can't create table ... oidc_cliente_grupos (errno: 150
  "Foreign key constraint is incorrectly formed")`.
- **0 failures are attributable to this change.** Filtering the root run for
  failure headers outside `Tests\OidcProvider\...` returns nothing.
- The WU-1-era claim of "20 pre-existing" was itself unstable: the same suite
  measured **20** at one point and **8** at another. The honest framing is
  **"pre-existing environmental failures of OidcProvider, count varies 8–20
  between runs"** — not a fixed number. This instability is why the WU-2 gate
  (task 5.5) is left unchecked rather than declared green.

### 4.2 WU-4 measurement — root suite `Failures: 7`, all `OidcProvider`

| Command | Result |
|---|---|
| `ddev exec php vendor/bin/phpunit` (root `Plugins` suite) | `Failures: 7` |
| grep of the run output for failure headers outside `Tests\OidcProvider\...` | **empty** |

- All 7 failures belong to `Tests\OidcProvider\...` — the pre-existing
  non-deterministic set already documented in WU-2's record (it varies 7–20
  between runs by DB state).
- **Zero failures are attributable to this change.** That is precisely why the
  WU-4 verification step (task 9.6) is left unchecked: the root-suite-green gate
  cannot be declared from a suite that is environmentally red.

---

## 5. Review workload — approved `size:exception` for PR-1

| Item | Value |
|---|---|
| Session review budget | 800 changed lines |
| PR-1 actual | **1395 insertions + 1 deletion** (1396 changed lines) |
| Decision | **`size:exception` — approved by the human before apply** |

Composition of `f68afbb0`:

| Part | Lines |
|---|---|
| Production: `model/empresa_sede.php` | 363 |
| Production: `model/table/empresa_sedes.xml` | 79 |
| **Production subtotal** | **442** |
| Tests: `tests/EmpresaSedeModelTest.php` | 489 |
| Tests: `tests/EmpresaSedeResolutionTest.php` | 463 |
| **Tests subtotal** | **952** |
| `fsframework.ini` version bump | 1 |
| **Total insertions** | **1395** |

**Justification:** `work-unit-commits` forbids separating the TDD suite from the
code it verifies ("tests belong in the same commit as the behaviour they
verify"). PR-1 is the business_data slice of a cross-plugin change; splitting the
952 test lines out would produce a commit whose tests cannot run and would break
the one-deliverable-scope rule. The overage cannot shrink further without
deleting tests or comments, which the review-budget rule forbids.

This corrective follow-up: **383 insertions + 23 deletions = 406 changed lines**,
test files only.

### WU-2 (PR-2) — inside budget

**510 insertions + 10 deletions = 520 changed lines** (7 files). Composition of
`e89efdf`:

| Part | Insertions | Deletions |
|---|---|---|
| Production: `Model/View/RelatedModelsLoader.php` | 60 | 5 |
| Production: 4 print-view call sites (1 line each) | 4 | 4 |
| Production: `fsframework.ini` version bump | 1 | 1 |
| Tests: `tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` | 445 | 0 |
| **Total** | **510** | **10** |

Under the 800-line session budget, so no `size:exception` is needed for this
slice (unlike PR-1).

### WU-3 (PR-3) — inside budget

**642 insertions + 1 deletion = 643 changed lines** (5 files). Composition of
`fa4dc92`:

| Part | Insertions | Deletions |
|---|---|---|
| Production: `lib/tpvmod_sede_mapping.php` | 110 | 0 |
| Production: `controller/tpvmod_settings.php` | 103 | 0 |
| Production: `view/tpvmod_settings.html.twig` | 68 | 0 |
| Production: `fsframework.ini` version bump | 1 | 1 |
| Tests: `tests/TpvmodSedeMappingSettingsTest.php` | 360 | 0 |
| **Total** | **642** | **1** |

Under the 800-line session budget, so no `size:exception` is needed for this
slice.

### WU-4 (PR-4) — `size:exception` required

| Item | Value |
|---|---|
| Session review budget | 800 changed lines |
| PR-4 actual | **1028 insertions + 11 deletions** (1039 changed lines) |
| Decision | **`size:exception` needed for PR-4** — same rationale as the approved PR-1 exception |

Composition of `51eb6401`:

| Part | Insertions | Deletions |
|---|---|---|
| Production: `controller/admin_empresa.php` | 182 | 11 |
| Production: `view/admin_empresa.html.twig` | 15 | 0 |
| Production: `view/block/admin_empresa_sedes.html.twig` | 240 | 0 |
| Tests: `tests/AdminEmpresaDispatchActionTest.php` | 591 | 0 |
| **Total** | **1028** | **11** |

**Justification.** The `tasks.md` forecast for WU-4 was **200–230 lines**
(production only); the overflow is the 591-line test suite. `work-unit-commits`
forbids splitting the tests from the code they verify, so the overage cannot be
shrunk without deleting tests (which the review-budget rule forbids). Precedent:
PR-1's approved `size:exception` (section 5).

---

## 6. Commits and branches

| Repo | Branch | Commit | Contents |
|---|---|---|---|
| `plugins/business_data` | `feat/empresa-sedes` | `f68afbb0` | WU-1: schema XML + model + both test files + `fsframework.ini` 1.1.0 |
| `plugins/business_data` | `feat/empresa-sedes` | `83be16bd` | Corrective batch: FIX 1/2/3 test hardening (test files only) |
| `plugins/factura_pdf1` | `feat/empresa-sedes` | `e89efdf` | WU-2: `RelatedModelsLoader` signature + `resolveEmpresa()` + guarded `empresa_sede` require, 4 print-view call sites, new test, `fsframework.ini` 1.1.0 |
| `plugins/tpvmod` | `feat/empresa-sedes` | `fa4dc92` | WU-3: `lib/tpvmod_sede_mapping.php` + `tpvmod_settings` controller/view + new test + `fsframework.ini` 2.2.0 |
| `plugins/business_data` | `feat/empresa-sedes-panel` | `51eb6401` | WU-4 (child branch off `feat/empresa-sedes`): `admin_empresa` panel — `resolveAction()` seam + handlers + template nav/panel/JS + new block + dispatch regression test. No version bump (WU-1 already took business_data to 1.1.0) |
| `plugins/tpvmod` | `feat/empresa-sedes` | *(this docs commit)* | SDD artifacts: `tasks.md` checkboxes + this `apply-progress.md` |

Nothing was amended, rebased or force-pushed. `plugins/business_data/model/cuenta_banco.php`
carries a pre-existing uncommitted change that is **not** part of this work and
was never staged (still modified-and-unstaged after WU-4). The
`plugins/factura_pdf1` repo was left clean after `e89efdf`; `plugins/tpvmod` was
clean after `fa4dc92`. WU-4 landed on the child branch
`feat/empresa-sedes-panel` so PR-4's diff stays focused; WU-3 stayed on
`feat/empresa-sedes`.

---

## 7. Deviations, issues and remaining work

- **Deviations from design (WU-1):** none. WU-1 matches AD-1…AD-7 and AD-11.
- **Deviations from design (WU-2) — two, both approved/rationalised:**
  1. `resolveEmpresa()` takes a **third optional** parameter
     `?callable $sedeLoader = null` (the design declared two). It is required so
     the design's own "mapped sede with a loader stub" test case can run DB-free.
     The two-argument public call is unchanged; production callers omit it.
  2. In `requireRelatedModels()` the `empresa_sede.php` require is guarded by
     **both** `class_exists` and `file_exists` (the design snippet only had
     `class_exists`). This degrades safely when business_data < 1.1.0 is
     deployed: sedes silently do not resolve and the loader falls back to the
     base company instead of fataling the print path.
- **Deviations from design (WU-3) — one, recorded, plus one documentary correction:**
  1. `tpvmod_sede_mapping_submitted(array $post, string $marker = TPVMOD_SEDE_MAPPING_POST_MARKER)`
     gained an **optional second parameter** (the design declared one) so the
     controller's own constant can carry the literal. Backwards compatible:
     callers that omit it keep the default marker.
  2. **Documentary correction (verify finding W3).** An earlier version of this
     record declared `lib/tpvmod_sede_mapping.php` a design deviation because
     the file was "**not** in the design's file list". That statement was
     factually wrong. The design specifies that exact file and its three
     functions in AD-14 (`design.md:250-255`), in the File Changes table
     (`design.md:326`) and in the interfaces block (`design.md:416-421`).
     Creating it is the design, not a deviation. The only WU-3 deviation is
     item 1 above.
- **Honest gaps (WU-3):**
  - CSRF rejection is covered by a **source contract** (`isCsrfValid()` asserted
    inside `saveSedeMapping()`), because the controller extends `fs_controller`
    and is not instantiable DB-free (AD-14). End-to-end CSRF behaviour belongs
    to manual smoke 10.6.
  - The 4-key write is **not atomic**: if the 3rd of 4 `setMappingFor()` calls
    fails, the earlier ones are already persisted (no transaction is available
    in this API). Documented and covered by `testPersistenceFailureAbortsWithAnError`.
- **Deviations from design (WU-4) — one, recorded:**
  - `dispatchAction()` now resolves over `$_POST`/`$_GET` through a `posted()`
    helper instead of `filter_input()`, because the pure seam receives arrays
    (this is what makes the collision regression DB-free testable). `posted()`
    replicates `filter_input()` truthiness (absent key → `null`; array value →
    `false`) and is covered by `testArrayValuedPayloadBehavesLikeFilterInput`.
    Residual difference: if some plugin mutated `$_POST` before dispatch,
    `filter_input()` would ignore it and `$_POST` would not — no such mutation
    exists in this controller.
- **Honest gaps / open item (WU-4):**
  - Task 9.6 is left unchecked: the root-suite-green gate cannot be declared
    because of the pre-existing `OidcProvider` failures (section 4.2), and
    proving the base row is byte-identical against a **real database** requires
    manual smoke 10.6. The structural proof exists: with `save_sede` present the
    token is `sede_save` and `handleEmpresaSave()` is unreachable, and both sede
    handlers never write `$this->empresa` (`testSedeHandlersNeverWriteTheBaseCompany`).
  - CSRF for the new handlers relies on the page gate (`pre_private_core()` →
    `validateCsrf()`), exactly like the existing `handleEmpresaSave()` /
    `handleSaveCuenta()` — deliberately unchanged.
  - Real create/save/delete of a sede against the DB belongs to manual smoke 10.6.
- **Open item (WU-1):** task 2.5 (RED confirmation) is permanently unverifiable
  for the original commit; it is recorded as NOT EVIDENCED rather than
  back-filled.
- **Open item (WU-2):** task 5.5 (PR-2 gate) is left unchecked — see section 4.1.
  The root suite cannot be declared green; the regression criterion is evidenced
  only through the flaky-suite argument plus the identical `factura_pdf1`
  baseline.
- **Open item (WU-3 + WU-4):** the REFACTOR tasks 7.5 and 9.5 are left unchecked
  because the recorded facts document only the RED→GREEN cycles (plus the WU-4
  mutation-RED probes); no separate refactor pass was captured. Recorded as NOT
  EVIDENCED rather than back-filled.
- **Deferred:** the live-DDL runtime check (WU-1), the end-to-end print smoke
  (WU-2: mapped sede → Cezpdf render, then unmapped → base header), the WU-3
  settings page load + read-back, and the WU-4 DB-backed sede create/save/delete
  remain in the manual smoke step (task 10.6); the accepted product decision on
  the base-company phone (AD-4) is task 10.7.
- **Next:** Phase 10 — cross-repo verification, manual smoke (10.6) and the
  release guard. No implementation slice remains.

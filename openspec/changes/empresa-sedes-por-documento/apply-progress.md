# Apply Progress: empresa-sedes-por-documento

> Cross-plugin SDD owned by `plugins/tpvmod/openspec/` (main beneficiary).
> Execution mode: **Strict TDD** (`plugins/tpvmod/openspec/config.yaml` →
> `strict_tdd: true`).
> This artifact covers **WU-1** (`plugins/business_data`, PR-1), its corrective
> follow-up commit that closed four coverage defects found by the independent
> verifier, and **WU-2** (`plugins/factura_pdf1`, PR-2), the print-integration
> slice. WU-3/WU-4 are still pending.
> Date of the last verification recorded here: **2026-09-22**.

---

## 1. Work-unit status

| Work unit | Repo | Status | Evidence |
|---|---|---|---|
| **WU-1** | `plugins/business_data` | **Implemented** (initial commit + coverage hardening) | `f68afbb0`, `83be16bd` on `feat/empresa-sedes` |
| **WU-2** | `plugins/factura_pdf1` | **Implemented** (PR-2 gate not fully closable — see 5.5) | `e89efdf` on `feat/empresa-sedes` |
| WU-3 | `plugins/tpvmod` | Not started | — |
| WU-4 | `plugins/business_data` | Not started | — |

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
| 6.x–9.x, 10.x | `[ ]` | Later slices (WU-3/WU-4) and the manual smoke / cross-repo gate |

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

WU-2 is the only slice so far whose strict-TDD cycle was captured end to end.

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

---

## 6. Commits and branches

| Repo | Branch | Commit | Contents |
|---|---|---|---|
| `plugins/business_data` | `feat/empresa-sedes` | `f68afbb0` | WU-1: schema XML + model + both test files + `fsframework.ini` 1.1.0 |
| `plugins/business_data` | `feat/empresa-sedes` | `83be16bd` | Corrective batch: FIX 1/2/3 test hardening (test files only) |
| `plugins/factura_pdf1` | `feat/empresa-sedes` | `e89efdf` | WU-2: `RelatedModelsLoader` signature + `resolveEmpresa()` + guarded `empresa_sede` require, 4 print-view call sites, new test, `fsframework.ini` 1.1.0 |
| `plugins/tpvmod` | `feat/empresa-sedes` | *(this docs commit)* | SDD artifacts: `tasks.md` checkboxes + this `apply-progress.md` |

Nothing was amended, rebased or force-pushed. `plugins/business_data/model/cuenta_banco.php`
carries a pre-existing uncommitted change that is **not** part of this work and
was never staged. The `plugins/factura_pdf1` repo was left clean after `e89efdf`.

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
- **Open item (WU-1):** task 2.5 (RED confirmation) is permanently unverifiable
  for the original commit; it is recorded as NOT EVIDENCED rather than
  back-filled.
- **Open item (WU-2):** task 5.5 (PR-2 gate) is left unchecked — see section 4.1.
  The root suite cannot be declared green; the regression criterion is evidenced
  only through the flaky-suite argument plus the identical `factura_pdf1`
  baseline.
- **Deferred:** the live-DDL runtime check (WU-1) and the end-to-end print smoke
  — mapped sede → Cezpdf render, then unmapped → base header (WU-2) — remain in
  the manual smoke step (task 10.6); the accepted product decision on the
  base-company phone (AD-4) is task 10.7.
- **Next:** WU-3 (`plugins/tpvmod`) — the sede mapping settings page.

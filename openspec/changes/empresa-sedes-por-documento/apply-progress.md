# Apply Progress: empresa-sedes-por-documento

> Cross-plugin SDD owned by `plugins/tpvmod/openspec/` (main beneficiary).
> Execution mode: **Strict TDD** (`plugins/tpvmod/openspec/config.yaml` →
> `strict_tdd: true`).
> This artifact covers **WU-1** (`plugins/business_data`, PR-1) and the
> corrective follow-up commit that closed four coverage defects found by the
> independent verifier. WU-2/WU-3/WU-4 are still pending.
> Date of the last verification recorded here: **2026-09-22**.

---

## 1. Work-unit status

| Work unit | Repo | Status | Evidence |
|---|---|---|---|
| **WU-1** | `plugins/business_data` | **Implemented** (initial commit + coverage hardening) | `f68afbb0`, `83be16bd` on `feat/empresa-sedes` |
| WU-2 | `plugins/factura_pdf1` | Not started | — |
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
| 4.x–9.x, 10.x | `[ ]` | Later slices (WU-2/WU-3/WU-4) and the manual smoke / cross-repo gate |

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
  failures. That number was wrong. The real, reproducible count is **20**.

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

---

## 6. Commits and branches

| Repo | Branch | Commit | Contents |
|---|---|---|---|
| `plugins/business_data` | `feat/empresa-sedes` | `f68afbb0` | WU-1: schema XML + model + both test files + `fsframework.ini` 1.1.0 |
| `plugins/business_data` | `feat/empresa-sedes` | `83be16bd` | Corrective batch: FIX 1/2/3 test hardening (test files only) |
| `plugins/tpvmod` | `feat/empresa-sedes` | *(this docs commit)* | SDD artifacts: `tasks.md` checkboxes + this `apply-progress.md` |

Nothing was amended, rebased or force-pushed. `plugins/factura_pdf1` was not
touched. `plugins/business_data/model/cuenta_banco.php` carries a pre-existing
uncommitted change that is **not** part of this work and was never staged.

---

## 7. Deviations, issues and remaining work

- **Deviations from design:** none. WU-1 matches AD-1…AD-7 and AD-11.
- **Open item:** task 2.5 (RED confirmation) is permanently unverifiable for the
  original commit; it is recorded as NOT EVIDENCED rather than back-filled.
- **Deferred:** the live-DDL runtime check and the byte-identical-output check
  remain in the manual smoke step (task 10.6); the accepted product decision on
  the base-company phone (AD-4) is task 10.7.
- **Next:** WU-2 (`plugins/factura_pdf1`) — it owns the base-identity
  `assertSame` contract that was deliberately not asserted at the model layer.

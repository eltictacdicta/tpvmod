```yaml
schema: gentle-ai.archive-report/v1
change: empresa-sedes-por-documento
plugin: tpvmod
ownership: plugin-local
archived_at: 2026-09-22
archive_path: plugins/tpvmod/openspec/changes/archive/2026-09-22-empresa-sedes-por-documento/
verdict_at_close: pass_with_warnings
critical_findings: 0
archive_kind: intentional-with-warnings   # manual smoke 10.6/10.7 + REFACTOR/TDD-evidence debt explicitly accepted
requirements: 12/12
scenarios: 26/26
scenarios_proven: 22/26
scenarios_partial: 4/26
tests: >
  tpvmod OK (129 tests, 562 assertions); business_data OK (50 tests, 491 assertions);
  EmpresaSede filter OK (39 tests, 374 assertions); factura_pdf1
  Tests: 228, Assertions: 679, Failures: 6 (all pre-existing)
delivery_state: local-branches-only (nothing pushed, no PR; human owns delivery)
```

# Archive Report — `empresa-sedes-por-documento`

**Plugin**: `tpvmod` (plugin-local SDD — the core `openspec/` is NOT this change's tracker)
**Archived**: 2026-09-22
**Archive path**: `plugins/tpvmod/openspec/changes/archive/2026-09-22-empresa-sedes-por-documento/`
**Task owner**: `sdd-archive` executor

## Closing Summary

The change is closed. A multi-row `empresa_sede` entity was added to
`plugins/business_data` (model + XML schema + transient print resolver), the
`plugins/factura_pdf1` PDF loader now resolves a sede by document type, and
`plugins/tpvmod` gained the sede → document-type mapping UI on
`tpvmod_settings` plus the sede management panel on `admin_empresa`
(WU-4, in `business_data`). The delta specs are merged into the plugin's spec
source of truth and the change folder has been moved mechanically into the
plugin archive.

The verification verdict at close is `pass_with_warnings` with
`critical_findings: 0`. All 12 requirements / 26 scenarios are covered; 22/26 are
PROVEN by automated evidence and the 4 PARTIAL scenarios map 1:1 to the declared
honest gaps (real DDL execution, CSRF end-to-end, base-row byte-identity against
a real DB, and the settings read/write HTTP round-trip). The residual risk is
confined to real-DB / real-HTTP surfaces exercised by manual smoke `10.6`.

The archive is **intentional-with-warnings**: the unchecked tasks are evidence,
refactor, gate and manual-smoke items — not production implementation work (full
inventory below). No core implementation task is pending.

## Final-State Facts (authoritative over intermediate snapshots)

`verify-report.md` and `apply-progress.md` are intermediate snapshots. The
statements below are the final state at close. Two corrections landed **after**
the verify report was written; they are recorded here, not treated as pending.

### Post-verify corrections (after `verify-report.md`)

- **W1 — administrator-only enforcement.** `tpvmod_settings` now declares the
  class-level `#[\FSFramework\Attribute\AdminOnly]` attribute. This corrects a
  spec requirement that the obsolete 4th `$admin` constructor argument never
  delivered (`fs_controller::__construct` ignores it). `business_data` commit
  `6a96c66`; test `plugins/tpvmod/tests/TpvmodSettingsAdminOnlyTest.php` (3
  tests). `admin_empresa` was deliberately left role-gated (declaring it
  admin-only would revoke existing role-based access).
- **W2 — explicit CSRF on sede mutation handlers.** `handleSaveSede()` and
  `handleDeleteSede()` now reject an invalid token with an `isCsrfValid()` guard
  before any write, closing the `FS_CSRF_SOFT=true` window. `business_data`
  commit `2f66f9c1`; test
  `plugins/business_data/tests/AdminEmpresaSedeCsrfTest.php` (3 tests).
- **W3 — documentary error corrected.** `apply-progress.md` no longer declares
  `lib/tpvmod_sede_mapping.php` a design deviation; the design does specify that
  file and its three functions. `tpvmod` commit `107dea4`.

Reference: `verify-report.md` → `## Follow-up corrections`.

### Final commit inventory across the 3 repos

Each plugin is its own git repo. Shas/order below were read from
`git -C <repo> log --oneline` at archive time (oldest → newest within each
branch).

**`plugins/business_data`** — base `eb37dfa9` (v1.0.4)

| Branch | Commit | Contents |
|---|---|---|
| `feat/empresa-sedes` (tip `83be16bd`) | `f68afbb0` | WU-1: `model/empresa_sede.php` + `model/table/empresa_sedes.xml` + both test files + `fsframework.ini` 1.1.0 |
| `feat/empresa-sedes` | `83be16bd` | test hardening after verification findings (test files only) |
| `feat/empresa-sedes-panel` (child, tip `2f66f9c1`) | `51eb6401` | WU-4: `admin_empresa` panel — `resolveAction()` seam + handlers + template nav/panel/JS + block + dispatch regression test |
| `feat/empresa-sedes-panel` | `2f66f9c1` | post-verify correction W2: reject invalid CSRF in sede mutation handlers |

**`plugins/factura_pdf1`** — base `1050a6f` (v1.0.6)

| Branch | Commit | Contents |
|---|---|---|
| `feat/empresa-sedes` (tip `e89efdf`) | `e89efdf` | WU-2: `RelatedModelsLoader` signature + `resolveEmpresa()` + guarded `empresa_sede` require, 4 print-view call sites, new test, `fsframework.ini` 1.1.0 |

**`plugins/tpvmod`** — base `82b53ea` (v2.1.0)

| Commit (oldest → newest) | Contents |
|---|---|
| `f47ef03` | docs: record WU-1 apply progress and checked-off tasks |
| `dc39c15` | docs: record WU-2 `factura_pdf1` apply progress and checked-off tasks |
| `fa4dc92` | WU-3: `lib/tpvmod_sede_mapping.php` + `tpvmod_settings` controller/view + new test + `fsframework.ini` 2.2.0 |
| `29d64da` | docs: record WU-3 tpvmod and WU-4 admin panel apply progress |
| `f006118` | docs: add closing verify report |
| `6a96c66` | post-verify correction W1: declare `tpvmod_settings` administrator-only |
| `107dea4` | post-verify correction W3: correct the admin-only gate and record follow-up fixes (branch tip before this archive) |

> **Correction vs the launch prompt.** The prompt listed the two post-verify
> `tpvmod` commits (`6a96c66`, `107dea4`) as trailing the verify-report commit
> `f006118`; the actual order is `f006118` → `6a96c66` → `107dea4`, exactly as
> the prompt's own prose implied but not its list order. The list above is the
> verified order.

### Version bumps (verified)

| Plugin | From | To | Evidence |
|---|---|---|---|
| `business_data` | 1.0.4 | 1.1.0 | `fsframework.ini:version = 1.1.0` |
| `factura_pdf1` | 1.0.6 | 1.1.0 | `fsframework.ini:version = 1.1.0` |
| `tpvmod` | 2.1.0 | 2.2.0 | `fsframework.ini:version = 2.2.0` |

### Final test evidence at close (re-run by the archive executor)

```bash
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
# OK (129 tests, 562 assertions)

ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/
# OK (50 tests, 491 assertions)

ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede
# OK (39 tests, 374 assertions)

ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml
# Tests: 228, Assertions: 679, Failures: 6, Warnings: 21, Skipped: 1.
```

The 6 `factura_pdf1` failures are **pre-existing**, proven (not claimed): they are
`SettingsCoverageTest` ×2, `SettingsEffectCoverageTest` ×3 and
`FacturaPdf1SettingsControllerTest` ×1. The root `phpunit.xml` already excludes
those exact files, and `git blame -L 110,118 -- phpunit.xml` (root repo) dates
that exclusion block to `c34539ab` on **2026-08-31**, three weeks before
`e89efdf` (2026-09-22). They are plugin-repo drift, not a regression.

### Pre-existing `Tests\OidcProvider\...` flakiness — NOT attributable to this change

The root `Plugins` suite carries pre-existing, **environmental, DB-state
dependent and NON-DETERMINISTIC** `OidcProvider` failures. Observed failure
counts for the same suite across runs: **7, 8, 14, 16 and 20** (assertion/skip
counts also vary: `1410/23` and `1395/26` assertions/skips for the same isolated
run). They reproduce **without any file of this change loaded** and with **zero
static coupling** to it:

```bash
grep -rln "empresa_sede\|RelatedModelsLoader\|empresa-sede" plugins/OidcProvider/
# NONE
```

- Isolated observation at archive time:
  `ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/OidcProvider/tests/`
  → `Tests: 391, Assertions: 1421, Failures: 7, Warnings: 1, Skipped: 23`.
- Root cause visible in the run log: `CREATE TABLE failed ... oidc_cliente_grupos
  (errno: 150 "Foreign key constraint is incorrectly formed")` and
  `oidc_cliente_profiles dropped from baseline 1200 to 0/1/5`, while other
  sessions ran suites against the same MySQL instance.

**0 failures are attributable to `empresa-sedes-por-documento`.**

## Task Completion Gate — unchecked-task inventory

`tasks.md` at close: **63 checked, 12 unchecked** (75 total). The 12 unchecked
items were enumerated and classified; **none is a production implementation
task**:

| Task | Class | Reason it is not implementation work |
|---|---|---|
| `2.5` | TDD evidence | "confirm RED" run never captured (`f68afbb0` landed as one commit) — NOT EVIDENCED, not back-filled |
| `5.5` | Verification gate | PR-2 gate: root `Plugins` suite cannot be declared green because of the pre-existing `OidcProvider` flakiness |
| `7.5` | REFACTOR | No separate refactor pass captured (RED→GREEN only) — NOT EVIDENCED |
| `9.5` | REFACTOR | No separate refactor pass captured (RED→GREEN + mutation probes only) — NOT EVIDENCED |
| `9.6` | Verification gate | PR-4 gate: root suite + real-DB byte-identity, blocked by the same flakiness and by manual smoke `10.6` |
| `10.1`–`10.5` | Cross-repo verification | Satisfied by `verify-report.md` (suite runs, no-Composer audit, isolation audit, version-bump check) |
| `10.6` | Manual smoke | Real DB + real HTTP; not automatable DB-free (below) |
| `10.7` | Product sign-off | Human decision on the accepted output change (base phone) |

The abandoned implementation tasks are all checked. The archive is therefore
`intentional-with-warnings`, in the same shape as the precedent
`2026-09-19-tpvmod-opcional-rapido` archive.

## Deferred Verification Debt (explicitly accepted)

### Manual smoke `10.6` — REQUIRED POST-ARCHIVE (NOT run by this archive)

This archive does **not** claim `10.6` ran. It must be executed against a real
DB/browser:

1. Create a sede from `index.php?page=admin_empresa#sedes`; confirm the
   `empresa_sedes` table is created lazily from the XML and the row persists.
2. Confirm the base company row is **byte-identical** after a sede POST.
3. Map the sede to `factura` in `index.php?page=tpvmod_settings`; print a PDF
   and confirm the sede's data, including `pais` (from the sede `codpais`) and
   `telefono1` (from the sede `telefono`).
4. Unmap and print again; confirm the base company is used and the base phone is
   now printed.

### Product sign-off `10.7` — REQUIRED POST-ARCHIVE (NOT run by this archive)

Accepted visible change: the base company phone now prints for every existing
installation (AD-4). This is the single documented exception to byte-identical
output.

### Residual un-automated surfaces

- Real DDL execution for `empresa_sedes` (the lazy `CREATE TABLE`).
- CSRF end-to-end rejection in `admin_empresa` (UI-level, real session).
- Base-row byte-identity against a real database.

## Spec Sync (delta → plugin source of truth)

Performed BEFORE the archive move.

| Domain | Action | Details |
|---|---|---|
| `empresa-sedes` | Created | New canonical spec at `plugins/tpvmod/openspec/specs/empresa-sedes/spec.md`, copied byte-identical (sha256 match) from the delta. Its ownership caveat header is preserved verbatim: the `empresa_sede` model + resolver live in `plugins/business_data`, the mapping UI in `plugins/tpvmod`, and the SDD is owned by `tpvmod` because `business_data` has no `openspec/` tree (creating one is the `AGENTS.md` anti-pattern). 10 requirements / 20 scenarios. |
| `tpvmod-config` | Updated | ADDED-only delta merged with the mandated native composer: +2 requirements / +6 scenarios, 0 deletions. Every pre-existing requirement preserved. 6 → 8 requirements. |

### Native composition evidence (`tpvmod-config`)

```bash
gentle-ai sdd-archive-compose \
  --canonical "plugins/tpvmod/openspec/specs/tpvmod-config/spec.md" \
  --delta "plugins/tpvmod/openspec/changes/empresa-sedes-por-documento/specs/tpvmod-config/spec.md" \
  --output "/tmp/opencode/arch/tpvmod-config.composed.md"
# exit 0
```

`diff -u <before> <composed>` showed **additions only** (no `-` lines); the two
ADDED requirements were appended after all 161 pre-existing lines. `grep -c
'^### Requirement:'` went 6 → 8.

### New-capability mechanical copy evidence (`empresa-sedes`)

Performed with the shell only (`cp` + `diff -r` + `mv`), no model Read→Write:

```
diff -r <delta> <temp copy>   →  (empty), exit 0
sha256 delta   = 17edbdd8a6bffcb38ce66e713ac8320b528a663452e55c44577fda8768073498
sha256 installed = 17edbdd8a6bffcb38ce66e713ac8320b528a663452e55c44577fda8768073498
```

## Archive Move Evidence

The change dir was tracked in the `tpvmod` plugin repo and the move ran through a
pre-move recursive snapshot followed by the readback:

```
source:      plugins/tpvmod/openspec/changes/empresa-sedes-por-documento
destination: plugins/tpvmod/openspec/changes/archive/2026-09-22-empresa-sedes-por-documento
move:        git -C plugins/tpvmod mv ...   → OK
readback:    diff -r <pre-move snapshot> <destination>
output:      (empty)
exit:        0
```

`ARCHIVE_MOVE_DIFF_EMPTY` — the empty `diff -r` is the passing evidence; the 8
archived artifacts are byte-identical to their pre-move snapshot. This
`archive-report.md` is additive and is excluded from the source/destination
comparison.

> Note: a root-repo `git mv` first failed with `directorio de fuente está vacío`
> because the plugin is its own repository. The move was rerun with
> `git -C plugins/tpvmod mv`, which succeeded; the source was verified intact
> before the rerun.

## Final Artifacts List (archived)

| Artifact | sha256 |
|---|---|
| `exploration.md` | `72d2103a50d65d84c5812ee8406479780759fa5c624234a47a31a030ebd7419b` |
| `proposal.md` | `0943e78be377738eccf84b18e67fed7d62a8ea1b9eda4adec7fa3524db2f74c2` |
| `design.md` | `719e153614208bcf518e74d25dd4bc9aff716a44200495d58981b8cf282b979b` |
| `tasks.md` | `60b4e7275555e9b7f9c5fa6192f85c1391fb4a7a459b26ca6a6a5f4d5efba2f4` |
| `apply-progress.md` | `39a93174c670179e89cafde97fc694931c9db22294fe16bb113db9cb7ba52502` |
| `verify-report.md` | `b7bdd4df2eb81965f6c77d1ef4aedfe434b4b49192c574030356454d3109d79c` |
| `specs/empresa-sedes/spec.md` (delta) | `17edbdd8a6bffcb38ce66e713ac8320b528a663452e55c44577fda8768073498` |
| `specs/tpvmod-config/spec.md` (delta) | `d52553f2f6d5de75634f40998861a112a3a1865a22b07475cd1780b4afc67606` |

Source-of-truth specs after sync:

| Spec | sha256 |
|---|---|
| `plugins/tpvmod/openspec/specs/empresa-sedes/spec.md` | `17edbdd8a6bffcb38ce66e713ac8320b528a663452e55c44577fda8768073498` |
| `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md` | `84e20da868ef2a5ec7dbbad4428f40d861940193d8354cb38596bf4ee4a6ce33` |

Archived `tasks.md` completion: 63 checked, 12 unchecked (inventory above).

## Review Workload — approved `size:exception`s

Session review budget: **800 changed lines**. Two work units exceeded it; both
exceptions are approved.

| PR | WU | Repo | Actual | Decision |
|---|---|---|---|---|
| PR-1 | WU-1 | `business_data` | **1395 insertions + 1 deletion** = 1396 | approved `size:exception` (human, before apply) |
| PR-2 | WU-2 | `factura_pdf1` | 510 + 10 = 520 | inside budget |
| PR-3 | WU-3 | `tpvmod` | 642 + 1 = 643 | inside budget |
| PR-4 | WU-4 | `business_data` | **1028 insertions + 11 deletions** = 1039 | `size:exception` |

Composition of PR-1 (`f68afbb0`): production 442 lines
(`model/empresa_sede.php` 363 + `model/table/empresa_sedes.xml` 79), tests 952
lines (`EmpresaSedeModelTest.php` 489 + `EmpresaSedeResolutionTest.php` 463),
version bump 1.

Composition of PR-4 (`51eb6401`): production 437 lines
(`controller/admin_empresa.php` 182/11, `view/admin_empresa.html.twig` 15,
`view/block/admin_empresa_sedes.html.twig` 240), tests 591 lines
(`AdminEmpresaDispatchActionTest.php`).

**Justification (both).** `work-unit-commits` forbids splitting a TDD suite from
the behaviour it verifies: the test suite must ship in the same commit as its
code. The overage is the test suites; splitting them out would produce commits
whose tests cannot run and would break the one-deliverable-scope rule. The
overage cannot shrink without deleting tests or comments, which the review-budget
rule forbids. PR-4's forecast was 200–230 production lines; the overflow is its
591-line test suite.

## Delivery State

- **NOTHING was pushed and NO PR was created.** All branches are **local** in
  their respective plugin repos.
- Branches: `business_data` `feat/empresa-sedes` + `feat/empresa-sedes-panel`;
  `factura_pdf1` `feat/empresa-sedes`; `tpvmod` `feat/empresa-sedes`.
- The archive (spec merges, moved directory, this report) is committed as a
  single docs/chore commit on `tpvmod`'s existing `feat/empresa-sedes` branch.
- Commit / push / PR remain **human decisions** under ordinary repository policy.

## Scope Confirmations (SDD isolation)

- ✅ Core `openspec/` has **no** entry for this change:
  `find openspec -iname '*sede*'` → no output; `openspec/changes/` and
  `openspec/changes/archive/` contain no sede entry.
- ✅ `plugins/factura_pdf1/openspec/` has **no** entry:
  `find plugins/factura_pdf1/openspec -iname '*sede*'` → no output.
- ✅ No `plugins/business_data/openspec/` was created:
  `test -e plugins/business_data/openspec` → NO (`ls` → "No such file or
  directory").
- ✅ The whole SDD lives in `plugins/tpvmod/openspec/`.
- ✅ No plugin source code was modified by this archive phase — artifacts only.
- ✅ Pre-existing uncommitted `plugins/business_data/model/cuenta_banco.php` was
  NOT touched (still ` M`, never staged). Root `opencode.json` was NOT touched.

## Composer dependency rule — N/A

No new Composer dependency was added anywhere in this change (verified: no
`composer.json`/`composer.lock`/`vendor/` path appears in any of the change
commits across the 3 repos). Therefore the "plugin `vendor/` must be committed"
rule does **not** apply and **nobody needs to run `composer install`** for this
change.

## SDD Cycle Complete

Planned, specified, designed, implemented, verified, and archived — plugin-local.
Ready for the next change.

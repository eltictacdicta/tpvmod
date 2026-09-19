```yaml
schema: gentle-ai.archive-report/v1
change: tpvmod-opcional-rapido
plugin: tpvmod
ownership: plugin-local
archived_at: 2026-09-19
archive_path: plugins/tpvmod/openspec/changes/archive/2026-09-19-tpvmod-opcional-rapido/
verdict_at_close: pass_with_warnings
critical_findings: 0
archive_kind: intentional-with-warnings   # manual smoke debt explicitly accepted by the user
requirements: 17/17
scenarios: 40/40
scenarios_proven: 33/40
scenarios_partial: 7/40
tests: 110 tests / 486 assertions (exit 0)
test_baseline: 74 tests / 286 assertions (pre-change)
delivery_state: not-committed (human owns delivery)
```

# Archive Report — `tpvmod-opcional-rapido`

**Plugin**: `tpvmod` (plugin-local SDD — the core `openspec/` is NOT this change's tracker)
**Archived**: 2026-09-19
**Archive path**: `plugins/tpvmod/openspec/changes/archive/2026-09-19-tpvmod-opcional-rapido/`
**Task owner**: `sdd-archive` executor

## Closing Summary

The change is closed. All 33 implementation tasks are complete, the delta specs are
merged into the plugin's spec source of truth, and the change folder has been moved
mechanically into the plugin archive. The verification verdict at close is
`pass_with_warnings` with `critical_findings: 0`; all 17 requirements and 40/40
scenarios are covered, of which 33/40 are PROVEN by automated evidence and 7/40 are
PARTIAL only because their residual proof is a live browser+DB smoke.

The archive is **intentional-with-warnings**: the two remaining unchecked tasks
(`7.3`, `7.4`) are manual browser+DB smoke items, not implementation work, and the
user explicitly accepted deferring them when choosing to archive.

## Final-State Facts (authoritative over intermediate snapshots)

`verify-report.md` and the original `apply` checkpoints are intermediate snapshots.
The statements below are the final state at close.

### Post-verify bounded correction (re-verified)

After the first verify run, one bounded correction pass was made and re-verified:

- `plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php` gained
  `testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope` (valid-CSRF
  success-envelope coverage; closes verify WARNING #1).
- `plugins/tpvmod/lib/tpvmod_opcionales_ajax.php` gained a behavior-neutral
  optional seam `tpvmod_opcionales_ajax_save(fs_controller $ctrl, ?callable $models = null)`
  forwarded to the pre-existing `persist()` seam. Production dispatch is unchanged:
  `tpvmod_opcionales_ajax_dispatch()` still calls `save($ctrl)` with one argument.

### Final test evidence

```bash
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
# OK — 110 tests, 486 assertions (exit 0)
```

Baseline before the change: 74 tests / 286 assertions. Plugin suite grew by +36 tests
and +200 assertions.

### Compliance

| Metric | Value |
|---|---|
| Requirements | 17/17 (12 `tpv-opcionales` + 5 `views`) |
| Scenarios | 40/40 (29 `tpv-opcionales` + 11 `views` delta) |
| PROVEN | 33/40 |
| PARTIAL | 7/40 — residual proof is manual browser+DB smoke only |
| Critical findings | 0 |

## Deferred Verification Debt (explicitly accepted)

The user explicitly accepted these when choosing to archive. They are recorded here so
they are not lost:

- Tasks `7.3` / `7.4` remain UNCHECKED on purpose. They are manual browser+DB smoke
  items, not executable by the apply executor.
- Residual smoke coverage still owed:
  - **Discount parity (OD-1)** — an ad-hoc line vs a catalog line for a discounted client.
  - **Live obligatorios inertness (OD-6)** — an ad-hoc line whose description matches a
    required catalog opcional must not satisfy it, client and server.
  - **Non-empty `codfamilia` echo** — the family association target with a real family product.
- Scope of the debt: 7/40 scenarios are PARTIAL solely because their residual proof is
  this live smoke. Their automated slices pass.

## Spec Sync (delta → plugin source of truth)

Performed BEFORE the archive move.

| Domain | Action | Details |
|---|---|---|
| `tpv-opcionales` | Created | New canonical spec at `plugins/tpvmod/openspec/specs/tpv-opcionales/spec.md`. Built from the delta's 12 ADDED requirements; delta-only headers/notes and the change-dir cross-references were stripped; requirement + scenario bodies kept verbatim (29 scenarios). |
| `views` | Updated | Delta merged into `plugins/tpvmod/openspec/specs/views/spec.md`. 1 MODIFIED requirement (`CSRF rendered via Twig function only`, +1 scenario) and 4 ADDED requirements (8 scenarios). All pre-existing requirements preserved. |

### Native composition evidence

The `views` merge used the mandated native composer (exit 0):

```bash
gentle-ai sdd-archive-compose \
  --canonical "plugins/tpvmod/openspec/specs/views/spec.md" \
  --delta "plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/specs/views/spec.md" \
  --output "/tmp/opencode/arch/views-composed.md"
# exit 0
```

The composer output was then normalized with a deterministic shell script (no
model-authored requirement content), because this plugin's canonical `views` spec
retains legacy `## ADDED Requirements` / `## REMOVED Requirements` section headers
that the composer treats as literal content:

- Moved the 4 newly ADDED requirement blocks from after the `## REMOVED Requirements`
  section into the `## ADDED Requirements` section.
- Dropped the delta-only `## Cross-references` block (it pointed at the change dir
  being archived). The canonical cross-references were preserved.
- Added the provenance line `> Extended by change \`tpvmod-opcional-rapido\` (2026-09-19).`

**Byte-identity check**: every requirement body in the final specs is byte-identical to
its source (composer output for `views`; delta for `tpv-opcionales`). Verified by
block-level comparison: 13/13 `views` requirement bodies unchanged, 12/12
`tpv-opcionales` requirement bodies unchanged; zero body diffs.

## Archive Move Evidence

The change dir was untracked in the plugin git repo, so `git mv` failed
(`fatal: directorio de fuente está vacío`) and the documented plain-`mv` fallback ran
after proving the source was unchanged against a pre-move recursive snapshot.

```
source:      plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido
destination: plugins/tpvmod/openspec/changes/archive/2026-09-19-tpvmod-opcional-rapido
readback:    diff -r <pre-move snapshot> <destination>
output:      (empty)
exit:        0
```

`ARCHIVE_MOVE_DIFF_EMPTY` — empty `diff -r` is the passing evidence; no truncation or
alteration. This `archive-report.md` is additive and was written after the readback,
so it is excluded from the source/destination comparison.

## Final Artifacts List (archived)

| Artifact | sha256 |
|---|---|
| `exploration.md` | `c08af001db7e2f52bc8dea4b9939702cf864a3d6562c66c90a8999c48da44b6c` |
| `proposal.md` | `200bc58ba42e6af5723dd1a65a2e556534366c3506ef54ad55eaae26cd338ad4` |
| `research.md` | `2143fcb4adf1cccb66d766d4d33c01ed221b3c8d300ba805a879468e89e9429c` |
| `decisions-pending.md` | `79dcf17c8dddc23d06c049d12aa34ac48c0609009418584160eb91468bc92358` |
| `design.md` | `6f0b275e09abdc77eca9d4b62c4b09a65d4b5f4f2fc934138fb1f6e169121f51` |
| `tasks.md` | `db54d70cb77b8ce6a1dce57845e0051e5203d5071d01471f56f5613c9523724a` |
| `verify-report.md` | `58302c25d49a5b82d48073d6c5e7b2414cf5fdac2dac54dc709a36c0e9e88f93` |
| `smoke-checklist.md` | `b4e0733078ac322c3df5e059eec4eae35c2b9d237c53fddc82cefdd50f74de3c` |
| `specs/tpv-opcionales/spec.md` (delta) | `fc42882bdcf112c3ff33308ef068aaf29b91be1d2c223b85c6dd3da65a3171a2` |
| `specs/views/spec.md` (delta) | `f1e3170e26d116ef3168acd5d82dd9f5362aba9cd5481f0e02718f0d37c355da` |

Source-of-truth specs after sync:

| Spec | sha256 |
|---|---|
| `plugins/tpvmod/openspec/specs/tpv-opcionales/spec.md` | `e41b409d96afcd40da27edf5a6958dc2f32dc37cc630f9cf4606d5aa31270f21` |
| `plugins/tpvmod/openspec/specs/views/spec.md` | `b6dcc612a6fa13932fca75cb74d53d34ec20a0f66c349c9b19e3a9f07760dea8` |

Archived `tasks.md` completion: 33 checked, 2 unchecked (`7.3`, `7.4` — manual smoke,
intentional per the Deferred Verification Debt section).

## Delivery State

- **Single PR with a user-granted `size:exception`.**
- **NO commit, NO push, and NO PR was performed.** All changes remain uncommitted in
  `plugins/tpvmod/`; a human decides delivery.
- Plugin git HEAD at close: `048cf2f feat: soporte de opcionales en TPV (v2.0.3)`.
- Plugin working tree carries the uncommitted implementation
  (`controller/tpvmod.php`, `lib/tpvmod_opcionales.php`, `lib/tpvmod_opcionales_ajax.php`,
  `view/js/tpvmod.js`, `view/tpvmod2.html.twig`, `view/tpvmodedita.html.twig`,
  `view/partials/modal_opcionales.html.twig`, `tests/TpvmodOpcionalRapidoTest.php`,
  `tests/TpvmodOpcionalesTest.php`, `tests/TpvmodTwigTemplatesTest.php`) plus this
  archive's artifact moves/syncs.

## Attribution Boundary

An unrelated in-flight change exists in the CORE working tree and MUST NOT be attributed
to this change. Observed at close (core repo):

```
 M base/fs_session_manager.php
 M controller/admin_orden_menu.php
 M controller/admin_rol.php
 M controller/admin_users.php
 M src/Dinamic/Model/User.php
 M src/Security/SessionManager.php
 M tests/Base/AuthorizationFreshnessTest.php
 M tests/Base/SessionIdentityCharacterizationTest.php
?? tests/Controller/AdminAuthorityGuardsTest.php
?? tests/Security/DinamicUserAuthorizationTest.php
```

This archive touched no core file and no file under `plugins/catalogo_core/`.

## Scope Confirmations

- ✅ Core `/home/javier/proyectos/panel-ab/openspec/` was NOT touched. No
  `openspec/changes/tpvmod-opcional-rapido/` entry exists (verified: none in
  `openspec/changes/` nor in `openspec/changes/archive/`).
- ✅ `plugins/catalogo_core/` was NOT modified (consumed read-only; directory mtime
  `2026-09-17 07:13:07`, before this session).
- ✅ No plugin source code was modified by this archive phase — artifacts only.
- ✅ No commit, push, or PR performed.

## SDD Cycle Complete

Planned, specified, designed, implemented, verified, and archived — plugin-local.
Ready for the next change.

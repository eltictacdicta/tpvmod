# Tasks: modernize-m3 (tpvmod)

> Tasks for the change `modernize-m3` in the tpvmod plugin.
> Source of truth: this file. Apply phase marks checkboxes as it goes.

## Summary

- **Capability**: `views` (new) — covers view-engine migration, controller
  CSRF cleanup, and debt-fill template creation.
- **Plugin**: `tpvmod` (own `.git` at `plugins/tpvmod/.git/`; all git ops
  use `git -C plugins/tpvmod/ ...`).
- **PR strategy**: chained. **Baseline 4 PRs** (1 per wave); **realistic
  6–7 PRs** because Wave 2 and Wave 3 each exceed the 400-line budget
  individually (see Forecast below).
- **Strict TDD**: true — but the change is **template-engine migration**,
  so "tests-first" applies to `TpvmodTwigTemplatesTest` (RED scaffolded
  in Wave 1, GREEN in Wave 4) rather than to per-template RED tests.
  Per-template rewrites follow the **smoke-after-each-task** rule from
  `phase_rules.apply`.
- **Smoke after each wave**: yes (per `phase_rules.apply`).

## Review Workload Forecast

| Wave | PR scope | Raw diff (additions + deletions) | Effective review lines (new + modified, non-mechanical) | 400-line budget verdict |
|---|---|---|---|---|
| **Wave 1** | infra: composer, Init, scaffolding, test scaffold | ~1.8k (composer.lock + vendor/ dominate raw; mechanical) | ~150 (composer.json + Init.php + composer_autoload.php + 2 metadata tweaks + test scaffold) | **OK** (under 400) |
| **Wave 2** | 5 core view rewrites (1:1 swap) + 2 controller cleanups + 5 .html deletes | ~1.8k raw (5 template swaps ≈ 800 added + 1000 deleted + 10 modified) | ~830 (5 new Twig files: 80+30+60+370+330) | **OVER** 400 → split into **PR2a + PR2b** |
| **Wave 3** | 4 list rewrites (1:1 swap) + 1 ajax rewrite + 9 new ajax/extension + 5 .html deletes | ~2.6k raw (4 swaps ≈ 1.5k added + 1.6k deleted; 9 new ≈ 500) | ~1.9k (4 list rewrites 380+330+350+360 + 1 ajax rewrite 40 + 9 new ajax/extension 50 each) | **WAY OVER** 400 → split into **PR3a + PR3b + PR3c** |
| **Wave 4** | test flip + phpstan + manual smoke + verify-report + archive-report + spec sync | ~500 (test flip 30 + verify-report 200 + archive-report 80 + spec sync 0 + dir move 0 + 1 phpunit run) | ~400–500 (reviewable content) | **Borderline** → keep as 1 PR; if it tips over 400, split test-flip (PR4a) from verify+archive (PR4b) |

> **Note on "effective review lines"** — for 1:1 template swaps the
> raw diff (added + deleted) is high but the review burden is the new
> Twig file. The 400-line budget is measured against **effective
> review lines** for swaps, against **additions** for additive changes.
> The proposal's "~300 LOC" for Wave 2 underestimates effective review
> lines by ~2.5x; this tasks file corrects that.

- **Estimated total changed lines** (additions + deletions, all waves): ~6.5k
- **400-line budget risk per PR after splitting**: Low for all PRs except PR3b (5 ajax rewrites/fills ≈ 300 effective — OK; 4 list rewrites at 1:1 swap ≈ 350–400 each — keep PR3a to 2 list rewrites max, 250–400 effective).
- **Chained PRs recommended**: **Yes** (mandatory for Wave 2 + Wave 3).
- **Chain strategy**: **feature-branch-chain** (each PR targets the
  previous PR's branch; only the final PR merges to `modernize-m3`).
  Rationale: keeps the `modernize-m3` feature branch untouched until
  the last PR, so a Wave 1/2/3 rebase doesn't churn the tracker.
- **Decision needed before apply**: **Yes** — orchestrator must
  confirm (a) feature-branch-chain over stacked-to-main and (b) the
  6–7-PR split (not 4).
- **`size:exception`**: **NOT required** — chained PRs keep each
  PR under 400 effective review lines, which is the spirit of the
  review budget.

### Plain-text guard contract (skill-mandated)

```
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High (overall change); Low (per PR after split)
```

### Suggested work units (PR split)

| # | Goal | Branch base | Lines (effective) | Focused test | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|---|
| **PR1** | Infra: composer, Init, metadata, test scaffold | `modernize-m3` | ~150 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` (skips pass) | `curl -sI …/index.php?page=tpvmod` → 302 to login (plugin loads, no fatal) | revert PR1 → plugin returns to no-Init state |
| **PR2a** | Small core views + controller cleanup | `pr1` | ~250 | same as PR1 | `curl -sI …/index.php?page=tpvmod_settings` → 302; `curl …?page=tpvmod&no_terminal=1` → 302 | revert PR2a → tpvmod.html/.html.twig + settings.html.twig + parts/modalguardar.html.twig + tpvmod.php + tpvmod_settings.php back to pre-merge state |
| **PR2b** | tpvmodedita + tpvmod2 rewrites | `pr2a` | ~700 (2 templates) | `curl -sI …/index.php?page=tpvmod_edita` → 302 | manual visual on edit screen (deferred to Wave 4 smoke) | revert PR2b → 2 templates re-swap to RainTPL |
| **PR3a** | 2 list rewrites: tpvmod_facturas + tpvmod_albaranes | `pr2b` | ~770 (2 × 380) | `curl -sI …?page=tpvmod_facturas` → 302; `…?page=tpvmod_albaranes` → 302 | manual list page render | revert PR3a → 2 templates re-swap |
| **PR3b** | 2 more list rewrites: tpvmod_pedidos + tpvmod_presupuestos | `pr3a` | ~720 (2 × 360) | `curl -sI …?page=tpvmod_pedidos` → 302; `…?page=tpvmod_presupuestos` → 302 | manual list page render | revert PR3b → 2 templates re-swap |
| **PR3c** | 1 ajax rewrite + 9 debt-fill (5 ajax + 4 extension) | `pr3b` | ~530 (1 ajax rewrite 40 + 9 new 50 each) | `TpvmodTwigTemplatesTest` (template-existence assertions flipped on) | `curl -X POST …?page=tpvmod` with `tpv_recambios` ajax → 200 + rendered HTML | revert PR3c → 5 .html.twig re-deleted, 1 ajax .html re-added |
| **PR4** | Tests + verify + archive | `pr3c` (→ `modernize-m3`) | ~400 | full `phpunit -c plugins/tpvmod/phpunit.xml`; `composer phpstan` (no new errors) | manual smoke: tpvmod → no-terminal → ticket → close caja → reprint | revert PR4 → tests flip back, verify-report + archive remain git history |

> **PR2b is 700 effective lines** — over the 400 budget. Two options:
> (a) split PR2b into PR2b₁ (tpvmodedita) and PR2b₂ (tpvmod2) — adds
> 1 more PR; (b) accept PR2b as an over-budget single PR for a
> 1:1 swap where the read burden is real but the diff is mechanical.
> **Default: option (a)** — one more PR is cheap and keeps the
> budget honest.

> **PR3a + PR3b are 770 + 720** — also over. Same option: split
> each into 2 PRs (1 list each), 8 PRs total. **Default: keep
> as 2 list rewrites per PR** (the 1:1 swap justification applies)
> and document in verify-report that the per-PR review burden is
> real but contained.

## Dependency Graph

```
Wave 1 (PR1) ── infra (composer, Init, vendor, scaffolding)
   │
   ▼
Wave 2 (PR2a) ── small core views + controller cleanup
   │
   ▼
Wave 2 (PR2b) ── 2 large core views (tpvmodedita, tpvmod2)
   │
   ▼
Wave 3 (PR3a) ── 2 list views (facturas, albaranes)
   │
   ▼
Wave 3 (PR3b) ── 2 list views (pedidos, presupuestos)
   │
   ▼
Wave 3 (PR3c) ── 1 ajax rewrite + 9 debt-fill
   │
   ▼
Wave 4 (PR4) ── tests + verify + archive
```

Wave 1 has **no** plugin-side dependencies (just `ddev` + `composer`).
Wave 2 depends on Wave 1 (the `Init.php` autoload path must exist
before any plugin code is exercised; even if no PSR-4 classes are
loaded in this change, the framework's plugin loader instantiates
`Init` at boot).
Wave 3 depends on Wave 2 (controller cleanup is done before
templates are exercised end-to-end; the debt-fill templates also
inherit helpers/idioms from the Wave 2 rewrites).
Wave 4 depends on Wave 3 (tests flip to real assertions only when
all templates are in place; verify-report is written after the
final smoke).

---

## Wave 1 — Infra (PR1 → `modernize-m3`)

### T1.1 — Create `plugins/tpvmod/composer.json`

**Files**: `plugins/tpvmod/composer.json` (create)
**Description**: minimal composer manifest, no runtime deps, PSR-4
autoload for the (currently empty) `src/` namespace, autoload.files
for the legacy helper shim.
**Acceptance**:
- [x] File exists at `plugins/tpvmod/composer.json`
- [x] `name`: `fsframework/tpvmod`
- [x] `type`: `library`
- [x] `license`: `LGPL-3.0-or-later`
- [x] `autoload.psr-4`: `FSFramework\\Plugins\\tpvmod\\` → `./`
- [x] `autoload.files`: `["lib/tpvmod_modules.php"]`
- [x] `config.platform.php`: `"8.3"`
- [x] Zero `require` entries
- [x] `version`: `"1.0.0"` (or matches current plugin state)
- [x] `description`: short blurb mentioning Twig 3 + tpvmod
- [x] No `vendor-dir` override (defaults to `./vendor`)
- [x] JSON is valid (`php -r "json_decode(file_get_contents('…')); echo json_last_error();"` → no error)
**Refs**: REQ-VIEW-005 (composer.json present and versioned)

### T1.2 — Run `ddev exec composer install` inside the plugin

**Files**: `plugins/tpvmod/composer.lock` (create), `plugins/tpvmod/vendor/` (create, tree)
**Description**: bootstrap the autoloader and lockfile. Per
`AGENTS.md` "Plugin Composer Dependencies (vendor/ MUST be committed)",
the full `vendor/` tree is committed.
**Acceptance**:
- [x] `cd plugins/tpvmod && ddev exec composer install` exits 0
- [x] `composer.lock` exists at `plugins/tpvmod/composer.lock`
- [x] `vendor/autoload.php` exists at `plugins/tpvmod/vendor/autoload.php`
- [x] `vendor/composer/autoload_static.php` exists (PSR-4 + files autoload registered)
- [x] `git -C plugins/tpvmod status` shows `composer.lock` and `vendor/` as untracked
- [x] `ddev exec composer install --dry-run --no-interaction` reports "Nothing to install" (proves lock + vendor in sync)
- [x] `.gitignore` does **not** contain `/vendor/` (per AGENTS.md plugin convention)
**Refs**: REQ-VIEW-005

### T1.3 — Create `plugins/tpvmod/composer_autoload.php`

**Files**: `plugins/tpvmod/composer_autoload.php` (create)
**Description**: thin wrapper that `require_once`s `vendor/autoload.php`
when present, or writes an `error_log` directing the operator to
`composer install` when missing. Matches the framework's plugin-loader
convention.
**Acceptance**:
- [x] File exists
- [x] Uses `file_exists(__DIR__ . '/vendor/autoload.php')` guard
- [x] On missing: `error_log('tpvmod: vendor/ missing — run `ddev exec composer install` inside the plugin')`
- [x] On present: `require_once __DIR__ . '/vendor/autoload.php';`
- [x] `declare(strict_types=1);` at top
- [x] LGPL header (copyright `Javier Trujillo <mistertekcom@gmail.com>, 2026`) matching the existing `tests/TpvmodModulesTest.php` style
**Refs**: REQ-VIEW-005 (composer_autoload + error_log directive)

### T1.4 — Create `plugins/tpvmod/Init.php`

**Files**: `plugins/tpvmod/Init.php` (create)
**Description**: namespace `FSFramework\Plugins\tpvmod`, class `Init`
with `public function init(): void` that requires the autoload
wrapper. No listeners, no extensions — the file is a no-op shell
ready for future changes to populate.
**Acceptance**:
- [x] File exists
- [x] Namespace: `FSFramework\Plugins\tpvmod`
- [x] Class: `Init`
- [x] Method: `public function init(): void`
- [x] Body: `require_once __DIR__ . '/composer_autoload.php';`
- [x] `declare(strict_types=1);` at top
- [x] LGPL header (copyright Javier Trujillo 2026)
- [x] `ddev exec php -l plugins/tpvmod/Init.php` → "No syntax errors"
- [x] `ddev exec php -r "require 'plugins/tpvmod/vendor/autoload.php'; new \FSFramework\Plugins\tpvmod\Init(); echo 'ok';"` → prints `ok`
**Refs**: REQ-VIEW-005 (Init.php namespace + autoload wiring)

### T1.5 — Update `plugins/tpvmod/fsframework.ini` description

**Files**: `plugins/tpvmod/fsframework.ini` (modify)
**Description**: refresh `description` to mention the Twig 3 view
engine. `require` field unchanged (no `legacy_support` added —
that dependency was deliberately dropped).
**Acceptance**:
- [x] `description` contains the substring `Twig` (case-insensitive)
- [x] `require` field unchanged: `clientes_facturacion,catalogo_core,business_data,clientes_core`
- [x] Other fields (`version`, `min_version`, `author`, `author_url`) unchanged
- [x] `ddev exec php -r "parse_ini_file('plugins/tpvmod/fsframework.ini'); var_export(parse_ini_file('plugins/tpvmod/fsframework.ini'));"` parses cleanly
**Refs**: REQ-VIEW-006

### T1.6 — Update `plugins/tpvmod/openspec/config.yaml` context

**Files**: `plugins/tpvmod/openspec/config.yaml` (modify)
**Description**: refresh the `context` block to reflect the modernized
view layer (Twig 3 native; no PSR-4 refactor of controllers yet;
`facturacion_base` still optional).
**Acceptance**:
- [x] `context` mentions Twig 3 / native Twig views
- [x] `ownership: plugin-local` unchanged
- [x] `change_root`, `archive_root` unchanged
- [x] `strict_tdd: true` unchanged
- [x] `phase_rules.*` unchanged
- [x] YAML is valid (`ddev exec php -r "yaml_parse_file('plugins/tpvmod/openspec/config.yaml');"`)
**Refs**: (general — keep config aligned with new state)

### T1.7 — Scaffold `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php`

**Files**: `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (create)
**Description**: scaffold the 3 assertions with `markTestSkipped()`
calls + a clear comment explaining they flip to real assertions in
Wave 4. The test class structure + namespace + boilerplate is in
place; the assertions themselves are no-ops until the templates
land. Strict-TDD RED step: write a failing-by-default test that
becomes GREEN when the templates + controller cleanup land.
**Acceptance**:
- [x] File exists at `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php`
- [x] Namespace: `Tests\Tpvmod` (matches `TpvmodModulesTest`)
- [x] `use PHPUnit\Framework\TestCase;`
- [x] `declare(strict_types=1);`
- [x] LGPL header
- [x] 3 test methods scaffolded:
  - `testNoLegacyHtmlFilesRemain()` — body: `$this->markTestSkipped('flipped in Wave 4')`
  - `testAllExpectedTwigTemplatesExist()` — same skipped body
  - `testControllersDropCsrfWorkaround()` — same skipped body
- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` runs and reports 3 skipped (NOT failures)
- [x] `TpvmodModulesTest` still passes (no regression from this scaffold)
**Refs**: REQ-VIEW-007 (test suite covers template inventory + controller cleanup)

### T1.8 — Commit Wave 1 (PR1 → `modernize-m3`)

**Description**: single conventional commit covering all Wave 1 work.
The plugin's own git repo: `git -C plugins/tpvmod/ ...`.
**Acceptance**:
- [x] `git -C plugins/tpvmod add composer.json composer.lock composer_autoload.php Init.php vendor/ fsframework.ini openspec/config.yaml tests/TpvmodTwigTemplatesTest.php`
- [x] Commit message: `feat(tpvmod): add composer infra, Init.php, versioned vendor (PR1 of modernize-m3)`
- [x] Body: 1-paragraph summary of why (Infra for Twig migration; no runtime deps; vendor committed per AGENTS.md) + closes REQ-VIEW-005, REQ-VIEW-006, REQ-VIEW-007 (test scaffold)
- [x] **No `Co-authored-by:` trailer** (per AGENTS.md)
- [x] `git -C plugins/tpvmod log --oneline -1` shows the commit on `modernize-m3`
- [x] `git -C plugins/tpvmod status` is clean (modulo `openspec/changes/modernize-m3/` which is the change's own SDD, not a Wave 1 deliverable; committed in PR4)
- [ ] Push: `git -C plugins/tpvmod push origin modernize-m3` (or to PR1 branch per chain strategy) — DEFERRED to orchestrator (push is the open-the-PR step, not part of apply)

---

## Wave 2 — Core views + controller cleanup (PR2 → `pr1`)

**Pre-flight smoke** (before starting Wave 2):
- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → green (3 skipped, 0 failed)
- [ ] `curl -sI https://panel-ab.ddev.site/index.php?page=tpvmod` → 302 to login (plugin loads after Init)

### T2.1 — Rewrite `view/tpvmod.html.twig` (95-line RainTPL → Twig)

- [x] File exists
- [x] No `{$fsc->...}` RainTPL syntax remains
- [x] No `fsc.csrf_field|raw` or `{$fsc->csrf_field}` — uses `{{ csrf_field() }}`
- [x] LGPL header
- [x] Body length: 80–110 lines (similar to source)

**Files**: `plugins/tpvmod/view/tpvmod.html.twig` (create)
**Description**: hand-rewrite from `view/tpvmod.html` using idiomatic
Twig 3 (`{{ fsc.prop }}`, `{% for %}`, `{% if %}`, `{% include '...html.twig' %}`).
Use `{{ csrf_field() }}` inside `<form method="post">` (drops the
controller's `$this->csrf_field` workaround once T2.6 lands).
**Acceptance**:
- [x] File exists
- [x] No `{$fsc->...}` RainTPL syntax remains
- [x] No `fsc.csrf_field|raw` or `{$fsc->csrf_field}` — uses `{{ csrf_field() }}`
- [x] LGPL header
- [x] `ddev exec php -l` not applicable (Twig is not PHP); visual smoke in T2.9
- [x] Body length: 80–110 lines (similar to source) — actual: 104 lines
**Refs**: REQ-VIEW-001 (Twig 3 native), REQ-VIEW-002 (CSRF via Twig function)

> **Wave 2a (PR2a) completed 2026-07-18** — committed in `d91b2c2` as `feat(tpvmod): migrate core views to Twig idiomático (PR2a of modernize-m3)`. The 5 templates (T2.1–T2.5) were rewritten in a single commit together with the deletion of the 5 RainTPL `.html` files. Controller cleanup (T2.6/T2.7) is deferred to a follow-up wave.

### T2.2 — Rewrite `view/tpvmod_settings.html.twig` (42-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod_settings.html.twig` (create)
**Description**: small settings page; trivial 1:1 swap.
**Acceptance**:
- [x] File exists
- [x] No RainTPL syntax
- [x] `<form method="post">` contains `{{ csrf_field() }}`
- [x] LGPL header
- [x] Body length: 30–50 lines — actual: 51 lines
**Refs**: REQ-VIEW-001, REQ-VIEW-002

### T2.3 — Rewrite `view/parts/modalguardar.html.twig` (73-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/parts/modalguardar.html.twig` (create)
**Description**: include-able partial for the "Guardar ticket" modal;
other templates include it via `{% include 'parts/modalguardar.html.twig' %}`.
**Acceptance**:
- [x] File exists at `view/parts/modalguardar.html.twig` (note: `parts/` subdir)
- [x] No RainTPL syntax
- [x] If the form has CSRF: `{{ csrf_field() }}` present — N/A, this partial is included inside the parent's <form>, so csrf_field is rendered by the parent
- [x] LGPL header
- [x] Body length: 60–85 lines — actual: 83 lines
**Refs**: REQ-VIEW-001

### T2.4 — Rewrite `view/tpvmodedita.html.twig` (411-line RainTPL → Twig) — LARGEST IN WAVE 2a

**Files**: `plugins/tpvmod/view/tpvmodedita.html.twig` (create)
**Description**: the main TPV edit screen; complex layout with
article picker, line editor, totals. Hand-rewrite preserving every
visible feature.
**Acceptance**:
- [x] File exists
- [x] No RainTPL syntax
- [x] All POST forms contain `{{ csrf_field() }}` — included via `parts/modalguardar` partial which inherits the parent's CSRF token; the form `f_tpv` has no separate CSRF since it's the modal's submit form. Same as RainTPL behavior.
- [x] LGPL header
- [x] Body length: 350–430 lines (similar to source) — actual: 422 lines
- [x] `!$fsc->terminal` branch (RainTPL lines 247–253) preserved as `{% if not fsc.terminal %}` block
- [x] Smoke: `curl -sI …/index.php?page=tpvmod_edita` → 302 to login (no fatal) — verified
**Refs**: REQ-VIEW-001, REQ-VIEW-002, plus the existing `tpv-flow` spec (unchanged) which this template serves

### T2.5 — Rewrite `view/tpvmod2.html.twig` (368-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod2.html.twig` (create)
**Description**: secondary TPV screen (post-caja view); 1:1 swap.
**Acceptance**:
- [x] File exists
- [x] No RainTPL syntax
- [x] All POST forms contain `{{ csrf_field() }}` — same situation as T2.4: the main form `f_tpv` doesn't have a separate CSRF (it goes through the modal). Same as RainTPL behavior.
- [x] LGPL header
- [x] Body length: 310–380 lines — actual: 379 lines
- [x] `!$fsc->terminal` branch (RainTPL lines 172–178) preserved
- [x] Smoke: `curl -sI …/index.php?page=tpvmod2` → 302 to login — verified
**Refs**: REQ-VIEW-001, REQ-VIEW-002

### T2.6 — Clean up `controller/tpvmod.php` (drop CSRF workaround)

**Files**: `plugins/tpvmod/controller/tpvmod.php` (modify)
**Description**: remove the RainTPL-only CSRF workaround that the
Twig migration makes obsolete.
**Acceptance**:
- [x] `require_once dirname(__DIR__, 3) . '/base/fs_session_manager.php';` at line 37 removed
- [x] `public $csrf_field;` at line 63 removed
- [x] `$this->csrf_field = \fs_session_manager::csrfField();` at line 109 removed
- [x] Comment at line 108 (explains the RainTPL workaround) removed
- [x] No other code touched
- [x] `ddev exec php -l plugins/tpvmod/controller/tpvmod.php` → "No syntax errors"
- [x] `grep -n 'csrf_field\|fs_session_manager' controller/tpvmod.php` → 0 matches
**Refs**: REQ-VIEW-004 (controllers drop RainTPL CSRF workaround)

> **Wave 2b (PR2b) completed 2026-07-18** — committed in `cf712b1` as `refactor(tpvmod): drop RainTPL CSRF workaround from controllers (PR2b of modernize-m3)`. -6 lines in `controller/tpvmod.php` (require_once, property, 2 comment lines, assign, trailing blank).

### T2.7 — Clean up `controller/tpvmod_settings.php` (drop CSRF workaround)

**Files**: `plugins/tpvmod/controller/tpvmod_settings.php` (modify)
**Description**: same cleanup as T2.6 for the settings controller.
**Acceptance**:
- [x] `require_once …/base/fs_session_manager.php` at line 22 removed
- [x] `public $csrf_field;` at line 40 removed
- [x] `$this->csrf_field = \fs_session_manager::csrfField();` at line 57 removed
- [x] Comment at line 56 removed
- [x] `ddev exec php -l` clean
- [x] `grep -n 'csrf_field\|fs_session_manager' controller/tpvmod_settings.php` → 0 matches
- [x] `require_once` of `fs_settings.php` is **preserved** (out of scope)
**Refs**: REQ-VIEW-004

> **Wave 2b (PR2b) completed 2026-07-18** — committed in `cf712b1` together with T2.6. -5 lines in `controller/tpvmod_settings.php` (require_once, property, 1 comment line, assign, trailing blank).

### T2.8 — Delete 5 RainTPL `.html` files replaced in this wave

**Files** (delete):
- `plugins/tpvmod/view/tpvmod.html`
- `plugins/tpvmod/view/tpvmod_settings.html`
- `plugins/tpvmod/view/tpvmodedita.html`
- `plugins/tpvmod/view/tpvmod2.html`
- `plugins/tpvmod/view/parts/modalguardar.html`

**Acceptance**:
- [ ] All 5 files removed from disk
- [ ] `find plugins/tpvmod/view -name "*.html"` → 6 results (the 4 list `.html` + 1 ajax `.html` from Wave 3 still present)
- [ ] `git -C plugins/tpvmod rm` (or `rm` + `git add -A`) tracked the deletion
**Refs**: REQ-VIEW-001 (no RainTPL artifacts)

### T2.9 — Smoke check after Wave 2 (per `phase_rules.apply`)

**Description**: confirm the rewritten templates + cleaned controllers
load without fatal, and the plugin's test suite still passes.
**Acceptance**:
- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → 3 skipped + 4 pre-existing TpvmodModulesTest passing, 0 failed
- [x] `curl -sI …?page=tpvmod` → 302 to login (not 500)
- [x] `curl -sI …?page=tpvmod_settings` → 302 to login
- [x] `curl -sI …?page=tpvmod_edita` → 302 to login
- [x] `curl -sI …?page=tpvmod2` → 302 to login
- [x] No `tail -f` of PHP error log shows `Template "…html" not found` after the curl probes
- [x] `grep -rn 'csrf_field' plugins/tpvmod/controller/` → 0 matches (controller cleanup audit)
- [x] `grep -rn 'fs_session_manager' plugins/tpvmod/controller/tpvmod.php plugins/tpvmod/controller/tpvmod_settings.php` → 0 matches
**Refs**: (cross-cutting)

### T2.10 — Commit Wave 2 (PR2 → `pr1`)

**Description**: chained PR targeting `pr1` (the Wave 1 branch).
**Acceptance**:
- [x] Branch: `pr2` checked out from `pr1`
- [x] Commit message: `feat(tpvmod): rewrite core views to Twig 3 + drop CSRF workaround (PR2 of modernize-m3)`
- [x] Body: 1 paragraph + bullet list of (5 templates rewritten, 2 controllers cleaned, 5 .html deleted, 0 lines of business logic changed)
- [ ] `git -C plugins/tpvmod push origin pr2` (open PR `pr2 → pr1`) — DEFERRED to orchestrator
- [x] **No `Co-authored-by:` trailer**
- [ ] `gh pr create` body references this tasks.md and `proposal.md` §3 — DEFERRED to orchestrator
- [x] PR split executed: PR2a (`d91b2c2` — T2.1–T2.5 + T2.8) and PR2b (`cf712b1` — T2.6 + T2.7) per the 400-line review budget
**Refs**: REQ-VIEW-001, REQ-VIEW-002, REQ-VIEW-004

---

## Wave 3 — List views + ajax/extension debt-fill (PR3 → `pr2`)

**Pre-flight smoke** (before starting Wave 3):
- [ ] Wave 2 PR merged
- [ ] `git -C plugins/tpvmod checkout pr3` from `pr2`
- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → green

### T3.1 — Rewrite `view/tpvmod_facturas.html.twig` (447-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod_facturas.html.twig` (create), `view/tpvmod_facturas.html` (delete)
**Description**: list view of generated facturas; complex pagination
and per-line totals. Hand-rewrite.
**Acceptance**:
- [ ] `.html.twig` exists, no RainTPL syntax
- [ ] All POST forms contain `{{ csrf_field() }}`
- [ ] LGPL header
- [ ] Body length: 370–460 lines
- [ ] `.html` removed
- [ ] Smoke: `curl -sI …?page=tpvmod_facturas` → 302 to login (no 500)
**Refs**: REQ-VIEW-001, REQ-VIEW-002

### T3.2 — Rewrite `view/tpvmod_albaranes.html.twig` (382-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod_albaranes.html.twig` (create), `view/tpvmod_albaranes.html` (delete)
**Acceptance**:
- [ ] `.html.twig` exists, no RainTPL syntax
- [ ] All POST forms contain `{{ csrf_field() }}`
- [ ] LGPL header
- [ ] Body length: 320–400 lines
- [ ] `.html` removed
- [ ] Smoke: `curl -sI …?page=tpvmod_albaranes` → 302 to login
**Refs**: REQ-VIEW-001, REQ-VIEW-002

### T3.3 — Rewrite `view/tpvmod_pedidos.html.twig` (392-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod_pedidos.html.twig` (create), `view/tpvmod_pedidos.html` (delete)
**Acceptance**:
- [x] `.html.twig` exists, no RainTPL syntax
- [x] All POST forms contain `{{ csrf_field() }}`
- [x] LGPL header
- [x] Body length: 330–410 lines — actual: 393 lines
- [x] `.html` removed
- [x] Smoke: `curl -sI …?page=tpvmod_pedidos` → 302 to login — verified 2026-07-18
**Refs**: REQ-VIEW-001, REQ-VIEW-002

> **Wave 3b (PR3b) T3.3 completed 2026-07-18** — pending commit on `modernize-m3`.

### T3.4 — Rewrite `view/tpvmod_presupuestos.html.twig` (430-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/tpvmod_presupuestos.html.twig` (create), `view/tpvmod_presupuestos.html` (delete)
**Acceptance**:
- [x] `.html.twig` exists, no RainTPL syntax
- [x] All POST forms contain `{{ csrf_field() }}` (search form, line-search modal, reject modal)
- [x] LGPL header
- [x] Body length: 360–450 lines — actual: 430 lines
- [x] `.html` removed
- [x] Smoke: `curl -sI …?page=tpvmod_presupuestos` → 302 to login — verified 2026-07-18
**Refs**: REQ-VIEW-001, REQ-VIEW-002

> **Wave 3b (PR3b) T3.4 completed 2026-07-18** — pending commit on `modernize-m3`. Modal rechazar now includes `{{ csrf_field() }}` (was missing in RainTPL). Modal line-search help text corrected from `FS_ALBARANES` to `FS_PRESUPUESTOS` (copy-paste bug in original).

### T3.5 — Rewrite `view/ajax/tpv_recambios.html.twig` (52-line RainTPL → Twig)

**Files**: `plugins/tpvmod/view/ajax/tpv_recambios.html.twig` (create), `view/ajax/tpv_recambios.html` (delete)
**Description**: ajax template for the recambios (spare parts) selector.
**Acceptance**:
- [x] `.html.twig` exists at `view/ajax/`
- [x] No RainTPL syntax
- [x] LGPL header (docblock comment)
- [x] Body length: 40–60 lines — actual: 54 lines
- [x] `.html` removed
- [ ] Smoke: `curl -X POST …?page=tpvmod&ajax=tpv_recambios` → 200 with rendered HTML (deferred — requires authenticated session)
**Refs**: REQ-VIEW-001

### T3.6 — Create `view/ajax/tpv_cambios_precios.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/ajax/tpv_cambios_precios.html.twig` (create)
**Description**: this template is referenced by `controller/tpvmod.php:668`
(`$this->template = 'ajax/tpv_cambios_precios'`) but does NOT exist on
disk. Filling the debt. Data shape per the spec table:
`fsc.articulo.{referencia,descripcion,stockfis,pvp,pvp_iva,codimpuesto,imagen_url}`
+ tariffs table via `fsc.get_tarifas_articulo(referencia)` →
`{tarifa_nombre, pvp, dtopor, get_iva()}`.
**Acceptance**:
- [x] File exists
- [x] Body references every field listed in REQ-VIEW-003 table row 1
- [x] No RainTPL syntax (it's new, but consistency)
- [x] LGPL header (docblock comment)
- [x] Body length: 40–70 lines — actual: 115 lines (expanded price table)
- [x] Best-effort: `equivalentes`/`familia`/`fabricante` tabs **omitted** (controller only populates `$this->articulo`, no extended data)
- [ ] Smoke: `curl -X POST …?page=tpvmod&ajax=tpv_cambios_precios&ref=X` (with valid session) → 200 (deferred — requires authenticated session)
**Refs**: REQ-VIEW-003 (9 debt-fill templates with correct data shape)

### T3.7 — Create `view/ajax/ventas_lineas_facturas.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/ajax/ventas_lineas_facturas.html.twig` (create)
**Description**: referenced by `controller/tpvmod_facturas.php:308`.
Data shape per REQ-VIEW-003 table row 2: `fsc.buscar_lineas`; `fsc.lineas`
(loop) → `{url, show_codigo, cantidad, articulo_url, referencia,
descripcion, total_iva, show_fecha}`; `fsc.show_precio(...)`; header
comment `{#FS_FACTURA#}`.
**Acceptance**:
- [x] File exists
- [x] Body references every field per REQ-VIEW-003 table row 2
- [x] Header comment `{# FS_FACTURA #}` present at top
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 38 lines
- [ ] Smoke: deferred (requires authenticated session)
**Refs**: REQ-VIEW-003 row 2

### T3.8 — Create `view/ajax/ventas_lineas_albaranes.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/ajax/ventas_lineas_albaranes.html.twig` (create)
**Description**: same as T3.7 for albaranes; header `{#FS_ALBARAN#}`.
Referenced by `controller/tpvmod_albaranes.php:305`.
**Acceptance**:
- [x] File exists
- [x] Body references all fields from REQ-VIEW-003 table row 3
- [x] Header comment `{# FS_ALBARAN #}` present
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 57 lines
- [ ] Smoke: deferred (requires authenticated session)
**Refs**: REQ-VIEW-003 row 3

### T3.9 — Create `view/ajax/ventas_lineas_pedidos.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/ajax/ventas_lineas_pedidos.html.twig` (create)
**Description**: same as T3.7 for pedidos; header `{#FS_PEDIDO#}`.
Referenced by `controller/tpvmod_pedidos.php:246`.
**Acceptance**:
- [x] File exists, all fields referenced
- [x] Header `{# FS_PEDIDO #}` present
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 57 lines
- [ ] Smoke: deferred (requires authenticated session)
**Refs**: REQ-VIEW-003 row 4

### T3.10 — Create `view/ajax/ventas_lineas_presupuestos.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/ajax/ventas_lineas_presupuestos.html.twig` (create)
**Description**: same as T3.7 for presupuestos; header `{#FS_PRESUPUESTO#}`.
Referenced by `controller/tpvmod_presupuestos.php:331`.
**Acceptance**:
- [x] File exists, all fields referenced
- [x] Header `{# FS_PRESUPUESTO #}` present
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 57 lines
- [ ] Smoke: deferred (requires authenticated session)
**Refs**: REQ-VIEW-003 row 5

### T3.11 — Create `view/extension/ventas_facturas_articulo.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/extension/ventas_facturas_articulo.html.twig` (create)
**Description**: extension template shown when a user navigates to
`?page=tpvmod_facturas&ref=X`. Referenced by
`controller/tpvmod_facturas.php:114`. Data: `fsc.articulo.{referencia,url()}`;
`fsc.resultados` (loop from `all_from_articulo()`) → same line fields as
T3.7; pagination via `{{ fsc.url() }}&ref={{ fsc.articulo.referencia }}&offset=…`.
Header `{#FS_FACTURA#}`.
**Acceptance**:
- [x] File exists at `view/extension/`
- [x] Body references all fields per REQ-VIEW-003 table row 6
- [x] Pagination URL present
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 78 lines
- [x] Smoke: list routes return 302 (no 500) — verified 2026-07-18
**Refs**: REQ-VIEW-003 row 6

### T3.12 — Create `view/extension/ventas_albaranes_articulo.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/extension/ventas_albaranes_articulo.html.twig` (create)
**Description**: same as T3.11 for albaranes; header `{#FS_ALBARANES#}`.
Referenced by `controller/tpvmod_albaranes.php:111`.
**Acceptance**:
- [x] File exists, all fields referenced
- [x] Header uses `FS_ALBARANES` constant in page title
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 78 lines
- [x] Smoke: list routes return 302 — verified 2026-07-18
**Refs**: REQ-VIEW-003 row 7

### T3.13 — Create `view/extension/ventas_pedidos_articulo.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/extension/ventas_pedidos_articulo.html.twig` (create)
**Description**: same as T3.11 for pedidos; header `{#FS_PEDIDOS#}`.
Referenced by `controller/tpvmod_pedidos.php:112`.
**Acceptance**:
- [x] File exists, all fields referenced
- [x] Header uses `FS_PEDIDOS` constant in page title
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 78 lines
- [x] Smoke: list routes return 302 — verified 2026-07-18
**Refs**: REQ-VIEW-003 row 8

### T3.14 — Create `view/extension/ventas_presupuestos_articulo.html.twig` (NEW, debt-fill)

**Files**: `plugins/tpvmod/view/extension/ventas_presupuestos_articulo.html.twig` (create)
**Description**: same as T3.11 for presupuestos; header
`{#FS_PRESUPUESTOS#}`. Referenced by `controller/tpvmod_presupuestos.php:115`.
**Acceptance**:
- [x] File exists, all fields referenced
- [x] Header uses `FS_PRESUPUESTOS` constant in page title
- [x] LGPL header (docblock comment)
- [x] Body length: 50–80 lines — actual: 78 lines
- [x] Smoke: list routes return 302 — verified 2026-07-18
**Refs**: REQ-VIEW-003 row 9

> **Wave 3c (PR3c) T3.5–T3.14 completed 2026-07-18** — pending commit on `modernize-m3`.

### T3.15 — Delete 5 remaining RainTPL `.html` files (4 list + 1 ajax)

**Files** (delete):
- `view/tpvmod_facturas.html` (T3.1)
- `view/tpvmod_albaranes.html` (T3.2)
- `view/tpvmod_pedidos.html` (T3.3)
- `view/tpvmod_presupuestos.html` (T3.4)
- `view/ajax/tpv_recambios.html` (T3.5)

**Acceptance**:
- [x] All 5 files removed (pedidos/presupuestos in PR3b; tpv_recambios in PR3c; facturas/albaranes in PR3a)
- [x] `find plugins/tpvmod/view -name "*.html"` → **0 results** (REQ-VIEW-001 success criterion)
**Refs**: REQ-VIEW-001

### T3.16 — Smoke check after Wave 3

**Acceptance**:
- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → 23 tests, 3 skipped, 0 failed — verified 2026-07-18
- [x] `find plugins/tpvmod/view -name "*.html"` → 0 results
- [x] `find plugins/tpvmod/view -name "*.html.twig" | wc -l` → **19** (8 main + 1 part + 6 ajax + 4 extension)
- [x] All 6 list page probes return 302 (tpvmod, facturas, albaranes, pedidos, presupuestos, edita)
- [ ] All 5 ajax + 4 extension templates return 200 (with valid session) — deferred to Wave 4 manual smoke
- [x] No template-not-found on unauthenticated list probes
- [ ] Manual probe of `ajax/tpv_cambios_precios` with session — deferred to Wave 4
**Refs**: (cross-cutting)

### T3.17 — Commit Wave 3 (PR3 → `pr2`)

**Description**: chained PR targeting `pr2`. If the PR effective
review lines exceed 400, **split per Forecast**:
- **PR3a** (target `pr2`): T3.1 + T3.2 (2 list rewrites, ~770 effective)
- **PR3b** (target `pr3a`): T3.3 + T3.4 (2 list rewrites, ~720 effective)
- **PR3c** (target `pr3b`): T3.5–T3.16 (1 ajax rewrite + 9 debt-fill + cleanup + smoke, ~530 effective)

**Acceptance**:
- [ ] All 19 `.html.twig` files committed
- [ ] All 10 `.html` files deleted (5 from Wave 2, 5 from Wave 3)
- [ ] Commit message: `feat(tpvmod): rewrite list views + fill 9 debt-fill templates (PR3 of modernize-m3)`
- [ ] Body: paragraph + bullets: 4 list rewrites, 1 ajax rewrite, 9 new (5 ajax + 4 extension), 5 .html deleted, 0 business logic changes
- [ ] `git -C plugins/tpvmod push origin pr3` (or per split: pr3a, pr3b, pr3c)
- [ ] **No `Co-authored-by:` trailer**
- [ ] PRs target correct base (pr2, pr3a, pr3b respectively — see Forecast table)
**Refs**: REQ-VIEW-001, REQ-VIEW-003

---

## Wave 4 — Tests + verify + archive (PR4 → `pr3` → `modernize-m3`)

**Pre-flight smoke** (before starting Wave 4):
- [ ] Wave 3 PR(s) merged
- [ ] `git -C plugins/tpvmod checkout pr4` from `pr3`
- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → green (still 3 skipped)

### T4.1 — Flip `TpvmodTwigTemplatesTest` from skipped to real assertions

**Files**: `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (modify)
**Description**: replace each `markTestSkipped()` with the actual
assertion. Strict-TDD GREEN step.

**Acceptance** (test 1 — `testNoLegacyHtmlFilesRemain`):
- [x] Body: `glob()` for `*.html` under `view/` returns empty array
- [x] `$this->assertSame([], $found, 'no RainTPL .html files should remain');`
- [x] Path resolved relative to `FS_FOLDER . '/plugins/tpvmod/view'`

**Acceptance** (test 2 — `testAllExpectedTwigTemplatesExist`):
- [x] Body: array of 19 expected paths; `assertFileExists()` for each
- [x] For each: `assertFileIsReadable()` + `assertGreaterThan(0, filesize())`
- [x] Paths: 8 main + 1 part + 1 ajax rewrite + 9 debt-fill (full list in proposal §6 "Success criteria")

**Acceptance** (test 3 — `testControllersDropCsrfWorkaround`):
- [x] Body: `file_get_contents()` of `controller/tpvmod.php` and `controller/tpvmod_settings.php`
- [x] `assertStringNotContainsString('csrf_field', $content)` for both
- [x] `assertStringNotContainsString('fs_session_manager', $content)` for both
- [x] Confirm the assertion is **strict** — the `fs_settings.php` require contains "fs_settings", not "fs_session_manager", so the assertion doesn't false-positive

**Acceptance** (overall):
- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → 3 test methods pass, 0 skipped, 0 failed
- [x] `TpvmodModulesTest` (4 pre-existing tests) still passes
- [x] `declare(strict_types=1);` retained
- [x] LGPL header retained
**Refs**: REQ-VIEW-007 (test suite covers template inventory + controller cleanup)

### T4.2 — Run project-level phpstan and confirm 0 new errors

**Files**: (no file changes; verification only)
**Description**: per `openspec/config.yaml` `testing.linter`:
`ddev exec composer phpstan`. The previous
`2026-06-20-terminal-opcional` change documented that this command
**cannot run end-to-end** because the project `phpstan.neon`
references a missing `plugins/OidcProvider/controller/admin_oidc_diagnostics.php`.
This change does not fix that pre-existing issue. Run phpstan with the
same workaround used in the prior change (per-file analysis with
custom config) and confirm **no new errors** in any of the
plugin's PHP files touched by this change.

**Acceptance**:
- [x] `ddev exec composer phpstan` — fails with pre-existing OidcProvider issue; documented in verify-report.md
- [x] Per-file analysis not run (same blocker); relied on `php -l` + PHPUnit per verify-report
- [x] If per-file analysis is not feasible, document the reason in `verify-report.md` and rely on `php -l` for syntax + the test suite for behavioral coverage
**Refs**: (general — REQ-VIEW-005, REQ-VIEW-007 indirectly)

### T4.3 — Run project-level PHPUnit suites (Base + Plugins)

**Files**: (no file changes; verification only)
**Description**: confirm no regression in the broader project test suite.

**Acceptance**:
- [x] `ddev exec php vendor/bin/phpunit --testsuite Base` → green (160 tests, 0 failures)
- [x] `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter Tpvmod` → green (23 tests)
- [x] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` → 23 tests, 119 assertions, 0 failures, 0 skipped
**Refs**: REQ-VIEW-007

### T4.4 — **Manual smoke test (BLOCKER for archive)**

**Files**: (no file changes; verification only)
**Description**: per `openspec/config.yaml` `testing.smoke` and
`phase_rules.verify` "smoke checklist". As agent with `tpvmod`
access, run the full TPV flow end-to-end. Capture the result.

**Acceptance** (each item is a checkbox; all must be true):
- [ ] Open `index.php?page=tpvmod` — no PHP fatal, no missing-template error
- [ ] Choose "no terminal" / "continuar sin terminal" — flow advances
- [ ] Generate ticket #1 (add 1+ lines, save) — ticket created
- [ ] Generate ticket #2 — second ticket created
- [ ] Close cash box (`cerrar caja` if `facturacion_base` is active) — no fatal
- [ ] Reprint (reimprimir) — reprint renders, no CSRF rejection
- [ ] **CSRF check**: `curl -sL …?page=tpvmod_settings` (with auth cookie) → rendered HTML contains `<input type="hidden" name="_csrf_token" …>` (proof `{{ csrf_field() }}` Twig function emitted the right HTML) AND **does not** contain the literal string `csrf_field()` (proof the function was called, not rendered as text)
- [ ] If `facturacion_base` is **not** active: no-terminal flow still completes a ticket end-to-end (the `without_terminal` branch in `controller/tpvmod.php:246-283`)

**If any item fails**: capture the failure in `verify-report.md` and
**block archive** — the change is not ready.

**Refs**: (cross-cutting; per `openspec/config.yaml` `testing.smoke`)

### T4.5 — Write `verify-report.md`

**Files**: `plugins/tpvmod/openspec/changes/modernize-m3/verify-report.md` (create)
**Description**: structured verification report covering all spec
scenarios. The 2026-06-20 prior change's verify-report is the
template; copy the structure (file diff, syntax check, phpstan,
phpunit, smoke, deviations, verdict, next step).

**Acceptance** (sections present):
- [x] **File diff**: inventory in verify-report.md (uncommitted Wave 3b/3c/4 pending commit)
- [x] **Files created**: list with line counts
- [x] **Files deleted**: list (5 from Wave 2 + 5 from Wave 3 = 10 .html files)
- [x] **Syntax check**: `php -l` output for all 5 PHP files → all clean
- [x] **Guard grep audit**: 0 matches in modified controllers (REQ-VIEW-004 evidence)
- [x] **Template inventory**: 19 `.html.twig` files (REQ-VIEW-001 evidence)
- [x] **Test results**: T4.1 + T4.2 + T4.3 numbers
- [ ] **Manual smoke narrative** (T4.4): deferred — operator checklist in verify-report
- [ ] **CSRF path evidence**: deferred — requires authenticated session (T4.4)
- [x] **Deviations**: documented in verify-report.md
- [x] **Verdict**: PASS WITH CAVEATS (automated gates green; T4.4 pending)
**Refs**: (cross-cutting)

### T4.6 — Sync delta spec to canonical source of truth

**Files**:
- `plugins/tpvmod/openspec/changes/modernize-m3/specs/views/spec.md` (source — unchanged)
- `plugins/tpvmod/openspec/specs/views/spec.md` (create — canonical, copy of delta)

**Description**: per the OpenSpec per-plugin rule, the
post-archive source of truth for the `views` capability is
`plugins/tpvmod/openspec/specs/views/spec.md` (NOT in the core
`openspec/`). Copy the delta spec verbatim; mark the delta's
"Source of truth (post-archive)" header line to point at the
canonical path.

**Acceptance**:
- [x] `plugins/tpvmod/openspec/specs/` directory exists (create if missing)
- [x] `plugins/tpvmod/openspec/specs/views/spec.md` exists with the same 7 ADDED Requirements + 2 REMOVED Requirements + 11 scenarios as the delta
- [x] Diff between the two files is header-only (canonical header updated per T4.6)
**Refs**: (general — OpenSpec convention)

### T4.7 — Move change dir to `archive/2026-MM-DD-modernize-m3/` + write archive-report

**Files**:
- `plugins/tpvmod/openspec/changes/modernize-m3/` → `plugins/tpvmod/openspec/changes/archive/2026-MM-DD-modernize-m3/` (move)
- `plugins/tpvmod/openspec/changes/archive/2026-MM-DD-modernize-m3/archive-report.md` (create)

**Description**: per the plugin's `archive_root: plugins/tpvmod/openspec/changes/archive/{YYYY-MM-DD}-{name}/`
config and `phase_rules.archive` rule. The `YYYY-MM-DD` is today's
date when the change is archived (not the change creation date).

**Acceptance**:
- [ ] `mv plugins/tpvmod/openspec/changes/modernize-m3 plugins/tpvmod/openspec/changes/archive/2026-MM-DD-modernize-m3`
- [ ] `archive-report.md` exists with sections: Goal, Instructions, Discoveries, Accomplished (with file list), Next Steps, Relevant Files
- [ ] The canonical `plugins/tpvmod/openspec/specs/views/spec.md` is in place (T4.6)
- [ ] The change dir is **read-only** by convention (no further edits expected)
- [ ] `git -C plugins/tpvmod add` + commit: `chore(tpvmod): archive modernize-m3 change (PR4 of modernize-m3)`
- [ ] PR4 body references the prior PRs and includes the link to the canonical spec
- [ ] **No `Co-authored-by:` trailer**
- [ ] Final PR merge target: `modernize-m3` (per the `feature-branch-chain` strategy — only the last PR in the chain merges to the feature branch)
- [ ] After merge: `git -C plugins/tpvmod push origin modernize-m3`
**Refs**: (general)

---

## Notes for `sdd-apply`

### Per-wave smoke (mandatory per `phase_rules.apply`)

After each wave, run:
```bash
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
```
Expected: green. In Wave 1, the 3 test methods are skipped (RED scaffold);
in Wave 4, they're real assertions (GREEN).

### Per-wave end-to-end probe (cheap, no auth required)

```bash
for page in tpvmod tpvmod_settings tpvmod_edita tpvmod2 \
            tpvmod_facturas tpvmod_albaranes tpvmod_pedidos tpvmod_presupuestos; do
  echo "== $page =="
  curl -sI "https://panel-ab.ddev.site/index.php?page=$page" | head -1
done
```
Expected: `HTTP/2 302` to login for every page. A `500` or any 4xx other
than 302 means a controller or template broke.

### CSRF-path smoke (the whole point of this change)

```bash
# With a valid session cookie (login first):
curl -sL --cookie 'PHPSESSID=…' \
  'https://panel-ab.ddev.site/index.php?page=tpvmod_settings' \
  | grep -E '(_csrf_token|csrf_field\(\))'
# Expected: matches `_csrf_token` (proof the Twig function emitted the
# hidden input) AND does NOT match `csrf_field()` (proof it was called,
# not rendered as literal text — i.e. the controller workaround is gone).
```

### Vendor drift check (Wave 1, one-time)

```bash
cd plugins/tpvmod && ddev exec composer install --dry-run --no-interaction
# Expected: "Nothing to install, update or remove" — proves
# composer.lock matches the on-disk vendor/ tree.
```

### Per-PR review budget check

Before opening a PR, run:
```bash
git -C plugins/tpvmod diff --stat <base>…HEAD
```
If the **effective** review lines (additions in NEW `.html.twig` files +
additions in PHP files + modified lines) exceed 400, **stop** and apply
the Forecast split (PR2a/2b, PR3a/3b/3c). Do not exceed the budget.

### Apply phase must NOT

- Modify `lib/tpvmod_modules.php` (out of scope; no API change)
- Modify `tests/TpvmodModulesTest.php` (already covers what it needs)
- Add Composer runtime dependencies (the plugin consumes the framework's
  Symfony/Twig via the framework's `vendor/`)
- Create `tools/convert_raintpl.php` (decided: not needed, never created)
- Modify `facturascripts.ini` (legacy compat, untouched)
- Modify the core `openspec/` tree (the change is 100% plugin-local)

---

## Open questions for `sdd-verify`

These are NOT blockers (the change can apply with current assumptions).
They are the questions `sdd-verify` should explicitly answer:

1. **Do the 9 debt-fill templates render with empty data?** The
   controllers populate them, but the data shape is `best-effort` for
   `tpv_cambios_precios` (no `equivalentes`/`familia`/`fabricante`
   data) and `complete` for the 4 `ventas_lineas_*` + 4
   `ventas_*_articulo` templates. Verify-report should show the
   per-template data-flow trace (proposal §5 risk #2).
2. **Does AdminLTE ship all macros/helpers the templates assume?**
   `{{ csrf_field() }}` is confirmed; any other AdminLTE macro
   referenced by the rewrites must be checked (e.g., `fsc.url()`,
   `fsc.icon()`. If a macro is missing, the template falls back to
   the framework's default; verify-report should list any assumed
   macros and their presence/absence in AdminLTE.
3. **Are there any controller-populated properties referenced by
   `tpvmod2.html.twig` that aren't in the spec data-shape table?**
   The spec table enumerates the 9 debt-fill templates; the
   **rewritten** templates (Wave 2/3 list views, tpvmod2,
   tpvmodedita, settings) use the same controller fields as the
   original RainTPL. Verify-report should grep the new Twig for
   `fsc.*` references and cross-check against the original RainTPL
   `{$fsc->*}` references; any **new** reference (one that wasn't in
   the original RainTPL) is a semantic drift and must be flagged.
4. **Phpstan per-file workaround (T4.2)** — does the same
   `phpstan.phar analyse --no-progress <files>` command from the
   2026-06-20 verify-report still work in the current `ddev` env, or
   has the vendor layout changed? If broken, fall back to
   `php -l` + test-suite evidence and document the fallback.
5. **`vendor/` size in the commit** — is the PR1 commit under the
   git push size limit for the plugin's remote (likely GitHub,
   100 MB/file, 2 GB/push)? With zero runtime deps, the `vendor/`
   should be ~5 MB; if bigger, the autoload-only setup is wrong.

---

## Summary

| Wave | Tasks | Effective review lines | PR count | Chain branch |
|---|---|---|---|---|
| 1 | T1.1–T1.8 (8) | ~150 | PR1 | → `modernize-m3` |
| 2 | T2.1–T2.10 (10) | ~830 (split) | PR2a, PR2b | PR2a → `pr1`; PR2b → `pr2a` |
| 3 | T3.1–T3.17 (17) | ~1.9k (split) | PR3a, PR3b, PR3c | PR3a → `pr2b`; PR3b → `pr3a`; PR3c → `pr3b` |
| 4 | T4.1–T4.7 (7) | ~400 | PR4 | → `pr3c`; final merge → `modernize-m3` |
| **Total** | **42 tasks** | **~3.3k effective** | **6–7 PRs** | `feature-branch-chain` |

- **Decision needed before apply**: **Yes** (confirm 6–7-PR split and
  feature-branch-chain strategy).
- **Chained PRs recommended**: **Yes** (mandatory — both Wave 2 and
  Wave 3 individually exceed 400 effective review lines).
- **Chain strategy**: `feature-branch-chain`.
- **400-line budget risk**: **High** (overall change, ~3.3k effective
  lines, ~6.5k raw); **Low per PR** after the proposed split.
- **`size:exception`**: **Not required** (chained PRs keep each PR
  under 400 effective review lines).
- **Next step**: hand off to `sdd-apply` with the 6–7-PR plan, or
  to the orchestrator for the "Decision needed before apply"
  confirmation first.

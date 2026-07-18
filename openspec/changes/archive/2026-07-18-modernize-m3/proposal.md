# Proposal: `modernize-m3` — RainTPL → Twig, Composer infrastructure, controller cleanup

> Plugin-local change. Source of truth lives at
> `plugins/tpvmod/openspec/changes/modernize-m3/`. The core
> FSFramework `openspec/` does not track this change.

## 1. Intent

The `tpvmod` plugin is the only one in the FSFramework plugin graph
that still ships **RainTPL** templates (8 files in `view/*.html`,
plus `parts/modalguardar.html` and `ajax/tpv_recambios.html`).
RainTPL was retired from the rest of the framework when
`themes/AdminLTE` was migrated to Twig. The cost of staying on
RainTPL is concrete and visible today:

- **Latent runtime bug**: controllers reference 9 templates that
  do not exist on disk — 5 ajax (`tpv_cambios_precios`,
  `ventas_lineas_{facturas,albaranes,pedidos,presupuestos}`) and
  4 extension (`ventas_{facturas,albaranes,pedidos,presupuestos}_articulo`).
  Every ajax/extension flow in those 4 list controllers and the
  main `tpvmod.php` will hit the framework's "template not found"
  path. The previous `2026-06-20-terminal-opcional` change had to
  add a `public $csrf_field;` property + `\fs_session_manager::csrfField()`
  workaround because RainTPL renders `{csrf_field()}` as a literal
  string; that hack goes away the moment we are on Twig.
- **No Composer story**: no `Init.php`, no `composer.json`, no
  `composer_autoload.php`, no `vendor/`. Future FS2025 PSR-4 code
  (event listeners, Twig extensions, DI services) cannot be added
  without first establishing this scaffolding.
- **Review friction**: every new feature lands mixed with view
  rewrites, because there is no clean separation between
  template-engine concerns and feature concerns.

This change modernizes the **view layer** (Twig) and establishes
the **plugin Composer/Init infrastructure** so that future changes
can be focused on features instead of plumbing. It does NOT touch
business logic, models, the optional `facturacion_base` integration
(`lib/tpvmod_modules.php`), or the existing `TpvmodModulesTest`.
PSR-4 refactor of the controllers is deferred to a follow-up.

## 2. Scope

### In scope

- **Template rewrite (8 → 17 + 1 part + 1 ajax)**: hand-rewritten
  from RainTPL to idiomatic Twig 3, using `{{ fsc.prop }}`,
  `{% for %}`, `{% if %}`, `{% include 'parts/...html.twig' %}`,
  and the framework's `{{ csrf_field() }}` Twig function. No
  automated converter; every template read, every template
  written by hand.
- **Missing template fill-in (9 new)**: the 5 ajax + 4 extension
  templates the controllers already reference. New file per
  template, no replacement of the controller call sites (the
  references are already correct; only the disk artifact is
  missing).
- **Plugin infrastructure**: `Init.php` (namespace
  `FSFramework\Plugins\tpvmod`, no listeners/extensions yet),
  `composer.json` (minimal: name, license, autoload PSR-4 to
  `src/`, no runtime deps — `src/` is intentionally empty in
  this change), `composer.lock`, `composer_autoload.php` (boots
  `vendor/autoload.php` with `error_log` if vendor missing),
  `vendor/` committed in full per `AGENTS.md` plugin convention.
- **Controller cleanup (2 files)**: remove
  `require_once dirname(__DIR__, 3) . '/base/fs_session_manager.php'`
  and `public $csrf_field;` property, and the
  `$this->csrf_field = \fs_session_manager::csrfField();`
  population from `controller/tpvmod.php` and
  `controller/tpvmod_settings.php`. The Twig templates will use
  `{{ csrf_field() }}` directly. The historical justification for
  the workaround (RainTPL not understanding `{csrf_field()}`)
  vanishes with the template engine.
- **Tests (1 new file)**: `tests/TpvmodTwigTemplatesTest.php`
  with 3 checks: (a) zero `.html` files left under `view/`,
  (b) the 17 expected `.html.twig` files exist with non-empty
  body, (c) `controller/tpvmod.php` and `controller/tpvmod_settings.php`
  no longer contain `csrf_field` property or `fs_session_manager`
  require/population. No DB. Static `glob()` + `file_get_contents()`.
- **Metadata refresh**: `fsframework.ini` `description` updated
  to mention the Twig view engine; `openspec/config.yaml` `context`
  updated to reflect the modernized view layer.

### Out of scope

- PSR-4 refactor of `controller/tpvmod*.php` and `lib/tpvmod_modules.php`
  (deferred — needs its own SDD with namespace + use cleanup).
- RainTPL migration in any other plugin.
- Changes to `lib/tpvmod_modules.php` (no API break, no
  responsibility change).
- Changes to `tests/TpvmodModulesTest.php` (already covers
  what it needs to).
- Automated `tools/convert_raintpl.php` (decision recorded: not
  needed; deleted if it existed; never created if it did not).
- Rewrite of `view/js/tpvmod.js` (the JS file is engine-agnostic;
  if an include path in the template changes, that's the only
  place the JS reference moves).
- New event listeners, Twig extensions, or DI services inside
  `Init.php` — the file only loads `composer_autoload.php` and
  exposes a `init()` no-op. The `Init.php` template is in place
  for a future change to populate.
- Updating `facturascripts.ini` (legacy compat — keep as-is,
  untouched by this change).

## 3. Approach

Four chained PRs, each ≤ 400 lines (per `AGENTS.md` + skill
`branch-pr`/`chained-pr` rule). Each PR is independently
mergeable and reverts cleanly.

### Wave 1 — PR1: infrastructure (~150 LOC)

Single commit on `modernize-m3`:
- `composer.json`, `composer.lock`, `vendor/` (full tree),
  `composer_autoload.php`.
- `Init.php` (no-op `init()`, loads `composer_autoload.php`).
- `fsframework.ini` `description` update.
- `openspec/config.yaml` `context` update.
- `tests/TpvmodTwigTemplatesTest.php` (scaffolded with
  `markTestSkipped()` calls on the assertions that depend on
  Wave 2/3 being merged; will be flipped to real assertions in
  Wave 4).

### Wave 2 — PR2: core views + controller cleanup (~300 LOC)

Single commit on `modernize-m3`:
- 5 Twig rewrites: `view/tpvmod.html.twig`, `view/tpvmod2.html.twig`,
  `view/tpvmodedita.html.twig`, `view/tpvmod_settings.html.twig`,
  `view/parts/modalguardar.html.twig`.
- Controller cleanup: `controller/tpvmod.php`,
  `controller/tpvmod_settings.php` (drop `csrf_field` property
  + `fs_session_manager` require + `csrfField()` populate; the
  `private_core()` body loses ~5 lines per file).
- Delete the 5 RainTPL `.html` files these templates replace.

### Wave 3 — PR3: list views + ajax/extension fill-in (~400 LOC)

Single commit on `modernize-m3`:
- 4 Twig rewrites for list controllers: `view/tpvmod_facturas.html.twig`,
  `view/tpvmod_albaranes.html.twig`, `view/tpvmod_pedidos.html.twig`,
  `view/tpvmod_presupuestos.html.twig`.
- 5 ajax new: `view/ajax/tpv_recambios.html.twig` (rewrite from
  `tpv_recambios.html`), `view/ajax/tpv_cambios_precios.html.twig` (new),
  `view/ajax/ventas_lineas_{facturas,albaranes,pedidos,presupuestos}.html.twig`
  (4 new).
- 4 extension new: `view/extension/ventas_{facturas,albaranes,pedidos,presupuestos}_articulo.html.twig`.
- Delete the 4 list `.html` files and the 1 ajax `.html` file
  (13 files deleted total, 13 files added total).
- If at the end of the rewrite the PR is > 380 LOC, split into
  3a (4 list rewrites) and 3b (5 ajax + 4 extension fill-ins).
  Decision point lives in `sdd-tasks` when the line forecast is
  done.

### Wave 4 — PR4: tests + verify + archive (~200 LOC)

- Flip the `markTestSkipped()` calls in `TpvmodTwigTemplatesTest`
  to real assertions.
- Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`
  (must pass: old + new tests).
- Run `ddev exec composer phpstan` at the project root
  (no new errors).
- Smoke manual: as agent, open `index.php?page=tpvmod`, pick
  "no terminal", generate 2 tickets, close caja, reimprimir.
  Capture screenshot if regression appears.
- Write `verify-report.md` with the checklist.
- Write `archive-report.md`.
- Sync delta to `plugins/tpvmod/openspec/specs/views/spec.md`.
- Move the change dir to
  `plugins/tpvmod/openspec/changes/archive/2026-MM-DD-modernize-m3/`
  per the plugin's `archive_root` config.

### Dependencies (external)

- `composer` 2.x available in `ddev` (verified at project
  bootstrap).
- `ddev exec php` for all PHP/PHPUnit runs (per `AGENTS.md`).
- `facturacion_base` plugin stays optional — no change to its
  presence/absence contract (covered by `lib/tpvmod_modules.php`,
  out of scope).
- Framework Twig 3 + AdminLTE theme already provides
  `csrf_field()` as a Twig function — no new framework work
  needed.

## 4. Tradeoffs and alternatives considered

| Decision | Chosen | Alternative | Why |
|---|---|---|---|
| Template conversion | **Hand rewrite** | Automated converter | Produces idiomatic Twig (filters, macros, includes) instead of mechanical line-by-line output. Estim. +2-4h per wave. Worth it for reviewability and future maintainability. |
| Stash prior WIP | **Dropped** | Keep as reference | The prior `stash@{0}` had smoke tests incomplete and Twig from the converter. Clean start is cheaper than untangling. |
| PR shape | **4 chained PRs** | 1 big PR or 1-per-template | 4 chained respects the 400-LOC review budget, allows incremental merge, isolates Wave 1 (infra) from Wave 2/3 (visual change). |
| `tools/convert_raintpl.php` | **Not included** | Ship it for next time | Decision already taken: not needed, no other plugins need it. If a future plugin needs it, it can be written fresh with the lessons learned. |
| `Init.php` scope | **Minimal (autoload only)** | Add event listeners / Twig extensions now | YAGNI: no concrete listener/extension requirement is in scope. Adding them would inflate the PR and create test surface for empty functionality. |
| `composer.json` deps | **Zero runtime deps** | Add `symfony/*` or `twig/*` directly | The plugin consumes the framework's Symfony/Twig via the framework's own `vendor/`. Adding plugin-level deps would double the install size and risk version drift. |
| `vendor/` versioning | **Committed in full** | `.gitignore` + install at deploy | Per `AGENTS.md` plugin convention: the plugin loader does NOT run `composer install` at boot, so a clone without `vendor/` is a broken plugin. `vendor/` is part of the shipping artifact. |
| `facturacion_base` | **Untouched, stays optional** | Make it required | Breaking change for any existing install that runs TPV without `facturacion_base` (the no-terminal flow). Out of scope. |

## 5. Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Hand-rewrite introduces semantic drift (column, condition, label) | Medium | Wave 4 includes manual smoke (open page, generate ticket, close caja, reimprimir) and the `TpvmodTwigTemplatesTest` body checks (file exists, non-empty). Verify-report must list every controller flow that uses a rewritten template and confirm visual + functional parity. |
| 9 missing templates have no documented expected rendering | Medium | Each new template is paired with the controller method that calls it. The rewriter reads `controller/tpvmod*.php` lines 111/114/115/246/305/308/331/668 to understand what data the template receives, and renders that data. Verify-report includes the data-flow trace per template. |
| `vendor/` drift vs `composer.lock` | Low | Test in CI: a `ddev exec composer install --dry-run --no-interaction` must report no changes. The plugin's own README updated in this change to document the `composer install` rule for plugin maintainers. |
| Review budget overrun in Wave 3 | Medium | Decision rule pre-staged: if PR3 > 380 LOC, split into 3a (4 list rewrites) and 3b (9 ajax/extension). The split point is decided in `sdd-tasks` based on the actual rewrite size, not pre-committed. |
| `csrf_field()` Twig function not registered when plugin runs outside AdminLTE | Low | Framework's `themes/AdminLTE` is the only supported theme, and it registers the function. If a future theme skips the registration, that's a framework bug, not a plugin bug. Documented in verify-report §"CSRF path". |
| `fs_session_manager` still needed for non-CSRF reasons (e.g., session ID in twig) | Low | Grep audit in Wave 1: confirm `fs_session_manager` is used only for `csrfField()`. The historical archive (`2026-06-20-terminal-opcional/verify-report.md`) shows it was added exclusively for the CSRF workaround. Verified. |
| `Init.php` shadowing framework's plugin discovery | Low | `Init.php` only loads autoload + no-op `init()`. No service registration, no event listeners. The framework's plugin loader instantiates `Init` after autoload, and a no-op `init()` is the documented no-touch baseline. |
| Plugin checkout (without `vendor/`) breaks the loader | Low | `composer_autoload.php` writes an `error_log` directing the operator to run `composer install`, matching the framework's plugin-loader convention. The README is updated with the same instruction. |

## 6. Success criteria

- [ ] `find plugins/tpvmod/view -name "*.html"` returns zero results.
- [ ] `find plugins/tpvmod/view -name "*.html.twig" | wc -l` returns `19`,
  broken down as: 8 main rewrites (`tpvmod`, `tpvmod2`, `tpvmodedita`,
  `tpvmod_settings`, `tpvmod_facturas`, `tpvmod_albaranes`,
  `tpvmod_pedidos`, `tpvmod_presupuestos`), 1 part rewrite
  (`parts/modalguardar`), 1 ajax rewrite (`ajax/tpv_recambios`),
  9 new (5 ajax `tpv_cambios_precios` + `ventas_lineas_{facturas,albaranes,pedidos,presupuestos}`,
  4 extension `ventas_{facturas,albaranes,pedidos,presupuestos}_articulo`).
- [ ] `plugins/tpvmod/Init.php` exists, namespace
  `FSFramework\Plugins\tpvmod`, `declare(strict_types=1)`, calls
  `composer_autoload.php`.
- [ ] `plugins/tpvmod/composer.json` + `composer.lock` +
  `composer_autoload.php` + `vendor/` all committed in the same
  Wave 1 commit.
- [ ] `plugins/tpvmod/.gitignore` does not contain `/vendor/`.
- [ ] `plugins/tpvmod/controller/tpvmod.php` and
  `tpvmod_settings.php` do NOT contain `csrf_field` property,
  `\fs_session_manager::csrfField()`, or
  `require_once .../base/fs_session_manager.php`. Verified by grep.
- [ ] `plugins/tpvmod/openspec/changes/modernize-m3/specs/views/spec.md`
  exists (written in the SPEC phase; this proposal only declares
  the intent to write it).
- [ ] `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` passes.
- [ ] `plugins/tpvmod/tests/TpvmodModulesTest.php` still passes
  (no regression on existing coverage).
- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`
  — full green.
- [ ] `ddev exec composer phpstan` at project root — no new errors.
- [ ] Manual smoke: as agent with `tpvmod` access, open
  `index.php?page=tpvmod`, pick "no terminal" (or "continuar sin
  terminal"), generate 2 tickets, close `caja`, reimprimir. No
  PHP fatals, no missing-template errors, no CSRF rejection.
- [ ] `verify-report.md` includes: file list diff, PHPUnit output,
  phpstan output, manual smoke narrative with `curl -sL` proof of
  no `{csrf_field()}` literal in HTML and presence of
  `_csrf_token` hidden input, and the controller grep audit
  table.
- [ ] `plugins/tpvmod/openspec/changes/modernize-m3/` moved to
  `plugins/tpvmod/openspec/changes/archive/2026-MM-DD-modernize-m3/`
  with `archive-report.md` and a synced
  `plugins/tpvmod/openspec/specs/views/spec.md` (the new
  capability's source of truth).

## 7. Files affected

| Type | Path | Action |
|---|---|---|
| Create | `plugins/tpvmod/Init.php` | new (no-op autoload) |
| Create | `plugins/tpvmod/composer.json` | new (no runtime deps) |
| Create | `plugins/tpvmod/composer.lock` | new (initial) |
| Create | `plugins/tpvmod/composer_autoload.php` | new (boots vendor/) |
| Create | `plugins/tpvmod/vendor/` | new (committed) |
| Modify | `plugins/tpvmod/fsframework.ini` | description updated |
| Modify | `plugins/tpvmod/openspec/config.yaml` | context updated |
| Modify | `plugins/tpvmod/controller/tpvmod.php` | drop csrf_field + fs_session_manager require |
| Modify | `plugins/tpvmod/controller/tpvmod_settings.php` | same |
| Create | `plugins/tpvmod/view/tpvmod.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod2.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmodedita.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod_settings.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/parts/modalguardar.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod_facturas.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod_albaranes.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod_pedidos.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/tpvmod_presupuestos.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/ajax/tpv_recambios.html.twig` | rewrite from `.html` |
| Create | `plugins/tpvmod/view/ajax/tpv_cambios_precios.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/ajax/ventas_lineas_facturas.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/ajax/ventas_lineas_albaranes.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/ajax/ventas_lineas_pedidos.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/ajax/ventas_lineas_presupuestos.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/extension/ventas_facturas_articulo.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/extension/ventas_albaranes_articulo.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/extension/ventas_pedidos_articulo.html.twig` | new (debt fill) |
| Create | `plugins/tpvmod/view/extension/ventas_presupuestos_articulo.html.twig` | new (debt fill) |
| Delete | `plugins/tpvmod/view/tpvmod.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod2.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmodedita.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod_settings.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod_facturas.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod_albaranes.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod_pedidos.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/tpvmod_presupuestos.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/parts/modalguardar.html` | raintpl removal |
| Delete | `plugins/tpvmod/view/ajax/tpv_recambios.html` | raintpl removal |
| Create | `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` | new (3 tests) |
| Create | `plugins/tpvmod/openspec/changes/modernize-m3/proposal.md` | this file |
| Create | `plugins/tpvmod/openspec/changes/modernize-m3/specs/views/spec.md` | SPEC phase |
| Create | `plugins/tpvmod/openspec/changes/modernize-m3/tasks.md` | TASKS phase |
| Create | `plugins/tpvmod/openspec/changes/modernize-m3/verify-report.md` | VERIFY phase |
| Create | `plugins/tpvmod/openspec/changes/modernize-m3/archive-report.md` | ARCHIVE phase |
| Create | `plugins/tpvmod/openspec/specs/views/spec.md` | source of truth (synced from delta) |
| Modify (optional) | `plugins/tpvmod/README.md` | add `composer install` note for plugin maintainers |

## 8. Conventions and standards

- **PHP**: 8.2+, `declare(strict_types=1)` in `Init.php`,
  `composer_autoload.php`, the new test, and any modified
  controller. The 2 modified controllers already declare it.
- **License header**: LGPL 3.0+, copyright `Javier Trujillo
  <mistertekcom@gmail.com>, 2026`. Per `AGENTS.md` "File Header
  Template", but with the plugin's own copyright (not the core's),
  matching the header style of the existing
  `tests/TpvmodModulesTest.php`.
- **Twig 3**: idiomatic syntax. `{{ fsc.prop }}` for property
  access, `{% for item in items %}` for iteration, `{% if %}`
  for branches, `{% include 'parts/modalguardar.html.twig' %}`
  for includes (path relative to the plugin's `view/`). No
  raw `|raw` on user-supplied data. Output escaping handled by
  the framework's auto-escape policy.
- **CSRF**: `{{ csrf_field() }}` (Twig function registered by
  AdminLTE theme). NO `{{ fsc.csrf_field|raw }}`, NO literal
  `_csrf_token` HTML in templates.
- **Includes**: `{% include 'parts/...html.twig' %}` and
  `{% include 'ajax/...html.twig' %}` and
  `{% include 'extension/...html.twig' %}`. Path resolution
  is the framework's standard (the `view/` directory of the
  plugin is the search root for its own templates).
- **Tests**: PHPUnit 11, `namespace Tests\Tpvmod;` (matches
  `TpvmodModulesTest`). No DB. `glob()` + `file_get_contents()`
  + `assertSame`/`assertStringContainsString` for template
  presence and content checks. No fixtures, no test doubles.
- **Vendoring**: `ddev exec composer install` inside
  `plugins/tpvmod/` to bootstrap. Commit the entire `vendor/`
  tree. Lockfile + vendor MUST be in sync (CI check via
  `composer install --dry-run`).
- **Plugin SDD**: change dir lives at
  `plugins/tpvmod/openspec/changes/modernize-m3/` ONLY. The
  core `openspec/` is NOT touched. The plugin has its own git
  repository at `plugins/tpvmod/.git/`, so all git operations
  for this change use `git -C plugins/tpvmod/ ...`.
- **OpenSpec per plugin** rule (`AGENTS.md` "OpenSpec per
  plugin"): this change is 100% inside the plugin's filesystem
  boundary. No core file is modified. Therefore, the change's
  source of truth is plugin-local and the core `openspec/`
  does not need a tracking entry.

## 9. Open questions

- **Resolved (assumed defaults, not blocking)**: vendor/ goes to
  `plugins/tpvmod/vendor/`. Init.php has no listeners. No new
  composer runtime deps. `facturascripts.ini` untouched.
- **Unresolved (block the SPEC phase)**: NONE. Every open
  question has a default documented above; the SPEC phase
  proceeds with those defaults and surfaces a conflict only
  if implementation evidence contradicts them.
- **Surfaced for SPEC phase**: the SPEC phase should explicitly
  enumerate, per template, which controller data fields are
  accessed. The proposal-level statement "read the controller"
  is enough for Wave 1 to start, but the SPEC must pin each
  data field per template as a scenario, so the Wave 3/4
  verification can grep for missing fields.

## Capabilities (contract with `sdd-spec`)

> This is the contract between the proposal and the SPEC phase.
> `sdd-spec` reads this section to know which spec files to
> create or update. Per OpenSpec convention, every proposal
> must declare this section explicitly.

### New Capabilities

- `views` — Template engine and plugin view layer for `tpvmod`.
  Covers: presence and content of every `.html.twig` file in
  the plugin's `view/` tree, the controller CSRF path
  (Twig function call vs property), the absence of RainTPL
  artifacts, and the debt-fill template coverage (every
  controller `$this->template = '...'` reference has a
  matching file on disk).

### Modified Capabilities

- None. The existing `tpv-flow` and `tpvmod-config` specs
  describe the TPV's runtime behavior (terminal mode, settings
  persistence, open/close caja). None of those requirements
  change with the template-engine migration; the controller
  logic is the same, only the view surface and the CSRF
  rendering mechanism change. No delta spec is needed for
  them.

## Rollback plan

- **Per PR**: `git revert <merge-sha>` on the corresponding
  branch. Each PR is independently revertible.
- **Whole change**: revert PR4, PR3, PR2, PR1 in reverse order.
  Reverting Wave 1 (infra) without PR2/3 leaves an `Init.php`
  pointing at a no-op and the `composer_autoload.php` boot path
  — both harmless, but the plugin keeps the new view engine
  files only if the rewrites have also been reverted. Reverting
  in reverse merge order restores master state exactly.
- **Vendor/ rollback**: the committed `vendor/` is the source
  of truth for the plugin's runtime autoload. Reverting Wave 1
  removes the new `composer.json` and `vendor/`; the plugin
  returns to the no-Init, no-composer-autoload state of master.

## 10. References

- `plugins/tpvmod/openspec/changes/archive/2026-06-20-terminal-opcional/verify-report.md`:
  the historical F2/F3/F4 entries document the
  `fs_session_manager` autoload bug and the CSRF RainTPL
  workaround. This proposal resolves both as a side effect of
  the Twig migration.
- `AGENTS.md` §"OpenSpec per plugin": the rule that this
  change's source of truth lives in `plugins/tpvmod/openspec/`,
  not in the core `openspec/`.
- `AGENTS.md` §"Plugin Composer Dependencies (vendor/ MUST be
  committed)": the rule that `vendor/` is part of the plugin
  shipping artifact.
- `AGENTS.md` §"Stack baseline" + §"Symfony 7.4": Twig 3 is
  the canonical template engine; the framework's
  `themes/AdminLTE` already provides `csrf_field()` as a Twig
  function.
- `plugins/tpvmod/openspec/config.yaml` `testing.linter`:
  `ddev exec composer phpstan` is the lint command; the change
  must pass it.

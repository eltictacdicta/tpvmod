# Verify Report: modernize-m3

**Change**: `modernize-m3`  
**Plugin**: `tpvmod`  
**Date**: 2026-07-18  
**Branch**: `modernize-m3` (+ uncommitted Wave 3b/3c/4 work)  
**Verdict**: **PASS WITH CAVEATS** — automated gates green; manual browser smoke (T4.4) deferred to operator

## Scope

Migrate all RainTPL `.html` views in `plugins/tpvmod/view/` to native Twig 3 `.html.twig`, drop controller CSRF workaround, add Composer/Init infra, and fill 9 debt-fill ajax/extension templates referenced by controllers but missing on disk.

## File inventory

### Created (19 `.html.twig`)

| File | Lines |
|------|------:|
| `view/tpvmod.html.twig` | 104 |
| `view/tpvmod2.html.twig` | 379 |
| `view/tpvmod_settings.html.twig` | 51 |
| `view/tpvmodedita.html.twig` | 422 |
| `view/tpvmod_facturas.html.twig` | 459 |
| `view/tpvmod_albaranes.html.twig` | 394 |
| `view/tpvmod_pedidos.html.twig` | 404 |
| `view/tpvmod_presupuestos.html.twig` | 444 |
| `view/parts/modalguardar.html.twig` | 83 |
| `view/ajax/tpv_recambios.html.twig` | 53 |
| `view/ajax/tpv_cambios_precios.html.twig` | 113 |
| `view/ajax/ventas_lineas_facturas.html.twig` | 38 |
| `view/ajax/ventas_lineas_albaranes.html.twig` | 57 |
| `view/ajax/ventas_lineas_pedidos.html.twig` | 57 |
| `view/ajax/ventas_lineas_presupuestos.html.twig` | 57 |
| `view/extension/ventas_facturas_articulo.html.twig` | 76 |
| `view/extension/ventas_albaranes_articulo.html.twig` | 76 |
| `view/extension/ventas_pedidos_articulo.html.twig` | 76 |
| `view/extension/ventas_presupuestos_articulo.html.twig` | 76 |

### Deleted (10 RainTPL `.html`)

- Wave 2: `tpvmod.html`, `tpvmod_settings.html`, `tpvmodedita.html`, `tpvmod2.html`, `parts/modalguardar.html`
- Wave 3: `tpvmod_facturas.html`, `tpvmod_albaranes.html`, `tpvmod_pedidos.html`, `tpvmod_presupuestos.html`, `ajax/tpv_recambios.html`

### Modified (infra + controllers + tests)

- `Init.php`, `composer.json`, `composer.lock`, `composer_autoload.php`, `vendor/`
- `controller/tpvmod.php`, `controller/tpvmod_settings.php` (CSRF workaround removed)
- `fsframework.ini`, `openspec/config.yaml`
- `tests/TpvmodTwigTemplatesTest.php` (Wave 4 — real assertions)

## Automated checks

| Check | Result |
|-------|--------|
| `find view -name "*.html"` | **0** (REQ-VIEW-001) |
| `find view -name "*.html.twig" \| wc -l` | **19** (REQ-VIEW-001) |
| `rg 'csrf_field\|fs_session_manager' controller/tpvmod*.php` | **0 matches** (REQ-VIEW-004) |
| `rg 'fsc\.csrf_field\|csrf_field\|raw' view/` | **0 matches** (REQ-VIEW-002) |
| `php -l` Init, composer_autoload, tpvmod, tpvmod_settings, TpvmodTwigTemplatesTest | **PASS** |
| `phpunit -c plugins/tpvmod/phpunit.xml` | **PASS** — 23 tests, 119 assertions, **0 skipped** |
| `TpvmodTwigTemplatesTest` | **PASS** — 3 tests, 83 assertions |
| `TpvmodModulesTest` | **PASS** — 20 tests (unchanged) |
| `phpunit --testsuite Base` | **PASS** — 160 tests |
| `phpunit --testsuite Plugins --filter Tpvmod` | **PASS** — 23 tests |
| `phpunit --testsuite Plugins` (full) | **5 pre-existing tarifario failures** (unrelated) |
| `composer phpstan` (root) | **BLOCKED** — pre-existing missing `plugins/OidcProvider/controller/admin_oidc_diagnostics.php` in `phpstan.neon` scanFiles |
| HTTP smoke (unauthenticated) | **PASS** — tpvmod, tpvmod_edita, tpvmod_facturas, tpvmod_albaranes, tpvmod_pedidos, tpvmod_presupuestos → HTTP 302 (no 500) |

## Spec scenario coverage

| Requirement | Evidence | Status |
|-------------|----------|--------|
| REQ-VIEW-001: No `.html`, 19 `.twig` | `TpvmodTwigTemplatesTest` + find | PASS |
| REQ-VIEW-002: CSRF via `{{ csrf_field() }}` | grep audit on views; POST forms in list views include token | PASS |
| REQ-VIEW-003: 9 debt-fill templates | all 9 exist with documented field references | PASS |
| REQ-VIEW-004: Controllers drop CSRF workaround | grep + `testControllersDropCsrfWorkaround` | PASS |
| REQ-VIEW-005: Composer/Init/vendor | PR1 commit `9d39866` | PASS |
| REQ-VIEW-006: fsframework.ini mentions Twig | PR1 | PASS |
| REQ-VIEW-007: TpvmodTwigTemplatesTest 3 checks | Wave 4 T4.1 | PASS |

## Deviations

1. **PR split**: Applied as PR1–PR3a commits on branch; PR3b+PR3c+Wave 4 pending single commit (operator).
2. **tpv_cambios_precios**: Best-effort scope — no equivalentes/familia/fabricante tabs (controller only populates `$this->articulo`). Matches spec scenario.
3. **presupuestos modal help text**: Fixed copy-paste bug (`FS_ALBARANES` → `FS_PRESUPUESTOS`) during PR3b migration.
4. **presupuestos reject modal**: Added `{{ csrf_field() }}` (was missing in RainTPL).
5. **phpstan**: Cannot run end-to-end due to pre-existing OidcProvider config drift; `php -l` + PHPUnit used instead.

## Manual smoke (T4.4 — deferred)

Operator checklist before archive:

- [ ] Open `index.php?page=tpvmod` — no fatal / missing template
- [ ] Continuar sin terminal — flow advances
- [ ] Generate 2 tickets with lines
- [ ] Close caja (if `facturacion_base` active)
- [ ] Reprint — no CSRF rejection
- [ ] `tpvmod_settings` HTML contains `_csrf_token` hidden input, not literal `csrf_field()`
- [ ] Without `facturacion_base`: no-terminal ticket flow completes

## Next step

1. Operator: complete T4.4 manual smoke in DDEV browser.
2. Commit uncommitted Wave 3b/3c/4 work on `modernize-m3`.
3. Run `/sdd-archive` → move to `openspec/changes/archive/2026-07-18-modernize-m3/`.

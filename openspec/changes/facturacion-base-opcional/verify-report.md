# Verify Report: facturacion-base-opcional

**Change**: `facturacion-base-opcional`  
**Plugin**: `tpvmod`  
**Date**: 2026-07-18  
**Verdict**: **PASS**

## Automated checks

| Check | Result |
|-------|--------|
| `ddev exec php -l plugins/tpvmod/controller/tpvmod.php` | PASS |
| `ddev exec php -l plugins/tpvmod/controller/tpvmod_settings.php` | PASS |
| `ddev exec php -l plugins/tpvmod/lib/tpvmod_modules.php` | PASS |
| `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` | PASS (11/11) |
| Root discovery `--filter TpvmodModulesTest` | PASS (11/11) |
| Plugins suite regression | 4 pre-existing tarifario failures (unrelated) |

## Spec scenarios (code audit)

| Scenario | Status |
|----------|--------|
| Direct sales without facturacion_base | PASS — `$tpv_active = $caja \|\| !$caja_module_enabled` |
| No `new caja()` when module off | PASS — block wrapped in `if ($caja_module_enabled)` |
| Terminal settings gated | PASS — `tpvmod_settings.html` + POST guard |
| Print links gated | PASS — `tpvmod_imprimir_link()` |
| Cerrar caja hidden | PASS — `tpvmod2.html` `{if="$fsc->caja_module_enabled"}` |
| Legacy caja flow with fbase | PASS — unchanged inner block |

## INI

- `fsframework.ini` created with hard deps (no facturacion_base).
- `facturascripts.ini` require updated to match.

## Manual smoke (deferred)

- [ ] Agent with only tpvmod + core plugins (no facturacion_base): lands on tpvmod2.
- [ ] Agent with facturacion_base: terminal/caja flow unchanged.
- [ ] Settings page with/without facturacion_base.

## Notes

- Presupuesto print now uses `ventas_imprimir&presupuesto=TRUE` when fbase active (replaces broken `imprimir_presu_pedi`).

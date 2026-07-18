# Tasks: facturacion-base-opcional

## Wave 1 — TDD bootstrap

- [x] **T1** Add `lib/tpvmod_modules.php` with plugin detection helpers.
- [x] **T2** Add `tests/TpvmodModulesTest.php` + `phpunit.xml`.
- [x] **T3** Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` — all green.

## Wave 2 — SDD + INI

- [x] **T4** proposal.md, design.md, delta specs (this change dir).
- [x] **T5** Add `fsframework.ini`; update `facturascripts.ini` require list.
- [x] **T6** Update `openspec/config.yaml` (strict_tdd, plugin_tests, context).

## Wave 3 — Controller

- [x] **T7** `tpvmod.php`: require lib; add `$caja_module_enabled`; effective terminal mode.
- [x] **T8** Wrap caja/terminal block in `if ($caja_module_enabled)`.
- [x] **T9** Use `$tpv_active = $caja || !$caja_module_enabled` for document handling.
- [x] **T10** Add `registrar_venta_en_caja()` + replace inline caja updates.
- [x] **T11** Use `tpvmod_imprimir_link()` / `tpvmod_imprimir_url()` for print URLs/messages.
- [x] **T12** `tpvmod_settings.php`: gate form and POST on `tpvmod_terminal_settings_available()`.

## Wave 4 — Views

- [x] **T13** `tpvmod.html`: direct-sales branch; gate terminal UI on `$caja_module_enabled`.
- [x] **T14** `tpvmod2.html`: hide cerrar caja when module off.
- [x] **T15** `tpvmod_settings.html`: conditional form + info alert.

## Wave 5 — Verify

- [x] **T16** Re-run tpvmod PHPUnit suite.
- [x] **T17** Root PHPUnit discovers TpvmodModulesTest (Plugins suite has unrelated tarifario failures).
- [x] **T18** Write `verify-report.md`.
- [ ] **T19** Manual smoke in browser (operator).

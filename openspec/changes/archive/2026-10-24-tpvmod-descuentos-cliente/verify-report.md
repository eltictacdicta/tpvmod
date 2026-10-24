# Verify Report: tpvmod-descuentos-cliente

**Date:** 2026-08-08  
**Status:** PASS (automated)

## Goal-backward verification

| Requirement | Evidence |
|-------------|----------|
| TPV applies cliente D1–D4 on lines (non-editable) | `TpvmodDiscountsTest` (6 tests), `tpvmod.js` recalc, `tpvmod_populate_linea_descuentos()` in save paths |
| Client change recalculates all lines | `tpvmod.js` handler + `datos_cliente` payload with d1–d4 |
| PDF exposes discount fields | `LineDiscountDisplayTest::testAdapterLineShapeIncludesDiscountFields` |
| PDF shows PVPR + net when `print_dto` | `LineDiscountDisplayTest`, `DiscountPdfSmokeTest` |
| Cross-plugin line shape | `TpvmodDiscountPdfBridgeTest` |

## Test commands

```bash
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml
```

## Results

- `plugins/tpvmod/phpunit.xml`: OK (7 tests after bridge test)
- `plugins/factura_pdf1/phpunit.xml`: OK (198 tests after smoke tests)

## Manual smoke (optional)

Browser check: TPV → cliente con dto → guardar albarán → Admin Empresa Impresión `print_dto` ON → imprimir PDF.

Automated equivalent: `DiscountPdfSmokeTest` + `TpvmodDiscountPdfBridgeTest`.

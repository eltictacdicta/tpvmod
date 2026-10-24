```yaml
schema: gentle-ai.archive-result/v1
change: tpvmod-descuentos-cliente
sdd_home: plugins/tpvmod/openspec/
ownership: plugin-local
archived_at: 2026-10-24
verify_report_date: 2026-08-08
archive_path: plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-descuentos-cliente/
verify_verdict: pass
critical_findings: 0
archive_status: complete
specs_created: 2   # tpv-discounts, factura-pdf-discount-display
specs_merged: 1    # tpv-flow (+2 requirements)
in_repo_tasks: 20/20
```

## Archive Report

**Change**: `tpvmod-descuentos-cliente`
**SDD home**: `plugins/tpvmod/openspec/` (`ownership: plugin-local`)
**Archived to**: `plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-descuentos-cliente/`
**Archive status**: **COMPLETE** — 0 CRITICAL, veredicto PASS.

### Resumen

Los cuatro descuentos en cascada del cliente (`d1`–`d4`) se aplican por línea en
TPV (`dtopor`–`dtopor4`), se recalculan en todas las líneas al cambiar de
cliente, se persisten en los cuatro tipos de documento y se reflejan en el PDF
cuando `print_dto` está activo. El operador no puede editar los descuentos: son
del cliente.

### Specs sincronizadas (antes del movimiento)

| Dominio | Acción | Detalle |
|---|---|---|
| `tpv-discounts` | **CREADA** | 6 requirements desde el delta, cuerpo byte-idéntico |
| `factura-pdf-discount-display` | **CREADA** | 2 requirements desde el delta, cuerpo byte-idéntico |
| `tpv-flow` | **MERGE** | +2 requirements (`Client data endpoint includes discounts`, `Article search preloads client discounts`); el canónico pasa de 12 a 14 requirements |

La composición se hizo por extracción de bytes del delta (header + requirements),
no reescribiendo el texto: `diff` entre el bloque de requirements del delta y el
canónico es **vacío** para las dos specs nuevas. Ningún canónico quedó con
marcadores `ADDED`/`MODIFIED`.

### Evidencia

- `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` →
  `OK (192 tests, 1586 assertions)` a la fecha de archivo (el verify-report
  registró 7 en el momento del cambio; la suite creció desde entonces).
- `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml` → OK
  (198 tests) según el verify-report del 2026-08-08.

### Limitaciones documentadas

- El smoke en navegador (cliente con descuento → guardar albarán → imprimir PDF
  con `print_dto`) queda para el operador. Su equivalente automatizado existe:
  `DiscountPdfSmokeTest` y `TpvmodDiscountPdfBridgeTest`.

### Mecánica de archivo

Snapshot `sha256sum` de los 6 archivos antes del `mv`, comparado con el destino:
**idéntico**. `archive-report.md` es aditivo y se escribió después del
movimiento.

### Observación de higiene (no de este change)

`specs/empresa-sedes/spec.md` y `specs/views/spec.md` conservan un encabezado
`## ADDED Requirements`, resto de archives anteriores que no limpiaron el
marcador. No afecta a este change, pero conviene normalizarlos.

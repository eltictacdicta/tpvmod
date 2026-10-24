```yaml
schema: gentle-ai.archive-result/v1
change: tpvmod-cliente-modales
sdd_home: plugins/tpvmod/openspec/
ownership: plugin-local
archived_at: 2026-10-24
archive_path: plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-cliente-modales/
verify_verdict: pass
critical_findings: 0
archive_status: complete
specs_merged: 1   # tpv-cliente-modales: 3 requirements from the delta
in_repo_tasks: 5/5
note: verify-report written at archive time; the change had none before.
```

## Archive Report

**Change**: `tpvmod-cliente-modales`
**SDD home**: `plugins/tpvmod/openspec/` (`ownership: plugin-local`)
**Archived to**: `plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-cliente-modales/`
**Archive status**: **COMPLETE** — 0 CRITICAL.

### Resumen

El TPV reemplazó el autocompletado JSON de clientes por modales, y ganó alta y
edición de cliente sin salir del punto de venta: búsqueda AJAX HTML, formulario
con pestañas Datos / Descuentos / Direcciones, y direcciones gestionadas por AJAX.
Los seis puntos de entrada (cuatro listados y dos pantallas TPV) comparten el
mismo JS, sin copias por pantalla.

### Estado al archivar

Las cinco tareas estaban cerradas pero el change **carecía de `verify-report`**,
así que el archivo se bloqueó hasta verificar contra el código y la suite
actuales. Se escribió `verify-report.md` con la evidencia por requisito.

### Spec canónica — hallazgo y corrección

El delta nunca se había sincronizado bien: la canónica `tpv-cliente-modales`
contenía una versión resumida, sin escenarios, y **le faltaba
`Modal de alta y edición de cliente`**. En este cierre se recompuso la canónica
desde el delta preservando bytes:

| Requirement | Resultado |
|---|---|
| Modal de búsqueda de clientes | cuerpo byte-idéntico al delta |
| Modal de alta y edición de cliente | incorporado, byte-idéntico |
| Sin autocompletado legacy | cuerpo byte-idéntico al delta |
| Modal de formulario de cliente | intacto (change posterior) |
| Una sola autoridad de mapeo de cliente | intacto (change posterior) |
| Usabilidad propia del consumidor sin Alpine | intacto (change posterior) |

Canónica final: 6 requirements, 13 escenarios, sin marcadores de delta.

### Evidencia

```
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
OK (192 tests, 1586 assertions)
```

- `controller/tpvmod.php:109` despacha `tpvmod_cliente_ajax_dispatch()`.
- `view/partials/modal_clientes.html.twig` incluido en `tpvmod2` y `tpvmodedita`.
- Sin `devbridgeAutocomplete` para clientes en `view/`, `controller/` ni `lib/`.

### Limitaciones

- Sin smoke manual en navegador. Cobertura de suite y de código.

### Mecánica de archivo

Snapshot `sha256sum` de los 4 archivos antes del `mv`, comparado con el destino:
**idéntico**. `archive-report.md` es aditivo y se escribió después del
movimiento.

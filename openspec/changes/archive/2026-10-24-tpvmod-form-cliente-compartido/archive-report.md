```yaml
schema: gentle-ai.archive-result/v1
change: tpvmod-form-cliente-compartido
sdd_home: plugins/tpvmod/openspec/
ownership: plugin-local
archived_at: 2026-10-24
archive_path: plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-form-cliente-compartido/
verify_verdict: pass
critical_findings: 0
archive_status: complete
specs_synced: 1/1   # tpv-cliente-modales (1 MODIFIED + 2 ADDED)
in_repo_tasks: 11/11
external_followups: 1   # traducciones ausentes en clientes_core
```

## Archive Report

**Change**: `tpvmod-form-cliente-compartido`
**SDD home**: `plugins/tpvmod/openspec/` (`ownership: plugin-local`)
**Archived to**: `plugins/tpvmod/openspec/changes/archive/2026-10-24-tpvmod-form-cliente-compartido/`
**Archive status**: **COMPLETE** — 0 CRITICAL.

### Resumen

El TPV dejó de duplicar el formulario de cliente. El mapeo del POST, la
resolución del grupo de descuentos y el flag `descuentos_modified` viven ahora en
`FSFramework\Plugins\clientes_core\ClienteForm`; el modal compone los parciales
`@clientes_core/Cliente/Fields` y `@clientes_core/Cliente/Discounts` dentro de su
propio `<form>`, con `id_prefix = tpv_cliente`.

Esto cierra el contrato `3.1`–`3.6` registrado por el change archivado de
`clientes_core` `cliente-form-compartido-grupos-obligatorios`:

| Tarea del contrato | Estado |
|---|---|
| `3.1` delegar en `ClienteForm::apply()` y borrar el fallback | Cumplida (WU-1) |
| `3.2` preservar el contrato AJAX `{ ok, codcliente, label, cliente }` | Cumplida, con test nuevo |
| `3.3` renderizar los parciales con `ignore_missing` y estado degradado | Cumplida (WU-2) |
| `3.4` declarar `clientes_core` en `require` | Ya cumplida antes (v2.3.1) |
| `3.5` verificación por la suite del plugin | Cumplida: 192 tests / 1586 assertions |
| `3.6` orden de release respecto de `clientes_core` | Sin riesgo: dependencia transitiva dura ya resuelta por el core |

### Commits (repo `plugins/tpvmod`)

- `b74922f` — declaración directa de `clientes_core` en `require` (v2.3.1)
- `a7785e8` — WU-1: delegación del mapeo en `ClienteForm`
- `c4cd597` — WU-2: el modal compone los parciales compartidos
- commit de cierre — WU-3: merge de spec + archive

### Evidencia de verificación

- `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` →
  `OK (192 tests, 1586 assertions)`.
- Rojo TDD previo en cada WU (12 errors + 1 failure en WU-1; 8 failures en WU-2).
- Barrido de símbolos huérfanos: sin residuos de los 6 helpers borrados.
- `plugins/clientes_core/` sin modificaciones.

### Limitaciones documentadas (no bloqueantes)

1. **Sin smoke en navegador.** La plantilla compila y renderiza en un entorno
   Twig real dentro de la suite, pero la verificación visual del modal (incluido
   el estado degradado) queda para el operador.
2. **Semántica de descuentos al quitar el grupo**: ya no pone `d1`–`d4` a cero.
   Es el comportamiento delegado esperado y está fijado por test.

### Follow-up externo

`clientes_core` usa `trans('descuentos')` y `trans('modificado')` en
`View/Cliente/Discounts.html.twig` sin que existan esas claves en ninguna
traducción. El defecto es preexistente y afecta a todos los consumidores (el
título del panel y la etiqueta de modificado muestran la clave cruda). Se corrige
en el repo de `clientes_core`, con su propio release; queda fuera de este change
por la decisión D5.

### Mecánica de archivo

Snapshot `sha256sum` de los 5 archivos tomado antes del `mv` y comparado con el
destino después: **idéntico byte a byte**. El change se movió con `mv` (el
directorio estaba sin trackear en git). El `archive-report.md` es aditivo y se
escribió después del movimiento.

### Cierre

Ciclo SDD completo: proposal, design, delta spec, tasks, dos work units
implementadas con TDD estricto, verificación y archivo. El core `openspec/` no
recibió ninguna entrada: el change es 100% interno al plugin.

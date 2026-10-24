# Tasks: tpvmod-form-cliente-compartido

Modo: `strict_tdd: true` — cada work unit escribe primero los tests y los ve
fallar antes de tocar producción.
Verificación por WU: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

## WU-1 — Autoridad PHP (mapeo delegado)

- [x] **1.1** Reescribir `tests/TpvmodClienteHelpersTest.php` contra la
  delegación: cubre vacío → `NULL` en `codgrupo` y `codgrupo_descuento`,
  defaults de grupo al cambiar, `descuentos_modified` recalculado, y que ningún
  camino escriba `'000000'` en `codgrupo`. Debe fallar antes de 1.2 y 1.3.
- [x] **1.2** `lib/tpvmod_cliente.php`: agregar
  `tpvmod_cliente_apply(object $cliente, array $post): object` que delega en
  `ClienteForm::apply()`; borrar `apply_from_post()`, `group_changed()`,
  `apply_group_defaults_on_change()`, `default_group_resolver()`,
  `discount_values()` y `sync_descuentos_modified_flag()`. Conservar
  `campo_display()`, `telefono_display()`, `direccion_apply_from_post()`,
  `cliente_json_response()` y `error_response()`.
- [x] **1.3** `lib/tpvmod_cliente_ajax.php`: `ajax_save_cliente()` usa el seam y
  pierde el fallback de `razonsocial`; si la autoridad no existe responde
  `ok: false` sin persistir; `ensure_models()` se reduce a lo no deprecado.
- [x] **1.4** Suite completa verde.

## WU-2 — Vista y JS del consumidor

- [x] **2.1** Ajustar primero `tests/TpvmodTwigTemplatesTest.php`: obligatoriedad
  invertida (`codgrupo` opcional con opción vacía, `codgrupo_descuento`
  obligatorio), inclusión de los parciales compartidos, `id_prefix` propio y
  ausencia de `name="codcliente"` duplicado.
- [x] **2.2** `view/ajax/tpv_cliente_form.html.twig`: sustituir los campos de
  datos y el bloque de descuentos por las inclusiones de
  `@clientes_core/Cliente/Fields.html.twig` y
  `@clientes_core/Cliente/Discounts.html.twig` con `ignore_missing`; alerta de
  estado degradado; ocultar `#tpv_cliente_btn_reset_descuentos`. Conservar
  `<form>`, CSRF, tabs, panel de direcciones y footer.
- [x] **2.3** `view/js/tpvmod-cliente.js`: listener delegado para
  `select[name="codgrupo_descuento"]` (d1–d4 desde `data-d*`, visibilidad de la
  ayuda) y `tpvmodClienteFormularioValido()` exigiendo solo el grupo de
  descuentos.
- [x] **2.4** Suite completa verde.

## WU-3 — Specs, verificación y cierre

- [x] **3.1** Merge del delta a `openspec/specs/tpv-cliente-modales/spec.md`.
- [x] **3.2** `verify-report.md` con veredicto y lista de hallazgos.
- [x] **3.3** Archivar el change en
  `openspec/changes/archive/2026-10-24-tpvmod-form-cliente-compartido/` con
  `archive-report.md`.
- [x] **3.4** Registrar como follow-up en `clientes_core` las traducciones
  ausentes `descuentos` y `modificado`.

## Fuera de alcance verificado

- `fsframework.ini` no cambia: `clientes_core` ya está declarado (v2.3.1), lo que
  satisface la tarea `3.4` del contrato de origen.
- `controller/tpvmod.php` no cambia: sólo despacha.
- Direcciones de cliente y contrato AJAX (`{ ok, codcliente, label, cliente }`)
  se conservan sin cambios.

## Definition of done

1. Las 3 WU cerradas y cada una con su commit.
2. Suite de `tpvmod` verde y sin tests nuevos omitidos.
3. Ningún helper de mapeo propio sobrevive fuera de `ClienteForm`.
4. Ningún camino escribe `'000000'` en `codgrupo`.
5. La spec canónica `tpv-cliente-modales` refleja el delta.
6. Core `openspec/changes/` sin entradas para este change.

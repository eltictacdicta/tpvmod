# Proposal: el TPV consume el formulario de cliente compartido

## Intent

Eliminar la duplicación del formulario de cliente en `tpvmod` y delegar el mapeo,
la validación y los descuentos en la autoridad compartida que ya vive en
`clientes_core` (`ClienteForm` + parciales Twig `@clientes_core/Cliente/*`).

Esto cierra el contrato `3.1`–`3.6` registrado por el change archivado de
`clientes_core` `cliente-form-compartido-grupos-obligatorios`. La dependencia ya
está declarada (`fsframework.ini` → `require` incluye `clientes_core` desde
v2.3.1), así que la tarea `3.4` ya está cumplida y este change implementa el
resto del contrato.

## Estado verificado

- `lib/tpvmod_cliente.php` duplica la autoridad: `apply_from_post()`,
  `group_changed()`, `apply_group_defaults_on_change()`,
  `default_group_resolver()`, `discount_values()` y
  `sync_descuentos_modified_flag()`.
- `lib/tpvmod_cliente_ajax.php::ajax_save_cliente()` vuelve a duplicar el
  fallback de `razonsocial` que `ClienteForm` ya resuelve.
- `view/ajax/tpv_cliente_form.html.twig` mantiene su propio markup de datos y
  descuentos, con `codgrupo` obligatorio y un `onchange` inline.
- El TPV **no carga Alpine** en `tpvmod2` ni en `tpvmodedita`.

## Scope

### In Scope

- `lib/tpvmod_cliente.php`: borrar los helpers que duplican la autoridad y
  exponer un wrapper mínimo sobre `ClienteForm::apply()`.
- `lib/tpvmod_cliente_ajax.php`: delegar el guardado y eliminar el fallback de
  `razonsocial` duplicado.
- `view/ajax/tpv_cliente_form.html.twig`: componer `Cliente/Fields` y
  `Cliente/Discounts` dentro del `<form>` existente, con `id_prefix`,
  `ignore_missing` y alerta de estado degradado.
- `view/js/tpvmod-cliente.js`: sustituir el `onchange` inline por un listener
  delegado (autollenado d1–d4 y visibilidad de la ayuda) y ajustar la validación
  al nuevo contrato de obligatoriedad.
- Tests: reescribir los que fijan los helpers borrados y el markup actual.
- OpenSpec: este change + delta de la capability `tpv-cliente-modales`.

### Out of Scope

- Direcciones de cliente: el panel y sus endpoints quedan intactos.
- Cambios en `clientes_core` (autoridad y parciales son de solo lectura).
- Cargar Alpine o introducir un build de frontend en el TPV.
- Usar `Cliente/Form.html.twig`: aporta `csrf_field`, `action`, fila de acciones
  y un `<style>` global que chocan con el CSRF y el footer ya existentes.

## Approach

1. **Una sola autoridad de mapeo.** El guardado del TPV llama a
   `ClienteForm::apply()`; los defaults de grupo los aplica el modelo
   (`applyGroupDiscounts`) cuando el grupo cambia, no el TPV.
2. **Parciales, no copia.** La vista incluye `Fields` y `Discounts` con
   `id_prefix: 'tpv_cliente'`; el `<form id="f_cliente_tpv">`, el CSRF, las tabs,
   el panel de direcciones y el footer siguen siendo del consumidor.
3. **UX sin Alpine.** Un listener delegado en `tpvmod-cliente.js` reemplaza el
   `onchange` inline: rellena d1–d4 desde `data-d*`, muestra u oculta la ayuda de
   grupo obligatorio y deja `tpvmodClienteFormularioValido()` como gate de envío.
4. **Contrato AJAX intacto.** `{ ok, codcliente, label, cliente }` y los nombres
   de acción (`save_cliente_tpv`, `cliente_form`, `buscar_cliente_modal`,
   `save_direccion_tpv`, `delete_direccion_tpv`) no cambian.
5. **Degradación explícita.** Si `clientes_core` no estuviera disponible, el
   modal muestra una alerta en lugar de un formulario vacío
   (`ignore_missing=true` + comprobación del render).

## Decisiones abiertas (se cierran en design)

| # | Decisión | Propuesta |
|---|---|---|
| D1 | `codgrupo` deja de ser obligatorio en el TPV (el parcial lo renderiza opcional, el modelo lo acepta y la spec lo exige) | Aceptar el contrato; reescribir el test que lo exige |
| D2 | Sin Alpine, `x-model`/`x-show`/`:disabled` quedan inertes | Mantener el JS propio del TPV como única fuente de UX |
| D3 | El parcial renderiza un botón reset de descuentos sin endpoint en el TPV | Ocultarlo desde el consumidor |
| D4 | El alta gana un `codcliente` editable con placeholder "Auto" | Aceptarlo (coherente con la autoridad compartida) |
| D5 | Faltan las traducciones `descuentos` y `modificado` (defecto preexistente de `clientes_core`) | Registrarlo como follow-up, no corregirlo acá |

## Capabilities

- `tpv-cliente-modales` — **MODIFIED**: el modal de cliente consume el parcial
  compartido y delega el guardado en la autoridad de `clientes_core`.

## Delivery

`auto-chain` con presupuesto de 800 líneas. Forecast: ~600–700 líneas netas
(se borran ~150 de helpers y se reescriben dos suites), cortado en tres work
units si el forecast se confirma alto:

1. Autoridad PHP + tests de helpers.
2. Vista + JS + tests de plantilla.
3. Sincronización de specs y verificación.

## Riesgos

- **Regresión de descuentos.** Quitar el grupo ya no pone d1–d4 a cero y el flag
  `descuentos_modified` se calcula distinto. Es benigno mientras el grupo de
  descuentos siga siendo obligatorio, pero los tests actuales lo fijan: hay que
  reemplazarlos, no borrarlos sin más.
- **Ruido de markup.** `clientes_catalogo` inyecta `coddivisa` por el hook
  `cliente_form_after_main`, que el parcial ya renderiza: verificar que no se
  duplique con ningún campo propio del TPV.
- **Doble `codcliente` en el POST.** El parcial emite `name="codcliente"` y el
  modal tiene un hidden con el mismo nombre: hay que dejar uno solo.

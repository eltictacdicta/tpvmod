# Design: el TPV consume el formulario de cliente compartido

## 1. Contrato de referencia

Autoridad y parciales: `plugins/clientes_core/` (`ClienteForm.php`,
`View/Cliente/Fields.html.twig`, `View/Cliente/Discounts.html.twig`).
Contrato de origen: tareas `3.1`–`3.6` del change archivado
`cliente-form-compartido-grupos-obligatorios`. La tarea `3.4` (declarar la
dependencia) ya está cumplida en `fsframework.ini` desde v2.3.1.

## 2. Delegación del guardado

`ClienteForm::apply(object $cliente, array $post): object` mapea de forma
selectiva por `array_key_exists`, normaliza vacíos a `NULL`, aplica los defaults
del grupo cuando cambia y recalcula `descuentos_modified`.

Se conserva un único seam en el plugin:

```php
// lib/tpvmod_cliente.php
function tpvmod_cliente_apply(object $cliente, array $post): object
```

- Si `FSFramework\Plugins\clientes_core\ClienteForm` existe → delega y devuelve
  el objeto mutado.
- Si no existe → no toca el objeto; el endpoint responde `ok: false` con un
  mensaje explícito (ver §6). Nunca persiste un cliente a medio mapear.

Se eliminan de `lib/tpvmod_cliente.php`: `apply_from_post()`, `group_changed()`,
`apply_group_defaults_on_change()`, `default_group_resolver()`,
`discount_values()` y `sync_descuentos_modified_flag()`.

Se conservan: `campo_display()`, `telefono_display()`,
`direccion_apply_from_post()`, `cliente_json_response()` y `error_response()`.

En `lib/tpvmod_cliente_ajax.php`:

- `ajax_save_cliente()` llama a `tpvmod_cliente_apply()` y borra el fallback de
  `razonsocial` duplicado (lo resuelve la autoridad).
- `ensure_models()` se reduce a lo que no es `no-op`; los `require_model()` son
  deprecados y no cargan nada.

## 3. Composición de la vista

`view/ajax/tpv_cliente_form.html.twig` queda como shell del consumidor:

| Elemento | Dueño |
|---|---|
| `<form id="f_cliente_tpv" name="f_cliente_tpv" onsubmit="return false">` | consumidor |
| `csrf_field()` y hidden `action=save_cliente_tpv` | consumidor |
| Tabs Bootstrap 3 (Datos / Descuentos / Direcciones) | consumidor |
| Campos de datos | `@clientes_core/Cliente/Fields.html.twig` |
| Bloque de descuentos | `@clientes_core/Cliente/Discounts.html.twig` |
| Panel de direcciones | consumidor (sin cambios) |
| Footer y botón "Guardar cliente" | consumidor |

Contexto de las inclusiones:

```twig
{% set fields_html %}
{% include '@clientes_core/Cliente/Fields.html.twig' ignore missing with {
    mode: es_nuevo ? 'create' : 'edit',
    cliente: cliente,
    grupos: fsc.tpv_grupos,
    regimenes_iva: fsc.tpv_regimenes_iva,
    id_prefix: 'tpv_cliente',
    input_class: 'form-control input-sm'
} %}
{% endset %}
```

Los catálogos son propiedades del controlador (`fsc.tpv_grupos`,
`fsc.tpv_grupos_descuentos`, `fsc.tpv_regimenes_iva`), tal como los carga
`ajax_form()`; `es_nuevo` ya existe en la plantilla y sale de
`fsc.tpv_cliente_nuevo`.

La captura con `{% set %}` es lo que permite detectar el estado degradado: con
`ignore missing`, una plantilla ausente deja la captura vacía y la vista muestra
la alerta en lugar de un formulario vacío.

`Discounts` recibe `cliente`, `grupos_descuentos`, `id_prefix` e
`input_class`. No se usa `Form.html.twig`: aporta `csrf_field`, `action`, fila de
acciones y un `<style>` global que chocarían con el CSRF y el footer existentes.

Reglas de integración:

- Un único `name="codcliente"`: se elimina el hidden del consumidor en modo
  edición y se conserva el del parcial.
- El `<select>` de grupo de cliente llega sin `required` (contrato del parcial y
  del modelo); el de grupo de descuentos llega con `required`.
- El botón `#tpv_cliente_btn_reset_descuentos` se oculta con una regla acotada al
  modal (`#f_cliente_tpv #tpv_cliente_btn_reset_descuentos { display: none }`),
  porque el TPV no tiene endpoint de reset.

## 4. JS del consumidor (sin Alpine)

En `view/js/tpvmod-cliente.js`:

- Se elimina el `onchange` inline del markup (el parcial no permite inyectar
  atributos en el `<select>`).
- Se agrega un listener delegado sobre `#f_cliente_tpv` para `change` en
  `select[name="codgrupo_descuento"]`: rellena `d1`–`d4` desde `data-d1`–`data-d4`
  y alterna la visibilidad de `#tpv_cliente_grupo_descuento_required`.
- `tpvmodClienteFormularioValido()` pasa a exigir solo el grupo de descuentos;
  deja de exigir `codgrupo`.
- `tpvmodGuardarCliente()` no cambia: sigue serializando el formulario y leyendo
  `json.label` / `json.cliente`.

## 5. Contrato AJAX preservado

Acciones y respuestas no cambian: `buscar_cliente_modal`, `cliente_form`,
`save_cliente_tpv`, `save_direccion_tpv`, `delete_direccion_tpv`, y la respuesta
`{ ok, codcliente, label, cliente }`.

## 6. Estado degradado

Si `@clientes_core` no resuelve, `ignore missing` deja el bloque vacío. La vista
comprueba el resultado del render (captura con `{% set %}`) y, si está vacío,
emite una alerta `alert alert-warning` con un mensaje traducido en lugar del
formulario. El endpoint de guardado responde `ok: false` con el mismo motivo.

## 7. Decisiones

| # | Decisión | Justificación |
|---|---|---|
| D1 | `codgrupo` opcional en el TPV | El parcial lo renderiza opcional, `cliente::test()` lo acepta y la spec canónica lo exige. Forzar `required` sería duplicar markup y contradecir la autoridad. |
| D2 | Sin Alpine; JS propio | El TPV no carga Alpine en `tpvmod2` ni en `tpvmodedita`. Introducirlo es un cambio de plataforma, no de este refactor. |
| D3 | Reset de descuentos oculto | No existe endpoint ni semántica clara; un control visible sin comportamiento es peor que ausente. |
| D4 | `codcliente` editable en alta | Lo aporta el parcial (`placeholder="Auto"`). Coherente con la autoridad; el modelo sigue generando el código cuando llega vacío. |
| D5 | Traducciones `descuentos` / `modificado` fuera de alcance | Defecto preexistente de `clientes_core`; se registra como follow-up en `clientes_core`, no se parchea desde el TPV. |

## 8. Pruebas

Se reescriben (no se borran sin reemplazo):

- `tests/TpvmodClienteHelpersTest.php`: los casos de `apply_from_post`,
  `group_changed`, `discount_values` y `sync_descuentos_modified_flag` pasan a
  verificar la delegación en `ClienteForm::apply()` y el comportamiento del
  wrapper (autoridad presente y ausente). Los dobles que necesiten defaults de
  grupo declaran `applyGroupDiscounts()`.
- `tests/TpvmodTwigTemplatesTest.php`:
  - el test del `<select>` de descuentos y `data-d1`/`data-d4` pasa a verificar
    la inclusión del parcial y el listener delegado en JS;
  - el test de obligatoriedad se invierte: `codgrupo` opcional con opción vacía,
    `codgrupo_descuento` obligatorio;
  - se conserva el inventario de plantillas y el test del dispatcher.

Nuevos:

- delegación real del guardado (sin `codgrupo`, con `codgrupo_descuento`);
- autoridad ausente → `ok: false` sin persistencia;
- render del modal con `id_prefix = tpv_cliente` y sin `name="codcliente"`
  duplicado.

## 9. Work units (delivery `auto-chain`, presupuesto 800)
| WU | Alcance | Verificación |
|---|---|---|
| WU-1 | Autoridad PHP: wrapper, borrado de helpers, delegación en el endpoint, tests de helpers | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` |
| WU-2 | Vista: parciales, shell del modal, listener delegado, tests de plantilla | ídem |
| WU-3 | Specs canónicas + verify-report + archive | ídem + revisión de delta |

Cada WU es un commit revisable con su propia verificación; el corte entre WU-1 y
WU-2 es el borde natural de review (PHP vs plantillas/JS).

## 10. Correcciones registradas durante el apply

- **§3 — orden de los modificadores de `include`.** El diseño original escribió
  `{% include 'x' with {...} ignore missing %}`, que no compila en Twig 3
  (`SyntaxError: Unexpected token "name" of value "ignore"`). El orden válido es
  `{% include 'x' ignore missing with {...} %}`. Implementado con el orden
  correcto dentro de la captura `{% set %}`.
- **§3 — archivos de traducción no previstos.** Se agregaron
  `translations/messages.es.yaml` y `translations/messages.en.yaml` con la clave
  `tpv-cliente-form-sin-clientes-core`. Sin ellos `trans()` devuelve la clave
  cruda al operador en el estado degradado.
- **§3 — `codcliente` del sub-formulario de direcciones.** El `name="codcliente"`
  que sobrevive fuera del parcial es el hidden de `#f_direccion_tpv`, necesario
  para `save_direccion_tpv`; el contrato de unicidad aplica solo al área de
  identidad del cliente.


# Capability: tpv-cliente-modales

Modales de búsqueda, alta y edición de clientes integrados en todas las pantallas del TPV.

## Requirements

### Requirement: Modal de búsqueda de clientes

En todas las pantallas TPV con selector de cliente, el botón del lápiz MUST abrir un modal
de búsqueda con el mismo patrón visual que `#modal_articulos` (formulario + resultados AJAX HTML).

#### Scenario: Búsqueda desde nuevo documento

- GIVEN el agente está en `tpvmod2`
- WHEN pulsa el botón del lápiz junto al campo Cliente
- THEN se abre `#modal_clientes` con el foco en el campo de búsqueda
- AND al escribir y buscar se muestran filas con nombre, CIF/NIF y teléfono

#### Scenario: Selección aplica descuentos

- GIVEN hay resultados en el modal de clientes
- WHEN el agente pulsa una fila
- THEN se actualiza el cliente activo del TPV
- AND se invoca `usar_cliente()` para recalcular descuentos e IVA
- AND el modal se cierra

### Requirement: Modal de alta y edición de cliente

El TPV MUST ofrecer un modal para crear clientes nuevos y editar el cliente seleccionado,
incluyendo datos principales, descuentos D1–D4 / grupo de descuentos, y direcciones.

#### Scenario: Nuevo cliente desde TPV

- GIVEN el agente está en una pantalla TPV con selector de cliente
- WHEN abre el modal de nuevo cliente y guarda datos válidos
- THEN el cliente se persiste en `clientes`
- AND queda seleccionado en la pantalla TPV con el mismo comportamiento que una búsqueda

#### Scenario: Editar cliente con direcciones

- GIVEN un cliente existente con al menos una dirección
- WHEN el agente abre el modal de edición
- THEN puede modificar datos, descuentos y direcciones vía AJAX sin abandonar el TPV

### Requirement: Modal de formulario de cliente

El modal de cliente compone los parciales compartidos
`@clientes_core/Cliente/Fields` y `@clientes_core/Cliente/Discounts` dentro de su
propio `<form id="f_cliente_tpv">`, con un `id_prefix` propio, y delega el mapeo,
la validación y los descuentos en
`FSFramework\Plugins\clientes_core\ClienteForm::apply()`. El TPV no mantiene
markup ni mapeo propio de los campos de cliente.

#### Scenario: El modal compone los parciales compartidos

- GIVEN un modal de alta o de edición de cliente abierto en el TPV
- WHEN se renderiza `view/ajax/tpv_cliente_form.html.twig`
- THEN los campos de datos provienen de `@clientes_core/Cliente/Fields.html.twig`
- AND los campos de descuento provienen de `@clientes_core/Cliente/Discounts.html.twig`
- AND el `<form>`, su token CSRF, sus tabs, el panel de direcciones y el footer
  siguen siendo del consumidor

#### Scenario: El guardado delega en la autoridad compartida

- GIVEN un POST a la acción `save_cliente_tpv` con CSRF válido
- WHEN el TPV guarda el cliente
- THEN el mapeo de campos lo realiza `ClienteForm::apply()`
- AND el TPV no asigna `codgrupo` ni `codgrupo_descuento` por su cuenta
- AND la respuesta conserva la forma `{ ok, codcliente, label, cliente }`

#### Scenario: El grupo de cliente es opcional en el TPV

- GIVEN un modal de edición de un cliente sin grupo de cliente
- WHEN el operador guarda sin elegir grupo
- THEN el guardado no falla por `codgrupo`
- AND el selector ofrece la opción vacía `— Sin grupo —`

#### Scenario: El grupo de descuentos sigue siendo obligatorio

- GIVEN un modal con el grupo de descuentos vacío
- WHEN el operador intenta guardar
- THEN `tpvmodClienteFormularioValido()` bloquea el envío
- AND el servidor rechaza el guardado por la validación del modelo

#### Scenario: Autollenado de descuentos sin Alpine

- GIVEN el TPV no carga Alpine en sus pantallas
- WHEN el operador cambia el grupo de descuentos
- THEN un listener delegado en `tpvmod-cliente.js` rellena `d1`–`d4` desde los
  atributos `data-d1`–`data-d4` de la opción elegida
- AND la ayuda de grupo obligatorio se muestra u oculta según el valor
- AND no se requiere `x-data` ni `x-model` para que el modal funcione

#### Scenario: Estado degradado sin clientes_core

- GIVEN `clientes_core` no está activo y el namespace `@clientes_core` no existe
- WHEN se abre el modal de cliente
- THEN se muestra una alerta explícita al operador
- AND el modal no queda con un formulario vacío
- AND la página del TPV no se rompe

### Requirement: Sin autocompletado legacy

Las pantallas del alcance MUST NOT usar `devbridgeAutocomplete` para clientes.


### Requirement: Una sola autoridad de mapeo de cliente

El TPV no implementa helpers propios para mapear el POST de cliente, resolver el
grupo de descuentos ni sincronizar el flag `descuentos_modified`. Esas
responsabilidades pertenecen a `ClienteForm` y al modelo `cliente`.

#### Scenario: Sin helpers duplicados de mapeo

- GIVEN el código de `plugins/tpvmod/lib/`
- WHEN se busca lógica de mapeo, resolución de grupo o flag de descuentos
- THEN no existe una segunda implementación fuera de `ClienteForm`
- AND ningún camino escribe `'000000'` en `codgrupo`

#### Scenario: La autoridad ausente falla de forma visible

- GIVEN que la clase `ClienteForm` no está disponible
- WHEN llega un POST a `save_cliente_tpv`
- THEN el endpoint responde `ok: false` con un mensaje explícito
- AND no persiste un cliente a medio mapear

### Requirement: Usabilidad propia del consumidor sin Alpine

El TPV mantiene su propio JS para la interacción del modal de cliente y no
depende de Alpine para descuentos, ayuda contextual ni bloqueo de envío.

#### Scenario: El botón reset del parcial no queda huérfano

- GIVEN el parcial de descuentos renderiza `{{ id_prefix }}_btn_reset_descuentos`
- WHEN el modal se muestra en el TPV
- THEN el botón no es visible para el operador
- AND no queda un control activo sin comportamiento

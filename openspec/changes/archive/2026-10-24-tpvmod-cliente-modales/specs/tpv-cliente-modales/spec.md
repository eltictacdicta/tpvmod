# Delta: tpv-cliente-modales

## ADDED Requirements

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

### Requirement: Sin autocompletado legacy

Las pantallas del alcance MUST NOT usar `devbridgeAutocomplete` para clientes.

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

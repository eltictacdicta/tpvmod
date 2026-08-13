# Capability: tpv-cliente-modales

Modales de búsqueda, alta y edición de clientes integrados en todas las pantallas del TPV.

## Requirements

### Requirement: Modal de búsqueda de clientes

El botón del lápiz abre `#modal_clientes` con búsqueda AJAX HTML (nombre, CIF, teléfono).
Al seleccionar una fila se aplica el mismo comportamiento que el autocomplete legacy.

### Requirement: Modal de formulario de cliente

Formulario con pestañas Datos, Descuentos y Direcciones, accesible para alta y edición
desde el TPV sin navegar a `ventas_cliente`.

### Requirement: Sin autocompletado legacy

Ninguna vista TPV usa `devbridgeAutocomplete` para clientes.

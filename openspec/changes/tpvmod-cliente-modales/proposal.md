# Proposal: Modales de cliente en TPV

## Intent

Sustituir el autocompletado JSON de clientes en todas las pantallas del plugin `tpvmod`
por modales al estilo del buscador de artículos, e incorporar modales para crear y editar
clientes (datos, descuentos y direcciones) sin salir del TPV.

## Scope

- Pantallas: `tpvmod2`, `tpvmodedita`, listados (`tpvmod_albaranes`, `_pedidos`, `_presupuestos`, `_facturas`).
- Backend compartido en `lib/tpvmod_cliente.php` y `lib/tpvmod_cliente_ajax.php`.
- Vistas parciales reutilizables + JS dedicado.
- Tests PHPUnit en `plugins/tpvmod/tests/`.

## Approach

1. Modal de búsqueda disparado por el botón del lápiz; resultados HTML (nombre, CIF, teléfono).
2. Modal de formulario para alta/edición con pestañas Datos / Descuentos / Direcciones.
3. Endpoints AJAX en todos los controladores tpvmod vía dispatcher compartido.
4. Eliminar `devbridgeAutocomplete` de clientes.

## Out of scope

- Cambios en `clientes_core` / `ventas_cliente`.
- Permisos de borrado de cliente desde el modal (solo edición/alta).

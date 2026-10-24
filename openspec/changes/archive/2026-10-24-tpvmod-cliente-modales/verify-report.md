# Verify Report: tpvmod-cliente-modales

**Date:** 2026-10-24 (verificación de cierre; el change se ejecutó antes y quedó
sin reporte)
**Status:** PASS

Este reporte se escribió al archivar. El change tenía sus 5 tareas cerradas pero
nunca se verificó formalmente, así que el archivo se bloqueó hasta comprobar el
resultado contra el código y la suite actuales.

## Verificación por requisito del delta

| Requirement | Evidencia |
|---|---|
| Modal de búsqueda de clientes | `view/partials/modal_clientes.html.twig` incluido en `tpvmod2.html.twig:381` y `tpvmodedita.html.twig:463`; resultados en `view/ajax/tpv_clientes.html.twig`; acción `buscar_cliente_modal` en `lib/tpvmod_cliente_ajax.php`; disparo en `view/js/tpvmod-cliente.js:14,18` |
| Modal de alta y edición de cliente | `tpvmodAbrirFormularioCliente()` (`view/js/tpvmod-cliente.js:28,38`) → `cliente_form` → `view/ajax/tpv_cliente_form.html.twig`, con paneles Datos, Descuentos y Direcciones; guardado por `save_cliente_tpv` |
| Sin autocompletado legacy | `grep -rn devbridgeAutocomplete` sobre `view/`, `controller/` y `lib/` filtrado por cliente: **sin coincidencias** |

## Integración

- `controller/tpvmod.php:109` despacha `tpvmod_cliente_ajax_dispatch($this)` antes
  de continuar con el flujo normal.
- Las cuatro pantallas de listado y las dos pantallas TPV comparten el mismo JS
  (`view/js/tpvmod-cliente.js`), sin copias por pantalla.

## Suite

```
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
OK (192 tests, 1586 assertions)
```

Incluye `TpvmodClienteHelpersTest` (contrato de helpers y endpoint) y
`TpvmodTwigTemplatesTest` (inventario de plantillas, modal y validación).

## Spec canónica — corrección aplicada en este cierre

El delta de este change **nunca se había sincronizado correctamente**: el canónico
`tpv-cliente-modales` tenía una versión resumida, sin escenarios, y le faltaba
por completo `Modal de alta y edición de cliente`.

En este cierre se compuso la canónica desde el delta preservando bytes:

| Requirement | Origen | Estado |
|---|---|---|
| Modal de búsqueda de clientes | delta, cuerpo byte-idéntico | sincronizado |
| Modal de alta y edición de cliente | delta, cuerpo byte-idéntico | **incorporado** |
| Sin autocompletado legacy | delta, cuerpo byte-idéntico | sincronizado |
| Modal de formulario de cliente | change posterior `tpvmod-form-cliente-compartido` | intacto |
| Una sola autoridad de mapeo de cliente | ídem | intacto |
| Usabilidad propia del consumidor sin Alpine | ídem | intacto |

La canónica queda con 6 requirements y 13 escenarios, sin marcadores de delta. Los
tres requirements del change posterior se verificaron intactos contra el commit
`f52d080`.

## Limitaciones

- Sin smoke manual en navegador (búsqueda, alta, edición y direcciones). La
  cobertura es de suite y de código.

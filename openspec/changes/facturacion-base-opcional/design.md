# Design: facturacion_base opcional

## Detection

```php
tpvmod_caja_module_enabled()  // in_array('facturacion_base', $GLOBALS['plugins'])
```

Sin dependencia de `fs_plugin_manager` en tests: el helper acepta `?array $plugins`.

## Controller state

| Property | Meaning |
|----------|---------|
| `$caja_module_enabled` | `facturacion_base` activo |
| `$caja` | Instancia `caja` abierta o `FALSE` |
| `$terminal_mode` | Modo efectivo vía `tpvmod_terminal_mode_effective()` |

## Flow diagram

```
agente presente?
  └─ NO → error agente
  └─ SÍ
       caja_module_enabled?
         └─ NO → tpv_active = TRUE → tpvmod2 (ventas directas)
         └─ SÍ → flujo caja existente (terminal-opcional archivado)
              caja abierta? → tpv_active → tpvmod2
              else → pick terminal / d_inicial
```

## View gates (RainTPL)

- `tpvmod.html`: `{if="$fsc->caja || !$fsc->caja_module_enabled"}` antes de tpvmod2.
- Terminal pick / `tpv_caja` link: `{if="$fsc->caja_module_enabled"}`.
- `tpvmod2.html`: botón cerrar caja `{if="$fsc->caja_module_enabled"}`.

## Settings

- `$terminal_settings_available = tpvmod_terminal_settings_available()`.
- POST que intente cambiar modo con fbase off → error, sin persistir.

## INI

```
# fsframework.ini / facturascripts.ini
require = "clientes_facturacion,catalogo_core,business_data,clientes_core"
```

`facturacion_base` no aparece en `require`; el operador lo activa cuando quiere caja/contabilidad.

## Print URLs

Centralizadas en `tpvmod_imprimir_url()` / `tpvmod_imprimir_link()`.
Solo `ventas_imprimir` cuando fbase activo (sustituye enlaces rotos a
`imprimir_presu_pedi` para pedido/presupuesto cuando fbase está presente).

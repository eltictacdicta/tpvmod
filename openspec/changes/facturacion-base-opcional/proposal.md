# Proposal: facturacion_base opcional para módulo caja/TPV

## Intent

Tras la reestructuración (`clientes_facturacion`), `tpvmod` ya no necesita
`facturacion_base` para los documentos de venta. Solo lo necesita para la
**capa TPV**: `caja`, `terminal_caja`, `tpv_caja`, `tpv_recambios` y
`ventas_imprimir`.

El objetivo es que `tpvmod` funcione **sin** `facturacion_base` activo
(modo ventas directo, sin apertura de caja) y que las opciones de caja/terminal
(`tpvmod_terminal_mode`, flujo de apertura, botón cerrar caja, enlaces de
impresión vía `ventas_imprimir`) **solo estén disponibles cuando
`facturacion_base` está activo**.

## Scope

### In Scope

- Helpers testables en `plugins/tpvmod/lib/tpvmod_modules.php`.
- Suite PHPUnit del plugin (`tests/TpvmodModulesTest.php`, `phpunit.xml`).
- Actualizar `facturascripts.ini` / nuevo `fsframework.ini`: dependencias
  duras = `clientes_facturacion,catalogo_core,business_data,clientes_core`;
  `facturacion_base` deja de ser `require` obligatorio.
- `controller/tpvmod.php`: ramificar flujo caja vs. ventas directas según
  plugin activo; centralizar actualización de caja e impresión.
- `controller/tpvmod_settings.php` + vista: ocultar/deshabilitar toggle terminal
  si `facturacion_base` no está activo.
- Vistas `tpvmod.html`, `tpvmod2.html`: ocultar UI de caja/terminal cuando el
  módulo no está disponible.
- Delta specs en `tpvmod-config` y `tpv-flow`.
- Actualizar `openspec/config.yaml` (strict_tdd, plugin_tests).

### Out of Scope

- Mover `caja` / `terminal_caja` fuera de `facturacion_base`.
- Crear `imprimir_presu_pedi` (página inexistente en el fork).
- Cambios en `plugins/facturacion_base/`.

## Capabilities

### Modified Capabilities

- `tpvmod-config`: el toggle `tpvmod_terminal_mode` solo es editable y relevante
  con `facturacion_base` activo.
- `tpv-flow`: flujo sin caja cuando `facturacion_base` inactivo; flujo caja
  actual preservado cuando está activo (incl. `without_terminal` ya archivado).

## Approach

1. TDD sobre `tpvmod_modules.php` (detección plugin, modo terminal efectivo,
   URLs de impresión).
2. Propiedad pública `$caja_module_enabled` en controladores para RainTPL.
3. Sustituir `if ($this->caja)` por `if ($this->caja || !$this->caja_module_enabled)`
   en vista; en controlador, `$tpv_active = $this->caja || !$this->caja_module_enabled`.
4. Envolver bloque caja/terminal (líneas ~245-342) en
   `if ($this->caja_module_enabled)`.
5. Métodos privados `registrar_venta_en_caja()` para no duplicar guards.
6. `tpvmod_imprimir_link()` para mensajes de éxito.

## Risks

| Risk | Mitigation |
|------|------------|
| Instanciar `caja` sin `facturacion_base` → fatal | Nunca instanciar modelos caja fuera del guard |
| Usuario desactiva `facturacion_base` con caja abierta | Caja queda en BD; TPV sigue en modo directo (sin actualizar caja) |
| `tpvmod_terminal_mode` guardado pero fbase off | Modo efectivo fuerza `with_terminal`; setting ignorado hasta reactivar fbase |

## Success Criteria

- [ ] PHPUnit `plugins/tpvmod/phpunit.xml` verde.
- [ ] Con solo `tpvmod` + deps duras: agente entra directo al TPV (tpvmod2).
- [ ] Con `facturacion_base` activo: comportamiento caja/terminal sin regresión.
- [ ] Settings: toggle terminal visible solo con fbase; aviso cuando no.

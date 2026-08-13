# Proposal: descuentos de cliente automáticos en TPV e impresión PDF

## Intent

Integrar los cuatro descuentos en cascada del cliente (`clientes_core`:
`d1`–`d4` vía `getEffectiveDiscounts()`) en el flujo de venta del TPV
(`tpvmod`) para presupuestos, pedidos, albaranes y facturas, y reflejarlos
en la impresión PDF (`factura_pdf1`) cuando la opción «mostrar descuentos»
esté activa en Admin → Empresa → Impresión.

## Decisiones acordadas (2026-08-08)

| Tema | Decisión |
|------|----------|
| Nivel de aplicación | Por **línea**: `d1`→`dtopor`, `d2`→`dtopor2`, `d3`→`dtopor3`, `d4`→`dtopor4` |
| Override operador | **No editable** en TPV; solo el PVP es editable. Descuentos siempre del cliente |
| Cambio de cliente | Recalcular descuentos y totales de **todas** las líneas del ticket |
| PDF | Opcional vía `print_dto` (fsvar impresión); si hay descuento: PVPR tachado + precio neto |

## Scope

### In scope — `tpvmod`

- Helpers testables en `lib/tpvmod_modules.php` (cálculo cascada, mapeo
  cliente→línea).
- Extender `datos_cliente` JSON con `d1`–`d4` efectivos.
- UI JS: campos dto por línea, `recalcular()` con cascada, recalc al cambiar
  cliente.
- Guardado PHP: aplicar dto en líneas al crear/editar los cuatro tipos de
  documento; persistir `dtopor`…`dtopor4` y `pvptotal` correctos.
- Delta spec `tpv-discounts` + ampliación `tpv-flow`.
- Tests PHPUnit en `tests/TpvmodDiscountsTest.php`.

### In scope — `factura_pdf1` (cross-plugin, referenciado)

- Adapter `getLineas()`: exponer `dtopor`…`dtopor4`, `pvpsindto`.
- Leer `print_dto` de configuración de impresión (`fsvar` / `impresion`).
- Columna precio: PVPR tachado + precio con descuento cuando aplica.
- Tests en `tests/Unit/LineDiscountDisplayTest.php`.

### Out of scope

- Descuentos de cabecera del documento (`dtopor1`…`dtopor5`).
- Cambios en `clientes_core` (API `getEffectiveDiscounts()` ya existe).
- Pantallas `ventas_*` fuera del TPV.
- `ventas_imprimir` (legacy `facturacion_base`); solo `factura_pdf1`.

## Capabilities

### New

- `tpv-discounts`: aplicación automática y editable de D1–D4 en TPV.

### Modified

- `tpv-flow`: recalcular líneas al cambiar cliente.

## Approach

1. TDD helpers puros en `tpvmod_modules.php`.
2. Extender endpoint `datoscliente` y estado JS del cliente.
3. UI por línea (4 campos dto) + recalcular cascada en frontend.
4. Backend save: leer dto de POST, calcular `pvptotal` con misma fórmula que
   `fbase_calc_due` / `linea_documento_venta`.
5. factura_pdf1: adapter + render condicional con `print_dto`.

## Risks

| Risk | Mitigation |
|------|------------|
| Cliente legacy sin `getEffectiveDiscounts()` | Helper devuelve ceros si método ausente |
| Desincronización JS/PHP en totales | Misma fórmula en helper PHP y función JS |
| PDF sin `print_dto` cargado | Default `false`; comportamiento actual preservado |

## Success criteria

- [ ] PHPUnit tpvmod + factura_pdf1 verdes.
- [ ] Cliente con D1=10%, D2=5% → líneas guardadas con dto correctos y total coherente.
- [ ] Cambiar cliente recalcula todas las líneas en pantalla.
- [ ] PDF con `print_dto=1` muestra PVPR tachado cuando hay descuento.

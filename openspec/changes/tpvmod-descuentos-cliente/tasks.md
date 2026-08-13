# Tasks: tpvmod-descuentos-cliente

## Wave 1 — Helpers (TDD)

- [x] T1. **Test** `TpvmodDiscountsTest`: `tpvmod_resolve_cliente_descuentos()` with stub cliente / sin método (~25 LoC)
- [x] T2. **Test** `TpvmodDiscountsTest`: `tpvmod_calc_pvptotal()` cascada 10%+10% → 162 (~15 LoC)
- [x] T3. **Test** `TpvmodDiscountsTest`: `tpvmod_apply_descuentos_a_linea()` asigna dtopor…4 (~15 LoC)
- [x] T4. **Impl** helpers en `lib/tpvmod_modules.php` (~45 LoC)

## Wave 2 — Endpoint y búsqueda

- [x] T5. **Test** `TpvmodDiscountsTest`: shape JSON `datos_cliente` con d1–d4 (~20 LoC)
- [x] T6. **Impl** `datos_cliente()` devuelve descuentos efectivos (~10 LoC)
- [x] T7. **Impl** `new_search()` asigna `dtopor` del cliente a resultados (~15 LoC)

## Wave 3 — Frontend TPV

- [x] T8. **Impl** `tpvmod.js`: estado `cliente.d1…d4`, recalc cascada (sin inputs dto; solo PVP editable) (~50 LoC)
- [x] T9. **Impl** handler cambio cliente → recalcular todas las líneas (~15 LoC)

## Wave 4 — Guardado documentos

- [x] T11. **Impl** método privado `tpvmod_nueva_linea()` reutilizable en save presu/pedi/alb/fact (~40 LoC)
- [x] T12. **Impl** integrar en `guardar_presupuesto`, `guardar_pedido`, `guardar_albaran`, `guardar_factura` (~60 LoC)
- [x] T13. **Impl** rutas edición documento: leer dto del POST y recalcular (~40 LoC)

## Wave 5 — factura_pdf1 (cross-plugin)

- [x] T14. **Test** `LineDiscountDisplayTest`: adapter expone dto fields (~25 LoC)
- [x] T15. **Test** `LineDiscountDisplayTest`: formateo PVPR tachado cuando print_dto (~30 LoC)
- [x] T16. **Impl** `AbstractClienteDocumentAdapter::getLineas()` dto + pvpsindto (~10 LoC)
- [x] T17. **Impl** cargar `print_dto` en render pipeline (~20 LoC)
- [x] T18. **Impl** `PortedPdfDocument`: columna precio con PVPR tachado (~35 LoC)

## Wave 6 — Verify

- [x] T19. Ejecutar `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`
- [x] T20. Ejecutar `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml`
- [x] T21. Smoke: `DiscountPdfSmokeTest` + `TpvmodDiscountPdfBridgeTest` (equivalente automatizado al flujo TPV → albarán → PDF)

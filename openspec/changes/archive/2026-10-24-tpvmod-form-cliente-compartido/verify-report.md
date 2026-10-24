# Verify Report: tpvmod-form-cliente-compartido

## Veredicto

**PASS** — 0 CRITICAL, 1 WARNING documentado, 1 follow-up externo.

- Suite del plugin: `OK (192 tests, 1586 assertions)` (baseline previa: 183/1486).
- Rojo TDD verificado en cada WU antes de tocar producción (12 errors + 1 failure
  en WU-1; 8 failures en WU-2).
- Spec canónica `tpv-cliente-modales` actualizada con el delta (1 MODIFIED, 2
  ADDED), sin marcadores de delta residuales.

## Cobertura de los escenarios del delta

| Escenario | Evidencia |
|---|---|
| El modal compone los parciales compartidos | `testClientFormIncludesSharedClientPartials`, `testClientFormKeepsNoOwnFieldMarkup` |
| El guardado delega en la autoridad compartida | `testSaveClienteEndpointUsesTheSharedAuthoritySeam`, `testApplyDelegatesEmptyCodgrupoToNull` |
| El grupo de cliente es opcional en el TPV | `testClientGroupIsOptionalAndDiscountGroupStaysMandatory` |
| El grupo de descuentos sigue siendo obligatorio | ídem + `testGuardarClienteValidatesOnlyItsOwnControls` |
| Autollenado de descuentos sin Alpine | `testDiscountGroupChangeUsesADelegatedListener`, `testClientFormStateIsInitializedWhenTheModalOpens` |
| Estado degradado sin clientes_core | `testDegradedStateRendersAnAlertInsteadOfTheForm` |
| Sin helpers duplicados de mapeo | `testApplyNeverWritesFallbackGroupCodes` + barrido de símbolos borrados |
| La autoridad ausente falla de forma visible | `testApplyDoesNotMutateWhenAuthorityIsUnavailable` (proceso aislado) + guarda en el endpoint |
| El botón reset del parcial no queda huérfano | `testModalScopedStylesHideCloakAndResetButton` |

## Restricciones duras

| # | Restricción | Resultado |
|---|---|---|
| 1 | `plugins/clientes_core/` sin cambios | PASS |
| 2 | Core `openspec/changes/` sin entrada para este change | PASS |
| 3 | `fsframework.ini` sin cambios (la tarea `3.4` del contrato ya estaba cumplida) | PASS |
| 4 | `controller/tpvmod.php` sin cambios | PASS |
| 5 | Contrato AJAX `{ ok, codcliente, label, cliente }` preservado | PASS (`testClienteJsonResponseShape`) |
| 6 | Direcciones de cliente intactas | PASS |
| 7 | Ningún camino escribe `'000000'` en `codgrupo` | PASS |

## Hallazgos

### WARNING-1 — La semántica de descuentos al quitar el grupo cambió

Quitar el grupo de descuentos ya no pone `d1`–`d4` a cero: la autoridad deja de
aplicar defaults y recalcula `descuentos_modified` desde cero. Es el
comportamiento delegado esperado y queda fijado por
`testApplyRemovingGroupKeepsSubmittedDiscounts`. Benigno mientras el grupo de
descuentos sea obligatorio.

### WARNING-2 — Sin verificación en navegador

No se ejecutó smoke manual del modal (abrir, cambiar grupo, guardar, reabrir,
estado degradado). La plantilla se compila y renderiza en un entorno Twig real
dentro de la suite, pero eso no sustituye la verificación visual del operador.

### Follow-up externo — Traducciones ausentes en `clientes_core`

`View/Cliente/Discounts.html.twig` usa `trans('descuentos')` y
`trans('modificado')`, y **ninguna de las dos claves existe** en
`plugins/clientes_core/translations/messages.{es,en}.yaml` ni en el core. El
defecto es preexistente y afecta a todos los consumidores, no solo al TPV: el
título del panel y la etiqueta de "modificado" muestran la clave cruda.

Fuera de alcance de este change (D5): se corrige en `clientes_core`, donde vive
la plantilla, y necesita su propio release.

## Correcciones de diseño registradas

- Orden inválido de modificadores en `include` (Twig 3 exige
  `ignore missing` antes de `with`). Ver `design.md §10`.
- Dos archivos de traducción no previstos en el TPV para el estado degradado.
- El `name="codcliente"` que sobrevive es el del sub-formulario de direcciones,
  no una duplicación del área de identidad.

## Archivos tocados

- `lib/tpvmod_cliente.php`, `lib/tpvmod_cliente_ajax.php` (WU-1, `a7785e8`)
- `view/ajax/tpv_cliente_form.html.twig`, `view/js/tpvmod-cliente.js`,
  `translations/messages.{es,en}.yaml` (WU-2, `c4cd597`)
- `tests/TpvmodClienteHelpersTest.php`, `tests/TpvmodTwigTemplatesTest.php`
- `openspec/specs/tpv-cliente-modales/spec.md` (merge del delta)

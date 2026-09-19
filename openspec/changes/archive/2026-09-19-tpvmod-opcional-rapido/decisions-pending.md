# Pending product decisions: tpvmod-opcional-rapido

> Status: BLOCKED before `sdd-propose`. All items below require explicit user
> confirmation. Full analysis: `exploration.md` §4.
> This file is the durable pending state; it is resolved into the proposal/spec
> handoff once every decision is answered.

| ID | Question | Options | Recommended |
|---|---|---|---|
| OD-1 | Do ad-hoc optional lines ignore the client's cascading discounts (`dtopor*`), or receive them like any other line? | (1) normal line semantics (client discount applies; entered price is the pre-discount base) · (2) true no-discount line (`dtopor=0`) | 1 |
| OD-2 | How is the `porcentaje` ad-hoc price computed? | (1) client-side over the product line PVP (`ctx.pvp`) · (2) server-side via `catalogo_opcional::precio_para_articulo()` | 1 |
| OD-3 | Product has no `codfamilia`: what does "asociar a la familia" do? | (1) hide/disable Familia, force Producto · (2) allow saving unassociated · (3) block save until Producto chosen | 1 |
| OD-4 | After "Añadir y guardar", is the line auto-added to the current sale? | (1) yes, auto-add · (2) no, refresh list only | 1 |
| OD-5 | Can a TPV-created opcional belong to a group? | (1) always ungrouped (sueltos) · (2) allow group selection | 1 |
| OD-6 | Do ad-hoc (unsaved) lines count toward obligatorios / exclusive groups? | (1) inert (never satisfy obligations) · (2) satisfiable by description match | 1 |
| OD-7 | "Asociar a la familia": propagate to all family articles? | (1) link family only (`add_familia_only`) · (2) propagate (`add_familia`) | 1 |
| OD-8 | If an opcional with the same nombre already exists: | (1) reuse it and associate · (2) reject with error · (3) create duplicate | 1 |
| OD-9 | Codigo field for the quick-add form? | (1) auto-generate (`OPC####`) · (2) optional manual input | 1 |
| OD-10 | Which screens support the quick-add flow? | (1) new ticket + document edit · (2) new ticket only | 1 |

Hard constraints already fixed (not open):
- Plugin-local: no edits under `plugins/catalogo_core/`; catalog models are consumed only.
- Business logic in `lib/`, thin CSRF-validated POST dispatch in `controller/tpvmod.php` (Approach B).
- Explicit `data-ad-hoc` marker on ad-hoc rows to close the client/server obligatorios divergence.
- CSRF on the new write endpoint; read-only `opcionales_articulo` stays CSRF-free.
- Strict TDD active; runner `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

---

## RESOLVED — 2026-09-19

All decisions below were confirmed by the user as one grouped prompt. The
recommended option was chosen for every item.

| ID | Resolution |
|---|---|
| OD-1 | Ad-hoc lines receive the client's normal cascading discounts; the entered price is only the pre-discount base. No discount bypass, so `tpvmod_populate_linea_descuentos()` stays untouched. |
| OD-2 | Percentage price computed client-side over the product line PVP (`ctx.pvp`) at insert time. |
| OD-3 | When the product has no `codfamilia`, the Familia option is hidden/disabled and Producto is forced. |
| OD-4 | After "Añadir y guardar", the line is auto-added to the current sale. |
| OD-5 | TPV-created opcionales are always ungrouped (`id_grupo = null`). |
| OD-6 | Ad-hoc lines are inert: they never satisfy obligatorios nor count in exclusive groups. The explicit `data-ad-hoc` marker closes the client/server divergence. |
| OD-7 | Family association links the family only (`add_familia_only()`); no propagation to family articles. |
| OD-8 | If an opcional with the same nombre exists for the target, reuse it and associate it instead of creating a duplicate. |
| OD-9 | Codigo is auto-generated (`get_new_codigo()`, `OPC####`); no manual codigo field. |
| OD-10 | Flow available in both new-sale (`tpvmod2`) and document-edit (`tpvmodedita`) screens. |

Research gate: user selected external research (`sdd-research`) before propose.
Status: RESEARCH IN PROGRESS, then PROPOSE.

# Exploration: tpvmod-opcional-rapido

> Plugin-local SDD. Change root: `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/`.
> Core `openspec/` is intentionally NOT touched.
> Status: exploration only. No proposal/spec/design/tasks artifacts created.

## Intent (verbatim, Spanish)

> "En `plugins/tpvmod`, cuando agrego un opcional sobre la marcha a un producto, al darle a 'nuevo opcional' necesito poder elegir entre los opcionales que ya están para añadir, o añadir uno nuevo. El nuevo tiene una línea como la de los productos añadidos a mano, donde se pone el nombre, la descripción, y se elige entre precio (precio sin contar el descuento del cliente) o porcentaje, igual que en la configuración de un nuevo opcional. Y por último el botón de 'añadir' o 'añadir y guardar opcional'. Si le doy solo 'añadir', no se guarda y se usa solo para esta venta; si le doy 'añadir y guardar', me preguntará si quiero asociarlo al producto o a la familia, y lo guardamos como opcional."

Interpretation: inside the existing "Añadir opcional" modal the agent must be able to either (a) pick an existing catalog opcional (today's behavior), or (b) compose a brand-new opcional inline (nombre, descripcion, tipo `fijo|porcentaje`, valor) and then either add it as an ad-hoc line for this sale only, or save it to the catalog and associate it with the product or its family.

---

## 1. Current-State Map

### 1.1 Modal markup (Twig)

| Location | Lines | Content |
|---|---|---|
| `plugins/tpvmod/view/tpvmod2.html.twig` | 383-398 | `#modal_opcionales` — header title "Añadir opcional", `#tpvmod_opcionales_list` body, footer only a "Cerrar" button. No form fields. |
| `plugins/tpvmod/view/tpvmodedita.html.twig` | 465-480 | Identical modal for the edit-document screen. |

Both modals are empty shells; `#tpvmod_opcionales_list` is fully replaced by JS. Any new form must be added in **both** templates (or extracted to a shared partial).

Related anchors:
- `tpvmod2.html.twig:94` and `:341` render `{{ csrf_field() }}` inside the two TPV forms, so `tpvmodCsrfToken()` can read the token.
- `tpvmodedita.html.twig:117` and `:424` render `{{ csrf_field() }}`.
- `tpvmodedita.html.twig:243` — the "Añadir opcional" button that calls `tpvmod_show_opcionales_modal('{{ parent_uid }}')`.

### 1.2 JS (`plugins/tpvmod/view/js/tpvmod.js`, 1531 lines)

| Function | Lines | Role |
|---|---|---|
| `tpvmod_opcional_prefix` | 23 | `'Opcional: '` constant. |
| `tpvmod_format_opcional_desc(text, grupoNombre)` | 139-148 | Builds `Opcional: [Grupo: ]texto`. |
| `tpvmod_is_opcional_row($row)` | 150-153 | Row predicate via `tpvmod-line-opcional` class. |
| `tpvmod_opcional_actions_cell(lineNum)` | 155-162 | Disabled generic icon + delete button for optional rows. |
| `tpvmod_reorder_opcionales()` | 164-179 | Keeps optional rows right after their parent product row. |
| `tpvmod_init_opcional_lines_from_dom()` | 181-203 | Rehydrates class/`data-parent-uid` from `Opcional: ` descriptions. |
| `tpvmod_sync_opcional_quantities(parentLineNum)` | 205-219 | Mirrors parent quantity into optional rows. |
| `tpvmod_bind_opcional_quantity_sync()` | 221-233 | Binds qty change handlers. |
| **`tpvmod_add_opcional_linea(parentUid, opcional, cantidad, codimpuesto, ivaArticulo)`** | 235-294 | Inserts `<tr class="tpvmod-line-opcional" data-parent-uid data-opcional-id data-grupo-id>` with hidden `referencia_N=""`, `idlinea_N=-1`, `tpvmod_opcional_id_N`, `tpvmod_opcional_grupo_id_N`, `tpvmod_parent_ref_N`, `iva_N`, `recargo_N`, `irpf_N`; textarea `desc_N`; `cantidad_N`, `pvp_N`, `neto_N`, `total_N`. |
| `tpvmod_normalize_opcionales_payload(data)` | 296-310 | Normalizes array/`{grupos,sueltos}` payload. |
| `tpvmod_store_obligatorios_requirements(ref, payload)` | 312-339 | Builds per-ref obligatorios map. |
| `tpvmod_prefetch_obligatorios_for_ref(ref, pvp)` | 341-361 | GET `opcionales_articulo` and caches. |
| `tpvmod_collect_missing_obligatorios()` | 363-414 | Client-side obligatorios validation. |
| `tpvmod_validate_obligatorios_before_save()` | 416-424 | Blocks save on missing obligations. |
| `tpvmod_row_missing_obligatorios(ref, parentUid)` | 426-460 | Per-row check. |
| `tpvmod_refresh_obligatorio_warnings()` | 462-473 | Highlights product rows missing obligations. |
| `tpvmod_opcionales_flat_list(payload)` | 475-493 | Flattens groups + sueltos. |
| `tpvmod_get_added_opcional_ids(parentUid)` | 495-507 | Reads `data-opcional-id` of existing optional rows. |
| `tpvmod_get_selected_grupo_opcional(parentUid, grupoId)` | 509-522 | Returns selected id in an exclusive group. |
| `tpvmod_remove_opcional_in_grupo(parentUid, grupoId)` | 524-532 | Removes group selection. |
| `tpvmod_get_parent_line_context_by_uid(parentUid)` | 534-551 | Returns `{parentUid, parentLineNum, ref, pvp, cantidad, codimpuesto, ivaArticulo}` — **no `codfamilia` today**. |
| **`tpvmod_render_opcionales_modal(payload, parentUid)`** | 553-626 | Renders groups + sueltos as `list-group` buttons; hides already-added; shows "no tiene opcionales disponibles". |
| `tpvmod_opcionales_cache` | 628 | Per `ref|pvp` cache. |
| **`tpvmod_show_opcionales_modal(parentUid)`** | 630-663 | Validates context, shows modal, GETs `opcionales_articulo` (cache-aware). |
| `tpvmod_opcionales_modal_data` | 665 | Last modal payload. |
| **`tpvmod_pick_opcional(parentUid, opcionalId)`** | 667-699 | Finds opcional in flat list, handles exclusive-group replacement, calls `tpvmod_add_opcional_linea`, reorders, renumbers, recalculates, re-renders. |
| `tpvmodCsrfToken()` | 1260-1268 | Reads `_csrf_token` from `form[name=f_tpv]` or `<meta name="csrf-token">`. |
| `get_precios(ref)` | 1270-1287 | Reference POST+CSRF AJAX pattern. |

### 1.3 Controller (`plugins/tpvmod/controller/tpvmod.php`, 2526 lines)

| Location | Lines | Role |
|---|---|---|
| `require_once .../lib/tpvmod_opcionales.php` | 38 | Loads opcional helpers. |
| AJAX dispatch `if (tpvmod_cliente_ajax_dispatch($this))` | 108-110 | Existing POST AJAX entry point (client modals), validates CSRF. |
| Dispatch `else if (isset($_REQUEST['opcionales_articulo']))` | 123-126 | Routes to `get_opcionales_articulo()`. |
| `tpvmod_validate_obligatorios_post($_POST)` gate | 390-398 | Server-side obligatorios validation before any document save; on errors it stops the save. |
| `get_opcionales_articulo()` | 600-618 | GET (fallback POST) `opcionales_articulo` + `pvp`; sets `template=false`; JSON `tpvmod_opcionales_for_articulo()`; `exit`. No CSRF (read-only GET). |
| `opcional_line_meta(parentRef, descripcion, pvp)` | 2370-2378 | Public helper used by `tpvmodedita.html.twig:210` to resolve hidden metadata on edit. |
| Line save flows (albaran / presupuesto / pedido / factura) | e.g. 1272-1424 | For each optional/manual line, builds `linea_*` and calls `tpvmod_populate_linea_descuentos($linea, $this->cliente_s, cantidad, pvp)` at 1328-1333 and 1390-1395. |

**Critical**: `tpvmod_populate_linea_descuentos()` applies the client's cascading discounts (`dtopor`…`dtopor4`) to **every** line, including optional lines. This is the current semantics and it directly intersects requirement (b)(c) and open decision OD-4/OD-6 below.

### 1.4 Helpers (`plugins/tpvmod/lib/tpvmod_opcionales.php`, 380 lines)

| Function | Lines | Role |
|---|---|---|
| `tpvmod_opcional_line_prefix()` | 14-17 | `'Opcional: '`. |
| `tpvmod_is_opcional_line_description()` | 22-27 | Prefix test. |
| `tpvmod_format_opcional_line_description()` | 32-44 | Canonical prefixing. |
| `tpvmod_has_catalogo_core()` | 49-52 | Active-plugin gate. |
| `tpvmod_default_lista_precio()` | 57-72 | Resolves default lista code (or `'DEF'`). |
| `tpvmod_build_opcional_item()` | 77-102 | Builds `{id, codigo, descripcion, precio, grupo_id, grupo_nombre, grupo_exclusivo, obligatorio}`; `precio` via `$opcional->precio_para_articulo($pvpArticulo, $codlista)`. |
| `tpvmod_opcionales_for_articulo()` | 112-173 | Groups + sueltos payload for an article. |
| `tpvmod_opcionales_flat_list()` | 181-196 | Flatten. |
| `tpvmod_opcional_line_text()` | 201-208 | Strips prefix. |
| `tpvmod_match_opcional_line_in_payload()` | 216-256 | Matches a saved line description to a catalog opcional. |
| `tpvmod_resolve_opcional_line_metadata()` | 263-276 | Resolves metadata for edit/re-save. |
| `tpvmod_validate_obligatorios_post()` | 283-380 | Server-side obligations; resolves metadata when `tpvmod_opcional_id_N <= 0` (lines 318-328). |

### 1.5 Catalog models (consumed, `plugins/catalogo_core/model/core/`)

| Symbol | Location | Contract |
|---|---|---|
| `catalogo_opcional` | `catalogo_opcional.php` | Fields `codigo, nombre, descripcion, precio, tipo_precio (fijo|porcentaje), porcentaje, activo, id_grupo`; `get_new_codigo()` returns `OPC` + `str_pad(max(id)+1, 4)`. `test()` requires codigo 1-20 chars and unique; nombre 1-100; porcentaje ≥ 0 or precio ≥ 0. `save()` INSERT/UPDATE. `add_familia()` → propagation; `add_familia_only()` → no propagation. |
| `catalogo_articulo_opcional` | `catalogo_articulo_opcional.php` | `add($referencia, $id_opcional, $obligatorio=false)`; `validate_opcional_for_articulo()` **rejects opcionales that belong to a group**; `exists_relation()`; `delete_all_from_opcional()`. |
| `catalogo_opcional_familia` | `catalogo_opcional_familia.php` | `add($id_opcional, $codfamilia)`; `add_with_propagation()` also assigns the opcional/group to every article of the family; `get_articulos_from_familia()`. |
| `catalogo_articulo_opcional_grupo` | `catalogo_articulo_opcional_grupo.php` | `get_grupos_from_articulo($referencia)` (line 90+). |
| `catalogo_opcional_grupo` | `catalogo_opcional_grupo.php` | Group entity; `get_new_codigo()`. |

`articulo->codfamilia` exists (`plugins/catalogo_core/model/core/articulo.php:48`), so the family association target is resolvable server-side from the product reference.

### 1.6 Tests

- `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` (13008 chars) — covers pure helpers and greps the JS for required symbols (`tpvmod_add_opcional_linea`, `tpvmod_show_opcionales_modal`, `:not(.tpvmod-line-opcional)`, `data-parent-uid`, etc.).
- Runner: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`. Suite `tpvmod` scans `tests/`, coverage `<source>` includes only `lib/`.

---

## 2. Proposed Approach

### 2.1 Modal UX (extend `#modal_opcionales`)

Keep the existing list of catalog opcionales, and add below it a collapsible "Nuevo opcional" section (details/summary or a toggle button) containing:

- `nombre` (text, required)
- `descripcion` (text)
- `tipo` radio: `fijo` | `porcentaje`
- `valor` (number; label/step switches with `tipo`: € for `fijo`, % for `porcentaje`)
- Two actions:
  - **"Añadir"** — builds an ad-hoc line for this sale only; nothing persisted.
  - **"Añadir y guardar opcional"** — persists to catalog, then opens a small association choice (Producto / Familia) and associates.

The existing `list-group` markup and `tpvmod_pick_opcional` stay untouched; the new section is additive and must be rendered in both Twig modals (or a shared partial `partials/modal_opcional_nuevo.html.twig`).

### 2.2 Ad-hoc line (not persisted)

Reuse `tpvmod_add_opcional_linea()` with a synthetic `opcional` object:
```
{ id: null/0, descripcion: nombre(+descripcion), precio: <valor|pvp*pct/100>, grupo_id: null, grupo_nombre: '' }
```
The resulting row already carries `data-opcional-id=""` (empty) and hidden `tpvmod_opcional_id_N=""`, so on save it becomes a plain manual line with the `Opcional: ` prefix and no catalog linkage. No controller change is strictly required for pure ad-hoc insertion — but see OD-1/OD-2 for the percentage base and OD-5 for catalog-match collisions.

**Recommended**: do NOT invent new markup. Extend `tpvmod_add_opcional_linea` to accept an optional marker (e.g. `opcional.ad_hoc = true`) that adds `data-ad-hoc="1"`, so client and server can distinguish ad-hoc lines from catalog-derived ones deterministically instead of relying on empty id + description heuristics.

### 2.3 Save-and-associate

New POST endpoint on `tpvmod.php` (mirroring the client-modal AJAX pattern in `lib/tpvmod_cliente_ajax.php`):

1. Validate CSRF via `$ctrl->isCsrfValid()` (same gate as `tpvmod_cliente_ajax_save_cliente`).
2. Read + sanitize `nombre`, `descripcion`, `tipo_precio`, `valor`, `referencia` (parent product ref).
3. Resolve `codigo`: auto via `catalogo_opcional::get_new_codigo()` (default) — the user intent does not mention a codigo field.
4. Build `catalogo_opcional`: set `tipo_precio`; if `porcentaje`, set `porcentaje` and `precio=0`, else set `precio` and `porcentaje=null`; `activo=true`; `id_grupo=null`. Call `save()`.
5. Optionally mirror the canonical detail flow (`Controller/VentasOpcional.php:229-239`): write `set_precio_lista()` / `set_porcentaje_lista()` for the default lista so the new opcional behaves like a catalog-created one.
6. Association step (client asks Producto / Familia, then calls the endpoint again or the same endpoint with `asociar=producto|familia`):
   - Producto → `catalogo_articulo_opcional::add($referencia, $id, $obligatorio=false)`.
   - Familia → `catalogo_opcional::add_familia($codfamilia)` **or** `add_familia_only($codfamilia)` (OD-7).
   - Family code resolved server-side from `articulo->codfamilia` (must exist; OD-3).
7. Return JSON `{ok, id, codigo, nombre, descripcion, precio, tipo_precio, porcentaje}` so the JS can immediately insert the line and refresh the modal cache.

### 2.4 Endpoint placement

Two viable shapes:

| Approach | Pros | Cons | Effort |
|---|---|---|---|
| **A. New POST action on `controller/tpvmod.php`** (e.g. `$_POST['guardar_opcional_tpv']`) handled inside the existing `private_core()` dispatch, next to `tpvmod_cliente_ajax_dispatch` | Single entry point; reuses `$this->isCsrfValid()`, `$this->articulo`, session defaults; consistent with client modals | Grows `tpvmod.php` (already 2526 lines) | Low |
| **B. New helper in `lib/tpvmod_opcionales.php` + thin dispatch in controller** | Keeps business logic testable in `lib/` under the plugin's PHPUnit `<source>include>` | Same controller dispatch either way | Low |

**Recommendation**: B — business logic (`tpvmod_save_opcional_from_post()`, `tpvmod_associate_opcional()`, ad-hoc line builder, percentage calc) in `lib/tpvmod_opcionales.php` (or a new `lib/tpvmod_opcionales_ajax.php`), and a thin CSRF-validated dispatch in `tpvmod.php`. This matches the `tpvmod_cliente_ajax.php` precedent and keeps strict-TDD coverage inside the existing suite source path.

---

## 3. Endpoints / CSRF / Codigo

| Concern | Decision / fact |
|---|---|
| Read endpoint | Existing `GET ?opcionales_articulo=<ref>&pvp=<n>` (no CSRF; read-only) — unchanged. |
| Write endpoint | New `POST` action on `tpvmod.php` for "guardar opcional", gated by `isCsrfValid()`. |
| CSRF transport | JS `tpvmodCsrfToken()` already reads `_csrf_token` from the TPV form; send as `_csrf_token` in the POST body, as `get_precios()` and `tpvmod-cliente.js` already do. |
| CSRF failure response | JSON `{ok:false, errors:['Token CSRF inválido.']}` (mirror `tpvmod_cliente_error_response`). |
| Codigo generation | `catalogo_opcional::get_new_codigo()` → `OPC####`. Note `MAX(id)+1` is not collision-safe under concurrency; `save()` will reject a duplicate codigo. Consider a small retry or timestamp suffix (OD-8). |
| Lista de precios | Use `tpvmod_default_lista_precio()` for parity with `VentasOpcional::guardarOpcional` (`set_precio_lista`/`set_porcentaje_lista`). |
| Family target | Resolve `articulo->codfamilia` from `$referencia` server-side. TPV models are loaded via `require_model`. |
| Percentage base | Reuse `catalogo_opcional::precio_para_articulo($pvpArticulo, $codlista)` for catalog-consistent math; the ad-hoc base is the product raw PVP (`ctx.pvp`), which excludes client discount. |

---

## 4. OPEN PRODUCT DECISIONS (must confirm before `sdd-propose`)

These are blocking. Each needs an explicit answer from the orchestrator/user.

### OD-1 — Ad-hoc lines and client discounts
- **Question**: The user says the "precio" field is "precio sin contar el descuento del cliente". Does the resulting **ad-hoc line** also skip the client's `dtopor` discounts at save time, or is only the *definition* of the price discount-free (and the line still receives the client discount like every other line)?
- **Options**:
  1. Ad-hoc line is inserted with `dtopor=dtopor2=...=0` (no client discount) — a true "sin descuento" line. Requires bypassing `tpvmod_populate_linea_descuentos()` for ad-hoc rows and sending the marker in POST.
  2. Ad-hoc line uses normal line semantics (client discount applies), and "sin contar el descuento" merely documents that the entered price is the pre-discount base.
- **Consequence**:
  1. Ad-hoc lines diverge from catalog opcionales (which DO receive client discounts today); totals must be tested; the save flow needs an ad-hoc discriminator.
  2. Minimal change; consistent with all other lines; but may contradict the user's literal wording.

### OD-2 — Percentage base for ad-hoc lines
- **Question**: For `tipo=porcentaje`, should the ad-hoc `pvp` be computed client-side as `productLinePvp * pct / 100`, or resolved server-side via `catalogo_opcional::precio_para_articulo()`?
- **Options**: (1) client-side from `ctx.pvp`; (2) server-side through a new endpoint that returns the computed unit price.
- **Consequence**: (1) Instant, no round-trip; but `ctx.pvp` is the editable product-line PVP, so it changes if the agent edits the line. (2) Consistent with catalog math and immune to client tampering, but adds a round-trip and an endpoint.

### OD-3 — Product without family
- **Question**: When the parent product has no `codfamilia` (or the family model lookup fails), what should "asociar a la familia" do?
- **Options**: (1) hide/disable the Familia option and force Producto; (2) allow saving the opcional unassociated (opcional-only, reusable later); (3) block the whole save until the user picks Producto.
- **Consequence**: (1) Clear UX, but the agent must still get at least one association. (2) Most permissive; leaves orphan catalog entries. (3) Safest data hygiene but can frustrate quick sales.

### OD-4 — Auto-add after saving
- **Question**: After "Añadir y guardar opcional" associates the opcional, should it also be inserted as a line in the current sale?
- **Options**: (1) yes, auto-add the line right after association; (2) no, require the agent to pick it again from the refreshed list.
- **Consequence**: (1) One-step flow matching the button label. (2) Two-step flow, clearer separation, but redundant after just typing the opcional.

### OD-5 — Group for a TPV-created opcional
- **Question**: The new opcional's `id_grupo` — always ungrouped (`null`, "sueltos"), or can the agent pick a group?
- **Options**: (1) always ungrouped (simplest; `catalogo_articulo_opcional::add()` requires ungrouped); (2) show a group selector and, if grouped, associate via `catalogo_articulo_opcional_grupo` instead.
- **Consequence**: (1) Keeps `add()` valid and the modal simple. (2) More powerful but requires group-association logic and changes obligatorios behavior.

### OD-6 — Ad-hoc lines vs obligatorios / grouping
- **Question**: Do ad-hoc (unsaved) lines participate in the obligatorios validation and exclusive-group logic? They have no `opcional_id`.
- **Options**: (1) ad-hoc lines are inert (never satisfy obligations, never count in groups); (2) ad-hoc lines can satisfy an obligation when their description matches a catalog opcional.
- **Consequence**: (1) Predictable; obligations still shown as unmet until a catalog opcional is chosen. (2) Requires the current description-matching fallback (`tpvmod_resolve_opcional_line_metadata`), which is fragile and can misclassify arbitrary manual lines as catalog opcionales.
- **Risk already present**: `tpvmod_validate_obligatorios_post()` lines 318-328 resolve a metadata match when `opcional_id <= 0`, so an ad-hoc line whose description equals a catalog opcional description WOULD be counted server-side, while the JS client-side collector (lines 383-392) would NOT. This client/server divergence must be closed by an explicit ad-hoc marker.

### OD-7 — Family association propagation
- **Question**: `add_familia()` propagates the opcional to every article in the family; `add_familia_only()` links only the family. Which does "asociarlo a la familia" mean?
- **Options**: (1) propagate to all articles (`add_familia()`); (2) link family only (`add_familia_only()`); (3) ask the agent.
- **Consequence**: (1) Matches the canonical detail UX and makes the opcional immediately available; but mass-assigns to potentially hundreds of products. (2) Minimal blast radius. (3) Extra prompt.

### OD-8 — Duplicate handling
- **Question**: If an opcional with the same nombre (or a generated codigo collision) already exists for the product/family, what happens?
- **Options**: (1) reject with an error; (2) reuse the existing opcional and just associate it; (3) create a duplicate anyway (distinct codigo).
- **Consequence**: (1) Safe but forces the agent to find it in the list. (2) Best UX, needs a name lookup + association path. (3) Catalog bloat. Note `catalogo_opcional::test()` only enforces codigo uniqueness, not nombre uniqueness.

### OD-9 — Codigo visibility
- **Question**: Should the quick-add form expose the `codigo` field, or always auto-generate?
- **Options**: (1) auto-generate only; (2) optional codigo input with auto fallback (canonical detail behavior).
- **Consequence**: (1) Simplest, matches user intent (nombre/descripcion/tipo/valor only). (2) More control, more UI.

### OD-10 — Both screens
- **Question**: Confirm the new flow must work in both `tpvmod2` (new ticket) and `tpvmodedita` (edit saved document).
- **Options**: (1) both; (2) new-ticket only.
- **Consequence**: (1) Double markup + JS wiring; edit flow additionally depends on `opcional_line_meta` for re-resolution. (2) Smaller scope but inconsistent UX.

---

## 5. Affected Areas

| File | Why affected |
|---|---|
| `plugins/tpvmod/view/tpvmod2.html.twig` (383-398) | Add "Nuevo opcional" form + association prompt inside `#modal_opcionales`. |
| `plugins/tpvmod/view/tpvmodedita.html.twig` (465-480) | Same modal for edit screen. |
| `plugins/tpvmod/view/js/tpvmod.js` (139-699, 1260-1287) | Render the new form, build ad-hoc items, AJAX save+associate, CSRF, refresh cache. |
| `plugins/tpvmod/controller/tpvmod.php` (108-126, 390-398, 600-618) | New POST dispatch; possibly ad-hoc discount bypass in the four save flows (1272-1424 and siblings). |
| `plugins/tpvmod/lib/tpvmod_opcionales.php` | New helpers: ad-hoc item builder, save-from-post, association, percentage base, ad-hoc discriminator; obligatorios server-side adjustment (318-328). |
| `plugins/tpvmod/lib/tpvmod_modules.php` (376-490) | Possible no-discount variant of `tpvmod_populate_linea_descuentos()` (OD-1). |
| `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` (+ new test file) | RED tests first (strict TDD). |
| `plugins/catalogo_core/model/core/*` | **Consumed only** — no edits. Keeps the change plugin-local. |

No file outside `plugins/tpvmod/` needs editing → **plugin-local SDD is correct**. `plugins/catalogo_core/openspec/` must not be touched.

---

## 6. Risks

1. **Client/server obligatorios divergence** (OD-6): the server’s description-based fallback can misclassify ad-hoc lines as catalog opcionales. Highest-priority correctness risk.
2. **Discount semantics** (OD-1): bypassing `tpvmod_populate_linea_descuentos()` for ad-hoc rows touches all four document save flows (albarán/presupuesto/pedido/factura); missing one produces inconsistent totals.
3. **Codigo collision**: `get_new_codigo()` uses `MAX(id)+1`; concurrent saves can collide and `save()` will fail. Needs retry or user feedback.
4. **Family propagation blow-up** (OD-7): `add_familia()` assigns to every product in the family; a mis-click can mass-modify the catalog.
5. **Modal duplication**: `tpvmod2` and `tpvmodedita` carry duplicate modal markup; drift between them is likely. A shared partial mitigates this.
6. **XSS**: the new form fields (nombre/descripcion) are rendered by the JS modal renderer. The existing renderer escapes via `tpvmod_escape_html`; new render paths MUST do the same, and server output MUST be escaped by Twig (`{{ }}`, no `|raw`).
7. **CSRF**: the new write endpoint must use `isCsrfValid()`; read-only `opcionales_articulo` stays CSRF-free.
8. **Cache staleness**: after saving, `tpvmod_opcionales_cache` keyed by `ref|pvp` must be invalidated so the new opcional appears and `tpvmod_obligatorios_by_ref` reflects any new obligation.

---

## 7. Recommended Test Strategy (strict TDD active)

- **RED first**, in `plugins/tpvmod/tests/` (suite `tpvmod`, runner `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`).
- Pure `lib/` functions (no DB) — extend `TpvmodOpcionalesTest.php` or add `TpvmodOpcionalRapidoTest.php`:
  - ad-hoc item builder: `fijo` price passthrough; `porcentaje` computes `pvp * pct/100` with `bround`; empty nombre rejected; negativo rejected.
  - ad-hoc discriminator: `tpvmod_is_opcional_line_description()` still true; new `is_ad_hoc` marker returns true only for ad-hoc rows.
  - obligatorios: an ad-hoc line with a description matching a catalog opcional MUST NOT satisfy the obligation (guards OD-6).
  - association helper: producto path calls `catalogo_articulo_opcional::add` semantics; familia path selection propagates vs not (mock-free via a fake relation double, following the anonymous-subclass pattern).
  - discount bypass helper (if OD-1 option 1): `tpvmod_calc_pvptotal` with zero discounts equals `cantidad * pvp`.
- **JS contract tests** (existing precedent in `TpvmodOpcionalesTest.php::testJsIncludesOpcionalHelpers`): grep for the new symbols (`tpvmod_add_opcional_ad_hoc`, `tpvmod_save_opcional_tpv`, `guardar_opcional_tpv`, `data-ad-hoc`) so the wiring cannot silently regress.
- **Smoke**: manual TPV flow in `ddev` — add product → "Añadir opcional" → pick existing; add product → new opcional "Añadir" (verify not persisted via catalog list); new opcional "Añadir y guardar" → Producto; → Familia with/without family; save the sale and verify line totals + obligatorios.
- Linter: `ddev exec composer phpstan` (project level).

---

## 8. Interaction With Already-Active Changes

Active changes in `plugins/tpvmod/openspec/changes/`:

| Change | Overlap | Action |
|---|---|---|
| `facturacion-base-opcional` | Touches `tpv-flow`/`tpvmod-config` specs; not the opcionales modal logic. | Flag only. No shared code paths with the modal/create flow. |
| `tpvmod-cliente-modales` | Owns the `tpvmod_cliente_ajax_dispatch()` entry point in `tpvmod.php` and `lib/tpvmod_cliente_ajax.php`, which the new POST endpoint will sit beside. | **Overlap risk**: both edit the `private_core()` dispatch chain and `tpvmod.php`. Sequence the dispatches so they do not collide; reuse its CSRF/JSON-response pattern rather than duplicating it. |
| `tpvmod-descuentos-cliente` | Owns `tpvmod_populate_linea_descuentos()`, `tpvmod_resolve_cliente_descuentos()` and the `dtopor*` line semantics used by the save flows. | **Highest overlap**: OD-1 (ad-hoc lines and client discounts) directly touches the same functions and the same four save loops. Do not modify these functions' contracts; if OD-1 option 1 is chosen, add an additive bypass (e.g. zero-discount variant) rather than changing existing behavior. |

No modifications were made to any active change or its artifacts during this exploration.

---

## 9. Recommendation

Proceed with **Approach B** (business logic in `lib/`, thin CSRF-validated dispatch in `tpvmod.php`), extending the existing `#modal_opcionales` with a collapsible "Nuevo opcional" form, and reusing `tpvmod_add_opcional_linea()` for both existing picks and ad-hoc lines gated by an explicit `data-ad-hoc` marker.

Before `sdd-propose`, resolve **OD-1, OD-2, OD-3, OD-6 and OD-7** — they change both the spec and the implementation shape. OD-1 and OD-6 are the ones that can break existing behavior; OD-7 has the largest blast radius on the catalog.

### Ready for Proposal

**No** — pending the open product decisions in Section 4. Once OD-1 through OD-10 are answered (at minimum OD-1, OD-2, OD-3, OD-6, OD-7), this is ready for `sdd-propose`.

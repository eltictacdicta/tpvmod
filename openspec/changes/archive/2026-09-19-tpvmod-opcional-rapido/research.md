# Research: tpvmod-opcional-rapido

> **revision**: 1
> **outcome**: done
> **lane**: repo-grounded implementation-pattern evidence
> **generated**: 2026-09-19
> **change root**: `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/`
> **artifact store**: `openspec` (plugin-local)
> **external evidence grants**: `documentation=[]`, `open-web=[]` — no external class admitted; every claim below is repository evidence.

This research builds on `exploration.md` and the RESOLVED section of
`decisions-pending.md`. It does not re-open the design; it grounds the
implementation pattern in concrete repository sources.

---

## 1. Research admission

| Field | Value |
|---|---|
| Capability id | `gentle-ai.sdd-research-capability/v1` |
| Declared grants | `documentation=[]`, `open-web=[]` |
| Admitted classes | repository sources only (internal workspace bytes read via filesystem) |
| Denied classes | external documentation, open web |
| Admission result | admitted — repo lane; no external claim emitted |
| Persistence target | OpenSpec file (`research.md`) + Engram `sdd/tpvmod-opcional-rapido/research` |

No external source is admitted or cited. Where the repo delegates to a library
behavior (Bootstrap collapse, Twig autoescape), the claim is explicitly marked
as an inference and supported only by repository facts.

---

## 2. Questions

| ID | Question |
|---|---|
| Q1 | Canonical "AJAX write + CSRF + JSON response" pattern in tpvmod: exact function names, response shapes, error envelope. |
| Q2 | How the canonical catalog admin creates an opcional (save sequence, `set_precio_lista`/`set_porcentaje_lista`, `get_new_codigo`). |
| Q3 | Exact model contracts to reuse and any constraint blocking the resolved decisions. |
| Q4 | The ad-hoc line path: what is written, which fields are read, and where ad-hoc lines must be excluded from obligatorios. Is `data-ad-hoc` sufficient? |
| Q5 | Modal-form capability in the AdminLTE theme (Bootstrap version, tabs/collapse, form patterns inside modals). |
| Q6 | Test patterns for writing RED tests first. |
| Q7 | XSS/escaping requirements for the new render path. |

---

## 3. Sources

`URL` is the repo-relative path; `accessed_at` = 2026-09-19 for all.

| ID | class | title | publisher | URL | excerpt |
|---|---|---|---|---|---|
| S1 | repo | tpvmod client modal AJAX dispatcher | panel-ab | `plugins/tpvmod/lib/tpvmod_cliente_ajax.php` | dispatch table + `isCsrfValid()` + `emit_json` |
| S2 | repo | tpvmod client JSON response helpers | panel-ab | `plugins/tpvmod/lib/tpvmod_cliente.php:177-196` | `{ok:true,...}` / `{ok:false,errors}` |
| S3 | repo | tpvmod controller dispatch + flows | panel-ab | `plugins/tpvmod/controller/tpvmod.php` | requires, `private_core` chain, obligatorios gate, save flows |
| S4 | repo | tpvmod JS CSRF + `get_precios` | panel-ab | `plugins/tpvmod/view/js/tpvmod.js:1260-1287` | `tpvmodCsrfToken()`, `_csrf_token` |
| S5 | repo | tpvmod client-modal JS JSON consumption | panel-ab | `plugins/tpvmod/view/js/tpvmod-cliente.js:237-285` | `dataType:'json'`, `json.ok`, `json.errors` |
| S6 | repo | framework CSRF gate | panel-ab | `base/fs_controller.php:432` | `isCsrfValid()` |
| S7 | repo | canonical catalog opcional controller | panel-ab | `plugins/catalogo_core/Controller/VentasOpcional.php` | `guardarOpcional`, `addFamilia`, `addArticulo` |
| S8 | repo | `catalogo_opcional` model | panel-ab | `plugins/catalogo_core/model/core/catalogo_opcional.php` | test/save/get_new_codigo/set_*_lista |
| S9 | repo | `catalogo_articulo_opcional` model | panel-ab | `plugins/catalogo_core/model/core/catalogo_articulo_opcional.php` | `add`, `validate_opcional_for_articulo`, `exists_relation` |
| S10 | repo | `catalogo_opcional_familia` model | panel-ab | `plugins/catalogo_core/model/core/catalogo_opcional_familia.php` | `add`, `add_with_propagation` |
| S11 | repo | `catalogo_articulo_opcional_grupo` model | panel-ab | `plugins/catalogo_core/model/core/catalogo_articulo_opcional_grupo.php:90-105` | `get_grupos_from_articulo` |
| S12 | repo | `articulo` model | panel-ab | `plugins/catalogo_core/model/core/articulo.php:44-48` | `public $codfamilia` |
| S13 | repo | tpvmod opcionales helpers | panel-ab | `plugins/tpvmod/lib/tpvmod_opcionales.php` | build/for_articulo/match/validate_obligatorios_post |
| S14 | repo | tpvmod line discount helpers | panel-ab | `plugins/tpvmod/lib/tpvmod_modules.php:376-490` | `tpvmod_populate_linea_descuentos` |
| S15 | repo | tpvmod2 template | panel-ab | `plugins/tpvmod/view/tpvmod2.html.twig` | globals, csrf, tabs, opcionales modal |
| S16 | repo | tpvmodedita template | panel-ab | `plugins/tpvmod/view/tpvmodedita.html.twig` | csrf, optional-line rehydration, modal |
| S17 | repo | shared client modals partial | panel-ab | `plugins/tpvmod/view/partials/modal_clientes.html.twig` | shared-modal precedent |
| S18 | repo | client form loaded inside modal | panel-ab | `plugins/tpvmod/view/ajax/tpv_cliente_form.html.twig` | tabs-inside-modal + hidden action field |
| S19 | repo | Bootstrap version | panel-ab | `package.json:29`; `view/js/bootstrap.min.js` | `"bootstrap": "^3.4.1"`; bundle `VERSION="3.4.1"` |
| S20 | repo | tpvmod opcionales tests | panel-ab | `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` | helper tests + JS grep contract |
| S21 | repo | tpvmod anonymous-double test patterns | panel-ab | `plugins/tpvmod/tests/TpvmodModulesTest.php:316-369`; `tests/TpvmodClienteHelpersTest.php:43` | `new class { ... }` doubles |
| S22 | repo | tpvmod PHPUnit config | panel-ab | `plugins/tpvmod/phpunit.xml` | testsuite `tpvmod`, source `lib/` |
| S23 | repo | active plugin SDD changes | panel-ab | `plugins/tpvmod/openspec/changes/` | `tpvmod-cliente-modales`, `tpvmod-descuentos-cliente`, `facturacion-base-opcional` |
| S24 | repo | framework form-token helper | panel-ab | `src/Core/Base/Controller.php:465` | `validateFormToken()` |
| S25 | repo | tpvmod plugin SDD config | panel-ab | `plugins/tpvmod/openspec/config.yaml` | `ownership: plugin-local`, strict_tdd, runner |

---

## 4. Validated claims → source → lines

### Q1 — Canonical AJAX write + CSRF + JSON pattern

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q1.1 | The controller's first branch in `private_core()` is `if (tpvmod_cliente_ajax_dispatch($this)) { return; }`, so a writable AJAX action is dispatched before any page rendering. | S3 | `controller/tpvmod.php:108-110` | Reuse this slot for the new opcional write. |
| Q1.2 | `tpvmod_cliente_ajax_dispatch(fs_controller $ctrl): bool` routes on `isset($_POST['...'])` keys and returns `true` when handled. | S1 | `:28-58` | Actions: `buscar_cliente_modal`, `cliente_form`, `save_cliente_tpv`, `save_direccion_tpv`, `delete_direccion_tpv`. |
| Q1.3 | Each write handler starts with `$ctrl->template = false;` then `if (!$ctrl->isCsrfValid()) { emit_json(error_response(['Token CSRF inválido.'])); return; }`. | S1 | `:120-127`, `:160-167`, `:209-215` | The gate is per-handler, not in the dispatcher. |
| Q1.4 | The CSRF gate is `fs_controller::isCsrfValid()`. | S6 | `base/fs_controller.php:432` | Framework contract. |
| Q1.5 | JSON emission is `header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload);` with **no `exit`**. | S1 | `:244-248` | Return-to-dispatcher contract: handler returns, dispatcher returns `true`, `private_core` returns. |
| Q1.6 | Success envelope: `{ok: true, codcliente, label, cliente}`. Error envelope: `{ok: false, errors: [...]}`. | S2 | `:177-185`, `:190-196` | Reuse the `{ok, errors}` error envelope; success payload is domain-specific. |
| Q1.7 | JS reads the token from `form[name=f_tpv] input[name="_csrf_token"]`, falling back to `meta[name="csrf-token"]`. | S4 | `tpvmod.js:1260-1268` | `tpvmodCsrfToken()`. |
| Q1.8 | `get_precios()` POSTs `referencia4precios`, `codcliente` and appends `&_csrf_token=` via `encodeURIComponent`; it consumes **HTML** (`dataType:'html'`). | S4 | `tpvmod.js:1270-1287` | The new write endpoint must follow the **JSON** pattern of Q1.6, not this HTML one. |
| Q1.9 | Client-modal JS POSTs with `dataType:'json'`, handles `success: function(json)`, branches on `!json.ok` and joins `json.errors`. | S5 | `tpvmod-cliente.js:246-266` | Reuse for the new form. |
| Q1.10 | The read endpoint `GET/POST ?opcionales_articulo=<ref>&pvp=<n>` sets `template=false`, echoes JSON and `exit`s; it performs no CSRF check. | S3 | `controller/tpvmod.php:600-618` | Read-only legacy path stays CSRF-free. |

### Q2 — Canonical catalog admin creates an opcional

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q2.1 | `guardarOpcional(Request)` validates the form token first and returns on failure. | S7 | `Controller/VentasOpcional.php:182-187` | Uses `validateFormToken()`. |
| Q2.2 | `validateFormToken()` is defined on the framework base controller. | S24 | `src/Core/Base/Controller.php:465` | tpvmod's AJAX uses `isCsrfValid()` instead; both are valid gates. |
| Q2.3 | Codigo resolution: `$codigo = trim(scodigo); if ($codigo === '') { $codigo = $this->opcional->get_new_codigo(); }`. | S7 | `:193-198` | Auto-generate fallback. |
| Q2.4 | Field mapping: `nombre`, `descripcion`, `tipo_precio` (normalized to `fijo|porcentaje`), then `porcentaje = value, precio = 0` **or** `porcentaje = null, precio = value`; `activo`; `id_grupo` (0 → null). | S7 | `:199-217` | Exact write contract to mirror. |
| Q2.5 | Save is conditional: `if (!$this->opcional->save()) { error; return; }`. | S7 | `:219-222` | `save()` itself runs `test()`. |
| Q2.6 | If the opcional has a group, direct article relations are cleared (`delete_all_from_opcional`). | S7 | `:224-227` | Not applicable for OD-5 (always ungrouped), but the model does it in `save()` too (`S8:408-411`). |
| Q2.7 | After save, the default lista row is written: percentage → `set_porcentaje_lista($lista_precio_defecto, porcentaje)`; fixed → `set_precio_lista($lista_precio_defecto, precio)`. | S7 | `:229-239` | This is the step the quick-add must replicate for catalog parity. |
| Q2.8 | Association to family is split: `add_familia()` (propagates) vs `add_familia_only()` (family only); product association is `$rel->add($referencia, id)`. | S7 | `:249-281`, `:313-336` | OD-7 selects `add_familia_only()`. |
| Q2.9 | Product association is rejected when the opcional belongs to a group. | S7 | `:319-322` | Mirrors `S9:47-60`. |
| Q2.10 | A new opcional's codigo is pre-filled with `get_new_codigo()` on the create screen. | S7 | `:83-95` | Confirms `OPC####`. |

### Q3 — Exact model contracts

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q3.1 | `catalogo_opcional` constants `TIPO_PRECIO_FIJO='fijo'`, `TIPO_PRECIO_PORCENTAJE='porcentaje'`; fields `id, codigo, nombre, descripcion, precio, tipo_precio, porcentaje, activo, id_grupo`. | S8 | `:13-27` | Constructor defaults: `precio=0.0`, `tipo_precio='fijo'`, `porcentaje=null`, `activo=true`, `id_grupo=null`. `:52-62` |
| Q3.2 | `es_precio_porcentaje()` is `tipo_precio === 'porcentaje'`. | S8 | `:85-88` | Use to branch price handling. |
| Q3.3 | `precio_para_articulo($articulo, $codlista)` for percentage returns `bround($pvp * $pct/100)`; for fixed returns the lista price (or `precio` when `id` is null). | S8 | `:111-128` | Server-side canonical math; OD-2 computes client-side from `ctx.pvp` instead. |
| Q3.4 | `get_by_codigo($codigo)` exists; there is **no** `get_by_nombre()`. `search()` filters `lower(codigo) LIKE %q% OR lower(nombre) LIKE %q%`. | S8 | `:270-279`, `:464-515` | OD-8 reuse-by-name has no exact lookup helper (finding F5). |
| Q3.5 | `get_new_codigo()` = `'OPC' . str_pad(MAX(id)+1, 4, '0', LEFT)`. | S8 | `:281-290` | `MAX(id)+1` is not concurrency-safe (finding F6). |
| Q3.6 | `test()` normalizes tipo, requires codigo 1–20 chars and unique, nombre 1–100, and `porcentaje >= 0` (percentage) or `precio >= 0` (fixed). It applies `no_html()` to codigo/nombre/descripcion. | S8 | `:337-372` | Only codigo uniqueness is enforced — not nombre. |
| Q3.7 | `save()` INSERT/UPDATEs, sets `id = lastval()` on insert, and clears direct article relations when `id_grupo` is set. | S8 | `:374-417` | |
| Q3.8 | `set_precio_lista($codlista,$precio)` writes a `catalogo_opcional_precio` row with `porcentaje=null`; `set_porcentaje_lista($codlista,$porcentaje)` writes it with `precio=0.0`. | S8 | `:578-614` | Both instantiate `catalogo_opcional_precio`; it is **not** required by this file (finding F7). |
| Q3.9 | `catalogo_opcional::add_familia_only($codfamilia)` delegates to `catalogo_opcional_familia::add()` (bool, idempotent); `add_familia()` delegates to `add_with_propagation()` (array `{familia, articulos, errores}`). | S8 | `:304-314`; S10 `:39-50`, `:52-88` | OD-7 selects the bool `add()` path. |
| Q3.10 | `catalogo_articulo_opcional::add($referencia, $id_opcional, $obligatorio=false)` calls `validate_opcional_for_articulo()`, which returns an error string if the opcional has `id_grupo`; then inserts, or updates `obligatorio` when the relation already exists. | S9 | `:47-81` | **Constraint confirmed: grouped opcionales cannot be added directly** — consistent with OD-5. |
| Q3.11 | `exists_relation($referencia,$id_opcional)` returns bool; `set_obligatorio()` updates the flag. | S9 | `:83-107` | For idempotent association (OD-4/OD-8). |
| Q3.12 | `catalogo_articulo_opcional_grupo::get_grupos_from_articulo($referencia)` returns group entities with `obligatorio_en_articulo`. | S11 | `:90-105` | Not needed for OD-5; confirms group path is separate. |
| Q3.13 | `articulo::$codfamilia` exists. | S12 | `articulo.php:44-48` | Server-side family resolution is possible from the parent reference. |

### Q4 — Ad-hoc line path and obligatorios exclusion

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q4.1 | `tpvmod_add_opcional_linea(parentUid, opcional, cantidad, codimpuesto, ivaArticulo)` resolves `ctx` from the parent row, increments `numlineas`, and formats the description with `tpvmod_format_opcional_desc()`. | S4 (JS) | `tpvmod.js:235-253` | `tpvmod_get_parent_line_context_by_uid` provides `{parentLineNum, ref, pvp, cantidad, codimpuesto, ivaArticulo}` (`:534-551`). |
| Q4.2 | The inserted `<tr class="tpvmod-line-opcional" data-parent-uid data-opcional-id data-grupo-id>` carries hidden inputs `referencia_N=""`, `idlinea_N=-1`, `tpvmod_opcional_id_N`, `tpvmod_opcional_grupo_id_N`, `tpvmod_parent_ref_N`, `iva_N`, `recargo_N`, `irpf_N`, plus `desc_N`, `cantidad_N`, `pvp_N`, `neto_N`, `total_N`. | S4 (JS) | `tpvmod.js:255-277` | Ad-hoc insertion reuses this exact markup with empty ids. |
| Q4.3 | The edit/save flow reads `idlinea_N`, `cantidad_N`, `pvp_N`, `desc_N`, `irpf_N`, `iva_N`, `recargo_N`, `referencia_N`; the new-line branch is gated on `idlinea_N == -1`. | S3 | `controller/tpvmod.php:1317-1419` | Optional lines take the same branch; no opcional id is persisted on the document line. |
| Q4.4 | Every line (including optional/ad-hoc) goes through `tpvmod_populate_linea_descuentos($linea, $cliente, $cantidad, $pvp)`, which applies `dtopor..dtopor4`. | S3; S14 | `controller/tpvmod.php:1328-1333`, `:1390-1395`; `lib/tpvmod_modules.php:478-490` | OD-1: no bypass; the entered price is the pre-discount base. |
| Q4.5 | Server-side obligation validation reads `referencia_N`, `desc_N`, `tpvmod_parent_ref_N`, `tpvmod_opcional_id_N`, `tpvmod_opcional_grupo_id_N`. | S13 | `lib/tpvmod_opcionales.php:298-316` | No ad-hoc field is read today. |
| Q4.6 | When `opcionalId <= 0` and `parentRef !== ''`, the server attempts description-based metadata resolution; a match promotes the line to a catalog selection. | S13 | `:318-328` | This is the divergence: a description-matching ad-hoc line can satisfy an obligation server-side. |
| Q4.7 | If after resolution `parentRef === '' || opcionalId <= 0`, the line is skipped (inert). | S13 | `:330-332` | An ad-hoc line with no description match is already inert. |
| Q4.8 | The obligation check runs before any document save; non-empty errors block the save. | S3 | `controller/tpvmod.php:390-398` | Server is authoritative; the client gate is advisory. |
| Q4.9 | Client-side `tpvmod_collect_missing_obligatorios()` counts only rows with a non-empty `data-grupo-id` or `data-opcional-id`; ad-hoc rows (empty ids) are ignored. | S4 (JS) | `tpvmod.js:363-414` | Client already treats ad-hoc as inert. |
| Q4.10 | `tpvmod_get_added_opcional_ids()` and `tpvmod_row_missing_obligatorios()` likewise key on non-empty `data-opcional-id`/`data-grupo-id`. | S4 (JS) | `tpvmod.js:426-460`, `:495-507` | Same inert semantics. |
| Q4.11 | There is **no** `ad_hoc` / `data-ad-hoc` token anywhere in the plugin today (JS, controller, lib, twig). | repo grep | `plugins/tpvmod/**` | `data-ad-hoc` is a DOM-only attribute and is **not submitted**, so it cannot by itself close the server-side divergence. |
| Q4.12 | `tpvmod_render_opcionales_modal()` replaces the entire inner HTML of `#tpvmod_opcionales_list` on every render. | S4 (JS) | `tpvmod.js:553-626`, esp. `:625` | Any static new-opcional form placed inside that container is destroyed (finding F1). |

**Answer to Q4 (sufficiency):** `data-ad-hoc` alone is **not sufficient**. The
server reads no DOM attributes; it reads POST fields only (Q4.5). To close the
divergence deterministically, the row must submit a marker (e.g. a hidden
`tpvmod_opcional_ad_hoc_N=1`) that `tpvmod_validate_obligatorios_post()` reads
and uses to skip the `opcionalId <= 0` description-resolution branch
(`:318-328`) while still skipping the line at `:330-332`. The client-side
collector already ignores ad-hoc rows (Q4.9), so the marker is needed for
server parity and to make the DOM predicate explicit.

### Q5 — Modal-form capability in the AdminLTE theme

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q5.1 | The bundled Bootstrap is 3.4.1 (`"bootstrap": "^3.4.1"`; the shipped JS bundle self-reports `VERSION="3.4.1"`). | S19 | `package.json:29`; `view/js/bootstrap.min.js` | BS3 syntax: `data-dismiss`, `col-sm-*`, `glyphicon`, `pull-right`. |
| Q5.2 | The theme header loads `view/css/bootstrap.min.css` and `view/js/bootstrap.min.js`, so BS3 JS plugins (modal, tab, collapse) are available globally. | S19 (theme) | `themes/AdminLTE/view/header.html.twig:23`, `:43` | |
| Q5.3 | BS3 tabs (`nav nav-tabs`, `role="tab"`, `data-toggle="tab"`, `.tab-pane`) are used across the theme, including inside a modal. | S18; theme | `tpv_cliente_form.html.twig:18-24`; `admin_user.html.twig:194-218` | Tabs-inside-modal is the proven in-repo pattern. |
| Q5.4 | No `data-toggle="collapse"` usage exists in tpvmod views or the AdminLTE theme. | repo grep | `plugins/tpvmod/**`, `themes/AdminLTE/**` | Collapse availability is inferred from the bundled BS3 bundle, not demonstrated in-repo. Prefer tabs or a plain always-visible section; native `<details>` is dependency-free. |
| Q5.5 | Modal forms use `<form id/name ... method="post">` + `{{ csrf_field() }}` + a hidden action flag (`save_cliente_tpv`). | S18 | `tpv_cliente_form.html.twig:11-16` | |
| Q5.6 | Modal body content can be a full form with tiled `.row/.col-*` fields and `form-group` controls. | S17; S18 | `modal_clientes.html.twig:18-40`; `tpv_cliente_form.html.twig:26-65` | |
| Q5.7 | A shared partial included by both TPV screens is an established pattern (`partials/modal_clientes.html.twig`). | S15; S16; S17 | `tpvmod2.html.twig:381`; `tpvmodedita.html.twig:463`; `partials/modal_clientes.html.twig:1-63` | Use a `partials/modal_opcional_nuevo.html.twig` (or equivalent) to avoid drift. |
| Q5.8 | Both opcionales modals are byte-identical shells and both are `#modal_opcionales`; the modal body **is** `#tpvmod_opcionales_list`. | S15; S16 | `tpvmod2.html.twig:383-398`; `tpvmodedita.html.twig:465-480` | Combined with Q4.12, the form cannot live inside `#tpvmod_opcionales_list`. |

### Q6 — Test patterns (RED first)

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q6.1 | The suite runner is `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`; the `tpvmod` testsuite scans `tests/`, and `<source><include>` covers only `lib/`. | S22; S25 | `phpunit.xml:9-19`; `config.yaml:49-55` | Business logic must be in `lib/` to be covered. |
| Q6.2 | `TpvmodOpcionalesTest` loads the lib in `setUp()` and tests pure helpers without a DB. | S20 | `TpvmodOpcionalesTest.php:14-17`, `:19-77` | |
| Q6.3 | The JS-contract test greps `view/js/tpvmod.js` for required symbols (`tpvmod_add_opcional_linea`, `tpvmod_show_opcionales_modal`, `:not(.tpvmod-line-opcional)`, `data-parent-uid`, `data-line-uid`, …). | S20 | `:79-104` | Extend with the new symbols (`ad_hoc` marker, save action). |
| Q6.4 | Test doubles are hand-rolled anonymous classes / `stdClass`; `getMockBuilder` is not used. | S21 | `TpvmodModulesTest.php:316-369`; `TpvmodClienteHelpersTest.php:43`; `TpvmodDiscountsTest.php:21,67,105,121` | Follow this pattern for model/relation doubles. |
| Q6.5 | Assertions in use: `assertSame`, `assertTrue/False`, `assertNotNull`, `assertCount`, `assertStringContainsString`. | S20; S21 | `TpvmodOpcionalesTest.php:19-104`; `TpvmodModulesTest.php:302-369` | |
| Q6.6 | Bootstrap constants come from `tests/bootstrap.php` via `FS_FOLDER`. | S20; S22 | `TpvmodOpcionalesTest.php:16`, `:81`; `phpunit.xml:4` | Use `FS_FOLDER` for file paths. |

### Q7 — XSS / escaping

| # | Claim | Source | Lines | Note |
|---|---|---|---|---|
| Q7.1 | `tpvmod_escape_html(text)` escapes `&`, `<`, `>`, `"` only (not `'`). | S4 (JS) | `tpvmod.js:130-137` | Never interpolate user text into single-quoted attributes. |
| Q7.2 | The modal renderer escapes group names and opcional descriptions with `tpvmod_escape_html` before injecting HTML. | S4 (JS) | `tpvmod.js:576`, `:594`, `:615` | New render path must do the same. |
| Q7.3 | `tpvmod_add_opcional_linea` escapes `ctx.ref` and the description into the row markup; numeric fields (`pvp`, `iva`, `recargo`, `irpf`, `cantidad`) are inserted unescaped. | S4 (JS) | `tpvmod.js:260-277` | The ad-hoc builder must pass numeric values (computed client-side per OD-2). |
| Q7.4 | Twig templates output user data through `{{ }}`; `|raw` is reserved for URLs and `json_encode()` payloads, not user text. | S15; S16 | `tpvmod2.html.twig:15-19`, `:93`; `tpvmodedita.html.twig:17-21`, `:116` | New nombre/descripcion output must use `{{ }}` and must **not** use `|raw`. |
| Q7.5 | The model defensively applies `no_html()` to `codigo`, `nombre`, `descripcion` in `test()`. | S8 | `:339-341` | Defense in depth on the persisted side. |

---

## 5. Confirmed constraints (from sources, binding)

1. **Plugin-local**: nothing outside `plugins/tpvmod/` is edited; `catalogo_core`
   is consumed only. `config.yaml:32-34`; confirmed by all model sources being
   read-only.
2. **Approach B**: business logic in `lib/tpvmod_opcionales.php` (covered by the
   suite `<source>`), with a thin CSRF-validated POST dispatch in
   `controller/tpvmod.php` next to `tpvmod_cliente_ajax_dispatch()`
   (S1:28-58; S3:108-110; S22:15-19).
3. **CSRF on the new write endpoint** via `$ctrl->isCsrfValid()` (S1:124-127;
   S6:432); the read-only `opcionales_articulo` endpoint stays CSRF-free
   (S3:600-618).
4. **Error envelope** `{ok:false, errors:[...]}` and success `{ok:true, ...}`
   (S2:177-196).
5. **JSON transport** uses `_csrf_token` in the POST body; the JS reads it via
   `tpvmodCsrfToken()` (S4:1260-1268) and consumes `dataType:'json'`
   (S5:246-266).
6. **Canonical save sequence** for catalog parity: set fields → `save()` →
   `set_precio_lista()`/`set_porcentaje_lista()` on the default lista
   (S7:193-239), requiring `catalogo_opcional_precio` to be loaded (F7).
7. **OD-5 is enforced by the model**: `catalogo_articulo_opcional::add()`
   rejects grouped opcionales (S9:47-60). TPV-created opcionales must be
   ungrouped.
8. **OD-7 maps to `add_familia_only()`** → `catalogo_opcional_familia::add()`
   (bool) (S8:310-314; S10:39-50).
9. **OD-1 requires no discount bypass**: all lines flow through
   `tpvmod_populate_linea_descuentos()` (S14:478-490; S3:1328-1333, 1390-1395).
10. **Strict TDD active**; tests in `plugins/tpvmod/tests/`, runner
    `plugins/tpvmod/phpunit.xml` (S22; S25:49-55).

---

## 6. Findings that change the plan

| ID | Severity | Finding | Impact |
|---|---|---|---|
| **F1** | High | The opcionales modal body **is** `#tpvmod_opcionales_list` (S15:390; S16:472), and `tpvmod_render_opcionales_modal()` replaces its full innerHTML on every render (S4 JS:625). | The new form cannot live inside `#tpvmod_opcionales_list`. Add a sibling container (second `modal-body`/div) inside `#modal_opcionales`, or change the renderer to target a nested list element. Exploration §2.1's "add below it" must be implemented as a sibling, not a child. |
| **F2** | High | `data-ad-hoc` is DOM-only and never submitted; the server (`tpvmod_opcionales.php:298-328`) reads only `tpvmod_opcional_id_*` / `tpvmod_opcional_grupo_id_*`. | The ad-hoc marker must be a **POST field** (e.g. `tpvmod_opcional_ad_hoc_N=1`) added to the hidden inputs in `tpvmod_add_opcional_linea` (S4 JS:255-277) and read in `tpvmod_validate_obligatorios_post()` to skip the description-resolution branch. "data-ad-hoc marker" wording in the resolved decision must be read as "DOM attribute + submitted hidden field". |
| **F3** | Low | Client-side obligatorios collection already ignores empty-id rows (S4 JS:383-392, 495-507). | No client obligatorios change is required; the marker is needed for server parity and for the explicit DOM predicate. |
| **F4** | Medium | No opcional id is persisted on document lines; on reload the edit screen re-resolves optional lines by description (`tpvmodedita.html.twig:210` → `opcional_line_meta` S3:2372-2384 → `tpvmod_match_opcional_line_in_payload` S13:216-256). | The ad-hoc marker can guarantee inertness only for the request that carries it. After a document is saved and re-opened, an ad-hoc line whose text equals a catalog opcional description is reclassified by the pre-existing heuristic. This is pre-existing behavior; fixing it would need schema/persistence work outside this change. The spec should scope OD-6 to the active session and record the reload limitation. |
| **F5** | Medium | `catalogo_opcional` exposes `get_by_codigo()` but **not** `get_by_nombre()`; `search()` is a `LIKE` filter (S8:270-279, 464-515). | OD-8 ("reuse existing opcional with the same nombre") needs an explicit exact-name lookup implemented in tpvmod's lib (e.g. `search($nombre)` + normalized equality), not a core method. |
| **F6** | Medium | `get_new_codigo()` = `MAX(id)+1`; `test()` rejects duplicate codigo (S8:281-290, 353-357). | OD-9 auto-generation can collide under concurrency; the save helper needs a retry/clear error path. |
| **F7** | Medium | `set_precio_lista()` / `set_porcentaje_lista()` instantiate `catalogo_opcional_precio`, which `catalogo_opcional.php` does not require (S8:578-614). | tpvmod must `require_once FS_FOLDER.'/plugins/catalogo_core/model/core/catalogo_opcional_precio.php'` (and ensure `catalogo_lista_precio` via `tpvmod_default_lista_precio()`, S13:57-72) before replicating the canonical sequence. |
| **F8** | Medium | The parent-line context (`tpvmod_get_parent_line_context_by_uid`, S4 JS:534-551) exposes no `codfamilia`. | OD-3 ("product has no codfamilia → hide/disable Familia") cannot be decided client-side today. Either resolve family availability server-side and return it from the save endpoint / a lookup, or add `codfamilia` to the client context. Plan must state where this comes from. |
| **F9** | Low | Both TPV screens carry identical modal markup and both include `partials/modal_clientes.html.twig` (S15:381, S17; S16:463). | Extract the new form into a shared partial to satisfy OD-10 without drift. |
| **F10** | Low | Bootstrap is 3.4.1 and tabs-inside-modal are proven, but no `data-toggle="collapse"` usage exists in tpvmod/AdminLTE. | Prefer tabs (proven) or a plain section / native `<details>` over a collapse control whose repo usage is uncited. |
| **F11** | Low | `tpvmod_cliente_ajax_emit_json()` does not `exit`; it relies on the handler returning and the dispatcher returning `true` (S1:244-248; S3:108-110). | The new handler must follow the return-to-dispatcher contract, not `exit` like the read endpoint (S3:617). |
| **F12** | Low | The modal renderer caches opcionales by `ref|pvp` (`tpvmod_opcionales_cache`, S4 JS:628, 643-656). | After save+associate, invalidate/refresh the cached payload so the new opcional appears and obligations update. |
| **F13** | Low | `tpvmod_escape_html` does not escape `'` (S4 JS:130-137). | New render path must keep user text in element content (escaped) and not in single-quoted attributes. |
| **F14** | Info | `facturacion-base-opcional` overlaps the plugin but not the modal/create flow; `tpvmod-cliente-modales` owns the `tpvmod_cliente_ajax_dispatch()` slot; `tpvmod-descuentos-cliente` owns `tpvmod_populate_linea_descuentos()`. | The new POST dispatch must sit beside (not replace) the client dispatch: ordering inside `private_core()` matters. Do not modify the discount helpers' contracts (OD-1 already avoids this). |

---

## 7. Contradictions, uncertainty, freshness

- **No contradictory sources found.** The exploration's stated line numbers for
  `Controller/VentasOpcional.php:229-239` (`set_precio_lista`/`set_porcentaje_lista`)
  are confirmed verbatim (S7:229-239).
- **Uncertainty (uncited by repo)**: BS3 collapse plugin availability is inferred
  from the bundled 3.4.1 JS bundle; no in-repo collapse usage exists (F10). Tabs
  are the only in-repo proven in-modal pattern.
- **Uncertainty (pre-existing behavior)**: the description-match heuristic
  (S13:216-256, 318-328) is the only link between saved optional lines and the
  catalog; F4 explains why the ad-hoc marker cannot survive a reload.
- **Freshness**: all repository sources were read on 2026-09-19 from the working
  tree at `/home/javier/proyectos/panel-ab`. No source is derived from memory or
  external documentation.

---

## 8. Product choices (separate, non-authoritative)

Recorded from the RESOLVED section of `decisions-pending.md`; these are product
decisions, not evidence claims.

| OD | Resolution | Evidence status |
|---|---|---|
| OD-1 | Ad-hoc lines receive normal client cascading discounts; entered price is the pre-discount base. | Grounded by S14/S3 (no bypass needed). |
| OD-2 | Percentage computed client-side over `ctx.pvp` at insert time. | Grounded by S4 JS:546 (pvp available); conflicts with S8:111-128 canonical server math by design. |
| OD-3 | No `codfamilia` → hide/disable Familia, force Producto. | Requires F8 resolution (client lacks codfamilia). |
| OD-4 | Auto-add the line after save+associate. | Grounded by S4 JS:692 reuse. |
| OD-5 | TPV-created opcionales always ungrouped. | Grounded by S9:47-60 (model constraint). |
| OD-6 | Ad-hoc lines inert for obligatorios/groups. | Grounded by S4 JS:383-392 (client); requires F2 POST marker for server. |
| OD-7 | Family association links the family only. | Grounded by S8:310-314; S10:39-50. |
| OD-8 | Reuse an existing opcional with the same nombre. | Requires F5 exact-name lookup. |
| OD-9 | Auto-generate codigo via `get_new_codigo()`. | Grounded by S8:281-290; concurrency caveat F6. |
| OD-10 | Both `tpvmod2` and `tpvmodedita`. | Grounded by S15/S16; F9 partial mitigates drift. |

---

## 9. Conclusion

All seven research questions are answered with repository citations. The
implementation shape is confirmed feasible and plugin-local. The plan must
incorporate F1 (form container), F2 (POST-carried ad-hoc marker), F5 (name
lookup), F7 (`catalogo_opcional_precio` require) and F8 (server-side family
resolution) before `sdd-propose`; F4 must be stated as a scoped limitation.

**Outcome: `done`.** Research is complete for the selected lane. Proposal
readiness also depends on confirmed product decisions (already confirmed) and
the OpenSpec store write (this file).

# Proposal: tpvmod-opcional-rapido

> Plugin-local SDD change. Location:
> `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/`.
> The core `openspec/` and `plugins/catalogo_core/` are intentionally NOT
> touched. `catalogo_core` models are consumed read-only.
> Inputs: `exploration.md`, `decisions-pending.md` (RESOLVED), `research.md`.

## Intent

Inside the TPV "Añadir opcional" modal an agent can today only pick a catalog
opcional that already exists for the product. When the needed opcional does not
exist, the agent has to leave the sale, create it in the catalog admin, associate
it, and come back — which breaks the point-of-sale flow.

This change adds an inline quick-create path inside the existing modal so the
agent can compose a new opcional (`nombre`, `descripcion`, `tipo` `fijo` /
`porcentaje`, `valor`) and either:

- **Añadir** — insert it as an ad-hoc line for the current sale only, with
  nothing persisted; or
- **Añadir y guardar opcional** — persist it to the catalog, ask whether to
  associate it with the **product** or its **family**, associate it, and
  auto-add the line to the current sale.

The flow must work on both the new-sale screen (`tpvmod2`) and the document-edit
screen (`tpvmodedita`).

## Scope

### In scope

- Extend the `#modal_opcionales` shell on both TPV screens with a quick-create
  form, extracted into a shared partial to avoid markup drift.
- Client-side ad-hoc line insertion reusing the existing optional-line markup
  and `tpvmod_add_opcional_linea()`.
- One CSRF-validated `POST` write endpoint (`guardar_opcional_tpv`) for
  save-and-associate.
- A submitted ad-hoc marker (hidden POST field + DOM attribute) that keeps
  ad-hoc lines inert for obligatorios/groups on both client and server.
- Business logic under `plugins/tpvmod/lib/` (covered by the plugin PHPUnit
  `<source>include>`) plus a thin dispatch in `controller/tpvmod.php`.
- Strict TDD: RED tests first in `plugins/tpvmod/tests/`.

### Out of scope

- Any edit under `plugins/catalogo_core/` — catalog models are consumed only.
- Group assignment for TPV-created opcionales (always ungrouped, OD-5).
- Family-wide propagation (OD-7 links the family only).
- A discount bypass for ad-hoc lines: they keep normal client cascading
  discounts (OD-1); `tpvmod_populate_linea_descuentos()` is not modified.
- Manual `codigo` input (OD-9: auto-generate only).
- Persisting the ad-hoc intent across save/reload (documented limitation, F4).
- Changes to `tpv-flow` / `tpvmod-config` (caja/terminal behavior untouched).

## Capabilities

### Added

- **`tpv-opcionales`** — inline quick-create of catalog opcionales from the TPV,
  ad-hoc (session-only) opcional lines, and the save/associate flow with
  product or family. This capability does not exist yet in
  `plugins/tpvmod/openspec/specs/`.

### Modified

- **`views`** — the `#modal_opcionales` shell moves to a shared partial
  (`view/partials/modal_opcionales.html.twig`) included by both TPV screens, and
  the new form follows the existing Twig conventions (`{{ }}` only, no `|raw`
  for user text, `{{ csrf_field() }}` in the form).

### Unchanged

- `tpv-flow` and `tpvmod-config` — caja/terminal flow and settings are not
  touched.
- Discount semantics — `tpvmod_populate_linea_descuentos()` and the four
  document save loops keep their current contract (OD-1).

## Confirmed product decisions (binding)

| OD | Resolution | Implementation consequence |
|---|---|---|
| OD-1 | Ad-hoc lines receive the client's normal cascading discounts; the entered price is only the pre-discount base. | No discount bypass; do not touch `tpvmod_populate_linea_descuentos()`. |
| OD-2 | Percentage price computed client-side over the parent product line PVP (`ctx.pvp`). | `tpv_build_ad_hoc_opcional()` receives the PVP base; no extra round-trip. |
| OD-3 | No `codfamilia` → hide/disable Familia, force Producto. | Family availability is resolved server-side and sent to the client. |
| OD-4 | After "Añadir y guardar", auto-add the line to the current sale. | JS inserts the returned opcional as a line immediately after a successful POST. |
| OD-5 | TPV-created opcionales are always ungrouped. | `id_grupo = null`; `catalogo_articulo_opcional::add()` stays valid. |
| OD-6 | Ad-hoc lines are inert for obligatorios/groups. | Submitted `tpvmod_opcional_ad_hoc_N=1` marker; server skips the description-resolution branch. |
| OD-7 | Family association links the family only. | `catalogo_opcional::add_familia_only($codfamilia)`. |
| OD-8 | Reuse an existing opcional with the same nombre. | Exact normalized-name lookup before creating a new row. |
| OD-9 | Codigo auto-generated (`get_new_codigo()`, `OPC####`). | No codigo field; bounded retry on collision. |
| OD-10 | Both `tpvmod2` and `tpvmodedita`. | Shared partial + shared JS path. |

## Approach

### 1. Shared modal partial (findings F1, F9, F10)

- Extract the whole `#modal_opcionales` shell into
  `plugins/tpvmod/view/partials/modal_opcionales.html.twig`, included by
  `tpvmod2.html.twig` and `tpvmodedita.html.twig` (mirrors the proven
  `partials/modal_clientes.html.twig` precedent).
- Inside the modal body, `#tpvmod_opcionales_list` becomes a **nested** element
  (the existing render target, unchanged id) and the new form lives in a
  **sibling** container `#tpvmod_opcional_nuevo_form` (F1). The new form is
  therefore never destroyed by `tpvmod_render_opcionales_modal()`, which
  replaces only the list's inner HTML.
- Use two BS3 tabs ("Opcionales existentes" / "Nuevo opcional"), the only
  in-repo proven in-modal pattern (`view/ajax/tpv_cliente_form.html.twig`).
  Avoid `data-toggle="collapse"` (F10: no in-repo precedent).
- Form fields: `nombre` (required), `descripcion`, `tipo_precio` radios
  (`fijo` / `porcentaje`), `valor` (number; label/step switches with the type),
  and two actions — **Añadir** and **Añadir y guardar opcional**.
- The association prompt (Producto / Familia) is rendered client-side after the
  save action; Familia is hidden/disabled when the product has no `codfamilia`
  (OD-3). Cancelling the prompt aborts without persisting.

### 2. Server-side family availability (finding F8)

- The parent-line context (`tpvmod_get_parent_line_context_by_uid`) exposes no
  `codfamilia`. Add an additive `codfamilia` field to the read payload built by
  `tpvmod_opcionales_for_articulo()` in `lib/tpvmod_opcionales.php`; the JS stores
  it with the modal payload and uses it to show/hide the Familia option.
- The server re-resolves `articulo->codfamilia` from the submitted `referencia`
  at save time and treats it as authoritative; the client value is advisory UX
  only.

### 3. Ad-hoc line marker (findings F2, F3, F13)

- Extend `tpvmod_add_opcional_linea(parentUid, opcional, ...)` so that when
  `opcional.ad_hoc === true` the row carries:
  - DOM attribute `data-ad-hoc="1"` (explicit client predicate), and
  - a hidden submitted input `tpvmod_opcional_ad_hoc_N=1`.
- Modify `tpvmod_validate_obligatorios_post()` in `lib/tpvmod_opcionales.php` to
  read the marker: when set, the line is inert — skip the `opcionalId <= 0`
  description-resolution branch and skip obligation counting, matching the
  client collector which already ignores empty-id rows (F3).
- Pure, testable helper `tpvmod_opcional_is_ad_hoc_post(array $post, int $n): bool`
  encapsulates the marker read.

### 4. Ad-hoc insertion (OD-2, OD-4)

- **Añadir** is fully client-side (no persistence, no round-trip): the JS builds
  a synthetic opcional via a pure lib helper and calls
  `tpvmod_add_opcional_linea()` with `ad_hoc = true`.
- Pure lib helper `tpvmod_build_ad_hoc_opcional(array $input, float $pvpBase): array`
  returns `{id: null, descripcion, precio, grupo_id: null, ad_hoc: true}`,
  computing `porcentaje` as `bround($pvpBase * pct / 100)` (OD-2) and validating
  non-empty `nombre` and non-negative `valor`.
- **Añadir y guardar opcional** posts to the write endpoint; on `{ok:true}` the
  JS auto-adds the returned catalog opcional as a line (OD-4) and invalidates the
  modal cache (F12).

### 5. Write endpoint (findings F5, F6, F7, F11, F14)

- New `lib/tpvmod_opcionales_ajax.php` with:
  - `tpvmod_opcionales_ajax_dispatch(fs_controller $ctrl): bool` — routes on
    `isset($_POST['guardar_opcional_tpv'])`, returns `true` when handled.
  - `tpvmod_opcionales_ajax_save(fs_controller $ctrl): void` — sets
    `$ctrl->template = false`, gates on `$ctrl->isCsrfValid()` (rejecting with
    `{ok:false, errors:['Token CSRF inválido.']}`), then delegates to the lib.
  - `tpvmod_opcionales_ajax_emit_json(array $payload): void` — mirrors
    `tpvmod_cliente_ajax_emit_json()` (header + `json_encode`, **no `exit`**), so
    the handler returns and `private_core()` returns normally (F11).
- In `controller/tpvmod.php`, `require_once` the new lib and add
  `if (tpvmod_opcionales_ajax_dispatch($this)) { return; }` immediately **beside**
  `tpvmod_cliente_ajax_dispatch()` (F14: add, never replace; ordering matters).
- Pure, testable lib helpers:
  - `tpvmod_normalize_opcional_input(array $post): array` — field mapping
    mirroring `VentasOpcional::guardarOpcional` (`nombre`, `descripcion`,
    `tipo_precio` normalized to `fijo|porcentaje`; `porcentaje = value, precio = 0`
    or `porcentaje = null, precio = value`), returning normalized data or errors.
  - `tpvmod_match_opcional_by_nombre(array $candidates, string $nombre): ?array`
    — exact normalized-name lookup for OD-8 (F5: `catalogo_opcional` has
    `get_by_codigo()` but no exact name lookup and `search()` is `LIKE`-only).
  - `tpvmod_next_opcional_codigo(callable $generator, callable $exists): ?string`
    — bounded collision retry around `get_new_codigo()` for OD-9 (F6).
  - `tpvmod_resolve_asociacion_target(array $post, string $codfamilia): array`
    — decides `producto|familia` and validates that Familia is only reachable
    with a non-empty `codfamilia` (OD-3).
- Save sequence (orchestration in the ajax lib, SQL touched through catalog
  models only):
  1. Normalize/validate input; on errors return `{ok:false, errors}`.
  2. Look up an existing opcional by exact nombre; if found, reuse it (OD-8).
  3. Otherwise create `catalogo_opcional` with the normalized fields,
     `activo = true`, `id_grupo = null` (OD-5), auto codigo with retry
     (OD-9/F6), then `save()`.
  4. Replicate catalog parity: `set_porcentaje_lista()` / `set_precio_lista()`
     on the default lista from `tpvmod_default_lista_precio()`. This requires an
     explicit `require_once` of
     `plugins/catalogo_core/model/core/catalogo_opcional_precio.php` (F7:
     `catalogo_opcional.php` does not load it).
  5. Associate: `producto` → `catalogo_articulo_opcional::add($referencia, $id, false)`;
     `familia` → `catalogo_opcional::add_familia_only($codfamilia)` (OD-7).
  6. Return `{ok:true, opcional:{id, codigo, nombre, descripcion, precio,
     tipo_precio, porcentaje, grupo_id:null}, codfamilia}`.

### 6. JS wiring (`view/js/tpvmod.js`)

- New `tpvmod_add_opcional_ad_hoc(parentUid)` (build + insert the ad-hoc line).
- New `tpvmod_save_opcional_tpv(parentUid)` (POST `guardar_opcional_tpv` with
  `_csrf_token` via `tpvmodCsrfToken()`, `dataType:'json'`, `{ok,errors}`
  handling, auto-add on success).
- Invalidate `tpvmod_opcionales_cache[ref|pvp]` and refresh the list after a
  successful save (F12).
- Escape all user text with `tpvmod_escape_html()` and keep it in element
  content, never in single-quoted attributes (F13).

### 7. Tests (strict TDD, RED first)

- New `plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php` for the pure helpers:
  ad-hoc build (`fijo` passthrough, `porcentaje` = `bround(pvp*pct/100)`),
  empty/negative validation, name match, codigo retry, association target
  resolution, and the ad-hoc marker gate returning inert for obligatorios.
- Extend the existing JS-grep contract test in `TpvmodOpcionalesTest.php` with
  the new symbols (`tpvmod_add_opcional_ad_hoc`, `tpvmod_save_opcional_tpv`,
  `guardar_opcional_tpv`, `tpvmod_opcional_ad_hoc_`, `data-ad-hoc`) so the wiring
  cannot silently regress.
- Runner: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| **F4 — ad-hoc lines reclassified on reload.** No opcional id is persisted on document lines; on reload the edit screen re-resolves lines by description (`tpvmod_match_opcional_line_in_payload`). An ad-hoc line whose text equals a catalog opcional can be reclassified after save/reload. | Medium | Documented, scoped limitation: OD-6 inertness is guaranteed only for the request that carries the marker. The spec scopes the guarantee to the active session; fixing reload would require document-line persistence outside this change. |
| **Codigo collision (F6).** `get_new_codigo()` uses `MAX(id)+1`; concurrent saves can collide and `save()` rejects the duplicate. | Medium | Bounded retry via `tpvmod_next_opcional_codigo()`; on exhaustion return `{ok:false, errors:[...]}`, never a silent failure. |
| **Cache staleness (F12).** Modal payloads are cached by `ref|pvp`; a new opcional would not appear. | Low | Invalidate the cache key after a successful save and re-render the list. |
| **Modal duplication across two templates (F9).** `tpvmod2` and `tpvmodedita` carry identical shell markup; drift is likely. | Medium | Single shared partial `partials/modal_opcionales.html.twig` included by both screens. |
| **Form destroyed by the renderer (F1).** `tpvmod_render_opcionales_modal()` replaces the full inner HTML of its target. | High | The new form lives in a sibling container; the renderer keeps targeting the nested `#tpvmod_opcionales_list` only. |
| **Client/server obligatorios divergence (F2).** The server's description-based fallback can promote an ad-hoc line to a catalog selection, while the client ignores empty-id rows. | High | Submitted hidden marker `tpvmod_opcional_ad_hoc_N=1` read by `tpvmod_validate_obligatorios_post()`; the line is inert on both sides. |
| **XSS (F13).** New fields are rendered by the JS modal path and by Twig. | High | `tpvmod_escape_html()` for element content only; no user text in single-quoted attributes; Twig `{{ }}` with no `|raw`; model-side `no_html()` as defense in depth. |
| **CSRF.** New write endpoint must not be open. | High | `$ctrl->isCsrfValid()` gate with `{ok:false, errors}` on failure; the read-only `opcionales_articulo` endpoint stays CSRF-free. |
| **Dispatch collision with active changes (F14).** `tpvmod-cliente-modales` owns `tpvmod_cliente_ajax_dispatch()`. | Medium | Add the new dispatch beside it, return-based (no `exit`); do not modify the client dispatch or the discount helpers. |
| **Family association blast radius (OD-7).** `add_familia()` would assign the opcional to every family article. | Medium | Use `add_familia_only()` (family link only); no propagation. |
| **Missing model dependency (F7).** `set_precio_lista()` / `set_porcentaje_lista()` instantiate `catalogo_opcional_precio`, not required by `catalogo_opcional.php`. | Medium | Explicit `require_once` of the model before the parity step. |
| **Name lookup gap (F5).** No `get_by_nombre()`; `search()` is `LIKE`-based. | Low | Exact normalized-name match implemented in tpvmod's lib over `search()` results. |

## Success criteria

- [ ] `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml` is green,
      including the new pure-helper tests and the extended JS-grep contract test.
- [ ] "Añadir" inserts an ad-hoc line for the current sale and persists nothing
      to the catalog.
- [ ] `tipo = porcentaje` computes the ad-hoc unit price as
      `bround(parentLinePvp * pct / 100)`.
- [ ] "Añadir y guardar opcional" → Producto persists a new opcional, associates
      it with the product, and auto-adds the line.
- [ ] "Añadir y guardar opcional" → Familia links the family only (no
      propagation), and the Familia option is hidden/disabled when the product
      has no `codfamilia`.
- [ ] An existing opcional with the same nombre is reused, not duplicated.
- [ ] Ad-hoc lines never satisfy obligatorios and never count in exclusive
      groups (client and server), even when their description matches a catalog
      opcional.
- [ ] Saving a document applies the client's normal cascading discounts to
      ad-hoc lines (OD-1), with coherent totals.
- [ ] A request to `guardar_opcional_tpv` without a valid CSRF token is rejected
      with `{ok:false, errors:[...]}` and persists nothing.
- [ ] Both `tpvmod2` and `tpvmodedita` expose the same flow through the shared
      partial.
- [ ] No file outside `plugins/tpvmod/` is modified; the core `openspec/` and
      `plugins/catalogo_core/` remain untouched.

## Review-budget note

Delivery is a single PR with an **800-line review budget**. Forecast: shared
partial (~60), two template includes (~10), JS additions (~250–350), new
`lib/tpvmod_opcionales_ajax.php` (~200–300), `lib/tpvmod_opcionales.php` marker
change (~20), controller dispatch (~10), tests (~200–300). This lands at roughly
**750–1000 changed lines**, so the change is **at risk of exceeding the budget**,
driven by the JS and test surfaces. Mitigations to stay inside: do not refactor
the existing modal renderer, keep the new lib helpers minimal, and extend the
existing test files where possible instead of adding broad new coverage. If the
implementation forecast crosses 800 lines, stop and request a chained-PR
decision (backend lib/controller/tests, then JS/templates) rather than silently
exceeding the budget.

## Research traceability

| Finding | Where the plan incorporates it |
|---|---|
| F1 | Shared partial; form in sibling `#tpvmod_opcional_nuevo_form`; renderer target nested. |
| F2 | Submitted hidden `tpvmod_opcional_ad_hoc_N=1` marker read server-side (not a DOM-only attribute). |
| F4 | Documented scoped limitation in Risks and Success criteria. |
| F5 | `tpvmod_match_opcional_by_nombre()` exact lookup. |
| F6 | `tpvmod_next_opcional_codigo()` bounded retry. |
| F7 | Explicit `require_once` of `catalogo_opcional_precio.php`. |
| F8 | `codfamilia` added to the read payload; re-resolved server-side at save time. |

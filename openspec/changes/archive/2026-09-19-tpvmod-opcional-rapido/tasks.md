# Tasks: tpvmod-opcional-rapido

> Plugin-local change. Scope: `plugins/tpvmod/` only — the core `openspec/` and
> `plugins/catalogo_core/` are never modified; catalog models are consumed.
> Strict TDD is active: every production task is preceded by its RED test.
> Runner: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~985 (design Size Plan) |
| Session review budget | 800 changed lines |
| Budget delta | ~185 lines over (~23%) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Delivery strategy | single-pr **with `size:exception` GRANTED** (user-approved 2026-09-19) |
| Chain strategy | n/a — single PR |
| Decision needed before apply | No — resolved (explicit size exception) |
| Recommended work-unit boundaries | WU1 backend (`lib/` + `controller/` + tests, ~591) → WU2 view wiring (`view/` + view tests, ~393) |

Guard contract (exact lines):

```text
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High
```

- Estimated changed lines: ~985
- Chained PRs recommended: Yes
- 400-line budget risk: High
- Decision needed before apply: Yes
- Recommended work-unit boundaries: WU1 backend → WU2 view wiring

Rationale: the design estimates ~985 changed lines, above the session's 800-line
`single-pr` budget and far above the generic 400-line guard. No `size:exception`
has been granted, so `single-pr` must not proceed without an explicit decision.
The two work units below land under 800 each (WU1 ~591, WU2 ~393), so chained PRs
are viable; both still exceed 400, which is why the session raised the budget to
800. If the team wants one PR, grant an explicit `size:exception` before apply;
otherwise chain along these boundaries.

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| WU1 | Backend: pure helpers, dispatch/save/persist orchestration, controller wiring | PR 1 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodOpcionalRapido` | Plugin suite green; manual POST to `guardar_opcional_tpv` with and without CSRF returns `{ok:...}` | Revert PR 1 commits: `lib/tpvmod_opcionales_ajax.php` (create), `lib/tpvmod_opcionales.php`, `controller/tpvmod.php`, `tests/TpvmodOpcionalRapidoTest.php` (create), and the WU1 assertion update in `tests/TpvmodOpcionalesTest.php`; no view dependency |
| WU2 | View wiring: shared partial, both screens, JS ad-hoc builder/insert/save, grep contracts | PR 2 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodTwigTemplates` (+ `--filter TpvmodOpcionales` for the JS-grep contract) | Smoke on `tpvmod2` and `tpvmodedita`: Añadir, Añadir y guardar → Producto/Familia, reuse, obligatorios inert, discount parity | Revert PR 2 commits: `view/partials/modal_opcionales.html.twig` (create), `view/tpvmod2.html.twig`, `view/tpvmodedita.html.twig`, `view/js/tpvmod.js`, and the WU2 test additions to `tests/TpvmodTwigTemplatesTest.php` and `tests/TpvmodOpcionalesTest.php`; WU1 backend stays valid standalone |

| Work unit | Depends on |
|---|---|
| WU1 | none |
| WU2 | WU1 (endpoint name, `codfamilia` payload key, ad-hoc builder shape) |

## Design precision notes (binding for apply)

- **Model sanitization boundary.** Pure `tpvmod_normalize_opcional_input()` and
  `tpvmod_build_ad_hoc_opcional()` tests assert only their own contract (field
  mapping, validation, price math). They MUST NOT be described as asserting the
  model's `no_html()` contract; persisted-field sanitization is verified at the
  catalog model's own boundary (task 7.1).
- **Save/persist split.** `tpvmod_opcionales_ajax_save()` performs the CSRF gate,
  input normalization and target resolution; it then calls
  `tpvmod_opcionales_ajax_persist()`, which receives already-normalized data and
  runs match/create/parity/associate. `persist()` does not normalize.
- **Data flow.** `tpvmod_opcionales_ajax_dispatch()` → `tpvmod_opcionales_ajax_save()`
  → `tpvmod_opcionales_ajax_persist()`. Keep the three stages explicit; do not
  collapse save and persist.

## Spec traceability

| Work unit | Requirements satisfied |
|---|---|
| WU1 | `tpv-opcionales`: Ad-hoc price semantics (OD-2, server mirror), Ad-hoc inert for obligatorios/groups (server gate), Save-and-associate ungrouped + auto-add (OD-4/5/9), Association target resolution (OD-3/F8), Familia links the family only (OD-7), Reuse by normalized nombre (OD-8/F5), CSRF-gated write endpoint, Catalog parity on the default lista (F7) |
| WU2 | `tpv-opcionales`: Dual modal affordance (OD-10), Ad-hoc line is session-only, Ad-hoc price semantics (OD-2, client), Ad-hoc lines keep client discounts (OD-1, row contract), Ad-hoc inert (client + marker), Association Familia hide (OD-3), User text escaped end to end (F13) · `views` delta: CSRF via Twig only, Shared partial (OD-10/F9), Sibling form container (F1), BS3 tabs (F10), Twig escaping (F13) |

---

## Work Unit 1 — Backend foundation (PR 1)

**Files:** `plugins/tpvmod/lib/tpvmod_opcionales_ajax.php` (create) · `plugins/tpvmod/lib/tpvmod_opcionales.php` (modify) · `plugins/tpvmod/controller/tpvmod.php` (modify) · `plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php` (create) · `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` (one assertion).
**Depends on:** none.
**Satisfies:** see Spec traceability (WU1).
**Verification step:** task 4.6.

### Phase 1 — RED: pure helper contracts

- [x] 1.1 Create `plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php` (namespace `Tests\Tpvmod`; `setUp()` requires `lib/tpvmod_opcionales.php` and `lib/tpvmod_opcionales_ajax.php`); RED tests for `tpvmod_build_ad_hoc_opcional`: `fijo` passthrough, `porcentaje = bround(pvp*pct/100)`, empty `nombre` and negative/non-numeric `valor` rejected (spec: Ad-hoc price semantics, OD-2).
- [x] 1.2 RED tests for `tpvmod_opcional_is_ad_hoc_post()` — marker present/absent for field `tpvmod_opcional_ad_hoc_N` (spec: inert for obligatorios, OD-6/F2).
- [x] 1.3 RED tests for `tpvmod_normalize_opcional_input()`: `activo = true`, `id_grupo = null`, unknown `tipo_precio` → `fijo`, `precio`/`porcentaje` mapping, empty `nombre` error (spec: Save-and-associate, OD-5). Assert only the normalize contract — no model `no_html()` claim.
- [x] 1.4 Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodOpcionalRapido`; confirm RED (undefined functions).

### Phase 2 — GREEN: pure helpers in `lib/tpvmod_opcionales.php`

- [x] 2.1 Add `tpvmod_build_ad_hoc_opcional()` and `tpvmod_opcional_is_ad_hoc_post()` to `plugins/tpvmod/lib/tpvmod_opcionales.php` (spec: Ad-hoc price semantics; inert lines).
- [x] 2.2 Add `tpvmod_articulo_codfamilia()` and the `codfamilia` key to both returns of `tpvmod_opcionales_for_articulo()`; update the existing empty-payload assertion in `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` to include `'codfamilia' => ''` (RED before GREEN) (spec: Association target resolution, OD-3/F8).
- [x] 2.3 Add the marker short-circuit to `tpvmod_validate_obligatorios_post()` immediately after the optional-line prefix check; a marker-carrying line skips the description-resolution branch and is not counted (RED first) (spec: inert lines; threat matrix HTTP routing input).
- [x] 2.4 Run the focused filter: pure-helper tests green.

### Phase 3 — RED: dispatch, CSRF gate, orchestration seams

- [x] 3.1 RED tests for `tpvmod_match_opcional_by_nombre()` (normalized equality), `tpvmod_bump_opcional_codigo()` and `tpvmod_next_opcional_codigo()` (first free candidate; `null` after exhaustion) (spec: Reuse by nombre OD-8/F5; auto `OPC####` OD-9/F6).
- [x] 3.2 RED tests for `tpvmod_resolve_asociacion_target()`: `producto` accepted; `familia` rejected when server `codfamilia` is empty; `familia` accepted otherwise (spec: Association target resolution).
- [x] 3.3 RED tests for `tpvmod_opcionales_ajax_dispatch()` (`true` on `guardar_opcional_tpv`, `false` otherwise) and `tpvmod_opcionales_ajax_emit_json()` (JSON body, `application/json` header, no `exit`), captured with `ob_start()`/`ob_get_clean()` (spec: CSRF-gated write endpoint; threat matrix HTTP routing).
- [x] 3.4 RED test for `tpvmod_opcionales_ajax_save()` with an anonymous `fs_controller` double whose `isCsrfValid()` is `false`: emits `{ok:false, errors:['Token CSRF inválido.']}` and persists nothing (spec: CSRF-gated write endpoint).
- [x] 3.5 RED test for `tpvmod_opcionales_ajax_opcional_payload()` from a model double: `{id, codigo, nombre, descripcion, precio, tipo_precio, porcentaje, grupo_id:null}` (spec: Save-and-associate).
- [x] 3.6 RED orchestration tests for `tpvmod_opcionales_ajax_persist()` (receives already-normalized `$data`; injected `$models` factory of doubles): reuse branch does not call `save()`, create branch does (OD-8); `set_precio_lista()` for `fijo` / `set_porcentaje_lista()` for `porcentaje` on the plugin default lista (F7/OD-9); `familia` calls `add_familia_only()` once, never `add_familia()`, 1 family relation and 0 article relations (OD-7); `producto` calls the article relation once and never the family relation (OD-3); retry exhaustion and association failure return `{ok:false, errors:[...]}`.

### Phase 4 — GREEN: ajax lib + controller wiring

- [x] 4.1 Create `plugins/tpvmod/lib/tpvmod_opcionales_ajax.php`: `require_once` `lib/tpvmod_opcionales.php`; implement `match`, `bump`/`next_codigo`, `resolve_asociacion_target`, `dispatch`, `emit_json`, `save` (CSRF gate + normalize + target resolution + delegation to persist), `persist` (match/reuse → create → `save()` → explicit `require_once` of `plugins/catalogo_core/model/core/catalogo_opcional_precio.php` + parity → associate) and `opcional_payload` (spec: all WU1 requirements).
- [x] 4.2 Set `$ctrl->template = false` in the write handler and keep the return-to-dispatcher contract (no `exit`) (spec: CSRF-gated write endpoint).
- [x] 4.3 Modify `plugins/tpvmod/controller/tpvmod.php`: `require_once` the new lib and add `if (tpvmod_opcionales_ajax_dispatch($this)) { return; }` immediately after `tpvmod_cliente_ajax_dispatch()`; the client dispatch is not modified (spec: CSRF-gated write endpoint; F14).
- [x] 4.4 Add a controller-order contract test to `plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php` asserting `plugins/tpvmod/controller/tpvmod.php` contains `tpvmod_cliente_ajax_dispatch` before `tpvmod_opcionales_ajax_dispatch` (RED before 4.3).
- [x] 4.5 Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodOpcionalRapido`; WU1 tests green.
- [x] 4.6 **WU1 verification step:** run the full plugin suite `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`; no regressions (PR 1 gate).

---

## Work Unit 2 — View wiring (PR 2, depends on WU1)

**Files:** `plugins/tpvmod/view/partials/modal_opcionales.html.twig` (create) · `plugins/tpvmod/view/tpvmod2.html.twig` (modify) · `plugins/tpvmod/view/tpvmodedita.html.twig` (modify) · `plugins/tpvmod/view/js/tpvmod.js` (modify) · `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (modify) · `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` (modify).
**Depends on:** WU1 (endpoint `guardar_opcional_tpv`, `codfamilia` payload key, ad-hoc builder shape, marker field name).
**Satisfies:** see Spec traceability (WU2).
**Verification step:** task 6.7.

### Phase 5 — RED: view contracts

- [x] 5.1 Extend `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php`: add `partials/modal_opcionales.html.twig` to the inventory; assert both screens `{% include 'partials/modal_opcionales.html.twig' %}` and no inline `id="modal_opcionales"`; assert the partial has BS3 tabs (`data-toggle="tab"`, `.tab-pane`), `{{ csrf_field() }}`, nested `#tpvmod_opcionales_list`, sibling `#tpvmod_opcional_nuevo_form`, no `data-toggle="collapse"`, no `|raw` on `nombre`/`descripcion` (spec: views delta — shared partial, sibling container, BS3 tabs, Twig escaping; threat matrix XSS/render).
- [x] 5.2 Extend the CSRF contract test: every `<form method="post"` template under `plugins/tpvmod/view/` contains `{{ csrf_field() }}` and no `{$fsc->csrf_field` or `csrf_field|raw` appears (spec: views delta — CSRF via Twig only).
- [x] 5.3 Extend `plugins/tpvmod/tests/TpvmodOpcionalesTest.php` JS-grep: `tpvmod_build_ad_hoc_opcional`, `tpvmod_add_opcional_ad_hoc`, `tpvmod_save_opcional_tpv`, `guardar_opcional_tpv`, `tpvmod_opcional_ad_hoc_`, `data-ad-hoc`, `codfamilia`, and `tpvmod_escape_html` around the ad-hoc description (spec: dual affordance, ad-hoc session-only, row contract for discounts, inert marker, Familia flag, escaping).
- [x] 5.4 Run both filtered suites (`TpvmodOpcionales`, `TpvmodTwigTemplates`); confirm RED.

### Phase 6 — GREEN: partial, screens, JS

- [x] 6.1 Create `plugins/tpvmod/view/partials/modal_opcionales.html.twig`: `#modal_opcionales` with BS3 tabs; pane 1 holds the nested `#tpvmod_opcionales_list`; pane 2 holds the sibling `#tpvmod_opcional_nuevo_form` with `<form id="f_opcional_nuevo" method="post" onsubmit="return false;">`, `{{ csrf_field() }}`, hidden `guardar_opcional_tpv`, `nombre` (required), `descripcion`, `tipo_precio` (`fijo`/`porcentaje`), `valor`, and the **Añadir** and **Añadir y guardar opcional** actions; user text via `{{ }}` only (spec: dual affordance; views delta).
- [x] 6.2 Replace the inline `#modal_opcionales` shell in `plugins/tpvmod/view/tpvmod2.html.twig` and `plugins/tpvmod/view/tpvmodedita.html.twig` with the include (spec: dual affordance OD-10; shared partial).
- [x] 6.3 In `plugins/tpvmod/view/js/tpvmod.js`: extend `tpvmod_add_opcional_linea()` so `ad_hoc` rows carry `data-ad-hoc="1"`, hidden `tpvmod_opcional_ad_hoc_N=1`, empty `tpvmod_opcional_id_N`, empty `tpvmod_opcional_grupo_id_N` plus the standard numeric fields; extend `tpvmod_normalize_opcionales_payload()` to carry `codfamilia` (spec: session-only line; discount row contract OD-1; inert marker; Familia flag).
- [x] 6.4 Add `tpvmod_build_ad_hoc_opcional()` (JS mirror of the PHP builder), `tpvmod_add_opcional_ad_hoc(parentUid)`, `tpvmod_save_opcional_tpv(parentUid)` (POST `guardar_opcional_tpv` with `tpvmodCsrfToken()`, `dataType:'json'`, `{ok,errors}` handling), `tpvmod_reset_opcional_nuevo_form()`, and cache invalidation of `tpvmod_opcionales_cache[ref|pvp]` on success (spec: ad-hoc price semantics OD-2; save-and-associate auto-add OD-4; F12).
- [x] 6.5 Association prompt on save: Producto/Familia; Familia hidden/disabled when the server `codfamilia` is empty and Producto forced (spec: association target resolution OD-3).
- [x] 6.6 Route all user text through `tpvmod_escape_html()` as element content, never into single-quoted attributes (spec: escaping F13).
- [x] 6.7 **WU2 verification step:** run the full plugin suite `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`; green (PR 2 gate).

---

## Phase 7 — Verification (spec scenarios with no PHP-unit path)

- [x] 7.1 Model sanitization at its own boundary: verify `no_html()` on persisted `nombre`/`descripcion` at the catalog model boundary (read-only contract) — not through the pure builders. No DB-free harness exists (`catalogo_opcional::__construct()` bootstraps `fs_db2`/`check_table()`), so it is recorded in `smoke-checklist.md` instead of asserted in `TpvmodOpcionalRapidoTest` (spec: escaping F13).
- [x] 7.2 Grep audit: no file outside `plugins/tpvmod/` is modified; `tpvmod_populate_linea_descuentos()` and the four document save loops are unchanged (spec: discount parity OD-1). Verified: core repo clean, `catalogo_core` clean, core `openspec/` clean; `lib/tpvmod_modules.php` untouched; controller diff is only the `require_once` + dispatch guard.
- [ ] 7.3 Smoke (manual, `config.yaml` checklist): **Añadir** persists nothing; save → Producto and save → Familia; reuse by normalized nombre; an ad-hoc line whose description matches a catalog opcional does not satisfy obligatorios; **discount parity of an ad-hoc line vs a catalog line for a discounted client** (OD-1) (spec: session-only, reuse OD-8, inert OD-6, discounts OD-1). Pending manual browser+DB smoke: not executable by the apply executor.
- [ ] 7.4 Smoke on both `tpvmod2` and `tpvmodedita`: same partial, same flow, same modal id (spec: dual affordance OD-10; views delta). Pending manual browser smoke: not executable by the apply executor.

> Smoke checklist for 7.1, 7.3 and 7.4: `smoke-checklist.md` (same change directory).

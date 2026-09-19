# Design: tpvmod-opcional-rapido

> Plugin-local SDD. Scope: `plugins/tpvmod/` only. `plugins/catalogo_core/` and the
> core `openspec/` are never modified; catalog models are consumed read/write-only.
> Inputs: `proposal.md`, `specs/tpv-opcionales/spec.md`, `specs/views/spec.md`,
> `research.md` (F1–F14), `decisions-pending.md` §RESOLVED (OD-1..OD-10).

## Technical Approach

Add an inline quick-create path inside the existing `#modal_opcionales` shell.
Business logic and orchestration live in `lib/` (the only directory the plugin
PHPUnit `<source><include>` covers); the controller gains one `require_once` and
one return-based dispatch line beside `tpvmod_cliente_ajax_dispatch()`. The modal
shell moves to a shared Twig partial included by both TPV screens, and the JS in
`tpvmod.js` gains an ad-hoc builder/inserter plus a JSON POST handler. No catalog
model, schema, or core file changes.

## Architecture Decisions

### AD-1 — lib-vs-controller split

**Choice**: all domain logic and the save orchestration in
`lib/tpvmod_opcionales_ajax.php` (new) and additive helpers in
`lib/tpvmod_opcionales.php`; `controller/tpvmod.php` only requires the lib and
dispatches.
**Alternatives considered**: logic in the controller (unmeasurable — the plugin
suite covers only `lib/`); a new Symfony service under `src/` (a core edit,
violates plugin-local scope).
**Rationale**: mirrors the proven `tpvmod_cliente_ajax.php` split (S1) and makes
every helper unit-testable under `plugins/tpvmod/phpunit.xml`.

### AD-2 — Return-based dispatch, no `exit`, ordered beside the client dispatch

**Choice**: `tpvmod_opcionales_ajax_dispatch(fs_controller $ctrl): bool` routes on
`isset($_POST['guardar_opcional_tpv'])` and returns `true` when handled;
`tpvmod_opcionales_ajax_emit_json(array $payload): void` does `header()` +
`json_encode()` and **returns** (no `exit`). Insert a second guard immediately
after the existing one:

```php
if (tpvmod_cliente_ajax_dispatch($this)) {
   return;
}
if (tpvmod_opcionales_ajax_dispatch($this)) {
   return;
}
else if( isset($_REQUEST['datoscliente']) )
```

The trailing `else if` chain then attaches to the new `if`; that is harmless
because the new handler returns whenever it matches.
**Alternatives considered**: `exit` like the read endpoint (S3:617) — breaks the
controller's return contract and is un-testable; replacing/merging into the client
dispatcher — F14 forbids touching it.
**Rationale**: F11 and the existing write handlers (S1) use the return contract;
F14 requires adding beside, never replacing, the client dispatch.

### AD-3 — Shared partial, sibling form container, BS3 tabs

**Choice**: new `view/partials/modal_opcionales.html.twig` owns `#modal_opcionales`
with two BS3 tab panes: pane 1 contains the nested `#tpvmod_opcionales_list`
(unchanged render target), pane 2 contains the sibling `#tpvmod_opcional_nuevo_form`
(`<form id="f_opcional_nuevo" method="post" onsubmit="return false;">` +
`{{ csrf_field() }}`). Both screens replace their inline shell with
`{% include 'partials/modal_opcionales.html.twig' %}`.
**Alternatives considered**: a `data-toggle="collapse"` control (no in-repo
precedent, F10); keeping the form inside `#tpvmod_opcionales_list` (destroyed by
`tpvmod_render_opcionales_modal()`, F1); duplicating markup in both screens (drift,
F9).
**Rationale**: BS3 tabs inside a modal are the only proven in-repo pattern
(`tpv_cliente_form.html.twig`); a sibling pane guarantees the renderer's
`$('#tpvmod_opcionales_list').html(...)` cannot remove the form.

### AD-4 — Submitted ad-hoc marker, not a DOM-only attribute

**Choice**: ad-hoc rows carry DOM `data-ad-hoc="1"` **and** a hidden submitted
field `tpvmod_opcional_ad_hoc_N=1`. A pure predicate
`tpvmod_opcional_is_ad_hoc_post(array $post, int $n): bool` reads the POST field.
`tpvmod_validate_obligatorios_post()` short-circuits right after the optional-line
prefix check:

```php
$desc = (string) ($post['desc_' . $i] ?? '');
if (!tpvmod_is_opcional_line_description($desc)) { continue; }
if (tpvmod_opcional_is_ad_hoc_post($post, $i)) { continue; }
```

so no description-resolution and no obligation counting occur for that line.
**Alternatives considered**: DOM attribute alone (the server reads POST only, F2);
relying on the empty `tpvmod_opcional_id_N` (the server's description fallback
promotes it, producing the client/server divergence, F2/Q4).
**Rationale**: F2/F3 — the marker makes client and server agree deterministically
for the request that carries it.

### AD-5 — Server-authoritative `codfamilia`

**Choice**: add a `codfamilia` key to the payload returned by
`tpvmod_opcionales_for_articulo()` (resolved through a new
`tpvmod_articulo_codfamilia(string $referencia): string` helper). At save time the
server re-resolves it from the submitted `referencia` and
`tpvmod_resolve_asociacion_target(array $post, string $codfamilia): array` rejects
`familia` when it is empty. The client flag is advisory UX only.
**Alternatives considered**: a new lookup endpoint (extra round-trip; the read
endpoint already returns the payload); client-only gating (spoofable; violates the
spec's "server overrides" scenario).
**Rationale**: F8 and the OD-3 scenarios require both the pre-emptive hide and an
authoritative server rejection.

### AD-6 — Reuse by normalized `nombre`

**Choice**: `tpvmod_match_opcional_by_nombre(array $candidates, string $nombre): ?array`
compares `mb_strtolower(trim())` with collapsed internal whitespace. Candidates
come from `catalogo_opcional::search($nombre)` (a LIKE prefilter on codigo/nombre),
then exact normalized equality selects the row.
**Alternatives considered**: adding `get_by_nombre()` to `catalogo_opcional`
(forbidden — catalog edit); `reject` or `create duplicate` (both violate OD-8).
**Rationale**: F5 — no exact-name lookup exists; the LIKE result is a small
superset that the pure helper filters safely.

### AD-7 — Bounded codigo retry

**Choice**: `tpvmod_next_opcional_codigo(string $base, callable $exists, int $maxAttempts = 5): ?string`
returns the first candidate for which `$exists($candidate)` is false, using the
pure `tpvmod_bump_opcional_codigo(string $codigo, int $attempt): string` to
increment the numeric suffix while keeping the `OPC####` shape; returns `null`
after exhaustion. Production wires `$exists = fn($c) => (bool) $opcional->get_by_codigo($c)`.
**Alternatives considered**: a `generator`/`exists` pair of callables with an
attempt contract (same behavior, but a `attempt`-aware generator is harder to make
pure); retrying `save()` blindly (re-runs `test()`, produces unclear errors).
**Rationale**: F6 — `get_new_codigo()` is `MAX(id)+1` and not concurrency-safe;
exhaustion must be a structured failure (spec scenario "Retry exhaustion").

### AD-8 — Catalog parity on the default lista, with explicit persistence invariants

**Choice**: after a successful `save()`, call
`set_precio_lista($lista, $precio)` for `fijo` or
`set_porcentaje_lista($lista, $porcentaje)` for `porcentaje`, with
`$lista = tpvmod_default_lista_precio()`, after an explicit
`require_once .../catalogo_opcional_precio.php`. The persisted row MUST satisfy:
`activo = true`, `id_grupo = null` (always ungrouped, OD-5), `tipo_precio` in
`{fijo, porcentaje}`, and exactly one of `{precio, porcentaje}` populated. The
anchor for that invariant is the normalize contract:

```
tpvmod_normalize_opcional_input(array $post): array
  → ['ok' => bool, 'errors' => list<string>, 'data' => ?array]
     data = [
       'nombre'      => string,                    // trim, required, <= 100 chars
       'descripcion' => string,                    // trim, may be empty
       'tipo_precio' => 'fijo'|'porcentaje',       // anything else → 'fijo'
       'precio'      => float,                     // = valor when fijo, 0.0 when porcentaje
       'porcentaje'  => null|float,                // = valor when porcentaje, null when fijo
       'activo'      => true,                      // fixed (OD-5/R6)
       'id_grupo'    => null,                      // fixed (OD-5/R6)
     ]
```

`valor` is parsed with `str_replace(',', '.', ...)`; a negative or non-numeric
`valor` is an error, and an empty `nombre` is an error. `tpvmod_opcionales_ajax_persist()`
assigns exactly these keys onto the model before `save()` and therefore cannot
introduce a grouped or inactive opcional.
**Alternatives considered**: skipping parity (a TPV-created opcional would show an
incorrect price when picked later); a generic sync helper (none exists); letting
the AJAX layer map fields ad hoc (no single invariant anchor, untestable).
**Rationale**: F7 + S7:193-239 — replicate the canonical `VentasOpcional` sequence
so the reused/created opcional behaves identically outside the TPV, and pin the
OD-5/R6 invariants (`activo = true`, `id_grupo = null`) to one pure, testable
function.

### AD-9 — Pure ad-hoc builder with a JS mirror

**Choice**: `tpvmod_build_ad_hoc_opcional(array $input, float $pvpBase): array`
returns `['ok'=>bool,'errors'=>list<string>,'opcional'=>?array]` where the opcional
is `{id:null, descripcion, precio, grupo_id:null, ad_hoc:true}`; `porcentaje`
computes `bround($pvpBase * $pct / 100)` (OD-2). The JS
`tpvmod_build_ad_hoc_opcional()` mirrors it for the actual client-side insertion.
**Alternatives considered**: format only in JS (no PHP contract test possible);
server-side percentage computation (OD-2 chose client-side to avoid a round-trip).
**Rationale**: gives a DB-free, testable source of truth for the ad-hoc shape while
honoring OD-2.

### AD-10 — Ad-hoc lines keep the client's normal cascading discounts (OD-1)

**Choice**: ad-hoc lines receive the client's normal cascading discounts
(`dtopor`…`dtopor4`) on document save, exactly like catalog optional lines. The
entered (`fijo`) or computed (`porcentaje`) price is the **pre-discount base**.
`tpvmod_populate_linea_descuentos()` (S14, `lib/tpvmod_modules.php:376-490`) and the
four document save loops in `controller/tpvmod.php` (S3:1328-1333, :1390-1395) are
**not modified and not bypassed**. The ad-hoc row submits the same numeric fields as
any optional row (`pvp_N`, `cantidad_N`, `iva_N`, `recargo_N`, `irpf_N`) plus the
marker only; there is no ad-hoc-only discount branch anywhere in the save path.
**Alternatives considered**: a true no-discount line (`dtopor = 0`), rejected by
OD-1; a discount bypass flag on the row, which would fork the save loops and the
discount helper contract (owned by `tpvmod-descuentos-cliente`, F14).
**Rationale**: OD-1 (binding). Because the marker only affects obligation
validation (AD-4) and never the discount helper, the ad-hoc line's totals are
computed by the same code path as a catalog line. Verification target: a document
save with a discounted client and an ad-hoc line whose `dtopor*` fields match the
client's and whose total equals the same computation applied to a catalog line
(spec scenario "Discount parity with a catalog line").

### AD-11 — Familia association links the family only, with no propagation (OD-7)

**Choice**: the `familia` target calls
`catalogo_opcional::add_familia_only($codfamilia)`, which delegates to
`catalogo_opcional_familia::add()` (idempotent bool) and creates **exactly one
family relation and zero article relations**. `add_familia()` (which delegates to
`add_with_propagation()` and mass-assigns the family's articles) MUST NOT be called.
The product target calls `catalogo_articulo_opcional::add($referencia, $id, false)`.
**Alternatives considered**: `add_familia()` (propagates to every family article —
OD-7 rejects this); saving unassociated when no family (OD-3 rejects it).
**Rationale**: OD-7 + S8:310-314 + S10:39-88.
**Mock/double strategy for the no-propagation invariant**: the injectable model
factory (see File Plan `tpvmod_opcionales_ajax_persist()`) supplies an anonymous
opcional double that records which of `add_familia_only()` / `add_familia()` was
invoked, a family-relation double that counts `add()` calls, and an
article-relation double that counts `add()` calls. The RED test with target
`familia` asserts: `add_familia_only` called once with the resolved `codfamilia`,
`add_familia` never called, family-relation `add()` count === 1, article-relation
`add()` count === 0. A second test asserts the `producto` target calls the article
relation once and the family relation never.

### AD-12 — User text is escaped end to end (F13, views V5)

**Choice**: every render path escapes user text, server and client:

- JS-built markup (ad-hoc row description, modal list, association prompt) uses
  `tpvmod_escape_html()` and keeps the value in **element content**, never inside a
  single-quoted attribute — `tpvmod_escape_html()` escapes `& < > "` but **not** `'`
  (F13/S4, `tpvmod.js:130-137`).
- Twig outputs `nombre`/`descripcion` through `{{ }}` only; **no `|raw`** is applied
  to user text (`|raw` stays reserved for URLs and `json_encode()` payloads).
- The model defensively applies `no_html()` to `codigo`, `nombre`, `descripcion` in
  `catalogo_opcional::test()` (defense in depth, S8:339-341) — this is the catalog
  model's existing behavior, not a new edit.
- The new form fields are also validated/normalized server-side (AD-8) so escaped
  output never masks malformed input.

**Alternatives considered**: relying on the model's `no_html()` alone (does not
protect the JS modal path, which renders before/without a round-trip); `htmlspecialchars`
in PHP output (irrelevant — this path emits JSON, and the browser render is JS).
**Rationale**: F13 is High; the views delta V5 explicitly forbids `|raw` on user
text and single-quoted attributes. Verification target: JS-grep asserts
`tpvmod_escape_html` wraps the ad-hoc description and no user-text interpolation into
single-quoted attributes; Twig-grep asserts no `|raw` on `nombre`/`descripcion`; a
unit test feeds `<script>alert(1)</script>` through the normalize/ad-hoc builders and
asserts the catalog model's `no_html()` contract on persisted fields.

## File Plan

| File | Action | Introduced symbols / change |
|---|---|---|
| `lib/tpvmod_opcionales.php` | Modify | `tpvmod_opcional_is_ad_hoc_post(array $post, int $n): bool`; `tpvmod_articulo_codfamilia(string $referencia): string`; `tpvmod_build_ad_hoc_opcional(array $input, float $pvpBase): array`; add `codfamilia` to both returns of `tpvmod_opcionales_for_articulo()`; marker gate in `tpvmod_validate_obligatorios_post()`. |
| `lib/tpvmod_opcionales_ajax.php` | Create | `tpvmod_opcionales_ajax_dispatch(fs_controller $ctrl): bool`; `tpvmod_opcionales_ajax_save(fs_controller $ctrl): void`; `tpvmod_opcionales_ajax_emit_json(array $payload): void`; `tpvmod_opcionales_ajax_persist(array $data, string $referencia, string $target, string $codfamilia, ?callable $models = null): array` (orchestrator seam; `$models` factory returns `{opcional, relation, familyRelation, candidates, lista}` for DB-free tests); `tpvmod_opcionales_ajax_opcional_payload(object $opcional): array`; `tpvmod_normalize_opcional_input(array $post): array`; `tpvmod_match_opcional_by_nombre(array $candidates, string $nombre): ?array`; `tpvmod_bump_opcional_codigo(string $codigo, int $attempt): string`; `tpvmod_next_opcional_codigo(string $base, callable $exists, int $maxAttempts = 5): ?string`; `tpvmod_resolve_asociacion_target(array $post, string $codfamilia): array`; `require_once __DIR__.'/tpvmod_opcionales.php'`. Family target uses `add_familia_only()` only (AD-11); parity uses `set_precio_lista()`/`set_porcentaje_lista()` (AD-8). |
| `controller/tpvmod.php` | Modify | `require_once dirname(__DIR__).'/lib/tpvmod_opcionales_ajax.php';` + the AD-2 dispatch guard. |
| `view/partials/modal_opcionales.html.twig` | Create | Shared shell; tabs; `#tpvmod_opcionales_list` (nested); `#tpvmod_opcional_nuevo_form` with `nombre`, `descripcion`, `tipo_precio`, `valor`, `{{ csrf_field() }}`, hidden `guardar_opcional_tpv`. User text rendered via `{{ }}` only, no `|raw` (AD-12). |
| `view/tpvmod2.html.twig` | Modify | Replace inline shell (383-398) with `{% include 'partials/modal_opcionales.html.twig' %}`. |
| `view/tpvmodedita.html.twig` | Modify | Replace inline shell (465-480) with the same include. |
| `view/js/tpvmod.js` | Modify | Extend `tpvmod_add_opcional_linea()` (`ad_hoc` → `data-ad-hoc` + hidden marker); extend `tpvmod_normalize_opcionales_payload()` to carry `codfamilia`; add `tpvmod_build_ad_hoc_opcional()`, `tpvmod_add_opcional_ad_hoc(parentUid)`, `tpvmod_save_opcional_tpv(parentUid)`, `tpvmod_reset_opcional_nuevo_form()`. All user text passes through `tpvmod_escape_html()` as element content, never in single-quoted attributes (AD-12). |
| `tests/TpvmodOpcionalRapidoTest.php` | Create | Pure-helper coverage (see Testing Strategy). |
| `tests/TpvmodOpcionalesTest.php` | Modify | Extend JS-grep contract; update the empty-payload assertion to include `codfamilia`. |
| `tests/TpvmodTwigTemplatesTest.php` | Modify | Add `partials/modal_opcionales.html.twig` to the inventory; assert both screens include it and the partial has tabs + CSRF + no `data-toggle="collapse"`. |

## Data Flow

### "Añadir" (client-only)

```
open modal → GET ?opcionales_articulo=<ref>&pvp=<n>  (CSRF-free read)
      → payload {grupos, sueltos, codfamilia}
fill new-opcional form → click Añadir → tpvmod_add_opcional_ad_hoc(parentUid)
      → ctx = tpvmod_get_parent_line_context_by_uid(parentUid)
      → tpvmod_build_ad_hoc_opcional(input, ctx.pvp)   (OD-2, validates)
      → tpvmod_add_opcional_linea(parentUid, {ad_hoc:true, ...})
      → reorder / renumber / recalcular / refresh warnings   (no POST, no persistence)
```

Ad-hoc row hidden fields: `referencia_N=""`, `idlinea_N=-1`,
`tpvmod_opcional_id_N=""`, `tpvmod_opcional_grupo_id_N=""`,
`tpvmod_parent_ref_N`, `iva_N`, `recargo_N`, `irpf_N`, `desc_N`, `cantidad_N`,
`pvp_N`, `neto_N`, `total_N`, **plus `tpvmod_opcional_ad_hoc_N=1`**.

### "Añadir y guardar opcional" (POST → persist → associate → auto-add)

```
click Añadir y guardar → prompt Producto/Familia (Familia hidden when codfamilia='')
POST tpv_url: guardar_opcional_tpv=1, _csrf_token, referencia,
              nombre, descripcion, tipo_precio, valor, asociacion
   → tpvmod_opcionales_ajax_dispatch → tpvmod_opcionales_ajax_save
       $ctrl->template=false
       !isCsrfValid()                     → {ok:false, errors:['Token CSRF inválido.']}
       normalize input                    → {ok:false, errors} on invalid
       server codfamilia from referencia  (authoritative)
       resolve target (reject familia w/o codfamilia)
       match by nombre → reuse, else create (codigo retry) → save → lista parity → associate
   → {ok:true, opcional:{...}, codfamilia} | {ok:false, errors:[...]}
JS: on ok  → invalidate tpvmod_opcionales_cache[ref|pvp] → refetch → add line → re-render
    on err → show errors, keep form, no line
```

**Success envelope**:
`{"ok":true,"opcional":{"id":12,"codigo":"OPC0012","nombre":"...","descripcion":"...","precio":12.5,"tipo_precio":"fijo","porcentaje":null,"grupo_id":null},"codfamilia":"FAM01"}`
**Error envelope**: `{"ok":false,"errors":["..."]}`
The read endpoint `?opcionales_articulo=<ref>&pvp=<n>` keeps its current shape plus
`codfamilia` and stays CSRF-free.

## Testing Strategy

| Layer | What | Approach |
|---|---|---|
| Unit (pure, DB-free, `lib/`) | ad-hoc build (`fijo` passthrough, `porcentaje` = `bround(pvp*pct/100)`, empty nombre / negative valor, `<script>` sanitization), `tpvmod_opcional_is_ad_hoc_post`, `tpvmod_normalize_opcional_input` (invariants `activo=true`/`id_grupo=null`, `precio` vs `porcentaje` mapping), `tpvmod_match_opcional_by_nombre`, `tpvmod_bump_opcional_codigo` + `tpvmod_next_opcional_codigo` exhaustion, `tpvmod_resolve_asociacion_target`, `tpvmod_articulo_codfamilia` (AD-5; empty ref and unknown article → `''`). | New `tests/TpvmodOpcionalRapidoTest.php`; hand-rolled anonymous doubles / closures (S21 pattern), no DB. |
| Unit (dispatch / emit / payload) | `tpvmod_opcionales_ajax_dispatch()` returns `true` on `guardar_opcional_tpv` and `false` otherwise; `tpvmod_opcionales_ajax_emit_json(array $payload)` writes the encoded JSON with the `application/json` header; `tpvmod_opcionales_ajax_opcional_payload()` returns `{id, codigo, nombre, descripcion, precio, tipo_precio, porcentaje, grupo_id:null}` from a model double. | Same test file; output captured with `ob_start()`/`ob_get_clean()` (introduced here — the emit helper has no in-repo emission test to cite). |
| Unit (save orchestration + parity, doubles) | `tpvmod_opcionales_ajax_persist()` sequence: normalize → match-by-nombre (reuse branch does **not** call `save()`; create branch does) → `save()` → `set_precio_lista()` for `fijo` / `set_porcentaje_lista()` for `porcentaje` → associate. Family target asserts AD-11's no-propagation invariant (`add_familia_only` once; `add_familia` never; 1 family relation; 0 article relations). Retry exhaustion and association failure return `{ok:false, errors}`. | Injected `$models` factory returns doubles (AD-11 strategy); no live DB. |
| Unit (CSRF gate) | `tpvmod_opcionales_ajax_save()` rejects when `isCsrfValid()` is false and emits `{ok:false, errors:['Token CSRF inválido.']}` without persisting. | Anonymous `fs_controller` subclass with empty constructor, driven to `isCsrfValid() === false` (pattern: `tests/Security/FsControllerCsrfBlockingTest.php:58-85`, which builds the subclass and asserts `isCsrfValid()` is false). That precedent does **not** capture output; `ob_start()`/`ob_get_clean()` is added here to assert the envelope. |
| Contract (JS/Twig grep) | Wiring cannot regress: `tpvmod_add_opcional_ad_hoc`, `tpvmod_save_opcional_tpv`, `guardar_opcional_tpv`, `tpvmod_opcional_ad_hoc_`, `data-ad-hoc`, `codfamilia`, `tpvmod_escape_html` around the ad-hoc description; partial has tabs, `{{ csrf_field() }}`, sibling `#tpvmod_opcional_nuevo_form`, no `data-toggle="collapse"`, no `|raw` on `nombre`/`descripcion`; both screens include the partial. | Extend `TpvmodOpcionalesTest` and `TpvmodTwigTemplatesTest`. |
| Smoke (manual) | End-to-end on `tpvmod2` and `tpvmodedita`: ad-hoc insert, save→product, save→family, reuse, obligatorios inert, **discount parity of an ad-hoc line vs a catalog line for a discounted client (OD-1)**. | `config.yaml` smoke checklist. |

Every new `lib/` symbol above is inside the plugin PHPUnit `<source><include> lib/`
scope and is therefore expected to be exercised by the new test file.

Runner: `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.

## Error Handling

| Case | Behaviour |
|---|---|
| Missing/empty `nombre`, negative or non-numeric `valor` | JS inline error; server `{ok:false, errors:[...]}`; nothing persisted. |
| Invalid/absent CSRF token | `{ok:false, errors:['Token CSRF inválido.']}`; nothing persisted. |
| Codigo retry exhausted | `{ok:false, errors:['No se pudo generar un código único...']}`; no opcional, no association. |
| `familia` target with empty server `codfamilia` | Rejected before persist: `{ok:false, errors:['El producto no tiene familia asignada.']}`. |
| Model `save()` fails | Surface `get_errors()`, else a generic message; nothing persisted. |
| Opcional saved but association fails | Return `{ok:false, errors:['El opcional se guardó, pero no se pudo asociar.']}` (partial failure, documented). No auto-add; the UI reports it. Rollback is deliberately rejected: it is destructive and ambiguous under OD-8 reuse. |
| Lista parity fails | Same partial-failure semantics; the opcional remains and the error is reported. |
| JS network failure | Generic error, form kept, no line inserted. |

## Edge Cases

- **Percentage frozen at insert** (spec): the JS computes the price once; `pvp_N`
  is a static value and `recalcular()` recomputes totals from it, never from the
  parent PVP.
- **Parent PVP edit**: does not rewrite existing optional rows, catalog or ad-hoc —
  consistent with current catalog-line behavior.
- **Reload limitation (F4)**: the marker is not persisted on document lines; after
  save/re-open, `tpvmodedita` may reclassify a description-matching line. Inertness
  is guaranteed only for the marker-carrying request; fixing reload needs document
schema work outside this change.
- **Description match is inert** while the marker is present: no server resolution,
  no obligation satisfaction; the client already ignores empty-id rows.
- **Both screens**: shared partial + shared JS path; both already load `tpvmod.js`
  and define `tpv_url`.
- **No `codfamilia`**: Familia hidden/disabled, Producto forced, direct `familia`
  requests rejected server-side.
- **Discount parity (OD-1)**: an ad-hoc line's `dtopor`…`dtopor4` are filled by
  `tpvmod_populate_linea_descuentos()` on save; the entered/computed price stays the
  pre-discount base and no ad-hoc-only discount branch exists (AD-10).
- **No propagation (OD-7)**: the `familia` target creates exactly one family
  relation and zero article relations; `add_familia()` is never invoked (AD-11).
- **Escaping (F13)**: `tpvmod_escape_html()` does not escape `'`, so user text must
  remain element content and must never be interpolated into a single-quoted
  attribute (AD-12).

## Requirement → Design Traceability

Behaviour delta (`specs/tpv-opcionales/spec.md`):

| Requirement | Design | Verification |
|---|---|---|
| Dual modal affordance (OD-10) | AD-3 | Twig-grep: both screens include the partial; tabs + list + form present. |
| Ad-hoc line is session-only | AD-9 | Unit (build) + smoke: no POST on **Añadir**; no catalog writes. |
| Ad-hoc price semantics by tipo (OD-2) | AD-9 | Unit: `fijo` passthrough; `porcentaje = bround(pvp*pct/100)`; invalid input rejected. |
| Ad-hoc lines keep client cascading discounts (OD-1) | **AD-10** | Unit (normalize shape) + smoke: `dtopor*` parity and coherent totals (no helper edit). |
| Ad-hoc lines are inert for obligatorios/groups (OD-6, F2) | AD-4 | Unit: `tpvmod_opcional_is_ad_hoc_post`; JS-grep: marker emitted; smoke: description match does not satisfy. |
| Save-and-associate persists ungrouped opcional + auto-add (OD-4/5/9) | AD-7, AD-8, Data Flow | Unit: retry/exhaustion, normalize invariants; orchestration test with doubles. |
| Association target resolution (OD-3, F8) | AD-5 | Unit: `tpvmod_articulo_codfamilia`, `tpvmod_resolve_asociacion_target`; smoke: server override. |
| Familia association links the family only (OD-7) | **AD-11** | Unit with doubles: `add_familia_only` once, `add_familia` never, 1 family / 0 article relations. |
| Reuse existing opcional by normalized nombre (OD-8, F5) | AD-6 | Unit: match by normalized nombre; orchestration reuse-vs-create branch. |
| Write endpoint is CSRF-gated | AD-2, Error Handling | Unit: `tpvmod_opcionales_ajax_save` with `isCsrfValid() === false`; smoke. |
| User text is escaped end to end (F13) | **AD-12** | JS/Twig-grep + unit: `no_html()` on persisted fields. |
| Catalog parity on the default lista (F7) | AD-8 | Orchestration test with doubles: parity call per tipo; smoke. |

Views delta (`specs/views/spec.md`): CSRF-via-Twig-only → AD-3; shared partial
(OD-10/F9) → AD-3; sibling form container (F1) → AD-3; BS3 tabs, no collapse
(F10) → AD-3; Twig escaping, no `|raw`, no single-quoted user text (F13) → **AD-12**.

## Threat Matrix

The change adds an authenticated, same-origin `$_POST` action (HTTP routing
boundary). No shell, subprocess, VCS/PR automation, executable-file classification,
or process-integration boundary is introduced.

| Boundary | Applicability | Expected safe / failure behaviour | RED test |
|---|---|---|---|
| HTTP routing (new POST action) | Applicable | Valid CSRF → JSON success; invalid/absent → `{ok:false}` and no writes; handler returns (no `exit`). | CSRF-gate unit test + `guardar_opcional_tpv` JS-grep contract. |
| XSS / render boundary (F13, High) | Applicable | User text (`nombre`, `descripcion`) escaped on every render path: `tpvmod_escape_html()` as element content (never in single-quoted attributes), Twig `{{ }}` with the raw filter forbidden; malicious input cannot produce executable markup. | JS-grep escape assertion + Twig-grep (no raw filter on user text) + unit `<script>` input through the normalize/ad-hoc builders. |
| Shell / subprocess | N/A — none introduced. | — | — |
| VCS / PR automation | N/A — none introduced. | — | — |
| Executable-file classification | N/A — none introduced. | — | — |
| Process integration | N/A — none introduced. | — | — |

## Migration / Rollout

No migration. No schema change; catalog models are consumed. Rollout is a normal
plugin release; no feature flag. The added `codfamilia` payload key is backward
compatible (readers ignore unknown keys).

## Open Questions

None blocking. Partial-failure semantics (AD-8 association/parity failure) are
resolved here; the F4 reload limitation is explicitly out of scope per the spec.

## Size Plan

| File | Estimated changed lines |
|---|---|
| `lib/tpvmod_opcionales.php` | ~45 |
| `lib/tpvmod_opcionales_ajax.php` | ~280 |
| `controller/tpvmod.php` | ~6 |
| `view/partials/modal_opcionales.html.twig` | ~70 |
| `view/tpvmod2.html.twig` + `view/tpvmodedita.html.twig` | ~8 |
| `view/js/tpvmod.js` | ~230 |
| `tests/TpvmodOpcionalRapidoTest.php` | ~260 |
| `tests/TpvmodOpcionalesTest.php` + `tests/TpvmodTwigTemplatesTest.php` | ~85 |
| **Total** | **~985** |

The corrective pass added the `tpvmod_opcionales_ajax_persist()` seam (testability of
the save/parity/association sequence) and its double-based tests, the AD-11
no-propagation test, the dispatch/emit/payload tests, and the AD-1/AD-7/AD-12
verification targets, moving the estimate from ~820 to ~985. This is now clearly
above the **800-line single-PR budget** (proposal forecast 750–1000, at risk). The
additions are coverage, not surface: they harden invariants the validator flagged.
Reductions available without losing behavior: keep the new lib orchestration in one
file and reuse `tpvmod_cliente_error_response()`-style envelopes; keep
`TpvmodOpcionalRapidoTest` focused on the pure helpers and orchestration doubles
rather than duplicating grep contracts. The apply gate should plan a chained PR:
PR 1 = `lib/` + `controller/` + tests (backend, reviewable against the spec
scenarios), PR 2 = JS + templates (view wiring). Exceeding 800 without an explicit
chained-PR decision is not permitted at the apply gate.

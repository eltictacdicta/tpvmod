# Capability: tpv-opcionales

Optional extras in the TPV: shared modal, quick-create (ad-hoc and catalog),
inertness for obligatorios, and catalog parity.

> **Established by change** `tpvmod-opcional-rapido` (2026-09-19).
> Plugin-local; `plugins/catalogo_core/` is consumed read-only.

## Requirements

### Requirement: Dual modal affordance (OD-10)

The opcionales modal MUST keep the existing catalog selection unchanged AND
expose an inline quick-create affordance, on both `tpvmod2` and `tpvmodedita`.

#### Scenario: Existing selection is preserved

- GIVEN the agent opens "Añadir opcional" for a product with catalog opcionales
- WHEN the modal renders
- THEN the catalog list is shown
- AND a quick-create form for a new opcional is available

#### Scenario: Picking an existing opcional is unchanged

- GIVEN the catalog list is rendered
- WHEN the agent picks an existing catalog opcional
- THEN a line carrying that catalog opcional id is inserted (unchanged behavior)

### Requirement: Ad-hoc line is session-only

**Añadir** MUST insert an optional line for the current sale only and MUST NOT
persist anything to the catalog.

#### Scenario: Añadir persists nothing

- GIVEN a valid composed opcional (nombre, tipo, valor)
- WHEN the agent chooses **Añadir**
- THEN a new optional row appears under the parent product line
- AND no `catalogo_opcional` row and no `catalogo_articulo_opcional` relation is created

#### Scenario: Saved without catalog linkage

- GIVEN an ad-hoc line exists in the current sale
- WHEN the document is saved
- THEN it is persisted as a document line only
- AND no catalog relation is created for it

### Requirement: Ad-hoc price semantics by tipo (OD-2)

For `tipo = fijo` the entered `valor` MUST be the ad-hoc unit price and MUST be
treated as the pre-discount base. For `tipo = porcentaje` the unit price MUST be
computed client-side at insert time as
`bround(parentLinePvp * pct / 100)`, using the parent product line PVP at that
moment. The price MUST NOT be recomputed afterwards.

#### Scenario: Fixed price passthrough

- GIVEN `tipo = fijo` and `valor = 12.50`
- WHEN **Añadir** is chosen
- THEN the ad-hoc unit price is `12.50`

#### Scenario: Percentage price uses the parent PVP

- GIVEN `tipo = porcentaje`, `valor = 10`, parent line PVP `25.00`
- WHEN **Añadir** is chosen
- THEN the ad-hoc unit price is `bround(25.00 * 10 / 100)`

#### Scenario: Percentage base is frozen at insert time

- GIVEN an ad-hoc percentage line was inserted from a parent PVP
- WHEN the agent later edits the parent product PVP
- THEN the ad-hoc unit price is NOT recalculated

#### Scenario: Invalid input is rejected

- GIVEN an empty `nombre` or a negative `valor`
- WHEN **Añadir** is chosen
- THEN no line is inserted and a validation error is reported

### Requirement: Ad-hoc lines keep client cascading discounts (OD-1)

Ad-hoc lines MUST receive the client's normal cascading discounts
(`dtopor`…`dtopor4`) on document save, exactly like catalog optional lines. The
entered/computed price MUST be the pre-discount base.
`tpvmod_populate_linea_descuentos()` MUST NOT be modified or bypassed.

#### Scenario: Discount parity with a catalog line

- GIVEN a client with cascading discounts and an ad-hoc line with a base and quantity
- WHEN the document is saved
- THEN the ad-hoc discount fields match the client's discounts
- AND its total equals the same computation applied to any other line

### Requirement: Ad-hoc lines are inert for obligatorios and groups (OD-6, F2)

Ad-hoc lines MUST NOT satisfy any `obligatorio` nor count in any exclusive
group, client and server, even when their description matches a catalog
opcional. Each ad-hoc row MUST submit the hidden marker
`tpvmod_opcional_ad_hoc_N=1`; when present, server obligation validation MUST
skip the description-resolution branch and MUST NOT count the line.

#### Scenario: Description match does not satisfy an obligation

- GIVEN a parent product with an unmet obligatorio
- AND an ad-hoc line whose description matches the required catalog opcional
- WHEN the save is attempted
- THEN client and server both report the obligation as unmet and the save is blocked

#### Scenario: No participation in an exclusive group

- GIVEN an exclusive group with a catalog opcional selected
- WHEN an ad-hoc line matches another opcional of that group
- THEN the ad-hoc row's group id is empty and the group selection is unchanged

#### Scenario: Marker drives the server gate

- GIVEN a submitted line with `tpvmod_opcional_ad_hoc_N=1` and an empty `tpvmod_opcional_id_N`
- WHEN `tpvmod_validate_obligatorios_post()` processes it
- THEN no description-based catalog resolution occurs and the line is skipped

#### Scenario: Inertness is scoped to the marker-carrying request (F4)

- GIVEN an ad-hoc line saved with the marker in the current request
- WHEN the document is re-opened on `tpvmodedita`
- THEN the rehydrated line carries no marker
- AND if its description matches a catalog opcional it MAY be reclassified
- (Documented limitation: the ad-hoc intent does not persist across reload.)

### Requirement: Save-and-associate persists an ungrouped opcional and auto-adds the line (OD-4, OD-5, OD-9)

**Añadir y guardar opcional** MUST persist a new catalog opcional with
`activo = true` and `id_grupo = null` (always ungrouped) and codigo
auto-generated as `OPC####`, associate it with the chosen target (product or
family), then auto-add the resulting line to the current sale. No `codigo` field
MUST be exposed.

#### Scenario: Successful save, association, and auto-add

- GIVEN a valid composed opcional
- WHEN **Añadir y guardar opcional** succeeds
- THEN a `catalogo_opcional` row exists with the entered `nombre`/`descripcion`, `activo = true`, `id_grupo = null`, codigo `OPC####`
- AND it is associated with the chosen target
- AND an optional line using it is inserted in the current sale

#### Scenario: Codigo collision is retried

- GIVEN the first generated codigo already exists
- WHEN persistence is attempted
- THEN a new codigo is generated and the save is retried

#### Scenario: Retry exhaustion is a structured failure

- GIVEN every generated codigo collides within the bounded attempts
- WHEN persistence is attempted
- THEN the response is `{ok:false, errors:[...]}`
- AND no opcional and no association is persisted

### Requirement: Association target resolution (OD-3, F8)

The association target MUST be `producto` or `familia`. The `familia` target
MUST only be accepted when the parent product has a non-empty `codfamilia`. The
server MUST re-resolve `articulo->codfamilia` from the submitted `referencia` at
save time and treat it as authoritative; the client availability flag is advisory
UX only.

#### Scenario: Family is available for a family product

- GIVEN the parent product has a non-empty `codfamilia`
- WHEN the agent chooses **Familia**
- THEN the opcional is associated with that family

#### Scenario: No family hides the option and rejects direct requests

- GIVEN the parent product has no `codfamilia`
- WHEN the association prompt renders
- THEN **Familia** is hidden or disabled and **Producto** is forced
- AND a direct request with target `familia` is rejected with `{ok:false, errors:[...]}`

#### Scenario: Server codfamilia overrides the client flag

- GIVEN the client claims a family exists
- WHEN the server re-resolves `articulo->codfamilia` and finds it empty
- THEN the family association is rejected regardless of the client flag

### Requirement: Familia association links the family only (OD-7)

Family association MUST link the opcional to the family only and MUST NOT
propagate to the family's articles.

#### Scenario: No propagation

- GIVEN a family with N articles and no prior relation
- WHEN the opcional is associated with the family
- THEN exactly one family relation is created
- AND no article relation is created for any of the N articles

### Requirement: Reuse existing opcional by normalized nombre (OD-8, F5)

Before creating a new opcional, the flow MUST look up an existing opcional by
exact normalized `nombre`; if found it MUST reuse it and associate it instead of
duplicating. Since the catalog model has no exact-name lookup, the match MUST be
implemented over the plugin's candidate results with normalized equality.

#### Scenario: Existing name is reused

- GIVEN an existing opcional whose normalized `nombre` equals the entered `nombre`
- WHEN **Añadir y guardar opcional** runs
- THEN no new `catalogo_opcional` row is created
- AND the existing opcional is associated and its id returned

#### Scenario: Different name creates a new opcional

- GIVEN the entered `nombre` differs from every existing opcional after normalization
- WHEN the flow runs
- THEN a new opcional is created

### Requirement: Write endpoint is CSRF-gated (read-only stays CSRF-free)

The `guardar_opcional_tpv` write endpoint MUST require a valid CSRF token and
respond `{ok:false, errors:[...]}` without persisting when it is invalid or
absent. The read-only `opcionales_articulo` endpoint MUST remain CSRF-free. The
write handler MUST follow the return-to-dispatcher contract (no `exit`) and MUST
sit beside, never replace, `tpvmod_cliente_ajax_dispatch()`.

#### Scenario: Valid write returns a success envelope

- GIVEN a POST to `guardar_opcional_tpv` with a valid token and payload
- WHEN the endpoint runs
- THEN the response is JSON `{ok:true, opcional:{...}}`

#### Scenario: Invalid token persists nothing

- GIVEN a POST to `guardar_opcional_tpv` without a valid token
- WHEN the endpoint runs
- THEN the response is `{ok:false, errors:['Token CSRF inválido.']}`
- AND nothing is persisted

#### Scenario: Read endpoint stays CSRF-free

- GIVEN a request to `?opcionales_articulo=<ref>&pvp=<n>`
- WHEN the endpoint runs
- THEN it responds without requiring a CSRF token (unchanged behavior)

### Requirement: User text is escaped end to end (F13)

User text (`nombre`, `descripcion`) MUST be escaped on every render path:
`tpvmod_escape_html()` in JS-built markup, kept in element content and never in
single-quoted attributes, and Twig `{{ }}` with no `|raw`. The model MUST
additionally apply `no_html()` on persisted fields.

#### Scenario: JS render escapes malicious text

- GIVEN a `nombre` containing `<script>alert(1)</script>`
- WHEN the modal or line is rendered
- THEN the markup contains the escaped form and no executable script
- AND the value is not placed inside a single-quoted attribute

#### Scenario: Model sanitizes persisted text

- GIVEN a `nombre` containing HTML markup
- WHEN the opcional is persisted
- THEN the stored `nombre` has its HTML removed or escaped by `no_html()`

### Requirement: Catalog parity on the default lista (F7)

On successful creation the flow MUST mirror the canonical catalog sequence: after
`save()`, write the default lista price via `set_precio_lista()` (fixed) or
`set_porcentaje_lista()` (percentage), using the plugin default lista. Because
`catalogo_opcional.php` does not load the price model, the flow MUST explicitly
`require_once` `catalogo_opcional_precio.php` before the parity step.

#### Scenario: Fixed opcional writes a fixed lista row

- GIVEN a fixed-price opcional is created
- WHEN the parity step runs
- THEN the default-lista row has `precio` set and `porcentaje = null`

#### Scenario: Percentage opcional writes a percentage lista row

- GIVEN a percentage opcional is created
- WHEN the parity step runs
- THEN the default-lista row has `precio = 0` and `porcentaje` set


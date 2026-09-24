# Delta: `tpv-cliente-modales` — tpvmod-listados-htmx

> Delta to the canonical source of truth
> `plugins/tpvmod/openspec/specs/tpv-cliente-modales/spec.md`.
> Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/`.
> Plugin-local SDD: the core `openspec/` is intentionally NOT touched.
>
> This delta narrows the scope of the client-search modal: it stays on the
> screens that keep the client selector (`tpvmod2`, `tpvmodedita`) and is removed
> from the four document listings, whose client filter is the `&codcliente=` URL
> parameter rendered as read-only text. The row/filter rendering contract of the
> listings is owned by the `listados-htmx` capability and is only referenced here.
> Traceability: `decisions-pending.md` D5; `proposal.md` §Capabilities.

## MODIFIED Requirements

### Requirement: Modal de búsqueda de clientes

The pencil button opens `#modal_clientes` with AJAX HTML search (name, CIF, phone)
on the TPV screens that keep the client selector (`tpvmod2`, `tpvmodedita`).
Selecting a row applies the same behavior as the legacy autocomplete. The four
document listings (`tpvmod_presupuestos`, `tpvmod_facturas`, `tpvmod_albaranes`,
`tpvmod_pedidos`) MUST NOT include the client picker: no `ac_cliente` field, no
`tpvmod-b-buscar-cliente` button, no `partials/modal_clientes.html.twig` include
and no `tpvmod-cliente.js` load. The modal partial and its JavaScript MUST NOT be
deleted: they remain in use by the screens that keep the picker.

#### Scenario: The modal opens on the screens that keep the picker

- GIVEN the agent is on `tpvmod2` or `tpvmodedita`
- WHEN the agent presses the pencil button next to the client field
- THEN `#modal_clientes` opens with the focus on the search field
- AND typing and searching shows rows with name, CIF/NIF and phone

#### Scenario: Selecting a row applies the client

- GIVEN there are results in the client modal
- WHEN the agent presses a row
- THEN the active TPV client is updated
- AND `usar_cliente()` is invoked to recalculate discounts and VAT
- AND the modal closes

#### Scenario: The modal is not part of the four listings

- GIVEN the four listing templates
- WHEN each is parsed
- THEN none references `ac_cliente`, `tpvmod-b-buscar-cliente`,
  `partials/modal_clientes.html.twig` or `tpvmod-cliente.js`

#### Scenario: The modal survives on the screens that keep it

- GIVEN `tpvmod2.html.twig`, `tpvmodedita.html.twig` and
  `view/partials/modal_clientes.html.twig`
- WHEN they are inspected
- THEN the include and the `tpvmod-cliente.js` load are still present
- AND `view/js/tpvmod-cliente.js` is not deleted

## ADDED Requirements

### Requirement: Filtro profundo por cliente en los listados (sin modal)

The `&codcliente=` URL filter MUST remain functional on the four listings. The
active client MUST render as read-only text in the filter area, the `[+]` row
links MUST keep filtering by that customer's `codcliente`, and a control MUST
allow clearing the filter without opening a modal. No other client-selection
mechanism MUST be introduced in the listings. The detailed row and filter
rendering contract is specified by the `listados-htmx` capability (LHT-08).

#### Scenario: Deep link keeps filtering without the picker

- GIVEN a listing URL carrying `&codcliente=CLI001`
- WHEN the page renders
- THEN the results are limited to that customer's documents
- AND no client picker or modal is rendered

#### Scenario: Active client is read-only

- GIVEN the listing is filtered by `codcliente`
- WHEN the filter area renders
- THEN the active customer is shown as read-only text
- AND it is not an editable client input

#### Scenario: The client filter is reversible

- GIVEN a listing filtered by `&codcliente=CLI001`
- WHEN the agent activates the clear control
- THEN the listing reloads without `codcliente`
- AND the unfiltered results are shown

# Delta Spec: `views` (tpvmod plugin) — tpvmod-opcional-rapido

> **Source of truth** (post-archive): `plugins/tpvmod/openspec/specs/views/spec.md`
>
> Delta to the `views` capability. Adds the shared opcionales modal partial and
> the quick-create form shell used by `tpv-opcionales`.
> Traceability: `OD-n` = confirmed decision in `decisions-pending.md` §RESOLVED;
> `F-n` = finding in `research.md` §6.

## MODIFIED Requirements

### Requirement: CSRF rendered via Twig function only

Every Twig template with `<form method="post">` MUST include
`{{ csrf_field() }}` inside the form. No template MUST contain
`{$fsc->csrf_field}` (RainTPL) or `{{ fsc.csrf_field|raw }}`. This includes the
quick-create form inside the shared opcionales modal partial.
(Previously: applied to every POST form under `view/`; now explicitly names the
shared opcionales modal partial and its quick-create form.)

#### Scenario: No legacy CSRF workaround anywhere in view/

- GIVEN every `.twig` file under `view/`
- WHEN grepped for `fsc.csrf_field` or `csrf_field|raw` or `{$fsc->csrf_field`
- THEN no matches

#### Scenario: Every POST form contains csrf_field

- GIVEN a Twig template with `<form method="post"`
- WHEN the file is parsed
- THEN `{{ csrf_field() }}` appears before the form's `</form>`

#### Scenario: Quick-create form carries the token

- GIVEN `view/partials/modal_opcionales.html.twig`
- WHEN the quick-create form is parsed
- THEN `{{ csrf_field() }}` appears inside its `<form method="post">`

## ADDED Requirements

### Requirement: Shared opcionales modal partial (OD-10, F9)

The `#modal_opcionales` shell MUST live in a single shared partial
(`view/partials/modal_opcionales.html.twig`) included by both
`tpvmod2.html.twig` and `tpvmodedita.html.twig`, mirroring the existing
`partials/modal_clientes.html.twig` precedent. Both screens MUST render the same
modal id and structure so the flow cannot drift.

#### Scenario: Both screens include the partial

- GIVEN `view/tpvmod2.html.twig` and `view/tpvmodedita.html.twig`
- WHEN each is parsed
- THEN both include `partials/modal_opcionales.html.twig`
- AND neither redefines the `#modal_opcionales` shell inline

#### Scenario: Flow is available on both screens

- GIVEN the agent is on `tpvmod2` (new ticket) or `tpvmodedita` (document edit)
- WHEN the agent opens the "Añadir opcional" modal
- THEN the same existing-selection list and quick-create form are available on both

### Requirement: Quick-create form is a sibling of the render target (F1)

Inside the modal, `#tpvmod_opcionales_list` MUST remain the render target whose
inner HTML the list renderer replaces. The quick-create form MUST live in a
sibling container (`#tpvmod_opcional_nuevo_form`), never inside
`#tpvmod_opcionales_list`, so a re-render of the list cannot destroy the form.

#### Scenario: Form survives a list re-render

- GIVEN the agent typed data into the quick-create form
- WHEN the list renderer replaces the inner HTML of `#tpvmod_opcionales_list`
- THEN the form container and its values are still present in the DOM

#### Scenario: Renderer targets only the list

- GIVEN the shared modal partial is rendered
- WHEN the list renderer runs
- THEN it replaces only `#tpvmod_opcionales_list`
- AND it does not remove `#tpvmod_opcional_nuevo_form`

### Requirement: Quick-create form uses the proven BS3 tab pattern (F10)

The modal MUST expose the existing catalog selection and the quick-create form
using the Bootstrap 3 tab pattern proven in-repo
(`nav nav-tabs` + `data-toggle="tab"` + `.tab-pane`), as used by
`view/ajax/tpv_cliente_form.html.twig`. The modal MUST NOT introduce
`data-toggle="collapse"`, which has no in-repo precedent.

#### Scenario: Tabs render for existing and new opcionales

- GIVEN the shared modal partial
- WHEN it renders
- THEN it contains a tab for existing opcionales and a tab for a new opcional
- AND no `data-toggle="collapse"` appears in the partial

#### Scenario: Form fields are present

- GIVEN the quick-create tab is active
- WHEN the form renders
- THEN it contains `nombre` (required), `descripcion`, `tipo_precio` (`fijo`/`porcentaje`), and `valor` fields
- AND it offers **Añadir** and **Añadir y guardar opcional** actions

### Requirement: Twig escaping conventions for user text (F13)

User-provided text (`nombre`, `descripcion`) MUST be rendered through Twig
`{{ }}` and MUST NOT use the `|raw` filter. User text MUST NOT be placed in
single-quoted HTML attributes.

#### Scenario: No raw output of user text

- GIVEN the shared modal partial and both screens
- WHEN grepped for `|raw`
- THEN no `|raw` is applied to `nombre` or `descripcion`

#### Scenario: No user text in single-quoted attributes

- GIVEN the shared modal partial is parsed
- WHEN it emits a user-provided value
- THEN the value is placed as escaped element content
- AND not inside a single-quoted attribute

## Cross-references

- Proposal: `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/proposal.md`
- Behaviour delta: `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/specs/tpv-opcionales/spec.md`
- Related (unchanged): `plugins/tpvmod/openspec/specs/tpv-flow/spec.md`,
  `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md`

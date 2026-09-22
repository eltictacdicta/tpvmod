# Delta: `tpvmod-config` — empresa-sedes-por-documento

> Delta to the `tpvmod-config` capability. Adds the sede → document-type
> mapping section to the admin settings page. The `tpvmod_terminal_mode`
> behavior is unchanged, so this delta is strictly `ADDED Requirements`; no
> existing requirement block is modified.
> Traceability: `exploration.md` §5, §7; `proposal.md` §Capabilities.

## ADDED Requirements

### Requirement: Sede mapping section on the settings page

The `tpvmod_settings` page MUST render a mapping section with four selectors —
`presupuesto`, `albaran`, `pedido`, `factura` — each offering the existing
sedes plus an explicit "no override / base company" option. Submitting the
form MUST persist each selection through
`empresa_sede::setMappingFor(string $tipo, ?string $codsede): bool`. The
page MUST be administrator-only, declared by the class-level `#[AdminOnly]`
attribute on the controller and resolved by `fs_page::is_admin_only_class()`
through the attribute name string (propagated to `fs_pages.admin_only` and
enforced by `fs_controller::isAccessAllowed()`). The `folder` constructor
argument and the obsolete 4th `$admin` argument MUST NOT be relied on for that
gate. The mutation MUST be CSRF-gated, and every rendered label MUST be
escaped.

#### Scenario: Round-trip read and write

- GIVEN an admin opens `index.php?page=tpvmod_settings` with one sede `S1`
- WHEN the admin selects `S1` for `factura` and submits with a valid CSRF token
- THEN `empresa_sede_factura` stores `S1`
- AND a subsequent GET shows `S1` selected for `factura`
- AND a success message is shown

#### Scenario: Invalid codsede is rejected

- GIVEN a submitted `codsede` that does not resolve to a row
- WHEN the controller validates the input
- THEN no mapping key is written
- AND an error message is shown

#### Scenario: Empty selection clears the override

- GIVEN `factura` is currently mapped to a sede
- WHEN the admin selects the "no override / base company" option and submits
- THEN the `empresa_sede_factura` key is cleared
- AND the base company is used for that document type

#### Scenario: CSRF and admin gate

- GIVEN a POST without a valid CSRF token, or from a non-admin user
- WHEN the mapping form is submitted
- THEN no mapping key is written
- AND an error message is shown

#### Scenario: Escaped labels

- GIVEN a sede whose `descripcion` contains markup
- WHEN the selectors render
- THEN the markup is escaped, not executed

### Requirement: Mapping section is independent of the facturacion_base gate

The mapping section MUST NOT inherit the `tpvmod_terminal_settings_available()`
gate, which depends on `facturacion_base` and is unrelated to sedes. The
section MUST render and persist whenever the page is reachable, regardless of
whether `facturacion_base` is active.

#### Scenario: Mapping works with facturacion_base inactive

- GIVEN `facturacion_base` is not in `$GLOBALS['plugins']`
- WHEN an admin opens `index.php?page=tpvmod_settings`
- THEN the four mapping selectors are rendered
- AND the admin can save and read back a mapping
- AND the terminal-mode gate continues to hide only its own controls

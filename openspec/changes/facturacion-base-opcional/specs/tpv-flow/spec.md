# Delta: tpv-flow — facturacion_base opcional

## ADDED Requirements

### Requirement: Direct sales mode without facturacion_base

When `facturacion_base` is not active, the TPV MUST allow an agent with a
linked `agente` to use the sales UI (`tpvmod2`) without opening a `caja`
and without selecting a `terminal_caja`.

#### Scenario: Agent lands on sales UI directly

- GIVEN `facturacion_base` is not active
- AND the agent has no open `caja`
- WHEN the agent opens `index.php?page=tpvmod`
- THEN the terminal pick screen is not shown
- AND the `d_inicial` form is not shown
- AND `tpvmod2` is rendered (subject to cliente default existing)

#### Scenario: No caja models instantiated

- GIVEN `facturacion_base` is not active
- WHEN `private_core()` runs
- THEN `new caja()` and `new terminal_caja()` are never executed

### Requirement: Caja module preserves legacy behaviour when facturacion_base active

When `facturacion_base` is active, the existing caja/terminal flow (including
the archived `terminal-opcional` behaviour) MUST be preserved unchanged.

#### Scenario: With facturacion_base, caja still required by default

- GIVEN `facturacion_base` is active
- AND effective mode is `with_terminal`
- WHEN the agent opens the TPV without an open caja
- THEN the terminal pick screen is shown (unchanged)

### Requirement: Print links gated on facturacion_base

Success messages and edit views MUST only offer `ventas_imprimir` links when
`facturacion_base` is active. When inactive, documents save successfully but
no print link to `ventas_imprimir` is appended.

#### Scenario: No ventas_imprimir link without facturacion_base

- GIVEN `facturacion_base` is not active
- WHEN an albarán is saved from the TPV
- THEN the success message does not contain `page=ventas_imprimir`

## MODIFIED Requirements

### Requirement: Mode read once per request

The TPV controller MUST read `tpvmod_terminal_mode` exactly once per
request, before branching on terminal pick vs. no-terminal flow, using
`tpvmod_terminal_mode_effective()` so that the effective mode is
`with_terminal` when `facturacion_base` is not active.

#### Scenario: Effective mode forced when caja module off

- GIVEN stored mode is `without_terminal` and `facturacion_base` is inactive
- WHEN the controller resolves mode at the start of `private_core()`
- THEN `$this->terminal_mode` is `with_terminal`
- AND caja/terminal branches are skipped entirely

### Requirement: abrir_caja and cerrar_caja tolerate no-terminal cajas

In all no-terminal paths **within the caja module** (`facturacion_base`
active), `abrir_caja()` and `cerrar_caja()` MUST tolerate a missing
`$this->terminal`. When `facturacion_base` is inactive, `abrir_caja()` and
`cerrar_caja()` MUST NOT be reachable from the UI and MUST NOT run.

#### Scenario: cerrar caja button hidden without caja module

- GIVEN `facturacion_base` is not active
- WHEN `tpvmod2` renders
- THEN the "Cerrar caja" control is not shown

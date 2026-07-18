# Capability: tpv-flow

> Plugin-local capability. Source of truth lives at
> `plugins/tpvmod/openspec/specs/tpv-flow/spec.md`. The core
> FSFramework `openspec/` does not track this capability.

## Purpose

Define the runtime flow that turns an agent visit to the TPV page
into an open or in-progress `caja`, including how terminal
selection is enforced by default and how the administrator can opt
out of it via the `tpvmod-config` setting.

## Requirements

### Requirement: Mode read once per request

The TPV controller (`plugins/tpvmod/controller/tpvmod.php`, around
lines 230-372) MUST read `tpvmod_terminal_mode` exactly once per
request, before branching on terminal pick vs. no-terminal flow.
The read MUST use `tpvmod_terminal_mode_effective()` so that the
effective mode is `with_terminal` when `facturacion_base` is not active.

#### Scenario: Read happens before the terminal branches

- GIVEN any agent visits the TPV page
- WHEN `private_core()` runs
- THEN the mode is resolved before the existing `$_POST['terminal']` and `$_GET['terminal']` branches

#### Scenario: Malformed value falls back to with_terminal

- GIVEN the stored mode is not `without_terminal`
- WHEN the controller resolves the mode
- THEN the controller behaves as if the mode were `with_terminal`

### Requirement: Default mode preserves current behavior

When the effective mode is `with_terminal`, the system MUST preserve
the pre-change flow: the agent MUST pick a `terminal_caja`; no
"skip terminal" request parameter MUST be honored; no `caja` row
MUST be persisted with `fs_id = 0`.

#### Scenario: Terminal still required

- GIVEN mode is `with_terminal`
- WHEN the agent submits the TPV page without selecting a terminal
- THEN no new `caja` row is created
- AND the pick screen re-renders

#### Scenario: Sentinel parameter is ignored

- GIVEN mode is `with_terminal`
- WHEN a request arrives with a "skip terminal" sentinel parameter
- THEN the controller MUST treat it as if it were absent
- AND the agent is still required to pick a terminal

### Requirement: Without-terminal mode with terminals available

When mode is `without_terminal` and at least one `terminal_caja`
exists, the pick screen (`view/tpvmod.html` lines 46-71) MUST
render existing terminals AND a "Continuar sin terminal"
affordance. Selecting it MUST submit a sentinel request parameter
that causes the controller to create a `caja` with `fs_id = 0`
(sentinel meaning "no terminal") instead of a real
`terminal_caja.id`.

#### Scenario: Pick screen shows the new button

- GIVEN mode is `without_terminal` and at least one terminal exists
- WHEN the agent opens the TPV page with no caja in progress
- THEN the pick screen lists the available terminals
- AND a "Continuar sin terminal" button is rendered

#### Scenario: Clicking the button creates a no-terminal caja

- GIVEN the agent clicked the "Continuar sin terminal" button
- WHEN the controller receives the resulting request (sentinel parameter set)
- THEN a new `caja` row is inserted with `fs_id = 0`, the agent's `codagente`, and the posted `d_inicial`
- AND the agent proceeds to the in-progress TPV page (`tpvmod2`)

### Requirement: Without-terminal mode with zero terminals

When mode is `without_terminal` and zero terminals exist, the pick
screen MUST be bypassed entirely; the agent MUST land directly on
the `d_inicial` form (`view/tpvmod.html` lines 17-45).

#### Scenario: Direct d_inicial form

- GIVEN mode is `without_terminal` and no `terminal_caja` rows exist
- WHEN the agent opens the TPV page with no caja in progress
- THEN the pick screen is NOT rendered
- AND the `d_inicial` form is shown directly

#### Scenario: Submitting creates a no-terminal caja

- GIVEN the agent is on the direct `d_inicial` form
- WHEN the agent submits a `d_inicial` value
- THEN a `caja` row is inserted with `fs_id = 0` and the posted value

### Requirement: Iframe stays guarded in no-terminal paths

In all no-terminal paths (when `$fsc->terminal` is falsy), the
`localhost:10080` printer iframe in `view/tpvmod.html` (lines
78-82) MUST NOT be rendered. The existing `if($fsc->terminal)`
guard SHALL be preserved.

#### Scenario: No iframe without terminal

- GIVEN a `caja` is open with `fs_id = 0` and `$fsc->terminal` is null/false
- WHEN the TPV page renders
- THEN the rendered HTML MUST NOT contain the `localhost:10080` `<iframe>` element

#### Scenario: Iframe still renders with a real terminal

- GIVEN a `caja` is open with `fs_id > 0`
- WHEN the TPV page renders
- THEN the iframe block is rendered unchanged

### Requirement: abrir_caja and cerrar_caja tolerate no-terminal cajas

In all no-terminal paths, `abrir_caja()` and `cerrar_caja()` MUST
tolerate a missing `$this->terminal`. The existing
`if($this->terminal)` guards in those methods SHALL be preserved.

#### Scenario: abrir_caja is a no-op

- GIVEN a `caja` is open with `fs_id = 0` and `$this->terminal` is null
- WHEN an admin triggers `abrir_caja()`
- THEN no exception is raised
- AND the open-cash-drawer block is skipped

#### Scenario: cerrar_caja skips the printer block

- GIVEN a `caja` is open with `fs_id = 0`
- WHEN an agent triggers `cerrar_caja()`
- THEN `caja->save()` runs for the close timestamp
- AND the printer block (`if($this->terminal)`) is skipped
- AND the page reloads without a `&terminal=` query parameter

### Requirement: Caja model accepts fs_id = 0 sentinel

The `caja` model used by the plugin MUST accept `fs_id = 0` when
persisting a no-terminal caja. Plugin-side reads of `cajas.fs_id`
MUST NOT assume `fs_id > 0`.

#### Scenario: caja->save() with fs_id = 0 succeeds

- GIVEN the controller is in the no-terminal branch
- WHEN it assigns `$this->caja->fs_id = 0` and calls `$this->caja->save()`
- THEN the save returns true
- AND the new row stores `fs_id = 0` (integer)

#### Scenario: Downstream listing renders fs_id = 0 without crashing

- GIVEN a `caja` row exists with `fs_id = 0`
- WHEN the plugin's caja listing reads that row
- THEN the row is included in the result set
- AND no null-deref or divide-by-zero error is raised
- AND the `fs_id` cell MAY render the literal `0` (cosmetic only; out of scope for this change)

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
`facturacion_base` is active.

#### Scenario: No ventas_imprimir link without facturacion_base

- GIVEN `facturacion_base` is not active
- WHEN an albarán is saved from the TPV
- THEN the success message does not contain `page=ventas_imprimir`

### Requirement: Effective mode forced when caja module off

- GIVEN stored mode is `without_terminal` and `facturacion_base` is inactive
- WHEN the controller resolves mode at the start of `private_core()`
- THEN `$this->terminal_mode` is `with_terminal`
- AND caja/terminal branches are skipped entirely

### Requirement: cerrar caja button hidden without caja module

- GIVEN `facturacion_base` is not active
- WHEN `tpvmod2` renders
- THEN the "Cerrar caja" control is not shown

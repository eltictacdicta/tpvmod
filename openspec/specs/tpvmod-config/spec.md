# Capability: tpvmod-config

> Plugin-local capability. Source of truth lives at
> `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md`. The core
> FSFramework `openspec/` does not track this capability.

## Purpose

Define the admin-controlled global toggle that decides whether the
TPV agent must pick a `terminal_caja` before opening a `caja`, and
the persistence and access rules for that toggle. The plugin owns
the setting; the framework only provides the `fs_settings`
ini-backed store.

## Requirements

### Requirement: Persisted global toggle

The plugin SHALL expose a single global setting named
`tpvmod_terminal_mode` whose value is one of the literal strings
`with_terminal` (default) or `without_terminal`.

The setting MUST be persisted via `fs_settings`
(`base/fs_settings.php`, ini-backed `$GLOBALS['config2']`,
`tmp/{FS_TMP_NAME}config2.ini`). It MUST NOT be persisted in any
database table.

#### Scenario: Default value on fresh install

- GIVEN the plugin is installed and the `tpvmod_terminal_mode` key has never been written
- WHEN any code inside the plugin reads the setting
- THEN the returned value is `with_terminal`
- AND no error is raised

#### Scenario: Round-trip persistence

- GIVEN an admin writes the value `without_terminal` and the request completes
- WHEN the same value is read back in a subsequent request
- THEN the read returns `without_terminal`
- AND the value survives a process restart (INI file written via `fs_settings::save()`)

### Requirement: Admin-only write guard

The system MUST reject any write attempt to `tpvmod_terminal_mode`
originating from a non-admin user. A rejected write MUST emit an
error message, MUST NOT mutate the stored value, and MUST keep the
admin on the settings page.

#### Scenario: Non-admin POST is rejected

- GIVEN a logged-in user whose `admin` flag is false
- WHEN the user POSTs a new value to the settings endpoint
- THEN no value is written to `config2`
- AND the user sees an error message naming the admin requirement
- AND the stored mode is unchanged

#### Scenario: Admin POST is accepted

- GIVEN a logged-in admin user
- WHEN the admin POSTs a valid mode with a valid CSRF token
- THEN the value is written via `fs_settings::set()` and persisted via `fs_settings::save()`
- AND the user sees a success message

### Requirement: Default fallback on read

Any code in the plugin that reads `tpvmod_terminal_mode` MUST treat
the value as `with_terminal` when the key is absent, empty, or holds
any string other than `without_terminal`. The system MUST NOT crash
on a malformed value.

The setting MUST only affect TPV runtime behaviour when
`facturacion_base` is active (`tpvmod_caja_module_enabled()` is true).
When `facturacion_base` is inactive, reads MUST behave as `with_terminal`
for branching purposes regardless of the stored value.

#### Scenario: Malformed value falls back to default

- GIVEN the stored value is an unknown string (e.g. `maybe`)
- WHEN the controller reads the mode
- THEN the effective mode used for branching is `with_terminal`
- AND no exception is raised

#### Scenario: Mid-request mode change is ignored

- GIVEN a request has read the mode as `with_terminal` at the start of execution
- WHEN a separate admin request changes the mode to `without_terminal` before the first request finishes
- THEN the in-flight request MUST continue using the value it read at the start (no late re-read)

### Requirement: Admin settings page route

The plugin MUST expose a settings page at
`index.php?page=tpvmod_settings`, backed by a new
`controller/tpvmod_settings.php` controller extending `fs_controller`
and a new `view/tpvmod_settings.html` template. The page MUST be
admin-only.

#### Scenario: Admin can open the page

- GIVEN an admin user
- WHEN the user navigates to `index.php?page=tpvmod_settings`
- THEN the page renders the current mode and a save form
- AND the form contains a `<select>` with the two allowed options and a submit button

#### Scenario: Non-admin cannot reach the page

- GIVEN a non-admin user
- WHEN the user navigates to `index.php?page=tpvmod_settings`
- THEN the page is denied (no form, no save action)

### Requirement: Settings page behavior

The settings page MUST display the currently stored mode, accept a
POST submitting a new mode, persist the new value through
`fs_settings::save()`, and show a success or error message via the
controller's standard `new_message()` / `new_error_msg()` helpers.

#### Scenario: Successful save

- GIVEN the page is open with current mode `with_terminal`
- WHEN the admin selects `without_terminal` and submits the form
- THEN `fs_settings::set('tpvmod_terminal_mode', 'without_terminal')` is called
- AND `fs_settings::save()` writes the INI file
- AND a success message is shown
- AND a subsequent GET shows the page with `without_terminal` selected

### Requirement: Terminal settings gated on facturacion_base

The admin settings for `tpvmod_terminal_mode` MUST only be editable when
`facturacion_base` is among the active plugins. When `facturacion_base` is
not active, the settings page MUST explain that caja/terminal features require
that plugin and MUST NOT persist changes to `tpvmod_terminal_mode`.

#### Scenario: Settings form hidden when facturacion_base inactive

- GIVEN `facturacion_base` is not in `$GLOBALS['plugins']`
- WHEN an admin opens `index.php?page=tpvmod_settings`
- THEN the terminal mode `<select>` is not shown
- AND an informational message states that `facturacion_base` is required

#### Scenario: POST rejected when facturacion_base inactive

- GIVEN `facturacion_base` is not active
- WHEN an admin POSTs a new terminal mode
- THEN the value is not written
- AND an error message is shown

#### Scenario: Stored without_terminal ignored without facturacion_base

- GIVEN `tpvmod_terminal_mode` is stored as `without_terminal`
- AND `facturacion_base` is not active
- WHEN the TPV controller resolves the effective mode
- THEN the effective mode is `with_terminal` for caja branching
- AND the agent is not offered caja/terminal UI (direct sales mode)

#### Scenario: Empty or invalid value rejected

- GIVEN the admin submits an empty `mode` field or any value other than the two allowed literals
- WHEN the controller validates the input
- THEN the value is NOT written
- AND an error message is shown
- AND the stored mode is unchanged
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

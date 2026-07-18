# Delta: tpvmod-config — facturacion_base opcional

## ADDED Requirements

### Requirement: Terminal settings gated on facturacion_base

The admin settings for `tpvmod_terminal_mode` MUST only be editable when
`facturacion_base` is among the active plugins. When `facturacion_base` is
not active, the settings page MUST explain that caja/terminal features require
that plugin and MUST NOT persist changes to `tpvmod_terminal_mode`.

#### Scenario: Settings form hidden when facturacion_base inactive

- GIVEN `facturacion_base` is not in `$GLOBALS['plugins']`
- WHEN an admin opens `index.php?page=tpvmod_settings`
- THEN the terminal mode `<select>` is not shown (or is disabled)
- AND an informational message states that `facturacion_base` is required

#### Scenario: POST rejected when facturacion_base inactive

- GIVEN `facturacion_base` is not active
- WHEN an admin POSTs a new terminal mode
- THEN the value is not written
- AND an error message is shown

## MODIFIED Requirements

### Requirement: Persisted global toggle

The plugin SHALL expose a single global setting named
`tpvmod_terminal_mode` whose value is one of the literal strings
`with_terminal` (default) or `without_terminal`.

The setting MUST be persisted via `fs_settings`
(`base/fs_settings.php`, ini-backed `$GLOBALS['config2']`,
`tmp/{FS_TMP_NAME}config2.ini`). It MUST NOT be persisted in any
database table.

The setting MUST only affect TPV runtime behaviour when
`facturacion_base` is active (`tpvmod_caja_module_enabled()` is true).
When `facturacion_base` is inactive, reads MUST behave as `with_terminal`
for branching purposes regardless of the stored value.

#### Scenario: Stored without_terminal ignored without facturacion_base

- GIVEN `tpvmod_terminal_mode` is stored as `without_terminal`
- AND `facturacion_base` is not active
- WHEN the TPV controller resolves the effective mode
- THEN the effective mode is `with_terminal` for caja branching
- AND the agent is not offered caja/terminal UI (direct sales mode)

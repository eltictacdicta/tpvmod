# Proposal: Make TPV terminal selection optional (admin-controlled)

## Intent

Today, `plugins/tpvmod/controller/tpvmod.php:230-372` forces every agent to pick a `terminal_caja` before opening a `caja`. Operators without a physical printer (e.g., back-office or catalog-only POS) cannot start a caja unless a terminal is configured. The user wants the administrator to be able to relax this requirement.

**Outcome**: a new global setting `tpvmod_terminal_mode` (default `with_terminal`, preserves current behavior; new `without_terminal` lets the agent skip the terminal pick). Only the admin can change it. When `without_terminal` is enabled, the agent sees the existing terminal pick plus a "Continuar sin terminal" button; if no terminals are configured at all, the pick screen is bypassed and the agent lands directly on the `dinero inicial` form.

## Scope

### In Scope
- New admin controller `plugins/tpvmod/controller/tpvmod_settings.php` + view `plugins/tpvmod/view/tpvmod_settings.html` exposing the toggle to admins.
- `plugins/tpvmod/controller/tpvmod.php` (230-372) — branch on the new setting; accept a new `$_POST['skip_terminal']` / `$_GET['no_terminal']`; persist `caja.fs_id = 0` sentinel when no terminal is used.
- `plugins/tpvmod/view/tpvmod.html` (46-71) — render the "Continuar sin terminal" button; render the `dinero inicial` form directly when no terminals exist and mode is `without_terminal`.
- New plugin-internal spec file `plugins/tpvmod/openspec/changes/terminal-opcional/specs/tpvmod-config/spec.md` (new capability) and `specs/tpv-flow/spec.md` (modified capability).

### Out of Scope
- **No changes to `plugins/facturacion_base/`.** The `caja` XML schema (`model/table/cajas.xml:15-19`) declares `fs_id` as `integer NOT NULL` with no default, so it cannot hold NULL/empty without a schema change. We use a sentinel `fs_id = 0` (terminal_caja uses `serial` starting at 1, so 0 is collision-free; no FK exists). See Risks.
- No changes to `base/`, `src/`, core `controller/`, core `model/`, `tpv_caja` in facturacion_base, or any Twig template.
- No printer iframe rewrite. The `localhost:10080?terminal=…` iframe at `view/tpvmod.html:78-82` is already guarded by `if($fsc->terminal)` and stays as-is.

## Capabilities

### New Capabilities
- `tpvmod-config`: persistent global toggle `tpvmod_terminal_mode` (`with_terminal` | `without_terminal`), admin-only writes via `fs_settings` (`base/fs_settings.php`, ini-backed `config2`, **not a DB table**).

### Modified Capabilities
- `tpv-flow`: terminal pick at `tpvmod.php:230-372` and view `tpvmod.html:46-71` gains a no-terminal branch; `caja.fs_id` accepts the `0` sentinel in this plugin only.

## Approach

1. Add `plugins/tpvmod/controller/tpvmod_settings.php` (extends `fs_controller`); reads/writes `tpvmod_terminal_mode` via `new fs_settings()`; rejects POST if `!$this->user->admin`. Pairs with `view/tpvmod_settings.html` (single select, `with_terminal` / `without_terminal`).
2. In `tpvmod.php:246-283`, before the `$_POST['terminal']` branch, read the mode: `$mode = (new fs_settings())->get('tpvmod_terminal_mode', 'with_terminal');`. If `$mode === 'without_terminal'`, accept a new `$_POST['skip_terminal']=1` (or `$_GET['no_terminal']=1`) and create the caja with `$this->caja->fs_id = 0` instead of `terminal->id`.
3. In `tpvmod.php:363-366`, when the pick screen would render, first check `terminal0->all()` (or `disponibles()`) — if empty **and** mode is `without_terminal`, force `$this->terminal = FALSE` and pre-set the `d_inicial` state so the view renders the "dinero inicial" form directly.
4. Update `view/tpvmod.html:46-71` to show a "Continuar sin terminal" button next to the existing "Administrar terminales" fallback, but only when `mode === 'without_terminal'`.
5. Leave `abrir_caja()` (2656) and `cerrar_caja()` (2672) untouched — they already guard on `if($this->terminal)`. Leave the `localhost:10080` iframe (78-82) untouched.
6. `tpvmod2.html:172-178` and `tpvmodedita.html:247-253` already handle `!$fsc->terminal` (selector visible); no change needed.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/tpvmod/controller/tpvmod.php:230-372` | Modified | Branch on mode; accept `skip_terminal`; `caja.fs_id=0` sentinel |
| `plugins/tpvmod/view/tpvmod.html:46-71` | Modified | Add "Continuar sin terminal" button (gated on mode) |
| `plugins/tpvmod/controller/tpvmod_settings.php` | New | Admin-only toggle page |
| `plugins/tpvmod/view/tpvmod_settings.html` | New | Form: select mode + save |
| `plugins/tpvmod/facturascripts.ini` | Unchanged | already requires `facturacion_base` |
| `plugins/facturacion_base/model/table/cajas.xml` | **Read-only check** | `fs_id` is `integer NOT NULL` (no default) — see Risks |

## Risks

| Risk | L | Mitigation |
|------|---|------------|
| `cajas.fs_id` schema is `integer NOT NULL` — no NULL/empty allowed without a facturacion_base change (out of scope) | H | Use sentinel `fs_id = 0`; verified safe: `terminal_caja` uses `serial` (id ≥ 1), no FK to `cajas_terminales` exists in `cajas_terminales.xml` (only `cajas_pkey` on `id`). Document in plugin README. |
| Existing reports / arqueo screens (`tpv_caja.html:112` shows `{$value->fs_id}`) will render `0` for no-terminal cajas | M | Render `0` as `"—"` or `"Sin terminal"` in the `fs_id` cell. Stay in tpvmod-owned views only. |
| Non-admin could POST to `tpvmod_settings` and flip the mode | M | Hard guard: `if (!$this->user->admin) { $this->new_error_msg('Sólo un administrador…'); return; }` — same pattern as `abrir_caja():2658`. |
| `fs_settings` is INI-backed (`tmp/{FS_TMP_NAME}config2.ini`), not a DB table as the user phrasing implied | L | Call this out in the spec; same persistence model other plugins use. |
| Hidden iframe at `view/tpvmod.html:80` still hits `localhost:10080` whenever a terminal exists; harmless when no terminal because guarded by `if($fsc->terminal)` | L | None — already safe. |
| `cerrar_caja()` redirects via `&terminal=…` (line 2703); with `fs_id=0` it falls into the `else` branch and reloads cleanly | L | Verified by code path. |

## Rollback Plan

1. Revert `tpvmod.php:230-372` and `view/tpvmod.html:46-71` to the pre-change branch (terminal required).
2. Delete `controller/tpvmod_settings.php` and `view/tpvmod_settings.html`.
3. Existing cajas with `fs_id = 0` remain valid (integer column). No DB migration needed. Admins who toggled `without_terminal` can clear the key via `fs_settings::remove('tpvmod_terminal_mode')` or just leave it — the default in code is `with_terminal`.
4. Do not merge delta specs into `openspec/specs/`.

## Dependencies

- `facturacion_base` (already required by `facturascripts.ini`) — for `caja` and `terminal_caja` models. Schema verified read-only.
- `base/fs_settings.php` — for `tpvmod_terminal_mode` persistence.
- `model/core/fs_user.php` — for `$this->user->admin` (line 81).
- No new Composer packages. No PHPUnit suite (plugin has no `tests/` per `openspec/config.yaml`).

## Success Criteria

- [ ] Admin visits `index.php?page=tpvmod_settings`, sees current mode, can toggle to `without_terminal`, save persists across reload.
- [ ] Non-admin POSTing to `tpvmod_settings` gets an error and the setting does not change.
- [ ] With mode `with_terminal` (default): existing behavior is byte-for-byte preserved (terminal pick required).
- [ ] With mode `without_terminal` and ≥1 terminal configured: terminal pick screen shows the existing terminals **and** a "Continuar sin terminal" button; clicking it opens the `d_inicial` form and creates a `caja` with `fs_id = 0`.
- [ ] With mode `without_terminal` and 0 terminals configured: agent lands directly on the `d_inicial` form (no pick screen).
- [ ] In all no-terminal paths, `abrir_caja` (admin open-cash-drawer) and `cerrar_caja` (print cierre) are no-ops; no NPE on `$this->terminal`; no `localhost:10080` iframe loaded.
- [ ] `tpv_caja.html` (facturacion_base) renders `0` rows without crashing; the `fs_id` cell shows the agent's fullname / date unchanged.
- [ ] `ddev exec composer phpstan` shows no new errors.
- [ ] `ddev exec php vendor/bin/phpunit --testsuite Base` and `--testsuite Plugins` pass (no regression in shared code).
- [ ] Manual smoke: agent opens caja, generates a fake ticket, closes caja — both with and without a terminal.

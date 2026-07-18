# Design: terminal-opcional

## 1. Context

`plugins/tpvmod/controller/tpvmod.php:230-372` forces every agent to pick a
`terminal_caja` before opening a `caja`. Operators without a physical printer
(back-office, catalog-only POS) cannot start a session. The administrator
needs a global toggle to relax this requirement.

This change adds a single persisted boolean-shaped mode `tpvmod_terminal_mode`
(`with_terminal` | `without_terminal`) stored via the framework's
ini-backed `fs_settings` (`base/fs_settings.php`, file
`tmp/{FS_TMP_NAME}config2.ini` — NOT a database table). When
`without_terminal` is active, agents either bypass the pick screen (zero
terminals configured) or click a new "Continuar sin terminal" button (≥1
terminal configured) which creates a `caja` with `fs_id = 0` (sentinel
meaning "no terminal"). The default value preserves the current behavior
byte-for-byte.

**Constraint**: plugin-local only. No edits to `base/`, `src/`, core
`controller/`, core `model/`, `tpv_caja` in facturacion_base, or any
Twig template. The `caja` XML schema (`plugins/facturacion_base/model/table/cajas.xml:15-19`)
declares `fs_id` as `integer NOT NULL` with no default — we use the integer
sentinel `0` because `cajas_terminales.id` is `serial` (starts at 1), so
`0` is collision-free and no FK exists.

**Persistence model**: `fs_settings` (ini-backed `$GLOBALS['config2']`).
Read with `new fs_settings()->get('tpvmod_terminal_mode', 'with_terminal')`,
write with `->set(...)` + `->save()`. Class is already loaded by the
core `fs_controller` constructor (`base/fs_controller.php:194-195`); no
`require_once` needed in the new controller.

## 2. File-by-file change outline

### 2.1 `plugins/tpvmod/controller/tpvmod_settings.php` (NEW)

Full skeleton:

```php
<?php
/**
 * File header (FSFramework LGPL-3.0) omitted for brevity.
 */

require_model('fs_user.php');   // already loaded by parent, defensive

class tpvmod_settings extends fs_controller
{
    /** Allowed values for the toggle. Keep in sync with the view <option> list. */
    private const ALLOWED_MODES = ['with_terminal', 'without_terminal'];
    private const SETTINGS_KEY  = 'tpvmod_terminal_mode';
    private const DEFAULT_MODE  = 'with_terminal';

    /** @var string Current effective mode (after read+validate). */
    public $terminal_mode;

    /** @var fs_settings */
    private $settings;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'TPVMOD settings', 'admin', TRUE, TRUE);
        //         ^name           ^title            ^folder  ^admin ^shmenu
        //
        // Admin gate: $folder='admin' registers the page in `fs_pages` with the
        // admin folder. No `fs_rol_access` row is created for non-admin roles,
        // so `fs_user::get_menu()` (model/core/fs_user.php:380-388) excludes it
        // for non-admins. `fs_controller::__construct` (base/fs_controller.php:231)
        // then routes them to the access_denied template. We rely on this —
        // do NOT add a manual `if (!$this->user->admin) return;` check (would
        // mask a misconfigured $folder and create two sources of truth).
        //
        // The 4th arg `$admin=TRUE` in the constructor is currently a
        // no-op in check_fs_page (base/fs_controller.php:192 drops it),
        // but we pass it for parity with business_data/admin_empresa.php:82
        // and system_updater/admin_updater.php:105 — when the framework
        // grows a real admin gate we'll be aligned.
    }

    protected function private_core()
    {
        $this->settings = new fs_settings();
        $this->terminal_mode = $this->read_mode();

        if ($this->request->getMethod() === 'POST') {
            $this->handle_post();
        }
    }

    private function read_mode(): string
    {
        $value = (string) $this->settings->get(self::SETTINGS_KEY, self::DEFAULT_MODE);
        return in_array($value, self::ALLOWED_MODES, TRUE) ? $value : self::DEFAULT_MODE;
    }

    private function handle_post(): void
    {
        // CSRF: validateCsrf() runs in pre_private_core for POSTs
        // (base/fs_controller.php:357-389). If it failed the template is
        // already set to access_denied and private_core() never runs —
        // so we get here only on a valid token. We double-check for safety:
        if (!$this->isCsrfValid()) {
            $this->new_error_msg('Token CSRF inválido.');
            return;
        }

        $raw = (string) ($this->request->request->get('tpvmod_terminal_mode') ?? '');
        if (!in_array($raw, self::ALLOWED_MODES, TRUE)) {
            $this->new_error_msg('Valor de modo no permitido. Use with_terminal o without_terminal.');
            return;
        }

        $this->settings->set(self::SETTINGS_KEY, $raw);
        $this->settings->save();
        $this->terminal_mode = $raw;   // refresh for the view
        $this->new_message('Modo de terminal guardado.');
    }
}
```

**Edge cases**:
- `$_POST['tpvmod_terminal_mode']` missing → `null → ''` → not in
  whitelist → error message, value NOT written.
- Empty string → same path as missing.
- Whitelisted value → saved + flash success.
- Malformed existing value in INI (e.g. `maybe`) → `read_mode()`
  normalizes to `with_terminal`; the view still renders correctly.
- `fs_settings::save()` writes the file via `fopen` (line 264-278); if
  the `tmp/` directory is unwritable, PHP raises a warning and
  `save()` returns false. The controller does not check the return
  value (matches the `fs_settings::save()` convention elsewhere). If
  this becomes a real failure mode, add an `if (!$this->settings->save())` check.

### 2.2 `plugins/tpvmod/view/tpvmod_settings.html` (NEW)

RainTPL template (no Twig; tpvmod is a legacy RainTPL plugin):

```html
{include="header"}

<div class="container">
   <div class="row">
      <div class="col-sm-6 col-sm-offset-3">
         <div class="page-header">
            <h1>Configuración de TPV (modo terminal)</h1>
         </div>
         <form action="{$fsc->url()}" method="post" class="form">
            {csrf_field()}
            <div class="form-group">
               <label for="tpvmod_terminal_mode">Modo de selección de terminal</label>
               <select name="tpvmod_terminal_mode" id="tpvmod_terminal_mode" class="form-control">
                  <option value="with_terminal" {if="$fsc->terminal_mode=='with_terminal'"}selected{/if}>
                     Con terminal (por defecto)
                  </option>
                  <option value="without_terminal" {if="$fsc->terminal_mode=='without_terminal'"}selected{/if}>
                     Sin terminal permitido
                  </option>
               </select>
               <p class="help-block">
                  <small>
                     <strong>Con terminal</strong>: cada agente debe elegir un terminal antes de abrir caja.
                     <strong>Sin terminal permitido</strong>: el agente puede abrir caja sin elegir
                     terminal físico (útil para back-office y TPV de catálogo).
                  </small>
               </p>
            </div>
            <div class="text-right">
               <button class="btn btn-sm btn-primary" type="submit">
                  <span class="glyphicon glyphicon-floppy-disk"></span> &nbsp; Guardar
               </button>
            </div>
         </form>
      </div>
   </div>
</div>

{include="footer"}
```

`{csrf_field()}` is provided by the `fs_controller` helper
(`base/fs_session_manager.php:421`). RainTPL escapes `{$fsc->url()}` and
`{$fsc->terminal_mode}` by default; the literal values used here come
from a fixed enum, so no XSS surface. The `selected` flag is rendered
through RainTPL, not user input.

### 2.3 `plugins/tpvmod/controller/tpvmod.php` (MODIFIED)

Two edits:

**(a) Add property + read mode once at the top of `private_core()`** (after line 86, before the existing `else` cascade at line 108):

```php
// tpvmod-opcional: read mode once per request, before any terminal branching.
$settings = new fs_settings();
$candidate = (string) $settings->get('tpvmod_terminal_mode', 'with_terminal');
$this->terminal_mode = ($candidate === 'without_terminal') ? 'without_terminal' : 'with_terminal';
```

Add the property declaration alongside the others (line 59 area):

```php
public $terminal_mode = 'with_terminal';
```

**(b) Restructure the no-caja block at `private_core()` lines 246-283** to
insert the no-terminal branch BEFORE the `$_POST['terminal']` branch:

```php
if (!$this->caja) {
   // tpvmod-opcional: no-terminal branch (mode=without_terminal only).
   if ($this->terminal_mode === 'without_terminal'
       && (isset($_GET['no_terminal']) || (isset($_POST['d_inicial'])
           && count($terminal0->all()) === 0))) {
      $this->caja = new caja();
      $this->caja->fs_id = 0;   // sentinel: no terminal — see proposal §Risks
      $this->caja->codagente = $this->agente->codagente;
      $this->caja->dinero_inicial = floatval($_POST['d_inicial'] ?? 0);
      $this->caja->dinero_fin     = floatval($_POST['d_inicial'] ?? 0);
      if ($this->caja->save()) {
         $this->new_message('Caja iniciada sin terminal.');
      } else {
         $this->new_error_msg('¡Imposible guardar los datos de caja!');
         $this->caja = FALSE;   // fall through to pick screen
      }
   }
   elseif (isset($_POST['terminal'])) {
      // ... EXISTING BLOCK UNCHANGED (lines 248-271) ...
   }
   elseif (isset($_GET['terminal'])) {
      // ... EXISTING BLOCK UNCHANGED (lines 272-282) ...
   }
}
```

**Why the branch condition looks weird**: it has to handle two entry
points in one place:

1. Agent clicks "Continuar sin terminal" → GET with `&no_terminal=1` →
   falls into the `isset($_GET['no_terminal'])` half of the `||`. The
   `d_inicial` form renders next; the second submit (POST) carries
   `d_inicial` and 0 terminals exist, so it lands in the second half of
   the `||`.
2. Agent lands directly on the `d_inicial` form (no terminals
   configured) → submits with `d_inicial` in POST and 0 terminals in the
   table → second half of the `||`.

The third clause `count($terminal0->all()) === 0` is what blocks the
pick-screen→d_inicial→submit cycle from being possible with terminals
configured (the GET `no_terminal=1` arm handles the "user picked the
button" path explicitly so the count check is moot there; we only
require it on the POST path). The two arms are mutually exclusive in
practice: a GET with `no_terminal=1` is the button click; a POST with
`d_inicial` (and no `no_terminal`) is the form submit.

This block preserves the existing `$_POST['terminal']` and
`$_GET['terminal']` paths byte-for-byte when mode is `with_terminal`,
because the outer `if ($this->terminal_mode === 'without_terminal' …)`
short-circuits to false. The `d_inicial` POST handler at line 260
already sets `$this->caja->fs_id = $this->terminal->id`; in the
no-terminal branch we explicitly bypass that by entering our branch
first.

**Existing `abrir_caja` (line 2656) and `cerrar_caja` (line 2672) are
NOT touched** — they already guard on `if($this->terminal)`, so a
no-terminal caja produces a no-op for the open-cash-drawer block and
the printer block. The `header('location: '.$this->url().'&terminal=…')`
at line 2703 only fires inside the `if($this->terminal)` branch; a
no-terminal caja lands in the `else` at line 2705 and reloads cleanly.

### 2.4 `plugins/tpvmod/view/tpvmod.html` (MODIFIED)

Wrap the existing fallback "Administrar terminales" `<a>` at line 64
in a RainTPL conditional that ALSO shows the new button when
`terminal_mode==='without_terminal'`:

```html
{loop="$fsc->results"}
<div class="col-sm-6">
   <a href="{$fsc->url()}&terminal={$value->id}" class="btn btn-block btn-default">
      <span class="glyphicon glyphicon-print" aria-hidden="true"></span> &nbsp; Terminal {$value->id}
   </a>
</div>
{else}
<div class="col-sm-12">
   <a href="index.php?page=tpv_caja#terminales" class="btn btn-block btn-info">
      <span class="glyphicon glyphicon-wrench" aria-hidden="true"></span> &nbsp; Administrar terminales
   </a>
   {if="$fsc->terminal_mode=='without_terminal'"}
   <br>
   <a href="{$fsc->url()}&no_terminal=1" class="btn btn-block btn-warning">
      <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> &nbsp; Continuar sin terminal
   </a>
   {/if}
</div>
{/loop}
```

When zero terminals are configured, `$fsc->results` is empty (set on
line 365) and the view renders the `else` block above. With
`terminal_mode=with_terminal`, the inner `if` is false and the
no-terminal button is hidden — existing behavior preserved. The
existing `if($fsc->terminal)` iframe guard at line 78-82 stays
untouched (verified: the guard fires only when a real terminal is
loaded, so a no-terminal caja never renders the iframe).

### 2.5 `plugins/facturacion_base/model/table/cajas.xml` (UNCHANGED, read-only check)

`fs_id` is `integer NOT NULL` (no default) at line 15-19. The sentinel
`fs_id = 0` is valid because:
- `cajas_terminales.id` is `serial` (starts at 1, monotonically
  increasing) — confirmed at
  `plugins/facturacion_base/model/table/cajas_terminales.xml:11`.
- No foreign key from `cajas.fs_id` to `cajas_terminales.id` exists
  (the only constraint in `cajas.xml` is `cajas_pkey` on `id`, line
  58-61).
- A grep across `plugins/facturacion_base` and `plugins/tpvmod` for
  `fs_id` shows 14 references; NONE use a `fs_id > 0` or
  `fs_id != 0` filter. All reads either (a) assign a real terminal
  via `terminal_caja::get($cj->fs_id)` and tolerate `FALSE` (lines
  190, 241), or (b) compare against `$this->id` on a known terminal
  object (line 137, `terminal_caja::disponible()`), or (c) interpolate
  into a string (line 2681, 662). The sentinel is safe everywhere.

No schema change required. No DB migration.

## 3. Data flow

```
Admin: GET /index.php?page=tpvmod_settings
   → tpvmod_settings::__construct (admin folder gate, fs_page auto-registered)
   → private_core(): fs_settings->get('tpvmod_terminal_mode', 'with_terminal')
                  → $this->terminal_mode
   → view tpvmod_settings.html renders <select> with current value

Admin: POST /index.php?page=tpvmod_settings
   → pre_private_core() validates CSRF (base/fs_controller.php:357-389)
   → private_core() → handle_post() → fs_settings->set() + ->save()
                  → INI file tmp/{FS_TMP_NAME}config2.ini updated
                  → flash success

Agent: GET /index.php?page=tpvmod (no caja open)
   → tpvmod::private_core():
        read mode once → $this->terminal_mode
        branch:
          with_terminal   → existing flow (terminal pick required)
          without_terminal + 0 terminals → fall through to d_inicial form
          without_terminal + ≥1 terminal → results[] shown + new button
   → view tpvmod.html renders either the pick screen (with new button) or the
     d_inicial form directly (when 0 terminals).

Agent: GET /index.php?page=tpvmod&no_terminal=1
   → tpvmod::private_core() no-caja branch, first arm fires
   → caja->save() with fs_id=0, codagente, d_inicial
   → re-renders tpvmod with $fsc->caja set, $fsc->terminal still FALSE
   → include="tpvmod2" path; iframe block at line 78-82 is skipped
     (if($fsc->terminal) is false)
   → agent does ticket flow
   → agent triggers cerrar_caja → if($this->terminal) is false → printer
     block skipped → header('location: '.$this->url()) reloads cleanly
     (line 2705-2709, already in the codebase)
```

`$this->terminal_mode` is set once in `tpvmod::private_core()` and read
once in `view/tpvmod.html`. No late re-reads (matches the
"mid-request mode change is ignored" scenario in the spec).

## 4. Security

- **CSRF (POST endpoints)**:
  - `tpvmod_settings` POST: `pre_private_core()` calls
    `validateCsrf()` (`base/fs_controller.php:355-389`); if the token
    is missing or invalid, the template is set to `access_denied` and
    `private_core()` never runs. We additionally re-check
    `isCsrfValid()` inside `handle_post()` for defence-in-depth.
  - `tpvmod` `d_inicial` POST: the existing form on line 23 of
    `view/tpvmod.html` does NOT currently include a CSRF token. The
    project baseline (AGENTS.md → "common safety baseline") is CSRF
    in all mutating flows. **This change adds `{csrf_field()}` to the
    existing `d_inicial` form on line 23 of `view/tpvmod.html`** —
    it's a single line, keeps the existing controller branch
    unchanged, and aligns with baseline. The pre-existing
    `$_POST['terminal']` flow is unaffected.
- **CSRF (GET endpoints — none required)**:
  - `no_terminal=1` link: GET, idempotent. No CSRF needed.
  - `terminal=N` link: GET, idempotent. No CSRF needed.
- **Admin gate**: rely on `parent::__construct(__CLASS__, 'TPVMOD
  settings', 'admin', TRUE, TRUE);`. The `folder='admin'` registers
  the page in `fs_pages` with the admin folder; non-admin users have
  no `fs_rol_access` row pointing to it, so `fs_user::get_menu()`
  (model/core/fs_user.php:380-388) excludes it and
  `have_access_to()` returns FALSE → `fs_controller` renders
  `access_denied`. **Do not add a manual `if (!$this->user->admin)
  return;`** in `private_core()`. Two gates means two sources of
  truth; if the framework changes the gate semantics, the manual
  check could mask a misconfiguration.
- **XSS**:
  - `{$fsc->terminal_mode}` in `view/tpvmod_settings.html`: the value
    is one of two literal strings from `ALLOWED_MODES`. RainTPL
    escapes `{$var}` by default. No XSS surface.
  - `{$fsc->url()}` in `view/tpvmod.html`: builder-generated URL;
    escaped by RainTPL.
  - No `|raw` usage introduced; no user-provided content reaches the
    view unescaped.
- **Sentinel `fs_id = 0` safety**: a `grep -r "fs_id"` over
  `plugins/facturacion_base` and `plugins/tpvmod` finds 14 matches
  across 3 files:
  - `plugins/facturacion_base/controller/tpv_recambios.php:190, 202,
    662` — same pattern as `tpvmod.php`: assigns
    `$this->terminal = $terminal0->get($cj->fs_id)`; tolerates FALSE
    when `fs_id=0`; no filter that would silently drop the row.
  - `plugins/facturacion_base/model/core/terminal_caja.php:137, 330`
    — `disponible()` filters by `fs_id = $this->id` (terminals start
    at 1, so 0 never matches); `disponibles()` uses
    `NOT IN (SELECT fs_id as id FROM cajas WHERE f_fin IS NULL)`.
    A `cajas.fs_id = 0` row contributes `0` to the subquery; since
    no real terminal has `id=0`, the `NOT IN` filter is correct
    (the `0` value is not in the set of real ids, so no terminal
    is incorrectly excluded).
  - `plugins/facturacion_base/model/core/caja.php:39, 91, 123, 188,
    201-202` — pure data layer; assigns and persists the integer
    as-is.
  - `plugins/tpvmod/controller/tpvmod.php:241, 258, 2681` — same
    patterns; tolerates FALSE.
  - `plugins/facturacion_base/view/tpv_caja.html:112` — renders
    `{$value->fs_id}` as a literal integer cell. Renders `0` as `0`
    (cosmetic only; spec R-caja-flow §Downstream listing scenario
    explicitly allows this).
  - **No code path treats `fs_id = 0` as invalid or filters it out.**
- **CSRF: NO manual `if (!$this->user->admin) return;`**: see Admin gate above.

## 5. Backward compatibility

- Default mode is `with_terminal` — preserves the existing flow
  byte-for-byte. The new `if ($this->terminal_mode ===
  'without_terminal' …)` branch is the first arm of the `if`/`elseif`
  chain at line 246; the existing `$_POST['terminal']` and
  `$_GET['terminal']` arms are unchanged.
- No DB migration. `cajas.fs_id` is `integer NOT NULL`; existing
  rows with `fs_id > 0` continue to work. The schema XML is not
  touched.
- `fs_settings` does not have `tpvmod_terminal_mode` on a fresh
  install → `get(..., 'with_terminal')` returns the default
  literal. No error, no notice.
- `tpvmod.php:230-372` is the only modified file in `plugins/tpvmod`
  for the controller. The view change in `tpvmod.html` adds ONE
  inner conditional — the outer fallback `<a>` is byte-identical
  with the prior code.
- No new Composer packages. No new translation keys (the
  "Continuar sin terminal" string is a hardcoded label matching the
  style of "Administrar terminales" two lines below).
- Plugin `facturascripts.ini` `version` does not need a bump (no
  schema or interface change). The plugin's git history is local to
  `plugins/tpvmod/.git` (gitignored from the core repo per
  `AGENTS.md`).

## 6. Rollback

1. `git checkout <commit-before-this-change> -- plugins/tpvmod/controller/tpvmod.php plugins/tpvmod/view/tpvmod.html` (works in the plugin's own `.git` — these are tracked there; from the parent core repo they are gitignored and `git checkout` is a no-op, so rollback happens inside the plugin's repo).
2. `rm -f plugins/tpvmod/controller/tpvmod_settings.php plugins/tpvmod/view/tpvmod_settings.html`.
3. Existing `cajas` rows with `fs_id = 0` remain valid integers; no DB cleanup. Admins who toggled `without_terminal` can either leave the key in `config2.ini` (the default-when-missing code path ignores it) or `fs_settings::remove('tpvmod_terminal_mode')` + `fs_settings::save()`.
4. Do not merge the delta specs into
   `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md` or
   `plugins/tpvmod/openspec/specs/tpv-flow/spec.md` — leave them in
   `plugins/tpvmod/openspec/changes/terminal-opcional/specs/` (the
   archive step is the only place that does the merge, and a
   rollback is defined as "the change never completed").
5. The `fs_pages` row auto-created for `tpvmod_settings` (via
   `check_fs_page` on first hit) stays in the DB after rollback.
   Harmless: no menu link is rendered for it (`show_on_menu=TRUE`
   in the page row, but the controller file is gone, so any click
   would 404). If you want to remove it, run
   `DELETE FROM fs_pages WHERE name='tpvmod_settings';`. Out of
   scope for the rollback plan.

## 7. Open questions

None — all pre-resolved picks baked in. The grep audit in §4
confirmed the `fs_id = 0` sentinel is safe; the
`$folder='admin'` gate matches `business_data/admin_empresa.php` and
`system_updater/admin_updater.php`; CSRF coverage is explicit per
endpoint; XSS surface is limited to two enum-literal values.

## 8. Ready for tasks?

**Yes.** `sdd-tasks` will produce a sequenced implementation plan:

1. Add `tpvmod_settings` controller + view (with CSRF).
2. Wire `private_core()` to read the mode once + restructure the
   no-caja branch.
3. Add the "Continuar sin terminal" `<a>` to `view/tpvmod.html` and
   the `{csrf_field()}` to the existing `d_inicial` form.
4. Manual smoke test (per `openspec/config.yaml` testing section):
   - mode = `with_terminal` (default): existing flow.
   - mode = `without_terminal` + 0 terminals: d_inicial renders
     directly.
   - mode = `without_terminal` + ≥1 terminal: pick screen + new
     button; click → caja(fs_id=0) → ticket → cerrar → clean reload.
5. Static analysis: `ddev exec composer phpstan` (no new errors).
6. Root suites: `ddev exec php vendor/bin/phpunit --testsuite Base`
   and `--testsuite Plugins` (no regression in shared code).

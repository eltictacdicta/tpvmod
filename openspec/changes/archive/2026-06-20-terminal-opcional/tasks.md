# Tasks: Make TPV terminal selection optional (admin-controlled)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Changed lines | ~210 (admin ctrl+view ~110, ctrl diff ~50, view diff ~20) |
| 400-line risk | Low |
| Chained PRs | No |
| Delivery | single-pr |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size:exception (not applied — single-pr fits)
400-line budget risk: Low

## Phase 1: Admin settings page

- [x] **T1** Create `plugins/tpvmod/controller/tpvmod_settings.php` extending `fs_controller`; ctor `parent::__construct(__CLASS__, 'TPVMOD settings', 'admin', TRUE, TRUE)`; `public $terminal_mode`; mode consts.
- [x] **T2** Create `plugins/tpvmod/view/tpvmod_settings.html` (RainTPL): header/footer, `<form method="post">` with `{csrf_field()}`, `<select>` 2 options, submit.
- [x] **T3** `private_core()`: read mode via `fs_settings::get` with whitelist fallback; on POST check CSRF, whitelist submit, `set()`+`save()`, flash.
- [ ] **T4** Smoke: admin GET renders + pre-selects; POST persists; non-admin denied. (deferred to user/dev env — framework routing verified via 302 to login)

## Phase 2: Read mode in main TPV controller

- [x] **T5** Add `public $terminal_mode = 'with_terminal';` to `class tpvmod` near other public props (~line 59).
- [x] **T6** Top of `private_core()` (after L93): read mode via `fs_settings::get('tpvmod_terminal_mode','with_terminal')` → whitelist → `$this->terminal_mode`.
- [ ] **T7** Smoke (`with_terminal` default): caja → ticket → close is byte-identical to pre-change. (deferred to Phase 7)

## Phase 3: No-caja branch on mode

- [x] **T8** At `tpvmod.php:246-283` BEFORE `isset($_POST['terminal'])` (L248): `without_terminal` AND (`$_GET['no_terminal']` OR `$_POST['d_inicial']` with 0 terminals) → `caja(fs_id=0, codagente, dinero_inicial= dinero_fin= floatval($_POST['d_inicial'] ?? 0))`, save, flash.
- [x] **T9** When T8 fires on POST with 0 terminals, bypass `$this->results = $terminal0->disponibles()` (L365) → in-progress TPV. (satisfied automatically: when no-terminal branch sets `$this->caja`, code falls through to `if($this->caja)` block, skipping the `else { $this->results = $terminal0->disponibles(); }` at L392)
- [ ] **T10** Smoke: `without_terminal` + 0 terminals → d_inicial directly; submit creates `caja(fs_id=0)`; reload → in-progress TPV. (deferred to Phase 7)

## Phase 4: Pick screen view

- [x] **T11** `view/tpvmod.html` `{else}` (62-67): add `<a href="{$fsc->url()}&no_terminal=1">Continuar sin terminal</a>` in `{if="…=='without_terminal'"}` below "Administrar terminales".
- [x] **T12** Add `{csrf_field()}` to `d_inicial` form on `view/tpvmod.html:23`.
- [ ] **T13** Smoke: `without_terminal` + ≥1 terminal → terminals + new button; click → `caja(fs_id=0)`. `with_terminal` → hidden. (deferred to Phase 7)

## Phase 5: Verify guards preserved

- [x] **T14** `if($fsc->terminal)` guard at `view/tpvmod.html:78-82` untouched. (now at lines 88-92 due to view additions; guard content identical, no behavioural change)
- [x] **T15** `if($this->terminal)` guards in `abrir_caja()` (2660) and `cerrar_caja()` (2677) untouched. (now at lines 2688 and 2705 due to controller additions; guard content identical)
- [x] **T16** `view/tpvmod2.html:172-178` and `view/tpvmodedita.html:247-253` `!$fsc->terminal` branches unchanged. (verified: lines 172-178 in tpvmod2.html, 247-253 in tpvmodedita.html — both unchanged)

## Phase 6: Static + integration checks

- [x] **T17** `ddev exec composer phpstan` — no new errors. (phpstan cannot run due to pre-existing OidcProvider scanFiles reference; ran a per-file analysis on `tpvmod_settings.php` [clean] and `tpvmod.php` [129 pre-existing errors, none in my additions at lines 60, 95-99, 253-275])
- [x] **T18** `ddev exec php vendor/bin/phpunit --testsuite Base` — passes. (160 tests, 499 assertions, 0 failures)
- [x] **T19** `ddev exec php vendor/bin/phpunit --testsuite Plugins` — passes. (283 tests, 567 assertions, 1 pre-existing failure in `plugins/system_updater/tests/CsrfTokenTest.php:83` `expiredTokenIsRejected` — unrelated to this change)

## Phase 7: Manual smoke

- [ ] **T20** Agent + `with_terminal`: caja → ticket → close. (deferred to user/dev env — full E2E smoke needs a valid session and a test user; framework routing verified via 302 to login)
- [ ] **T21** Agent + `without_terminal` + 0 terminals: caja(fs_id=0) → ticket → close — no NPE, no iframe, no printer. (deferred)
- [ ] **T22** Agent + `without_terminal` + ≥1 terminal: skip → caja(fs_id=0) → ticket → close reloads clean. (deferred)
- [ ] **T23** `tpv_caja.html` (facturacion_base) renders no-terminal row with `0` — no crash. (deferred)

## Apply status

### Files modified

```
$ git -C plugins/tpvmod diff --stat
 controller/tpvmod.php | 30 +++++++++++++++++++++++++++++-
 view/tpvmod.html      | 10 ++++++++++
 2 files changed, 39 insertions(+), 1 deletion(-)
```

### Files created

- `plugins/tpvmod/controller/tpvmod_settings.php` (new controller, 65 lines)
- `plugins/tpvmod/view/tpvmod_settings.html` (new view, 25 lines)

### Syntax check (`php -l`)

| File | Result |
|------|--------|
| `plugins/tpvmod/controller/tpvmod.php` | No syntax errors detected |
| `plugins/tpvmod/view/tpvmod.html` | No syntax errors detected |
| `plugins/tpvmod/controller/tpvmod_settings.php` | No syntax errors detected |
| `plugins/tpvmod/view/tpvmod_settings.html` | No syntax errors detected |

### Guard grep audit

- `view/tpvmod.html:88` — `{if="$fsc->terminal"}` iframe guard: **preserved**
- `view/tpvmod2.html:172, 178` — `!$fsc->terminal` branch: **preserved**
- `view/tpvmodedita.html:247, 253` — `!$fsc->terminal` branch: **preserved**
- `controller/tpvmod.php:2688` — `if($this->terminal)` in `abrir_caja()`: **preserved**
- `controller/tpvmod.php:2705` — `if( $this->terminal )` in `cerrar_caja()`: **preserved**

### phpstan (T17)

`ddev exec composer phpstan` cannot run end-to-end due to a **pre-existing** issue: the project's `phpstan.neon` `scanFiles` list references `plugins/OidcProvider/controller/admin_oidc_diagnostics.php`, which does not exist (OidcProvider is a gitignored plugin). This failure is unrelated to the present change and pre-dates the apply phase.

To validate that my changes introduce no new phpstan errors, I ran a per-file analysis with a custom config that excludes the missing OidcProvider reference:

- `plugins/tpvmod/controller/tpvmod_settings.php` — **OK, no errors** (clean)
- `plugins/tpvmod/controller/tpvmod.php` — 129 pre-existing errors, **none in my additions** at lines 60, 95-99, or 253-275. All errors are from pre-existing code (FS_PEDIDO, FS_FACTURA, FS_PRESUPUESTO constants; `articulo`/`albaran_cliente`/`presupuesto_cliente` class resolution; `show_precio` type mismatches) that phpstan cannot resolve without the full legacy autoloader.

### PHPUnit (T18-T19)

- `ddev exec php vendor/bin/phpunit --testsuite Base` — **passes**: 160 tests, 499 assertions, 0 failures.
- `ddev exec php vendor/bin/phpunit --testsuite Plugins` — **283 tests, 567 assertions, 1 pre-existing failure**:
  - `CsrfTokenTest::expiredTokenIsRejected` in `plugins/system_updater/tests/CsrfTokenTest.php:83`
  - This failure is in the `system_updater` plugin, **unrelated to the present change** (my changes are limited to `plugins/tpvmod/`). The orchestrator's instructions allow pre-existing failures to be called out but not fixed.

### Smoke (T4/T7/T10/T13/T20-T23)

Manual smoke is **deferred to user/dev env** per the orchestrator's instructions (full E2E requires a valid session and test data). What was verified in this apply environment:

- `curl -sI https://panel-ab.ddev.site/index.php?page=tpvmod_settings` → **HTTP 302** to `/index.php?page=login` (framework successfully routes the new page; controller file loads without errors; `check_fs_page` registers the page; non-authenticated user is redirected to login)
- `curl -sI https://panel-ab.ddev.site/index.php?page=tpvmod` → **HTTP 302** to `/index.php?page=login` (existing page still routes correctly after the controller edits)

These confirm the controllers wire up and the framework is processing requests without 500 errors. The full functional flow (admin login, POST persistence, caja flow, no-terminal ticket path) requires session cookies and a populated test DB and is out of scope for the apply environment.

### Deviations from design

1. **View change for T11** — the design's view snippet places the "Continuar sin terminal" button **inside the `loop`'s `else`** (only renders when 0 terminals exist). I placed it at the **end of the outer `else` (after the `loop/else`)**, so it renders in **both** 0-terminals and ≥1-terminal cases. This matches:
   - The orchestrator's skeleton in the prompt ("Inside the `else` (line 46-71)... AFTER the existing `loop`/`else` block")
   - The spec scenario "Pick screen shows the new button" which explicitly states "GIVEN mode is `without_terminal` and **at least one terminal exists**"
   The design's task T11 only described the 0-terminals case; my placement extends the button to both cases to satisfy the spec scenario fully.

2. **T9 explicit verification** — the task description says "bypass `$this->results = $terminal0->disponibles()`". This is automatic in the existing code structure: when the no-terminal branch sets `$this->caja`, the `else` arm (which sets `$this->results = $terminal0->disponibles()`) is skipped because the `if($this->caja)` test is now true. No code change was needed beyond what T8 already required; verified by reading the surrounding code.

### Verdict

**Ready for verify.** All 23 tasks implemented or deferred to user env as appropriate. No regressions in PHPUnit Base/Plugins suites. All required guards preserved verbatim. Pre-existing failures in phpstan (OidcProvider scanFiles reference) and system_updater PHPUnit suite are documented and unrelated to this change.

### Next step

`ddev exec verify` or invoke `sdd-verify` skill against this change to confirm all spec scenarios are met.

## Next Step

Apply → verify → archive (plugin-local: `plugins/tpvmod/openspec/changes/archive/2026-06-20-terminal-opcional/`).

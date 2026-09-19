```yaml
schema: gentle-ai.verify-result/v1
evidence_revision: sha256:9572a13f5be32a0cb53a0246e8b55d44daaaef049f30b7be5c784558f762f86a
verdict: pass_with_warnings
blockers: 0
critical_findings: 0
requirements: 17/17
scenarios: 40/40
test_command: ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
test_exit_code: 0
test_output_hash: sha256:0c06603c042aa9a259615f4dc3773c7f558c6851fe3f70362a9039af8f5b2b3f
build_command: ddev exec php -l plugins/tpvmod/lib/tpvmod_opcionales_ajax.php
build_exit_code: 0
build_output_hash: sha256:7cc369d23a6b702f6fd3f97ac6fa791c740ef0818f6a057ec6a1209f7c9dd817
```

## Verification Report

**Change**: tpvmod-opcional-rapido (plugin `tpvmod`, plugin-local SDD)
**Version**: N/A
**Mode**: Standard (Strict TDD is declared active in `plugins/tpvmod/openspec/config.yaml`; the change followed RED→GREEN per `tasks.md`, but no `strict-tdd-verify.md` run is required for this phase)
**Verifier**: sdd-verify executor (independent, read-only)
**Verification kind**: Focused re-verification after a bounded corrective pass (valid-CSRF success-envelope test + behavior-neutral `$models` seam).

> `evidence_revision` is the sha256 of the ordered concatenation of the
> authoritative evidence basis for this run: `specs/tpv-opcionales/spec.md`,
> `specs/views/spec.md`, `tasks.md`, `lib/tpvmod_opcionales_ajax.php`,
> `tests/TpvmodOpcionalRapidoTest.php`. It is refreshed for this corrective pass.

### Completeness

| Metric | Value |
|--------|-------|
| Tasks total | 35 |
| Tasks complete | 33 |
| Tasks incomplete | 2 (`7.3`, `7.4` — manual browser+DB smoke, annotated pending) |
| Requirements | 12 (`tpv-opcionales`) + 5 (`views`) = 17 |
| Scenarios | 29 (`tpv-opcionales`) + 11 (`views`) = 40 |

### Cor. Re-verification Corrective Pass

| Item | Evidence | Verdict |
|---|---|---|
| WARNING #1 (valid-write success envelope untested) | `TpvmodOpcionalRapidoTest::testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope` (test file line 331) drives `tpvmod_opcionales_ajax_save($ctrl, $models)` with `makeControllerDouble(true)` (valid CSRF) and injected model doubles (`modelsFactory(...)`), then asserts the exact envelope `{ok:true, opcional:{id,codigo,nombre,descripcion,precio,tipo_precio,porcentaje,grupo_id:null}, codfamilia}`, `template=false`, `saveCalls=1`, `codigo='OPC0009'`, relation `add('REF1',12,false)` | ✅ CLOSED |
| New `$models` seam is behavior-neutral | `tpvmod_opcionales_ajax_save(fs_controller $ctrl, ?callable $models = null)` (ajax line 353) forwards `$models` to the pre-existing `persist()` seam; the sole production caller `tpvmod_opcionales_ajax_dispatch()` (line 336) still calls `tpvmod_opcionales_ajax_save($ctrl)` with one argument (line 342). When `$models` is null, `persist()` resolves its unchanged production default factory (`ajax:214`). | ✅ PROVEN |
| Read endpoint, `tpvmod_cliente_ajax_dispatch`, discounts unchanged | `controller/tpvmod.php` diff is still exactly +4 lines (one `require_once` + the 3-line dispatch guard, inserted **after** `tpvmod_cliente_ajax_dispatch()`); `lib/tpvmod_modules.php` (owner of `tpvmod_populate_linea_descuentos()`) has no diff; read endpoint `get_opcionales_articulo()` untouched. | ✅ PROVEN |
| No implementation-behavior file changed beyond the two corrective files | Every other implementation file has an mtime older than the prior report (`verify-report.md` written 12:45:05): `controller/tpvmod.php` 12:26, `lib/tpvmod_opcionales.php` 12:25, `view/js/tpvmod.js` 12:28, both screens 12:27, partial 12:27. Only `tests/TpvmodOpcionalRapidoTest.php` (12:47:53) and `lib/tpvmod_opcionales_ajax.php` (12:48:04) were touched after it. `git -C plugins/tpvmod status --short` lists the same file set as before plus those two. | ✅ PROVEN |
| Test delta | Plugin suite went 109 tests / 481 assertions → **110 tests / 486 assertions** (+1 test, +5 assertions). `TpvmodOpcionalRapidoTest` went 31 → 32 tests (130 assertions). | ✅ PROVEN |

### Build & Tests Execution

**Build**: Passed — `php -l` on all six changed/created PHP files.
```text
No syntax errors detected in plugins/tpvmod/lib/tpvmod_opcionales.php
No syntax errors detected in plugins/tpvmod/lib/tpvmod_opcionales_ajax.php
No syntax errors detected in plugins/tpvmod/controller/tpvmod.php
No syntax errors detected in plugins/tpvmod/tests/TpvmodOpcionalRapidoTest.php
No syntax errors detected in plugins/tpvmod/tests/TpvmodOpcionalesTest.php
No syntax errors detected in plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php
```

**Tests**: 110 passed / 0 failed / 0 skipped (486 assertions).
```text
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.33
Configuration: /var/www/html/plugins/tpvmod/phpunit.xml
...............................................................  63 / 110 ( 57%)
...............................................                 110 / 110 (100%)
Time: 00:00.031, Memory: 6.00 MB
OK (110 tests, 486 assertions)
```
Suite composition: `TpvmodOpcionalRapidoTest` 32 tests / 130 assertions (was 31), `TpvmodOpcionalesTest` 10, `TpvmodTwigTemplatesTest` 10, plus 58 pre-existing plugin tests.
Focused run: `--filter TpvmodOpcionalRapido` → `OK (32 tests, 130 assertions)`.

**Coverage**: Not available — no coverage driver configured for the plugin suite. `➖`

**Static analysis (project linter)**: `ddev exec composer phpstan` is configured but its `paths` are `src` and `tests` only, so `plugins/tpvmod/` is outside its scope. Running it reports 4 pre-existing `method.impossibleType` errors in two **unmodified** core test files (`tests/Base/FsLoginCharacterizationTest.php`, `tests/Base/TrustedSessionRestampTest.php`); none are caused by this change. Recorded as a WARNING, not a gate failure.

### Spec Compliance Matrix

#### Delta spec `tpv-opcionales` (29 scenarios)

| Requirement | Scenario | Evidence | Verdict |
|---|---|---|---|
| Dual modal affordance (OD-10) | Existing selection is preserved | `TpvmodTwigTemplatesTest::testOpcionalesPartialHasTabsListAndSiblingForm` + partial lines 24–31 (`#tpvmod_opcionales_list` + sibling form) | ✅ PROVEN |
| Dual modal affordance (OD-10) | Picking an existing opcional is unchanged | `TpvmodOpcionalesTest::testJsIncludesOpcionalHelpers` (`tpvmod_pick_opcional`); JS diff removes no function and does not touch `tpvmod_pick_opcional` | ⚠️ PARTIAL (grep contract only; no behavioural test of the pick→insert path) |
| Ad-hoc line is session-only | Añadir persists nothing | `tpvmod_add_opcional_ad_hoc` (js:794–819) performs DOM insert only, zero network calls; `TpvmodOpcionalesTest::testJsIncludesQuickCreateOpcionalWiring` asserts the wiring symbols | ✅ PROVEN |
| Ad-hoc line is session-only | Saved without catalog linkage | Ad-hoc row submits `referencia_N=""`, `idlinea_N=-1`, empty `tpvmod_opcional_id_N` (js:261–271); save loop (controller:1377–1423) creates a document line and never writes a catalog relation | ⚠️ PARTIAL (code path proven; end-to-end DB persistence is manual smoke 7.3) |
| Ad-hoc price semantics by tipo (OD-2) | Fixed price passthrough | `TpvmodOpcionalRapidoTest::testBuildAdHocOpcionalFixedPricePassthrough` (12.50 → 12.5) | ✅ PROVEN |
| Ad-hoc price semantics by tipo (OD-2) | Percentage price uses the parent PVP | `TpvmodOpcionalRapidoTest::testBuildAdHocOpcionalPercentageUsesParentPvp` asserts `bround(25.00*10/100)`; JS mirror js:756–758 | ✅ PROVEN |
| Ad-hoc price semantics by tipo (OD-2) | Percentage base is frozen at insert time | JS computes once in `tpvmod_build_ad_hoc_opcional` and writes a static `pvp_N`; `recalcular()` reads `pvp_N` only (js:1373–1391), never the parent PVP | ✅ PROVEN (code) |
| Ad-hoc price semantics by tipo (OD-2) | Invalid input is rejected | `testBuildAdHocOpcionalRejectsEmptyNombre`, `…RejectsNegativeValor`, `…RejectsNonNumericValor`, `testNormalizeOpcionalInputRejectsInvalidValor` | ✅ PROVEN |
| Ad-hoc lines keep client cascading discounts (OD-1) | Discount parity with a catalog line | `lib/tpvmod_modules.php` unmodified (`git diff --name-only` empty); all 12 `tpvmod_populate_linea_descuentos()` call sites unchanged (`git diff controller/tpvmod.php` = +4 lines only); ad-hoc rows submit the same `cantidad_N`/`pvp_N`/`iva_N` fields | ⚠️ PARTIAL (mechanism and non-bypass proven; numeric parity for a discounted client is manual smoke 7.3) |
| Ad-hoc lines are inert for obligatorios and groups (OD-6, F2) | Description match does not satisfy an obligation | Server short-circuit `lib/tpvmod_opcionales.php:444–448`; marker predicate tested; full obligation run needs live `catalogo_core`+DB | ⚠️ PARTIAL (gate code proven; live obligation scenario is manual smoke 7.3) |
| Ad-hoc lines are inert for obligatorios and groups (OD-6, F2) | No participation in an exclusive group | Ad-hoc row carries empty `tpvmod_opcional_grupo_id_N` and empty `data-grupo-id`; client counting (`tpvmod_collect_missing_obligatorios`, js:388–399) only records non-empty ids | ⚠️ PARTIAL (code proven; no runtime test) |
| Ad-hoc lines are inert for obligatorios and groups (OD-6, F2) | Marker drives the server gate | `TpvmodOpcionalRapidoTest::testOpcionalIsAdHocPostReadsMarker` (present/absent/`0`) + placement of the `continue` immediately after the optional-prefix check | ✅ PROVEN |
| Ad-hoc lines are inert for obligatorios and groups (OD-6, F2) | Inertness is scoped to the marker-carrying request (F4) | Documented limitation accepted by the spec; `tpvmodedita.html.twig` renders no `tpvmod_opcional_ad_hoc_*` field (grep absent), so reloaded lines carry no marker and may be reclassified | ✅ PROVEN (matches documented behaviour) |
| Save-and-associate … (OD-4, OD-5, OD-9) | Successful save, association, and auto-add | `testPersistCreatesAndSavesFixedOpcionalWithListaParity` asserts `save()` once, `activo=true`, `id_grupo=null`, relation `add('REF1', 12, false)`; auto-add via `tpvmod_refresh_opcionales_after_save` (js:882–914) | ⚠️ PARTIAL (double-level + code proven; real row/auto-add is manual smoke 7.3) |
| Save-and-associate … (OD-4, OD-5, OD-9) | Codigo collision is retried | `testNextOpcionalCodigoReturnsFirstFreeCandidate`, `testBumpOpcionalCodigoKeepsShape` | ✅ PROVEN |
| Save-and-associate … (OD-4, OD-5, OD-9) | Retry exhaustion is a structured failure | `testPersistRetryExhaustionFailsWithoutSaving` (saveCalls=0, no association, `{ok:false}`) | ✅ PROVEN |
| Association target resolution (OD-3, F8) | Family is available for a family product | `testResolveAsociacionTargetAcceptsFamiliaWithCodfamilia` + `testPersistFamiliaTargetLinksFamilyOnlyWithoutPropagation` | ✅ PROVEN |
| Association target resolution (OD-3, F8) | No family hides the option and rejects direct requests | `testResolveAsociacionTargetRejectsFamiliaWithoutCodfamilia` (server reject) + `tpvmod_update_asociacion_control` (js:779–792) hides/disables and forces `producto` | ✅ PROVEN |
| Association target resolution (OD-3, F8) | Server codfamilia overrides the client flag | `tpvmod_opcionales_ajax_save` (ajax:375–382) re-resolves `tpvmod_articulo_codfamilia($referencia)` and ignores any client-supplied flag (the client does not submit one); covered by the rejection test | ✅ PROVEN |
| Familia association links the family only (OD-7) | No propagation | `testPersistFamiliaTargetLinksFamilyOnlyWithoutPropagation`: `add_familia_only` once with `FAM01`, `add_familia` never, 1 family relation, 0 article relations; `add_familia_only` → `catalogo_opcional_familia::add()` (no propagation) | ✅ PROVEN |
| Reuse existing opcional by normalized nombre (OD-8, F5) | Existing name is reused | `testMatchOpcionalByNombreUsesNormalizedEquality` + `testPersistReusesMatchingOpcionalWithoutSaving` (`saveCalls=0`, reused id 7) | ✅ PROVEN |
| Reuse existing opcional by normalized nombre (OD-8, F5) | Different name creates a new opcional | `testPersistCreatesAndSavesFixedOpcionalWithListaParity` (empty candidates → `save()` called) | ✅ PROVEN |
| Write endpoint is CSRF-gated (read-only stays CSRF-free) | Valid write returns a success envelope | `TpvmodOpcionalRapidoTest::testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope` — valid-CSRF controller double (`makeControllerDouble(true)`) + injected model doubles; asserts exact `{ok:true, opcional:{id:12, codigo:'OPC0009', nombre:'Toallero', descripcion:'Cromo', precio:12.5, tipo_precio:'fijo', porcentaje:null, grupo_id:null}, codfamilia:''}`, `template=false`, `saveCalls=1`, relation `add('REF1', 12, false)` | ✅ PROVEN |
| Write endpoint is CSRF-gated (read-only stays CSRF-free) | Invalid token persists nothing | `testOpcionalesAjaxSaveRejectsInvalidCsrfAndPersistsNothing` asserts exact `{ok:false,errors:['Token CSRF inválido.']}` and `template=false` | ✅ PROVEN |
| Write endpoint is CSRF-gated (read-only stays CSRF-free) | Read endpoint stays CSRF-free | `get_opcionales_articulo()` (controller:604–622) has no `isCsrfValid()` gate; `validateCsrf()` returns `true` for non-POST, so a GET requires no token | ✅ PROVEN (code) |
| User text is escaped end to end (F13) | JS render escapes malicious text | `testJsIncludesQuickCreateOpcionalWiring` asserts `tpvmod_escape_html(desc)`; `tpvmod_escape_html` (js:130–137) escapes `& < > "`; user text is emitted as element content (textarea/`<strong>`), never a single-quoted attribute | ✅ PROVEN |
| User text is escaped end to end (F13) | Model sanitizes persisted text | `catalogo_opcional::test()` applies `no_html()` to `nombre`/`descripcion` (catalog lines 339–341); no DB-free harness exists → smoke-checklist 7.1 | ⚠️ PARTIAL (read-only catalog code proven; live row inspection pending) |
| Catalog parity on the default lista (F7) | Fixed opcional writes a fixed lista row | `testPersistCreatesAndSavesFixedOpcionalWithListaParity` asserts `set_precio_lista('DEF', 12.5)`; catalog `set_precio_lista` sets `precio` and `porcentaje=null` | ✅ PROVEN |
| Catalog parity on the default lista (F7) | Percentage opcional writes a percentage lista row | `testPersistCreatesPercentageOpcionalWithPercentageParity` asserts `set_porcentaje_lista('DEF', 10.0)`; catalog sets `precio=0.0` and `porcentaje` | ✅ PROVEN |

#### Delta spec `views` (11 scenarios)

| Requirement | Scenario | Evidence | Verdict |
|---|---|---|---|
| CSRF rendered via Twig function only | No legacy CSRF workaround anywhere in view/ | `TpvmodTwigTemplatesTest::testEveryPostFormCarriesCsrfField` asserts absence of `{$fsc->csrf_field` and `csrf_field|raw`; repo grep returns none | ✅ PROVEN |
| CSRF rendered via Twig function only | Every POST form contains csrf_field | Same test iterates every `.twig` under `view/` containing `method="post"` and asserts `{{ csrf_field() }}`; 10 files checked | ✅ PROVEN |
| CSRF rendered via Twig function only | Quick-create form carries the token | `testOpcionalesPartialHasTabsListAndSiblingForm` asserts `{{ csrf_field() }}` in the partial; partial line 33 places it inside `<form id="f_opcional_nuevo" method="post">` | ✅ PROVEN |
| Shared opcionales modal partial (OD-10, F9) | Both screens include the partial | `testOpcionalesModalIsSharedByBothScreens` asserts the include in both screens and no inline `id="modal_opcionales"` | ✅ PROVEN |
| Shared opcionales modal partial (OD-10, F9) | Flow is available on both screens | Both screens include the same partial and both load `view/js/tpvmod.js` (grep) | ✅ PROVEN (structure; live flow is manual smoke 7.4) |
| Quick-create form is a sibling of the render target (F1) | Form survives a list re-render | Form lives in a separate tab pane (`#tpvmod_opcional_nuevo`); grep shows zero JS writes to `#tpvmod_opcional_nuevo_form` — all three list writes target `#tpvmod_opcionales_list` only | ✅ PROVEN |
| Quick-create form is a sibling of the render target (F1) | Renderer targets only the list | `testOpcionalesPartialHasTabsListAndSiblingForm` asserts `listPos < formPos` (sibling after the list) + list-only writes above | ✅ PROVEN |
| Quick-create form uses the proven BS3 tab pattern (F10) | Tabs render for existing and new opcionales | Test asserts `nav nav-tabs`, `data-toggle="tab"`, `tab-pane`, and no `data-toggle="collapse"` | ✅ PROVEN |
| Quick-create form uses the proven BS3 tab pattern (F10) | Form fields are present | Test asserts `name="nombre"`, `name="descripcion"`, `name="tipo_precio"`, `name="valor"`, `name="guardar_opcional_tpv"`; partial lines 78–87 expose **Añadir** and **Añadir y guardar opcional** | ✅ PROVEN |
| Twig escaping conventions for user text (F13) | No raw output of user text | Test asserts no `|raw` in the partial; repo grep finds no `nombre|raw`/`descripcion|raw` | ✅ PROVEN |
| Twig escaping conventions for user text (F13) | No user text in single-quoted attributes | The partial contains no single-quoted attributes; user text reaches the DOM only through escaped element content | ✅ PROVEN |

**Compliance summary**: 33/40 scenarios PROVEN, 7/40 PARTIAL, 0 FAILING, 0 UNTESTED.

> **Envelope count semantics**: the envelope reports `scenarios: 40/40` because every one of the 40 spec scenarios has a passing covering test or direct code evidence, and none is FAILING or UNTESTED. `PARTIAL` (7 scenarios) is a depth sub-classification, not an incomplete scenario: each PARTIAL has a passing covering test that proves part of the scenario, and its residual proof is a live browser+DB smoke (tasks 7.3/7.4, explicitly deferred). The valid-CSRF success-envelope scenario is now PROVEN (it was PARTIAL in the prior report — WARNING #1 closed). No scenario is unproven or failing, and no behavioural defect was found.

### High-Risk Guarantee Checks

| Guarantee | Verdict | Evidence |
|---|---|---|
| **OD-1** — ad-hoc lines get normal client discounts; `tpvmod_populate_linea_descuentos()` + the four save loops unchanged | ✅ PROVEN | `git diff --name-only` in the plugin repo lists only `controller/tpvmod.php`, `lib/tpvmod_opcionales.php`, `tests/*`, `view/js/tpvmod.js`, `view/tpvmod2.html.twig`, `view/tpvmodedita.html.twig`. `lib/tpvmod_modules.php` (owner of `tpvmod_populate_linea_descuentos`, line 478) is **not** modified. `git diff controller/tpvmod.php` is exactly +4 lines (one `require_once` + the 3-line dispatch guard); no save loop or discount call site changed. Ad-hoc rows submit the same `cantidad_N`/`pvp_N`/`iva_N`/`irpf_N` fields as catalog lines, so the identical loop applies. |
| **OD-6/F2** — marker is a submitted hidden POST field; `tpvmod_validate_obligatorios_post()` skips description resolution/counting for marker lines | ✅ PROVEN | JS emits `<input type="hidden" name="tpvmod_opcional_ad_hoc_<N>" value="1"/>` (js:256–259). `tpvmod_opcional_is_ad_hoc_post()` reads `$_POST['tpvmod_opcional_ad_hoc_' . $n]` and treats `''`/`'0'` as absent (lib:54–64, tested). The `continue` sits immediately after the optional-prefix check and before any `tpvmod_resolve_opcional_line_metadata()`/counting (lib:440–448). |
| **OD-7** — `add_familia_only()` used; `add_familia()` never called by new code | ✅ PROVEN | `lib/tpvmod_opcionales_ajax.php:296` calls `$opcional->add_familia_only($codfamilia)`. Repo grep for `add_familia(` outside test doubles returns nothing; the only `add_familia(` occurrence is the double definition at `tests/TpvmodOpcionalRapidoTest.php:716` (a spy asserting it is never invoked). `add_familia_only` delegates to `catalogo_opcional_familia::add()` (family row only, no propagation). |
| **OD-5** — created opcional is `activo = true` / `id_grupo = null` | ✅ PROVEN | Set at `ajax:251–252`; mirrored in `tpvmod_normalize_opcional_input()` (`activo => true`, `id_grupo => null`, lib:110–111). Asserted by `testNormalizeOpcionalInputMapsFixedFields`, `…MapsPercentageFields`, `testPersistCreatesAndSavesFixedOpcionalWithListaParity` and the new valid-CSRF test (`grupo_id: null` in the envelope). |
| **OD-3/F8** — `codfamilia` in the read payload, re-resolved server-side; `familia` rejected when empty | ✅ PROVEN | `tpvmod_opcionales_for_articulo()` returns `codfamilia` on both the empty early-return (lib:244) and the full return (lib:301), resolved by `tpvmod_articulo_codfamilia()` (lib:156–172). At save, `ajax:375` re-resolves from the submitted `referencia` and `tpvmod_resolve_asociacion_target()` rejects `familia` when empty (lib:125–131, tested). The client flag is UX-only. |
| **CSRF** — write endpoint gated; read endpoint CSRF-free | ✅ PROVEN | Write: `tpvmod_opcionales_ajax_save()` sets `template=false` then returns `{ok:false, errors:['Token CSRF inválido.']}` before any persistence when `!$ctrl->isCsrfValid()` (`ajax:352–358`), tested by `testOpcionalesAjaxSaveRejectsInvalidCsrfAndPersistsNothing`. Valid-token success path now runtime-tested by `testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope`. Framework `validateCsrf()` runs in `pre_private_core()` before `private_core()`. Read: `get_opcionales_articulo()` has no CSRF gate and is reached via GET. |
| **Seam neutrality** — `$models` is test-only; production call unchanged | ✅ PROVEN | `tpvmod_opcionales_ajax_dispatch()` (ajax:336–345) is the sole production caller and invokes `tpvmod_opcionales_ajax_save($ctrl)` with one argument. `$models` defaults to `null`; `save()` forwards it to `persist()`, which falls back to the unchanged `tpvmod_opcionales_ajax_default_models()` factory (ajax:214–217). No production call site passes the seam. |
| **Escaping** — no `|raw` on user text; `tpvmod_escape_html` used; no user text in single-quoted attributes | ✅ PROVEN | `TpvmodTwigTemplatesTest::testOpcionalesPartialHasTabsListAndSiblingForm` asserts no `|raw` in the partial; `testJsIncludesQuickCreateOpcionalWiring` asserts `tpvmod_escape_html(desc)`. Repo grep finds no `nombre|raw`/`descripcion|raw`. The partial contains no single-quoted attributes; JS emits user text as element content. (Note: `tpvmod_escape_html` does not escape `'`, which is safe only because no single-quoted attribute carries user text — verified.) |
| **F1** — quick-create form is a sibling of `#tpvmod_opcionales_list` | ✅ PROVEN | Partial: `#tpvmod_opcionales_list` (pane 1) and `#tpvmod_opcional_nuevo_form` (pane 2) are separate `.tab-pane` siblings. Test asserts `strpos(list) < strpos(form)`. JS writes `.html(...)` only to `#tpvmod_opcionales_list` (3 sites). |
| **Plugin-local** — no file outside `plugins/tpvmod/` changed | ✅ PROVEN | Plugin repo (`git -C plugins/tpvmod status --short`) lists only files under `plugins/tpvmod/`. Core repo changes are `src/Dinamic/Model/User.php` and `tests/Security/DinamicUserAuthorizationTest.php` — an **unrelated in-flight auth/impersonation change**, not this change. `plugins/catalogo_core/` is clean. Core `openspec/changes/` has **no** `tpvmod*` entry (correct per the plugin-SDD rule). `plugins/tpvmod` is gitignored by the core repo (`git ls-files plugins/tpvmod` empty). |

### Correctness (Static Evidence)

| Requirement | Status | Notes |
|---|---|---|
| Dual modal affordance (OD-10) | ✅ Implemented | Shared partial included by both screens; catalog list preserved. |
| Ad-hoc line is session-only | ✅ Implemented | `Añadir` is a pure DOM insert; no persistence path exists client-side. |
| Ad-hoc price semantics (OD-2) | ✅ Implemented | PHP builder + JS mirror agree (`bround` / `FS_NF0` rounding). |
| Ad-hoc discounts (OD-1) | ✅ Implemented | No discount bypass; identical line fields and save loop. |
| Ad-hoc inertness (OD-6/F2) | ✅ Implemented | Submitted marker + server short-circuit; F4 documented limitation accepted. |
| Save-and-associate (OD-4/5/9) | ✅ Implemented | Ungrouped/active create, `OPC####` with bounded retry, parity, association, auto-add. |
| Association target (OD-3/F8) | ✅ Implemented | Server re-resolution is authoritative; client flag advisory. |
| Familia no propagation (OD-7) | ✅ Implemented | `add_familia_only()` only. |
| Reuse by nombre (OD-8/F5) | ✅ Implemented | Normalized equality over `search()` candidates. |
| CSRF write gate / CSRF-free read | ✅ Implemented | Return-to-dispatcher contract, no `exit`, ordered after the client dispatch. Both the invalid-token and valid-token success branches are runtime-tested. |
| Escaping (F13) | ✅ Implemented | JS `tpvmod_escape_html` + Twig `{{ }}`; model `no_html()` at the catalog boundary. |
| Catalog parity (F7) | ✅ Implemented | Explicit `require_once catalogo_opcional_precio.php` before parity; default lista resolved via `tpvmod_default_lista_precio()`. |

### Coherence (Design)

| Decision | Followed? | Notes |
|---|---|---|
| AD-1 lib-vs-controller split | ✅ Yes | All logic in `lib/tpvmod_opcionales_ajax.php`; controller adds only `require_once` + dispatch. |
| AD-2 return-based dispatch, no `exit`, beside the client dispatch | ✅ Yes | `dispatch()` returns `true`; `emit_json()` has no `exit`; guard inserted after `tpvmod_cliente_ajax_dispatch()`; order asserted by `testControllerDispatchesOpcionalesBesideClienteDispatch`. The trailing `else if` now attaches to the new `if`, which is harmless because the handler returns on match. |
| AD-3 shared partial, sibling form, BS3 tabs | ✅ Yes | Verified in the partial and both screens. |
| AD-4 submitted marker | ✅ Yes | Hidden field + predicate + server short-circuit. |
| AD-5 server-authoritative `codfamilia` | ✅ Yes | Re-resolved at save; client flag ignored. |
| AD-6 reuse by normalized nombre | ✅ Yes | `tpvmod_match_opcional_by_nombre()` over `search()` candidates. |
| AD-7 bounded codigo retry | ✅ Yes | `tpvmod_next_opcional_codigo()` with `maxAttempts=5`, `null` on exhaustion. |
| AD-8 parity on the default lista | ✅ Yes | `set_precio_lista`/`set_porcentaje_lista` + explicit `require_once`. |
| AD-9 pure builder + JS mirror | ✅ Yes | PHP and JS builders share validation and rounding semantics. |
| AD-10 normal cascading discounts | ✅ Yes | No bypass; discount function untouched. |
| AD-11 familia without propagation | ✅ Yes | `add_familia_only()` once; `add_familia` never. |
| AD-12 escaping end to end | ✅ Yes | `tpvmod_escape_html` + Twig `{{ }}` + model `no_html()`. |
| Save/persist split (design precision note) | ✅ Yes | `save()` does gate+normalize+resolve, then delegates to `persist()`, which does not normalize. The optional `$models` seam is forwarded to `persist()` and does not alter the split. |
| Data flow `dispatch → save → persist` | ✅ Yes | Three explicit stages, uncollapsed; the production dispatch reaches `save()` with one argument. |

### Issues Found

**CRITICAL**: None.

**WARNING**
1. **Manual smoke tasks 7.3 and 7.4 are pending** (2 of 35 tasks). These require a live browser+DB and were explicitly annotated as not executable by the apply executor. They are not abandoned implementation work, but they leave 7 scenarios at PARTIAL. Residual manual items are enumerated below with exact steps.
2. **Plugin is outside phpstan scope.** `phpstan.neon` analyses `src` and `tests` only, so the new `lib/` code receives no static analysis. Running the project linter surfaces 4 pre-existing errors in unmodified core test files (not caused by this change).
3. **Verification environment note (not a defect of this change).** The core working tree carries an unrelated in-flight change (`src/Dinamic/Model/User.php` + `tests/Security/DinamicUserAuthorizationTest.php`). It must not be attributed to `tpvmod-opcional-rapido`; the plugin change itself touches only `plugins/tpvmod/`.

**CLOSED since prior report**
- **WARNING #1 — Valid-write success envelope is not runtime-tested.** CLOSED by `TpvmodOpcionalRapidoTest::testOpcionalesAjaxSaveWithValidCsrfEmitsSuccessEnvelope` (valid-CSRF controller double + injected model doubles), which asserts the full `{ok:true, opcional:{...}, codfamilia}` envelope and its side effects. The scenario is now PROVEN.

**SUGGESTION**
1. Consider a plugin-scoped phpstan config (or adding `plugins/tpvmod/lib` to a dedicated analysis path) so plugin `lib/` code is statically checked.
2. `tpvmod_escape_html` intentionally omits `'`; that is safe today because user text never lands in a single-quoted attribute. If future markup introduces one, the escaper should be extended or the attribute should stay double-quoted.
3. The valid-CSRF test asserts `codfamilia: ''` because no DB is available; the non-empty `codfamilia` echo is covered only by manual smoke 7.3. A future DB-backed harness could assert a non-empty value.

### Items Requiring Manual Verification

These are NOT VERIFIED-MANUAL: they need a live browser + DB and cannot be proven by the DB-free plugin suite. Exact steps are in `plugins/tpvmod/openspec/changes/tpvmod-opcional-rapido/smoke-checklist.md`.

- **7.1 — Model sanitization at the catalog boundary** (spec: escaping F13). Save an opcional named `<script>alert(1)</script>`; inspect the `catalogo_opcionales` row for escaped `nombre`/`descripcion`.
- **7.3 — Behavioural flow** (spec: session-only, reuse OD-8, inert OD-6, discounts OD-1, parity F7):
  1. Open `tpvmod2`, add a product line, open "Añadir opcional" → "Nuevo opcional".
  2. **Añadir** with a valid opcional: assert a line is inserted and no `catalogo_opcionales` row / `catalogo_articulo_opcional` relation is created.
  3. **Añadir y guardar opcional** → **Producto**: assert a row with `activo=true`, `id_grupo=null`, codigo `OPC####`, one article relation, and an auto-added line.
  4. Same with **Familia** on a product with `codfamilia`: assert exactly one `catalogo_opcional_familias` relation and zero article relations for the family's articles.
  5. Repeat with the same normalized `nombre`: assert no duplicate row and the existing id is reused.
  6. Product **without** `codfamilia`: assert Familia is hidden/disabled, Producto forced, and a direct `asociacion=familia` POST returns `{ok:false, errors:[...]}`.
  7. Product with an unmet obligatorio + an ad-hoc line whose description matches it: assert the save is blocked on client and server.
  8. Discounted client: assert the ad-hoc line's `dtopor`…`dtopor4` match the client discounts and its total equals the catalog-line computation; entered price stays the pre-discount base.
  9. Percentage line: edit the parent PVP afterwards and assert the ad-hoc price does not change.
  10. Invalid input (empty `nombre`, negative/non-numeric `valor`): assert no line and a validation error.
  11. CSRF: a POST to `guardar_opcional_tpv` without a token returns `{ok:false, errors:['Token CSRF inválido.']}` and persists nothing; `?opcionales_articulo=<ref>&pvp=<n>` stays CSRF-free. (Unit-level valid/invalid token branches are now covered by `TpvmodOpcionalRapidoTest`; this item remains for the live HTTP round-trip against a real DB, including a non-empty `codfamilia` echo.)
- **7.4 — Both screens** (spec: dual affordance OD-10; views delta): repeat the flow on `tpvmod2` and `tpvmodedita` and assert the same modal id, partial and behaviour.

### Verdict

**PASS WITH WARNINGS** — the plugin suite is fully green (110 tests / 486 assertions, exit 0) and every implementation task is complete; all 17 requirements and 33/40 scenarios are proven by passing tests or direct code evidence, with 0 CRITICAL findings. WARNING #1 (valid-write success envelope untested) is closed by the new valid-CSRF test, and the added `$models` seam is behavior-neutral (the sole production caller still uses the one-argument form). The remaining 7 scenarios are PARTIAL solely because their residual proof is a live browser+DB smoke (tasks 7.3/7.4, explicitly deferred). Archive readiness should account for the pending manual smoke.

## Key Learnings

1. The ad-hoc marker must be a submitted POST field; a DOM-only `data-ad-hoc` attribute cannot close the client/server obligatorios divergence because the server reads `$_POST` only.
2. `catalogo_opcional` does not load its price model, so the parity step must `require_once` `catalogo_opcional_precio.php` explicitly before calling `set_precio_lista()`.
3. Ad-hoc rows must reuse the exact same `cantidad_N`/`pvp_N`/`iva_N` fields as catalog lines so `tpvmod_populate_linea_descuentos()` applies client cascading discounts with no bypass.
4. `tpvmod_escape_html` escapes `& < > "` but not `'`; this is safe only while user text never lands in a single-quoted attribute.
5. The project phpstan config analyses only `src` and `tests`, so plugin `lib/` code is not statically checked by `composer phpstan`.
6. A `?callable $models = null` seam keeps the production call signature intact: the dispatcher still calls `save($ctrl)` with one argument, so the test-only injection is behavior-neutral.

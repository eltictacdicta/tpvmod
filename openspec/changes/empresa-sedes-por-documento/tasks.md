# Tasks: empresa-sedes-por-documento

> Cross-plugin SDD owned by `plugins/tpvmod/openspec/` (main beneficiary).
> Scope touches **3 independent git repos**: `plugins/business_data`,
> `plugins/factura_pdf1`, `plugins/tpvmod`. Core (`base/`, `src/`, `controller/`,
> `model/`), core `openspec/`, `plugins/factura_pdf1/openspec/` and a new
> `plugins/business_data/openspec/` are **never** modified (`AGENTS.md` →
> "OpenSpec per Plugin").
> Strict TDD is active (`plugins/tpvmod/openspec/config.yaml` → `strict_tdd: true`):
> every production task is preceded by its RED test.
> Runners: `ddev exec php vendor/bin/phpunit` (root `Plugins` suite covers
> `plugins/business_data/tests/` and `plugins/factura_pdf1/tests/`) and
> `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`.
> All test cases are numbered per the design's 44-case plan.

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | WU-1 ~500–570 · WU-2 ~175 · WU-3 ~250 · WU-4 ~200–230 · **total ~1125–1225 across 3 repos** |
| Session review budget | 800 changed lines |
| 400-line budget risk | **High** |
| Chained PRs recommended | **Yes** |
| Delivery strategy | `ask-on-risk` |
| Chain strategy | **UNRESOLVED** (`pending`) — orchestrator asks the human after this phase |
| Decision needed before apply | **Yes** |
| Suggested split | PR-1=WU-1 (business_data) → PR-2=WU-2 (factura_pdf1) → PR-3=WU-3 (tpvmod) → PR-4=WU-4 (business_data, chained after PR-1) |

Guard contract (exact lines):

```text
Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High
```

- Estimated changed lines: ~1125–1225 total; no slice exceeds 800 alone.
- Chained PRs recommended: Yes
- 400-line budget risk: High
- Decision needed before apply: Yes

Rationale: **a single PR is IMPOSSIBLE.** `plugins/business_data`,
`plugins/factura_pdf1` and `plugins/tpvmod` are three independent git
repositories (each has its own `.git`; the root repo gitignores `plugins/*`), and
no PR can span two of them. On top of that the total change (~1125–1225 lines)
exceeds the 800-line session review budget, so `ask-on-risk` must not be resolved
by "one big PR". The reviewable unit is one repo-slice per session. Version bumps
ship with their own repo's PR (business_data → PR-1; factura_pdf1 → PR-2;
tpvmod → PR-3).

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| WU-1 | business_data `empresa_sede` model + `empresa_sedes` XML + resolver/mapping API + tests | PR-1 | `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede` | `ddev exec php vendor/bin/phpunit --testsuite Plugins`; model instantiation creates `empresa_sedes` lazily | Revert PR-1 commits: `model/empresa_sede.php` + `model/table/empresa_sedes.xml` + 2 tests + version bump. Leaves an inert unused table; no consumer exists yet. |
| WU-2 | factura_pdf1 `load($document, $documentType)` + `resolveEmpresa()` seam + 4 one-line call sites + tests | PR-2 | `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter RelatedModelsLoaderEmpresaSede` | Print a factura with a mapped sede; unmap and print again | Revert PR-2 commits only: `RelatedModelsLoader.php`, 4 print views, new test, version bump. This is the **only** output-affecting slice (incl. the base phone). |
| WU-3 | tpvmod `lib/tpvmod_sede_mapping.php` + `tpvmod_settings` controller/view + tests | PR-3 | `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodSedeMappingSettings` | Open `index.php?page=tpvmod_settings` with `facturacion_base` inactive; save + read back a mapping | Revert PR-3 commits: `lib/tpvmod_sede_mapping.php`, `controller/tpvmod_settings.php`, `view/tpvmod_settings.html.twig`, new test, version bump. Mapping keys stay inert. |
| WU-4 | business_data `admin_empresa` panel: `resolveAction()`/handlers + template nav/panel/JS + new block + dispatch regression test | PR-4 (chained after PR-1) | `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter AdminEmpresaDispatchAction` | Open `index.php?page=admin_empresa#sedes`; create/save/delete a sede and confirm the base row is untouched | Revert PR-4 commits: `controller/admin_empresa.php`, `view/admin_empresa.html.twig`, `view/block/admin_empresa_sedes.html.twig`, new test. WU-1 model stays valid standalone. |

| Work unit | Depends on |
|---|---|
| WU-1 | none |
| WU-2 | WU-1 (public `empresa_sede` API + `resolveForDocumentType`/`withPrintablePhone`) |
| WU-3 | WU-1 (`empresa_sede::mapping()`/`setMappingFor()`) |
| WU-4 | WU-1 (public `empresa_sede` API); chained after PR-1 on the same repo |

## Design precision notes (binding for apply)

These correct six defects found by design validation. Apply MUST use these values,
not the design.md text where they differ.

1. **Sede editable field count is 12, not 11.** The narrowed editable set is
   exactly `descripcion`, `nombre`, `cifnif`, `direccion`, `apartado`,
   `codpostal`, `ciudad`, `provincia`, `codpais`, `email`, `web`, `telefono`;
   plus PK `codsede` = **13 columns** in `empresa_sedes.xml`. `descripcion` MUST
   be persisted and MUST round-trip as the selector label (fallback
   `{{ sede.descripcion ?: sede.nombre }}`). Design test 26's "11 sede field
   names" is WRONG; use the 12-name list and assert the round-trip.
2. **The `tpvmod_settings` `strpos` assertion must target the prefixed call.**
   Design test 43's `strpos('save_sede_mapping') < strpos('terminal_settings_available')`
   is unsatisfiable: `public $terminal_settings_available;` is declared at
   `plugins/tpvmod/controller/tpvmod_settings.php:38`, **before** any new branch.
   Assert against the prefixed CALL `tpvmod_terminal_settings_available(`
   (`:49`) AND assert the prefixed call occurs exactly once (so docblocks cannot
   satisfy the ordering). No behavioural alternative exists: the controller
   extends `fs_controller` and is not instantiable DB-free (AD-14).
3. **JS insertion anchor is `:325`, not `:324`.** In
   `plugins/business_data/view/admin_empresa.html.twig` the `cuentasb` branch
   body ends at `:324` and closes at `} else if (id == 'impresion') {` on `:325`.
   Inserting after `:324` lands **inside** the branch body. Insert the new
   `else if (id == 'sedes')` branch by splitting the line at `:325`, i.e. after
   the cuentasb branch's closing `}`.
4. **`delete_cuenta` POST/GET asymmetry is preserved.** `dispatchAction()`
   dispatches on `filter_input(INPUT_POST, 'delete_cuenta')`
   (`plugins/business_data/controller/admin_empresa.php:260`), but its handler
   `handleDeleteCuenta()` reads `filter_input(INPUT_GET, 'delete_cuenta')`
   (`:317`). Therefore `resolveAction(array $post, array $get)` MUST resolve
   `cuenta_delete` against **`$post`** (legacy dispatch behaviour), while
   `delete_logo` resolves against **`$get`** (`:258`) and `nombre`/`logo`/`iban`
   against `$post`. The handlers' existing `filter_input()` reads stay
   byte-identical (AD-12). Do **not** silently "fix" the legacy asymmetry.
5. **factura_pdf1 test path** is
   `plugins/factura_pdf1/tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php`. The
   real tree has no `tests/Unit/View/`; the existing `RelatedModelsLoaderTest.php`
   lives in `tests/Unit/`. The proposal/exploration paths are stale.
6. **DB-free precondition for the dispatch test.** Including
   `plugins/business_data/controller/admin_empresa.php` in a test process
   requires pre-loading `base/fs_controller.php` first, otherwise
   `Class "fs_controller" not found`. `AdminEmpresaDispatchActionTest::setUp()`
   MUST `require_once` `base/fs_controller.php` (read-only) **before** the
   controller file.

## Spec traceability

| Work unit | Requirements satisfied |
|---|---|
| WU-1 | `empresa-sedes`: Multi-row sede entity and lazy schema · Editable field set narrowed · Mapping storage encapsulated · Canonical vocabulary · Document-type resolution contract · Zero-sede backwards compatibility · Base-company phone becomes printable (model half) |
| WU-2 | `empresa-sedes`: Print integration contract · Zero-sede backwards compatibility (loader half) · Base-company phone becomes printable (loader half) · RuntimeException preserved |
| WU-3 | `tpvmod-config`: Sede mapping section on the settings page · Mapping section independent of the `facturacion_base` gate |
| WU-4 | `empresa-sedes`: Sede save never overwrites the base company · Security of sede flows (admin/CSRF/escaped output in the panel) |

---

## Work Unit 1 — business_data model, schema and resolver (PR-1)

**Files:** `plugins/business_data/model/table/empresa_sedes.xml` (create) · `plugins/business_data/model/empresa_sede.php` (create) · `plugins/business_data/tests/EmpresaSedeModelTest.php` (create) · `plugins/business_data/tests/EmpresaSedeResolutionTest.php` (create) · `plugins/business_data/fsframework.ini` (modify).
**Depends on:** none.
**Verification step:** task 3.4.

### Phase 1 — RED: `EmpresaSedeModelTest` (design cases 1–9) + the 12-field round-trip

- [x] 1.1 Create `plugins/business_data/tests/EmpresaSedeModelTest.php`: namespace `Tests`; `setUp()`/`tearDown()` reset `$GLOBALS['config2']`, `$GLOBALS['plugins']`, `empresa::$empresa_row_cache` (Reflection, `plugins/business_data/model/empresa.php:83` (read-only)) and `fs_model::$checked_tables`; inject an anonymous `fs_db2` spy with the exact `exec($sql, $transaction = null, $params = [], $batch = false)` signature (precedent `plugins/catalogo_core/tests/CaracteristicaModelTest.php` (read-only)).
- [x] 1.2 RED — case 1: hydrate from a full row; every optional key defaults when absent (spec: Generated code is unique).
- [x] 1.3 RED — cases 2 + 3: `test()` rejects a blank `nombre`; `test()` `no_html()`-sanitizes every field with `<script>` gone (spec: Sanitization and validation; threat matrix XSS/write).
- [x] 1.4 RED — case 4 XML inventory: `model/table/empresa_sedes.xml` exists, filename equals the table name, its column set is exactly the **13** columns (`codsede`, `descripcion`, `nombre`, `cifnif`, `direccion`, `apartado`, `codpostal`, `ciudad`, `provincia`, `codpais`, `email`, `web`, `telefono`), and `fax`/`lema`/`pie_factura`/`horario`/`nombrecorto` are absent (spec: No field without a PDF consumer; `fs_model.php:508`,`:524` (read-only)). Strengthened post-verify with the DB-free structural contract test (`testSchemaXmlIsWellFormedAndMatchesTheFrameworkAndEmpresaTypes`).
- [x] 1.5 RED — case 5: `save()` on a new row with empty `codsede` → spy `MAX` row → `INSERT` contains the generated code and `sql_to_int('codsede')` was used (spec: Generated code is unique; threat matrix SQL injection). Strengthened post-verify with sequential uniqueness across two successive saves.
- [x] 1.6 RED — case 6: `save()` on an existing row emits `UPDATE`; `delete()` emits `DELETE … WHERE codsede = '3'` via `var2str()` (threat matrix SQL injection).
- [x] 1.7 RED — cases 7 + 8 + 9: `all()` hydrates N rows with `ORDER BY descripcion ASC, codsede ASC`; `url()` points at `admin_empresa` with the `#sedes` fragment; `install()` returns `''` (no migration code). Case 7 renamed post-verify to state honestly that only the emitted ORDER BY clause and the hydration of every returned row are provable DB-free.
- [x] 1.8 RED — **12-field contract** (correction 1): the editable field set returned by the entity is exactly the 12 names `descripcion`, `nombre`, `cifnif`, `direccion`, `apartado`, `codpostal`, `ciudad`, `provincia`, `codpais`, `email`, `web`, `telefono`; `save()` then hydration round-trips `descripcion` verbatim (spec: Multi-row sede entity — `descripcion` renders as the selector label). Made a real round-trip post-verify: parse the emitted INSERT, re-hydrate from that row, assert all 13 fields verbatim.

### Phase 2 — RED: `EmpresaSedeResolutionTest` (design cases 10–21)

- [x] 2.1 Create `plugins/business_data/tests/EmpresaSedeResolutionTest.php` with the same resets and an injected `$sedeLoader` spy (the only test seam); RED — cases 10–13: unknown tipo → `null` **and the loader is never called**; absent key → `null`; empty key (`''`) → `null`; dangling code → `null` (spec: Every null case; threat matrix — no DB access on the early-return path).
- [x] 2.2 RED — cases 14–17: a mapped sede yields `instanceof \empresa` with the sede fields and the spy DB recorded no `INSERT`/`UPDATE` on `empresa`; `toEmpresa()` non-empty sede field wins and an empty field inherits `$base` without mutating `$base`; `toEmpresa()` sets `telefono1 === sede telefono`; `withPrintablePhone()` sets `telefono1` from `telefono`, returns the **same instance**, and does not overwrite a non-empty value (spec: Mapped sede is hydrated transiently; Field merge and phone mapping; Base phone; No persistence side effect).
- [x] 2.3 RED — cases 18–20: `setMappingFor()` unknown tipo → `false`; invalid code → `false` and `$GLOBALS['config2']` unchanged; `setMappingFor('factura', null)` → the key is `''` and `mapping()['factura'] === null`; round trip `setMappingFor('factura','S1')` → `mapping()['factura'] === 'S1'` with `array_keys(mapping())` exactly the 4 canonical types `presupuesto|albaran|pedido|factura` (spec: Invalid codsede rejected; Empty selection clears; Unknown type is not accepted; Canonical vocabulary).
- [x] 2.4 RED — case 21: `resolveForDocumentType()` returns `null` for each of the four null cases while the base identity stays untouched (spec: Every null case; Identity is preserved — model level). Post-verify: the tautological `assertSame($base, $adopt(...))` was removed and the test renamed to what it truly proves; the `assertSame` identity contract belongs to WU-2.
- [ ] 2.5 Run `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede`; confirm RED (class/functions undefined). **NOT EVIDENCED**: WU-1 landed as the single commit `f68afbb0`, so no failing run was captured. See `apply-progress.md` → TDD Cycle Evidence.

### Phase 3 — GREEN, REFACTOR, release (PR-1)

- [x] 3.1 Create `plugins/business_data/model/table/empresa_sedes.xml`: the 13 columns of note 1, types copied from `plugins/business_data/model/table/empresa.xml` (read-only), constraint `empresa_sedes_pkey` → `PRIMARY KEY (codsede)`.
- [x] 3.2 Create `plugins/business_data/model/empresa_sede.php` (`extends \fs_model`; table `empresa_sedes`): `__construct`, `test()`, `save()`, `delete()`, `exists()`, `all()`, `get()`, `get_new_codigo()` (`MAX($this->db->sql_to_int('codsede')) + 1`), `url()`, `toEmpresa(\empresa $base)` (clone + overlay + `telefono1`), `mapping()`, `setMappingFor(string $tipo, ?string $codsede, ?callable $sedeLoader = null)`, `resolveForDocumentType(string $tipo, \empresa $base, ?callable $sedeLoader = null)`, `withPrintablePhone(\empresa $empresa)`, and `private const TIPOS` with the 4 private `fs_settings` keys (spec: all WU-1 requirements; AD-2/3/4/6/7).
- [x] 3.3 Run the focused filter; all WU-1 RED tests green.
- [x] 3.4 **WU-1 verification step (PR-1 gate):** run `ddev exec php vendor/bin/phpunit` — the root `Plugins` suite must stay green with no regressions (root `phpunit.xml` sets `failOnWarning="true"`/`failOnRisky="true"`). **Verified with a documented pre-existing condition**: WU-1 introduces no regression (`plugins/business_data/tests/` green), but the root suite carries **20 pre-existing `Tests\OidcProvider\...` failures** — reproduced with no WU-1 file loaded. See `apply-progress.md` → Pre-existing failures.
- [x] 3.5 **REFACTOR:** review `empresa_sede.php` for duplication and naming only; no behaviour change, then re-run task 3.3.
- [x] 3.6 Bump `version = 1.0.4` → `version = 1.1.0` in `plugins/business_data/fsframework.ini`.
- [x] 3.7 **No new Composer dependency** (repo-wide constraint): this change adds none, so do **not** run `composer install/update`, do **not** touch `composer.json`/`composer.lock`/`vendor/`, and the plugin `vendor/` commit rule does **not** apply.
- [x] 3.8 Commit WU-1 in the `plugins/business_data` repo (`feat(empresa-sede): add multi-row sede model, schema and print resolver`), staging only the files listed for WU-1. Landed as `f68afbb0` on `feat/empresa-sedes`; post-verify coverage hardening landed as `83be16bd` on the same branch.

---

## Work Unit 2 — factura_pdf1 print integration (PR-2)

**Files:** `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php` (modify) · `plugins/factura_pdf1/Model/View/AlbaranPrintView.php` (modify) · `plugins/factura_pdf1/Model/View/PedidoPrintView.php` (modify) · `plugins/factura_pdf1/Model/View/PresupuestoPrintView.php` (modify) · `plugins/factura_pdf1/Model/View/FacturaPrintView.php` (modify) · `plugins/factura_pdf1/tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` (create) · `plugins/factura_pdf1/fsframework.ini` (modify).
**Depends on:** WU-1 (public `empresa_sede` API).
**Verification step:** task 5.5.

### Phase 4 — RED: `RelatedModelsLoaderEmpresaSedeTest` (design cases 27–35)

- [ ] 4.1 Create `plugins/factura_pdf1/tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` (correction 5 path; precedent `plugins/factura_pdf1/tests/Unit/RelatedModelsLoaderTest.php` (read-only)); reset `$GLOBALS['config2']`, `$GLOBALS['plugins']` and `empresa::$empresa_row_cache` in `setUp()`; require `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php` and the business_data model by path.
- [ ] 4.2 RED — case 27: base `false` → `\RuntimeException('Empresa no configurada.')`, even with a mapping key present (spec: RuntimeException preserved).
- [ ] 4.3 RED — cases 28 + 29: `resolveEmpresa($base, null)` → `assertSame($base, …)` and `telefono1 === $base->telefono`; `resolveEmpresa($base, 'factura_simplificada')` (unknown literal) → `assertSame($base, …)` (spec: Unmapped or no-type call keeps base identity; Base phone is printed; Canonical vocabulary).
- [ ] 4.4 RED — cases 30 + 31: a mapped sede (loader stub) returns an object carrying the sede `nombre`, `codpais` and `telefono1` while `$base` is unchanged; a mapping present but dangling → `assertSame($base, …)` (spec: Sede wins and its country resolves; Mapped sede transient; Field merge and phone mapping).
- [ ] 4.5 RED — case 32: `resolveEmpresa(...)->codpais === 'ESP'` from the sede **and** the source order `strpos($src, 'self::resolveEmpresa(') < strpos($src, '$codpais =')` proves the override precedes `:65` (spec: Sede wins and its country resolves).
- [ ] 4.6 RED — cases 33 + 34 + 35: Reflection asserts `load()` parameter 2 is named `documentType`, optional, default `null`, and the file still contains `RuntimeException('Empresa no configurada.')`; `empresa::$empresa_row_cache` is unchanged across `resolveEmpresa()`; `resolveEmpresa()` issues no `INSERT`/`UPDATE` on the spy DB (spec: Print integration contract; No persistence side effect).
- [ ] 4.7 Run `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml --filter RelatedModelsLoaderEmpresaSede`; confirm RED.

### Phase 5 — GREEN, REFACTOR, release (PR-2)

- [ ] 5.1 Modify `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php`: change the signature to `load(object $document, ?string $documentType = null): array`; replace `:42-45` with `$empresa = self::resolveEmpresa((new \empresa())->get(), $documentType);`; add `public static function resolveEmpresa(\empresa|false $base, ?string $documentType): \empresa` between `load()` and `requireRelatedModels()`; add the guarded `empresa_sede` `require_once` in `requireRelatedModels()` next to the existing `empresa` require (`:82-84`) (spec: Print integration contract; AD-13).
- [ ] 5.2 Pass the canonical literal at each of the 4 call sites: `plugins/factura_pdf1/Model/View/AlbaranPrintView.php:181` → `'albaran'`; `plugins/factura_pdf1/Model/View/PedidoPrintView.php:181` → `'pedido'`; `plugins/factura_pdf1/Model/View/PresupuestoPrintView.php:181` → `'presupuesto'`; `plugins/factura_pdf1/Model/View/FacturaPrintView.php:219` → `'factura'` (spec: Canonical vocabulary).
- [ ] 5.3 Run the focused filter; all WU-2 RED tests green.
- [ ] 5.4 **REFACTOR:** keep `ClientDocumentPrintViewInterface::getEmpresa(): object`, the `instanceof \empresa` guard and `plugins/factura_pdf1/Model/View/PortedPdfDocument.php` (read-only) untouched; re-run task 5.3.
- [ ] 5.5 **WU-2 verification step (PR-2 gate):** run `ddev exec php vendor/bin/phpunit` (root `Plugins` suite) and `ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml`; no regressions.
- [ ] 5.6 Bump `version = 1.0.6` → `version = 1.1.0` in `plugins/factura_pdf1/fsframework.ini`.
- [ ] 5.7 Confirm no new Composer dependency in this repo: `composer.json`/`composer.lock`/`vendor/` untouched; do not run composer.
- [ ] 5.8 Commit WU-2 in the `plugins/factura_pdf1` repo (`feat(print): resolve empresa sede by document type`), staging only the files listed for WU-2.

---

## Work Unit 3 — tpvmod mapping settings (PR-3)

**Files:** `plugins/tpvmod/lib/tpvmod_sede_mapping.php` (create) · `plugins/tpvmod/controller/tpvmod_settings.php` (modify) · `plugins/tpvmod/view/tpvmod_settings.html.twig` (modify) · `plugins/tpvmod/tests/TpvmodSedeMappingSettingsTest.php` (create) · `plugins/tpvmod/fsframework.ini` (modify).
**Depends on:** WU-1 (`empresa_sede::mapping()` / `setMappingFor()`).
**Verification step:** task 7.6.

### Phase 6 — RED: `TpvmodSedeMappingSettingsTest` (design cases 36–44)

- [ ] 6.1 Create `plugins/tpvmod/tests/TpvmodSedeMappingSettingsTest.php` (namespace `Tests\Tpvmod`; precedent `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (read-only)); `setUp()` requires `plugins/tpvmod/lib/tpvmod_sede_mapping.php` and resets `$GLOBALS['config2']` and `$GLOBALS['plugins']`; RED — case 36: `tpvmod_sede_mapping_submitted(['save_sede_mapping' => '1']) === true`, `[] === false`.
- [ ] 6.2 RED — case 37: `tpvmod_normalize_sede_mapping()` with `sede_factura='S1'` and the other three absent/`''` → `['presupuesto'=>null,'albaran'=>null,'pedido'=>null,'factura'=>'S1']`.
- [ ] 6.3 RED — cases 38 + 40: round trip through the injected `$setMappingFor` spy — exactly 4 calls with the exact `(tipo, codsede)` pairs and `ok === true`; `sede_factura=''` → `setMappingFor('factura', null)` and `ok === true` (spec: Round-trip read and write; Empty selection clears the override).
- [ ] 6.4 RED — cases 39 + 41: an invalid code with `$sedeExists` returning `false` → `ok === false`, `saved === []` and `$setMappingFor` **never called**; `$setMappingFor` returning `false` → `ok === false` plus an error message (all-or-nothing semantics) (spec: Invalid codsede is rejected).
- [ ] 6.5 RED — case 42 view contract (grep style): `plugins/tpvmod/view/tpvmod_settings.html.twig` contains `{{ csrf_field() }}`, a hidden `save_sede_mapping`, the 4 select names, an explicit empty option ("no override / base company"), and its mapping block starts **after** the terminal gate `{% endif %}` at `:46` (spec: Mapping works with facturacion_base inactive).
- [ ] 6.6 RED — cases 43 + 44: controller contract — `strpos($src, 'save_sede_mapping') < strpos($src, 'tpvmod_terminal_settings_available(')` **and** `substr_count($src, 'tpvmod_terminal_settings_available(') === 1` (correction 2: the broken unprefixed assertion is replaced), plus `isCsrfValid()` present inside the mapping path; no `|raw` on `sede.descripcion` and the template renders `{{ sede.descripcion }}` escaped (spec: CSRF and admin gate; Escaped labels).
- [ ] 6.7 Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml --filter TpvmodSedeMappingSettings`; confirm RED.

### Phase 7 — GREEN, REFACTOR, release (PR-3)

- [ ] 7.1 Create `plugins/tpvmod/lib/tpvmod_sede_mapping.php`: `tpvmod_sede_mapping_submitted(array $post)`, `tpvmod_normalize_sede_mapping(array $post)`, `tpvmod_save_sede_mapping(array $post, ?callable $setMappingFor = null, ?callable $sedeExists = null)` returning `['ok' => bool, 'errors' => list<string>, 'saved' => list<string>]` (spec: Sede mapping section; AD-14).
- [ ] 7.2 Modify `plugins/tpvmod/controller/tpvmod_settings.php`: guarded `empresa_sede` `require_once`; `$sedes` and `$sede_mapping` properties populated from `empresa_sede::mapping()`/`all()`; add the POST branch **before** the terminal gate with its own `isCsrfValid()` check and `saveSedeMapping()`; the existing terminal flow stays byte-identical (spec: Mapping section independent of the `facturacion_base` gate).
- [ ] 7.3 Modify `plugins/tpvmod/view/tpvmod_settings.html.twig`: add the mapping section after the terminal gate `{% endif %}` at `:46`, outside the terminal `{% if %}`; all labels through `{{ }}` (spec: Escaped labels; mapping works with `facturacion_base` inactive).
- [ ] 7.4 Run the focused filter; all WU-3 RED tests green.
- [ ] 7.5 **REFACTOR:** `lib/` helpers only; no behaviour change; re-run task 7.4.
- [ ] 7.6 **WU-3 verification step (PR-3 gate):** run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`; green. Also confirm the mapping section renders with `facturacion_base` absent from `$GLOBALS['plugins']`.
- [ ] 7.7 Bump `version = 2.1.0` → `version = 2.2.0` in `plugins/tpvmod/fsframework.ini`.
- [ ] 7.8 Confirm no new Composer dependency in `plugins/tpvmod` (`composer.json`/`composer.lock`/`vendor/` untouched; do not run composer).
- [ ] 7.9 Commit WU-3 in the `plugins/tpvmod` repo (`feat(settings): add empresa sede to document-type mapping UI`), staging only the files listed for WU-3.

---

## Work Unit 4 — business_data admin panel (PR-4, chained after PR-1)

**Files:** `plugins/business_data/controller/admin_empresa.php` (modify) · `plugins/business_data/view/admin_empresa.html.twig` (modify) · `plugins/business_data/view/block/admin_empresa_sedes.html.twig` (create) · `plugins/business_data/tests/AdminEmpresaDispatchActionTest.php` (create).
**Depends on:** WU-1 (public `empresa_sede` API); chained after PR-1 on the same repo.
**Verification step:** task 9.6.

### Phase 8 — RED: `AdminEmpresaDispatchActionTest` (design cases 22–26) + panel contracts

- [ ] 8.1 Create `plugins/business_data/tests/AdminEmpresaDispatchActionTest.php`: `setUp()` requires `base/fs_controller.php` (read-only) **before** `plugins/business_data/controller/admin_empresa.php` (correction 6: otherwise `Class "fs_controller" not found`); resets `$GLOBALS['config2']`, `$GLOBALS['plugins']`, `empresa::$empresa_row_cache` (Reflection) and `fs_model::$checked_tables`.
- [ ] 8.2 RED — case 22 (highest-severity collision regression): `resolveAction(['save_sede' => '1', 'nombre' => 'Sede X'], []) === 'sede_save'` (spec: Sede form does not touch the base row; Reserved order under regression).
- [ ] 8.3 RED — case 23: `resolveAction(['delete_sede' => '3', 'nombre' => 'X'], []) === 'sede_delete'`.
- [ ] 8.4 RED — case 24: a full sede POST (all 12 field names + `save_sede`) resolves to `'sede_save'`, proving `handleEmpresaSave()` is unreachable on that path and the base row stays byte-identical.
- [ ] 8.5 RED — case 25 existing branches unchanged, **including the correction-4 asymmetry**: `nombre` → `'empresa'`; `logo` → `'logo'`; `delete_logo` in `$get` → `'delete_logo'`; `delete_cuenta` in **`$post`** → `'cuenta_delete'` and `delete_cuenta` in `$get` only → `'none'`; `iban` → `'cuenta_save'`; empty `$post`/`$get` → `'none'`.
- [ ] 8.6 RED — case 26 (correction 1): `sedeFieldsFromPost()` returns exactly the **12** sede field names (`descripcion`, `nombre`, `cifnif`, `direccion`, `apartado`, `codpostal`, `ciudad`, `provincia`, `codpais`, `email`, `web`, `telefono`) and none of `contintegrada`, `codalmacen`, `codserie`, `fax`, `lema`, `pie_factura`, `horario`, `nombrecorto`.
- [ ] 8.7 RED — view contracts (grep style, precedent `plugins/tpvmod/tests/TpvmodTwigTemplatesTest.php` (read-only)): `plugins/business_data/view/admin_empresa.html.twig` contains `<li id="b_sedes">` after the `b_cuentasb` `</li>` (`:35`), `<div id="panel_sedes">` after `panel_cuentasb`'s `</div>` and **outside** `</form>` (`:194`), the `block/admin_empresa_sedes.html.twig` include, the `comprobar_url()` hash branch for `sedes`, `$("#panel_sedes").hide()` after `:310`, `$("#b_sedes").removeClass('active')` after `:315`, and the new `else if (id == 'sedes')` branch anchored at the `:325` closure (correction 3); the 5 existing panel ids and their branches are unchanged.
- [ ] 8.8 RED — block contract: `plugins/business_data/view/block/admin_empresa_sedes.html.twig` contains `{{ csrf_field() }}`, the `save_sede` and `delete_sede` markers, **no** `onclick="this.disabled` pattern (AD-10 trap: a disabled submit drops its marker) and no `|raw` on user text (spec: Security of sede flows; threat matrix XSS/render).
- [ ] 8.9 Run `ddev exec php vendor/bin/phpunit --testsuite Plugins --filter AdminEmpresaDispatchAction`; confirm RED.

### Phase 9 — GREEN, REFACTOR, release (PR-4)

- [ ] 9.1 Modify `plugins/business_data/controller/admin_empresa.php`: guarded `require_once` of `model/empresa_sede.php`; add `public $empresa_sede;` and `public $sedes = [];`; instantiate the entity in `initializeModels()`; add `loadSedes()` after `dispatchAction()`; add `public static function resolveAction(array $post, array $get): string` returning `sede_save|sede_delete|empresa|logo|delete_logo|cuenta_delete|cuenta_save|none` with `sede_save`/`sede_delete` tested **before** `nombre` and `cuenta_delete` resolved against `$post` (corrections 2/4; AD-9/AD-12); rewrite `dispatchAction()` to `switch` on it; add `handleSaveSede()`, `handleDeleteSede()` and `sedeFieldsFromPost(array $post)` — `$this->empresa` is never written by a sede handler.
- [ ] 9.2 Modify `plugins/business_data/view/admin_empresa.html.twig` at exactly the 4 edit sites (nav `:35`, panel `:197`, `comprobar_url()` `:294-306`, `mostrar_seccion()` `:307-337`) using the correction-3 anchor; no other line changes.
- [ ] 9.3 Create `plugins/business_data/view/block/admin_empresa_sedes.html.twig`: one `<form>` per sede with its own `{{ csrf_field() }}` and the two markers `name="save_sede" value="1"` / `name="delete_sede" value="{{ codsede }}"`, plus an always-visible create form (never a Bootstrap modal — AD-8 trap) and an empty state; selector label `{{ sede.descripcion ?: sede.nombre }}` escaped.
- [ ] 9.4 Run the focused filter; all WU-4 RED tests green.
- [ ] 9.5 **REFACTOR:** re-read `resolveAction()`/`dispatchAction()` for clarity only and re-run task 9.4.
- [ ] 9.6 **WU-4 verification step (PR-4 gate):** run `ddev exec php vendor/bin/phpunit` (root `Plugins` suite); green. Confirm the base company row is byte-identical after a sede POST.
- [ ] 9.7 Commit WU-4 in the `plugins/business_data` repo (`feat(admin-empresa): add empresas sedes panel`), staging only the files listed for WU-4.

---

## Phase 10 — Cross-repo verification and release guard

- [ ] 10.1 Run `ddev exec php vendor/bin/phpunit` at the repo root; the `Plugins` suite covers `plugins/business_data/tests/` and `plugins/factura_pdf1/tests/` and must be green.
- [ ] 10.2 Run `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`; green.
- [ ] 10.3 Confirmation audit: no `vendor/`, `composer.json` or `composer.lock` change in any of the 3 repos (no new Composer dependency anywhere ⇒ the plugin `vendor/` commit rule does not apply).
- [ ] 10.4 Isolation audit: `git status` shows zero changes in `base/`, `src/`, `controller/`, `model/`, core `openspec/`, `plugins/factura_pdf1/openspec/`, and no new `plugins/business_data/openspec/`.
- [ ] 10.5 Verify the 3 version bumps: `plugins/business_data/fsframework.ini` 1.1.0, `plugins/factura_pdf1/fsframework.ini` 1.1.0, `plugins/tpvmod/fsframework.ini` 2.2.0.
- [ ] 10.6 Manual smoke (not executable DB-free by apply): create a sede → map it to `factura` → print a factura (sede header incl. phone, `pais` from the sede `codpais`) → unmap and print again (base header with the base phone) → save a sede and confirm the base company row is unchanged.
- [ ] 10.7 Confirm the outstanding product decision before apply: the base-company phone becomes printed for every existing install (AD-4) — the single documented exception to byte-identical output.

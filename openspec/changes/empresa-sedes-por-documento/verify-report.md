# Verify Report: empresa-sedes-por-documento

**Date:** 2026-09-22
**Verifier:** independent SDD verify phase (read-only; no source modified)
**Overall verdict:** **PASS WITH WARNINGS**

**Per work unit**

| WU | Repo / branch / commit | Verdict |
|----|------------------------|---------|
| WU-1 model + schema + resolver + mapping API | `plugins/business_data` · `feat/empresa-sedes` · `f68afbb0` + `83be16bd` | **PASS WITH WARNINGS** |
| WU-2 print resolution | `plugins/factura_pdf1` · `feat/empresa-sedes` · `e89efdf` | **PASS WITH WARNINGS** |
| WU-3 mapping UI | `plugins/tpvmod` · `feat/empresa-sedes` · `fa4dc92` | **PASS WITH WARNINGS** |
| WU-4 admin panel + dispatch seam | `plugins/business_data` · `feat/empresa-sedes-panel` · `51eb6401` | **PASS WITH WARNINGS** |

No CRITICAL finding. No requirement is MISSING. Four scenarios are PARTIAL, and each
of the four corresponds to a gap the apply records had already declared honestly.
The implementation matches the spec and the design on every point that a DB-free
runtime can prove; the residual risk is confined to real-DB / real-HTTP surfaces
(tasks `10.6`, `10.7`) and two documentation inaccuracies.

---

## 1. Requirement coverage

Counted from the retrieved deltas: `specs/empresa-sedes/spec.md` **10 requirements /
20 scenarios** + `specs/tpvmod-config/spec.md` **2 requirements / 6 scenarios** =
**12 requirements / 26 scenarios**.

| Requirement | Scenario | Status | Concrete proof |
|---|---|---|---|
| **R1** Multi-row sede entity and lazy schema | First instantiation creates the table | **PARTIAL** | `empresa_sede extends fs_model` (`model/empresa_sede.php:28`) + `model/table/empresa_sedes.xml` (13 cols, PK `empresa_sedes_pkey`). `testInstallReturnsEmptyString` proves no migration code; `testSchemaXmlIsWellFormedAndMatchesTheFrameworkAndEmpresaTypes` proves the XML the framework feeds to DDL generation. **No test executes the real lazy `CREATE TABLE`** (`fs_model::check_table()` at `base/fs_model.php:277`) — declared gap (a), deferred to manual smoke 10.6 |
| | Generated code is unique and identifies the sede | **IMPLEMENTED** | `testSaveNewRowGeneratesMaxPlusOneAndInserts`, `testTwoSuccessiveSavesProduceTwoDifferentNonEmptyCodes`, `testSaveThenHydrationRoundTripsThePersistedRowVerbatim` (`EmpresaSedeModelTest.php`) |
| **R2** Editable field set narrowed | No field without a PDF consumer | **IMPLEMENTED** | `testEditableFieldSetIsExactlyTheTwelveNames`, `testXmlDeclaresExactlyTheNarrowedColumns`; `EDITABLE_FIELDS` (`empresa_sede.php:41-54`) exactly the 12 names, `fax/lema/pie_factura/horario/nombrecorto` absent |
| | Sanitization and validation | **IMPLEMENTED** | `testTestRejectsBlankNombre`, `testTestSanitizesEveryEditableField`; `test()` `no_html()`s every field and rejects blank `nombre` (`empresa_sede.php:150-168`) |
| **R3** Mapping storage encapsulated | Invalid `codsede` is rejected | **IMPLEMENTED** | `testSetMappingForInvalidCodsedeReturnsFalseAndLeavesTheStoreUntouched`; `setMappingFor()` returns `false` before writing (`empresa_sede.php:306-308`) |
| | Empty selection clears the override | **IMPLEMENTED** | `testSetMappingForNullClearsTheOverride`; key stored as `''`, `mapping()` reports `null` (`:305-313`, `:293`) |
| **R4** Canonical document-type vocabulary | Unknown type is not accepted | **IMPLEMENTED** | `testSetMappingForUnknownTipoReturnsFalse`, `testUnknownTipoReturnsNullAndNeverCallsTheLoader`, factura `testUnknownDocumentTypeKeepsBaseIdentity`; only `presupuesto\|albaran\|pedido\|factura` in `TIPOS` (`:63-68`) |
| **R5** Document-type resolution contract | Mapped sede is hydrated transiently | **IMPLEMENTED** | `testMappedSedeIsHydratedTransientlyWithoutPersistence` — `instanceof \empresa`, spy DB records zero writes, base unmodified |
| | Every null case | **IMPLEMENTED** | `testUnknownTipoReturnsNullAndNeverCallsTheLoader`, `testAbsentMappingKey...`, `testEmptyMappingKey...`, `testDanglingCodsedeReturnsNull`, `testResolveForDocumentTypeReturnsNullForEveryNullCaseWithoutMutatingTheBase` |
| | Field merge and phone mapping | **IMPLEMENTED** | `testToEmpresaMergesFieldsAndKeepsTheBaseUnmodified` (sede wins / base inherits / `assertNotSame`), `testWithPrintablePhone...` |
| **R6** Print integration contract | Sede wins and its country resolves | **IMPLEMENTED** | `testResolvedSedeCountryIsExposedBeforeTheCodpaisDerivation` (asserts `codpais === 'ESP'` **and** `strpos('self::resolveEmpresa(') < strpos('$codpais =')`) |
| | Unmapped or no-type call keeps base identity | **IMPLEMENTED** | `testNullDocumentTypeKeepsBaseIdentityAndPrintsTheBasePhone`, `testUnmappedTypeKeepsBaseIdentityAndPrintsTheBasePhone`, `testDanglingMappingKeepsTheBaseIdentity` — all `assertSame($base, $out)` |
| | `RuntimeException` preserved | **IMPLEMENTED** | `testMissingBaseCompanyStillThrowsEvenWhenASedeIsMapped`; `RelatedModelsLoader.php:106-108` |
| **R7** Zero-sede backwards compatibility | Identity is preserved | **IMPLEMENTED** | `assertSame($base, $out)` (factura tests above) + `resolveEmpresa()` returns `withPrintablePhone($base)` which returns the **same instance** (`:118-124`) |
| **R8** Base-company phone becomes printable | Base phone is printed | **IMPLEMENTED** | `testNullDocumentTypeKeepsBaseIdentityAndPrintsTheBasePhone` (`telefono1 === '600999'`); `PortedPdfDocument.php:545` reads `telefono1` |
| | No persistence side effect | **IMPLEMENTED** | `testResolveEmpresaIssuesNoDatabaseWrites`, `testResolvingASedeDoesNotPopulateOrAlterTheEmpresaRowCache`, `testMappedSedeIsHydratedTransientlyWithoutPersistence` |
| **R9** Security of sede flows | CSRF and admin rejection | **PARTIAL** | tpvmod: `testControllerValidatesCsrfInsideTheMappingPath` (source contract) + controller `isCsrfValid()` (`controller/tpvmod_settings.php:124`). admin_empresa: relies only on the page gate (`fs_controller.php:988`), **no per-handler check and no runtime test**. Admin enforcement is folder/role based, not administrator-only (Finding W1) |
| | Escaped output | **IMPLEMENTED** | `testSedesBlockRendersEscapedMarkersWithoutDisabledSubmits` (real Twig render: `Sede <b>Norte</b>` → `Sede &lt;b&gt;Norte&lt;/b&gt;`), `testMappingOptionsRenderEscapedSedeLabels` |
| **R10** Sede save never overwrites the base company | Sede form does not touch the base row | **IMPLEMENTED** | `testSaveSedeMarkerWinsOverTheNombreBranch`, `testFullSedePostResolvesToTheSedeHandlerOnly`, `testSedeHandlersNeverWriteTheBaseCompany`; structural proof that with `save_sede` the token is `sede_save` so `handleEmpresaSave()` is unreachable. Real-DB byte-identity deferred to 10.6 (gap d) |
| | Reserved order under regression | **IMPLEMENTED** | `testResolveActionIsAPureSeamThatTestsTheSedeMarkersFirst` — source-order assertion `save_sede` before `nombre`; impl at `admin_empresa.php:289` vs `:297` |
| **R11** Sede mapping section on the settings page | Round-trip read and write | **PARTIAL** | `testRoundTripPersistsTheFourCanonicalPairs`, `testOnlyCanonicalTiposArePersisted`, `testMappingRendersAndPersistsWithFacturacionBaseInactive` (helper + injected spies). **No real HTTP GET→POST→GET round-trip** — declared gap (b)/AD-14, manual smoke 10.6 |
| | Invalid `codsede` is rejected | **IMPLEMENTED** | `testInvalidCodsedeIsRejectedWithoutWriting` — all-or-nothing, `$setMappingFor` never called |
| | Empty selection clears the override | **IMPLEMENTED** | `testEmptySelectionClearsTheOverride` — `setMappingFor('factura', null)` |
| | CSRF and admin gate | **PARTIAL** | `testControllerValidatesCsrfInsideTheMappingPath` (source contract only); no runtime rejection test — declared gap (b) |
| | Escaped labels | **IMPLEMENTED** | `testMappingOptionsRenderEscapedSedeLabels` (asserts no `\|raw` in the mapping block) |
| **R12** Mapping section independent of the `facturacion_base` gate | Mapping works with `facturacion_base` inactive | **IMPLEMENTED** | `testMappingRendersAndPersistsWithFacturacionBaseInactive` (asserts gate is closed, mapping form sits after `{% endif %}`, persistence still `ok`), `testControllerRunsTheMappingBranchBeforeTheTerminalGate` (`strpos` order + gate consulted exactly once) |

**Scenarios with no covering test at all:** none. Four scenarios are PARTIAL for the
same four declared gaps. `R1/S1.1` has partial coverage (structural XML contract +
`install() === ''`); `R9/S9.1` and `R11/S11.4` have source-contract coverage only;
`R11/S11.1` has helper-level coverage only.

---

## 2. End-to-end traceability across the 3 repos

The intended chain is coherent and verified by reading the real code:

```
admin_empresa (business_data)
  initializeModels()  → new empresa_sede()        controller/admin_empresa.php:183
  dispatchAction()    → handleSaveSede()/Delete   controller/admin_empresa.php:339-374
  loadSedes()         → empresa_sede::all()       controller/admin_empresa.php:376-382
tpvmod_settings (tpvmod)
  tpvmod_save_sede_mapping()  → \empresa_sede::setMappingFor()   lib/tpvmod_sede_mapping.php:74-76
  loadSedeMapping()           → empresa_sede::mapping()/all()    controller/tpvmod_settings.php:157-160
empresa_sede::setMappingFor()  → fs_settings key empresa_sede_<tipo>   model/empresa_sede.php:299-314
RelatedModelsLoader (factura_pdf1)
  load($doc, $tipo)  → resolveEmpresa()  → empresa_sede::resolveForDocumentType()  :42, :99-125
                                  ↓
                     toEmpresa()/withPrintablePhone()  → PortedPdfDocument::telefono1  :545
```

**Canonical vocabulary consistency.** The four literals `presupuesto|albaran|pedido|factura`
are identical in all three repos:

| Location | Evidence |
|---|---|
| business_data | `empresa_sede.php:63-68` (`private const TIPOS`) |
| tpvmod | `lib/tpvmod_sede_mapping.php:18` (`TPVMOD_SEDE_MAPPING_TIPOS`); `controller/tpvmod_settings.php:43` marker; view selects `sede_presupuesto/sede_albaran/sede_pedido/sede_factura` |
| factura_pdf1 | 4 call sites pass `'albaran'`/`'pedido'`/`'presupuesto'`/`'factura'` (`AlbaranPrintView.php:181`, `PedidoPrintView.php:181`, `PresupuestoPrintView.php:181`, `FacturaPrintView.php:219`) |
| Pre-existing shared vocabulary | `lib/tpvmod_modules.php:86-99` (`tpvmod_imprimir_url()`), `Controller/FacturaPdf1Controller.php:179-195` (`resolveAdapter()` `match`) — same four literals, no second vocabulary |

**Raw key containment.** A repo-wide grep for `empresa_sede_presupuesto|albaran|pedido|factura`
outside `business_data` returns **only test fixtures** (factura_pdf1
`RelatedModelsLoaderEmpresaSedeTest.php` sets `$GLOBALS['config2']['empresa_sede_factura']`
to stub the settings store) and a docblock in `lib/tpvmod_sede_mapping.php:11-14` that
explicitly states the keys are private. **No production consumer outside `business_data`
reads or writes a raw `empresa_sede_*` key.** Requirement R3 satisfied.

---

## 3. Independently reproduced test results (real output)

All commands run via `ddev exec` (PHP 8.3.33, PHPUnit 11.5.56). Never host PHP.

### 3.1 The change's own suites — green

```bash
$ ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/
OK (47 tests, 471 assertions)

$ ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml
Tests: 228, Assertions: 679, Failures: 6, Warnings: 21, Skipped: 1.

$ ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
OK (126 tests, 554 assertions)

$ ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede
OK (36 tests, 354 assertions)
```

`EmpresaSede`-filtered 36 = 13 (`EmpresaSedeModelTest`) + 13 (`EmpresaSedeResolutionTest`)
+ 10 (`RelatedModelsLoaderEmpresaSedeTest`). `AdminEmpresaDispatchActionTest` (14) and
`TpvmodSedeMappingSettingsTest` (13) are inside the suite totals above and passed in the
full run (3.2).

### 3.2 Full root `Plugins` suite — only `OidcProvider`, zero attributable to this change

```bash
$ ddev exec php vendor/bin/phpunit --testsuite Plugins
ERRORS!
Tests: 2038, Assertions: 8041, Errors: 2, Failures: 14, Warnings: 1, Skipped: 46.
```

Every failing header belongs to `Tests\OidcProvider\...` (16 failing tests:
`admin_oidc_customer_edit_grupos`, `admin_oidc_customer_access_validation_groups`,
`admin_oidc_customer_password`, `OidcClienteProfileIntegrityGuard`,
`OidcLegacySchemaParity`, `OidcSchemaContract`, `migration011_cliente_grupos`).

```
$ grep -E "^[0-9]+\) " root-plugins-run1.txt | grep -v "OidcProvider"
NONE outside OidcProvider
```

### 3.3 Isolated `OidcProvider` suite — failures reproduce without our files

```bash
$ ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/OidcProvider/tests/
Tests: 391, Assertions: 1421, Failures: 7, Warnings: 1, Skipped: 23.
```

7 failures, **all** `Tests\OidcProvider\...`, overlapping my full-run set
(`OidcLegacySchemaParityTest`, `OidcSchemaContractTest`, `migration011_cliente_gruposTest` ×4).

### 3.4 Method — how "not attributable to this change" was established

I did **not** accept the pre-existing claim on faith. Four independent lines of evidence:

1. **Failure-set intersection.** In my `--testsuite Plugins` run, filtering failure headers
   for anything outside `Tests\OidcProvider\...` returns **empty**. The root `phpunit.xml`
   `Plugins` suite discovers `plugins/*/tests`, so it *does* include all five new test files.
2. **Isolated reproduction.** `plugins/OidcProvider/tests/` run alone (391 tests) fails
   7×, all `OidcProvider`, on the same classes (schema parity/contract, migration011
   backfill, registration). No new test file is loaded in that run.
3. **Zero static coupling.** `grep -rln "empresa_sede\|RelatedModelsLoader\|empresa-sede"
   plugins/OidcProvider/` → **NONE**. OidcProvider lists `business_data` only as an
   activated plugin name (for `cliente` tables); it never instantiates `empresa_sede`.
4. **Observed non-determinism confirmed.** Same suite, different runs: mine measured **16**
   failing (2 errors + 14 failures) and the isolated run **7**; the apply records cite
   **7, 8 and 20**. Root cause is visible in the run log and is environmental:
   `CREATE TABLE failed ... oidc_cliente_grupos (errno: 150 "Foreign key constraint is
   incorrectly formed")` and `oidc_cliente_profiles dropped from baseline 1200 to 0/1/5`.
   During my measurement the host was simultaneously running **three** `--testsuite Plugins`
   processes and a `plugins/catalogo_core/phpunit.xml` run from other sessions against the
   same MySQL instance — the most plausible source of the DB-state races.

**Conclusion: 0 failures attributable to `empresa-sedes-por-documento`.**

### 3.5 The 6 `factura_pdf1` failures are pre-existing (proof, not a claim)

The 6 failures are exactly `FacturaPdf1SettingsControllerTest` ×1, `SettingsCoverageTest` ×2,
`SettingsEffectCoverageTest` ×3 (warehouse-signal datasets `mostraralmacen`,
`tituloalmacen`, `mostraralmacentel`). `phpunit.xml:113-115` already **excludes those exact
files** from the root suite with a documented reason, and `git blame` dates that exclusion
block to commit `c34539ab3` on **2026-08-31** — three weeks before `e89efdf` (2026-09-22).
They are `factura_pdf1` plugin-repo drift, not a regression.

---

## 4. The two highest-severity contracts

### (a) `admin_empresa` dispatch collision — **VERIFIED**

- The sede markers are evaluated **before** the `nombre` branch:
  `admin_empresa.php:289` (`save_sede`) and `:293` (`delete_sede`) precede `:297` (`nombre`).
- `dispatchAction()` switches on the pure seam: `switch (self::resolveAction($_POST, $_GET))`
  (`:341`); `case 'sede_save'` calls `handleSaveSede()` only. With `save_sede` present the
  token is `sede_save`, so `handleEmpresaSave()` is **unreachable** on that path.
- `handleSaveSede()`/`handleDeleteSede()` never touch `$this->empresa` (asserted by
  `testSedeHandlersNeverWriteTheBaseCompany`), and `sedeFieldsFromPost()` projects only the
  12 `empresa_sede::EDITABLE_FIELDS` (`:392-402`), so a sede POST cannot leak
  `contintegrada`, `codalmacen`, `codserie`, etc. into the base row.
- AD-10 trap avoided: the block's submit buttons carry their own marker and use **no**
  `onclick="this.disabled = …"` (asserted by `testSedesBlockRendersEscapedMarkersWithoutDisabledSubmits`).
- `<div id="panel_sedes">` (`view/admin_empresa.html.twig:203`) sits **outside**
  `<form name="f_empresa">` (closed at `:199`), with its own `{{ csrf_field() }}`.

### (b) Zero-sede backwards compatibility — **VERIFIED**

- `resolveForDocumentType()` short-circuits before any DB access:
  unknown `tipo` → `null` (`empresa_sede.php:318-320`); absent/empty mapping → `null`
  (`:322-325`). `mapping()` only reads `$GLOBALS['config2']` via `fs_settings::get()`
  (`base/fs_settings.php:43-46`; `fs_settings` has no constructor and no DB).
  The DB-touching `loadSede()` (`:327`) is reached **only** when a non-empty mapping exists.
- Tests prove the injected loader is called **0** times for unknown / absent / empty keys
  (`EmpresaSedeResolutionTest.php:127-172`).
- The loader then returns the **same instance**:
  `RelatedModelsLoader::resolveEmpresa()` → `$empresa = $sede instanceof \empresa ? $sede : $base;`
  then `return \empresa_sede::withPrintablePhone($empresa);` which returns the same object
  (`empresa_sede.php:273-281`). Pinned by `assertSame($base, $out)` in
  `testNullDocumentTypeKeepsBaseIdentityAndPrintsTheBasePhone`,
  `testUnmappedTypeKeepsBaseIdentityAndPrintsTheBasePhone`,
  `testDanglingMappingKeepsTheBaseIdentity`.

---

## 5. The one deliberate output change

The base company's `telefono` now prints as `telefono1` for **every** existing installation.

- **Implemented:** `RelatedModelsLoader::resolveEmpresa()` applies
  `\empresa_sede::withPrintablePhone($base)` on the no-sede path
  (`RelatedModelsLoader.php:118-124`); `withPrintablePhone()` publishes
  `telefono1 = telefono` without overwriting a non-empty value and returns the same
  instance (`empresa_sede.php:273-281`). `PortedPdfDocument.php:545` reads `telefono1`
  via `property_exists()`, which sees the dynamic property because `fs_model` carries
  `#[AllowDynamicProperties]` (`base/fs_model.php:32`).
- **Scoped:** it is unconditional on the base path (by design) but only when
  `class_exists('empresa_sede', false)` — i.e. only once business_data ≥ 1.1.0 is deployed
  (`RelatedModelsLoader.php:120-122`). Old business_data keeps the old output.
- **Test-covered:** `testNullDocumentTypeKeepsBaseIdentityAndPrintsTheBasePhone`,
  `testUnmappedTypeKeepsBaseIdentityAndPrintsTheBasePhone`,
  `testWithPrintablePhoneSetsTelefono1AndReturnsTheSameInstance`,
  `testWithPrintablePhoneDoesNotOverwriteANonEmptyValue`.
- **Not hidden:** flagged as the single exception to byte-identical output in
  `proposal.md` §Decision 3 / §Risk table, `design.md` AD-4 and Open Question, the
  `RelatedModelsLoader::resolveEmpresa()` docblock (`:89-92`), and it is task `10.7`.

**Verdict: correctly implemented, scoped, tested and disclosed — not a concealed side effect.**

---

## 6. Security review of the new HTTP surface

| Vector | Finding | Evidence |
|---|---|---|
| CSRF — `admin_empresa` sede forms | Field present on every form (`view/block/admin_empresa_sedes.html.twig:19, 146`). Enforcement is by the **page gate**: `pre_private_core()` → `validateCsrf()` (`base/fs_controller.php:988`) and the constructor only calls `private_core()` when it returns true (`:259-261`). No per-handler check. Matches the pre-existing `handleEmpresaSave()`/`handleSaveCuenta()` pattern. See Finding W2 for the soft-mode caveat | `fs_controller.php:250-261, 382-425, 979-998` |
| CSRF — `tpvmod_settings` mapping form | Field present (`view/tpvmod_settings.html.twig:65`) **and** a second per-handler check `if (!$this->isCsrfValid())` inside `saveSedeMapping()` (`controller/tpvmod_settings.php:124`). Stronger than the host page needed | idem |
| Admin gating | Both pages live in the `admin` folder and are role-gated; **neither is administrator-only**. See Finding W1. Note the design's claim (`design.md:602`) that `parent::__construct(..., 'admin', TRUE, TRUE)` "blocks non-admins" is **incorrect** for this framework version — `fs_controller::__construct` ignores the `$admin` argument (`base/fs_controller.php:191`) | `base/fs_controller.php:191, 919-929` |
| Output escaping | All sede/mapping labels through `{{ }}`; no `\|raw` on user data anywhere in the new/modified templates. `fsc.url()\|raw` is system-generated and mirrors the pre-existing terminal form | grep: only `tpvmod_settings.html.twig:27` (pre-existing) and `:64` |
| XSS defence in depth | `empresa_sede::test()` runs `no_html()` over all 12 fields before persistence; the real Twig render test proves `Sede <b>Norte</b>` is escaped | `empresa_sede.php:150-154`; `testSedesBlockRendersEscapedMarkersWithoutDisabledSubmits` |
| SQL | Every value/PK through `var2str()`; code generation through `$db->sql_to_int('codsede')`; no string concatenation of user input; mapping codes validated against real rows (`$sedeExists`) before any write | `empresa_sede.php:128-244, 299-314`; `lib/tpvmod_sede_mapping.php:86-94` |

---

## 7. Declared deviations — judgement

| # | Declared deviation | Behaviour-preserving? | Tested? | Documented? | Judgement |
|---|---|---|---|---|---|
| i | `resolveEmpresa()` gained a 3rd optional `?callable $sedeLoader` (design declared 2) | **Yes** — additive optional; production callers use the 2-arg form (`RelatedModelsLoader.php:42`) | **Yes** — the 3-arg form is exercised by `testMappedSedeIsAdoptedWithItsFieldsAndPrintablePhone`, `testDanglingMappingKeepsTheBaseIdentity`, `testMissingBaseCompanyStillThrowsEvenWhenASedeIsMapped` | **Yes** (`apply-progress.md:485-488`; design contract at `design.md:410` is the narrower one) | **Accepted.** Test seam, no public contract change |
| ii | `empresa_sede.php` require guarded by `class_exists` **and** `file_exists` | **Yes** — degrades to base company on older business_data instead of fataling | **No dedicated test**; the safe-fallback path is the same one covered by the no-class branch of `resolveEmpresa()` | **Yes** (`apply-progress.md:489-493`; inline docblock `RelatedModelsLoader.php:132-139`) | **Accepted.** Strictly more defensive than the design snippet |
| iii | `tpvmod_sede_mapping_submitted()` gained an optional 2nd param | **Yes** — default preserves the 1-arg call | **Yes** — `testSedeMappingSubmittedDetectsItsOwnMarker` | **Yes** (`apply-progress.md:494-497`) | **Accepted** |
| iv | `lib/tpvmod_sede_mapping.php` "not in the design's file list" | n/a | n/a | **Claim is factually wrong** | **WARNING (W3).** The design *does* specify that exact file and its three functions — `design.md:252`, `design.md:326` (File Changes table), `design.md:416-421` (interfaces). This is not a deviation at all; the apply record mis-declares it |
| v | `dispatchAction()` resolves over `$_POST`/`$_GET` via `posted()` instead of `filter_input()` | **Yes, verified.** `posted()` replicates `filter_input()` truthiness: absent → `false`, array → `false`, `'0'`/`''` → `false` (`admin_empresa.php:330-337`). Confirmed no code mutates `$_POST`/`$_GET` before `dispatchAction()` (`grep` for superglobal assignment → NONE). The only residual difference (a third party mutating `$_POST`) does not occur in this controller | **Yes** — `testArrayValuedPayloadBehavesLikeFilterInput` plus `testExistingDispatchBranchesAreUnchangedIncludingTheDeleteCuentaAsymmetry` | **Yes** (`apply-progress.md:512-520`) | **Accepted.** The legacy `delete_cuenta` POST/GET asymmetry is deliberately preserved and pinned by test |

Nothing silently changes confirmed behaviour. The only inaccurate declaration is (iv),
which over-states a deviation rather than hiding one.

---

## 8. Honest-gap audit

| Gap | Genuine? | Assessment |
|---|---|---|
| (a) Real DDL execution for `empresa_sedes` is untested | **Genuine.** `empresa_sede` tests use an anonymous subclass with an injected spy `fs_db2`; running the real lazy `CREATE TABLE` would create a real table, out of bounds for the suite | **Acceptable.** Compensated by `testSchemaXmlIsWellFormedAndMatchesTheFrameworkAndEmpresaTypes` (well-formedness + framework shape + 13 columns with `empresa.xml` types/nullability + filename == table name) and by the in-repo `cuentasbanco` precedent. **Residual risk:** a real DDL-generation failure would only surface at 10.6 |
| (b) CSRF rejection covered only by a source contract (tpvmod mapping) | **Genuine.** `tpvmod_settings` extends `fs_controller` (needs DB + session) and is not instantiable DB-free (AD-14) | **Acceptable.** The controller's `isCsrfValid()` call is asserted inside the mapping path; end-to-end rejection belongs to 10.6. **Residual risk:** low (framework-level CSRF is exercised across the codebase) |
| (c) The 4-key mapping write is non-atomic | **Genuine.** `fs_settings::save()` (`base/fs_settings.php:264-278`) writes the whole INI with no transaction; there is no transactional API in this layer | **Acceptable.** Single-admin settings screen; a partial write leaves earlier keys set and re-submitting fixes it. Pinned by `testPersistenceFailureAbortsWithAnError` (`['presupuesto','albaran']` already saved when the 3rd call fails) — the limitation is *documented and tested*, not glossed over |
| (d) Base-row byte-identity against a real DB needs manual smoke | **Genuine.** Proving the stored row is unchanged requires a live DB comparison | **Acceptable but the highest residual risk of the change.** The structural proof is strong (`save_sede` ⇒ `handleEmpresaSave()` unreachable; both sede handlers never write `$this->empresa`; `sedeFieldsFromPost()` cannot project base fields), but it is not a real-DB equivalence check. **Deferred to 10.6** |
| (e) Tasks `7.5`, `9.5`, `9.6`, all `10.x` unchecked | **Partly genuine, partly process debt** | `7.5`/`9.5` (REFACTOR) and `2.5`/`5.5` are **evidence/process tasks** left unchecked rather than back-filled — honest, but they mean "all tasks complete" is not literally true. `9.6` is a gate blocked by the flaky root suite. `10.1–10.5` are cross-repo verification and are satisfied by **this report**; `10.6` (manual smoke) and `10.7` (product sign-off) remain open |

**Acceptable for this change:** (a), (b), (c). **Genuine residual risk:** (d) and the open
`10.6`/`10.7` items. **Process debt, not correctness:** (e).

---

## 9. SDD isolation audit

| Check | Result |
|---|---|
| Core `openspec/changes/{empresa-sedes-por-documento}` exists? | **No** (`ls openspec/changes/` has no sede entry) |
| `plugins/factura_pdf1/openspec/changes/*sede*` exists? | **No** |
| `plugins/business_data/openspec/` exists? | **No** (`ls` → "No such file or directory") |
| Root repo `git status` shows openspec changes? | **No** (only `M opencode.json`, pre-existing) |

The whole SDD lives in `plugins/tpvmod/openspec/` (main beneficiary). No core contamination.

---

## 10. Working-tree hygiene

| Repo | Status | Expected |
|---|---|---|
| `plugins/business_data` (`feat/empresa-sedes-panel`) | ` M model/cuenta_banco.php` only | ✅ Pre-existing and **not ours**: the diff replaces `entidad/oficina/dc/cuenta` with `codsubcuenta` (unrelated to sedes), file mtime 2026-09-22 09:13 — before the evening apply work — and it was never staged (`f68afbb0`, `83be16bd`, `51eb6401` do not include it) |
| `plugins/factura_pdf1` (`feat/empresa-sedes`) | clean | ✅ |
| `plugins/tpvmod` (`feat/empresa-sedes`) | clean before this report; this report is the only added path | ✅ |
| Root (`master`) | ` M opencode.json` only | ✅ Pre-existing |

---

## 11. Findings

### CRITICAL
None.

### WARNING

- **W1 — "Admin-only" is enforced as role-based folder access, not administrator-only.**
  `admin_empresa` and `tpvmod_settings` carry **no `#[AdminOnly]`** (grep of both controller
  dirs → none). `fs_controller::isAccessAllowed()` (`base/fs_controller.php:919-929`) returns
  `TRUE` for any non-admin user with an `fs_rol_access` row for the page. Consequently a role
  explicitly granted the page can mutate sedes, the sede mapping **and** the base company.
  `design.md:602` justifies the gate with `parent::__construct(..., 'admin', TRUE, TRUE)` and
  claims it "blocks non-admins", but `fs_controller::__construct` (`base/fs_controller.php:191`)
  **ignores the `$admin` argument** entirely (the 4th `$admin` param is documented as obsolete
  in `AGENTS.md`). This matches the host pages' pre-existing posture and introduces **no
  regression**, but it does not literally satisfy the spec wording "admin-only". Remediation:
  a per-handler `$this->user->admin` check for the sede/mapping mutations, or `#[AdminOnly]`
  on the controllers (the latter would also make the existing base-company editor admin-only).
- **W2 — Sede mutations in `admin_empresa` rely solely on the page-gate CSRF.**
  `handleSaveSede()`/`handleDeleteSede()` (`admin_empresa.php:404-438`) contain no
  `isCsrfValid()`/`requireCsrf()` call. `pre_private_core()` only calls `private_core()` when
  `validateCsrf()` returns true (`base/fs_controller.php:988, 250-261`), so the default
  (strict) mode is safe. But in `FS_CSRF_SOFT=true` mode `validateCsrf()` returns `true` for
  invalid tokens, so the sede write would proceed — the `tpvmod_settings` mapping path is
  stronger because it re-checks (`controller/tpvmod_settings.php:124`). Consistent with the
  pre-existing handlers, but `requireCsrf()` exists precisely for mutating operations
  (`base/fs_controller.php:449-465`). Impact limited to soft-mode installs.
- **W3 — `apply-progress.md:499-503` mis-declares deviation (iv).** It states
  `lib/tpvmod_sede_mapping.php` "was **not** in the design's file list", but `design.md:252`,
  `:326` and `:416-421` specify that exact file and its three functions. Documentation
  inaccuracy only; no behavioural impact.
- **W4 — Unchecked tasks remain.** `2.5`, `5.5`, `7.5`, `9.5`, `9.6` and all of `10.x` are
  `[ ]`. No core *implementation* task is pending (1.x, 3.x, 4.x, 5.1-5.4, 5.6-5.8, 6.x,
  7.1-7.4, 7.6-7.9, 8.x, 9.1-9.4, 9.7 are all `[x]`), so this does not block the
  implementation, but full "all tasks complete" verification cannot be declared. This report
  satisfies `10.1-10.5`; `10.6` (manual smoke) and `10.7` (product sign-off) stay open.

### SUGGESTION

- **S1** — `view/tpvmod_settings.html.twig:64` uses `fsc.url()|raw`, mirroring the
  pre-existing line 27. The URL is framework-generated, not user data, so it is not a
  vulnerability, but the escaping exception is unnecessary in new code.
- **S2** — `empresa_sede::save()` (`:170-178`) calls `exists()` twice and generates the code
  before `test()`; harmless, but `test()` before code generation would read more cleanly.
- **S3** — The `tpvmod_settings` docblock (`controller/tpvmod_settings.php:59`,
  `// require_admin=TRUE, only_admin=TRUE: blocks non-admins…`) repeats the inaccurate gate
  claim. It is **pre-existing** (visible as unchanged context in `git show fa4dc92`), so out
  of scope, but it is the source of the W1 misconception.
- **S4** — Visiting `tpvmod_settings` or `admin_empresa` instantiates `empresa_sede` and
  therefore lazy-creates `empresa_sedes` even for installations that never map a sede. This
  is the intended lazy-creation behaviour (design → Migration), noted for completeness.

---

## 12. Residual risk and manual smoke (`10.x`)

Left to manual smoke (`tasks.md` 10.6), not automatable DB-free:

1. Create a sede via `index.php?page=admin_empresa#sedes`; confirm the `empresa_sedes` table
   is created lazily from the XML and the row persists.
2. Confirm the base company row is **byte-identical** after a sede POST (the real-DB half of
   R10; gap d).
3. Map the sede to `factura` in `index.php?page=tpvmod_settings`; print a factura → sede
   header, sede phone printed as `telefono1`, `pais` resolved from the sede `codpais`.
4. Unmap and print again → base header, base phone now printed (the deliberate AC-4 change).
5. Submit the mapping form without a valid CSRF token (and as a non-admin) → no write, error
   shown (end-to-end half of R9/R11).
6. Product sign-off (`10.7`): accept that the base company phone becomes printable for every
   existing installation.

Rollback remains per-repo revert as documented in `proposal.md` §Rollback: reverting
`factura_pdf1` restores prior output (it owns the only output-affecting delta); reverting
`business_data` drops the model/mapping API and leaves an inert table; the four mapping
`fs_settings` keys are inert with no sede.

---

## 13. Verification commands (as run)

```bash
# change suites
ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/business_data/tests/
ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml
ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml
ddev exec php vendor/bin/phpunit --testsuite Plugins --filter EmpresaSede

# full root suite + independence check
ddev exec php vendor/bin/phpunit --testsuite Plugins
ddev exec php vendor/bin/phpunit -c phpunit.xml plugins/OidcProvider/tests/

# static traceability / isolation / hygiene
grep -rn "empresa_sede_" plugins --include="*.php" --include="*.twig"
grep -rn "RelatedModelsLoader::load(" plugins/factura_pdf1/Model/View/*.php
grep -rln "empresa_sede\|RelatedModelsLoader" plugins/OidcProvider/
git -C plugins/{business_data,factura_pdf1,tpvmod} status --short
```

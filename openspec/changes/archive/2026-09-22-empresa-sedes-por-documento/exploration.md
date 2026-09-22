# Exploration: empresa-sedes-por-documento

> Change owner: `plugins/tpvmod/openspec/changes/empresa-sedes-por-documento/`
> (`ownership: plugin-local`, `strict_tdd: true`, runner
> `ddev exec php vendor/bin/phpunit`, isolated
> `ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`).
>
> Cross-plugin change (business_data + tpvmod + factura_pdf1). Per `AGENTS.md`
> → "OpenSpec per Plugin" the SDD lives where the main beneficiary lives; the
> orchestrator already placed it in tpvmod, matching the existing precedent
> `plugins/tpvmod/openspec/changes/tpvmod-descuentos-cliente/` (Wave 5 =
> "factura_pdf1 (cross-plugin)"). No entries are to be created in
> `openspec/` (core) nor in `plugins/business_data/` (it has no `openspec/`
> tree) nor in `plugins/factura_pdf1/openspec/`.

## Current State

### 1. Company identity is a single-row model

- `plugins/business_data/model/empresa.php:142-160` — `empresa::get($id)` **ignores
  `$id` entirely**: it runs `SELECT * FROM empresa ORDER BY id ASC;` and returns the
  first row. The single-company assumption is hard-baked; multi-"sede" cannot be
  expressed by inserting extra `empresa` rows.
- `plugins/business_data/model/empresa.php:83` — `private static $empresa_row_cache`
  memoizes the row per request (`save()`/`delete()` reset it at `:389`, `:469`).
- `plugins/business_data/fsframework.ini` — `require = ""`. business_data has **zero
  plugin dependencies** and is the canonical owner of company master data (`empresa`,
  `cuenta_banco`, `ejercicio`, `serie`, `forma_pago`).
- `plugins/business_data/model/cuenta_banco.php` + `plugins/business_data/model/table/cuentasbanco.xml`
  — the in-repo precedent for a multi-row company-owned entity: `codcuenta`
  `character varying(6)` PK, `get_new_codigo()` at `:169-178` generates `MAX+1`, and
  the table XML filename **must equal the table name** (see `fs_model::get_base_dir()`
  + `fs_model::get_xml_table()` below).
- `base/fs_model.php:105-132` — the constructor calls `check_table($table_name)`; table
  creation/sync happens lazily on first instantiation from
  `plugins/{active-plugin}/model/table/{table_name}.xml`
  (`base/fs_model.php:499-513`, `:522-524`). **No explicit migration is required** for a
  new table, exactly like `cuentasbanco` (confirmed: `plugins/business_data/model/table/`
  contains only the 5 XMLs, no migration code).

### 2. `admin_empresa.html.twig` has no inline injection point, and one hard-to-see trap

- `plugins/business_data/view/admin_empresa.html.twig:46-52` — the only existing
  plugin-extension affordance is the `fsc.extensions` loop, fed by
  `base/fs_controller.php:887-895` `load_extensions()` from the **DB table
  `fs_extension`** (rows keyed `name`+`from`, `model/fs_extension.php:29-67`). It renders
  page-level **buttons that navigate to another page** — it cannot render an inline
  section, and there is no code-level API to declare a row.
- Template structure:
  - nav `<ul>` at `:20-53` (5 `<li id="b_*">` entries + extension loop)
  - one `<form name="f_empresa">` at `:56` → `</form>` at `:194`
  - panels inside the form: `panel_generales` `:58`, `panel_facturacion` `:185`,
    `panel_impresion` `:188`, `panel_traducciones` `:191`
  - `panel_cuentasb` at `:195-197` sits **outside** `</form>`, and each bank account is
    its own `<form>` + `csrf_field()` (`view/block/admin_empresa_bancos.html.twig:2-3`)
  - JS at `:293-367`; `mostrar_seccion(id)` at `:307-337` **hardcodes the 5 panel ids**
    and hardcodes the hide/show + `.active` toggling per id.
- **Trap (must be designed around):** `plugins/business_data/controller/admin_empresa.php:252-267`
  `dispatchAction()` dispatches on the mere **presence of `nombre`** (`:254`). A sede form
  that posts a field literally named `nombre` (one of the required sede fields) would be
  routed into `handleEmpresaSave()` → `applyEmpresaFields()` (`:269-304`) and would
  overwrite the base company row with sede data. Any new POST branch must be keyed on a
  distinct marker **and evaluated before** the `nombre` branch. Existing sibling branches:
  `logo` `:256`, `delete_cuenta` `:260`, `iban` `:262`.

### 3. The core hook mechanism exists and is the sanctioned inline-extension point

- `src/View/ViewHookRegistry.php:14-53` — `FSFramework\View\ViewHookRegistry` with
  `register(hook, template)`, `has(hook)`, `render(twig, hook, context)`. Static
  `private static array $hooks` (`:19`) → needs Reflection reset in tests.
  Render failures are swallowed to `error_log` (`:47-49`), so a broken hook template
  degrades to an empty string instead of a fatal error.
- `src/Core/Html.php:417-424` registers the Twig function `render_hook` with
  `is_safe => ['html']`; `:429-434` keeps the deprecated `clientes_render_hook` alias.
  Registration is idempotent (catches `LogicException`).
- Consumption precedent: `plugins/clientes_core/view/ventas_cliente.html.twig:217` and
  `:464` → `{{ render_hook('cliente_form_after_main', {'fsc': fsc, 'cliente': cliente})|raw }}`.
- Registration precedent: `plugins/catalogo_core/Init.php:211-219` (`registerHooks()` on
  `TwigInitEvent`, guarded by a static flag) and the ownership tests
  `plugins/catalogo_core/tests/Integration/CatalogoOpcionalesHookOwnershipTest.php`,
  `plugins/tarifario/tests/Integration/HookRegistrationTest.php:69-78` (Reflection reset of
  `ViewHookRegistry::$hooks`).
- `Plugin view/` directories are prepended to the **default** Twig namespace
  (`src/Core/Html.php:193-216`) and also exposed under a per-plugin namespace
  (`addPath($legacyPath, $plugin)`), so a hook template can be addressed either bare or as
  `@business_data/block/...`.
- **The alternative `getIncludeViews()` pattern is unusable here**:
  `src/Core/Html.php:622-653` + `:655-690` render `Extension/View/{Template}_{position}_{order}.html.twig`
  with **an empty context** — `src/Core/Html.php:643` is literally
  `self::$twig->render($ext['path'], [])`. A sedes section needs `fsc`, so this pattern
  would require a core change. It also has vestigial, non-functional leftovers in this
  repo (`plugins/tpvmod/view/extension/*.html.twig` sit in `view/extension/`, which
  `findExtensionViews()` never scans — it scans `Extension/View/`), consistent with the
  core proposal calling the pattern dead.
- The core change `openspec/changes/view-hook-registry-core/` is **implemented but not
  archived** and explicitly lists "Refactoring `admin_empresa` to use hooks" as a separate
  future change (`openspec/changes/view-hook-registry-core/proposal.md:19`).

### 4. The printing path and the single company-resolution point

- `facturacion_base` is **not installed** in this environment (absent from `plugins/`;
  `plugins/tpvmod/lib/tpvmod_modules.php:82-108` prefers `ventas_imprimir` but falls back to
  `factura_pdf1`, and the module-enabled check at `:63-67` returns false). So the live PDF
  path is `index.php?page=factura_detallada&tipo=<tipo>&id=N`.
- `plugins/factura_pdf1/controller/factura_detallada.php:27-29` is a legacy page-name shim over
  `plugins/factura_pdf1/Controller/FacturaPdf1Controller.php`; `resolveAdapter()` at `:175-185`
  maps `tipo` → `AlbaranClienteAdapter|PedidoClienteAdapter|PresupuestoClienteAdapter|FacturaClienteAdapter`
  with the exact literals `albaran|pedido|presupuesto|factura` (`default => factura`).
- Each adapter builds its print view via `fromId()`
  (e.g. `Model/Adapters/PresupuestoClienteAdapter.php:28-32`), and each `*PrintView::build()`
  calls **`RelatedModelsLoader::load($document)`** — the one and only company resolution
  point:
  - `Model/View/PresupuestoPrintView.php:181`, `Model/View/PedidoPrintView.php:181`,
    `Model/View/AlbaranPrintView.php:181`, `Model/View/FacturaPrintView.php:219`.
- `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php:42` —
  `$empresa = (new \empresa())->get();`, then `:43-45` throws
  `\RuntimeException('Empresa no configurada.')` when it is not an `\empresa`. The array is
  consumed by the print views (`empresa` feeds the `\empresa`-typed constructor at
  `PresupuestoPrintView.php:45`), exposed through `ClientDocumentPrintViewInterface::getEmpresa(): object`
  (`Model/View/ClientDocumentPrintViewInterface.php:32`) and consumed by
  `Lib/PDF/PortedPdfDocument.php:459` (`insertHeader`) and `:527` (`prepareCompanyInfo`).
- `RelatedModelsLoader.php:65` — `$codpais = $empresa->codpais !== '' ? $empresa->codpais : ($cliente->codpais ?? '')`
  → the resolved company **also selects the `pais` row** used for the country name in the
  header. The override must therefore happen at `:42`, before `:65`.
- `RelatedModelsLoader::requireRelatedModels()` at `:80-97` **hard-`require_once`s
  business_data model files by absolute path** (`:83`, `:92`) and catalogo_core's by path.
  This is the established, already-existing mechanism by which factura_pdf1 consumes
  business_data classes without a loader dependency.
- **Which `empresa` fields actually reach the PDF** (evidence in `PortedPdfDocument.php`):
  | Field | Consumed at | Printed? |
  |---|---|---|
  | `nombre` | `:530` | yes (header) |
  | `direccion`, `apartado`, `codpostal`, `ciudad`, `provincia`, `codpais` | `combineAddress()` `:279-320` | yes |
  | `cifnif` | `:534-538` | yes (labelled with `tipoidfiscal`, default `NIF`) |
  | `email` | `:541-543` | yes (contact block) |
  | `web` | `:559-565` | yes (contact block) |
  | `telefono1`, `telefono2` | `:545-554` | **`empresa` has no such properties** → the company phone is currently never printed |
  | `logo` | NOT read; `insertCompanyLogo()` `:398-410` resolves the logo from system branding (`EmpresaLogoResolver::resolveSystemLogo()`) | n/a |
  | `pie_factura`, `lema`, `fax`, `horario`, `nombrecorto` | no reference anywhere in `plugins/factura_pdf1/` (only in test fixtures `tests/Fixtures/DocumentPrintViewFixture.php:163-185`) | **no** |
- The `tipo` literal vocabulary (`presupuesto|albaran|pedido|factura`) is already shared by
  `tpvmod_imprimir_url()` (`lib/tpvmod_modules.php:91-105`),
  `FacturaPdf1Controller::resolveAdapter()`/`resolveDocumentSlug()`
  (`Controller/FacturaPdf1Controller.php:175-197`) and the mapping requirement. Reuse these
  exact literals; do not invent a second vocabulary.
- `plugins/factura_pdf1/controller/admin_factura_pdf1.php` → `Controller/Admin/FacturaPdf1SettingsController.php`
  (`:61` template `admin/factura_pdf1/settings`, `:138` `$service->save()`), backed by the
  single-row table `factura_pdf1_settings` (`Model/FacturaPdf1Setting.php:27-35`,
  `Services/SettingsService.php:28`). It is the *print* settings page and would be the
  technically-cleaner home for a document→sede mapping, but the human chose tpvmod_settings.
- Adjacent (out of scope) print path: the TPV thermal ticket header hardcodes the same
  `empresa` fields at `plugins/tpvmod/controller/tpvmod.php:2249-2253`. The requirement is
  explicitly about the printed **PDF**, so this path is not covered.

### 5. factura_pdf1 already declares a dependency on tpvmod (premise correction)

- `plugins/factura_pdf1/fsframework.ini` → `require = "tpvmod"`. The orchestrator's premise
  ("factura_pdf1 must NOT depend on the TPV plugin") does not match the **declared** state.
  The real runtime flow is the opposite direction (tpvmod builds the print URL). Regardless
  of that declaration, the semantic argument still holds: company identity is business_data
  domain data, and business_data is already factura_pdf1's hard `require_once` dependency for
  `empresa`. Placing the sede model in business_data adds **zero** new dependency edges.
- The only other factura_pdf1→tpvmod references are soft (`Services/GestorProgramaRoleDefinition.php:24-29`
  lists tpvmod pages for the `gestor_programa` role).

### 6. Repo topology (drives the PR shape)

- `/plugins/*` is gitignored by the root repo (`.gitignore:38`) and **each of
  `plugins/business_data`, `plugins/tpvmod`, `plugins/factura_pdf1` is its own git
  repository** (`git ls-files plugins/<p>` = 0 in the parent; each has its own `.git`).
  Only `plugins/system_updater` is a submodule (`.gitmodules`). → Three independent
  repositories, three independent PRs, version/`fsframework.ini` bumps per repo.

### 7. Settings stores available today

- `base/fs_settings.php:43-58` — `get/set/has` operate on `$GLOBALS['config2']`; `:264-280`
  `save()` rewrites `tmp/{FS_TMP_NAME}/config2.ini`. Pure INI store, no DB, no query cost
  after bootstrap. Precedent inside business_data: `admin_empresa.php:485-512`
  (`save_traducciones`) already writes document labels through `fs_settings`.
- `model/fs_var.php:274-296` — `array_get($array, $replace = TRUE)` is a **gotcha**: missing
  keys are replaced with `FALSE` by default (not left alone); `:304-319` `array_save()` treats
  `FALSE` as a **delete**. `simple_save()` at `:211-227` stores plaintext via `var2str()`.
  Precedent inside business_data: `admin_empresa.php:182-191` (`loadConfigDefaults` → `fs_var`).
- `plugins/tpvmod/controller/tpvmod_settings.php:46-86` — admin-only
  (`parent::__construct(..., 'admin', TRUE, TRUE)`), CSRF-gated (`:62-66`), single
  `fs_settings` key `tpvmod_terminal_mode`. The plugin's spec
  `plugins/tpvmod/openspec/specs/tpvmod-config/spec.md` explicitly mandates `fs_settings`
  and **forbids** DB persistence for that toggle.
- Cross-plugin settings write precedent: `plugins/business_data/controller/admin_empresa.php:465-480`
  (`savePdfPluginSettings()`) instantiates factura_pdf1's `SettingsService` behind an
  `in_array('factura_pdf1', $GLOBALS['plugins']) && class_exists(...)` guard (`:443-447`).

---

## Affected Areas (per repo)

### `plugins/business_data` (own git repo)
| File | Kind | Why |
|---|---|---|
| `model/empresa_sede.php` | NEW | Multi-row sede entity + document-type resolver API (single source of truth for everyone) |
| `model/table/empresa_sedes.xml` | NEW | Schema (filename **must** be `empresa_sedes.xml` to match the table name, `base/fs_model.php:508`, `:524`) |
| `controller/admin_empresa.php` | MOD | New POST branch in `dispatchAction()` (`:252-267`, **before** the `nombre` branch), save/delete handlers, load sedes for the view |
| `view/admin_empresa.html.twig` | MOD | 6th nav entry + new panel outside `f_empresa`; extend `mostrar_seccion()` (`:307-337`) and `comprobar_url()` (`:294-306`) |
| `view/block/admin_empresa_sedes.html.twig` | NEW | Per-sede form (own `<form>` + `csrf_field()`), mirrors `block/admin_empresa_bancos.html.twig` |
| `tests/EmpresaSedeModelTest.php` | NEW | CRUD + validation + schema-hydration (mirrors `tests/BusinessDataModelTest.php`) |
| `tests/EmpresaSedeResolutionTest.php` | NEW | Document-type → sede resolution, fallback, `toEmpresa()` field mapping |
| `fsframework.ini` | MOD | version bump only |

### `plugins/tpvmod` (own git repo — SDD owner)
| File | Kind | Why |
|---|---|---|
| `controller/tpvmod_settings.php` | MOD | 4 mapping selects, CSRF, admin gate (already present), persist via business_data API |
| `view/tpvmod_settings.html.twig` | MOD | Mapping form section, escaped output, `csrf_field()` |
| `tests/TpvmodSedeMappingSettingsTest.php` | NEW | Round-trip, invalid sede rejected, CSRF, admin-only |
| `fsframework.ini` | MOD | version bump only |
| `openspec/specs/{domain}/spec.md` | NEW/MOD | Capability spec for the mapping + resolution behavior |

### `plugins/factura_pdf1` (own git repo)
| File | Kind | Why |
|---|---|---|
| `Model/View/RelatedModelsLoader.php` | MOD | Accept an explicit `tipo`, consult the resolver at `:42`, keep the `RuntimeException` contract at `:43-45` |
| `Model/View/{Factura,Albaran,Pedido,Presupuesto}PrintView.php` | MOD | Pass the literal `tipo` to `load()` (1 line each: `:219`, `:181`, `:181`, `:181`) |
| `tests/Unit/View/RelatedModelsLoaderEmpresaSedeTest.php` | NEW | Sede wins when mapped; base empresa when unmapped; throw preserved |
| `fsframework.ini` | MOD | version bump only |

**Explicitly NOT touched:** `src/`, `base/`, `controller/`, `model/` (core), `openspec/` (core),
`plugins/factura_pdf1/openspec/`, `plugins/business_data/` (no openspec), the `empresa` model's
public API and `save()`/`test()` (`model/empresa.php:366-465`) — preserved verbatim.

---

## Approaches

### Q1 — Where does the `empresa_sede` model live?

| # | Approach | Pros | Cons | Effort |
|---|---|---|---|---|
| **A** | **business_data** (recommended) | Correct domain (company master data beside `empresa`/`cuenta_banco`); factura_pdf1 **already** `require_once`s business_data files by path (`RelatedModelsLoader.php:83`) → zero new dependency edges; works even if tpvmod is deactivated; mirrors the `cuenta_banco`/`cuentasbanco.xml` precedent exactly; lazy table creation needs no migration | business_data grows a second multi-row table; the sedes UI is a business_data feature even though the change is "driven from tpvmod" | Medium |
| B | tpvmod | SDD stays 100 % plugin-local; tpvmod already depends on business_data so the write path is free | Company identity is not a TPV concept; factura_pdf1 would depend on tpvmod **for company data** (semantically inverted, even though `require = "tpvmod"` already exists); if tpvmod is deactivated the sedes data becomes unreachable and the PDF silently reverts to the base company | Medium |
| C | new `empresa_sedes` plugin | Clean isolation | A whole new plugin for one table; adds a 4th repo, activation/versioning burden, and a new required dependency for factura_pdf1 | High |

**Recommendation: A.** The deciding criterion is the print path: factura_pdf1 must resolve a
sede without depending on the TPV plugin, and it already loads business_data models directly
from disk. `business_data` is also the only plugin with no plugin dependencies
(`require = ""`), so it is the natural, cycle-free home.

### Q2 — The injection/UI contract in `admin_empresa`

| # | Approach | Pros | Cons | Effort |
|---|---|---|---|---|
| **A** | **Native panel in business_data's own template** (recommended) | business_data owns the data *and* the UI, so no cross-plugin contract to freeze; a 6-line JS/template edit in the owner's file; own `<form>` + `csrf_field()` outside `f_empresa` (mirrors `panel_cuentasb` at `:195-197`); no dependency on the **unarchived** core hook change | Not "plugin-extensible" for future third parties; the file is edited (but by its owner) | Low |
| B | `render_hook` (hook injected by tpvmod) | Uses the sanctioned core mechanism; tpvmod owns the section; makes `admin_empresa` extensible by any plugin | Requires freezing a hook contract in someone else's template; the hook JS must fight `mostrar_seccion()`'s hardcoded ids (`:307-337`); depends on a core change that is implemented but **not archived**; tpvmod would own UI for data it does not own | Medium |
| C | Separate page `admin_empresa_sedes` linked from `admin_empresa` | Zero form/JS entanglement; own controller, CSRF, admin gating; cleanest POST routing; could be reached via the existing `fsc.extensions` button loop (`view/admin_empresa.html.twig:46-52`) | Registrable only through DB rows in `fs_extension` (no code-level API, `base/fs_controller.php:887-895`); weakest reading of "extend `admin_empresa.html.twig` so data can be entered" (a navigation hop, not inline entry) | Low/Medium |

**Recommendation: A (native panel), with C as the fallback shape.** Concretely:
*A new 6th panel `panel_sedes` placed **after** `panel_cuentasb` at
`admin_empresa.html.twig:197` — i.e. **outside** `<form name="f_empresa">` — containing one
`<form>` per sede with `{{ csrf_field() }}`, exactly like
`view/block/admin_empresa_bancos.html.twig:2-3`. A new nav `<li id="b_sedes">`, and 3 lines
each added to `comprobar_url()` (`:294-306`) and `mostrar_seccion()` (`:307-337`).*
Its POST must carry a marker such as `save_sede` and field names that do **not** collide with
`nombre`, checked **before** the `nombre` branch in `dispatchAction()`
(`controller/admin_empresa.php:254`).* If the design phase instead wants tpvmod to own the
section, the hook contract to freeze is:

- `admin_empresa_nav_after` — rendered inside the `<ul class="nav nav-pills nav-stacked">`
  right after the extension loop (`view/admin_empresa.html.twig:52`); context
  `{'fsc': fsc}`; the template renders an `<li>` with its own `onclick`.
- `admin_empresa_panel_after` — rendered immediately after `panel_cuentasb`
  (`view/admin_empresa.html.twig:197`), i.e. **outside** the main form; context `{'fsc': fsc}`.
- Registration from `tpvmod/Init.php` on `TwigInitEvent`, guarded by a static flag (precedent
  `plugins/catalogo_core/Init.php:202-219`), addressing the templates as
  `@tpvmod/...` (per-plugin namespace, `src/Core/Html.php:201`).
- The hook fragment must be self-contained: its own `<form>` + `{{ csrf_field() }}`, and its
  own show/hide JS that cooperates with `mostrar_seccion()` (wrap it inside `$(document).ready`,
  or simply navigate to a dedicated page instead of toggling a panel).

### Q3 — Where is the document-type → sede mapping persisted?

| # | Approach | Pros | Cons | Effort |
|---|---|---|---|---|
| **A** | **`fs_settings` keys, encapsulated in business_data's `empresa_sede` API** (recommended) | Zero schema/migration; zero-query read (`$GLOBALS['config2']` is already in memory when the PDF renders); idiomatic to the chosen UI host (`tpvmod_settings` writes `fs_settings` today, `controller/tpvmod_settings.php:48-75`); key names stay private to business_data because factura_pdf1 only calls the resolver; harmless dangling reference (the resolver looks the sede up anyway → fallback) | Mapping is config-file data rather than DB data, unlike the rest of business_data's domain; no FK/cascade semantics; slightly harder to inspect in DB tooling | Low |
| B | Dedicated table `empresa_sede_documento` (tipo PK → codsede) | One sede per type enforced by schema; enumerable/auditable; FK-ish cleanup on sede delete; testable with the same mock-`fs_db2` pattern as the sede model (no globals) | A 3rd new table for 4 scalar references; one extra query per PDF render (cacheable); more files (XML + model + install) | Medium |
| C | Per-sede flags `usar_en_*` | Fewest moving parts; the UI is a checkbox on the sede | Allows contradictory states (two sedes flagged for `factura`) with no defined precedence; conflates "sede" with "policy"; `factura` vs future `factura_simplificada`/`rectificativa` cannot be expressed | Low |
| D | 4 nullable columns on the `empresa` row | No new table; trivially readable | Pollutes the legacy single-row model and forces edits to `empresa::save()` (`model/empresa.php:387-465`) and `test()` (`:366-385`) — directly conflicts with the "preserve the legacy `empresa` API" constraint | Low |

**Recommendation: A.** Use four `fs_settings` keys — `empresa_sede_presupuesto`,
`empresa_sede_albaran`, `empresa_sede_pedido`, `empresa_sede_factura` — whose value is a
`codsede` (empty string / absent = no override). Expose them only through
`empresa_sede::mapping(): array`, `empresa_sede::setMappingFor(string $tipo, ?string $codsede): bool`
and the resolver, so no consumer ever sees a raw key name. Escalate to option B only if the
mapping later needs per-user/per-terminal scope or an audit trail.

### Q4 — How factura_pdf1 resolves the sede

| # | Approach | Pros | Cons | Effort |
|---|---|---|---|---|
| **A** | **Explicit `tipo` argument**: `RelatedModelsLoader::load(object $document, ?string $documentType = null)`; the 4 `*PrintView::build()` call sites pass their literal; the loader asks business_data for a replacement `\empresa` at `:42` and adopts it when non-null | Zero string sniffing; the literal vocabulary is already canonical (`resolveAdapter()`, `tpvmod_imprimir_url()`); the optional parameter keeps every existing caller and every test signature-compatible; the replacement point is a single line before `:65` so `pais` also resolves from the sede | 5 one-line edits in factura_pdf1 | Low |
| B | Infer the tipo from `get_class($document)` inside business_data | No call-site edits | Requires business_data to know `\FSFramework\model\*cliente` class names (it has `require = ""` and must not depend on clientes_facturacion); the legacy alias subclasses make `instanceof`/`str_ends_with` sniffing fragile; business_data would silently return no override for an unrecognized document | Low/Medium |
| C | Resolve the sede in `FacturaPdf1Controller` from the request `tipo` and pass an `\empresa` down | The tipo is already known at `:177-184` | Breaks the architecture: the controller does not build the print views; would require threading an object through adapters and views, touching 10+ files and the `PrintableDocumentInterface` contract | High |

**Recommendation: A.** Keep `RelatedModelsLoader::load()` as the single interception point;
replace lines `:42-45` with base-load + optional override:

```php
$empresa = (new \empresa())->get();
if (!$empresa instanceof \empresa) {
    throw new \RuntimeException('Empresa no configurada.');   // contract preserved
}
$sede = \empresa_sede::resolveForDocumentType($documentType ?? '', $empresa);
if ($sede instanceof \empresa) {
    $empresa = $sede;
}
```

The resolver must return a **freshly hydrated transient `\empresa`** (not a new row, nothing
persisted), so that:
- `instanceof \empresa` at `:43` still holds,
- the `\empresa` type hints in the 4 print-view constructors (`PresupuestoPrintView.php:45`)
  and `getEmpresa(): object` (`ClientDocumentPrintViewInterface.php:32`) are untouched,
- `PortedPdfDocument` needs **no** change.
Merge rule for `toEmpresa(\empresa $base)`: copy every non-empty sede field onto the transient
`empresa`; inherit from `$base` when the sede field is empty (safer than wholesale replacement
and preserves `logo`-independent behavior). Map sede `telefono → telefono1` so the phone
becomes visible (`PortedPdfDocument.php:545-554` reads `telefono1`/`telefono2`, which `empresa`
does not define). Do **not** touch `admin_factura_pdf1`; its `factura_pdf1_settings` row is a
separate concern and the mapping deliberately lives in tpvmod_settings.

### Q5 — Backwards compatibility with zero sedes

- `resolveForDocumentType()` returns `null` when (a) the tipo is unknown, (b) no mapping key is
  set, (c) the mapped `codsede` does not resolve to a row, or (d) `empresa_sedes` has no rows.
  In all four cases `load()` keeps the base `empresa` → **byte-identical PDF output** and the
  `RuntimeException('Empresa no configurada.')` behavior is unchanged.
- `empresa` and its cache (`model/empresa.php:83`, `:142-160`) are not modified → no behavior
  change for any other caller.
- The new table is created lazily on first instantiation and is never read unless a mapping key
  exists, so a fresh install that ignores the feature is unaffected.
- The new `admin_empresa` panel renders an "empty state" when there are no sedes; the 5 existing
  panels keep their current ids, so `mostrar_seccion()` (`:307-337`) behavior for them is
  unchanged.
- If the hook route is chosen instead: an unregistered hook renders `''`
  (`src/View/ViewHookRegistry.php:39-41`) → the template addition is a no-op.

---

## Recommendation

1. **Ownership:** `empresa_sede` (table `empresa_sedes`, PK `codsede varchar(6)` generated
   `MAX+1` à la `cuenta_banco::get_new_codigo()`, plus `descripcion varchar(100)` as the
   selector label and the 14 requested data columns typed to match `empresa.xml`) lives in
   **business_data**. business_data also owns the mapping storage and the resolver.
2. **Mapping:** four `fs_settings` keys, reachable only through business_data's API.
3. **Entry UI:** a **native** 6th panel in `admin_empresa` (outside `f_empresa`, own form +
   CSRF, distinct POST marker checked before the `nombre` branch). Do **not** introduce the
   `ViewHookRegistry` hook for this change; the exact hook contract is documented in Q2 above
   if the design phase decides tpvmod must own the section.
4. **Mapping UI:** `tpvmod_settings` (per the human decision), a thin client of
   `empresa_sede::setMappingFor()`. Keep the write path admin-only + CSRF-gated as today.
5. **Print resolution:** `RelatedModelsLoader::load()` + explicit optional `tipo` from the 4
   print views; the resolver returns a transient `\empresa` and all contracts stay intact.
6. **Field-effect honesty (must be resolved in the spec/design, not silently ignored):** of the
   14 requested fields, only `nombre`, `cifnif`, `direccion`, `apartado`, `codpostal`, `ciudad`,
   `provincia`, `codpais`, `email`, `web` are currently printed; `telefono` becomes printable
   via the `telefono1` mapping; **`fax`, `lema` and `pie_factura` have no consumer in
   `plugins/factura_pdf1/`** and would be stored-but-invisible. Either extend the renderer for
   them (a clearly scoped extra task) or narrow the editable field list and say so in the spec.

---

## Risks

1. **`dispatchAction()` collision** (`controller/admin_empresa.php:254`): a sede form posting
   `nombre` is routed into `handleEmpresaSave()` and would overwrite the base company row. The
   new branch must be keyed on a distinct marker and evaluated first. Highest-severity risk of
   this change; needs an explicit regression test.
2. **`mostrar_seccion()` hardcodes 5 panel ids** (`view/admin_empresa.html.twig:307-337`). A 6th
   panel must be added to both the hide list and the show branch, and to `comprobar_url()`
   (`:294-306`), or the panel will leak into other sections / be unreachable by deep link.
3. **Fields with no PDF effect** (`fax`, `lema`, `pie_factura`): a user could "configure" them
   and see no change on the printed document, violating the "must have real effect" spirit for
   those specific fields. Must be decided explicitly in the spec.
4. **`telefono1` asymmetry**: pre-populating `telefono1` for sedes makes the sede phone appear
   while the base company's phone stays invisible (the base row has `telefono`, not `telefono1`).
   Decide whether to also add a `telefono` fallback for the base company (a visible output
   change for every existing installation) or to accept the asymmetry.
5. **Print path coverage**: the override only affects `factura_pdf1`. `tpvmod_imprimir_url()`
   (`lib/tpvmod_modules.php:82-108`) prefers `ventas_imprimir` when `facturacion_base` is
   active; in that configuration the mapping would be stored but have **no** effect. Document
   this as an explicit scope boundary (facturacion_base does not exist in this repo copy).
6. **TPV thermal ticket header** (`controller/tpvmod.php:2249-2253`) keeps using the base
   company. Explicitly out of scope (the requirement says printed PDF), but a user may expect
   consistency.
7. **Review budget**: estimated ~1 000–1 150 changed lines across 3 repos, above the 800-line
   session budget → must be chained/sliced (see below).
8. **Unarchived core dependency**: the `view-hook-registry-core` change is implemented but not
   archived. The recommended design avoids depending on it; the hook alternative carries that
   dependency risk.
9. **Two table-name/path constraints** are easy to get wrong: the XML filename must equal the
   table name (`fs_model::get_base_dir()` `:508`, `get_xml_table()` `:524`) and the plugin must
   be **active** for the table to be resolvable (the relative `plugins/...` lookup at `:508`).
10. **`fs_var` is a trap if chosen instead of `fs_settings`**: `array_get()`'s default
    `$replace = TRUE` turns missing keys into `FALSE` (`model/fs_var.php:289-291`) and
    `array_save()` deletes on `FALSE` (`:309-312`). If the design switches stores, audit both.

---

## Tests (strict TDD)

**business_data** — `plugins/business_data/tests/` (auto-discovered by the root `Plugins`
suite; business_data has no `phpunit.xml` of its own today):
- `empresa_sede` hydration from a row (`new \empresa_sede([...])`), unknown/missing keys → defaults.
- `test()` rejects a blank `nombre` / invalid `codsede`; `no_html()` sanitization of each field.
- `save()`/`delete()`/`exists()`/`all()` SQL shape with an anonymous mock `fs_db2`
  (`select`/`exec`/`lastval`), mirroring `tests/BusinessDataModelTest.php` and
  `plugins/factura_pdf1/tests/Support` mocks. `install()` and `get_new_codigo()` covered.
- `resolveForDocumentType($tipo, $base)`: null for an unknown tipo; null when the mapping key is
  absent/empty; null when the mapped `codsede` does not exist (dangling reference); the sede's
  `\empresa` when mapped; **and `assertSame` on `$base` identity when unmapped** (the explicit
  zero-sedes backwards-compat assertion).
- `toEmpresa($base)`: `instanceof \empresa`; every non-empty sede field copied; empty sede fields
  fall back to `$base`; `telefono → telefono1`.
- `setMappingFor()` round-trip through `fs_settings` (set `$GLOBALS['config2']` in the test;
  `FS_TMP_NAME` is `test_` in `tests/bootstrap.php:29`).
- Static state to reset in `setUp()`/`tearDown()`: `empresa::$empresa_row_cache` (private static,
  `model/empresa.php:83`, via Reflection), `$GLOBALS['config2']`, `$GLOBALS['plugins']`, and
  `fs_model::$checked_tables` if the mock DB path interacts with it.

**factura_pdf1** — `plugins/factura_pdf1/tests/Unit/View/`:
- `RelatedModelsLoader::load($document, 'presupuesto')` adopts the resolved sede.
- `load($document)` (no tipo) and `load($document, 'unknown')` keep the base `empresa`
  (identity assertion) — the regression guard for existing output.
- `RuntimeException('Empresa no configurada.')` is still thrown when the base row is missing
  (contract preserved even with a sede mapped).
- `$pais` is resolved from the sede's `codpais` (assert the loader's `pais` entry) — proves the
  override sits before `RelatedModelsLoader.php:65`.
- Use the existing seams: `*PrintView::setResolversForTests()`
  (`PresupuestoPrintView.php:74-78`, `resetResolversForTests()` `:66-71`) and
  `AbstractClienteDocumentAdapter::setSharedRelatedModelsLoaderForTests()`
  (`Model/Adapters/AbstractClienteDocumentAdapter.php:51-58`).

**tpvmod** — `plugins/tpvmod/tests/`:
- The settings page persists/reads the 4 mapping keys; a non-existent `codsede` is rejected with
  an error and no write; empty selection clears the key.
- CSRF rejection path and admin-only gating (mirror the `tpvmod_settings` checks at
  `controller/tpvmod_settings.php:54-85`).
- Regression: with `facturacion_base` inactive the page must still render (the existing
  terminal-mode form is gated on `tpvmod_terminal_settings_available()`, `:49`; the mapping
  section must **not** inherit that gate because it depends on factura_pdf1, not
  facturacion_base).

**Framework test conventions to honor** (per `AGENTS.md` → Testing): namespace `Tests\*`,
anonymous `fs_model` subclasses with empty constructors when DB must be skipped, mock `fs_db2`
with an `escape_string()`-only stub for `fs_query_builder`, and **Reflection reset for static
state** — note `ViewHookRegistry::$hooks` (`src/View/ViewHookRegistry.php:19`) is only relevant
if the hook alternative is adopted; today the affected statics are
`empresa::$empresa_row_cache` and `$GLOBALS['config2']`.

---

## Work-unit / PR slicing (estimated changed lines)

Three repositories ⇒ at least three PRs. Recommended chained order:

| WU | Repo | Contents | Est. lines |
|---|---|---|---|
| **WU-1** | business_data | `model/empresa_sede.php` (CRUD + `toEmpresa()` + mapping API + resolver) + `model/table/empresa_sedes.xml` + `tests/EmpresaSedeModelTest.php` + `tests/EmpresaSedeResolutionTest.php` | ~450–570 |
| **WU-2** | factura_pdf1 | `RelatedModelsLoader::load()` + 4 one-line print-view call sites + `tests/Unit/View/RelatedModelsLoaderEmpresaSedeTest.php` | ~175 |
| **WU-3** | tpvmod | `tpvmod_settings` controller + view mapping section + `tests/TpvmodSedeMappingSettingsTest.php` | ~230 |
| **WU-4** | business_data | `admin_empresa` panel: controller branch/handlers + template nav/panel/JS + `view/block/admin_empresa_sedes.html.twig` + dispatch-collision regression test | ~180–230 |

- **Dependency order:** WU-1 → (WU-2 ∥ WU-3 → WU-4 is independent of WU-2/3). WU-2 is what
  proves the "real effect on the PDF" requirement end-to-end with deterministic tests, so it
  should land right after WU-1 and before the two UX units.
- **PR shape:** `PR-1 = WU-1` (business_data, ~450–570 lines — split into
  `WU-1a model+resolver+tests` / `WU-1b` if the owner wants ≤400), `PR-2 = WU-2`
  (factura_pdf1), `PR-3 = WU-3 + WU-4` is **not** possible (different repos) → `PR-3 = WU-3`
  (tpvmod), `PR-4 = WU-4` (business_data, second PR on the same repo, chained after PR-1).
- **Total:** ~1 035–1 200 lines > the 800-line session review budget ⇒ chained PRs are
  mandatory; never a single PR across repos.
- **Version bumps:** `plugins/business_data/fsframework.ini` (1.0.4 → minor),
  `plugins/tpvmod/fsframework.ini` (2.1.0 → minor),
  `plugins/factura_pdf1/fsframework.ini` (1.0.6 → minor). No new Composer dependency anywhere
  ⇒ the "`vendor/` must be committed" plugin rule does not apply here.

---

## Ready for Proposal

**Yes.** All seven open questions have a concrete recommendation with file:line evidence, and
the cross-repo shape is known. Two items must be settled by the human **before** the spec is
frozen (they change the requirement text, not the architecture):

1. `fax`, `lema` and `pie_factura` currently reach no PDF consumer — either narrow the editable
   field list or add an explicit renderer task for them.
2. The sede `telefono` should be published as `telefono1` (sede-only asymmetry) or the base
   company's `telefono` should also be printed (a visible change for every existing install).

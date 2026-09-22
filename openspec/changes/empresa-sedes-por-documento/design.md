# Design: empresa-sedes-por-documento

> Cross-plugin SDD owned by `plugins/tpvmod/openspec/` (main beneficiary).
> Inputs: `proposal.md`, `specs/empresa-sedes/spec.md` (10 req / 20 scenarios),
> `specs/tpvmod-config/spec.md` (2 req / 6 scenarios), `exploration.md` §1–§8.
> Format precedent: `plugins/tpvmod/openspec/changes/archive/2026-09-19-tpvmod-opcional-rapido/design.md`.
>
> **Repos touched (3 independent git repos):** `plugins/business_data`,
> `plugins/factura_pdf1`, `plugins/tpvmod`. Core (`base/`, `src/`, `controller/`,
> `model/`), core `openspec/`, `plugins/factura_pdf1/openspec/` and a new
> `plugins/business_data/openspec/` are **never** modified (`AGENTS.md` →
> "OpenSpec per Plugin").
>
> **Verified during design** (not assumed): `fs_model` carries
> `#[AllowDynamicProperties]` (`base/fs_model.php:32`) and the attribute is
> inherited, so an `empresa` clone may carry dynamic `telefono1` with no
> deprecation; `property_exists()` returns `true` for dynamic properties
> (`PortedPdfDocument.php:545-546` therefore sees them); `new \empresa([...])`
> and `clone` both work under `tests/bootstrap.php`; `admin_empresa.php` is
> includable in a DB-free test process and `BusinessDataModelTest` is green.

## Technical Approach

business_data gains a multi-row `empresa_sede` entity (table `empresa_sedes`,
PK `codsede` `MAX+1`) modelled line-for-line on `cuenta_banco` /
`model/table/cuentasbanco.xml`, plus four private `fs_settings` keys that map a
canonical document-type literal to a `codsede`. The entity owns **all** fusion
logic: `toEmpresa()` produces a **clone** of the base `\empresa` with non-empty
sede fields overlaid, and `withPrintablePhone()` publishes `telefono` as the
dynamic `telefono1` that `PortedPdfDocument` actually reads. factura_pdf1 keeps
its single company-resolution point (`RelatedModelsLoader::load()`), gains an
optional `?string $documentType`, and delegates to one new static seam
(`resolveEmpresa()`), so `pais` at `:65` resolves from the adopted company. The
two UIs are thin clients: a native 6th panel in business_data's own
`admin_empresa` (own forms outside `f_empresa`, distinct POST markers) and a
mapping section in `tpvmod_settings` driven by a pure `lib/` helper that is
**not** gated by `tpvmod_terminal_settings_available()`.

Zero sedes ⇒ resolver returns `null` before any DB access ⇒ base instance
preserved (`assertSame`) ⇒ output byte-identical **except** the deliberately
accepted base-company phone.

## Architecture Decisions

### AD-1 — Entity lives in business_data, built on the `cuenta_banco` precedent

**Choice**: `plugins/business_data/model/empresa_sede.php` + `model/table/empresa_sedes.xml`.
**Alternatives considered**: tpvmod (inverts the domain — factura_pdf1 would
depend on the TPV plugin for company data); a new `empresa_sedes` plugin (4th
repo, activation/versioning burden, new hard dependency edge).
**Rationale**: company identity is business_data master data; factura_pdf1
already `require_once`s business_data models by path
(`RelatedModelsLoader.php:82-84`) and business_data is the only plugin with
`require = ""`, so the edge count does not grow. Lazy creation needs no
migration (`fs_model::get_base_dir()` `:499-513`, `get_xml_table()` `:522-524`).

### AD-2 — Transient hydraton by **clone**, never by `new \empresa($partialRow)`

**Choice**: `toEmpresa(\empresa $base): \empresa` returns `clone $base` with the
non-empty sede fields overlaid.
**Alternatives considered**:
(a) `new \empresa($sedeRowArray)` — rejected: `hydrateFromRow()` reads
`$data['id']`, `$data['cifnif']`, `$data['nombre']`, `$data['administrador']`,
`$data['direccion']` **without `??`** (`empresa.php:97-103`) and a sede row has no
`id`/`administrador`, so it needs a fabricated full row; it also calls
`getMailService()->getConfig()` (`:129`), coupling the print path to the mail
container; and under the root `phpunit.xml` (`failOnWarning="true"`,
`failOnRisky="true"`) any undefined key becomes a red suite.
(b) `new \empresa()` then per-field copy — rejected: reimplements `clear()`'s
defaults and risks drifting from `empresa`'s property set.
**Rationale**: the clone inherits **every** base field for free (including
`id`, `codejercicio`, `coddivisa`, `codpago`, `codserie`, `codalmacen`, `logo`,
`email_config`) and touches no legacy API. `empresa::$empresa_row_cache`
(`empresa.php:83`) is only written by `get()`/`save()`/`delete()`
(`:144-148`, `:389`, `:469`): the clone path calls **none** of them, so the
request cache is neither read into nor polluted by the transient.

### AD-3 — The transient carries the base `id`

**Choice**: keep `clone $base`'s `id` (and every other non-overlaid field).
**Alternatives considered**: nulling `id` so `exists()` is false (the transient
is never saved either way) — rejected: it would degrade `getEmpresa(): object`
consumers that read `$empresa->id`, and buys nothing because we never call
`exists()`/`save()` on the transient.
**Rationale**: the transient must stay type- and shape-compatible with the base
object at every consumer (`ClientDocumentPrintViewInterface::getEmpresa(): object`,
the `\empresa`-typed print-view constructors, `PortedPdfDocument`). A documented
invariant covers the risk: **a transient `\empresa` is never passed to `save()`,
`delete()` or `exists()`**.

### AD-4 — `telefono → telefono1` mapping, and the base-phone fix, live in the model

**Choice**: `toEmpresa()` assigns `$empresa->telefono1` from the **merged**
`telefono` (sede value, else base value); a second static
`withPrintablePhone(\empresa $empresa): \empresa` sets `telefono1` from
`telefono` only when not already set, and returns the **same instance**.
`RelatedModelsLoader::resolveEmpresa()` calls it on the no-sede path.
**Alternatives considered**: (a) set `telefono1` inside `load()`
(factura_pdf1) — rejected: it would put the company-phone contract in the
printer plugin and force a duplicate in any future `ventas_imprimir` path;
(b) declare `telefono1`/`telefono2` on `empresa` — rejected: modifies the legacy
model's public surface, explicitly out of scope.
**Rationale**: `fs_model` has `#[AllowDynamicProperties]`, so the dynamic
property is legal and `property_exists()` sees it (`PortedPdfDocument.php:545`).
Keeping both halves of the phone rule in `empresa_sede` is the "fusion logic in
one place" requirement. `toEmpresa()` **overwrites** `telefono1` (deterministic
sede semantics); `withPrintablePhone()` is the conditional, identity-preserving
counterpart for the base path.

### AD-5 — Identity preserved on the no-override path (`assertSame`)

**Choice**: `resolveEmpresa()` returns the **very same** `\empresa` instance
when no sede resolves (only the dynamic `telefono1` is added).
**Alternatives considered**: always return a clone (uniform) — rejected: it
breaks the `assertSame` backwards-compat scenario and makes "nothing changed"
harder to prove.
**Rationale**: the object from `(new \empresa())->get()` is per-call
(`empresa.php:154` creates a fresh instance), so mutating it is safe and no
caller shares it.

### AD-6 — Mapping in four private `fs_settings` keys, not a table, not `fs_var`

**Choice**: `empresa_sede_presupuesto|albaran|pedido|factura`, value = `codsede`,
empty/absent = no override. Reachable only through `mapping()`,
`setMappingFor(string $tipo, ?string $codsede): bool` and the resolver. Keys live
in a `private const TIPOS` inside the model.
**Alternatives considered**: (a) a dedicated `empresa_sede_documento` table
(3rd new table for 4 scalars; extra query per render); (b) per-sede `usar_en_*`
flags (contradictory states, no precedence); (c) 4 nullable columns on
`empresa` (forces edits to `empresa::save()`/`test()`); (d) `fs_var` — rejected
on evidence: `fs_var::array_get($array, $replace = TRUE)` replaces missing keys
with `FALSE` and `array_save()` deletes on `FALSE`
(`model/fs_var.php:274-296`, `:304-319`), so "absent = no override" is unsafe
there (`exploration.md` §7/risk 10).
**Rationale**: `fs_settings` is a pure INI store already in memory at render
time (`base/fs_settings.php:43-58`, `save()` `:264-280`), is already the store
of the chosen UI host (`controller/tpvmod_settings.php:48-75`), and has an
in-business_data precedent (`admin_empresa.php:504-510`). Dangling references are
harmless: the resolver looks the row up anyway and falls back to `null`.

### AD-7 — `MAX+1` code generation, no retry loop

**Choice**: `get_new_codigo()` mirrors `cuenta_banco::get_new_codigo()`
(`cuenta_banco.php:169-178`) via `MAX($this->db->sql_to_int('codsede')) + 1`.
**Alternatives considered**: the bounded retry loop of the archive precedent
AD-7 — rejected here: the sede UI is a single-admin settings screen, the direct
in-repo precedent for this entity does not retry, and a collision surfaces as a
`false` return plus "Imposible guardar la sede" (fail-closed, no silent
corruption).
**Rationale**: honest concurrency caveat — two simultaneous sede creations can
compute the same code, the PK rejects the second, and the admin retries. The
6-char `varchar(6)` ceiling (>999999 sedes) is inherited from `cuenta_banco` and
documented, not silently assumed away.

### AD-8 — Native 6th panel, no hook, create form **not** in a modal

**Choice**: 6th `<li id="b_sedes">` + `<div id="panel_sedes">` after
`panel_cuentasb`, block `view/block/admin_empresa_sedes.html.twig` with one
`<form>` per sede (own `csrf_field()`) plus an always-visible create form.
**Alternatives considered**: (a) `render_hook`/`ViewHookRegistry` — rejected:
cross-plugin contract over data+UI that business_data owns, and the core
`view-hook-registry-core` change is implemented but **not archived**; (b) a
Bootstrap **modal** create form like `f_nueva_cuenta` — rejected on a concrete
trap: `#panel_sedes` is toggled with jQuery `.hide()`
(`admin_empresa.html.twig:307-337`), and a `.modal` nested in a `display:none`
ancestor does not render when `.modal('show')` runs.
**Rationale**: the owner edits its own template; `panel_cuentasb`
(`:195-197`) is the exact precedent for a panel outside `<form name="f_empresa">`
(`:56`) → `</form>` (`:194`); zero new JS, zero dependency on an unarchived
change.

### AD-9 — Marker-based dispatch, evaluated before `nombre`, via a pure seam

**Choice**: new public `admin_empresa::resolveAction(array $post, array $get): string`
returns `sede_save` | `sede_delete` | `empresa` | `logo` | `delete_logo` |
`cuenta_delete` | `cuenta_save` | `none`; `dispatchAction()` switches on it.
`resolveAction()` tests `sede_save` then `sede_delete` **before** `nombre`.
**Alternatives considered**: (a) inlining more `filter_input()` branches —
rejected: `filter_input()` reads the real SAPI request and **cannot be stubbed
in CLI/PHPUnit**, so the collision could never get a RED test; (b) a hidden
`save_sede` field together with a `delete_sede` button in one form — rejected:
the hidden field is always posted, so every delete would route to save.
**Rationale**: the highest-severity risk of this change (a sede form posting
`nombre` reaching `handleEmpresaSave()` → `applyEmpresaFields()`,
(`admin_empresa.php:254`, `:287-304`)) becomes a one-assertion regression test
that needs neither a DB nor a session (verified: the controller file loads in a
DB-free test process).

### AD-10 — Trap: never disable a marker-bearing submit button

**Choice**: the per-sede form has **two submit buttons**, each carrying its own
marker (`name="save_sede" value="1"`, `name="delete_sede" value="{{ codsede }}"`),
and **neither** uses the bancos `onclick="this.disabled = true; this.form.submit();"`
trick.
**Alternatives considered**: reusing `admin_empresa_bancos.html.twig:26`
verbatim — rejected: disabling the clicked submit button removes its name/value
from the payload, which would silently drop `save_sede` and hand the request to
the `nombre` branch, i.e. the very collision AD-9 exists to prevent.
**Rationale**: only the clicked submit button contributes its name/value, so the
marker is unambiguous; delete stays CSRF-gated because it lives in the same
`<form>` with `{{ csrf_field() }}` (unlike the legacy GET `delete_cuenta` at
`admin_empresa.php:258-261`).

### AD-11 — `empresa_sede::test()` requires `nombre` only; `descripcion` has a fallback

**Choice**: `test()` sanitizes every text field with `no_html()` and rejects a
blank `nombre` and a `codsede` not matching `/^[A-Z0-9]{1,6}$/i` (the
`cuenta_banco` rule). `descripcion` is optional; the selector renders
`{{ sede.descripcion ?: sede.nombre }}`.
**Alternatives considered**: making `descripcion` mandatory — rejected as a
TDD trap: the spec scenario only mandates the blank-`nombre` rejection, and a
test written from the spec (markup in `direccion`) would not necessarily set
`descripcion`.
**Rationale**: keeps the spec's rejection set exact while guaranteeing a usable
label (an empty selector label is a UX bug, not a data bug).

### AD-12 — Controller value extraction is preserved verbatim

**Choice**: only the **decision** moves to `resolveAction()`; `dispatchAction()`
still drives the handlers and every handler keeps its existing
`filter_input()`/superglobal reads; `applyEmpresaFields()`, `savePrintConfig()`,
`savePdfPluginSettings()` and `save_traducciones()` are untouched. `$this->empresa`
is never written by a sede handler.
**Alternatives considered**: passing extracted values through the switch —
rejected: it would rewrite four working handlers for no behavioural gain.
**Rationale**: minimizes blast radius on a legacy admin controller. Post-condition
for the collision regression: with `save_sede` present the token is `sede_save`,
so `handleEmpresaSave()` is unreachable and the base row is byte-identical.

### AD-13 — factura_pdf1 keeps one interception point, with a testable seam

**Choice**: `load(object $document, ?string $documentType = null): array`
replaces lines `42-45` with
`$empresa = self::resolveEmpresa((new \empresa())->get(), $documentType);` and
adds `public static function resolveEmpresa(\empresa|false $base, ?string $documentType): \empresa`
which keeps `\RuntimeException('Empresa no configurada.')`, short-circuits to
`withPrintablePhone($base)` when the type is `null`/`''`, and otherwise consults
`\empresa_sede::resolveForDocumentType()`.
**Alternatives considered**: (a) inline the logic in `load()` — rejected:
`load()` needs a live DB for `cliente`/`divisa`/`forma_pago`/`pais`, so none of
the four contract behaviours could get a RED test; (b) resolve the sede in
`FacturaPdf1Controller` — rejected: 10+ files and a change to
`PrintableDocumentInterface` (exploration Q4/C).
**Rationale**: the `\empresa` return type, the `instanceof` guard, the
`getEmpresa(): object` contract and `PortedPdfDocument` all stay unchanged;
the override still lands at `:42`, i.e. **before** the `pais` derivation at
`:65`, and a test asserts the adopted object's `codpais` plus the source order
`strpos('self::resolveEmpresa(') < strpos('$codpais =')`.

### AD-14 — Mapping persistence extracted into `lib/` (testability), ungated

**Choice**: new `plugins/tpvmod/lib/tpvmod_sede_mapping.php` with
`tpvmod_sede_mapping_submitted(array $post): bool` and
`tpvmod_save_sede_mapping(array $post, ?callable $setMappingFor = null, ?callable $sedeExists = null): array`
(all-or-nothing validation, then one `setMappingFor()` per type).
`tpvmod_settings::private_core()` gains a branch **before** the terminal gate
with its own `isCsrfValid()` check.
**Alternatives considered**: (a) persistence inline in the controller —
rejected: `tpvmod_settings` extends `fs_controller` (session/user required), so
it is not instantiable in a DB-free test, and the plugin PHPUnit `<source>`
only covers `lib/` (`plugins/tpvmod/phpunit.xml`) — the archive precedent AD-1
made exactly this split for the same reason; (b) inheriting the
`tpvmod_terminal_settings_available()` gate — rejected: it depends on
`facturacion_base` (`controller/tpvmod_settings.php:49`, `:56-60`) and is
unrelated to sedes.
**Rationale**: the AD-1 rule of the plugin ("logic in `lib/`, controller only
dispatches") is preserved, both scenarios of the added `tpvmod-config`
requirement become unit-testable, and the existing terminal flow is left
byte-identical because the new branch returns before reaching it.

## Data Flow

### Resolution at print time

```
FacturaPrintView::build() ─┐
AlbaranPrintView::build()  ├─→ RelatedModelsLoader::load($doc, '<literal>')
Pedido/Presupuesto::build()┘        │
                                    ├─ (new \empresa())->get() ──► base | false
                                    ├─ resolveEmpresa(base, tipo)
                                    │     ├─ !base instanceof \empresa → RuntimeException('Empresa no configurada.')
                                    │     ├─ tipo null/'' ──────────► withPrintablePhone(base)   [same instance]
                                    │     └─ \empresa_sede::resolveForDocumentType(tipo, base)
                                    │            ├─ mapping()[tipo] ?? null → null (NO DB access)
                                    │            ├─ get(codsede) → false     → null
                                    │            └─ sede → toEmpresa(base)   → clone + overlay + telefono1
                                    ├─ $empresa = $empresa|$sede
                                    └─ :65  $codpais = $empresa->codpais !== '' ? … : $cliente->codpais
                                                └─ \pais::get($codpais)   ← sede's country
```

### Write paths

```
admin_empresa  POST save_sede=1   ─→ resolveAction() = 'sede_save'   ─→ handleSaveSede()   ─→ empresa_sede::save()
admin_empresa  POST delete_sede=X ─→ resolveAction() = 'sede_delete' ─→ handleDeleteSede() ─→ empresa_sede::delete()
admin_empresa  POST nombre=…      ─→ resolveAction() = 'empresa'     ─→ handleEmpresaSave()  (unchanged)
admin_empresa  GET  list          ─→ loadSedes() ─→ $this->sedes  ─→ block/admin_empresa_sedes.html.twig

tpvmod_settings POST save_sede_mapping=1 ─→ (branch before the terminal gate)
      └─ isCsrfValid() ? ─(no)─→ error, no write
                         └(yes)→ tpvmod_save_sede_mapping($_POST)
                                   ├─ normalize the 4 values → null | codsede
                                   ├─ any non-null code not in empresa_sedes → all-or-nothing reject
                                   └─ empresa_sede::setMappingFor(tipo, codsede) ×4 → fs_settings::save()
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `plugins/business_data/model/table/empresa_sedes.xml` | Create | 13 columns; PK `codsede`. Filename **must** equal the table name (`fs_model.php:508`, `:524`). |
| `plugins/business_data/model/empresa_sede.php` | Create | Entity + fusion + mapping API + resolver (contracts below). |
| `plugins/business_data/controller/admin_empresa.php` | Modify | Guarded `require_once` of the model; `public $empresa_sede;` `public $sedes = [];`; `initializeModels()` instantiates the entity; `loadSedes()` after `dispatchAction()`; `resolveAction()` + rewritten `dispatchAction()`; `handleSaveSede()`, `handleDeleteSede()`, `sedeFieldsFromPost()`. |
| `plugins/business_data/view/admin_empresa.html.twig` | Modify | 4 edit sites (nav, panel, `comprobar_url()`, `mostrar_seccion()`). |
| `plugins/business_data/view/block/admin_empresa_sedes.html.twig` | Create | Per-sede `<form>` (2 markers) + create form + empty state. |
| `plugins/business_data/tests/EmpresaSedeModelTest.php` | Create | Schema/hydration/CRUD/sanitization. |
| `plugins/business_data/tests/EmpresaSedeResolutionTest.php` | Create | Mapping + resolution + `toEmpresa()` + phone. |
| `plugins/business_data/tests/AdminEmpresaDispatchActionTest.php` | Create | Dispatch-collision regression. |
| `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php` | Modify | `$documentType` param; `:42-45` → 1 line; `resolveEmpresa()`; `empresa_sede` require. |
| `plugins/factura_pdf1/Model/View/AlbaranPrintView.php` | Modify | `:181` pass `'albaran'`. |
| `plugins/factura_pdf1/Model/View/PedidoPrintView.php` | Modify | `:181` pass `'pedido'`. |
| `plugins/factura_pdf1/Model/View/PresupuestoPrintView.php` | Modify | `:181` pass `'presupuesto'`. |
| `plugins/factura_pdf1/Model/View/FacturaPrintView.php` | Modify | `:219` pass `'factura'`. |
| `plugins/factura_pdf1/tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php` | Create | Override / fallback / throw / `pais` / signature. |
| `plugins/tpvmod/lib/tpvmod_sede_mapping.php` | Create | Pure mapping helpers (inside the plugin PHPUnit `<source>`). |
| `plugins/tpvmod/controller/tpvmod_settings.php` | Modify | Guarded model require; `$sedes`, `$sede_mapping`; ungated POST branch; `saveSedeMapping()`. |
| `plugins/tpvmod/view/tpvmod_settings.html.twig` | Modify | Mapping section outside the terminal gate. |
| `plugins/tpvmod/tests/TpvmodSedeMappingSettingsTest.php` | Create | Pure helpers + view/controller contract. |
| `plugins/business_data/fsframework.ini` | Modify | `1.0.4` → `1.1.0`. |
| `plugins/factura_pdf1/fsframework.ini` | Modify | `1.0.6` → `1.1.0`. |
| `plugins/tpvmod/fsframework.ini` | Modify | `2.1.0` → `2.2.0`. |

### Schema: `model/table/empresa_sedes.xml`

Types copied from `empresa.xml` for every shared column; no `fax`, `lema`,
`pie_factura`, `horario`, `nombrecorto` (no consumer in `plugins/factura_pdf1/`,
`PortedPdfDocument.php:527-568`).

| Column | Type | Null | Source |
|---|---|---|---|
| `codsede` | `character varying(6)` | NO | PK, `MAX+1` |
| `descripcion` | `character varying(100)` | YES | selector label (fallback `nombre`) |
| `nombre` | `character varying(100)` | NO | `empresa.xml:125-129` |
| `cifnif` | `character varying(30)` | NO | `:19-23` |
| `direccion` | `character varying(100)` | NO | `:79-83` |
| `apartado` | `character varying(10)` | YES | `:14-18` |
| `codpostal` | `character varying(10)` | YES | `:64-68` |
| `ciudad` | `character varying(100)` | YES | `:24-28` |
| `provincia` | `character varying(100)` | YES | `:140-144` |
| `codpais` | `character varying(20)` | YES | `:59-63` |
| `email` | `character varying(100)` | YES | `:84-88` |
| `web` | `character varying(100)` | YES | `:160-164` |
| `telefono` | `character varying(20)` | YES | `:155-159` |

Constraint: `empresa_sedes_pkey` → `PRIMARY KEY (codsede)`.

### Exact edits: `view/admin_empresa.html.twig`

| # | Site | Edit |
|---|------|------|
| 1 | after `:35` (`</li>` of `b_cuentasb`) | insert `<li id="b_sedes"><a href="#sedes" onclick="mostrar_seccion('sedes');">…Sedes</a></li>` |
| 2 | after `:197` (`</div>` of `panel_cuentasb`, outside `</form>` at `:194`) | insert `<div id="panel_sedes">{% include 'block/admin_empresa_sedes.html.twig' %}</div>` |
| 3 | `comprobar_url()` `:294-306` | insert `else if (window.location.hash.substring(1) == 'sedes') { mostrar_seccion('sedes'); }` before the final `else` |
| 4 | `mostrar_seccion()` `:307-337` | after `:310` add `$("#panel_sedes").hide();`; after `:315` add `$("#b_sedes").removeClass('active');`; after the `cuentasb` branch (`:324`) add `else if (id == 'sedes') { $("#panel_sedes").show(); $("#b_sedes").addClass('active'); }` |

No other line changes; the 5 existing panel ids and their JS branches are
untouched (zero-sede regression guarantee for them).

## Interfaces / Contracts

```php
// plugins/business_data/model/empresa_sede.php  (extends \fs_model; table empresa_sedes)
public function __construct($data = FALSE)                       // $data row → hydrate; else clear()
public function test(): bool                                     // no_html() on every text field;
                                                                 // reject blank nombre, invalid codsede
public function save()                                           // MAX+1 when new; INSERT/UPDATE
public function delete()                                         // DELETE ... WHERE codsede = var2str()
public function exists()                                         // bool, by codsede
public function all(): array                                     // ORDER BY descripcion ASC, codsede ASC
public function get($cod)                                        // self|FALSE
public function get_new_codigo(): string                          // MAX(sql_to_int(codsede)) + 1
public function url(): string                                    // index.php?page=admin_empresa[&codsede=..]#sedes
public function toEmpresa(\empresa $base): \empresa              // clone + overlay + telefono1
public static function mapping(): array                          // ['presupuesto'=>?string,'albaran'=>..,'pedido'=>..,'factura'=>..]
public static function setMappingFor(string $tipo, ?string $codsede,
                                     ?callable $sedeLoader = null): bool
public static function resolveForDocumentType(string $tipo, \empresa $base,
                                     ?callable $sedeLoader = null): ?\empresa
public static function withPrintablePhone(\empresa $empresa): \empresa

private const TIPOS = [
    'presupuesto' => 'empresa_sede_presupuesto',
    'albaran'     => 'empresa_sede_albaran',
    'pedido'      => 'empresa_sede_pedido',
    'factura'     => 'empresa_sede_factura',
];
```

The `?callable $sedeLoader` is the **only** test seam; default
`(new self())->get($cod)`. It must NOT be reachable from
`resolveForDocumentType`'s early returns: the mapping lookup happens **first**,
so unknown-`tipo` / absent-key / empty-key never instantiate the model and never
touch the DB (this is what makes zero-sede installs byte-identical and lets the
RED tests run DB-free).

```php
// plugins/factura_pdf1/Model/View/RelatedModelsLoader.php
public static function load(object $document, ?string $documentType = null): array
public static function resolveEmpresa(\empresa|false $base, ?string $documentType): \empresa

// plugins/business_data/controller/admin_empresa.php
public static function resolveAction(array $post, array $get): string
public static function sedeFieldsFromPost(array $post): array

// plugins/tpvmod/lib/tpvmod_sede_mapping.php
function tpvmod_sede_mapping_submitted(array $post): bool
function tpvmod_normalize_sede_mapping(array $post): array          // ['factura' => ?string, …]
function tpvmod_save_sede_mapping(array $post,
        ?callable $setMappingFor = null, ?callable $sedeExists = null): array
    // ['ok' => bool, 'errors' => list<string>, 'saved' => list<string>]
```

`load()`'s replacement of lines `42-45` (one statement) and the new method
(placed between `load()` and `requireRelatedModels()`):

```php
        $empresa = self::resolveEmpresa((new \empresa())->get(), $documentType);

    public static function resolveEmpresa(\empresa|false $base, ?string $documentType): \empresa
    {
        self::requireRelatedModels();

        if (!$base instanceof \empresa) {
            throw new \RuntimeException('Empresa no configurada.');   // contract preserved
        }

        $sede = $documentType === null || $documentType === ''
            ? null
            : \empresa_sede::resolveForDocumentType($documentType, $base);

        return $sede instanceof \empresa
            ? $sede
            : \empresa_sede::withPrintablePhone($base);                // same instance
    }
```

`requireRelatedModels()` gains, next to the existing `empresa` require at `:82-84`:

```php
        if (!class_exists('empresa_sede', false)) {
            require_once FS_FOLDER . '/plugins/business_data/model/empresa_sede.php';
        }
```

**Why explicit `require_once` and not the model autoloader** (evidence):
`fs_model_autoloader::register()` snapshots `$GLOBALS['plugins']` into
`self::$modelDirs` at registration (`fs_model_autoloader.php:88`, `:140-157`),
and `tests/bootstrap.php` sets `$GLOBALS['plugins'] = []` (`:64-66`) *before*
`register()` (`:81`) — verified by probe: plugin models are not autoloadable in
the test process, so a `require_once` by path is mandatory for the tests.
factura_pdf1 already uses exactly this mechanism for `empresa`/`forma_pago`
(`RelatedModelsLoader.php:82-93`); tpvmod must copy it
(`dirname(__DIR__, 3) . '/plugins/business_data/model/empresa_sede.php'`,
guarded). business_data's own `admin_empresa` also adds the guarded include
(`dirname(__DIR__) . '/model/empresa_sede.php'`) for determinism.

`tpvmod_save_sede_mapping()` semantics: (1) normalize the four posted values
(`''`/absent → `null`); (2) if any non-`null` code fails `$sedeExists`, return
`['ok' => false, 'errors' => ['La sede seleccionada no existe.'], 'saved' => []]`
and write **nothing**; (3) apply `setMappingFor()` for all four (a `false`
return aborts with `'No se pudo guardar el mapeo de sedes.'`); (4) `['ok' =>
true, 'errors' => [], 'saved' => [4 tipos]]`. `setMappingFor()` maps
`null`/`''` to `set($key, '')` + `save()`, so an empty selection clears the
override while `mapping()` keeps reporting `null`.

## Testing Strategy

Strict TDD (`plugins/tpvmod/openspec/config.yaml` → `strict_tdd: true`): RED
first, per repo. Conventional resets in `setUp()`/`tearDown()`:
`$GLOBALS['config2']`, `$GLOBALS['plugins']`, `empresa::$empresa_row_cache`
(Reflection, `empresa.php:83`), `fs_model::$checked_tables` if the spy path
touches it. DB-free model tests use an **anonymous subclass with an empty
constructor** injecting a spy `fs_db2` (`select`/`exec`/`lastval`/`sql_to_int`,
`exec($sql, $transaction = null, $params = [], $batch = false)` — the exact
signature matters; see `phpunit.xml` note 2 and the precedent
`plugins/catalogo_core/tests/CaracteristicaModelTest.php`).

### business_data (`Plugins` suite of the root `phpunit.xml`; no plugin `phpunit.xml` today)

`tests/EmpresaSedeModelTest.php`
1. hydrates from a full row; optional keys default (scenario "Generated code…").
2. `test()` rejects a blank `nombre` (scenario "Sanitization and validation").
3. `test()` `no_html()`-sanitizes every field; `<script>` gone (same scenario).
4. XML inventory: `model/table/empresa_sedes.xml` exists, its column set equals
   the 13 narrowed columns, and `fax`/`lema`/`pie_factura`/`horario`/`nombrecorto`
   are absent (scenario "No field without a PDF consumer"; also guards
   filename == table name, `fs_model.php:508`, `:524`).
5. `save()` new row: `codsede` empty → spy `MAX` row → `INSERT` contains the
   generated code; `sql_to_int('codsede')` used (scenario "Generated code…").
6. `save()` existing row → `UPDATE`; `delete()` → `DELETE … WHERE codsede = '3'`.
7. `all()` hydrates N rows and issues `ORDER BY descripcion ASC, codsede ASC`.
8. `url()` points at `admin_empresa` with the `#sedes` fragment.
9. `install()` returns `''` (lazy creation, no migration code).

`tests/EmpresaSedeResolutionTest.php`
10. unknown tipo → `null`, and the injected loader is **never called**.
11. absent key → `null` (no DB access). 12. empty key (`''`) → `null`.
13. dangling code → `null` (loader returns null).
14. mapped sede → `instanceof \empresa`, sede fields present, spy DB recorded no
    `INSERT`/`UPDATE` on `empresa` (scenario "Mapped sede is hydrated
    transiently" + "No persistence side effect").
15. `toEmpresa()`: non-empty sede field wins, empty field inherits `$base`,
    `assertSame`-free shape, base object unmodified (scenario "Field merge").
16. `toEmpresa()`: `telefono1 === sede telefono` (scenario "Field merge and
    phone mapping").
17. `withPrintablePhone()`: sets `telefono1` from `telefono`; returns the same
    instance; does not overwrite an existing non-empty value.
18. `setMappingFor()` unknown tipo → `false` (scenario "Unknown type is not
    accepted"); invalid code → `false` and `$GLOBALS['config2']` unchanged
    (scenario "Invalid codsede is rejected").
19. `setMappingFor('factura', null)` → key `''`, `mapping()['factura'] === null`
    (scenario "Empty selection clears the override").
20. round trip `setMappingFor('factura','S1')` → `mapping()['factura'] === 'S1'`
    and `array_keys(mapping())` is exactly the 4 canonical types.
21. `resolveForDocumentType($tipo, $base)` returns `null` for each of the four
    null cases (scenario "Every null case"); base identity untouched
    (scenario "Identity is preserved", model level).

`tests/AdminEmpresaDispatchActionTest.php`
22. `resolveAction(['save_sede' => '1', 'nombre' => 'Sede X'], []) === 'sede_save'`
    — the collision regression (scenarios "Sede form does not touch the base
    row" + "Reserved order under regression").
23. `resolveAction(['delete_sede' => '3', 'nombre' => 'X'], []) === 'sede_delete'`.
24. full sede POST (all 11 fields + `save_sede`) → `'sede_save'` → proves
    `handleEmpresaSave()` is unreachable on that path, hence the base row is
    byte-identical.
25. existing branches unchanged: `nombre`→`empresa`, `logo`, `delete_logo` (GET),
    `delete_cuenta`, `iban`, none.
26. `sedeFieldsFromPost()` returns only the 11 sede field names (no
    `contintegrada`, `codalmacen`, `codserie`, …).

### factura_pdf1 (`ddev exec php vendor/bin/phpunit -c plugins/factura_pdf1/phpunit.xml`)

`tests/Unit/RelatedModelsLoaderEmpresaSedeTest.php`
27. base `false` → `\RuntimeException('Empresa no configurada.')` — even with a
    mapping key present (scenario "RuntimeException preserved").
28. `resolveEmpresa($base, null)` → `assertSame($base, …)` and
    `telefono1 === $base->telefono` (scenarios "Unmapped or no-type call keeps
    base identity" + "Base phone is printed").
29. `resolveEmpresa($base, 'factura_simplificada')` (unknown) → `assertSame`
    (canonical vocabulary, scenario "Unknown type is not accepted").
30. mapped sede (loader stub) → returned object carries the sede `nombre`,
    `codpais` and `telefono1`; `$base` unchanged (scenarios "Sede wins…",
    "Mapped sede…", "Field merge and phone mapping").
31. mapping present but dangling → `assertSame($base, …)`.
32. `resolveEmpresa(...)->codpais` is the sede's (`'ESP'`) and the source order
    `strpos('self::resolveEmpresa(') < strpos('$codpais =')` (scenario "Sede wins
    and its country resolves"; proves the override precedes `:65`).
33. Reflection contract: `load()` parameter 2 is named `documentType`, optional,
    default `null`; the file still contains `RuntimeException('Empresa no
    configurada.')`.
34. Reflection: `empresa::$empresa_row_cache` is unchanged across
    `resolveEmpresa()` (no cache pollution).
35. `resolveEmpresa()` does not `INSERT`/`UPDATE` anything (spy DB on the base).

### tpvmod (`ddev exec php vendor/bin/phpunit -c plugins/tpvmod/phpunit.xml`)

`tests/TpvmodSedeMappingSettingsTest.php`
36. `tpvmod_sede_mapping_submitted(['save_sede_mapping' => '1'])` true; `[]` false.
37. `tpvmod_normalize_sede_mapping()`: `sede_factura='S1'`, others absent/`''` →
    `['presupuesto'=>null,'albaran'=>null,'pedido'=>null,'factura'=>'S1']`.
38. round trip via the injected `$setMappingFor` spy: 4 calls, exact
    `(tipo, codsede)` pairs, `ok === true` (scenario "Round-trip read and
    write").
39. invalid code with `$sedeExists` false → `ok === false`, `saved === []`,
    **`$setMappingFor` never called** (scenario "Invalid codsede is rejected").
40. `sede_factura=''` → `setMappingFor('factura', null)` and `ok === true`
    (scenario "Empty selection clears the override").
41. `$setMappingFor` returning `false` → `ok === false` + error message
    (partial-write semantics documented).
42. view contract (grep style, precedent `TpvmodTwigTemplatesTest`): the mapping
    form has `{{ csrf_field() }}`, a hidden `save_sede_mapping`, the 4 select
    names, an explicit empty option ("no override / base company"), and its
    block starts **after** the terminal gate `{% endif %}` (scenario "Mapping
    works with facturacion_base inactive" + "Escaped labels").
43. controller contract: `strpos('save_sede_mapping') <
    strpos('terminal_settings_available')` in `controller/tpvmod_settings.php`
    (proves the new branch is not gated); the file contains
    `isCsrfValid()` inside the mapping path (scenario "CSRF and admin gate").
44. no `|raw` on `sede.descripcion`; template renders `{{ sede.descripcion }}`
    (scenario "Escaped labels").

Smoke (manual, `config.yaml`): create a sede, map it to `factura`, print a
factura → sede header incl. phone; unmap → base header with the base phone;
save a sede without changing the base company.

## Threat Matrix

| Boundary | Applicability | Expected safe / failure behaviour | RED test |
|---|---|---|---|
| HTTP routing (new POST actions: `save_sede`, `delete_sede`, `save_sede_mapping`) | Applicable | Correct marker ⇒ the sede/mapping handler runs; administrator-only access is declared by the class-level `#[AdminOnly]` attribute on the controller, resolved by `fs_page::is_admin_only_class()` through the attribute name string (the obsolete 4th `$admin` constructor argument is ignored and is not a gate); every handler validates CSRF and writes nothing on failure. | 22–26 (dispatch), 39, 43 (tpvmod CSRF), `TpvmodSettingsAdminOnlyTest` (admin-only declaration) |
| XSS / render boundary | Applicable | All sede output through `{{ }}`; no `|raw` on user text; `test()` `no_html()`-sanitizes on write (defense in depth); `descripcion` fallback `?: sede.nombre` still escaped. | 3, 42, 44 |
| SQL injection | Applicable | `var2str()` on every value/PK; `sql_to_int('codsede')` in `get_new_codigo()`; no string concatenation of user input. | 5, 6, 7 |
| Shell / subprocess | N/A — none introduced. | — | — |
| VCS / PR automation | N/A — none introduced. | — | — |
| Executable-file classification | N/A — none introduced. | — | — |
| Process integration | N/A — none introduced. | — | — |

## Migration / Rollout

No migration, no feature flag. `empresa_sedes` is created lazily by the first
instantiation of `empresa_sede` from `model/table/empresa_sedes.xml`
(`fs_model.php:105-135`) — the same path as `cuentasbanco`, and it is
instantiated by `admin_empresa` (panel) and by the mapping path only. Installs
that configure zero sedes never resolve a sede and never read the table; the
only output delta is the base-company phone (accepted, AD-4).

Per-repo additive rollout with plugin `fsframework.ini` bumps; no new Composer
dependency anywhere ⇒ the "`vendor/` must be committed" rule does not apply.
Rollback is a per-repo revert: reverting **factura_pdf1** restores the previous
output (it owns the only output-affecting delta); reverting business_data drops
the model/mapping API and leaves a harmless unused table (or the panel, if only
the panel PR is reverted); the four `fs_settings` keys are inert with no sede.

## Work-Unit / PR Slicing (800-line review budget, `ask-on-risk`)

**Chained PRs are REQUIRED, and a single PR is impossible**: `plugins/business_data`,
`plugins/tpvmod` and `plugins/factura_pdf1` are three **independent git
repositories** (each has its own `.git`; `plugins/*` is gitignored by the root
repo, `.gitignore:38` — `exploration.md` §6), and no PR can span two of them.
On top of that, the total change (~1 100–1 250 lines) exceeds the 800-line
session review budget, so `ask-on-risk` must not be resolved by "one big PR":
the reviewable unit is one repo-slice per session.

| WU | Repo | Contents | Depends on | Est. lines |
|---|---|---|---|---|
| **WU-1** | business_data | `model/empresa_sede.php` + `model/table/empresa_sedes.xml` + `EmpresaSedeModelTest` + `EmpresaSedeResolutionTest` | — | ~500–570 |
| **WU-2** | factura_pdf1 | `RelatedModelsLoader` + 4 one-line call sites + `RelatedModelsLoaderEmpresaSedeTest` | WU-1 (public API) | ~175 |
| **WU-3** | tpvmod | `lib/tpvmod_sede_mapping.php` + `tpvmod_settings` controller + view + `TpvmodSedeMappingSettingsTest` | WU-1 (public API) | ~250 |
| **WU-4** | business_data | `admin_empresa` controller + template + `block/admin_empresa_sedes.html.twig` + `AdminEmpresaDispatchActionTest` | WU-1 (public API) | ~200–230 |

| PR | = WU | Repo | Est. lines | ≤ 800 alone? | Notes |
|---|---|---|---|---|---|
| **PR-1** | WU-1 | business_data | ~500–570 | Yes | Largest slice; must NOT absorb WU-4. If the owner wants ≈400, split `model+resolver` / `tests` — **not** recommended (tests belong with the code). |
| **PR-2** | WU-2 | factura_pdf1 | ~175 | Yes | Lands second: it is what proves the real PDF effect. |
| **PR-3** | WU-3 | tpvmod | ~250 | Yes | Independent of PR-2. |
| **PR-4** | WU-4 | business_data | ~200–230 | Yes | Chained after PR-1 on the same repo. |
| **Total** | | 3 repos | **~1 125–1 225** | — | > 800 ⇒ chained PRs mandatory. |

No PR exceeds 800 lines by itself; the session budget is protected because each
PR is reviewed in its own session. Version bumps ship with their own repo's PR
(business_data: PR-1; factura_pdf1: PR-2; tpvmod: PR-3).

## Risks / Open Questions

| Risk | Severity | Mitigation in this design |
|---|---|---|
| `dispatchAction()` collision overwrites the base company row | High | AD-9 + AD-10; tests 22–26; no disabled marker buttons. |
| `mostrar_seccion()` hardcodes 5 panel ids | Med | Exact 4 edit sites (no line left to guess); 5 existing ids untouched. |
| Base-company phone now printed = intentional visible change | Med | Accepted (proposal decision 3), scoped to `telefono1` only, flagged in Migration; test 28. |
| Byte-identical output with zero sedes | Med | Resolver early-returns before any DB access; AD-5 identity; test 28/29/31. |
| Transient built from a partial row (undefined keys) | Med | AD-2 clone approach removes the class of bug; no per-field `$data[...]` assembly. |
| `filter_input()` not stubbable in tests | Med | AD-9 pure `resolveAction()` seam. |
| Mapping inert under `ventas_imprimir` (facturacion_base) | Low | Documented scope boundary; only `factura_pdf1` covered. |
| TPV thermal ticket header ignores sedes | Low | Explicitly out of scope (requirement is the printed PDF). |
| XML filename ≠ table name, or plugin inactive | Low | `empresa_sedes.xml`; test 4; lazy creation only. |

### Open questions (non-blocking, for the human)

- [ ] **Accepted product change, please confirm explicitly**: with a base
      company phone set, every PDF starts printing it (AD-4). This is the single
      documented exception to byte-identical output and it affects all existing
      installs. The proposal chose this; the design implements it without a
      feature flag.
- [ ] Should the sede ever drive the document **logo**? Today
      `insertCompanyLogo()` resolves the logo from system branding
      (`PortedPdfDocument.php:398-410`), so a sede's identity and its logo can
      diverge. Out of scope here, but it is the next most visible gap.
- [ ] Should `empresa_sede` (and its mapping) eventually move to a dedicated
      `empresa_sede_documento` table if the mapping needs per-user or
      per-terminal scope or an audit trail? AD-6 records the escalation path.

## Requirement → Design Traceability

| Requirement (spec) | Design | Verification |
|---|---|---|
| Multi-row sede entity and lazy schema | AD-1, AD-7, File Changes/XML | 1, 4, 5, 9 |
| Editable field set narrowed to PDF-consuming fields | XML table, AD-11 | 3, 4 |
| Mapping storage encapsulated in business_data | AD-6 | 18–20 |
| Canonical document-type vocabulary | `private const TIPOS`, AD-13 | 10, 29 |
| Document-type resolution contract | Interfaces, AD-2/3/4 | 10–17, 21, 30, 31 |
| Print integration contract | AD-13, `resolveEmpresa()` | 27–33 |
| Zero-sede backwards compatibility | AD-5, Data Flow | 21, 28, 29, 31 |
| Base-company phone becomes printable | AD-4 | 17, 28, 34, 35 |
| Security of sede flows | Threat Matrix, AD-10, AD-14 | 22–26, 39, 42–44 |
| Sede save never overwrites the base company | **AD-9**, AD-10, AD-12 | 22–26 |
| Sede mapping section on the settings page | AD-14, File Changes | 36–44 |
| Mapping section independent of the `facturacion_base` gate | AD-14 | 42, 43 |

# Proposal: empresa-sedes-por-documento

## Intent

Company identity is a single-row model: `empresa::get($id)` ignores `$id` and
returns the first row (`plugins/business_data/model/empresa.php:142-160`). Every
printed PDF resolves that one company at a single point,
`RelatedModelsLoader::load()` (`plugins/factura_pdf1/Model/View/RelatedModelsLoader.php:42`),
so all documents of an installation carry the same identity.

Real installations operate several **sedes** (branches / legal identities) that
must appear depending on the document type (an albarán from one, a factura from
another). This change introduces multiple full company records selectable per
document type and printed instead of the base company.

While wiring the sede phone we found a latent bug: `PortedPdfDocument` prints
`telefono1`/`telefono2` (`:545-554`), properties `empresa` never defines, so the
base company phone is **never** printed today. Decision 3 fixes that too.

## Scope

### In Scope

- New multi-row `empresa_sede` entity (table `empresa_sedes`, PK `codsede`
  `MAX+1`) in **business_data**, modeled on
  `cuenta_banco` / `model/table/cuentasbanco.xml`. Lazy table creation from
  `model/table/empresa_sedes.xml` — no migration code.
- Editable sede fields **narrowed** to those that actually reach the PDF:
  `nombre`, `cifnif`, `direccion`, `apartado`, `codpostal`, `ciudad`,
  `provincia`, `codpais`, `email`, `web`, `telefono`. Some base-`empresa` fields
  (`fax`, `lema`, `pie_factura`, `horario`, `nombrecorto`) are **not part of a
  sede**: they have no consumer in `plugins/factura_pdf1/` (exploration §4).
- Document-type → sede mapping in four `fs_settings` keys
  (`empresa_sede_presupuesto|albaran|pedido|factura`; value = a `codsede`;
  empty/absent = no override). Keys stay **private to business_data**, exposed
  only through `empresa_sede::mapping()`, `setMappingFor()`, and the resolver.
- **Native 6th panel** `panel_sedes` in business_data's own
  `view/admin_empresa.html.twig`, placed after `panel_cuentasb` (**outside**
  `<form name="f_empresa">`), own `<form>` + `{{ csrf_field() }}`; new block
  `view/block/admin_empresa_sedes.html.twig`. Extend `mostrar_seccion()` and
  `comprobar_url()` for the 6th id.
- Mapping UI in `tpvmod_settings` (controller + view), a thin client of
  `setMappingFor()`; admin-only + CSRF-gated.
- Print resolution: `RelatedModelsLoader::load(object $document, ?string $documentType = null)`;
  the 4 call sites pass their literal (`Albaran`/`Pedido`/`Presupuesto` `:181`,
  `Factura` `:219`). Override at line 42, **before** line 65.
- **Base-company phone fix**: the base company's `telefono` also becomes
  printable (decision 3).
- Tests + version bumps in the 3 repos.

### Out of Scope

- `ViewHookRegistry` / `render_hook` route — **rejected**: business_data owns
  data + UI, a cross-plugin hook contract is avoidable coupling, and
  `view-hook-registry-core` is implemented but **not archived**.
- Renderer extension for `fax`, `lema`, `pie_factura`.
- TPV thermal ticket header (`controller/tpvmod.php:2249-2253`) — requirement is
  the printed PDF.
- `ventas_imprimir` legacy path (`facturacion_base` not installed here).
- External research lane (decision 8).
- Any change to `empresa`'s public API, `save()` or `test()` (preserved
  verbatim).
- New Composer dependency ⇒ no `vendor/` commit rule applies.
- SDD entries in core `openspec/`, `plugins/factura_pdf1/openspec/`, or a new
  `openspec/` in business_data (AGENTS.md anti-pattern).

## Capabilities

### New Capabilities

- `empresa-sedes`: multi-row sede entity, document-type mapping storage, and
  the print-time resolution contract.

### Modified Capabilities

- `tpvmod-config`: the settings page gains the sede→document-type mapping
  section. Adds a requirement; the terminal-mode toggle behavior is unchanged.

## Approach

1. **business_data owns model + mapping + resolver.**
   `empresa_sede::resolveForDocumentType(string $tipo, \empresa $base): ?\empresa`
   returns a freshly hydrated **transient** `\empresa` (nothing persisted).
   `toEmpresa($base)` copies non-empty sede fields, inherits base fields when
   empty, and maps `telefono → telefono1`.
2. **factura_pdf1 adopts the optional `$documentType`** and replaces lines
   `42-45` with base-load + optional override, preserving
   `RuntimeException('Empresa no configurada.')` and the `instanceof \empresa`
   contract. Same canonical literal vocabulary as `resolveAdapter()` /
   `tpvmod_imprimir_url()` — no second vocabulary.
3. **Base-company phone** uses the same transient-hydration path: at render time
   the loader hydrates a transient `\empresa` with `telefono1 = $empresa->telefono`.
   No `empresa::save()`/`test()`/API change.
4. **Entry UI**: native panel outside `<form name="f_empresa">`, mirroring
   `admin_empresa_bancos.html.twig`; new POST marker `save_sede` evaluated
   **before** the `nombre` branch in `dispatchAction()`.
5. **Mapping UI** thin client, admin-only + CSRF, and MUST NOT inherit the
   `tpvmod_terminal_settings_available()` gate (it depends on
   `facturacion_base`, which is not installed and is unrelated).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `plugins/business_data/model/empresa_sede.php` | New | CRUD + `toEmpresa()` + mapping API + resolver |
| `plugins/business_data/model/table/empresa_sedes.xml` | New | Schema; filename MUST equal the table name |
| `plugins/business_data/controller/admin_empresa.php` | Modified | `save_sede` branch before `nombre`; handlers; load sedes |
| `plugins/business_data/view/admin_empresa.html.twig` | Modified | 6th nav `<li>` + panel + `mostrar_seccion()`/`comprobar_url()` |
| `plugins/business_data/view/block/admin_empresa_sedes.html.twig` | New | Per-sede form + `csrf_field()` |
| `plugins/business_data/tests/EmpresaSede{Model,Resolution}Test.php` | New | CRUD, mapping, resolution, zero-sede identity |
| `plugins/factura_pdf1/Model/View/RelatedModelsLoader.php` | Modified | `$documentType` param + transient override at `:42` |
| `plugins/factura_pdf1/Model/View/{Albaran,Pedido,Presupuesto,Factura}PrintView.php` | Modified | 1 line each: pass the literal |
| `plugins/factura_pdf1/tests/Unit/View/RelatedModelsLoaderEmpresaSedeTest.php` | New | override / fallback / throw / `pais` |
| `plugins/tpvmod/controller/tpvmod_settings.php` | Modified | 4 mapping selects, CSRF, persist via API |
| `plugins/tpvmod/view/tpvmod_settings.html.twig` | Modified | Mapping section, escaped output |
| `plugins/tpvmod/tests/TpvmodSedeMappingSettingsTest.php` | New | Round-trip, invalid sede, CSRF, admin gate |
| `{business_data,tpvmod,factura_pdf1}/fsframework.ini` | Modified | Version bumps: `1.0.4`, `2.1.0`, `1.0.6` → minor |
| core `openspec/`, `factura_pdf1/openspec/`, business_data `openspec/` | Untouched | No SDD entries created |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `dispatchAction()` collision: a sede form posting `nombre` routes into `handleEmpresaSave()` and overwrites the base row (`controller/admin_empresa.php:254`) | High | Distinct `save_sede` marker evaluated **before** the `nombre` branch; explicit regression test |
| Base-company phone now printed = intentional visible change for every existing install | Med | Accepted (decision 3); DONE precisely and flagged as the single exception to byte-identical output |
| `mostrar_seccion()` hardcodes 5 panel ids; 6th panel leaks / is unreachable | Med | Extend hide list + show branch + `comprobar_url()` |
| Byte-identical output with zero sedes | Med | Resolver returns `null` for unknown tipo / absent key / dangling `codsede` / empty table; `load()` keeps base; identity assertion test |
| Mapping has no effect under `ventas_imprimir` (facturacion_base) | Med | Documented scope boundary; only `factura_pdf1` covered |
| Mapping section inherits `tpvmod_terminal_settings_available()` gate (depends on facturacion_base) | Med | Explicit non-inheritance; regression test with facturacion_base inactive |
| Review budget: ~1035–1200 lines > 800-line session budget | Med | Chained PRs mandatory: PR-1=WU-1 (business_data), PR-2=WU-2 (factura_pdf1), PR-3=WU-3 (tpvmod), PR-4=WU-4 (business_data, chained after PR-1) |
| XML filename ≠ table name, or plugin inactive, prevents resolution | Low | Filename `empresa_sedes.xml`; no migration required (lazy creation per `cuentasbanco`) |

## Rollback Plan

Each repo carries its change in a single additive PR, so rollback is per-repo
revert. Reverting the **factura_pdf1** PR restores prior output (the only
output-affecting delta, including the base-company phone, lives there).
Reverting **business_data PR-1** drops the model/mapping API and leaves a
harmless unused table; reverting **PR-4** removes the panel. The four mapping
`fs_settings` keys are inert when no sede resolves, so leaving them does not
alter behavior. There is **no data migration**, hence no down-migration to run.

## Dependencies

- factura_pdf1 already declares `require = "tpvmod"` and already
  hard-`require_once`s business_data models by path
  (`RelatedModelsLoader.php:83`) ⇒ **zero new dependency edges**.
- No dependency on the unarchived `view-hook-registry-core` change.

## Success Criteria

- [ ] With zero sedes, PDF output is byte-identical to today except the
      now-printed base company phone.
- [ ] A sede mapped to `factura` prints that sede's fields (incl. phone as
      `telefono1`); `pais` resolves from the sede's `codpais`.
- [ ] Unmapped / unknown tipo / dangling `codsede` → base company;
      `RuntimeException` contract preserved.
- [ ] `admin_empresa` saves a sede without touching the base company row
      (regression test green).
- [ ] `tpvmod_settings` persists/clears the 4 mapping keys, admin-only + CSRF,
      and still renders with `facturacion_base` inactive.
- [ ] PHPUnit green in all 3 repos (`ddev exec php vendor/bin/phpunit`, plus
      `-c plugins/tpvmod/phpunit.xml`).

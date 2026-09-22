# Delta Spec: `empresa-sedes` — empresa-sedes-por-documento

> **New capability.** Post-archive source of truth:
> `plugins/tpvmod/openspec/specs/empresa-sedes/spec.md`.
>
> **Ownership caveat.** The model, mapping storage and resolver live in
> `plugins/business_data/`; the mapping UI lives in `plugins/tpvmod/`. The SDD
> is intentionally owned by `tpvmod` (main beneficiary): `business_data` has no
> `openspec/` tree and creating one is an explicit anti-pattern (`AGENTS.md` →
> "OpenSpec per Plugin"). At archive this delta merges into the tpvmod tree.
>
> Traceability: `exploration.md` §1–§5; `proposal.md` §Capabilities.

## ADDED Requirements

### Requirement: Multi-row sede entity and lazy schema

The system MUST provide an `empresa_sede` model backed by table `empresa_sedes`,
PK `codsede` (`character varying(6)`), generated `MAX+1`, plus a `descripcion`
label, modeled on `cuenta_banco`/`cuentasbanco.xml` (`exploration.md` §1). The
table MUST be created lazily on first instantiation from
`plugins/business_data/model/table/empresa_sedes.xml` (filename equals the table
name). No migration code MUST be introduced.

#### Scenario: First instantiation creates the table

- GIVEN the table does not exist and `business_data` is active
- WHEN `new empresa_sede()` is instantiated
- THEN the table is created from the XML, with no migration script

#### Scenario: Generated code is unique and identifies the sede

- GIVEN two sedes saved in sequence
- WHEN the second is persisted
- THEN its `codsede` is `MAX(codsede)+1`, distinct from the first
- AND `descripcion` renders as the selector label

### Requirement: Editable field set is narrowed to PDF-consuming fields

The entity MUST expose exactly these editable fields:

| Field(s) | PDF consumer |
|---|---|
| `nombre` | header (`PortedPdfDocument:530`) |
| `cifnif` | `:534-538` |
| `direccion`, `apartado`, `codpostal`, `ciudad`, `provincia`, `codpais` | `combineAddress():279-320` |
| `email` | `:541-543` |
| `web` | `:559-565` |
| `telefono` | `telefono1` at `:545-554` |

It MUST NOT expose any field with no consumer in `plugins/factura_pdf1/`:
`fax`, `lema`, `pie_factura`, `horario` and `nombrecorto` are explicitly NOT
sede fields (`exploration.md` §4). `test()` MUST reject invalid input and every
field MUST pass through `no_html()` before persistence.

#### Scenario: No field without a PDF consumer

- GIVEN the entity and its edit form
- WHEN the exposed fields are enumerated
- THEN each maps to a `plugins/factura_pdf1/` consumer
- AND `fax`, `lema`, `pie_factura`, `horario`, `nombrecorto` are absent

#### Scenario: Sanitization and validation

- GIVEN a submitted sede with a blank `nombre` or markup in `direccion`
- WHEN `test()` runs before `save()`
- THEN the blank `nombre` is rejected with an error
- AND stored text fields are `no_html()`-sanitized

### Requirement: Mapping storage encapsulated in business_data

The system MUST persist the type → sede mapping in exactly four `fs_settings`
keys: `empresa_sede_presupuesto`, `empresa_sede_albaran`, `empresa_sede_pedido`,
`empresa_sede_factura`; the value MUST be a `codsede`, and empty/absent MUST mean
"no override". Raw key names MUST stay private to `business_data`, reachable only
through `empresa_sede::mapping()`,
`empresa_sede::setMappingFor(string $tipo, ?string $codsede): bool` and the
resolver.

#### Scenario: Invalid codsede is rejected

- GIVEN a `codsede` that does not resolve to a row
- WHEN `setMappingFor()` is called
- THEN it returns `false` and the stored key is unchanged

#### Scenario: Empty selection clears the override

- GIVEN a type currently mapped to a sede
- WHEN `setMappingFor('factura', null)` is called
- THEN the key is cleared and `mapping()` reports no override

### Requirement: Canonical document-type vocabulary

The system MUST use only the literal vocabulary `presupuesto|albaran|pedido|factura`
already shared by `tpvmod_imprimir_url()` and
`FacturaPdf1Controller::resolveAdapter()` (`exploration.md` §4). No second
vocabulary MUST be introduced.

#### Scenario: Unknown type is not accepted

- GIVEN the literal `factura_simplificada`
- WHEN passed to `setMappingFor()` or the resolver
- THEN no mapping is written and no override is resolved

### Requirement: Document-type resolution contract

`empresa_sede::resolveForDocumentType(string $tipo, \empresa $base): ?\empresa`
MUST return a freshly hydrated **transient** `\empresa` (nothing persisted) or
`null`, for each of: unknown tipo; absent mapping key; empty mapping key; mapped
`codsede` with no row; empty `empresa_sedes` table. `toEmpresa($base)` MUST copy
every non-empty sede field, inherit from `$base` when the sede field is empty,
and map sede `telefono → telefono1`.

#### Scenario: Mapped sede is hydrated transiently

- GIVEN a sede mapped to `presupuesto`
- WHEN the resolver runs for `presupuesto`
- THEN the result is an `\empresa` instance
- AND nothing is inserted or updated in `empresa`

#### Scenario: Every null case

- GIVEN an unknown tipo, no/empty mapping key, a dangling `codsede`, or zero sedes
- WHEN the resolver runs
- THEN it returns `null`

#### Scenario: Field merge and phone mapping

- GIVEN a sede with `nombre` set, `web` empty and `telefono` set
- WHEN `toEmpresa($base)` runs
- THEN `nombre` comes from the sede and `web` from `$base`
- AND `telefono1` equals the sede `telefono`

### Requirement: Print integration contract

`RelatedModelsLoader::load(object $document, ?string $documentType = null)` MUST
adopt the resolved sede at `RelatedModelsLoader.php:42`, before the `pais`
resolution at `:65`, so `pais` also resolves from the sede's `codpais`. The
`instanceof \empresa` check, the `\empresa` type hints,
`ClientDocumentPrintViewInterface::getEmpresa(): object` and `PortedPdfDocument`
MUST remain unchanged; the four print views MUST pass their literal type.

#### Scenario: Sede wins and its country resolves

- GIVEN a sede mapped to `factura` with `codpais` = `ESP`
- WHEN a factura print view builds
- THEN the loader exposes the sede as `empresa`
- AND `pais` resolves from the sede's `codpais`

#### Scenario: Unmapped or no-type call keeps base identity

- GIVEN no mapping for the type, or `load($document)` with no type
- WHEN the loader runs
- THEN the base `empresa` is returned identically

#### Scenario: RuntimeException preserved

- GIVEN the base `empresa` row is missing even with a sede mapped
- WHEN the loader runs
- THEN `\RuntimeException('Empresa no configurada.')` is thrown

### Requirement: Zero-sede backwards compatibility

With no sedes and no mapping, resolution MUST return `null` and base `\empresa`
identity MUST be preserved, so PDF output stays byte-identical to today — with
the single documented exception of the base-company phone below.

#### Scenario: Identity is preserved

- GIVEN no sedes and no mapping keys
- WHEN the loader resolves the company
- THEN the resolver returns `null`
- AND the loader's `empresa` is the same instance as the base row's

### Requirement: Base-company phone becomes printable

The base company's `telefono` MUST also become printable through the same
transient-hydration path at render time (`telefono1 = $empresa->telefono`),
without modifying `empresa::save()`, `empresa::test()` or its public API. This
is an intentional, reviewable output change for every existing installation.

#### Scenario: Base phone is printed

- GIVEN a base `empresa` with `telefono` and no sede resolved
- WHEN a document is rendered
- THEN the contact block prints that phone number

#### Scenario: No persistence side effect

- GIVEN the transient hydration runs
- WHEN the request completes
- THEN `empresa`'s stored row is unchanged

### Requirement: Security of sede flows

Every mutating sede or mapping flow MUST be admin-only and CSRF-gated. Twig
output MUST be escaped, SQL MUST use prepared statements or `var2str()`, and no
`|raw` MUST be applied to user data.

#### Scenario: CSRF and admin rejection

- GIVEN a POST without a valid CSRF token, or from a non-admin user
- WHEN the flow is dispatched
- THEN no sede or mapping write occurs and an error is shown

#### Scenario: Escaped output

- GIVEN a sede whose `descripcion` contains markup
- WHEN rendered in a selector
- THEN the markup is escaped, not executed

### Requirement: Sede save never overwrites the base company

`admin_empresa::dispatchAction()` dispatches on the mere presence of POST
`nombre` (`plugins/business_data/controller/admin_empresa.php:254`). A sede form
posting a field named `nombre` MUST NOT overwrite the base company row: the sede
branch MUST use the distinct `save_sede` marker and MUST be evaluated before the
`nombre` branch.

#### Scenario: Sede form does not touch the base row

- GIVEN a base company and a sede POST carrying `save_sede` and `nombre`
- WHEN `dispatchAction()` runs
- THEN the sede is saved or validated
- AND the base company row is byte-identical to before

#### Scenario: Reserved order under regression

- GIVEN both `save_sede` and `nombre` in the same POST
- WHEN the branch order is inspected
- THEN `save_sede` is evaluated first

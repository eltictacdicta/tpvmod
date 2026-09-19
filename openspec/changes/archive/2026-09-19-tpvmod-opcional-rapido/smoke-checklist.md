# Smoke checklist: tpvmod-opcional-rapido

> Manual verification items for spec scenarios with no PHP-unit path. Run on a
> live TPV after the plugin suite is green. Scope: `plugins/tpvmod/` only.

## Environment

- Terminal: `ddev start`
- Agent user with TPV permissions.
- `tpvmod` active; `catalogo_core`, `business_data`, `clientes_core` active.
- At least one product with a `codfamilia`, one product without it, and one
  client with cascading discounts (`dtopor`…`dtopor4`).

## 7.1 — Model sanitization at the catalog boundary (F13)

`catalogo_opcional::test()` applies `no_html()` to `codigo`, `nombre` and
`descripcion` (verified by code inspection; no DB-free harness exists because
`catalogo_opcional::__construct()` bootstraps `fs_db2`/`check_table()`).

- [ ] Save an opcional named `<script>alert(1)</script>` with description
      `<b>x</b>`.
- [ ] Inspect the persisted `catalogo_opcionales` row: `nombre` and
      `descripcion` no longer contain raw `<`/`>` (escaped by `no_html()`).
- [ ] The catalog list/admin renders the sanitized text with no executable markup.

## 7.3 — Behavioural flow (OD-1, OD-6, OD-8)

- [ ] Open `tpvmod2`, add a product line, open "Añadir opcional", switch to the
      "Nuevo opcional" tab.
- [ ] **Añadir** with a valid composed opcional inserts a line for the current
      sale and persists nothing: no new `catalogo_opcionales` row, no
      `catalogo_articulo_opcional` relation.
- [ ] **Añadir y guardar opcional** with target **Producto**: a new
      `catalogo_opcionales` row exists with `activo = true`, `id_grupo = null`,
      codigo `OPC####`; a `catalogo_articulo_opcional` relation exists; the line
      is auto-added to the sale.
- [ ] **Añadir y guardar opcional** with target **Familia** (product with
      `codfamilia`): exactly one `catalogo_opcional_familias` relation is
      created, and **no** `catalogo_articulo_opcional` rows for the family's
      articles (no propagation, OD-7).
- [ ] **Reuse**: repeating the save with the same normalized `nombre` creates no
      duplicate `catalogo_opcionales` row and reuses the existing id (OD-8).
- [ ] **Product without `codfamilia`**: the "Familia" option is hidden/disabled
      and Producto is forced; a direct request with `asociacion=familia` is
      rejected with `{ok:false, errors:[...]}` (OD-3).
- [ ] **Inert obligatorio**: a product with an unmet obligatorio plus an ad-hoc
      line whose description matches the required catalog opcional still blocks
      the save on both client and server (OD-6).
- [ ] **Discount parity (OD-1)**: with a discounted client, an ad-hoc line's
      `dtopor`…`dtopor4` fields match the client's cascading discounts and its
      total equals the same computation applied to a catalog line; the entered
      price remains the pre-discount base.
- [ ] **Percentage price frozen (OD-2)**: an ad-hoc percentage line inserted
      from a parent PVP keeps its price after the parent PVP is edited.
- [ ] **Invalid input**: empty `nombre`, negative or non-numeric `valor` inserts
      no line and reports a validation error.
- [ ] **CSRF**: a POST to `guardar_opcional_tpv` without a valid token returns
      `{ok:false, errors:["Token CSRF inválido."]}` and persists nothing; the
      read-only `?opcionales_articulo=<ref>&pvp=<n>` endpoint stays CSRF-free.

## 7.4 — Both screens (OD-10)

- [ ] `tpvmod2` and `tpvmodedita` both render the same `#modal_opcionales`
      include with the same tabs, list and quick-create form.
- [ ] The same flow (Añadir, Añadir y guardar) works identically on both.

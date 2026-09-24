# Decisions — tpvmod-listados-htmx

Status: **all decisions confirmed — `sdd-propose` unblocked.**
Research: **skipped by the human** (in-repo evidence is authoritative:
`openspec/specs/htmx-core-support/spec.md`, `openspec/specs/list-search-security/spec.md`).
Source: `exploration.md` → `## Open questions`.
Change root: `plugins/tpvmod/openspec/changes/tpvmod-listados-htmx/` (plugin-local).

---

## Confirmed by the human

| # | Decision | Resolution |
|---|---|---|
| D1 | City source | **Document snapshot `value.ciudad`** (billing address captured on the document). No extra query. |
| D2 | Phone display | **`telefono1`, falling back to `telefono2`.** One value per row. |
| D3 | Search scope | **Live company name (`clientes.nombre` + `razonsocial`) + `telefono1/2`** via `codcliente IN (subquery)`, keeping the current `codigo` / `numero2` / `observaciones`. Adapts the existing `f_custom_search` form. No `cifnif`. Portable multi-field `LIKE`/`ILIKE`, no schema change. |
| D4 | Filter form position | **Move the filter form above the tabs** so tabs + order + table + pagination live inside the swapped region. |
| D6 | Facturas line search | **Add offset/pager parity** with the other three modules. |
| D7 | Date fields | **All 10 `.datepicker` inputs → native `<input type="date">`** + `date_iso` filter; `class="datepicker"` removed; controller normalizes incoming `Y-m-d` for `var2str`. Includes `tpvmodedita`. tpvmod drops the jQuery UI dependency for date input. |

## D5 — Client search modal (revised by the human)

Human answer: *"No quiero modal cliente, quiero full text + filtro de fechas +
empleado y serie."*

Interpretation confirmed by matching the enumeration against the current form
(`query`, `codserie`, `codagente`, `ac_cliente`, `desde`, `hasta`): the listing
search form becomes **full text + date + employee + serie**; the client picker
(`ac_cliente` field + `partials/modal_clientes.html.twig` + `tpvmod-b-buscar-cliente`
button + the `tpvmod-cliente.js` include in the **four listing views**) is removed
from the listings. The modal and its JS stay for `tpvmod2` and `tpvmodedita`,
which keep using them.

### Residual: the `codcliente` deep link — **resolved: option A**

| Option | Effect |
|---|---|
| **A. Keep the URL filter** — **CHOSEN** | `[+]` and external `&codcliente=` keep filtering; the active client is shown as read-only text in the filter area (no modal, no picker). No broken links. |
| B. Drop the client filter entirely | Listings stop accepting `codcliente`; the `[+]` link must be removed, and callers that deep-link with `codcliente` (e.g. `ventas_cliente` extensions) lose the filter. |

---

## Consequences carried into spec + tasks (not decisions)

- R1: raw SQL concatenation in `buscar()` → migrate to `var2str`.
- R2: `cron_job()` (5 UPDATEs per load) → skip on `isHtmxRequest()`.
- R3: `paginas()` builds unencoded URLs → rebuild with `http_build_query`.
- R5/R6: Alpine idempotency + `htmx:after:swap` `initTree`; region boundaries.
- R7 (datepicker inside a swapped region) is **removed** by D7: native inputs
  need no re-init.
- D7 motivation — **corrected during `sdd-spec`**: the jQuery UI `.datepicker` was
  NOT causing a functional bug. `fs_db2::var2str()` already normalizes `d-m-Y` to
  the engine's `date_style()` (MySQL `Y-m-d`) through `parseDateValue()`
  (`base/fs_db2.php:189-213`), and PHP's `strtotime()` parses dash-separated
  `dd-mm-yyyy` as European. The real motivation is the **jQuery UI dependency
  itself**: `plugins/legacy_support/view/js/legacy-init.js:26-33` auto-initializes
  `.datepicker` (core's own `base.js` no longer does), so tpvmod keeps that legacy
  coupling and a widget that needs re-init after every swap. Native inputs remove
  it. Verified by the orchestrator.
- Out of scope unless requested: `rechazar()` ignores the date entered in the
  modal and rejects every pending document.
- Shared helper for the four modules to avoid clone drift; per-module assertions
  keep facturas/albaranes/pedidos extras covered.
- Removing the client modal from the listings touches pinned tests:
  `TpvmodTwigTemplatesTest.php:107` asserts `tpvmod_cliente_ajax_dispatch` in the
  controllers and `TpvmodOpcionalRapidoTest.php:641` does `strpos` on it. Spec/tasks
  must state whether the dispatch call stays in the listing controllers.
- `strict_tdd: true` → DB-free helper tests plus Twig template-contract tests.

# tpv-discounts (delta)

## ADDED Requirements

### Requirement: Resolve effective client discounts

When `clientes_core` is active, the TPV SHALL resolve the four effective
discount percentages for the selected client using
`cliente::getEffectiveDiscounts()` (client override with group fallback).
When the method is unavailable, all four values SHALL default to `0.00`.

#### Scenario: Client with group discounts

- GIVEN client C with effective discounts `d1=10`, `d2=5`, `d3=0`, `d4=2`
- WHEN the TPV loads client data for C
- THEN the JSON payload includes those four values

#### Scenario: Client without clientes_core API

- GIVEN a cliente object without `getEffectiveDiscounts()`
- WHEN discounts are resolved
- THEN all four values are `0.00`

### Requirement: Apply discounts on new lines

When an article is added to the ticket, the TPV SHALL pre-fill the line's
four discount fields from the current client's effective discounts.

#### Scenario: Add line inherits client discounts

- GIVEN client effective discounts `10, 5, 0, 2`
- WHEN the operator adds an article to the ticket
- THEN the new line shows dto fields `10, 5, 0, 2`

### Requirement: Cascading line total calculation

Line net amount SHALL use the same cascade as
`linea_documento_venta` / `fbase_calc_due`:

`pvptotal = cantidad × pvpunitario × (1 - d1/100) × (1 - d2/100) × (1 - d3/100) × (1 - d4/100)`

#### Scenario: Two-step cascade

- GIVEN quantity 2, unit price 100, discounts `10, 10, 0, 0`
- WHEN the line total is calculated
- THEN `pvptotal` is `162.00` (200 × 0.9 × 0.9)

### Requirement: Discounts are not editable in TPV

The TPV UI SHALL NOT expose editable discount fields. The operator
MAY edit unit price (`pvp`) and quantity only. Discount percentages
SHALL always come from the selected client's effective discounts.

#### Scenario: Operator changes PVP but not discounts

- GIVEN client with effective discounts `10, 5, 0, 0`
- WHEN the operator changes line unit price from 100 to 90
- THEN saved line keeps `dtopor=10`, `dtopor2=5`, etc.
- AND `pvptotal` reflects PVP 90 with the client cascade

#### Scenario: No discount inputs in TPV form

- GIVEN the TPV sales screen
- WHEN the operator views a ticket line
- THEN there are no dto1–dto4 input fields

### Requirement: Persist discounts on all document types

Creating or editing presupuesto, pedido, albarán or factura from the TPV
SHALL write the four line discount fields and correct header totals.

#### Scenario: Presupuesto save with discounts

- GIVEN a ticket with one discounted line
- WHEN the operator saves as presupuesto
- THEN `lineaspresupuestoscli` stores the four dto columns
- AND document `neto`/`total` match the discounted sum

### Requirement: Recalculate on client change

When the operator selects a different client and the ticket has lines,
the TPV SHALL replace every line's four discount fields with the new
client's effective values and recalculate all line and header totals.

#### Scenario: Client change updates all lines

- GIVEN a ticket with 3 lines and client A (all zeros)
- WHEN the operator selects client B (`d1=20`, rest zero)
- THEN all 3 lines show `d1=20`
- AND header totals reflect the new amounts

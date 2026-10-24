# tpv-flow (delta)

## ADDED Requirements

### Requirement: Client data endpoint includes discounts

The `datoscliente` AJAX endpoint SHALL return the effective discount
fields (`d1`, `d2`, `d3`, `d4`) alongside existing client metadata.

#### Scenario: datoscliente JSON shape

- GIVEN client `000002` with effective discounts
- WHEN the browser requests `datoscliente=000002`
- THEN the JSON includes `d1`, `d2`, `d3`, `d4` as numbers

### Requirement: Article search preloads client discounts

When searching articles with a selected client, result rows SHALL carry
the client's effective `dtopor` (D1) for display; full four-field
application happens when the line is added.

#### Scenario: Search with client selected

- GIVEN client with `d1=10` selected in the TPV form
- WHEN article search results are returned
- THEN each result's displayed price reflects D1 on unit price preview

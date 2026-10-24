# factura-pdf-discount-display (delta — implemented in `plugins/factura_pdf1/`)

Cross-plugin spec referenced from the tpvmod change
`tpvmod-descuentos-cliente`. Implementation tasks live in
`tasks.md` section **factura_pdf1**.

## ADDED Requirements

### Requirement: Line adapter exposes discount fields

`AbstractClienteDocumentAdapter::getLineas()` SHALL include
`dtopor`, `dtopor2`, `dtopor3`, `dtopor4`, and `pvpsindto` for each line.

#### Scenario: Adapter line shape

- GIVEN a factura line with `dtopor=10`, `pvpunitario=100`, `pvpsindto=200`
- WHEN `getLineas()` is called
- THEN the first line array contains those discount fields

### Requirement: Optional discount display controlled by print_dto

When empresa impresión setting `print_dto` is enabled and a line has a
non-zero combined discount, the PDF price column SHALL show:

1. The unit price before discount (PVPR) with strikethrough styling.
2. The discounted unit price (after the D1–D4 cascade).

When `print_dto` is disabled or the line has no discount, the PDF SHALL
keep the current single-price display.

#### Scenario: print_dto on with 10% discount

- GIVEN `print_dto=1` and a line with unit 100 and combined discount 10%
- WHEN the PDF body is rendered
- THEN the price cell contains strikethrough PVPR and net unit price 90

#### Scenario: print_dto off

- GIVEN `print_dto=0` and a discounted line
- WHEN the PDF is rendered
- THEN only the standard unit price is shown (no strikethrough)

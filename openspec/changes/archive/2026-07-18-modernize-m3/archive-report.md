# Archive Report — modernize-m3

**Change**: `modernize-m3`  
**Plugin**: `plugins/tpvmod/`  
**Archived**: 2026-07-18  
**Status**: ✅ **ARCHIVED** — SDD cycle closed

## Status

**ARCHIVED** — the `modernize-m3` change migrated all RainTPL views in
`plugins/tpvmod/view/` to native Twig 3, removed the controller CSRF
workaround, added Composer/Init infrastructure, filled nine debt-fill
ajax/extension templates, and activated `TpvmodTwigTemplatesTest` with
real assertions. Canonical spec lives at
`plugins/tpvmod/openspec/specs/views/spec.md`. No entry exists in core
`openspec/changes/`.

## Change summary

Wave-based migration across four PRs:

| Wave | Scope |
|------|-------|
| PR1 | `composer.json`, `Init.php`, `vendor/`, test scaffold |
| PR2a/2b | 5 core views + CSRF cleanup in `tpvmod.php` / `tpvmod_settings.php` |
| PR3a/3b/3c | 4 list views + 1 ajax rewrite + 9 debt-fill templates |
| PR4 | `TpvmodTwigTemplatesTest` GREEN + verify-report + canonical spec |

**Outcome**: 0 `.html` files, 19 `.html.twig` files, 23/23 PHPUnit tests
passing (119 assertions, 0 skipped).

## Commits (branch `modernize-m3`)

| SHA | Message |
|-----|---------|
| `9d39866` | feat(tpvmod): add composer infra, Init.php, versioned vendor (PR1) |
| `d91b2c2` | feat(tpvmod): migrate core views to Twig idiomático (PR2a) |
| `cf712b1` | refactor(tpvmod): drop RainTPL CSRF workaround from controllers (PR2b) |
| `a2aa90c` | feat(tpvmod): migrate list views to Twig — facturas and albaranes (PR3a) |
| `465a234` | feat(tpvmod): migrate pedidos and presupuestos list views to Twig (PR3b) |
| `33279e7` | feat(tpvmod): rewrite ajax views and fill 9 debt-fill templates (PR3c) |
| `53d6d9d` | test(tpvmod): activate Twig template inventory tests and verify report (PR4) |

## SDD artifacts archived

- `proposal.md`
- `specs/views/spec.md` (delta)
- `tasks.md`
- `verify-report.md`
- `archive-report.md` (this file)

## Spec sync

Canonical source of truth:
`plugins/tpvmod/openspec/specs/views/spec.md` (copied from delta at T4.6).

## Verify outcome

**PASS** — all REQ-VIEW-001 through REQ-VIEW-007 scenarios covered by
automated tests and grep audits. Authenticated TPV end-to-end browser
smoke (ticket generation, caja close, reprint) remains an operator
follow-up; unauthenticated HTTP probes confirm no 500 on all eight pages.

## Core openspec isolation

```bash
$ ls openspec/changes/modernize-m3/ 2>/dev/null || echo "ABSENT (correct)"
ABSENT (correct)
```

## SDD cycle complete

Ready for merge of `modernize-m3` to the plugin's main branch and optional
push/PR to remote.

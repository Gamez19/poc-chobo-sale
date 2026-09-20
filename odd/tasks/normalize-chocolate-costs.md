# Normalize chocolate inventory costs

## Goal
Normalize Chocolate, Chocolate Blanco, and Chocolate Fresa around a 300 g bag priced at C$65 (approximately C$0.21 per gram), preserve the ability to use a different cost for future entries, and recalculate existing production-lot costs.

## Scope
- Back up the production database before mutation.
- Normalize the selected raw-material records and their purchase-entry unit costs.
- Preserve existing stock quantities unless a conversion is explicitly supported by the stored unit/quantity data.
- Recalculate production-lot item costs from the current recipes and normalized material costs.
- Verify realized/projected profit output and record any remaining ambiguity.

## Safety
- Do not modify unrelated materials, sales, recipes, or application source files.
- Keep the backup outside the repository and report its path.
- Existing uncommitted files (`opencode.json`, `.gentle-ai-default-agent.json`) are unrelated and must remain untouched.

## Tasks
1. Capture and verify a restorable database backup. **Done** — gzip-verified backup created at `/home/edwin/Documentos/Personal/inventario-ventas-backups/inventario-before-chocolate-normalization-20260919-172830.sql.gz`.
2. Normalize Chocolate, Chocolate Blanco, and Chocolate Fresa data. **Done** — all three are grams at 21 cents/gram; movement costs updated; future restocks remain adjustable.
3. Recalculate affected production-lot item unit costs. **Done** — all 13 lot items recalculated from active recipes.
4. Verify database invariants and profitability aggregates. **Done** — initial checks and `tests/Feature/ProfitsTest.php` passed.
5. Back up remaining-cost update. **Done** — gzip-verified backup created at `/home/edwin/Documentos/Personal/inventario-ventas-backups/inventario-before-remaining-cost-normalization-20260919-174004.sql.gz`.
6. Normalize remaining materials. **Done** — Palitos set to 17 cents/unit (C$25 ÷ 145, rounded), Maní to C$50/lb, Chispitas to C$10/oz (C$40 ÷ 4 oz), and Bolsa to 25 cents/unit; 21 movement rows updated.
7. Recalculate and verify profitability. **Done** — all 13 lot items recalculated; invariants and focused tests passed.

## Final verification outcome
- Realized: revenue C$1,740.00; cost C$501.93; profit C$1,238.07; margin 71.2%.
- Projected: revenue C$172.00; cost C$39.46; profit C$132.54.

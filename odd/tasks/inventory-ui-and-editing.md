# Inventory UI and editing update

## Goal
Match the requested operations UI and expose safe editing workflows:
- keep navigation in a left sidebar, including a usable responsive variant;
- show finished stock/existencias before today's sales on the dashboard;
- hide SKU from rendered UI while retaining generated internal SKU values;
- edit production-lot metadata and quantities with stock/ledger validation;
- edit all raw-material fields, including current quantity and unit cost, using the explicitly selected direct-correction behavior.

## Decisions
- Sidebar remains on the left at all viewport sizes; narrow screens use a compact left rail rather than bottom navigation.
- Lot editing includes code, production/expiry dates, notes, and existing variant quantities. Quantity changes preserve sold allocations, adjust raw-material stock transactionally, and recalculate availability/costs.
- Raw-material quantity and unit-cost edits are direct corrections, as explicitly chosen by the user; no adjustment movement is created for those corrections.
- SKU remains generated and persisted internally, but no SKU input, label, badge, or rendered value is shown.

## Safety
- Keep changes limited to the listed UI/components/services/tests.
- Do not modify production data in this feature implementation.
- Preserve unrelated working-tree changes.

## Tasks
1. Map existing UI, Livewire, services, models, and test conventions. **Done** — scout completed.
2. Implement responsive left sidebar, dashboard stock-first order, and SKU visibility changes. **Done** — desktop sidebar retained; narrow screens use a compact left rail; stock card is first; rendered SKU surfaces removed.
3. Implement production-lot editing with transactional quantity adjustments and validation. **Done** — metadata and existing-variant quantities are editable; sold allocations are protected; material deltas, availability, costs, and status update atomically.
4. Implement direct editing of raw-material quantity and unit cost. **Done** — current quantity and unit cost are editable directly, with validation and no movement row added.
5. Add/update feature tests for each changed behavior. **Done** — focused feature coverage added/updated, including `tests/Feature/ProductionLotsTest.php`.
6. Run focused tests, formatting, and review the final diff. **Done** — Pint, Vite build, focused tests (60/298), full suite (110/471), and `git diff --check` passed.

## Verification notes
- Vite build warning: optional `fontaine` package is not installed; font fallback optimization is disabled, but the build succeeds.
- Browser rendering was not automated in this session; CSS and rendered markup were source-verified.

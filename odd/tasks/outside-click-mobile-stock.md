# Outside-click menu and mobile stock priority

## Goal
Close the expanded sidebar when the user clicks outside it, and place the dashboard's finished-stock panel before the stats cards on mobile while preserving desktop layout.

## Decisions
- Use Alpine's outside-click behavior on the existing sidebar state.
- On mobile only, reorder the existing stock panel before `.stats-grid`; desktop order and two-column layout remain unchanged.

## Tasks
1. Add outside-click close behavior to the sidebar. **Done** — Alpine `@click.outside` closes the expanded menu without affecting inside interactions.
2. Add a mobile-only dashboard flow ordering for the stock panel. **Done** — `Existencia por variante` appears after the header/button and before `.stats-grid` only on mobile.
3. Build and run regression checks; inspect the final diff. **Done** — Vite build passed, full suite passed (110 tests/471 assertions), and static checks passed.

## Verification notes
- Vite warning: optional `fontaine` package is not installed; font fallback optimization is disabled, but the build succeeds.
- Browser interaction/responsive rendering was not automated.

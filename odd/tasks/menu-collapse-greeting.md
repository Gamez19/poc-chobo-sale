# Collapsible sidebar and contextual header

## Goal
Make the navigation a collapsed icon rail by default, expand it with a hamburger button, move logout into the navigation, show a time-based greeting with the user's name, and right-align the date in the header.

## Decisions
- Default state is collapsed icons-only.
- Hamburger toggles expanded labels; the sidebar remains on the left at all viewport sizes.
- Logout is a sidebar navigation action with an icon.
- Greeting uses the server's current hour: Buenos días / Buenas tardes / Buenas noches, followed by the authenticated user's name.
- Header date is pushed to the far right.

## Safety
- Limit source edits to the shared app layout and stylesheet.
- Preserve named routes, Livewire navigation, authentication form behavior, and unrelated working-tree changes.

## Tasks
1. Add accessible collapsed/expanded sidebar state and logout navigation action. **Done** — Alpine state defaults collapsed; hamburger exposes dynamic ARIA state; logout lives in the sidebar.
2. Add contextual greeting and right-aligned date. **Done** — greeting uses Buenos días/tardes/noches plus the authenticated name; date chip is pushed right.
3. Update responsive CSS for collapsed rail/expanded sidebar. **Done** — desktop grid expands 76px → 252px; narrow screens use a left compact rail and expanded overlay.
4. Run focused rendering/build checks and inspect the diff. **Done** — Vite build and full 110-test suite passed; static source checks passed. Browser interaction was not automated.

## Verification notes
- Vite warning: optional `fontaine` package is not installed; font fallback optimization is disabled, but the build succeeds.
- Existing unrelated working-tree changes were preserved.

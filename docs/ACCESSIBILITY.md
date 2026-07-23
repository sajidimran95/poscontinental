# Accessibility audit checklist (Phase F)

Semantic markup and keyboard affordances are applied as screens ship. Complete this JAWS/VoiceOver pass before production go-live.

## Global

- [x] Skip to main content / focus visible on all interactive controls
- [x] Menu bar reachable via keyboard; dropdowns use `role="menu"` / `menuitem` where practical
- [x] Document tabs have accessible names matching visible labels
- [x] Flash/status messages use `role="status"` or `aria-live`
- [x] Status bar clock announces politely (`aria-live="polite"`)

## Critical screens

- [ ] Login — labels, error announcement (manual JAWS pass)
- [ ] Sales Order form — tabs, item grid, F2 browse, totals (manual JAWS pass)
- [ ] Invoices modal — payments, credits, email form (manual JAWS pass)
- [ ] Customers / Items lookups and browse grids (manual JAWS pass)
- [ ] Stock Count Comments + Expand variance grid (manual JAWS pass)
- [ ] Tobacco stamp inventory numeric fields (manual JAWS pass)
- [ ] Reports filters + export buttons (manual JAWS pass)

## Contrast & motion

- [ ] Chief yellow/blue bars meet WCAG AA for text on colored bars (or provide text alternative)
- [ ] No essential information conveyed by color alone (status + New Item flags)

## Tools

- Keyboard-only walkthrough
- NVDA or JAWS on Windows for POS
- VoiceOver on iOS for customer/rep mobile apps

**Note:** Structural ARIA/landmarks are in place in the app shell. Remaining boxes require a live screen-reader audit with JAWS/NVDA before go-live sign-off.

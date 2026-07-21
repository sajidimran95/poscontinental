# Accessibility audit checklist (Phase F)

Semantic markup and keyboard affordances are applied as screens ship. Complete this JAWS/VoiceOver pass before production go-live.

## Global

- [ ] Skip to main content / focus visible on all interactive controls
- [ ] Menu bar reachable via keyboard; dropdowns announce expanded/collapsed
- [ ] Document tabs have accessible names matching visible labels
- [ ] Flash/status messages use `role="status"` or `aria-live`

## Critical screens

- [ ] Login — labels, error announcement
- [ ] Sales Order form — tabs, item grid, F2 browse, totals
- [ ] Invoices modal — payments, credits, email form
- [ ] Customers / Items lookups and browse grids
- [ ] Stock Count Comments + Expand variance grid
- [ ] Tobacco stamp inventory numeric fields
- [ ] Reports filters + export buttons

## Contrast & motion

- [ ] Chief yellow/blue bars meet WCAG AA for text on colored bars (or provide text alternative)
- [ ] No essential information conveyed by color alone (status + New Item flags)

## Tools

- Keyboard-only walkthrough
- NVDA or JAWS on Windows for POS
- VoiceOver on iOS for customer/rep mobile apps

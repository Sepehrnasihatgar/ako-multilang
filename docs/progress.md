# Progress

## Milestone 2 — REST + admin shell (in progress, branch `wip/m2-admin`)

Done
- `cedar/v1` REST: `/languages` (GET, POST, PUT, DELETE, `/order`, `/catalog`), `/links` (overview), `/links/candidates`, `/links/{id}` (PUT link, DELETE unlink `scope=post|group`).
- Permission matrix verified in Playground: anonymous 401, subscriber 403, editor can list links only, admin full; invalid params 400.
- Admin menu "Cedar" (Translations, Languages), assets load only on Cedar screens, root config passed as an escaped data attribute (no inline script).
- `src/tokens/tokens.css` generated from Figma variables; Inter + Vazirmatn bundled (OFL, `assets/fonts/OFL.txt`).
- React components mirroring Figma (Button, Badge, Language tag, Tabs, Input, Select, Field, Checkbox, Toggle, Menu, Dialog, Toast, Empty state, Skeleton, Coverage card, Header).
- Screens: Translations (filters, content-type menu, search, pagination, bulk unlink with undo, link existing page) and Languages (list, drag + keyboard reorder, add from catalog, custom language, edit, remove dialog).
- `npm run build` passes; ESLint 0 errors; Stylelint clean.
- Minimum WordPress raised to 6.6 (`react-jsx-runtime`).

Next
1. Visual QA of both screens against the Figma frames (screenshots in Playground), fix gaps.
2. Commit to `main` once verified.
3. Later milestones add: Needs review tab + SEO health card (M5), Import / Link pages header buttons and first-run empty state (M6), Duplicate as draft (M3), Get Pro / Docs header links (when URLs exist).

Run locally
- `npm install && npm run build`
- `npm run playground`

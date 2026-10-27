# Progress

## Milestone 2 — REST + admin shell (done, merged to `main`)

Done
- `cedar/v1` REST: `/languages` (GET, POST, PUT, DELETE, `/order`, `/catalog`), `/links` (overview), `/links/candidates`, `/links/{id}` (PUT link, DELETE unlink `scope=post|group`).
- Permission matrix verified in Playground: anonymous 401, subscriber 403, editor can list links only, admin full; invalid params 400.
- Admin menu "Cedar" (Translations, Languages), assets load only on Cedar screens, root config passed as an escaped data attribute (no inline script).
- `src/tokens/tokens.css` generated from Figma variables; Inter + Vazirmatn bundled (OFL, `assets/fonts/OFL.txt`).
- React components mirroring Figma (Button, Badge, Language tag, Tabs, Input, Select, Field, Checkbox, Toggle, Menu, Dialog, Toast, Empty state, Skeleton, Coverage card, Header).
- Screens: Translations (filters, content-type menu, search, pagination, bulk unlink with undo, link existing page) and Languages (list, drag + keyboard reorder, add from catalog, custom language, edit, remove dialog).
- `npm run build` passes; ESLint 0 errors; Stylelint clean.
- Minimum WordPress raised to 6.6 (`react-jsx-runtime`).

- Visual QA in Playground at 1440×1024 against Figma: Translations (all, add-translation menu, link existing page, bulk bar, undo) and Languages (edit, invalid hreflang, remove dialog, add from catalog, keyboard reorder). Fixed: RTL title alignment, card grid, draft dates, slug trailing slash, popovers clipped by the table (now fixed-position), candidate ordering, catalog ranking, help-text color.

## Milestone 3 — Editor (done)

- "Language · Cedar" panel: block editor (`PluginDocumentSettingPanel`) and classic metabox render the same React component. States from Figma: default, duplicating, all linked, no language, linking.
- Duplicate as draft (`Content/Duplicate.php`): validates first, then copies content, excerpt, meta (SEO fields, Elementor data, featured image; skips edit locks and caches), terms and translated parent; links the draft; removes the copy if linking fails. Verified byte-identical content/meta with backslashes and arrays.
- Translations table: "Duplicate as draft" in the add-translation menu, bulk "Duplicate missing as drafts".
- Link pickers: pages linked elsewhere are shown but can't be picked (no silent group changes).

## Milestone 4a — Switcher front end (done)

- Profiles (`cedar_ml_switchers`), items per request (only published translations are linked; missing → language home, hidden or disabled with a tooltip).
- One renderer + overridable template (`your-theme/cedar-multilang/switcher.php`), output always through `wp_kses()`.
- Five styles from Figma (Link with Plain/Outline/Soft/Solid, Inline, Segmented, Dropdown, Floating), shapes, show code/native/both, small-screen mode, "Inherit from theme".
- `[cedar_switcher id type show]`, legacy `[lang_switch]`, `cedar_ml_switcher()` / `cedar_ml_get_switcher()`, block `cedar-multilang/switcher`, classic menu box + Navigation block links (`#cedar-switcher-{id}`).
- CSS 2.0 KB and keyboard script 0.65 KB gzipped; dropdown works without JS.
- Tested on PHP 7.4 with Twenty Twenty-Five: every style, RTL mirroring, keyboard (Enter, arrows, Escape), invalid shortcode values ignored, classic menu, Navigation block, block registration.

## Milestone 4b — Switcher designer (done)

- Cedar → Switcher: style tiles (radio group, arrow keys), Content and Look settings, live preview (Desktop/Mobile, Light/Dark, EN/FA page with RTL) rendered with the real front-end CSS and markup (`SwitcherMarkup.js` mirrors `templates/switcher.php`), "Add it to your site" with Copy, save bar with Discard, create / delete switchers, unsaved-changes guard.
- Tested in Playground: style change, accent color, RTL + dark preview, save → front end uses the new accent.

## Milestone 5 — SEO & health (done)

- Integrations: detects Yoast SEO, Rank Math, All in One SEO. Pages marked noindex or canonicalized elsewhere (Yoast, Rank Math) leave the hreflang cluster. og:locale printed only when no SEO plugin prints it; og:locale:alternate always. Yoast XML sitemap gets `xhtml:link` alternates (valid XML).
- WordPress core sitemap can't carry hreflang (its renderer allows only loc/lastmod/changefreq/priority); without Yoast the toggle is disabled with an honest note — hreflang stays in each page's head.
- Health checks (stored in `cedar_ml_health`): one-way groups (stored members ≠ real members) with one-click repair, missing translations per language (Duplicate for a single page, Review otherwise), pages left out of hreflang.
- SEO screen: x-default, per-language hreflang, toggles, SEO plugins, "What search engines see" preview, health checks, save bar.
- Translations: Needs review tab (rows show the real pages with a One-way badge), SEO health card, `#missing` / `#review` / `#drafts` deep links.
- Tested with Yoast SEO 28.7 in Playground.

## Milestone 6 — Setup, Settings, About, importers (in progress on `m6-wip`)

- Setup wizard (hidden screen, reached from notices / plugin row): languages with catalog search (combobox), connect pages (WPML / Polylang import in batches + suggested pairs by language folder, suffix, slug and title), switcher look and placement (adds a menu item to the theme's primary menu, or the floating button), summary with next steps. Reaching the summary finishes setup.
- Importers read WPML (`icl_translations`, `icl_languages`) and Polylang (`language`, `post_translations` terms) straight from the database — work while the other plugin is deactivated, never write to its data, skip groups whose pages are gone or already linked differently, and report why.
- Settings: modules, content types, RTL compatibility toggles, delete-on-uninstall with export reminder, settings export/import (JSON; "delete on uninstall" is never imported; languages pages use are never removed).
- About: version, update notice from WordPress's own cached update check, changelog, system status (PHP check read from WordPress's cache — Cedar makes no remote requests), copy system info, credits.
- Notices: "Cedar is active — set it up" (Plugins), "Cedar is almost ready" (Dashboard), "Cedar 6.2 data was moved over"; manage_options only, dismissed per user with a nonce-checked link.
- Translations: Import and Link pages header buttons, first-run card.
- Tested so far (Playground, real Polylang 3.9 + simulated WPML tables): notices, setup step 1 (search, no match, add), step 2 (both sources detected, 5 suggested pairs incl. /fa/ folder pages, WPML import 2 linked + 1 skipped with review).

Next
1. Finish M6 testing (Polylang import, steps 3–4, Settings, About, Translations dialogs), RTL compat stylesheets after testing each theme, visual QA, merge.
2. PHPCS (WordPress, WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 7.4+): 0 errors, 0 warnings — run `composer lint` before every commit.
3. Later milestones add: Needs review tab + SEO health card (M5), Import / Link pages header buttons and first-run empty state (M6), Duplicate as draft (M3), Get Pro / Docs header links (when URLs exist).

Run locally
- `npm install && npm run build`
- `npm run playground`

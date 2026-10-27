# Progress

## Milestone 2 — REST + admin shell (done, merged to `main`)

Done
- `ako/v1` REST: `/languages` (GET, POST, PUT, DELETE, `/order`, `/catalog`), `/links` (overview), `/links/candidates`, `/links/{id}` (PUT link, DELETE unlink `scope=post|group`).
- Permission matrix verified in Playground: anonymous 401, subscriber 403, editor can list links only, admin full; invalid params 400.
- Admin menu "Ako" (Translations, Languages), assets load only on Ako screens, root config passed as an escaped data attribute (no inline script).
- `src/tokens/tokens.css` generated from Figma variables; Inter + Vazirmatn bundled (OFL, `assets/fonts/OFL.txt`).
- React components mirroring Figma (Button, Badge, Language tag, Tabs, Input, Select, Field, Checkbox, Toggle, Menu, Dialog, Toast, Empty state, Skeleton, Coverage card, Header).
- Screens: Translations (filters, content-type menu, search, pagination, bulk unlink with undo, link existing page) and Languages (list, drag + keyboard reorder, add from catalog, custom language, edit, remove dialog).
- `npm run build` passes; ESLint 0 errors; Stylelint clean.
- Minimum WordPress raised to 6.6 (`react-jsx-runtime`).

- Visual QA in Playground at 1440×1024 against Figma: Translations (all, add-translation menu, link existing page, bulk bar, undo) and Languages (edit, invalid hreflang, remove dialog, add from catalog, keyboard reorder). Fixed: RTL title alignment, card grid, draft dates, slug trailing slash, popovers clipped by the table (now fixed-position), candidate ordering, catalog ranking, help-text color.

## Milestone 3 — Editor (done)

- "Language · Ako" panel: block editor (`PluginDocumentSettingPanel`) and classic metabox render the same React component. States from Figma: default, duplicating, all linked, no language, linking.
- Duplicate as draft (`Content/Duplicate.php`): validates first, then copies content, excerpt, meta (SEO fields, Elementor data, featured image; skips edit locks and caches), terms and translated parent; links the draft; removes the copy if linking fails. Verified byte-identical content/meta with backslashes and arrays.
- Translations table: "Duplicate as draft" in the add-translation menu, bulk "Duplicate missing as drafts".
- Link pickers: pages linked elsewhere are shown but can't be picked (no silent group changes).

## Milestone 4a — Switcher front end (done)

- Profiles (`ako_ml_switchers`), items per request (only published translations are linked; missing → language home, hidden or disabled with a tooltip).
- One renderer + overridable template (`your-theme/ako-multilang/switcher.php`), output always through `wp_kses()`.
- Five styles from Figma (Link with Plain/Outline/Soft/Solid, Inline, Segmented, Dropdown, Floating), shapes, show code/native/both, small-screen mode, "Inherit from theme".
- `[ako_switcher id type show]`, legacy `[lang_switch]`, `ako_ml_switcher()` / `ako_ml_get_switcher()`, block `ako-multilang/switcher`, classic menu box + Navigation block links (`#ako-switcher-{id}`).
- CSS 2.0 KB and keyboard script 0.65 KB gzipped; dropdown works without JS.
- Tested on PHP 7.4 with Twenty Twenty-Five: every style, RTL mirroring, keyboard (Enter, arrows, Escape), invalid shortcode values ignored, classic menu, Navigation block, block registration.

## Milestone 4b — Switcher designer (done)

- Ako → Switcher: style tiles (radio group, arrow keys), Content and Look settings, live preview (Desktop/Mobile, Light/Dark, EN/FA page with RTL) rendered with the real front-end CSS and markup (`SwitcherMarkup.js` mirrors `templates/switcher.php`), "Add it to your site" with Copy, save bar with Discard, create / delete switchers, unsaved-changes guard.
- Tested in Playground: style change, accent color, RTL + dark preview, save → front end uses the new accent.

## Milestone 5 — SEO & health (done)

- Integrations: detects Yoast SEO, Rank Math, All in One SEO. Pages marked noindex or canonicalized elsewhere (Yoast, Rank Math) leave the hreflang cluster. og:locale printed only when no SEO plugin prints it; og:locale:alternate always. Yoast XML sitemap gets `xhtml:link` alternates (valid XML).
- WordPress core sitemap can't carry hreflang (its renderer allows only loc/lastmod/changefreq/priority); without Yoast the toggle is disabled with an honest note — hreflang stays in each page's head.
- Health checks (stored in `ako_ml_health`): one-way groups (stored members ≠ real members) with one-click repair, missing translations per language (Duplicate for a single page, Review otherwise), pages left out of hreflang.
- SEO screen: x-default, per-language hreflang, toggles, SEO plugins, "What search engines see" preview, health checks, save bar.
- Translations: Needs review tab (rows show the real pages with a One-way badge), SEO health card, `#missing` / `#review` / `#drafts` deep links.
- Tested with Yoast SEO 28.7 in Playground.

## Milestone 6 — Setup, Settings, About, importers (done)

- Setup wizard (hidden screen, reached from notices / plugin row): languages with catalog search (combobox), connect pages (WPML / Polylang import in batches + suggested pairs by language folder, suffix, slug and title), switcher look and placement (adds a menu item to the theme's primary menu, or the floating button), summary with next steps. Reaching the summary finishes setup.
- Importers read WPML (`icl_translations`, `icl_languages`) and Polylang (`language`, `post_translations` terms) straight from the database — work while the other plugin is deactivated, never write to its data, skip groups whose pages are gone or already linked differently, and report why.
- Settings: modules, content types, RTL compatibility toggles, delete-on-uninstall with export reminder, settings export/import (JSON; "delete on uninstall" is never imported; languages pages use are never removed).
- About: version, update notice from WordPress's own cached update check, changelog, system status (PHP check read from WordPress's cache — Ako makes no remote requests), copy system info, credits.
- Notices: "Ako is active — set it up" (Plugins), "Ako is almost ready" (Dashboard), "Cedar 6.2 data was moved over"; manage_options only, dismissed per user with a nonce-checked link.
- Translations: Import and Link pages header buttons, first-run card.
- Tested in Playground (real Polylang 3.9 + simulated WPML tables, Twenty Twenty-One): notices; every setup step (language search / no match, both import sources, WPML 2 linked + 1 skipped with review, Polylang 2 linked, re-import is safe, 5 suggested pairs incl. /fa/ folder pages connected, switcher added to the primary menu, summary 18 pages / 36 hreflang); default language locked once pages have languages; Settings save / export / import (bad file, unknown keys and unavailable content types rejected); About; front end after deactivating Polylang (RTL, hreflang, switcher in the menu).
- RTL compatibility stylesheets (Elementor, Hello Elementor, Astra, GeneratePress, Kadence) move to M7: each needs testing in its theme. Until a stylesheet ships, its toggle isn't shown.

## Milestone 7 — QA and release (done)

- Plugin Check (all categories) on the release zip: 0 errors, 0 warnings. WPML table names use `%i`; IN lists are built inline.
- Release zip now includes `blocks/` and `templates/` (the switcher block and template were missing).
- RTL tested on Farsi pages in Astra, Kadence, Hello Elementor, Twenty Twenty-Five, GeneratePress and Elementor (heading, text, icon list, icon box). Only GeneratePress needed a fix: it keeps left/right physical, so `assets/rtl/generatepress.css` mirrors the header and sidebars (desktop and mobile checked). Toggle in Settings; loads only on RTL pages.
- Accessibility: axe-core on all 7 admin screens, 0 violations. Secondary/tertiary text tokens moved to neutral 700/600 (Figma updated too) for 4.5:1; missing-cell label starts with its visible text; header no longer a nested banner.
- Performance: +4 queries on linked pages, 0 on the home page; 2 KB CSS, JS only where a switcher needs it.
- readme.txt for wordpress.org (Tested up to 7.1), banner, icon and five screenshots in `.wordpress-org/`.

## After M7 — fixes from a fresh-install test

- Block themes (the default since WordPress 6.x): the switcher block can now go inside the Navigation block (core only accepts listed blocks), and setup's "Header menu" placement adds it to the header's navigation, creating the menu from WordPress's fallback when there isn't one yet. Header patterns are resolved first (Twenty Twenty-Five keeps its header in one).
- A floating-style switcher placed in a menu, block or shortcode shows as a dropdown in place; the floating button stays the only floating one.
- Dropdown opens toward the other side when it would run off the screen.
- Setup detects the other languages: titles in another script (Farsi/Arabic/Urdu told apart by their letters), language folders like /de/, and installed language packs (offered, not pre-selected).
- The block editor canvas is right-to-left for right-to-left languages and follows the language picked in the panel.

## Theme compatibility pass

Automated run (Playwright + Playground, logged-out visitor, 1280px and 390px, English and Farsi page) on Astra, Hello Elementor (with Elementor), GeneratePress, Kadence, OceanWP, Neve, Blocksy, Twenty Twenty-Five, Twenty Twenty-Four and Twenty Twenty-One, for the Dropdown, Segmented and Link looks: setup finds the main menu and adds the switcher, it shows in the header (or the opened mobile menu), the dropdown opens on screen, the Farsi link leads to the Farsi page, the Farsi page is RTL, no horizontal scroll. 120 checks, all pass.

Fixed along the way:
- Themes hide `li ul` as sub-menus (Hello, Elementor menus) or move them off-screen (GeneratePress), which hid the switcher inside menus. The switcher no longer uses ul/li (div with role="list"/"listitem").
- A layout layer with real specificity (display, gap, padding, box-sizing) so theme resets (`summary { display: list-item }`, `.menu a { display: block }`, `.dropdown-menu * { padding: 0 }`, `#mobile-dropdown ul li a`) can't break it; colors and borders stay overridable.
- Setup prefers main-menu locations and skips footer, mobile, social and top-bar menus (OceanWP picked Top Bar, Blocksy picked Footer).

## Rename to Ako Multilang (October 27, 2026)

"Cedar" is an AWS trademark and the name of a CNCF open source project, and wordpress.org refuses slugs that start with another project's name. Renamed before the first submission: plugin name, slug and text domain `ako-multilang`, PHP namespace `Ako\Multilang`, prefixes `ako_ml` and `AKO_ML`, REST namespace `ako/v1`, CSS classes and custom properties `ako-`, block `ako-multilang/switcher`, shortcode `[ako_switcher]`, template override folder `ako-multilang/`. Kept on purpose: the 6.2 option keys the migration reads, the legacy `[lang_switch]` shortcode, and the `cedar` green palette family name (matches Figma). Figma still needs the rename, a new logo, and the copy pass.

Next
1. Before submitting: create the wp.org account's SVN assets from `.wordpress-org/`, run Plugin Check once more on the final zip, then submit at wordpress.org/plugins/developers/add/.
2. PHPCS (WordPress, WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 7.4+): 0 errors, 0 warnings — run `composer lint` before every commit.
3. Header Get Pro / Docs links once those URLs exist.

Run locally
- `npm install && npm run build`
- `npm run playground`

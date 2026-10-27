# Cedar Multilang — Architecture (v1.0)

> Companion to [brief.md](brief.md). Source of truth for UI: the Figma file (Foundations, Components, Admin screens, Switcher). This doc decides **how** we build it so it is fast, secure, and passes the wordpress.org review on the first try.

## 1. Targets

| | |
|---|---|
| Plugin slug / text domain | `cedar-multilang` |
| Requires WordPress | 6.6+ (block apiVersion 3, current editor plugin APIs, `react-jsx-runtime` used by @wordpress/scripts builds) |
| Requires PHP | 7.4+ (tested on 7.4 → 8.4) |
| Tested up to | latest stable WordPress at submission time |
| License | GPLv2 or later — every bundled file GPL-compatible |
| Prefix | PHP namespace `Cedar\Multilang`, functions/options/meta/hooks `cedar_ml_`, CSS `.cedar-`, JS globals none |

## 2. Repository layout

```
cedar-multilang/
├─ cedar-multilang.php        # Header + bootstrap only (no logic)
├─ uninstall.php              # Deletes data only if the user opted in
├─ readme.txt                 # wordpress.org readme (changelog, FAQ, screenshots)
├─ includes/                  # PHP, PSR-4 under Cedar\Multilang
│  ├─ Plugin.php              # Wires modules; nothing runs at file load
│  ├─ Settings.php            # Typed access to options + defaults
│  ├─ Languages/Registry.php  # Languages: code, locale, hreflang, names, dir, order, default
│  ├─ Content/Taxonomies.php  # Hidden taxonomies: cedar_ml_language, cedar_ml_group
│  ├─ Content/Links.php       # Link / unlink / get translations (the core API)
│  ├─ Content/Duplicate.php   # "Duplicate as draft" (content, meta allow-list, terms, thumbnail)
│  ├─ Seo/Hreflang.php        # <link rel="alternate">, x-default, <html lang dir>
│  ├─ Seo/Integrations/*.php  # Yoast, Rank Math, AIOSEO, WP core sitemap
│  ├─ Rtl/Locale.php          # Per-request locale → is_rtl(), theme rtl.css, .mo files
│  ├─ Rtl/Compat.php          # Small CSS fixes (assets/rtl/*.css), loaded on RTL pages only when the builder/theme is in use
│  ├─ Switcher/Renderer.php   # One renderer → block, shortcode, menu item, PHP tag
│  ├─ Switcher/Profiles.php   # Saved switcher styles ("Header switcher", …)
│  ├─ Switcher/Shortcode.php  # [cedar_switcher] + legacy [lang_switch]
│  ├─ Switcher/MenuItem.php   # Appearance → Menus integration
│  ├─ Rest/*.php              # REST controllers (namespace cedar/v1)
│  ├─ Admin/*.php             # Menu, assets, notices, plugin row links
│  ├─ Migration/Legacy62.php  # Cedar 6.2 data, migrated once automatically
│  ├─ Import/*.php            # WPML + Polylang: read straight from the database, batched, never write to their data
│  └─ Setup/*.php             # Setup wizard steps + suggested pairs (slug / title / language folder)
├─ src/                       # JS/SCSS sources (shipped — wp.org requires readable source)
│  ├─ admin/                  # React admin app (screens from Figma)
│  ├─ components/             # React components = Figma components, same names & variants
│  ├─ editor/                 # Block-editor "Language" panel (PluginDocumentSettingPanel)
│  ├─ classic/                # Classic-editor metabox enhancement
│  ├─ blocks/switcher/        # block.json + edit.js (dynamic block, PHP render)
│  └─ tokens/                 # tokens.css generated from Figma variables
├─ build/                     # Compiled by @wordpress/scripts (git-ignored, in release zip)
├─ templates/switcher.php     # Overridable via yourtheme/cedar-multilang/switcher.php
├─ assets/switcher.css        # Front-end CSS (< 3 KB), loaded only when a switcher renders
├─ languages/                 # .pot
└─ tests/                     # PHPUnit + JS unit tests
```

The 6.2 file (`cedar-multilang-helper.php`) stays in git history only; its data is migrated by `Migration/Legacy62.php`.

## 3. Data model

| What | Where | Why |
|---|---|---|
| Languages | option `cedar_ml_languages` (autoload) | Small, read on every request |
| A post's language | hidden taxonomy `cedar_ml_language` (term = language code) | Indexed queries ("all Arabic pages"), cached by core |
| Translation group | hidden taxonomy `cedar_ml_group` (one term per group) + term meta `cedar_ml_members` = `{lang: post_id}` | Linking by **ID**, so slug changes never break; one cached lookup per page |
| Switcher styles | option `cedar_ml_switchers` (autoloaded, small) | The floating switcher is checked on every front-end request |
| Settings | option `cedar_ml_settings` (autoload) | Modules, post types, RTL compat, fallback, uninstall choice |
| Dismissed notices | user meta `cedar_ml_dismissed` | Per user, never global |

Taxonomies are `public => false`, `show_ui => false`, `show_in_rest => false`, `rewrite => false` — they never create URLs. Front-end cost: one term-cache lookup on singular views, zero on archives.

## 4. Request flow (front end)

1. `wp` hook: resolve the queried post's language and group (object cache primed).
2. `switch_to_locale()` on `wp` (front end only) to that language's locale → `is_rtl()`, theme `rtl.css`, translations reload.
3. `language_attributes`: `lang` + `dir` on `<html>`.
4. `wp_head` (priority 1): hreflang cluster — published, indexable members only, self-reference, `x-default`. Skipped if an SEO plugin we integrate with already outputs hreflang.
5. Switcher renders through **one** renderer (block, shortcode, menu item and PHP tag all call it) and its output passes `wp_kses()` with a strict allow-list. The stylesheet (2 KB gzipped, all rules inside `:where()`) is enqueued in `<head>` on the front end to avoid a flash of unstyled content; accent colors come through `wp_add_inline_style()`. Zero JavaScript by default. The Dropdown and Floating styles are built on `<details>`/`<summary>` (work without JS) and load one < 1 KB progressive-enhancement script that adds ↑/↓ and Esc — accessibility wins over the zero-JS ideal, and only where it's needed.

No cookies, no redirects, no per-visitor output → full-page-cache safe.

## 5. Admin

- One top-level menu **Cedar** with submenus Translations · Languages · Switcher · SEO · Settings · About (About is last). No duplicate in-page tabs.
- Screens are a React app (`@wordpress/element`, `@wordpress/api-fetch`, `@wordpress/i18n`, `@wordpress/data`). Components are built from our Figma components (same names, variants, states); styling only through `tokens.css` custom properties.
- Editor panel: `PluginDocumentSettingPanel` (Block editor) and a classic metabox (Classic editor) render the **same** React component.
- Switcher block: `block.json` (apiVersion 3), dynamic render in PHP via the shared renderer.
- Notices: activation and migration only, dismissible, shown only to `manage_options` users, only on Dashboard / Plugins / Cedar screens.
- Plugin row: Settings · Docs action links; "Get Pro" in row meta (allowed, not a nag).

## 6. REST API — `cedar/v1`

| Route | Methods | Capability |
|---|---|---|
| `/languages` | GET, POST, PUT, DELETE | `manage_options` |
| `/links` | GET (paginated, filters) | `edit_posts` + per-row `edit_post` filtering |
| `/links/{post_id}` | GET, PUT (link), DELETE (unlink) | `edit_post` on **every** post touched |
| `/links/{post_id}/language` | PUT | `edit_post` |
| `/links/{post_id}/duplicate` | POST | `edit_post` source + `create_posts` for the post type |
| `/links/candidates` | GET | `edit_posts` for the post type; only editable posts listed |
| `/switchers` | GET | `edit_posts` (the block lists them) |
| `/switchers`, `/switchers/{id}` | POST, PUT, DELETE | `manage_options` |
| `/settings`, `/settings/export`, `/settings/import` | GET, PUT; GET; POST | `manage_options` |
| `/system` | GET (About: versions, cached update info) | `manage_options` |
| `/seo`, `/seo/preview`, `/seo/scan`, `/seo/fix` | GET, PUT; GET; POST; POST | `manage_options` |
| `/setup`, `/setup/languages`, `/setup/suggestions`, `/setup/pairs`, `/setup/switcher`, `/setup/summary`, `/setup/complete` | GET; PUT; GET; POST; POST; GET; POST | `manage_options` (+ `edit_post` on both pages of each pair, `edit_theme_options` to add a menu item) |
| `/import`, `/import/{wpml\|polylang}` | GET (sources), POST (one batch: `offset`, `limit` ≤ 50) | `manage_options` |

Every route has a `permission_callback` (never `__return_true`), a JSON schema with `sanitize_callback` + `validate_callback`, enums for every choice, and integer bounds. Auth is cookie + `X-WP-Nonce` via `api-fetch`.

## 7. Extensibility (for Pro and theme developers)

Actions: `cedar_ml_loaded`, `cedar_ml_linked`, `cedar_ml_unlinked`, `cedar_ml_duplicated`.
Filters: `cedar_ml_hreflang_links`, `cedar_ml_switcher_items`, `cedar_ml_switcher_markup`, `cedar_ml_duplicate_meta_keys`, `cedar_ml_health_checks`, `cedar_ml_admin_screens`.
The free plugin contains **no** license checks, upsell locks, or Pro code paths — Pro is a separate plugin that uses these hooks.

## 8. Security & wordpress.org compliance (non-negotiable)

**Access**
- Every PHP file starts with `defined( 'ABSPATH' ) || exit;`.
- Every write checks `current_user_can()` with the narrowest capability (table in §6); object-level checks use `edit_post( $id )` on each post, not just a global cap.
- Nonces on every state change: REST via `X-WP-Nonce`; any classic form via `wp_nonce_field()` + `check_admin_referer()`; AJAX is not used.

**Input → output**
- Read input once: `wp_unslash()` then a type-specific sanitizer (`absint`, `sanitize_key`, `sanitize_text_field`, `sanitize_hex_color`, `esc_url_raw`), or REST schema validation. Choices are allow-listed (presets, display modes, fallback, locales from `wp_get_available_translations()` / known list, hreflang matches `^[a-z]{2,3}(-[A-Za-z]{2,4})?$`).
- Escape late, at output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses()` with an explicit allow-list for the switcher template; translated strings via `esc_html__()` & co.
- No `eval`, `create_function`, `extract`, `unserialize` of user data, `$_REQUEST`, or dynamic `include` of user-controlled paths. Template override is `locate_template()` on a fixed filename.

**Data**
- Core APIs first (`WP_Query`, taxonomy & meta APIs). Direct SQL only in the WPML/Polylang importers — read-only, every query through `$wpdb->prepare()`, table existence checked first, results validated as integers.
- Settings import accepts JSON in the REST body only (size-capped, schema-validated); nothing is written to disk.
- `uninstall.php` checks `WP_UNINSTALL_PLUGIN` and deletes data only when "Delete data on uninstall" is on. Pages are never touched.

**Assets & privacy**
- No inline `<script>`/`<style>` echoed: everything through `wp_enqueue_*`, `wp_add_inline_style()` for custom properties, `wp_set_script_translations()` for JS i18n.
- No external requests, CDNs, fonts, tracking, or telemetry. All icons are bundled inline SVG (Lucide, ISC — credited in readme).
- No cookies; GDPR-neutral. Privacy note in readme.

**Review hygiene**
- Unique prefixes everywhere; no generic function/class/option names.
- Fully translatable; text domain = slug; no variables inside gettext calls; translators comments for placeholders.
- Human-readable source shipped (`src/`) plus build instructions in readme; no minified-only code, no obfuscation.
- Admin notices: dismissible, capability-gated, screen-limited. No admin-wide banners, no auto-redirect on activation without user action (setup is a notice button).
- Version numbers consistent in header, `readme.txt` Stable tag, and constants.

**Tooling gates (must pass before any release)**
- PHPCS: `WordPress`, `WordPress-Extra`, `WordPress-Docs`, `PHPCompatibilityWP` (7.4+) — zero errors.
- Plugin Check (PCP) — zero errors, warnings reviewed.
- ESLint / Stylelint via `@wordpress/scripts`.
- Manual: keyboard-only pass, screen-reader pass on the switcher, RTL pass, cache-plugin pass.

## 9. Design fidelity

- `tokens.css` is generated from the Figma variables (same names as the code syntax already set on every variable). No hard-coded colors, spacing, radii, or font sizes in CSS.
- React components mirror the Figma component list (Button, Input, Field, Checkbox, Radio, Toggle, Tab, Badge, Alert, Toast, Save bar, Setting row, …) with the same props as the Figma variants.
- Each screen is checked against its Figma frame (1440 × 1024) before it's called done.

## 10. Local development

- WordPress Playground (no PHP/Docker needed): `npx @wp-playground/cli server --mount=.:/wordpress/wp-content/plugins/cedar-multilang --login`.
- `npm run start` (watch) / `npm run build` / `npm run plugin-zip`.
- PHP linting runs through Composer + PHPCS (PHP is needed for that step — install via Homebrew when we reach it).

## 11. Milestones

1. **Core** — bootstrap, languages, taxonomies, link API, hreflang, lang/dir, locale/RTL, 6.2 migration.
2. **REST + admin shell** — tokens, base components, Translations & Languages screens.
3. **Editor** — Block-editor panel + classic metabox, duplicate as draft.
4. **Switcher** — renderer, template, CSS, block, shortcode, menu item, profiles, designer screen.
5. **SEO & health** — integrations, sitemap, health checks.
6. **Onboarding, Settings, About, importers** — WPML/Polylang.
7. **QA & release** — PHPCS, PCP, a11y, perf, readme, assets, screenshots.

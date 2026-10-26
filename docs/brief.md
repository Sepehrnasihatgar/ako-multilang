# Cedar Multilang — Product Brief (v1.0)

> **A lightweight bilingual plugin for RTL sites. Link your pages across languages — no WPML, no slowdown.**

## The problem
Most bilingual WordPress sites use WPML for one thing: linking the English page to its Farsi (or Arabic, or Hebrew) twin. For that, they pay ~$100/year, take a real hit on page speed, and ignore 90% of the features (auto-translation, string translation, etc.). Polylang is free, but it rewrites your URL structure and is heavy to bolt onto a site that already exists. And on RTL sites, right-to-left layout rarely works out of the box with Elementor or popular themes.

## Who it's for
- **Bilingual site owners** who already built a page per language and just want them linked properly.
- **Designers and freelancers** building Farsi/English or Arabic/English client sites, often with Elementor.

## Principles
1. **Lightweight.** No front-end JavaScript except a < 1 KB keyboard enhancement for the Dropdown/Floating styles. CSS loads only on pages that actually render a switcher. At most one extra DB query per page.
2. **SEO-first.** Every output follows Google's multilingual guidelines.
3. **RTL-first.** Farsi, Arabic, and Hebrew pages render correctly with no hand-written CSS.
4. **Hands off your URLs and theme.** Install on a live site; nothing breaks.
5. **Modular.** SEO, RTL, Switcher, and Migration are independent modules you can turn on or off.
6. **Cache-friendly.** No cookies, no redirects, no per-visitor output — works with every caching plugin and CDN.

## v1.0 scope

### 1. Languages & linking
- Any number of languages, each with code, locale, native name, and text direction. One is the default.
- All public post types: pages, posts, WooCommerce products, CPTs.
- An editor sidebar panel: set "this page's language" and link its translations (searchable, not a giant dropdown).
- A **Translations** overview screen with a "Missing translation" filter.
- Links are stored by ID, so changing a slug never breaks anything.

### 2. SEO
- Complete hreflang clusters: every version, self-referencing, configurable `x-default`.
- Drafts, private, and noindex pages are excluded from hreflang.
- Correct `<html lang dir>`, with configurable regional codes (`fa-IR`, `en-GB`).
- Canonicals untouched — each version keeps its own self-canonical.
- **Yoast, Rank Math, and AIOSEO integration:** `og:locale` + `og:locale:alternate`, hreflang in XML sitemaps.
- No duplicate hreflang if another plugin already outputs it.
- No automatic redirects based on IP or browser language (per Google's guidance).
- Switcher links are real, crawlable `<a href>` elements with `hreflang` and `lang` attributes.

### 3. RTL
- Switches the WordPress locale per page, so `is_rtl()` is accurate and the theme's own `rtl.css` loads.
- Toggleable RTL fixes for Elementor and five popular themes: Hello Elementor, Astra, GeneratePress, Kadence, Twenty Twenty-Five.

### 4. Language switcher (the design showcase)
The switcher has to fit *any* site, not force its own look on it. Two layers:

**a) Our presets — ready to use, nicely designed**
- **Types:** text links, segmented toggle, dropdown, floating button.
- **Placements:** header/nav, mobile menu, footer, inline in content, floating corner.
- **Content:** native name, language code, or flag (optional), with or without an icon.
- **Responsive:** e.g. collapses from full name to code on small screens.
- **States:** default, hover, focus, active (current language), disabled (no translation).
- **Accessibility:** full keyboard support, ARIA, WCAG AA contrast.
- **Styling:** presets + CSS custom properties, with a live preview in the admin. Fonts inherit from the site by default, or pick from fonts already installed (WordPress Font Library, theme, Elementor custom fonts).

**b) Bring-your-own-style — so it never fights the user's design**
- **Shortcode** with attributes, usable anywhere (page builders, widgets, template parts):
  `[cedar_switcher type="dropdown" show="name" style="inherit" class="my-class"]`
- **`style="inherit"` / unstyled mode:** clean semantic markup (`nav > ul > li > a`) with BEM classes. Inherits the theme's font, color, and sizing — no fixed widths, no layout shifts.
- **Low-specificity CSS:** every rule is scoped under `.cedar-switcher` and wrapped in `:where()`, so any theme or custom CSS overrides it with zero `!important`.
- **PHP template tag** `cedar_switcher( $args )` for theme developers.
- **Template override:** copy `templates/switcher.php` into `yourtheme/cedar/` to fully customize the markup.
- **Filters** on the language list and on the output markup.
- **Ways to add it:** Gutenberg block, nav menu item, shortcode, Elementor widget, PHP template tag.
- **When there's no translation:** hide it, link to that language's homepage, or show it disabled.
- The legacy `[lang_switch]` shortcode keeps working.

### 5. Migration
- **Cedar 6.2:** data migrates automatically on update.
- **Polylang & WPML:** translation links are read straight from the database, even after the other plugin is deactivated. WPML import is tested against simulated data.

## Free vs Pro
**Rule:** the free plugin fully solves the problem — nothing locked, no limits, no trials (wordpress.org guideline 5). Pro is a separate add-on plugin (`cedar-multilang-pro`) that plugs into free via hooks, sold from our own site.

| Area | Free | Pro |
|---|---|---|
| Languages & content | Unlimited languages and content, all post types, categories/tags, per-language menus | — |
| Linking | Editor panel, Translations overview, **duplicate as new translation**, auto-suggested pairs (by slug/title) | Bulk linking via CSV import/export |
| SEO | Everything in §2 + inline health warnings | Scheduled SEO health reports (email/PDF) |
| RTL | Everything in §3 | — |
| Switcher | All presets, inherit mode, every insertion method, live preview | Custom saved presets, per-location styles, custom icons/flags, "Also available in your language" banner |
| Migration | Cedar 6.2, Polylang, WPML | — |
| WooCommerce | Link products across languages | Sync price, stock and attributes between linked products |
| Other | — | Multisite |

Pro licenses are tiered by number of sites. UI rule: one "Cedar Pro" screen plus a few dismissible one-line hints — no disabled or locked controls in free.

**Release phasing:** taxonomies/menus and auto-suggest ship in v1.1; everything else in free ships in v1.0.

## Deliberately out of scope
Auto-translation, language prefixes in URLs (`/fa/`), theme string translation, browser-language redirects.

## Success metrics
- **Speed:** no change in Lighthouse score with the plugin active; < 3 KB of front-end CSS.
- **Time to value:** install → first linked pair in under 5 minutes.
- **Code quality:** passes Plugin Check and WPCS with zero errors.
- **SEO:** hreflang output validates in standard hreflang testing tools.

## Phases
1. ~~Brief~~
2. **Figma:** user flows, admin screens, switcher component library (variants, responsive, RTL)
3. **Build** from the designs
4. **Release:** `readme.txt`, icon, banners, screenshots, case study

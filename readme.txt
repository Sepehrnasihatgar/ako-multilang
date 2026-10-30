=== Ako Multilang ===
Contributors: sepehrnasihatgar
Tags: multilingual, hreflang, rtl, language switcher, bilingual
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link your pages across languages with hreflang, right-to-left support, and a language switcher. Your URLs stay the same.

== Description ==

Ako Multilang connects pages you already have in different languages. It doesn't translate anything, and it never changes your URLs, so it fits right into a site you've already built.

It's made for bilingual sites with a right-to-left language, like Farsi, Arabic, or Hebrew next to English. It works just as well for any other pair of languages.

= Link your pages =

* Link any post, page, or custom post type to its translations. Do it from the editor, the Translations screen, or the setup.
* Setup finds the languages your site already uses and suggests page pairs from your URLs and titles, like `/about` and `/fa/about`. You check every pair before anything is saved.
* Start a translation with one click: Ako copies a page into a draft in the other language.
* Works with pages you built in Elementor or the block editor.

= SEO =

* `hreflang` tags for every linked page, including `x-default`.
* The right `lang` and `dir` on every page, plus `og:locale` for social previews.
* Works alongside Yoast SEO, Rank Math, and All in One SEO. Pages they mark noindex, or point elsewhere with a canonical, stay out of `hreflang`.
* A health check that finds pages missing a translation and links that only go one way.

= Right-to-left =

* Each page loads in its own language, so `is_rtl()` is correct and your theme's own `rtl.css` loads on right-to-left pages.
* The block editor switches to right-to-left while you write a Farsi, Arabic, or Hebrew page.
* Tested with Astra, Hello Elementor, GeneratePress, Kadence, OceanWP, Neve, Blocksy, Twenty Twenty-Five, Twenty Twenty-Four, Twenty Twenty-One, and Elementor. GeneratePress gets a small fix that mirrors its header and sidebar on right-to-left pages. You can turn it off in Settings.

= Language switcher =

* Add it as a block, a menu item, the `[akoml_switcher]` shortcode, or a floating button. Setup can add it to your header menu for you, on classic and block themes.
* Five looks: link, inline, segmented, dropdown, and floating, with a live preview in the admin.
* Works with a keyboard and screen readers. When a page has no translation, it links to that language's homepage.

= Switching from another plugin =

* Imports linked pages from WPML and Polylang. Ako only reads their data and never changes it, so you can switch back anytime.

= Privacy =

Ako Multilang makes no external requests, adds no tracking, and collects no data. Its admin fonts and icons ship with the plugin.

== Installation ==

1. Install Ako Multilang from the Plugins screen, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate it.
3. Follow the setup: pick your languages, connect your pages, and add the switcher.

You can change all of it later under Ako in the admin menu.

== Frequently Asked Questions ==

= Does it translate my content? =

No. It links pages you've already written in each language. To start a translation, use "Duplicate as draft" and write the new version yourself.

= Does it change my URLs? =

No. Every page keeps its address. Ako only adds the links between them.

= I use WPML or Polylang. Can I switch? =

Yes. Setup finds their data and imports your linked pages. Their own data isn't changed, so you can deactivate them once the import is done, or go back.

= Does it work with Elementor? =

Yes. Elementor pages link like any other page, and right-to-left pages load Elementor's right-to-left styles. To show the switcher in an Elementor layout, put the `[akoml_switcher]` shortcode in a Shortcode widget.

= The switcher doesn't show in my menu. What should I check? =

Make sure you added it to the menu your header actually uses. On classic themes, that's the menu assigned to the main location in Appearance → Menus. On block themes, open the header in the Site Editor and add the "Language Switcher" block to the navigation. You also need at least two languages, since the switcher hides itself when there's nothing to switch to.

= Where is the source code? =

The admin screens, editor panel, and block are built with `@wordpress/scripts`. Their source ships with the plugin in the `src` folder, and the full project, including build steps, is on GitHub: https://github.com/Sepehrnasihatgar/ako-multilang

= What happens when I uninstall it? =

Your pages stay exactly as they are. Ako's settings and links stay too, unless you turn on "Delete Ako data on uninstall" in Settings first.

== Screenshots ==

1. Translations: every page and its translations in one place.
2. Setup suggests page pairs from your URLs and titles.
3. Switcher looks with a live preview.
4. SEO: the exact hreflang search engines see, plus health checks.
5. Linking a translation from the editor.

== Changelog ==

= 1.0.1 =
* The floating switcher’s arrow now points up, toward the menu it opens.

= 1.0.0 =
* First public release.

== Credits ==

Admin fonts: Inter (Copyright 2016 The Inter Project Authors) and Vazirmatn (Copyright 2015 The Vazirmatn Project Authors), both under the SIL Open Font License 1.1. The license is in `assets/fonts/OFL.txt`.

Icons: Lucide (https://lucide.dev), under the ISC License, with portions from Feather under the MIT License. The license is in `assets/icons/LICENSE-lucide.txt`.

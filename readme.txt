=== Cedar Multilang ===
Contributors: sepehrnasihatgar
Tags: multilingual, hreflang, rtl, language switcher, bilingual
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link your pages across languages: hreflang, right-to-left support and a language switcher, without changing your URLs.

== Description ==

Cedar Multilang connects pages you already have in different languages. It doesn't translate content and it doesn't change your URLs, so it fits a site that already exists.

It's built for bilingual sites with a right-to-left language, like Farsi, Arabic or Hebrew next to English, and it works for any language pair.

= Link your pages =

* Link any post, page or custom post type to its translations from the editor, the Translations screen or the setup wizard.
* Setup suggests pairs from your URLs and titles, like `/about` and `/fa/about`. You check them before anything is saved.
* Duplicate a page as a draft in another language to start a translation.
* Keeps working with pages you've already built in Elementor or the block editor.

= SEO =

* `hreflang` tags for every linked page, with `x-default`.
* The right `lang` and `dir` on every page, and `og:locale` for social previews.
* Works next to Yoast SEO, Rank Math and All in One SEO. Pages they mark noindex, or point elsewhere with a canonical, are left out of `hreflang`.
* A health check that finds pages missing a translation and links that only go one way.

= Right-to-left =

* Each page loads in its own language, so `is_rtl()` is right and your theme's own `rtl.css` loads on right-to-left pages.
* Tested with Astra, Kadence, Hello Elementor, Twenty Twenty-Five and Elementor. GeneratePress gets a small fix that mirrors its header and sidebar on right-to-left pages. You can turn it off in Settings.

= Language switcher =

* A block, a menu item, the `[cedar_switcher]` shortcode or a floating button.
* Four looks: text link, segmented, dropdown and floating, with a live preview in the admin.
* Keyboard and screen reader friendly, and it links to the homepage when a page has no translation.

= Moving from another plugin =

* Imports linked pages from WPML and Polylang. Cedar reads their data and never changes it, so you can switch back at any time.
* Keeps pairs and the `[lang_switch]` shortcode from Cedar Multilang 6.2.

= Privacy =

Cedar Multilang makes no external requests, adds no tracking and collects no data. Its admin fonts ship with the plugin.

== Installation ==

1. Install Cedar Multilang from the Plugins screen, or upload the plugin folder to `/wp-content/plugins/`.
2. Activate it.
3. Follow the setup: pick your languages, connect your pages and add the switcher.

You can change any of it later under Cedar in the admin menu.

== Frequently Asked Questions ==

= Does it translate my content? =

No. It links pages you've already written in each language.

= Does it change my URLs? =

No. Every page keeps its address. Cedar only adds the links between them.

= I use WPML or Polylang. Can I switch? =

Yes. Setup finds their data and imports your linked pages. Their own data isn't changed, so you can deactivate them once the import is done, or go back.

= Does it work with Elementor? =

Yes. Elementor pages link like any other page, and right-to-left pages load Elementor's right-to-left styles.

= What happens when I uninstall it? =

Your pages stay as they are. Cedar's settings and links stay too, unless you turn on "Delete Cedar data on uninstall" in Settings first.

== Screenshots ==

1. Translations: every page and its translations in one place.
2. Setup: Cedar suggests pairs from your URLs and titles.
3. Language switcher looks with a live preview.
4. SEO: the exact hreflang search engines see, plus health checks.
5. Linking a translation from the editor.

== Changelog ==

= 1.0.0 =
* First public release.

== Credits ==

Admin fonts: Inter (Copyright 2016 The Inter Project Authors) and Vazirmatn (Copyright 2015 The Vazirmatn Project Authors), both under the SIL Open Font License 1.1. The license is in `assets/fonts/OFL.txt`.

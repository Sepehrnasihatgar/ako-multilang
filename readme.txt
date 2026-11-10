=== Ako Multilang ===
Contributors: sepehrnasihatgar
Tags: multilingual, hreflang, rtl, language switcher, bilingual
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link the pages you already have in each language. Get hreflang, right-to-left layouts, and a language switcher, with no URL changes.

== Description ==

Ako Multilang turns the pages you already have into a bilingual site. Link each page to its translation, and Ako handles the rest: hreflang for search engines, the right layout for right-to-left languages, and a switcher your visitors can actually find.

No machine translation. No URL changes. Just your site, in two languages.

= Why Ako =

* **Keeps your URLs.** Nothing moves, so your rankings and links stay intact.
* **Right-to-left built in.** Farsi, Arabic, and Hebrew pages load your theme’s right-to-left styles, and the editor flips while you write.
* **Set up in minutes.** Pick your languages, approve suggested page pairs, and drop the switcher into your menu.
* **Everything included.** No add-ons, no subscription.

= Features =

* **Link anything.** Posts, pages, and custom post types, right from the editor or the Translations screen. Duplicate a page as a draft in the other language to start a translation.
* **SEO handled.** `hreflang` with `x-default`, `lang`, `dir`, and `og:locale`. Works with Yoast SEO, Rank Math, and All in One SEO, and a health check flags missing or one-way links.
* **Five switcher styles.** Link, inline, segmented, dropdown, or floating, with a live preview. Add it as a block, a menu item, a floating button, or the `[akoml_switcher]` shortcode.
* **Accessible.** Works with a keyboard and screen readers. If a page has no translation yet, the switcher links to that language’s homepage.
* **Easy to switch.** Imports your links from WPML and Polylang without touching their data.

= Tested with =

Astra, Hello Elementor, GeneratePress, Kadence, OceanWP, Neve, Blocksy, Twenty Twenty-One, Twenty Twenty-Four, Twenty Twenty-Five, and Elementor.

= Privacy =

No external requests, no tracking, and no data collection.

== Installation ==

1. Go to **Plugins → Add New Plugin** and search for “Ako Multilang.”
2. Install and activate it.
3. Click **Start setup** and follow three quick steps: languages, pages, and switcher.

Nothing on your site changes until you finish setup.

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

= 1.0.2 =
* Security: unlinking or relinking a page now also checks that you can edit any page it leaves without a translation.
* The shortcode is now `[akoml_switcher]`.

= 1.0.1 =
* The floating switcher’s arrow now points up, toward the menu it opens.

= 1.0.0 =
* First public release.

== Credits ==

Admin fonts: Inter (Copyright 2016 The Inter Project Authors) and Vazirmatn (Copyright 2015 The Vazirmatn Project Authors), both under the SIL Open Font License 1.1. The license is in `assets/fonts/OFL.txt`.

Icons: Lucide (https://lucide.dev), under the ISC License, with portions from Feather under the MIT License. The license is in `assets/icons/LICENSE-lucide.txt`.

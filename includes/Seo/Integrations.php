<?php
/**
 * Working next to SEO plugins: noindex, canonicals, og:locale and sitemaps.
 *
 * @package AkoMultilang
 */

namespace AkoMultilang\Seo;

use AkoMultilang\Content\Links;
use AkoMultilang\Content\Taxonomies;
use AkoMultilang\Languages\Registry;
use AkoMultilang\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Detects Yoast SEO, Rank Math and All in One SEO and adapts Ako's output:
 * pages they mark noindex (or canonicalize elsewhere) leave the hreflang
 * cluster, og:locale is only printed when no SEO plugin prints it, and Yoast's
 * XML sitemap gets hreflang alternates.
 */
final class Integrations {

	/**
	 * Hooks the integrations.
	 */
	public static function register() {
		$settings = Settings::get();

		if ( ! empty( $settings['skip_noindex'] ) ) {
			add_filter( 'akoml_is_indexable', array( self::class, 'respect_seo_plugins' ), 10, 2 );
		}
		if ( ! empty( $settings['og_locale'] ) ) {
			add_action( 'wp_head', array( self::class, 'print_og_locale' ), 2 );
		}
		if ( ! empty( $settings['sitemap'] ) && self::is_active( 'yoast' ) ) {
			add_filter( 'wpseo_sitemap_urlset', array( self::class, 'yoast_urlset' ) );
			add_filter( 'wpseo_sitemap_entry', array( self::class, 'yoast_entry' ), 10, 3 );
			add_filter( 'wpseo_sitemap_url', array( self::class, 'yoast_url' ), 10, 2 );
		}
	}

	/**
	 * Known SEO plugins and how to detect them.
	 *
	 * @return array<string,array{name:string,constant:string}>
	 */
	public static function known() {
		return array(
			'yoast'    => array(
				'name'     => 'Yoast SEO',
				'constant' => 'WPSEO_VERSION',
			),
			'rankmath' => array(
				'name'     => 'Rank Math',
				'constant' => 'RANK_MATH_VERSION',
			),
			'aioseo'   => array(
				'name'     => 'All in One SEO',
				'constant' => 'AIOSEO_VERSION',
			),
		);
	}

	/**
	 * Whether a known SEO plugin is active.
	 *
	 * @param string $key yoast|rankmath|aioseo.
	 * @return bool
	 */
	public static function is_active( $key ) {
		$known = self::known();
		return isset( $known[ $key ] ) && defined( $known[ $key ]['constant'] );
	}

	/**
	 * Status of each known plugin, for the SEO screen.
	 *
	 * @return array<int,array{key:string,name:string,active:bool,version:string}>
	 */
	public static function status() {
		$out = array();
		foreach ( self::known() as $key => $plugin ) {
			$active = defined( $plugin['constant'] );
			$out[]  = array(
				'key'     => $key,
				'name'    => $plugin['name'],
				'active'  => $active,
				'version' => $active ? (string) constant( $plugin['constant'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Which sitemap carries hreflang: 'yoast', or 'none' (head only).
	 *
	 * @return string
	 */
	public static function sitemap_provider() {
		return self::is_active( 'yoast' ) ? 'yoast' : 'none';
	}

	/**
	 * Drops pages that an SEO plugin marks noindex or canonicalizes to
	 * another URL — hreflang belongs on canonical, indexable pages only.
	 *
	 * @param bool $indexable Indexable so far.
	 * @param int  $post_id   Post ID.
	 * @return bool
	 */
	public static function respect_seo_plugins( $indexable, $post_id ) {
		if ( ! $indexable ) {
			return false;
		}
		return ! self::is_noindex( $post_id ) && ! self::canonical_elsewhere( $post_id );
	}

	/**
	 * Noindex set in Yoast SEO or Rank Math.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_noindex( $post_id ) {
		if ( self::is_active( 'yoast' ) && '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return true;
		}
		if ( self::is_active( 'rankmath' ) ) {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A custom canonical (Yoast SEO, Rank Math) that points to another URL.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function canonical_elsewhere( $post_id ) {
		$canonical = '';
		if ( self::is_active( 'yoast' ) ) {
			$canonical = (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
		}
		if ( '' === $canonical && self::is_active( 'rankmath' ) ) {
			$canonical = (string) get_post_meta( $post_id, 'rank_math_canonical_url', true );
		}
		if ( '' === $canonical ) {
			return false;
		}
		return untrailingslashit( esc_url_raw( $canonical ) ) !== untrailingslashit( (string) get_permalink( $post_id ) );
	}

	/**
	 * Prints og:locale for the current language (when no SEO plugin prints it — their
	 * value follows the switched locale) plus og:locale:alternate for each
	 * published translation.
	 */
	public static function print_og_locale() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! Taxonomies::supports( $post->post_type ) ) {
			return;
		}

		$translations = Links::get_translations( $post->ID );
		$current      = Links::get_language( $post->ID );
		if ( ! $current || count( $translations ) < 2 ) {
			return;
		}

		$any_seo_plugin = self::is_active( 'yoast' ) || self::is_active( 'rankmath' ) || self::is_active( 'aioseo' );
		$language       = Registry::get( $current );
		if ( ! $any_seo_plugin && $language ) {
			printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( self::og_locale( $language['locale'] ) ) );
		}

		foreach ( $translations as $code => $member_id ) {
			$other = Registry::get( $code );
			if ( $code === $current || ! $other || ! Hreflang::is_indexable( $member_id ) ) {
				continue;
			}
			printf( '<meta property="og:locale:alternate" content="%s" />' . "\n", esc_attr( self::og_locale( $other['locale'] ) ) );
		}
	}

	/**
	 * Open Graph wants ll_CC; WordPress locales like "ar" get a region-less value.
	 *
	 * @param string $locale WordPress locale.
	 * @return string
	 */
	public static function og_locale( $locale ) {
		$parts = explode( '_', (string) $locale );
		return isset( $parts[1] ) ? $parts[0] . '_' . $parts[1] : $parts[0];
	}

	/**
	 * Yoast sitemap: declare the xhtml namespace.
	 *
	 * @param string $urlset Opening <urlset> tag.
	 * @return string
	 */
	public static function yoast_urlset( $urlset ) {
		if ( false === strpos( $urlset, 'xmlns:xhtml' ) ) {
			$urlset = str_replace( '<urlset ', '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml" ', $urlset );
		}
		return $urlset;
	}

	/**
	 * Yoast sitemap: remember each post's hreflang cluster.
	 *
	 * @param array  $url  Sitemap entry.
	 * @param string $type Entry type.
	 * @param mixed  $item Post (for type "post").
	 * @return array
	 */
	public static function yoast_entry( $url, $type, $item ) {
		if ( 'post' === $type && $item instanceof \WP_Post && Taxonomies::supports( $item->post_type ) && is_array( $url ) ) {
			$url['akoml_hreflang'] = Hreflang::cluster( $item->ID );
		}
		return $url;
	}

	/**
	 * Yoast sitemap: print the alternates inside the entry.
	 *
	 * @param string $output Entry XML.
	 * @param array  $url    Sitemap entry.
	 * @return string
	 */
	public static function yoast_url( $output, $url ) {
		if ( empty( $url['akoml_hreflang'] ) || ! is_array( $url['akoml_hreflang'] ) ) {
			return $output;
		}
		$links = '';
		foreach ( $url['akoml_hreflang'] as $hreflang => $href ) {
			$links .= sprintf(
				"\t\t<xhtml:link rel=\"alternate\" hreflang=\"%1\$s\" href=\"%2\$s\" />\n",
				esc_attr( $hreflang ),
				esc_url( $href )
			);
		}
		return str_replace( '</url>', $links . "\t</url>", $output );
	}
}

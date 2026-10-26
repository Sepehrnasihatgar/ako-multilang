<?php
/**
 * Plugin Name: Cedar Multilang Website
 * Description: Manage bilingual pages, with the ability to switch between languages button | RTL/LTR, hreflang, Persian/English page pairing, and language button styling without the need for WPML or Elementor Pro
 * Version: 6.2
 * Author: Sepehr Nasihatgar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CEDAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CEDAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* =========================================================
   Bundled fonts (self-hosted inside this plugin, no external requests)
   ========================================================= */
function cedar_get_bundled_fonts() {
	return array(
		'vazirmatn' => array(
			'label'   => 'Vazirmatn (Farsi)',
			'family'  => 'Cedar Vazirmatn',
			'type'    => 'variable',
			'weights' => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
			'file'    => 'Vazirmatn-Variable.woff2',
		),
		'sahel' => array(
			'label'   => 'Sahel (Farsi)',
			'family'  => 'Cedar Sahel',
			'type'    => 'variable',
			'weights' => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
			'file'    => 'Sahel-Variable.woff2',
		),
		'estedad' => array(
			'label'   => 'Estedad (Farsi)',
			'family'  => 'Cedar Estedad',
			'type'    => 'variable',
			'weights' => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
			'file'    => 'Estedad-Variable.woff2',
		),
		'inter' => array(
			'label'   => 'Inter',
			'family'  => 'Cedar Inter',
			'type'    => 'variable',
			'weights' => array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ),
			'file'    => 'Inter-Variable.woff2',
		),
		'poppins' => array(
			'label'   => 'Poppins',
			'family'  => 'Cedar Poppins',
			'type'    => 'static',
			'weights' => array( 400, 600, 700 ),
			'files'   => array(
				400 => 'Poppins-400.woff2',
				600 => 'Poppins-600.woff2',
				700 => 'Poppins-700.woff2',
			),
		),
		'roboto' => array(
			'label'   => 'Roboto',
			'family'  => 'Cedar Roboto',
			'type'    => 'static',
			'weights' => array( 400, 600, 700 ),
			'files'   => array(
				400 => 'Roboto-400.woff2',
				600 => 'Roboto-600.woff2',
				700 => 'Roboto-700.woff2',
			),
		),
	);
}

function cedar_get_bundled_icons() {
	return array(
		'languages' => 'Languages (double A)',
		'globe'     => 'Globe (simple)',
		'earth'     => 'Earth (detailed)',
	);
}

/* =========================================================
   Multi-button storage
   ========================================================= */
function cedar_get_button_defaults() {
	return array(
		'label'            => '',
		'bg_color'         => '',
		'bg_color_hover'   => '',
		'text_color'       => '',
		'text_color_hover' => '',

		'border_radius_num'  => '',
		'border_radius_unit' => 'px',
		'padding_num'        => '',
		'padding_unit'       => 'px',
		'margin_num'         => '',
		'margin_unit'        => 'px',

		'font_source_en'    => '',
		'font_custom_en'    => '',
		'font_weight_en'    => '',
		'font_size_en_num'  => '',
		'font_size_en_unit' => 'px',

		'font_source_fa'    => '',
		'font_custom_fa'    => '',
		'font_weight_fa'    => '',
		'font_size_fa_num'  => '',
		'font_size_fa_unit' => 'px',

		'icon_choice'    => 'none',
		'icon_size_num'  => '',
		'icon_size_unit' => 'px',

		'icon_position_en' => 'left',
		'icon_position_fa' => 'left',

		'label_source_en' => '',
		'label_custom_en' => '',
		'label_source_fa' => '',
		'label_custom_fa' => '',
	);
}

function cedar_get_all_buttons() {
	$buttons = get_option( 'cedar_buttons', null );

	if ( $buttons === null ) {
		// First run on this install, or upgrading from the single-button version — migrate.
		$legacy = get_option( 'cedar_style_settings', array() );
		if ( ! empty( $legacy ) ) {
			$migrated       = wp_parse_args( $legacy, cedar_get_button_defaults() );
			$migrated['label'] = 'Button 1';
			$buttons = array( 1 => $migrated );
		} else {
			$defaults          = cedar_get_button_defaults();
			$defaults['label'] = 'Button 1';
			$buttons = array( 1 => $defaults );
		}
		update_option( 'cedar_buttons', $buttons );
		if ( get_option( 'cedar_button_next_id', false ) === false ) {
			update_option( 'cedar_button_next_id', 2 );
		}
	}

	return $buttons;
}

function cedar_get_button_settings( $id ) {
	$buttons = cedar_get_all_buttons();
	if ( isset( $buttons[ $id ] ) ) {
		return wp_parse_args( $buttons[ $id ], cedar_get_button_defaults() );
	}
	// Fall back to the first available button so a bad id never breaks the front-end
	$first = reset( $buttons );
	return $first ? wp_parse_args( $first, cedar_get_button_defaults() ) : cedar_get_button_defaults();
}

function cedar_combine_dimension( $num, $unit ) {
	if ( $num === '' || $num === null ) {
		return '';
	}
	$unit = $unit ? $unit : 'px';
	return $num . $unit;
}

function cedar_resolve_font( $lang, $settings, $bundled ) {
	$source = $settings[ 'font_source_' . $lang ];
	$weight = $settings[ 'font_weight_' . $lang ];

	if ( $source === 'custom' && ! empty( $settings[ 'font_custom_' . $lang ] ) ) {
		return array(
			'family'      => $settings[ 'font_custom_' . $lang ],
			'weight'      => $weight,
			'bundled_key' => null,
		);
	}

	if ( isset( $bundled[ $source ] ) ) {
		return array(
			'family'      => $bundled[ $source ]['family'],
			'weight'      => $weight,
			'bundled_key' => $source,
		);
	}

	return array( 'family' => '', 'weight' => '', 'bundled_key' => null );
}

function cedar_resolve_label( $lang, $settings ) {
	$source = $settings[ 'label_source_' . $lang ];
	if ( $source === 'custom' ) {
		return $settings[ 'label_custom_' . $lang ] ? $settings[ 'label_custom_' . $lang ] : ( $lang === 'fa' ? 'فارسی' : 'EN' );
	}
	if ( ! empty( $source ) ) {
		return $source;
	}
	return $lang === 'fa' ? 'فارسی' : 'EN';
}

/* =========================================================
   Shortcode: [lang_switch] or [lang_switch id="2"]
   ========================================================= */
function cedar_lang_switch_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 1 ), $atts );
	$id   = absint( $atts['id'] );
	if ( ! $id ) {
		$id = 1;
	}

	$url = get_post_meta( get_the_ID(), 'lang_alt_url', true );
	if ( ! $url ) {
		return '';
	}
	$current_lang = get_post_meta( get_the_ID(), 'page_lang', true );

	$s       = cedar_get_button_settings( $id );
	$bundled = cedar_get_bundled_fonts();

	$target_lang = ( $current_lang === 'fa' ) ? 'en' : 'fa';
	$font        = cedar_resolve_font( $target_lang, $s, $bundled );

	$styles   = array();
	$styles[] = 'display:inline-flex';
	$styles[] = 'align-items:center';
	$styles[] = 'gap:6px';
	$styles[] = 'text-decoration:none';
	$styles[] = 'white-space:nowrap';

	if ( $s['bg_color'] )   { $styles[] = 'background-color:' . $s['bg_color']; }
	if ( $s['text_color'] ) { $styles[] = 'color:' . $s['text_color']; }

	$border_radius = cedar_combine_dimension( $s['border_radius_num'], $s['border_radius_unit'] );
	$padding       = cedar_combine_dimension( $s['padding_num'], $s['padding_unit'] );
	$margin        = cedar_combine_dimension( $s['margin_num'], $s['margin_unit'] );

	if ( $border_radius ) { $styles[] = 'border-radius:' . $border_radius; }
	if ( $padding )       { $styles[] = 'padding:' . $padding; }
	if ( $margin )        { $styles[] = 'margin:' . $margin; }

	if ( $font['family'] ) { $styles[] = "font-family:'" . $font['family'] . "'"; }
	if ( $font['weight'] ) { $styles[] = 'font-weight:' . $font['weight']; }

	$font_size = cedar_combine_dimension( $s[ 'font_size_' . $target_lang . '_num' ], $s[ 'font_size_' . $target_lang . '_unit' ] );
	if ( $font_size ) { $styles[] = 'font-size:' . $font_size; }

	$style_attr = implode( ';', $styles );

	$label = cedar_resolve_label( $target_lang, $s );

	$icon_html = cedar_get_icon_svg_markup( $s['icon_choice'], cedar_combine_dimension( $s['icon_size_num'], $s['icon_size_unit'] ), $target_lang );

	$hover_css = '';
	$hover_class = 'cedar-lang-switch-btn-' . $id;
	if ( $s['bg_color_hover'] || $s['text_color_hover'] ) {
		$hover_rules = array();
		if ( $s['bg_color_hover'] )   { $hover_rules[] = 'background-color:' . $s['bg_color_hover'] . ' !important'; }
		if ( $s['text_color_hover'] ) { $hover_rules[] = 'color:' . $s['text_color_hover'] . ' !important'; }
		$hover_css = '<style>.' . $hover_class . ':hover{' . implode( ';', $hover_rules ) . '}</style>';
	}

	$position   = $s[ 'icon_position_' . $target_lang ] === 'right' ? 'right' : 'left';
	$text_html  = '<span>' . esc_html( $label ) . '</span>';
	$inner_html = ( $position === 'right' ) ? ( $text_html . $icon_html ) : ( $icon_html . $text_html );

	return $hover_css . '<a href="' . esc_url( $url ) . '" class="cedar-lang-switch-btn ' . esc_attr( $hover_class ) . '" style="' . esc_attr( $style_attr ) . '">' . $inner_html . '</a>';
}
add_shortcode( 'lang_switch', 'cedar_lang_switch_shortcode' );

function cedar_get_icon_svg_markup( $icon_key, $size, $target_lang = null ) {
	if ( $icon_key === 'none' || empty( $icon_key ) ) {
		return '';
	}

	$size = $size ? $size : '18px';

	if ( $icon_key === 'flags' ) {
		$file = ( $target_lang === 'fa' ) ? 'ir.svg' : 'us.svg';
		$path = CEDAR_PLUGIN_DIR . 'icons/flags/' . $file;
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$svg = file_get_contents( $path );
		$svg = preg_replace( '/width="[^"]*"/', '', $svg, 1 );
		$svg = preg_replace( '/height="[^"]*"/', '', $svg, 1 );
		$svg = str_replace( '<svg', '<svg width="' . esc_attr( $size ) . '" style="flex-shrink:0;display:inline-block;vertical-align:middle;border-radius:2px;"', $svg );
		return $svg;
	}

	$icons = cedar_get_bundled_icons();
	if ( ! isset( $icons[ $icon_key ] ) ) {
		return '';
	}
	$path = CEDAR_PLUGIN_DIR . 'icons/' . $icon_key . '.svg';
	if ( ! file_exists( $path ) ) {
		return '';
	}
	$svg  = file_get_contents( $path );
	$size = $size ? $size : '18px';
	$svg  = preg_replace( '/width="[^"]*"/', 'width="' . esc_attr( $size ) . '"', $svg, 1 );
	$svg  = preg_replace( '/height="[^"]*"/', 'height="' . esc_attr( $size ) . '"', $svg, 1 );
	$svg  = str_replace( '<svg', '<svg style="flex-shrink:0;display:inline-block;vertical-align:middle;"', $svg );
	return $svg;
}

/* =========================================================
   Font face injection — union across every button profile
   ========================================================= */
function cedar_enqueue_bundled_fonts() {
	$buttons = cedar_get_all_buttons();
	$bundled = cedar_get_bundled_fonts();

	$keys_to_load = array();
	foreach ( $buttons as $id => $raw ) {
		$s = wp_parse_args( $raw, cedar_get_button_defaults() );
		foreach ( array( 'en', 'fa' ) as $lang ) {
			$font = cedar_resolve_font( $lang, $s, $bundled );
			if ( $font['bundled_key'] ) {
				$keys_to_load[ $font['bundled_key'] ] = true;
			}
		}
	}

	if ( empty( $keys_to_load ) ) {
		return;
	}

	echo '<style id="cedar-bundled-fonts">' . "\n";
	foreach ( array_keys( $keys_to_load ) as $key ) {
		$def = $bundled[ $key ];
		if ( $def['type'] === 'variable' ) {
			$url = CEDAR_PLUGIN_URL . 'fonts/' . $def['file'];
			echo "@font-face{font-family:'{$def['family']}';src:url('" . esc_url( $url ) . "') format('woff2');font-weight:100 900;font-style:normal;font-display:swap;}\n";
		} else {
			foreach ( $def['files'] as $weight => $file ) {
				$url = CEDAR_PLUGIN_URL . 'fonts/' . $file;
				echo "@font-face{font-family:'{$def['family']}';src:url('" . esc_url( $url ) . "') format('woff2');font-weight:{$weight};font-style:normal;font-display:swap;}\n";
			}
		}
	}
	echo '</style>' . "\n";
}
add_action( 'wp_head', 'cedar_enqueue_bundled_fonts' );

/* =========================================================
   Body class per page language
   ========================================================= */
function cedar_body_lang_class( $classes ) {
	if ( is_singular() ) {
		$lang = get_post_meta( get_the_ID(), 'page_lang', true );
		if ( $lang === 'fa' ) {
			$classes[] = 'lang-fa';
		} elseif ( $lang === 'en' ) {
			$classes[] = 'lang-en';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'cedar_body_lang_class' );

/* =========================================================
   RTL layout fixes
   ========================================================= */
function cedar_rtl_inline_style() {
	if ( is_singular() ) {
		$lang = get_post_meta( get_the_ID(), 'page_lang', true );
		if ( $lang === 'fa' ) {
			?>
			<style id="cedar-rtl-style">
				body.lang-fa { direction: rtl; text-align: right; }
				body.lang-fa .elementor-container,
				body.lang-fa .e-con,
				body.lang-fa .elementor-row { direction: rtl; }
				body.lang-fa .elementor-icon-list-item { flex-direction: row-reverse; }
				body.lang-fa .elementor-button-content-wrapper { flex-direction: row-reverse; }
				body.lang-fa .elementor-button-icon { margin-left: 8px; margin-right: 0; }
				body.lang-fa input[type="text"],
				body.lang-fa input[type="email"],
				body.lang-fa input[type="tel"],
				body.lang-fa textarea,
				body.lang-fa select { direction: rtl; text-align: right; }
				body.lang-fa .elementor-nav-menu { direction: rtl; }
				body.lang-fa .elementor-widget-breadcrumbs { direction: rtl; }
				body.lang-fa blockquote { border-right: 4px solid currentColor; border-left: none; padding-right: 16px; padding-left: 0; }
				body.lang-fa .swiper-wrapper { direction: rtl; }
				body.lang-fa .elementor-accordion-icon,
				body.lang-fa .elementor-toggle-icon { float: left; }
				body.lang-fa .elementor-tabs-wrapper { direction: rtl; }
			</style>
			<?php
		}
	}
}
add_action( 'wp_head', 'cedar_rtl_inline_style' );

/* =========================================================
   <html lang> / dir attributes
   ========================================================= */
function cedar_language_attributes( $output ) {
	if ( is_singular() ) {
		$lang = get_post_meta( get_the_ID(), 'page_lang', true );

		if ( $lang === 'fa' ) {
			$target_dir  = 'rtl';
			$target_lang = 'fa-IR';
		} elseif ( $lang === 'en' ) {
			$target_dir  = 'ltr';
			$target_lang = 'en-US';
		} else {
			return $output;
		}

		if ( preg_match( '/lang="[^"]*"/', $output ) ) {
			$output = preg_replace( '/lang="[^"]*"/', 'lang="' . $target_lang . '"', $output );
		} else {
			$output .= ' lang="' . $target_lang . '"';
		}

		if ( preg_match( '/dir="[^"]*"/', $output ) ) {
			$output = preg_replace( '/dir="[^"]*"/', 'dir="' . $target_dir . '"', $output );
		} else {
			$output .= ' dir="' . $target_dir . '"';
		}
	}
	return $output;
}
add_filter( 'language_attributes', 'cedar_language_attributes' );

/* =========================================================
   hreflang tags
   ========================================================= */
function cedar_hreflang_tags() {
	if ( is_singular() ) {
		$current_url  = get_permalink();
		$current_lang = get_post_meta( get_the_ID(), 'page_lang', true );
		$alt_url      = get_post_meta( get_the_ID(), 'lang_alt_url', true );

		if ( ! $current_lang ) {
			return;
		}

		$alt_lang = ( $current_lang === 'fa' ) ? 'en' : 'fa';

		echo '<link rel="alternate" hreflang="' . esc_attr( $current_lang ) . '" href="' . esc_url( $current_url ) . '" />' . "\n";

		if ( $alt_url ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $alt_lang ) . '" href="' . esc_url( $alt_url ) . '" />' . "\n";
			echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $current_url ) . '" />' . "\n";
		}
	}
}
add_action( 'wp_head', 'cedar_hreflang_tags' );

/* =========================================================
   Admin menu + tabbed page
   ========================================================= */
function cedar_admin_menu() {
	add_menu_page(
		'Cedar Multilang',
		'Cedar Multilang',
		'manage_options',
		'cedar-multilang',
		'cedar_render_admin_page',
		'dashicons-translation',
		80
	);
}
add_action( 'admin_menu', 'cedar_admin_menu' );

function cedar_sanitize_dimension_unit( $unit ) {
	$allowed = array( 'px', '%', 'em', 'rem', 'vh', 'vw' );
	return in_array( $unit, $allowed, true ) ? $unit : 'px';
}

function cedar_get_available_fonts() {
	$fonts = array();
	if ( post_type_exists( 'elementor_font' ) ) {
		$custom_fonts = get_posts( array(
			'post_type'   => 'elementor_font',
			'numberposts' => -1,
			'post_status' => 'publish',
		) );
		foreach ( $custom_fonts as $cf ) {
			$fonts[] = $cf->post_title;
		}
	}
	return array_values( array_unique( $fonts ) );
}

/* ---------- Save: page pairs ---------- */
function cedar_handle_pairs_save() {
	if ( ! isset( $_POST['cedar_pairs_nonce'] ) || ! wp_verify_nonce( $_POST['cedar_pairs_nonce'], 'cedar_save_pairs' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$raw_pairs   = isset( $_POST['cedar_pairs'] ) ? (array) $_POST['cedar_pairs'] : array();
	$clean_pairs = array();

	foreach ( $raw_pairs as $pair ) {
		$en_id = isset( $pair['en'] ) ? absint( $pair['en'] ) : 0;
		$fa_id = isset( $pair['fa'] ) ? absint( $pair['fa'] ) : 0;

		if ( ! $en_id || ! $fa_id ) {
			continue;
		}

		$en_url = get_permalink( $en_id );
		$fa_url = get_permalink( $fa_id );

		if ( ! $en_url || ! $fa_url ) {
			continue;
		}

		update_post_meta( $en_id, 'page_lang', 'en' );
		update_post_meta( $en_id, 'lang_alt_url', $fa_url );

		update_post_meta( $fa_id, 'page_lang', 'fa' );
		update_post_meta( $fa_id, 'lang_alt_url', $en_url );

		$clean_pairs[] = array( 'en' => $en_id, 'fa' => $fa_id );
	}

	update_option( 'cedar_multilang_pairs', $clean_pairs );

	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-success is-dismissible"><p>Page pairs saved and applied successfully.</p></div>';
	} );
}

/* ---------- Save: post pairs (separate from page pairs) ---------- */
function cedar_handle_post_pairs_save() {
	if ( ! isset( $_POST['cedar_post_pairs_nonce'] ) || ! wp_verify_nonce( $_POST['cedar_post_pairs_nonce'], 'cedar_save_post_pairs' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$raw_pairs   = isset( $_POST['cedar_post_pairs'] ) ? (array) $_POST['cedar_post_pairs'] : array();
	$clean_pairs = array();

	foreach ( $raw_pairs as $pair ) {
		$en_id = isset( $pair['en'] ) ? absint( $pair['en'] ) : 0;
		$fa_id = isset( $pair['fa'] ) ? absint( $pair['fa'] ) : 0;

		if ( ! $en_id || ! $fa_id ) {
			continue;
		}

		$en_url = get_permalink( $en_id );
		$fa_url = get_permalink( $fa_id );

		if ( ! $en_url || ! $fa_url ) {
			continue;
		}

		update_post_meta( $en_id, 'page_lang', 'en' );
		update_post_meta( $en_id, 'lang_alt_url', $fa_url );

		update_post_meta( $fa_id, 'page_lang', 'fa' );
		update_post_meta( $fa_id, 'lang_alt_url', $en_url );

		$clean_pairs[] = array( 'en' => $en_id, 'fa' => $fa_id );
	}

	update_option( 'cedar_multilang_post_pairs', $clean_pairs );

	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-success is-dismissible"><p>Post pairs saved and applied successfully.</p></div>';
	} );
}

/* ---------- Save: one button's style settings ---------- */
function cedar_handle_button_save() {
	if ( ! isset( $_POST['cedar_button_nonce'] ) || ! wp_verify_nonce( $_POST['cedar_button_nonce'], 'cedar_save_button' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$id = absint( $_POST['cedar_button_id'] ?? 0 );
	if ( ! $id ) {
		return;
	}

	$bundled     = cedar_get_bundled_fonts();
	$valid_fonts = array_keys( $bundled );
	$valid_icons = array_keys( cedar_get_bundled_icons() );

	$icon_choice = isset( $_POST['icon_choice'] ) ? sanitize_key( $_POST['icon_choice'] ) : 'none';
	if ( $icon_choice !== 'none' && $icon_choice !== 'flags' && ! in_array( $icon_choice, $valid_icons, true ) ) {
		$icon_choice = 'none';
	}

	$font_source_en = isset( $_POST['font_source_en'] ) ? sanitize_key( $_POST['font_source_en'] ) : '';
	if ( $font_source_en !== 'custom' && ! in_array( $font_source_en, $valid_fonts, true ) ) {
		$font_source_en = '';
	}
	$font_source_fa = isset( $_POST['font_source_fa'] ) ? sanitize_key( $_POST['font_source_fa'] ) : '';
	if ( $font_source_fa !== 'custom' && ! in_array( $font_source_fa, $valid_fonts, true ) ) {
		$font_source_fa = '';
	}

	$clean = array(
		'label'            => sanitize_text_field( $_POST['button_label'] ?? '' ),
		'bg_color'         => sanitize_hex_color( $_POST['bg_color'] ?? '' ),
		'bg_color_hover'   => sanitize_hex_color( $_POST['bg_color_hover'] ?? '' ),
		'text_color'       => sanitize_hex_color( $_POST['text_color'] ?? '' ),
		'text_color_hover' => sanitize_hex_color( $_POST['text_color_hover'] ?? '' ),

		'border_radius_num'  => sanitize_text_field( $_POST['border_radius_num'] ?? '' ),
		'border_radius_unit' => cedar_sanitize_dimension_unit( $_POST['border_radius_unit'] ?? 'px' ),
		'padding_num'        => sanitize_text_field( $_POST['padding_num'] ?? '' ),
		'padding_unit'       => cedar_sanitize_dimension_unit( $_POST['padding_unit'] ?? 'px' ),
		'margin_num'         => sanitize_text_field( $_POST['margin_num'] ?? '' ),
		'margin_unit'        => cedar_sanitize_dimension_unit( $_POST['margin_unit'] ?? 'px' ),

		'font_source_en'    => $font_source_en,
		'font_custom_en'    => sanitize_text_field( $_POST['font_custom_en'] ?? '' ),
		'font_weight_en'    => absint( $_POST['font_weight_en'] ?? 0 ),
		'font_size_en_num'  => sanitize_text_field( $_POST['font_size_en_num'] ?? '' ),
		'font_size_en_unit' => cedar_sanitize_dimension_unit( $_POST['font_size_en_unit'] ?? 'px' ),

		'font_source_fa'    => $font_source_fa,
		'font_custom_fa'    => sanitize_text_field( $_POST['font_custom_fa'] ?? '' ),
		'font_weight_fa'    => absint( $_POST['font_weight_fa'] ?? 0 ),
		'font_size_fa_num'  => sanitize_text_field( $_POST['font_size_fa_num'] ?? '' ),
		'font_size_fa_unit' => cedar_sanitize_dimension_unit( $_POST['font_size_fa_unit'] ?? 'px' ),

		'icon_choice'    => $icon_choice,
		'icon_size_num'  => sanitize_text_field( $_POST['icon_size_num'] ?? '' ),
		'icon_size_unit' => cedar_sanitize_dimension_unit( $_POST['icon_size_unit'] ?? 'px' ),

		'icon_position_en' => ( ( $_POST['icon_position_en'] ?? 'left' ) === 'right' ) ? 'right' : 'left',
		'icon_position_fa' => ( ( $_POST['icon_position_fa'] ?? 'left' ) === 'right' ) ? 'right' : 'left',

		'label_source_en' => sanitize_text_field( $_POST['label_source_en'] ?? '' ),
		'label_custom_en' => sanitize_text_field( $_POST['label_custom_en'] ?? '' ),
		'label_source_fa' => sanitize_text_field( $_POST['label_source_fa'] ?? '' ),
		'label_custom_fa' => sanitize_text_field( $_POST['label_custom_fa'] ?? '' ),
	);

	$buttons        = cedar_get_all_buttons();
	$buttons[ $id ] = $clean;
	update_option( 'cedar_buttons', $buttons );

	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-success is-dismissible"><p>Button settings saved successfully.</p></div>';
	} );
}

/* ---------- Add a new button profile ---------- */
function cedar_handle_add_button() {
	if ( ! isset( $_POST['cedar_add_button_nonce'] ) || ! wp_verify_nonce( $_POST['cedar_add_button_nonce'], 'cedar_add_button' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$buttons = cedar_get_all_buttons();
	$next_id = absint( get_option( 'cedar_button_next_id', 2 ) );

	$defaults          = cedar_get_button_defaults();
	$defaults['label'] = 'Button ' . $next_id;
	$buttons[ $next_id ] = $defaults;

	update_option( 'cedar_buttons', $buttons );
	update_option( 'cedar_button_next_id', $next_id + 1 );

	wp_safe_redirect( admin_url( 'admin.php?page=cedar-multilang&tab=button&id=' . $next_id ) );
	exit;
}

/* ---------- Delete a button profile ---------- */
function cedar_handle_delete_button() {
	if ( ! isset( $_POST['cedar_delete_button_nonce'] ) || ! wp_verify_nonce( $_POST['cedar_delete_button_nonce'], 'cedar_delete_button' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$id      = absint( $_POST['cedar_button_id'] ?? 0 );
	$buttons = cedar_get_all_buttons();

	if ( $id && isset( $buttons[ $id ] ) && count( $buttons ) > 1 ) {
		unset( $buttons[ $id ] );
		update_option( 'cedar_buttons', $buttons );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=cedar-multilang&tab=pairs' ) );
	exit;
}

add_action( 'admin_init', function() {
	if ( isset( $_POST['cedar_pairs_nonce'] ) ) {
		cedar_handle_pairs_save();
	}
	if ( isset( $_POST['cedar_post_pairs_nonce'] ) ) {
		cedar_handle_post_pairs_save();
	}
	if ( isset( $_POST['cedar_button_nonce'] ) ) {
		cedar_handle_button_save();
	}
	if ( isset( $_POST['cedar_add_button_nonce'] ) ) {
		cedar_handle_add_button();
	}
	if ( isset( $_POST['cedar_delete_button_nonce'] ) ) {
		cedar_handle_delete_button();
	}
} );

/* ---------- Render: top tab navigation ---------- */
function cedar_render_tab_nav( $current_tab, $current_id ) {
	$buttons = cedar_get_all_buttons();
	?>
	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cedar-multilang&tab=pairs' ) ); ?>" class="nav-tab <?php echo $current_tab === 'pairs' ? 'nav-tab-active' : ''; ?>">Page Pairing</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cedar-multilang&tab=posts' ) ); ?>" class="nav-tab <?php echo $current_tab === 'posts' ? 'nav-tab-active' : ''; ?>">Post Pairing</a>
		<?php foreach ( $buttons as $id => $b ) :
			$label = ! empty( $b['label'] ) ? $b['label'] : ( 'Button ' . $id );
			$active = ( $current_tab === 'button' && (int) $current_id === (int) $id );
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cedar-multilang&tab=button&id=' . $id ) ); ?>" class="nav-tab <?php echo $active ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cedar-multilang&tab=about' ) ); ?>" class="nav-tab <?php echo $current_tab === 'about' ? 'nav-tab-active' : ''; ?>" style="margin-left:auto;">About Plugin</a>
	</h2>
	<form method="post" style="margin: 10px 0 20px;">
		<?php wp_nonce_field( 'cedar_add_button', 'cedar_add_button_nonce' ); ?>
		<button type="submit" class="button">+ Add New Button</button>
	</form>
	<?php
}

/* ---------- Render: main router ---------- */
function cedar_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pairs';
	$id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	echo '<div class="wrap"><h1>Cedar Multilang Website — Settings</h1>';
	cedar_render_tab_nav( $tab, $id );

	if ( $tab === 'button' && $id ) {
		cedar_render_button_tab( $id );
	} elseif ( $tab === 'posts' ) {
		cedar_render_posts_tab();
	} elseif ( $tab === 'about' ) {
		cedar_render_about_tab();
	} else {
		cedar_render_pairs_tab();
	}

	echo '</div>';
}

/* ---------- Render: Page Pairing tab ---------- */
function cedar_render_pairs_tab() {
	$all_pages   = get_pages( array( 'sort_column' => 'post_title', 'post_status' => 'publish' ) );
	$saved_pairs = get_option( 'cedar_multilang_pairs', array() );
	?>
	<p>For each pair, select the English page and its Farsi equivalent. Saving updates both pages automatically, in both directions.</p>

	<form method="post">
		<?php wp_nonce_field( 'cedar_save_pairs', 'cedar_pairs_nonce' ); ?>
		<table class="widefat" id="cedar-pairs-table">
			<thead>
				<tr>
					<th>English Page</th>
					<th>Farsi Page</th>
					<th></th>
				</tr>
			</thead>
			<tbody id="cedar-pairs-body">
				<?php
				$row_index = 0;
				if ( ! empty( $saved_pairs ) ) {
					foreach ( $saved_pairs as $pair ) {
						cedar_render_pair_row( $all_pages, $row_index, $pair['en'], $pair['fa'] );
						$row_index++;
					}
				}
				cedar_render_pair_row( $all_pages, $row_index, 0, 0 );
				?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="cedar-add-row">+ Add New Pair</button></p>
		<p><button type="submit" class="button button-primary">Save Pairs</button></p>
	</form>

	<script>
	(function() {
		var tbody = document.getElementById('cedar-pairs-body');
		var addBtn = document.getElementById('cedar-add-row');
		var rowIndex = <?php echo (int) ( $row_index + 1 ); ?>;

		addBtn.addEventListener('click', function() {
			var firstRow = tbody.querySelector('tr');
			var newRow = firstRow.cloneNode(true);
			var selects = newRow.querySelectorAll('select');
			selects[0].name = 'cedar_pairs[' + rowIndex + '][en]';
			selects[0].value = '0';
			selects[1].name = 'cedar_pairs[' + rowIndex + '][fa]';
			selects[1].value = '0';
			tbody.appendChild(newRow);
			rowIndex++;
		});

		tbody.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('cedar-remove-row')) {
				e.preventDefault();
				if (tbody.querySelectorAll('tr').length > 1) {
					e.target.closest('tr').remove();
				}
			}
		});
	})();
	</script>
	<?php
}

function cedar_render_pair_row( $all_pages, $index, $en_selected, $fa_selected, $field_name = 'cedar_pairs' ) {
	?>
	<tr>
		<td>
			<select name="<?php echo esc_attr( $field_name ); ?>[<?php echo (int) $index; ?>][en]">
				<option value="0">— select —</option>
				<?php foreach ( $all_pages as $page ) : ?>
					<option value="<?php echo (int) $page->ID; ?>" <?php selected( (int) $en_selected, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select name="<?php echo esc_attr( $field_name ); ?>[<?php echo (int) $index; ?>][fa]">
				<option value="0">— select —</option>
				<?php foreach ( $all_pages as $page ) : ?>
					<option value="<?php echo (int) $page->ID; ?>" <?php selected( (int) $fa_selected, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><a href="#" class="cedar-remove-row">Remove</a></td>
	</tr>
	<?php
}

/* ---------- Render: Post Pairing tab (separate from Page Pairing) ---------- */
function cedar_render_posts_tab() {
	$all_posts   = get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
	$saved_pairs = get_option( 'cedar_multilang_post_pairs', array() );
	?>
	<p>Same as Page Pairing, but for blog posts. For each pair, select the English post and its Farsi equivalent.</p>

	<form method="post">
		<?php wp_nonce_field( 'cedar_save_post_pairs', 'cedar_post_pairs_nonce' ); ?>
		<table class="widefat" id="cedar-post-pairs-table">
			<thead>
				<tr>
					<th>English Post</th>
					<th>Farsi Post</th>
					<th></th>
				</tr>
			</thead>
			<tbody id="cedar-post-pairs-body">
				<?php
				$row_index = 0;
				if ( ! empty( $saved_pairs ) ) {
					foreach ( $saved_pairs as $pair ) {
						cedar_render_pair_row( $all_posts, $row_index, $pair['en'], $pair['fa'], 'cedar_post_pairs' );
						$row_index++;
					}
				}
				cedar_render_pair_row( $all_posts, $row_index, 0, 0, 'cedar_post_pairs' );
				?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="cedar-add-post-row">+ Add New Pair</button></p>
		<p><button type="submit" class="button button-primary">Save Post Pairs</button></p>
	</form>

	<script>
	(function() {
		var tbody = document.getElementById('cedar-post-pairs-body');
		var addBtn = document.getElementById('cedar-add-post-row');
		var rowIndex = <?php echo (int) ( $row_index + 1 ); ?>;

		addBtn.addEventListener('click', function() {
			var firstRow = tbody.querySelector('tr');
			var newRow = firstRow.cloneNode(true);
			var selects = newRow.querySelectorAll('select');
			selects[0].name = 'cedar_post_pairs[' + rowIndex + '][en]';
			selects[0].value = '0';
			selects[1].name = 'cedar_post_pairs[' + rowIndex + '][fa]';
			selects[1].value = '0';
			tbody.appendChild(newRow);
			rowIndex++;
		});

		tbody.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('cedar-remove-row')) {
				e.preventDefault();
				if (tbody.querySelectorAll('tr').length > 1) {
					e.target.closest('tr').remove();
				}
			}
		});
	})();
	</script>
	<?php
}

/* ---------- Render: About Plugin tab ---------- */
function cedar_render_about_tab() {
	$year = date( 'Y' );
	?>
	<div style="max-width:700px; background:#fff; border:1px solid #ccd0d4; padding:25px 30px;">
		<h2 style="margin-top:0;">Cedar Multilang Website</h2>
		<p><strong>Author:</strong> Sepehr Nasihatgar</p>

		<h3>Description</h3>
		<p>Cedar Multilang Website is a lightweight, self-contained WordPress plugin for building manually-paired bilingual (Persian/English) websites without relying on WPML or Elementor Pro. It provides:</p>
		<ul style="list-style:disc; margin-left:20px;">
			<li>Automatic hreflang tag generation for paired pages and posts</li>
			<li>Correct RTL/LTR and lang attribute handling per page</li>
			<li>Manual page and post pairing from a single admin panel</li>
			<li>One or more independently styleable language-switch buttons (colors, self-hosted variable fonts, icons or flags, spacing), usable anywhere on the site via shortcode</li>
			<li>Self-hosted fonts and icons — no external requests, no added page-load dependencies</li>
		</ul>

		<h3>Copyright</h3>
		<p>Copyright © <?php echo esc_html( $year ); ?> | Sepehr Nasihatgar. All rights reserved.</p>
		<p>This plugin is proprietary software. It may not be copied, redistributed, or used on any other website or project without prior written permission from the author.</p>
		<p>To request permission for use on a new project, contact: <a href="mailto:sepehrnasihatgar@gmail.com">sepehrnasihatgar@gmail.com</a></p>
	</div>
	<?php
}

/* ---------- Render: single Button tab ---------- */
function cedar_render_button_tab( $id ) {
	$s       = cedar_get_button_settings( $id );
	$bundled = cedar_get_bundled_fonts();
	$icons   = cedar_get_bundled_icons();
	$custom_fonts_available = cedar_get_available_fonts();
	$unit_options = array( 'px', '%', 'em', 'rem', 'vh', 'vw' );
	$shortcode    = '[lang_switch id="' . $id . '"]';
	$buttons_count = count( cedar_get_all_buttons() );
	?>
	<div style="background:#fff; border:1px solid #ccd0d4; padding:15px 20px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
		<strong>Shortcode:</strong>
		<input type="text" readonly id="cedar-shortcode-<?php echo (int) $id; ?>" value="<?php echo esc_attr( $shortcode ); ?>" style="width:220px;" onclick="this.select();" />
		<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('cedar-shortcode-<?php echo (int) $id; ?>').value); this.textContent='Copied!'; setTimeout(()=>{this.textContent='Copy';}, 1500);">Copy</button>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'cedar_save_button', 'cedar_button_nonce' ); ?>
		<input type="hidden" name="cedar_button_id" value="<?php echo (int) $id; ?>" />

		<table class="form-table">
			<tr>
				<th><label for="button_label">Button Name <span class="description">(shown as the tab label above)</span></label></th>
				<td><input type="text" id="button_label" name="button_label" class="regular-text" value="<?php echo esc_attr( $s['label'] ); ?>" placeholder="e.g. Header, Footer" /></td>
			</tr>
		</table>

		<h3>Colors</h3>
		<table class="form-table">
			<tr>
				<th><label for="bg_color">Background Color (Normal)</label></th>
				<td><input type="text" id="bg_color" name="bg_color" class="cedar-color-field" value="<?php echo esc_attr( $s['bg_color'] ); ?>" placeholder="#ffffff" /></td>
			</tr>
			<tr>
				<th><label for="bg_color_hover">Background Color (Hover)</label></th>
				<td><input type="text" id="bg_color_hover" name="bg_color_hover" class="cedar-color-field" value="<?php echo esc_attr( $s['bg_color_hover'] ); ?>" placeholder="#eeeeee" /></td>
			</tr>
			<tr>
				<th><label for="text_color">Text Color (Normal)</label></th>
				<td><input type="text" id="text_color" name="text_color" class="cedar-color-field" value="<?php echo esc_attr( $s['text_color'] ); ?>" placeholder="#000000" /></td>
			</tr>
			<tr>
				<th><label for="text_color_hover">Text Color (Hover)</label></th>
				<td>
					<input type="text" id="text_color_hover" name="text_color_hover" class="cedar-color-field" value="<?php echo esc_attr( $s['text_color_hover'] ); ?>" placeholder="#444444" />
					<p class="description">Since the icon is an SVG using <code>currentColor</code>, it recolors automatically with this setting.</p>
				</td>
			</tr>
		</table>

		<h3 style="margin-top:30px;">Typography (separate per language)</h3>
		<div style="display:flex; gap:40px; flex-wrap:wrap;">

			<div style="flex:1; min-width:320px;">
				<h4>English Font <span class="description">(shown when the button is on a Farsi page and displays "EN")</span></h4>
				<table class="form-table">
					<tr>
						<th><label for="font_source_en">Preset Font</label></th>
						<td>
							<select id="font_source_en" name="font_source_en" class="cedar-font-source" data-lang="en">
								<option value="">— none —</option>
								<?php foreach ( $bundled as $key => $def ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['font_source_en'], $key ); ?>><?php echo esc_html( $def['label'] ); ?></option>
								<?php endforeach; ?>
								<option value="custom" <?php selected( $s['font_source_en'], 'custom' ); ?>>Custom (type below)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="font_custom_en">Custom Font Name</label></th>
						<td>
							<input type="text" id="font_custom_en" name="font_custom_en" list="cedar-custom-font-list" class="regular-text" value="<?php echo esc_attr( $s['font_custom_en'] ); ?>" placeholder="e.g. a font already loaded elsewhere on the site" />
							<p class="description">Only used when "Custom" is selected above.</p>
						</td>
					</tr>
					<tr>
						<th><label for="font_weight_en">Font Weight</label></th>
						<td>
							<select id="font_weight_en" name="font_weight_en" class="cedar-font-weight" data-lang="en">
								<?php foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $w ) : ?>
									<option value="<?php echo esc_attr( $w ); ?>" <?php selected( (int) $s['font_weight_en'], $w ); ?>><?php echo esc_html( $w ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="font_size_en_num">Font Size</label></th>
						<td>
							<input type="text" id="font_size_en_num" name="font_size_en_num" style="width:80px;" value="<?php echo esc_attr( $s['font_size_en_num'] ); ?>" placeholder="16" />
							<select name="font_size_en_unit">
								<?php foreach ( $unit_options as $u ) : ?>
									<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['font_size_en_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="label_source_en">Button Text</label></th>
						<td>
							<select id="label_source_en" name="label_source_en">
								<?php foreach ( array( '' => 'Default (EN)', 'EN' => 'EN', 'English' => 'English', 'US' => 'US', 'انگلیسی' => 'انگلیسی', 'ENG' => 'ENG', 'custom' => 'Custom (type below)' ) as $val => $lbl ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['label_source_en'], $val ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="label_custom_en">Custom Text</label></th>
						<td><input type="text" id="label_custom_en" name="label_custom_en" value="<?php echo esc_attr( $s['label_custom_en'] ); ?>" placeholder="Only used when \"Custom\" is selected above" /></td>
					</tr>
					<tr>
						<th>Icon Position</th>
						<td>
							<label style="margin-left:15px;"><input type="radio" name="icon_position_en" value="left" <?php checked( $s['icon_position_en'], 'left' ); ?> /> Left of text</label>
							<label style="margin-left:15px;"><input type="radio" name="icon_position_en" value="right" <?php checked( $s['icon_position_en'], 'right' ); ?> /> Right of text</label>
						</td>
					</tr>
				</table>
			</div>

			<div style="flex:1; min-width:320px;">
				<h4>Farsi Font <span class="description">(shown when the button is on an English page and displays "فارسی" / Farsi)</span></h4>
				<table class="form-table">
					<tr>
						<th><label for="font_source_fa">Preset Font</label></th>
						<td>
							<select id="font_source_fa" name="font_source_fa" class="cedar-font-source" data-lang="fa">
								<option value="">— none —</option>
								<?php foreach ( $bundled as $key => $def ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['font_source_fa'], $key ); ?>><?php echo esc_html( $def['label'] ); ?></option>
								<?php endforeach; ?>
								<option value="custom" <?php selected( $s['font_source_fa'], 'custom' ); ?>>Custom (type below)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="font_custom_fa">Custom Font Name</label></th>
						<td>
							<input type="text" id="font_custom_fa" name="font_custom_fa" list="cedar-custom-font-list" class="regular-text" value="<?php echo esc_attr( $s['font_custom_fa'] ); ?>" placeholder="e.g. a Farsi font already loaded elsewhere on the site" />
							<p class="description">Only used when "Custom" is selected above.</p>
						</td>
					</tr>
					<tr>
						<th><label for="font_weight_fa">Font Weight</label></th>
						<td>
							<select id="font_weight_fa" name="font_weight_fa" class="cedar-font-weight" data-lang="fa">
								<?php foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $w ) : ?>
									<option value="<?php echo esc_attr( $w ); ?>" <?php selected( (int) $s['font_weight_fa'], $w ); ?>><?php echo esc_html( $w ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="font_size_fa_num">Font Size</label></th>
						<td>
							<input type="text" id="font_size_fa_num" name="font_size_fa_num" style="width:80px;" value="<?php echo esc_attr( $s['font_size_fa_num'] ); ?>" placeholder="16" />
							<select name="font_size_fa_unit">
								<?php foreach ( $unit_options as $u ) : ?>
									<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['font_size_fa_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="label_source_fa">Button Text</label></th>
						<td>
							<select id="label_source_fa" name="label_source_fa">
								<?php foreach ( array( '' => 'Default (فارسی)', 'فارسی' => 'فارسی', 'FA' => 'FA', 'Farsi' => 'Farsi', 'Persian' => 'Persian', 'فا' => 'فا', 'custom' => 'Custom (type below)' ) as $val => $lbl ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['label_source_fa'], $val ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="label_custom_fa">Custom Text</label></th>
						<td><input type="text" id="label_custom_fa" name="label_custom_fa" value="<?php echo esc_attr( $s['label_custom_fa'] ); ?>" placeholder="Only used when \"Custom\" is selected above" /></td>
					</tr>
					<tr>
						<th>Icon Position</th>
						<td>
							<label style="margin-left:15px;"><input type="radio" name="icon_position_fa" value="left" <?php checked( $s['icon_position_fa'], 'left' ); ?> /> Left of text</label>
							<label style="margin-left:15px;"><input type="radio" name="icon_position_fa" value="right" <?php checked( $s['icon_position_fa'], 'right' ); ?> /> Right of text</label>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<datalist id="cedar-custom-font-list">
			<?php foreach ( $custom_fonts_available as $f ) : ?>
				<option value="<?php echo esc_attr( $f ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<h3 style="margin-top:30px;">Spacing &amp; Shape</h3>
		<table class="form-table">
			<tr>
				<th><label for="border_radius_num">Border Radius</label></th>
				<td>
					<input type="text" id="border_radius_num" name="border_radius_num" style="width:80px;" value="<?php echo esc_attr( $s['border_radius_num'] ); ?>" placeholder="6" />
					<select name="border_radius_unit">
						<?php foreach ( $unit_options as $u ) : ?>
							<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['border_radius_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="padding_num">Padding <span class="description">(all sides)</span></label></th>
				<td>
					<input type="text" id="padding_num" name="padding_num" style="width:80px;" value="<?php echo esc_attr( $s['padding_num'] ); ?>" placeholder="8" />
					<select name="padding_unit">
						<?php foreach ( $unit_options as $u ) : ?>
							<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['padding_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="margin_num">Margin <span class="description">(all sides)</span></label></th>
				<td>
					<input type="text" id="margin_num" name="margin_num" style="width:80px;" value="<?php echo esc_attr( $s['margin_num'] ); ?>" placeholder="0" />
					<select name="margin_unit">
						<?php foreach ( $unit_options as $u ) : ?>
							<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['margin_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<h3 style="margin-top:30px;">Icon</h3>
		<table class="form-table">
			<tr>
				<th>Choose Icon</th>
				<td>
					<label style="display:inline-block; margin-left:20px;">
						<input type="radio" name="icon_choice" value="none" <?php checked( $s['icon_choice'], 'none' ); ?> />
						None (text only)
					</label>
					<?php foreach ( $icons as $key => $title ) : ?>
						<label style="display:inline-block; margin-left:20px;">
							<input type="radio" name="icon_choice" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['icon_choice'], $key ); ?> />
							<?php echo cedar_get_icon_svg_markup( $key, '20px' ); ?>
							<?php echo esc_html( $title ); ?>
						</label>
					<?php endforeach; ?>
					<label style="display:inline-block; margin-left:20px;">
						<input type="radio" name="icon_choice" value="flags" <?php checked( $s['icon_choice'], 'flags' ); ?> />
						<?php echo cedar_get_icon_svg_markup( 'flags', '20px', 'en' ); ?>
						<?php echo cedar_get_icon_svg_markup( 'flags', '20px', 'fa' ); ?>
						Flags (US / Iran — automatically switches based on which language the button links to)
					</label>
					<p class="description">Flags are full-color images and will not recolor with the hover settings above (unlike the line icons).</p>
				</td>
			</tr>
			<tr>
				<th><label for="icon_size_num">Icon Size</label></th>
				<td>
					<input type="text" id="icon_size_num" name="icon_size_num" style="width:80px;" value="<?php echo esc_attr( $s['icon_size_num'] ); ?>" placeholder="18" />
					<select name="icon_size_unit">
						<?php foreach ( $unit_options as $u ) : ?>
							<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $s['icon_size_unit'], $u ); ?>><?php echo esc_html( $u ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p><button type="submit" class="button button-primary">Save Button Settings</button></p>
	</form>

	<?php if ( $buttons_count > 1 ) : ?>
	<form method="post" onsubmit="return confirm('Delete this button profile? This cannot be undone.');">
		<?php wp_nonce_field( 'cedar_delete_button', 'cedar_delete_button_nonce' ); ?>
		<input type="hidden" name="cedar_button_id" value="<?php echo (int) $id; ?>" />
		<button type="submit" class="button" style="color:#a00;">Delete This Button</button>
	</form>
	<?php endif; ?>

	<script>
	(function() {
		var bundledWeights = <?php echo wp_json_encode( array_map( function( $def ) { return $def['weights']; }, $bundled ) ); ?>;
		var allWeights = [100, 200, 300, 400, 500, 600, 700, 800, 900];

		document.querySelectorAll('.cedar-font-source').forEach(function(sourceSelect) {
			sourceSelect.addEventListener('change', function() {
				var lang = sourceSelect.getAttribute('data-lang');
				var weightSelect = document.querySelector('.cedar-font-weight[data-lang="' + lang + '"]');
				var currentValue = weightSelect.value;
				var weights = bundledWeights[sourceSelect.value] || allWeights;

				weightSelect.innerHTML = '';
				weights.forEach(function(w) {
					var opt = document.createElement('option');
					opt.value = w;
					opt.textContent = w;
					if (String(w) === currentValue) { opt.selected = true; }
					weightSelect.appendChild(opt);
				});
			});
		});
	})();
	</script>
	<?php
}

function cedar_admin_assets( $hook ) {
	if ( strpos( $hook, 'cedar-multilang' ) === false ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', 'jQuery(document).ready(function($){ $(".cedar-color-field").wpColorPicker(); });' );
}
add_action( 'admin_enqueue_scripts', 'cedar_admin_assets' );

<?php
/**
 * Built-in list of languages offered in "Add a language".
 *
 * Bundled on purpose: no request to api.wordpress.org is ever made.
 *
 * @package Cedar\Multilang
 */

namespace Cedar\Multilang\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress locales with their English name, native name and direction.
 */
final class Catalog {

	/**
	 * Locale => array( English name, native name, direction ).
	 *
	 * Names are proper names of languages, shown as data; they are not
	 * translated (the native name is how speakers write it).
	 *
	 * @var array<string,array{0:string,1:string,2:string}>
	 */
	const LOCALES = array(
		'af'             => array( 'Afrikaans', 'Afrikaans', 'ltr' ),
		'am'             => array( 'Amharic', 'አማርኛ', 'ltr' ),
		'ar'             => array( 'Arabic', 'العربية', 'rtl' ),
		'ary'            => array( 'Moroccan Arabic', 'العربية المغربية', 'rtl' ),
		'as'             => array( 'Assamese', 'অসমীয়া', 'ltr' ),
		'az'             => array( 'Azerbaijani', 'Azərbaycan dili', 'ltr' ),
		'azb'            => array( 'South Azerbaijani', 'گؤنئی آذربایجان', 'rtl' ),
		'bel'            => array( 'Belarusian', 'Беларуская мова', 'ltr' ),
		'bg_BG'          => array( 'Bulgarian', 'Български', 'ltr' ),
		'bn_BD'          => array( 'Bengali (Bangladesh)', 'বাংলা', 'ltr' ),
		'bo'             => array( 'Tibetan', 'བོད་ཡིག', 'ltr' ),
		'bs_BA'          => array( 'Bosnian', 'Bosanski', 'ltr' ),
		'ca'             => array( 'Catalan', 'Català', 'ltr' ),
		'ceb'            => array( 'Cebuano', 'Cebuano', 'ltr' ),
		'ckb'            => array( 'Kurdish (Sorani)', 'كوردی', 'rtl' ),
		'cs_CZ'          => array( 'Czech', 'Čeština', 'ltr' ),
		'cy'             => array( 'Welsh', 'Cymraeg', 'ltr' ),
		'da_DK'          => array( 'Danish', 'Dansk', 'ltr' ),
		'de_AT'          => array( 'German (Austria)', 'Deutsch (Österreich)', 'ltr' ),
		'de_CH'          => array( 'German (Switzerland)', 'Deutsch (Schweiz)', 'ltr' ),
		'de_DE'          => array( 'German', 'Deutsch', 'ltr' ),
		'de_DE_formal'   => array( 'German (Formal)', 'Deutsch (Sie)', 'ltr' ),
		'dsb'            => array( 'Lower Sorbian', 'Dolnoserbšćina', 'ltr' ),
		'dzo'            => array( 'Dzongkha', 'རྫོང་ཁ', 'ltr' ),
		'el'             => array( 'Greek', 'Ελληνικά', 'ltr' ),
		'en_AU'          => array( 'English (Australia)', 'English (Australia)', 'ltr' ),
		'en_CA'          => array( 'English (Canada)', 'English (Canada)', 'ltr' ),
		'en_GB'          => array( 'English (UK)', 'English (UK)', 'ltr' ),
		'en_NZ'          => array( 'English (New Zealand)', 'English (New Zealand)', 'ltr' ),
		'en_US'          => array( 'English', 'English', 'ltr' ),
		'en_ZA'          => array( 'English (South Africa)', 'English (South Africa)', 'ltr' ),
		'eo'             => array( 'Esperanto', 'Esperanto', 'ltr' ),
		'es_AR'          => array( 'Spanish (Argentina)', 'Español de Argentina', 'ltr' ),
		'es_CL'          => array( 'Spanish (Chile)', 'Español de Chile', 'ltr' ),
		'es_CO'          => array( 'Spanish (Colombia)', 'Español de Colombia', 'ltr' ),
		'es_CR'          => array( 'Spanish (Costa Rica)', 'Español de Costa Rica', 'ltr' ),
		'es_DO'          => array( 'Spanish (Dominican Republic)', 'Español de República Dominicana', 'ltr' ),
		'es_EC'          => array( 'Spanish (Ecuador)', 'Español de Ecuador', 'ltr' ),
		'es_ES'          => array( 'Spanish (Spain)', 'Español', 'ltr' ),
		'es_GT'          => array( 'Spanish (Guatemala)', 'Español de Guatemala', 'ltr' ),
		'es_MX'          => array( 'Spanish (Mexico)', 'Español de México', 'ltr' ),
		'es_PE'          => array( 'Spanish (Peru)', 'Español de Perú', 'ltr' ),
		'es_PR'          => array( 'Spanish (Puerto Rico)', 'Español de Puerto Rico', 'ltr' ),
		'es_UY'          => array( 'Spanish (Uruguay)', 'Español de Uruguay', 'ltr' ),
		'es_VE'          => array( 'Spanish (Venezuela)', 'Español de Venezuela', 'ltr' ),
		'et'             => array( 'Estonian', 'Eesti', 'ltr' ),
		'eu'             => array( 'Basque', 'Euskara', 'ltr' ),
		'fa_AF'          => array( 'Persian (Afghanistan)', 'فارسی افغانستان', 'rtl' ),
		'fa_IR'          => array( 'Farsi', 'فارسی', 'rtl' ),
		'fi'             => array( 'Finnish', 'Suomi', 'ltr' ),
		'fr_BE'          => array( 'French (Belgium)', 'Français de Belgique', 'ltr' ),
		'fr_CA'          => array( 'French (Canada)', 'Français du Canada', 'ltr' ),
		'fr_FR'          => array( 'French', 'Français', 'ltr' ),
		'fur'            => array( 'Friulian', 'Friulian', 'ltr' ),
		'fy'             => array( 'Frisian', 'Frysk', 'ltr' ),
		'gd'             => array( 'Scottish Gaelic', 'Gàidhlig', 'ltr' ),
		'gl_ES'          => array( 'Galician', 'Galego', 'ltr' ),
		'gu'             => array( 'Gujarati', 'ગુજરાતી', 'ltr' ),
		'haz'            => array( 'Hazaragi', 'هزاره گی', 'rtl' ),
		'he_IL'          => array( 'Hebrew', 'עִבְרִית', 'rtl' ),
		'hi_IN'          => array( 'Hindi', 'हिन्दी', 'ltr' ),
		'hr'             => array( 'Croatian', 'Hrvatski', 'ltr' ),
		'hsb'            => array( 'Upper Sorbian', 'Hornjoserbšćina', 'ltr' ),
		'hu_HU'          => array( 'Hungarian', 'Magyar', 'ltr' ),
		'hy'             => array( 'Armenian', 'Հայերեն', 'ltr' ),
		'id_ID'          => array( 'Indonesian', 'Bahasa Indonesia', 'ltr' ),
		'is_IS'          => array( 'Icelandic', 'Íslenska', 'ltr' ),
		'it_IT'          => array( 'Italian', 'Italiano', 'ltr' ),
		'ja'             => array( 'Japanese', '日本語', 'ltr' ),
		'jv_ID'          => array( 'Javanese', 'Basa Jawa', 'ltr' ),
		'ka_GE'          => array( 'Georgian', 'ქართული', 'ltr' ),
		'kab'            => array( 'Kabyle', 'Taqbaylit', 'ltr' ),
		'kk'             => array( 'Kazakh', 'Қазақ тілі', 'ltr' ),
		'km'             => array( 'Khmer', 'ភាសាខ្មែរ', 'ltr' ),
		'kn'             => array( 'Kannada', 'ಕನ್ನಡ', 'ltr' ),
		'ko_KR'          => array( 'Korean', '한국어', 'ltr' ),
		'kir'            => array( 'Kyrgyz', 'Кыргызча', 'ltr' ),
		'lo'             => array( 'Lao', 'ພາສາລາວ', 'ltr' ),
		'lt_LT'          => array( 'Lithuanian', 'Lietuvių kalba', 'ltr' ),
		'lv'             => array( 'Latvian', 'Latviešu valoda', 'ltr' ),
		'mk_MK'          => array( 'Macedonian', 'Македонски јазик', 'ltr' ),
		'ml_IN'          => array( 'Malayalam', 'മലയാളം', 'ltr' ),
		'mn'             => array( 'Mongolian', 'Монгол', 'ltr' ),
		'mr'             => array( 'Marathi', 'मराठी', 'ltr' ),
		'ms_MY'          => array( 'Malay', 'Bahasa Melayu', 'ltr' ),
		'my_MM'          => array( 'Myanmar (Burmese)', 'ဗမာစာ', 'ltr' ),
		'nb_NO'          => array( 'Norwegian (Bokmål)', 'Norsk bokmål', 'ltr' ),
		'ne_NP'          => array( 'Nepali', 'नेपाली', 'ltr' ),
		'nl_BE'          => array( 'Dutch (Belgium)', 'Nederlands (België)', 'ltr' ),
		'nl_NL'          => array( 'Dutch', 'Nederlands', 'ltr' ),
		'nl_NL_formal'   => array( 'Dutch (Formal)', 'Nederlands (Formeel)', 'ltr' ),
		'nn_NO'          => array( 'Norwegian (Nynorsk)', 'Norsk nynorsk', 'ltr' ),
		'oci'            => array( 'Occitan', 'Occitan', 'ltr' ),
		'pa_IN'          => array( 'Punjabi', 'ਪੰਜਾਬੀ', 'ltr' ),
		'pl_PL'          => array( 'Polish', 'Polski', 'ltr' ),
		'ps'             => array( 'Pashto', 'پښتو', 'rtl' ),
		'pt_AO'          => array( 'Portuguese (Angola)', 'Português de Angola', 'ltr' ),
		'pt_BR'          => array( 'Portuguese (Brazil)', 'Português do Brasil', 'ltr' ),
		'pt_PT'          => array( 'Portuguese (Portugal)', 'Português', 'ltr' ),
		'pt_PT_ao90'     => array( 'Portuguese (Portugal, AO90)', 'Português (AO90)', 'ltr' ),
		'rhg'            => array( 'Rohingya', 'Ruáinga', 'ltr' ),
		'ro_RO'          => array( 'Romanian', 'Română', 'ltr' ),
		'ru_RU'          => array( 'Russian', 'Русский', 'ltr' ),
		'sah'            => array( 'Sakha', 'Сахалыы', 'ltr' ),
		'si_LK'          => array( 'Sinhala', 'සිංහල', 'ltr' ),
		'sk_SK'          => array( 'Slovak', 'Slovenčina', 'ltr' ),
		'skr'            => array( 'Saraiki', 'سرائیکی', 'rtl' ),
		'sl_SI'          => array( 'Slovenian', 'Slovenščina', 'ltr' ),
		'snd'            => array( 'Sindhi', 'سنڌي', 'rtl' ),
		'sq'             => array( 'Albanian', 'Shqip', 'ltr' ),
		'sr_RS'          => array( 'Serbian', 'Српски језик', 'ltr' ),
		'sv_SE'          => array( 'Swedish', 'Svenska', 'ltr' ),
		'sw'             => array( 'Swahili', 'Kiswahili', 'ltr' ),
		'szl'            => array( 'Silesian', 'Ślōnskŏ gŏdka', 'ltr' ),
		'ta_IN'          => array( 'Tamil', 'தமிழ்', 'ltr' ),
		'ta_LK'          => array( 'Tamil (Sri Lanka)', 'தமிழ் (இலங்கை)', 'ltr' ),
		'tah'            => array( 'Tahitian', 'Reo Tahiti', 'ltr' ),
		'te'             => array( 'Telugu', 'తెలుగు', 'ltr' ),
		'th'             => array( 'Thai', 'ไทย', 'ltr' ),
		'tl'             => array( 'Tagalog', 'Tagalog', 'ltr' ),
		'tr_TR'          => array( 'Turkish', 'Türkçe', 'ltr' ),
		'tt_RU'          => array( 'Tatar', 'Татар теле', 'ltr' ),
		'ug_CN'          => array( 'Uighur', 'ئۇيغۇرچە', 'rtl' ),
		'uk'             => array( 'Ukrainian', 'Українська', 'ltr' ),
		'ur'             => array( 'Urdu', 'اردو', 'rtl' ),
		'uz_UZ'          => array( 'Uzbek', 'O‘zbekcha', 'ltr' ),
		'vi'             => array( 'Vietnamese', 'Tiếng Việt', 'ltr' ),
		'zh_CN'          => array( 'Chinese (China)', '简体中文', 'ltr' ),
		'zh_HK'          => array( 'Chinese (Hong Kong)', '香港中文', 'ltr' ),
		'zh_TW'          => array( 'Chinese (Taiwan)', '繁體中文', 'ltr' ),
	);

	/**
	 * The catalog as a list ready for the admin app.
	 *
	 * @return array<int,array{locale:string,name:string,native:string,dir:string,code:string,hreflang:string}>
	 */
	public static function all() {
		$out = array();
		foreach ( self::LOCALES as $locale => $entry ) {
			$out[] = array(
				'locale'   => $locale,
				'name'     => $entry[0],
				'native'   => $entry[1],
				'dir'      => $entry[2],
				'code'     => self::code_for( $locale ),
				'hreflang' => self::hreflang_for( $locale ),
			);
		}
		return $out;
	}

	/**
	 * Catalog entry for a locale, or null.
	 *
	 * @param string $locale WordPress locale.
	 * @return array|null
	 */
	public static function get( $locale ) {
		if ( ! isset( self::LOCALES[ $locale ] ) ) {
			return null;
		}
		$entry = self::LOCALES[ $locale ];
		return array(
			'locale'   => $locale,
			'name'     => $entry[0],
			'native'   => $entry[1],
			'dir'      => $entry[2],
			'code'     => self::code_for( $locale ),
			'hreflang' => self::hreflang_for( $locale ),
		);
	}

	/**
	 * Suggested short code: the language part ("fa_IR" → "fa"); regional
	 * variants of the same language get the region ("pt_BR" → "pt-br") when
	 * the plain code is already used.
	 *
	 * @param string   $locale WordPress locale.
	 * @param string[] $taken  Codes already in use.
	 * @return string
	 */
	public static function code_for( $locale, array $taken = array() ) {
		$parts = explode( '_', (string) $locale );
		$code  = strtolower( $parts[0] );

		if ( in_array( $code, $taken, true ) && isset( $parts[1] ) ) {
			$code .= '-' . strtolower( $parts[1] );
		}
		return $code;
	}

	/**
	 * hreflang value for a locale: language plus region ("fa_IR" → "fa-IR").
	 * Variant suffixes such as "_formal" are dropped.
	 *
	 * @param string $locale WordPress locale.
	 * @return string
	 */
	public static function hreflang_for( $locale ) {
		$parts = explode( '_', (string) $locale );
		$value = strtolower( $parts[0] );
		if ( isset( $parts[1] ) && preg_match( '/^[A-Z]{2}$/', $parts[1] ) ) {
			$value .= '-' . $parts[1];
		}
		return $value;
	}
}

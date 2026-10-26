/**
 * Extends the default @wordpress/scripts config.
 *
 * Fonts ship once, in assets/fonts next to their license (OFL.txt). CSS keeps
 * the relative URL (build/admin → ../../assets/fonts) instead of copying them
 * into build/.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const keepFontUrls = ( rule ) => {
	if ( ! Array.isArray( rule.use ) ) {
		return rule;
	}
	return {
		...rule,
		use: rule.use.map( ( use ) =>
			use.loader && /[\\/]css-loader[\\/]/.test( use.loader )
				? {
						...use,
						options: {
							...use.options,
							url: { filter: ( url ) => ! url.includes( '/assets/fonts/' ) },
						},
				  }
				: use
		),
	};
};

module.exports = {
	...defaultConfig,
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map( keepFontUrls ),
	},
};

/**
 * Mirrors templates/switcher.php so the designer previews exactly what the
 * front end prints (same classes, same stylesheet). Keep the two in sync.
 */
import { __ } from '@wordpress/i18n';

/**
 * Same rule as Renderer::is_light() in PHP (WCAG relative luminance).
 *
 * @param {string} hex Hex color.
 * @return {boolean} Whether text on it should be dark.
 */
export function isLight( hex ) {
	let value = hex.replace( '#', '' );
	if ( 3 === value.length ) {
		value = value.replace( /./g, '$&$&' );
	}
	const [ r, g, b ] = [ 0, 2, 4 ].map( ( offset ) => {
		const c = parseInt( value.substr( offset, 2 ), 16 ) / 255;
		return c <= 0.03928
			? c / 12.92
			: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.4;
}

const Globe = () => (
	<svg
		className="cedar-switcher__icon"
		width="16"
		height="16"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
	>
		<circle cx="12" cy="12" r="10" />
		<path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
		<path d="M2 12h20" />
	</svg>
);

const Chevron = () => (
	<svg
		className="cedar-switcher__chevron"
		width="14"
		height="14"
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
	>
		<path d="m6 9 6 6 6-6" />
	</svg>
);

function Label( { item, icon } ) {
	return (
		<>
			{ icon && <Globe /> }
			<span
				className="cedar-switcher__name"
				lang={ item.hreflang }
				dir={ item.dir }
			>
				{ item.native }
			</span>
			<span className="cedar-switcher__code" aria-hidden="true">
				{ item.code.toUpperCase() }
			</span>
		</>
	);
}

function Item( { item, icon } ) {
	if ( item.current ) {
		return (
			<span
				className="cedar-switcher__item is-current"
				aria-current="true"
			>
				<Label item={ item } icon={ icon } />
			</span>
		);
	}
	if ( ! item.available ) {
		return (
			<span
				className="cedar-switcher__item is-disabled"
				aria-disabled="true"
				data-tooltip={ __( 'Not translated yet', 'cedar-multilang' ) }
			>
				<Label item={ item } icon={ icon } />
			</span>
		);
	}
	return (
		<a
			className="cedar-switcher__item"
			href="#preview"
			hrefLang={ item.hreflang }
			lang={ item.hreflang }
		>
			<Label item={ item } icon={ icon } />
		</a>
	);
}

/**
 * @param {Object}  props
 * @param {Object}  props.profile Switcher profile.
 * @param {Array}   props.items   Items like the PHP Items class returns.
 * @param {boolean} [props.small] Apply the small-screen setting.
 */
export default function SwitcherMarkup( { profile, items, small = false } ) {
	let show = profile.show;
	let icon = profile.icon;
	let hideCode = false;
	if ( small && 'code' === profile.mobile ) {
		show = 'code';
	} else if ( small && 'icon' === profile.mobile ) {
		show = 'code';
		icon = true;
		hideCode = true;
	}

	const classes = [
		'cedar-switcher',
		`cedar-switcher--${ profile.style }`,
		`cedar-switcher--${ profile.shape }`,
		`cedar-switcher--show-${ show }`,
		'cedar-switcher--sm-same',
	];
	if ( 'inherit' === profile.styling ) {
		classes.push( 'cedar-switcher--inherit' );
	}
	if ( 'link' === profile.style ) {
		classes.push( `cedar-switcher--look-${ profile.linkLook }` );
	}
	if ( hideCode ) {
		classes.push( 'is-preview-icon-only' );
	}

	const style =
		profile.accent && 'inherit' !== profile.styling
			? {
					'--cedar-switcher-accent': profile.accent,
					'--cedar-switcher-on-accent': isLight( profile.accent )
						? '#181815'
						: '#ffffff',
					'--cedar-switcher-focus-ring': profile.accent,
				}
			: undefined;

	const current = items.find( ( item ) => item.current );
	const dropdown =
		'dropdown' === profile.style || 'floating' === profile.style;

	return (
		<nav
			className={ classes.join( ' ' ) }
			style={ style }
			aria-label={ __( 'Language', 'cedar-multilang' ) }
		>
			{ dropdown && current ? (
				<details className="cedar-switcher__details">
					<summary className="cedar-switcher__toggle">
						<Label item={ current } icon={ icon } />
						<Chevron />
					</summary>
					<div className="cedar-switcher__menu" role="list">
						{ items.map( ( item ) => (
							<div
								key={ item.code }
								className="cedar-switcher__entry"
								role="listitem"
							>
								<Item item={ item } icon={ false } />
							</div>
						) ) }
					</div>
				</details>
			) : (
				<div className="cedar-switcher__list" role="list">
					{ items
						.filter(
							( item ) =>
								! ( 'link' === profile.style && item.current )
						)
						.map( ( item ) => (
							<div
								key={ item.code }
								className="cedar-switcher__entry"
								role="listitem"
							>
								<Item item={ item } icon={ icon } />
							</div>
						) ) }
				</div>
			) }
		</nav>
	);
}

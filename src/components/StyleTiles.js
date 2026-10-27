/**
 * Switcher style tiles with live thumbnails, shared by the Switcher designer
 * and setup.
 */
import { useMemo, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import SwitcherMarkup from './SwitcherMarkup';

export const STYLES = [
	{ value: 'link', label: () => __( 'Link', 'ako-multilang' ) },
	{ value: 'inline', label: () => __( 'Inline', 'ako-multilang' ) },
	{ value: 'segmented', label: () => __( 'Segmented', 'ako-multilang' ) },
	{ value: 'dropdown', label: () => __( 'Dropdown', 'ako-multilang' ) },
	{ value: 'floating', label: () => __( 'Floating', 'ako-multilang' ) },
];

/**
 * Sample items for previews: the site's languages, one of them current.
 *
 * @param {Array}  languages Languages from the API.
 * @param {string} current   Current language code.
 * @param {string} missing   Profile `missing` setting (preview shows the third language as missing).
 * @return {Array} Items.
 */
export function sampleItems( languages, current, missing ) {
	return languages
		.map( ( language, index ) => ( {
			code: language.code,
			hreflang: language.hreflang,
			native: language.native,
			dir: language.dir,
			current: language.code === current,
			available: ! ( index >= 2 && 'disabled' === missing ),
			hidden:
				index >= 2 && 'hide' === missing && language.code !== current,
		} ) )
		.filter( ( item ) => ! item.hidden );
}

/**
 * Style tiles (Figma: Onboarding patterns / Preset tile) as a radio group.
 *
 * @param {Object}   props
 * @param {string}   props.value      Selected style.
 * @param {Function} props.onChange   Called with a style.
 * @param {Array}    props.languages  Languages for the thumbnails.
 * @param {Array}    [props.styles]   Styles to offer.
 * @param {string}   props.labelledBy Id of the group label.
 */
export default function StyleTiles( {
	value,
	onChange,
	languages,
	styles = STYLES,
	labelledBy,
} ) {
	const refs = useRef( {} );
	const items = useMemo(
		() =>
			sampleItems(
				languages.slice( 0, 2 ),
				languages[ 0 ] && languages[ 0 ].code,
				'home'
			),
		[ languages ]
	);

	const onKeyDown = ( event, index ) => {
		const moves = {
			ArrowRight: 1,
			ArrowDown: 1,
			ArrowLeft: -1,
			ArrowUp: -1,
		};
		if ( ! ( event.key in moves ) ) {
			return;
		}
		event.preventDefault();
		const rtl = 'rtl' === document.documentElement.dir;
		let step = moves[ event.key ];
		if (
			rtl &&
			( 'ArrowRight' === event.key || 'ArrowLeft' === event.key )
		) {
			step = -step;
		}
		const next =
			styles[ ( index + step + styles.length ) % styles.length ].value;
		onChange( next );
		refs.current[ next ].focus();
	};

	return (
		<div
			className="ako-tiles"
			role="radiogroup"
			aria-labelledby={ labelledBy }
		>
			{ styles.map( ( style, index ) => {
				const selected = style.value === value;
				return (
					<button
						type="button"
						key={ style.value }
						ref={ ( node ) =>
							( refs.current[ style.value ] = node )
						}
						role="radio"
						aria-checked={ selected }
						tabIndex={ selected ? 0 : -1 }
						className={ `ako-tile ${ selected ? 'is-selected' : '' }` }
						onClick={ () => onChange( style.value ) }
						onKeyDown={ ( event ) => onKeyDown( event, index ) }
					>
						<span className="ako-tile__thumb" inert="">
							{ items.length > 1 && (
								<SwitcherMarkup
									profile={ {
										style: style.value,
										shape: 'pill',
										show:
											'link' === style.value ||
											'inline' === style.value
												? 'native'
												: 'code',
										icon: [
											'link',
											'dropdown',
											'floating',
										].includes( style.value ),
										mobile: 'same',
										styling: 'ako',
										accent: '',
										linkLook: 'outline',
									} }
									items={ items }
								/>
							) }
						</span>
						<span className="ako-tile__label">
							<span>{ style.label() }</span>
							{ selected && (
								<Icon name="circle-check" size={ 14 } />
							) }
						</span>
					</button>
				);
			} ) }
		</div>
	);
}

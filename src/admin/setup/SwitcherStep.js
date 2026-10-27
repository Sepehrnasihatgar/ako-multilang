/**
 * Setup step 3 (Figma: Onboarding / 3 · Switcher — Default, Hover, No menu
 * in theme).
 */
import { useId, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '../../components/controls';
import StyleTiles, { STYLES } from '../../components/StyleTiles';
import { errorMessage, placeSwitcher } from '../api';

const LOOKS = STYLES.filter( ( style ) =>
	[ 'link', 'segmented', 'dropdown', 'floating' ].includes( style.value )
);

/**
 * @param {Object}   props
 * @param {Object}   props.state  Setup state.
 * @param {Function} props.notify Toast.
 * @param {Function} props.onBack Previous step.
 * @param {Function} props.onNext Next step.
 */
export default function SwitcherStep( { state, notify, onBack, onNext } ) {
	const menuReady = !! state.menu && state.canEditMenus;
	const [ look, setLook ] = useState( 'segmented' );
	const [ placement, setPlacement ] = useState(
		menuReady ? 'menu' : 'floating'
	);
	const [ saving, setSaving ] = useState( false );
	const lookId = useId();

	const chooseLook = ( value ) => {
		setLook( value );
		if ( 'floating' === value ) {
			setPlacement( 'floating' );
		} else if ( 'floating' === placement ) {
			setPlacement( menuReady ? 'menu' : 'manual' );
		}
	};

	const choosePlacement = ( value ) => {
		setPlacement( value );
		if ( 'floating' === value ) {
			setLook( 'floating' );
		} else if ( 'floating' === look ) {
			setLook( 'dropdown' );
		}
	};

	const save = async () => {
		setSaving( true );
		try {
			await placeSwitcher( look, placement );
			setSaving( false );
			onNext();
		} catch ( error ) {
			setSaving( false );
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The switcher couldn’t be added.', 'ako-multilang' )
				),
			} );
		}
	};

	let menuHelp = __(
		'Detected in your theme. The switcher becomes the last menu item.',
		'ako-multilang'
	);
	if ( ! state.menu ) {
		menuHelp = __(
			'We couldn’t find a menu in your theme. Use the floating button or add the block.',
			'ako-multilang'
		);
	} else if ( ! state.canEditMenus ) {
		menuHelp = __( 'You can’t edit menus on this site.', 'ako-multilang' );
	}

	const placements = [
		{
			value: 'menu',
			label: state.menu
				? state.menu.name
				: __( 'Primary menu', 'ako-multilang' ),
			help: menuHelp,
			disabled: ! menuReady,
			recommended: menuReady,
		},
		{
			value: 'floating',
			label: __( 'Floating button', 'ako-multilang' ),
			help: __(
				'Sits in the bottom corner of every page. No theme changes.',
				'ako-multilang'
			),
			recommended: ! menuReady,
		},
		{
			value: 'manual',
			label: __( 'I’ll place it myself', 'ako-multilang' ),
			help: __(
				'Use the block, the shortcode, or the PHP tag.',
				'ako-multilang'
			),
		},
	];

	return (
		<>
			<div className="ako-setup__intro">
				<h1 tabIndex={ -1 }>
					{ __( 'Add a language switcher', 'ako-multilang' ) }
				</h1>
				<p>
					{ __(
						'Choose a look and where it goes. You can fine-tune colors, labels, and placement later in Switcher.',
						'ako-multilang'
					) }
				</p>
			</div>

			<div className="ako-field">
				<span className="ako-field__label" id={ lookId }>
					{ __( 'Look', 'ako-multilang' ) }
				</span>
				<StyleTiles
					value={ look }
					onChange={ chooseLook }
					languages={ state.languages.languages }
					styles={ LOOKS }
					labelledBy={ lookId }
				/>
			</div>

			<fieldset className="ako-field ako-placements">
				<legend className="ako-field__label">
					{ __( 'Where should it go?', 'ako-multilang' ) }
				</legend>
				{ placements.map( ( option ) => (
					<div
						key={ option.value }
						className={ `ako-placement ${
							placement === option.value ? 'is-selected' : ''
						} ${ option.disabled ? 'is-disabled' : '' }` }
					>
						<input
							id={ `${ lookId }-${ option.value }` }
							type="radio"
							name="ako-placement"
							value={ option.value }
							checked={ placement === option.value }
							disabled={ option.disabled }
							aria-describedby={ `${ lookId }-${ option.value }-help` }
							onChange={ () => choosePlacement( option.value ) }
						/>
						<label
							htmlFor={ `${ lookId }-${ option.value }` }
							className="ako-placement__title"
						>
							{ option.label }
							{ option.recommended && (
								<span className="ako-placement__tag">
									{ __( 'Recommended', 'ako-multilang' ) }
								</span>
							) }
						</label>
						<span
							id={ `${ lookId }-${ option.value }-help` }
							className="ako-placement__help"
						>
							{ option.help }
						</span>
					</div>
				) ) }
			</fieldset>

			<div className="ako-setup__footer">
				<Button variant="ghost" onClick={ onBack } disabled={ saving }>
					{ __( 'Back', 'ako-multilang' ) }
				</Button>
				<span className="ako-setup__footer-end">
					<Button
						variant="ghost"
						onClick={ onNext }
						disabled={ saving }
					>
						{ __( 'Skip', 'ako-multilang' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ saving }
						aria-busy={ saving || undefined }
					>
						{ __( 'Add switcher', 'ako-multilang' ) }
					</Button>
				</span>
			</div>
		</>
	);
}

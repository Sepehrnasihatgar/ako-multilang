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
					__( 'The switcher couldn’t be added.', 'cedar-multilang' )
				),
			} );
		}
	};

	let menuHelp = __(
		'Detected in your theme. The switcher becomes the last menu item.',
		'cedar-multilang'
	);
	if ( ! state.menu ) {
		menuHelp = __(
			'We couldn’t find a menu in your theme. Use the floating button or add the block.',
			'cedar-multilang'
		);
	} else if ( ! state.canEditMenus ) {
		menuHelp = __(
			'You can’t edit menus on this site.',
			'cedar-multilang'
		);
	}

	const placements = [
		{
			value: 'menu',
			label: state.menu
				? state.menu.name
				: __( 'Primary menu', 'cedar-multilang' ),
			help: menuHelp,
			disabled: ! menuReady,
			recommended: menuReady,
		},
		{
			value: 'floating',
			label: __( 'Floating button', 'cedar-multilang' ),
			help: __(
				'Sits in the bottom corner of every page. No theme changes.',
				'cedar-multilang'
			),
			recommended: ! menuReady,
		},
		{
			value: 'manual',
			label: __( 'I’ll place it myself', 'cedar-multilang' ),
			help: __(
				'Use the block, the shortcode, or the PHP tag.',
				'cedar-multilang'
			),
		},
	];

	return (
		<>
			<div className="cedar-setup__intro">
				<h1 tabIndex={ -1 }>
					{ __( 'Add a language switcher', 'cedar-multilang' ) }
				</h1>
				<p>
					{ __(
						'Choose a look and where it goes. You can fine-tune colors, labels, and placement later in Switcher.',
						'cedar-multilang'
					) }
				</p>
			</div>

			<div className="cedar-field">
				<span className="cedar-field__label" id={ lookId }>
					{ __( 'Look', 'cedar-multilang' ) }
				</span>
				<StyleTiles
					value={ look }
					onChange={ chooseLook }
					languages={ state.languages.languages }
					styles={ LOOKS }
					labelledBy={ lookId }
				/>
			</div>

			<fieldset className="cedar-field cedar-placements">
				<legend className="cedar-field__label">
					{ __( 'Where should it go?', 'cedar-multilang' ) }
				</legend>
				{ placements.map( ( option ) => (
					<div
						key={ option.value }
						className={ `cedar-placement ${
							placement === option.value ? 'is-selected' : ''
						} ${ option.disabled ? 'is-disabled' : '' }` }
					>
						<input
							id={ `${ lookId }-${ option.value }` }
							type="radio"
							name="cedar-placement"
							value={ option.value }
							checked={ placement === option.value }
							disabled={ option.disabled }
							aria-describedby={ `${ lookId }-${ option.value }-help` }
							onChange={ () => choosePlacement( option.value ) }
						/>
						<label
							htmlFor={ `${ lookId }-${ option.value }` }
							className="cedar-placement__title"
						>
							{ option.label }
							{ option.recommended && (
								<span className="cedar-placement__tag">
									{ __( 'Recommended', 'cedar-multilang' ) }
								</span>
							) }
						</label>
						<span
							id={ `${ lookId }-${ option.value }-help` }
							className="cedar-placement__help"
						>
							{ option.help }
						</span>
					</div>
				) ) }
			</fieldset>

			<div className="cedar-setup__footer">
				<Button variant="ghost" onClick={ onBack } disabled={ saving }>
					{ __( 'Back', 'cedar-multilang' ) }
				</Button>
				<span className="cedar-setup__footer-end">
					<Button
						variant="ghost"
						onClick={ onNext }
						disabled={ saving }
					>
						{ __( 'Skip', 'cedar-multilang' ) }
					</Button>
					<Button
						variant="primary"
						onClick={ save }
						disabled={ saving }
						aria-busy={ saving || undefined }
					>
						{ __( 'Add switcher', 'cedar-multilang' ) }
					</Button>
				</span>
			</div>
		</>
	);
}

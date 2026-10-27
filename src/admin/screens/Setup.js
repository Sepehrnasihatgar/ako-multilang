/**
 * Setup (Figma: Admin screens / Onboarding — 1 · Languages, 2 · Connect
 * pages, 3 · Switcher, 4 · Done). Each step saves through the same REST
 * routes as the rest of Ako; nothing changes on the site before a step's
 * main button is pressed.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import { Button } from '../../components/controls';
import { EmptyState, Logo } from '../../components/display';
import { errorMessage, getCatalog, getSetup } from '../api';
import LanguagesStep from '../setup/LanguagesStep';
import ConnectStep from '../setup/ConnectStep';
import SwitcherStep from '../setup/SwitcherStep';
import DoneStep from '../setup/DoneStep';

const STEPS = [
	() => __( 'Languages', 'ako-multilang' ),
	() => __( 'Connect pages', 'ako-multilang' ),
	() => __( 'Switcher', 'ako-multilang' ),
	() => __( 'Done', 'ako-multilang' ),
];

/**
 * Stepper (Figma: Onboarding patterns / Stepper).
 *
 * @param {Object} props
 * @param {number} props.current Current step index.
 */
function Stepper( { current } ) {
	return (
		<ol className="ako-stepper">
			{ STEPS.map( ( label, index ) => {
				let state = 'upcoming';
				if ( index < current || 3 === current ) {
					state = 'done';
				} else if ( index === current ) {
					state = 'current';
				}
				return (
					<li
						key={ index }
						className={ `ako-stepper__step is-${ state }` }
						aria-current={
							'current' === state ? 'step' : undefined
						}
					>
						<span
							className="ako-stepper__marker"
							aria-hidden="true"
						>
							{ 'done' === state ? (
								<Icon name="check" size={ 12 } />
							) : (
								index + 1
							) }
						</span>
						<span className="ako-stepper__label">
							{ label() }
							{ 'done' === state && (
								<span className="screen-reader-text">
									{ __( '(done)', 'ako-multilang' ) }
								</span>
							) }
						</span>
					</li>
				);
			} ) }
		</ol>
	);
}

export default function Setup( { config, notify } ) {
	const [ state, setState ] = useState( null );
	const [ catalog, setCatalog ] = useState( [] );
	const [ loadError, setLoadError ] = useState( null );
	const [ step, setStep ] = useState( 0 );
	const cardRef = useRef();

	const load = useCallback( () => {
		setLoadError( null );
		Promise.all( [ getSetup(), getCatalog() ] )
			.then( ( [ setup, list ] ) => {
				setState( setup );
				setCatalog( list );
			} )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__( 'Setup couldn’t be loaded.', 'ako-multilang' )
					)
				)
			);
	}, [] );

	useEffect( load, [ load ] );

	// Move focus to the new step's heading so keyboard and screen-reader
	// users start at the top of it.
	const go = ( next ) => {
		setStep( next );
		window.requestAnimationFrame( () => {
			const heading =
				cardRef.current && cardRef.current.querySelector( 'h1' );
			if ( heading ) {
				heading.focus();
			}
		} );
	};

	let content = null;
	if ( loadError ) {
		content = (
			<EmptyState
				icon="alert"
				title={ __( 'Couldn’t load setup', 'ako-multilang' ) }
				description={ loadError }
				actions={
					<Button variant="primary" onClick={ load }>
						{ __( 'Try again', 'ako-multilang' ) }
					</Button>
				}
			/>
		);
	} else if ( ! state ) {
		content = (
			<p className="ako-setup__loading" role="status">
				{ __( 'Loading…', 'ako-multilang' ) }
			</p>
		);
	} else if ( 0 === step ) {
		content = (
			<LanguagesStep
				state={ state }
				catalog={ catalog }
				languagesUrl={ config.urls.languages }
				onSaved={ ( languages ) => {
					setState( { ...state, languages } );
					go( 1 );
				} }
			/>
		);
	} else if ( 1 === step ) {
		content = (
			<ConnectStep
				state={ state }
				notify={ notify }
				onBack={ () => go( 0 ) }
				onNext={ () => go( 2 ) }
				onFinish={ () => go( 3 ) }
			/>
		);
	} else if ( 2 === step ) {
		content = (
			<SwitcherStep
				state={ state }
				notify={ notify }
				onBack={ () => go( 1 ) }
				onNext={ () => go( 3 ) }
			/>
		);
	} else {
		content = <DoneStep config={ config } notify={ notify } />;
	}

	return (
		<div className="ako-setup">
			<div className="ako-setup__brand">
				<Logo size={ 32 } className="ako-setup__logo" />
				<span>{ __( 'Set up Ako', 'ako-multilang' ) }</span>
			</div>
			<nav
				aria-label={ sprintf(
					/* translators: 1: Current step number, 2: Number of steps. */
					__( 'Setup progress, step %1$d of %2$d', 'ako-multilang' ),
					step + 1,
					STEPS.length
				) }
			>
				<Stepper current={ step } />
			</nav>
			<div className="ako-setup__card" ref={ cardRef }>
				{ content }
			</div>
		</div>
	);
}

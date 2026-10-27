/**
 * Setup step 2 (Figma: Onboarding / 2 · Connect pages — Default, Importing,
 * Imported, Import failed, Nothing found).
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import { Button } from '../../components/controls';
import { Callout } from '../../components/display';
import {
	connectPairs,
	errorMessage,
	getImportSources,
	getSuggestions,
} from '../api';
import ImportBanner from './ImportBanner';
import PairList, { defaultSelection, pairKey } from './PairList';

/**
 * @param {Object}   props
 * @param {Object}   props.state    Setup state.
 * @param {Function} props.notify   Toast.
 * @param {Function} props.onBack   Previous step.
 * @param {Function} props.onNext   Next step.
 * @param {Function} props.onFinish Skip to the summary.
 */
export default function ConnectStep( {
	state,
	notify,
	onBack,
	onNext,
	onFinish,
} ) {
	const [ sources, setSources ] = useState( state.sources );
	const [ pairs, setPairs ] = useState( null );
	const [ selected, setSelected ] = useState( new Set() );
	const [ importing, setImporting ] = useState( false );
	const [ connecting, setConnecting ] = useState( false );
	const [ loadError, setLoadError ] = useState( '' );

	const defaultCode = state.languages.default;

	const loadPairs = useCallback( () => {
		setLoadError( '' );
		return getSuggestions()
			.then( ( response ) => {
				setPairs( response.pairs );
				setSelected( defaultSelection( response.pairs ) );
			} )
			.catch( ( error ) => {
				setPairs( [] );
				setLoadError(
					errorMessage(
						error,
						__( 'Suggestions couldn’t be loaded.', 'ako-multilang' )
					)
				);
			} );
	}, [] );

	useEffect( () => {
		loadPairs();
		getImportSources()
			.then( setSources )
			.catch( () => {} );
	}, [ loadPairs ] );

	const connect = async () => {
		const chosen = pairs
			.filter( ( pair ) => selected.has( pairKey( pair ) ) )
			.map( ( pair ) => ( {
				source: pair.source.id,
				target: pair.target.id,
				language: pair.language,
			} ) );
		setConnecting( true );
		try {
			const result = await connectPairs( chosen );
			if ( result.errors.length ) {
				notify( {
					tone: 'error',
					message: sprintf(
						/* translators: 1: Connected pairs, 2: First error message. */
						_n(
							'%1$d pair connected. One couldn’t be: %2$s',
							'%1$d pairs connected. One couldn’t be: %2$s',
							result.linked,
							'ako-multilang'
						),
						result.linked,
						result.errors[ 0 ].message
					),
				} );
			} else {
				notify( {
					tone: 'success',
					message: sprintf(
						/* translators: %d: Number of pairs. */
						_n(
							'%d pair connected.',
							'%d pairs connected.',
							result.linked,
							'ako-multilang'
						),
						result.linked
					),
				} );
			}
			setConnecting( false );
			onNext();
		} catch ( error ) {
			setConnecting( false );
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The pairs couldn’t be connected.', 'ako-multilang' )
				),
			} );
		}
	};

	const loading = null === pairs;
	const nothing = ! loading && ! sources.length && ! pairs.length;
	const count = loading
		? 0
		: pairs.filter( ( pair ) => selected.has( pairKey( pair ) ) ).length;
	const busy = importing || connecting;

	return (
		<>
			<div className="ako-setup__intro">
				<h1 tabIndex={ -1 }>
					{ __( 'Connect your pages', 'ako-multilang' ) }
				</h1>
				<p>
					{ nothing
						? __(
								'We looked for WPML, Polylang, and Cedar 6.2 data, and for pages that look like translations of each other.',
								'ako-multilang'
							)
						: __(
								'Ako found translations you already made. Review them, and we’ll set up hreflang and the switcher for every pair.',
								'ako-multilang'
							) }
				</p>
			</div>

			{ sources.map( ( source ) => (
				<ImportBanner
					key={ source.key }
					source={ source }
					onBusy={ setImporting }
					onImported={ loadPairs }
				/>
			) ) }

			{ loading && (
				<p className="ako-setup__loading" role="status">
					{ __( 'Looking for pages that match…', 'ako-multilang' ) }
				</p>
			) }

			{ loadError && (
				<Callout
					tone="danger"
					icon="alert"
					role="alert"
					title={ __(
						'Suggestions couldn’t be loaded',
						'ako-multilang'
					) }
					action={
						<Button onClick={ loadPairs }>
							{ __( 'Retry', 'ako-multilang' ) }
						</Button>
					}
				>
					{ loadError }
				</Callout>
			) }

			{ ! loading && pairs.length > 0 && (
				<PairList
					pairs={ pairs }
					selected={ selected }
					onChange={ setSelected }
					defaultCode={ defaultCode }
					disabled={ busy }
				/>
			) }

			{ nothing && ! loadError && (
				<div className="ako-setup__empty">
					<span className="ako-empty__tile" aria-hidden="true">
						<Icon name="link" size={ 20 } />
					</span>
					<h2 className="ako-empty__title">
						{ __(
							'No existing translations found',
							'ako-multilang'
						) }
					</h2>
					<p className="ako-empty__description">
						{ __(
							'That’s fine. Your languages are saved. Add the switcher now, or finish and link pages later from Translations or right in the editor.',
							'ako-multilang'
						) }
					</p>
				</div>
			) }

			<div className="ako-setup__footer">
				<Button variant="ghost" onClick={ onBack } disabled={ busy }>
					{ __( 'Back', 'ako-multilang' ) }
				</Button>
				<span className="ako-setup__footer-end">
					{ nothing ? (
						<Button variant="ghost" onClick={ onFinish }>
							{ __( 'Finish setup', 'ako-multilang' ) }
						</Button>
					) : (
						count > 0 && (
							<Button
								variant="ghost"
								onClick={ onNext }
								disabled={ busy }
							>
								{ __( 'Skip for now', 'ako-multilang' ) }
							</Button>
						)
					) }
					{ count > 0 ? (
						<Button
							variant="primary"
							onClick={ connect }
							disabled={ busy }
							aria-busy={ connecting || undefined }
						>
							{ sprintf(
								/* translators: %d: Number of pairs. */
								_n(
									'Connect %d pair',
									'Connect %d pairs',
									count,
									'ako-multilang'
								),
								count
							) }
						</Button>
					) : (
						<Button
							variant="primary"
							onClick={ onNext }
							disabled={ busy || loading }
						>
							{ __( 'Next: Switcher', 'ako-multilang' ) }
						</Button>
					) }
				</span>
			</div>
		</>
	);
}

/**
 * Import and Link pages dialogs for the Translations screen header and its
 * first-run state. Same parts as setup step 2.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Dialog } from '../../components/overlays';
import { connectPairs, errorMessage, getSuggestions } from '../api';
import ImportBanner from './ImportBanner';
import PairList, { defaultSelection, pairKey } from './PairList';

/**
 * @param {Object}   props
 * @param {Array}    props.sources Import sources with data.
 * @param {Function} props.onClose Close handler (reloads the list).
 */
export function ImportDialog( { sources, onClose } ) {
	const [ busy, setBusy ] = useState( false );
	return (
		<Dialog
			wide
			icon="arrows-left-right"
			title={ __( 'Import translations', 'cedar-multilang' ) }
			description={ __(
				'Cedar reads the links straight from your database. The other plugin’s data isn’t changed.',
				'cedar-multilang'
			) }
			busy={ busy }
			onCancel={ onClose }
		>
			<div className="cedar-dialog__stack">
				{ sources.map( ( source ) => (
					<ImportBanner
						key={ source.key }
						source={ source }
						onBusy={ setBusy }
					/>
				) ) }
			</div>
		</Dialog>
	);
}

/**
 * @param {Object}   props
 * @param {string}   props.defaultCode Default language code.
 * @param {Function} props.notify      Toast.
 * @param {Function} props.onClose     Close handler.
 * @param {Function} props.onLinked    Called after pairs were linked.
 */
export function LinkPagesDialog( { defaultCode, notify, onClose, onLinked } ) {
	const [ pairs, setPairs ] = useState( null );
	const [ selected, setSelected ] = useState( new Set() );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		getSuggestions()
			.then( ( response ) => {
				setPairs( response.pairs );
				setSelected( defaultSelection( response.pairs ) );
			} )
			.catch( ( failure ) => {
				setPairs( [] );
				setError(
					errorMessage(
						failure,
						__(
							'Suggestions couldn’t be loaded.',
							'cedar-multilang'
						)
					)
				);
			} );
	}, [] );

	const chosen = pairs
		? pairs.filter( ( pair ) => selected.has( pairKey( pair ) ) )
		: [];

	const connect = async () => {
		setBusy( true );
		try {
			const result = await connectPairs(
				chosen.map( ( pair ) => ( {
					source: pair.source.id,
					target: pair.target.id,
					language: pair.language,
				} ) )
			);
			notify( {
				tone: result.errors.length ? 'error' : 'success',
				message: result.errors.length
					? sprintf(
							/* translators: 1: Connected pairs, 2: First error message. */
							_n(
								'%1$d pair connected. One couldn’t be: %2$s',
								'%1$d pairs connected. One couldn’t be: %2$s',
								result.linked,
								'cedar-multilang'
							),
							result.linked,
							result.errors[ 0 ].message
						)
					: sprintf(
							/* translators: %d: Number of pairs. */
							_n(
								'%d pair connected.',
								'%d pairs connected.',
								result.linked,
								'cedar-multilang'
							),
							result.linked
						),
			} );
			onLinked();
		} catch ( failure ) {
			setBusy( false );
			notify( {
				tone: 'error',
				message: errorMessage(
					failure,
					__( 'The pairs couldn’t be connected.', 'cedar-multilang' )
				),
			} );
		}
	};

	let description = __(
		'Pages that look like translations of each other, by slug and title. Check the ones to connect.',
		'cedar-multilang'
	);
	if ( null === pairs ) {
		description = __( 'Looking for pages that match…', 'cedar-multilang' );
	} else if ( error ) {
		description = error;
	} else if ( ! pairs.length ) {
		description = __(
			'No pages look like translations of each other. Use “Add translation” on any row, or the language panel in the editor.',
			'cedar-multilang'
		);
	}

	return (
		<Dialog
			wide={ !! ( pairs && pairs.length ) }
			icon="link"
			title={ __( 'Link pages', 'cedar-multilang' ) }
			description={ description }
			busy={ busy }
			onCancel={ onClose }
			confirmLabel={
				chosen.length
					? sprintf(
							/* translators: %d: Number of pairs. */
							_n(
								'Connect %d pair',
								'Connect %d pairs',
								chosen.length,
								'cedar-multilang'
							),
							chosen.length
						)
					: undefined
			}
			onConfirm={ chosen.length ? connect : undefined }
		>
			{ pairs && pairs.length > 0 && (
				<PairList
					pairs={ pairs }
					selected={ selected }
					onChange={ setSelected }
					defaultCode={ defaultCode }
					disabled={ busy }
				/>
			) }
		</Dialog>
	);
}

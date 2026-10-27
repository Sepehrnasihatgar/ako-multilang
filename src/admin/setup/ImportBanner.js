/**
 * Import from WPML or Polylang (Figma: Onboarding / 2 · Connect pages —
 * Default, Importing, Imported, Import failed). Runs in small batches so a
 * big site never hits a request timeout.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '../../components/controls';
import { Callout } from '../../components/display';
import { Dialog } from '../../components/overlays';
import { errorMessage, importBatch } from '../api';

const REASONS = {
	missing: () => __( 'Pages no longer exist', 'cedar-multilang' ),
	conflict: () =>
		__( 'Already linked differently in Cedar', 'cedar-multilang' ),
	error: () => __( 'Couldn’t be saved', 'cedar-multilang' ),
};

/**
 * @param {Object}   props
 * @param {Object}   props.source       { key, name, groups, imported }.
 * @param {Function} [props.onImported] Called after a finished import.
 * @param {Function} [props.onBusy]     Called with true while importing.
 */
export default function ImportBanner( { source, onImported, onBusy } ) {
	const [ state, setState ] = useState( source.imported ? 'before' : 'idle' );
	const [ progress, setProgress ] = useState( {
		processed: 0,
		total: source.groups,
	} );
	const [ result, setResult ] = useState( { linked: 0, skipped: [] } );
	const [ error, setError ] = useState( '' );
	const [ reviewing, setReviewing ] = useState( false );

	const run = async ( start, previous ) => {
		setState( 'importing' );
		if ( onBusy ) {
			onBusy( true );
		}
		let offset = start;
		let linked = previous.linked;
		let skipped = previous.skipped;
		try {
			for (;;) {
				const batch = await importBatch( source.key, offset );
				linked += batch.linked;
				skipped = skipped.concat( batch.skipped );
				offset = batch.processed;
				setProgress( {
					processed: batch.processed,
					total: batch.total,
				} );
				if ( batch.done ) {
					break;
				}
			}
			setResult( { linked, skipped } );
			setState( 'done' );
			if ( onImported ) {
				onImported();
			}
		} catch ( failure ) {
			setResult( { linked, skipped } );
			setError(
				errorMessage(
					failure,
					sprintf(
						/* translators: %s: Plugin name, e.g. "WPML". */
						__(
							'Cedar can’t read the %s translations.',
							'cedar-multilang'
						),
						source.name
					)
				)
			);
			setState( 'failed' );
		}
		if ( onBusy ) {
			onBusy( false );
		}
	};

	const start = () => {
		setProgress( { processed: 0, total: source.groups } );
		run( 0, { linked: 0, skipped: [] } );
	};

	if ( 'importing' === state ) {
		const percent = progress.total
			? Math.round( ( 100 * progress.processed ) / progress.total )
			: 0;
		return (
			<div className="cedar-import-progress">
				<div className="cedar-import-progress__head">
					<strong>
						{ sprintf(
							/* translators: %s: Plugin name, e.g. "WPML". */
							__( 'Importing from %s…', 'cedar-multilang' ),
							source.name
						) }
					</strong>
					<span>
						{ sprintf(
							/* translators: 1: Groups done, 2: All groups. */
							__( '%1$d of %2$d', 'cedar-multilang' ),
							progress.processed,
							progress.total
						) }
					</span>
				</div>
				<div
					className="cedar-progress"
					role="progressbar"
					aria-valuemin={ 0 }
					aria-valuemax={ 100 }
					aria-valuenow={ percent }
					aria-label={ __( 'Import progress', 'cedar-multilang' ) }
				>
					<span
						className="cedar-progress__value"
						style={ { inlineSize: `${ percent }%` } }
					/>
				</div>
				<p>
					{ __(
						'Reading translation groups. Keep this page open until it finishes.',
						'cedar-multilang'
					) }
				</p>
			</div>
		);
	}

	if ( 'failed' === state ) {
		const done = progress.processed;
		return (
			<Callout
				tone="danger"
				icon="alert"
				role="alert"
				title={ sprintf(
					/* translators: %s: Plugin name, e.g. "WPML". */
					__( 'Couldn’t read %s data', 'cedar-multilang' ),
					source.name
				) }
				action={
					<Button onClick={ () => run( done, result ) }>
						{ __( 'Retry', 'cedar-multilang' ) }
					</Button>
				}
			>
				{ error }{ ' ' }
				{ done
					? sprintf(
							/* translators: %d: Number of groups already imported. */
							_n(
								'%d group was imported before it stopped — retry to finish. Links already made are kept.',
								'%d groups were imported before it stopped — retry to finish. Links already made are kept.',
								done,
								'cedar-multilang'
							),
							done
						)
					: __(
							'Nothing was changed — retry, or skip and link pages later.',
							'cedar-multilang'
						) }
			</Callout>
		);
	}

	if ( 'done' === state ) {
		const skipped = result.skipped.length;
		const allMissing =
			skipped > 0 &&
			result.skipped.every( ( group ) => 'missing' === group.reason );
		let description = __(
			'Every group was linked automatically.',
			'cedar-multilang'
		);
		if ( skipped && allMissing ) {
			description = sprintf(
				/* translators: 1: Linked groups, 2: Skipped groups. */
				_n(
					'%1$d linked automatically. %2$d points to pages that no longer exist — review it before you continue.',
					'%1$d linked automatically. %2$d point to pages that no longer exist — review them before you continue.',
					skipped,
					'cedar-multilang'
				),
				result.linked,
				skipped
			);
		} else if ( skipped ) {
			description = sprintf(
				/* translators: 1: Linked groups, 2: Skipped groups. */
				_n(
					'%1$d linked automatically. %2$d couldn’t be linked — review it before you continue.',
					'%1$d linked automatically. %2$d couldn’t be linked — review them before you continue.',
					skipped,
					'cedar-multilang'
				),
				result.linked,
				skipped
			);
		}

		return (
			<>
				<Callout
					tone="success"
					icon="circle-check"
					title={ sprintf(
						/* translators: %d: Number of groups. */
						_n(
							'%d group imported',
							'%d groups imported',
							progress.processed,
							'cedar-multilang'
						),
						progress.processed
					) }
					action={
						skipped > 0 && (
							<Button onClick={ () => setReviewing( true ) }>
								{ sprintf(
									/* translators: %d: Number of groups to review. */
									__( 'Review %d', 'cedar-multilang' ),
									skipped
								) }
							</Button>
						)
					}
				>
					{ description }
				</Callout>
				{ reviewing && (
					<ReviewDialog
						groups={ result.skipped }
						onClose={ () => setReviewing( false ) }
					/>
				) }
			</>
		);
	}

	if ( ! source.groups ) {
		return (
			<Callout
				tone="neutral"
				icon="arrows-left-right"
				title={ sprintf(
					/* translators: %s: Plugin name, e.g. "WPML". */
					__( '%s data found', 'cedar-multilang' ),
					source.name
				) }
			>
				{ __(
					'It has no translated pages or posts for the content types Cedar handles.',
					'cedar-multilang'
				) }
			</Callout>
		);
	}

	if ( 'before' === state ) {
		return (
			<Callout
				tone="success"
				icon="circle-check"
				title={ sprintf(
					/* translators: %s: Plugin name, e.g. "WPML". */
					__( '%s links imported', 'cedar-multilang' ),
					source.name
				) }
				action={
					<Button onClick={ start }>
						{ __( 'Import again', 'cedar-multilang' ) }
					</Button>
				}
			>
				{ __(
					'Run it again to pick up pages added since. Links you already have stay as they are.',
					'cedar-multilang'
				) }
			</Callout>
		);
	}

	return (
		<Callout
			icon="arrows-left-right"
			title={ sprintf(
				/* translators: 1: Plugin name, e.g. "WPML", 2: Number of groups. */
				_n(
					'%1$s data detected — %2$d translation group',
					'%1$s data detected — %2$d translation groups',
					source.groups,
					'cedar-multilang'
				),
				source.name,
				source.groups
			) }
			action={
				<Button onClick={ start }>
					{ sprintf(
						/* translators: %d: Number of groups. */
						_n(
							'Import %d group',
							'Import %d groups',
							source.groups,
							'cedar-multilang'
						),
						source.groups
					) }
				</Button>
			}
		>
			{ sprintf(
				/* translators: %s: Plugin name, e.g. "WPML". */
				__(
					'Import them now. %s stays untouched, so you can switch back anytime.',
					'cedar-multilang'
				),
				source.name
			) }
		</Callout>
	);
}

/**
 * Groups that weren't linked, and why.
 *
 * @param {Object}   props
 * @param {Array}    props.groups  Skipped groups { reason, pages }.
 * @param {Function} props.onClose Close handler.
 */
function ReviewDialog( { groups, onClose } ) {
	return (
		<Dialog
			icon="alert"
			title={ sprintf(
				/* translators: %d: Number of groups. */
				_n(
					'%d group wasn’t linked',
					'%d groups weren’t linked',
					groups.length,
					'cedar-multilang'
				),
				groups.length
			) }
			description={ __(
				'Nothing was changed for these. If you still need them, link the pages from Translations or in the editor.',
				'cedar-multilang'
			) }
			onCancel={ onClose }
		>
			<ul className="cedar-review-list">
				{ groups.slice( 0, 50 ).map( ( group, index ) => (
					<li key={ index }>
						<span className="cedar-review-list__pages" dir="auto">
							{ group.pages.length
								? group.pages
										.map( ( page ) => page.title )
										.join( ' · ' )
								: __( '(no pages left)', 'cedar-multilang' ) }
						</span>
						<span className="cedar-review-list__reason">
							{ ( REASONS[ group.reason ] || REASONS.error )() }
						</span>
					</li>
				) ) }
			</ul>
		</Dialog>
	);
}

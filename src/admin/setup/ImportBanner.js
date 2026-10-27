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
	missing: () => __( 'Pages no longer exist', 'ako-multilang' ),
	conflict: () => __( 'Already linked differently in Ako', 'ako-multilang' ),
	error: () => __( 'Couldn’t be saved', 'ako-multilang' ),
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
							'Ako can’t read the %s translations.',
							'ako-multilang'
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
			<div className="ako-import-progress">
				<div className="ako-import-progress__head">
					<strong>
						{ sprintf(
							/* translators: %s: Plugin name, e.g. "WPML". */
							__( 'Importing from %s…', 'ako-multilang' ),
							source.name
						) }
					</strong>
					<span>
						{ sprintf(
							/* translators: 1: Groups done, 2: All groups. */
							__( '%1$d of %2$d', 'ako-multilang' ),
							progress.processed,
							progress.total
						) }
					</span>
				</div>
				<div
					className="ako-progress"
					role="progressbar"
					aria-valuemin={ 0 }
					aria-valuemax={ 100 }
					aria-valuenow={ percent }
					aria-label={ __( 'Import progress', 'ako-multilang' ) }
				>
					<span
						className="ako-progress__value"
						style={ { inlineSize: `${ percent }%` } }
					/>
				</div>
				<p>
					{ __(
						'Reading translation groups. Keep this page open until it finishes.',
						'ako-multilang'
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
					__( 'Couldn’t read %s data', 'ako-multilang' ),
					source.name
				) }
				action={
					<Button onClick={ () => run( done, result ) }>
						{ __( 'Retry', 'ako-multilang' ) }
					</Button>
				}
			>
				{ error }{ ' ' }
				{ done
					? sprintf(
							/* translators: %d: Number of groups already imported. */
							_n(
								'%d group was imported before it stopped. Retry to finish. Links you already have are kept.',
								'%d groups were imported before it stopped. Retry to finish. Links you already have are kept.',
								done,
								'ako-multilang'
							),
							done
						)
					: __(
							'Nothing was changed. Retry, or skip this and link pages later.',
							'ako-multilang'
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
			'ako-multilang'
		);
		if ( skipped && allMissing ) {
			description = sprintf(
				/* translators: 1: Linked groups, 2: Skipped groups. */
				_n(
					'%1$d linked automatically. %2$d points to pages that no longer exist. Review it before you continue.',
					'%1$d linked automatically. %2$d point to pages that no longer exist. Review them before you continue.',
					skipped,
					'ako-multilang'
				),
				result.linked,
				skipped
			);
		} else if ( skipped ) {
			description = sprintf(
				/* translators: 1: Linked groups, 2: Skipped groups. */
				_n(
					'%1$d linked automatically. %2$d couldn’t be linked. Review it before you continue.',
					'%1$d linked automatically. %2$d couldn’t be linked. Review them before you continue.',
					skipped,
					'ako-multilang'
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
							'ako-multilang'
						),
						progress.processed
					) }
					action={
						skipped > 0 && (
							<Button onClick={ () => setReviewing( true ) }>
								{ sprintf(
									/* translators: %d: Number of groups to review. */
									__( 'Review %d', 'ako-multilang' ),
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
					__( '%s data found', 'ako-multilang' ),
					source.name
				) }
			>
				{ __(
					'It has no translated pages or posts for the content types Ako handles.',
					'ako-multilang'
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
					__( '%s links imported', 'ako-multilang' ),
					source.name
				) }
				action={
					<Button onClick={ start }>
						{ __( 'Import again', 'ako-multilang' ) }
					</Button>
				}
			>
				{ __(
					'Run it again to pick up pages added since. Links you already have stay as they are.',
					'ako-multilang'
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
					'Found %1$s data: %2$d translation group',
					'Found %1$s data: %2$d translation groups',
					source.groups,
					'ako-multilang'
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
							'ako-multilang'
						),
						source.groups
					) }
				</Button>
			}
		>
			{ sprintf(
				/* translators: %s: Plugin name, e.g. "WPML". */
				__(
					'Import them now. %s isn’t changed, so you can switch back anytime.',
					'ako-multilang'
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
					'ako-multilang'
				),
				groups.length
			) }
			description={ __(
				'Nothing was changed for these. If you still need them, link the pages from Translations or in the editor.',
				'ako-multilang'
			) }
			onCancel={ onClose }
		>
			<ul className="ako-review-list">
				{ groups.slice( 0, 50 ).map( ( group, index ) => (
					<li key={ index }>
						<span className="ako-review-list__pages" dir="auto">
							{ group.pages.length
								? group.pages
										.map( ( page ) => page.title )
										.join( ' · ' )
								: __( '(no pages left)', 'ako-multilang' ) }
						</span>
						<span className="ako-review-list__reason">
							{ ( REASONS[ group.reason ] || REASONS.error )() }
						</span>
					</li>
				) ) }
			</ul>
		</Dialog>
	);
}

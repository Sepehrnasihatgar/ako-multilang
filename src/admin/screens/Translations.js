/**
 * Translations screen (Figma: Admin screens / Daily work / Translations — *).
 */
import {
	useCallback,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import {
	Badge,
	Button,
	Checkbox,
	LanguageTag,
	Tabs,
	TextInput,
} from '../../components/controls';
import {
	CoverageCard,
	EmptyState,
	PageTitle,
	SkeletonRow,
} from '../../components/display';
import { Menu, usePopoverStyle } from '../../components/overlays';
import {
	duplicatePost,
	errorMessage,
	getCandidates,
	getImportSources,
	getLinks,
	linkPost,
	unlinkPost,
} from '../api';
import { ImportDialog, LinkPagesDialog } from '../setup/Dialogs';
import { candidateMeta, sortCandidates } from '../candidates';

const PER_PAGE = 20;
const DRAFT_STATUSES = [ 'draft', 'pending', 'future' ];

/**
 * Debounced copy of a value.
 *
 * @param {*}      value Value.
 * @param {number} delay Milliseconds.
 * @return {*} Value after the delay.
 */
function useDebounced( value, delay ) {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		const timer = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}

export default function Translations( { config, notify } ) {
	const [ query, setQuery ] = useState( () => {
		const hash = window.location.hash.replace( '#', '' );
		return {
			post_type: '',
			filter: [ 'missing', 'review', 'drafts' ].includes( hash )
				? hash
				: 'all',
			page: 1,
		};
	} );
	const [ searchText, setSearchText ] = useState( '' );
	const search = useDebounced( searchText.trim(), 300 );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( null );
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ linking, setLinking ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ duplicating, setDuplicating ] = useState( () => new Set() );
	const [ sources, setSources ] = useState( [] );
	const [ dialog, setDialog ] = useState( null );

	// Import sources (WPML / Polylang data) are only offered to site owners.
	useEffect( () => {
		if ( config.canManage ) {
			getImportSources()
				.then( setSources )
				.catch( () => {} );
		}
	}, [ config.canManage ] );

	const reload = useCallback( () => setReloadKey( ( key ) => key + 1 ), [] );

	// A new search starts from page 1.
	useEffect( () => {
		setQuery( ( current ) => ( { ...current, page: 1 } ) );
	}, [ search ] );

	useEffect( () => {
		let ignore = false;
		setLoading( true );
		setLoadError( null );

		getLinks( { ...query, search, per_page: PER_PAGE } )
			.then( ( response ) => {
				if ( ! ignore ) {
					setData( response );
				}
			} )
			.catch( ( error ) => {
				if ( ! ignore ) {
					setLoadError(
						errorMessage(
							error,
							__(
								'The list couldn’t be loaded.',
								'ako-multilang'
							)
						)
					);
				}
			} )
			.finally( () => {
				if ( ! ignore ) {
					setLoading( false );
				}
			} );

		return () => {
			ignore = true;
		};
	}, [ query, search, reloadKey ] );

	// Selection and open editors belong to one result page.
	useEffect( () => {
		setSelected( new Set() );
		setLinking( null );
	}, [ query, search ] );

	const languages = data ? data.languages : [];
	const byCode = Object.fromEntries(
		languages.map( ( language ) => [ language.code, language ] )
	);
	const source = data ? byCode[ data.default ] : null;
	const targets = data
		? data.target_codes.map( ( code ) => byCode[ code ] ).filter( Boolean )
		: [];
	const rows = data ? data.rows : [];
	const currentType = data
		? data.types.find( ( type ) => type.name === data.post_type )
		: null;

	const setFilter = ( filter ) =>
		setQuery( ( current ) => ( { ...current, filter, page: 1 } ) );
	const setType = ( postType ) =>
		setQuery( ( current ) => ( {
			...current,
			post_type: postType,
			page: 1,
		} ) );
	const setPage = ( page ) =>
		setQuery( ( current ) => ( { ...current, page } ) );

	/**
	 * Unlinks with an Undo that re-creates the same links.
	 *
	 * @param {Array}  jobs    [{ id, scope, restore: [ [ sourceId, targetId, code ] ] }].
	 * @param {string} message Toast message.
	 */
	const unlinkWithUndo = async ( jobs, message ) => {
		setBusy( true );
		try {
			for ( const job of jobs ) {
				await unlinkPost( job.id, job.scope );
			}
			notify( {
				tone: 'success',
				message,
				action: {
					label: __( 'Undo', 'ako-multilang' ),
					onClick: async () => {
						notify( {
							tone: 'info',
							message: __( 'Restoring links…', 'ako-multilang' ),
						} );
						try {
							for ( const job of jobs ) {
								for ( const [
									sourceId,
									targetId,
									code,
								] of job.restore ) {
									await linkPost( sourceId, targetId, code );
								}
							}
							notify( {
								tone: 'success',
								message: __(
									'Links restored.',
									'ako-multilang'
								),
							} );
						} catch ( error ) {
							notify( {
								tone: 'error',
								message: errorMessage(
									error,
									__(
										'The links couldn’t be restored.',
										'ako-multilang'
									)
								),
							} );
						}
						reload();
					},
				},
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Unlinking failed.', 'ako-multilang' )
				),
			} );
		}
		setSelected( new Set() );
		setBusy( false );
		reload();
	};

	const restoreAll = ( row ) =>
		Object.entries( row.translations ).map( ( [ code, translation ] ) => [
			row.id,
			translation.id,
			code,
		] );

	const unlinkRow = ( row ) =>
		unlinkWithUndo(
			[ { id: row.id, scope: 'group', restore: restoreAll( row ) } ],
			sprintf(
				/* translators: %s: Page title. */
				__( 'Unlinked the translations of “%s”.', 'ako-multilang' ),
				row.title
			)
		);

	const unlinkTranslation = ( row, code ) => {
		const translation = row.translations[ code ];
		unlinkWithUndo(
			[
				{
					id: translation.id,
					scope: 'post',
					restore: [ [ row.id, translation.id, code ] ],
				},
			],
			sprintf(
				/* translators: 1: Translation title, 2: Page title. */
				__( 'Unlinked “%1$s” from “%2$s”.', 'ako-multilang' ),
				translation.title,
				row.title
			)
		);
	};

	/**
	 * Creates linked drafts for [ row, code ] pairs, one request at a time.
	 *
	 * @param {Array} pairs [ [ row, code ] ].
	 */
	const duplicatePairs = async ( pairs ) => {
		const keys = pairs.map( ( [ row, code ] ) => `${ row.id }:${ code }` );
		setBusy( true );
		setDuplicating( new Set( keys ) );
		let created = 0;
		let lastError = null;
		let lastDraft = null;
		for ( const [ row, code ] of pairs ) {
			try {
				const response = await duplicatePost( row.id, code );
				lastDraft = response.created;
				created++;
			} catch ( error ) {
				lastError = errorMessage(
					error,
					__( 'A draft couldn’t be created.', 'ako-multilang' )
				);
			}
		}
		setDuplicating( new Set() );
		setBusy( false );
		setSelected( new Set() );
		if ( lastError && ! created ) {
			notify( { tone: 'error', message: lastError } );
		} else if ( lastError ) {
			notify( {
				tone: 'error',
				message: sprintf(
					/* translators: 1: Drafts created, 2: Drafts requested. */
					__(
						'Created %1$d of %2$d drafts. Try the rest again.',
						'ako-multilang'
					),
					created,
					pairs.length
				),
			} );
		} else {
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %d: Number of drafts. */
					_n(
						'Created %d draft.',
						'Created %d drafts.',
						created,
						'ako-multilang'
					),
					created
				),
				action:
					1 === created && lastDraft && lastDraft.editUrl
						? {
								label: __( 'Edit draft', 'ako-multilang' ),
								url: lastDraft.editUrl,
							}
						: null,
			} );
		}
		reload();
	};

	const missingInSelection = rows
		.filter( ( row ) => selected.has( row.id ) && row.canEdit )
		.flatMap( ( row ) =>
			targets
				.filter( ( language ) => ! row.translations[ language.code ] )
				.map( ( language ) => [ row, language.code ] )
		);

	const unlinkSelected = () => {
		const chosen = rows.filter(
			( row ) =>
				selected.has( row.id ) && Object.keys( row.translations ).length
		);
		if ( ! chosen.length ) {
			notify( {
				tone: 'info',
				message: __(
					'The selected pages have no translations to unlink.',
					'ako-multilang'
				),
			} );
			return;
		}
		unlinkWithUndo(
			chosen.map( ( row ) => ( {
				id: row.id,
				scope: 'group',
				restore: restoreAll( row ),
			} ) ),
			sprintf(
				/* translators: %d: Number of pages. */
				_n(
					'Unlinked %d page.',
					'Unlinked %d pages.',
					chosen.length,
					'ako-multilang'
				),
				chosen.length
			)
		);
	};

	const onLinked = ( row, code, candidate ) => {
		setLinking( null );
		notify( {
			tone: 'success',
			message: sprintf(
				/* translators: 1: Page title, 2: Language name, 3: Page title. */
				__(
					'Linked “%1$s” as the %2$s version of “%3$s”.',
					'ako-multilang'
				),
				candidate.title,
				byCode[ code ].name,
				row.title
			),
		} );
		reload();
	};

	const toggleRow = ( id, on ) =>
		setSelected( ( current ) => {
			const next = new Set( current );
			if ( on ) {
				next.add( id );
			} else {
				next.delete( id );
			}
			return next;
		} );

	const allOnPage =
		rows.length > 0 && rows.every( ( row ) => selected.has( row.id ) );
	const someOnPage = rows.some( ( row ) => selected.has( row.id ) );
	const toggleAll = ( on ) =>
		setSelected(
			on ? new Set( rows.map( ( row ) => row.id ) ) : new Set()
		);

	const canLink = config.canManage && !! data && targets.length > 0;
	const canImport = sources.length > 0;
	const firstRun =
		!! data &&
		targets.length > 0 &&
		'all' === query.filter &&
		! search &&
		targets.every( ( language ) => ! data.coverage[ language.code ] );

	const columns = `var(--ako-checkbox-size) minmax(200px, 384fr) repeat(${ Math.max(
		targets.length,
		1
	) }, minmax(200px, 260fr)) 96px 60px`;

	return (
		<>
			<PageTitle
				title={ __( 'Translations', 'ako-multilang' ) }
				description={ __(
					'Every page, in every language.',
					'ako-multilang'
				) }
				actions={
					canLink && (
						<>
							{ canImport && (
								<Button
									icon="arrows-left-right"
									onClick={ () => setDialog( 'import' ) }
								>
									{ __( 'Import', 'ako-multilang' ) }
								</Button>
							) }
							<Button
								variant="primary"
								icon="link"
								onClick={ () => setDialog( 'link' ) }
							>
								{ __( 'Link pages', 'ako-multilang' ) }
							</Button>
						</>
					)
				}
			/>

			{ firstRun && (
				<div className="ako-card ako-first-run">
					<EmptyState
						icon="link"
						title={ __(
							'No pages are linked yet',
							'ako-multilang'
						) }
						description={ __(
							'Link a page to its translation, and Ako adds hreflang, sets lang and dir, and shows the switcher for you.',
							'ako-multilang'
						) }
						actions={
							canLink && (
								<>
									{ canImport && (
										<Button
											variant="ghost"
											onClick={ () =>
												setDialog( 'import' )
											}
										>
											{ __(
												'Import from WPML or Polylang',
												'ako-multilang'
											) }
										</Button>
									) }
									<Button
										variant="primary"
										onClick={ () => setDialog( 'link' ) }
									>
										{ __( 'Link pages', 'ako-multilang' ) }
									</Button>
								</>
							)
						}
					/>
				</div>
			) }

			{ 'import' === dialog && (
				<ImportDialog
					sources={ sources }
					onClose={ () => {
						setDialog( null );
						reload();
					} }
				/>
			) }
			{ 'link' === dialog && (
				<LinkPagesDialog
					defaultCode={ data.default }
					notify={ notify }
					onClose={ () => setDialog( null ) }
					onLinked={ () => {
						setDialog( null );
						reload();
					} }
				/>
			) }

			{ data && source && targets.length > 0 && (
				<section
					className="ako-overview"
					aria-label={ __( 'Coverage', 'ako-multilang' ) }
				>
					<CoverageCard
						source
						language={ source }
						value={ data.counts.all - data.counts.missing }
						total={ data.counts.all }
						caption={ sprintf(
							/* translators: %d: Number of pages. */
							_n(
								'%d page',
								'%d pages',
								data.source_total,
								'ako-multilang'
							),
							data.source_total
						) }
					/>
					{ targets.map( ( language ) => (
						<CoverageCard
							key={ language.code }
							language={ language }
							value={ data.coverage[ language.code ] || 0 }
							total={ data.source_total }
							caption={ sprintf(
								/* translators: 1: Linked pages, 2: All pages. */
								__( '%1$d of %2$d linked', 'ako-multilang' ),
								data.coverage[ language.code ] || 0,
								data.source_total
							) }
						/>
					) ) }
					{ data.health && (
						<HealthCard
							health={ data.health }
							url={ config.urls.seo }
						/>
					) }
				</section>
			) }

			<section
				className="ako-table"
				aria-label={ __( 'Translations', 'ako-multilang' ) }
				style={ { '--ako-table-columns': columns } }
			>
				{ selected.size > 0 ? (
					<div className="ako-bulkbar">
						<Checkbox
							checked={ allOnPage }
							indeterminate={ ! allOnPage }
							label={ __( 'Clear selection', 'ako-multilang' ) }
							onChange={ () => setSelected( new Set() ) }
						/>
						<span className="ako-bulkbar__count" aria-live="polite">
							{ sprintf(
								/* translators: %d: Number of selected pages. */
								_n(
									'%d selected',
									'%d selected',
									selected.size,
									'ako-multilang'
								),
								selected.size
							) }
							{ missingInSelection.length > 0 &&
								' · ' +
									sprintf(
										/* translators: %d: Number of missing translations. */
										_n(
											'%d missing translation',
											'%d missing translations',
											missingInSelection.length,
											'ako-multilang'
										),
										missingInSelection.length
									) }
						</span>
						<span className="ako-bulkbar__spacer" />
						<Button
							size="small"
							icon="file-plus"
							onClick={ () =>
								duplicatePairs( missingInSelection )
							}
							disabled={ busy || ! missingInSelection.length }
						>
							{ __(
								'Duplicate missing as drafts',
								'ako-multilang'
							) }
						</Button>
						<Button
							size="small"
							icon="close"
							onClick={ unlinkSelected }
							disabled={ busy }
						>
							{ __( 'Unlink', 'ako-multilang' ) }
						</Button>
						<button
							type="button"
							className="ako-bulkbar__clear"
							onClick={ () => setSelected( new Set() ) }
						>
							{ __( 'Clear', 'ako-multilang' ) }
						</button>
					</div>
				) : (
					<div className="ako-toolbar">
						<Tabs
							label={ __( 'Filter', 'ako-multilang' ) }
							value={ query.filter }
							onChange={ setFilter }
							items={ [
								{
									value: 'all',
									label: __( 'All', 'ako-multilang' ),
									count: data?.counts.all,
								},
								{
									value: 'missing',
									label: __( 'Missing', 'ako-multilang' ),
									count: data?.counts.missing,
								},
								{
									value: 'review',
									label: __(
										'Needs review',
										'ako-multilang'
									),
									count: data?.counts.review,
								},
								{
									value: 'drafts',
									label: __( 'Drafts', 'ako-multilang' ),
									count: data?.counts.drafts,
								},
							] }
						/>
						<div className="ako-toolbar__end">
							{ data && (
								<Menu
									label={ __(
										'Content type',
										'ako-multilang'
									) }
									renderTrigger={ ( props ) => (
										<button
											type="button"
											className="ako-input is-select is-trigger"
											{ ...props }
										>
											<span>
												{ currentType
													? sprintf(
															/* translators: 1: Content type, e.g. "Pages". 2: Count. */
															__(
																'%1$s · %2$d',
																'ako-multilang'
															),
															currentType.label,
															currentType.count
														)
													: data.post_type }
											</span>
											<Icon name="chevron-down" />
										</button>
									) }
									items={ data.types.map( ( type ) => ( {
										id: type.name,
										label: type.label,
										meta: type.enabled
											? String( type.count )
											: __(
													'Off. Turn it on in Settings.',
													'ako-multilang'
												),
										checked: type.name === data.post_type,
										disabled: ! type.enabled,
										onSelect: () => setType( type.name ),
									} ) ) }
								/>
							) }
							<TextInput
								search
								className="ako-toolbar__search"
								value={ searchText }
								onChange={ ( event ) =>
									setSearchText( event.target.value )
								}
								placeholder={ __(
									'Search titles or slugs…',
									'ako-multilang'
								) }
								aria-label={ __(
									'Search titles or slugs',
									'ako-multilang'
								) }
							/>
						</div>
					</div>
				) }

				<div className="ako-table__scroll">
					<div
						className="ako-grid"
						role="table"
						aria-label={ __(
							'Pages and their translations',
							'ako-multilang'
						) }
						aria-busy={ loading }
						aria-rowcount={ data ? data.total + 1 : -1 }
					>
						<div className="ako-row is-header" role="row">
							<span role="columnheader">
								<Checkbox
									checked={ allOnPage }
									indeterminate={ someOnPage && ! allOnPage }
									label={ __(
										'Select all on this page',
										'ako-multilang'
									) }
									onChange={ toggleAll }
									disabled={ ! rows.length }
								/>
							</span>
							<span role="columnheader" className="ako-overline">
								{ source
									? sprintf(
											/* translators: 1: Content type, e.g. "Page". 2: Language name. */
											__(
												'%1$s · %2$s (source)',
												'ako-multilang'
											),
											data.post_type_tag,
											source.name
										)
									: __( 'Page', 'ako-multilang' ) }
							</span>
							{ targets.map( ( language ) => (
								<span role="columnheader" key={ language.code }>
									<LanguageTag
										code={ language.code }
										name={ language.native }
									/>
								</span>
							) ) }
							<span role="columnheader" className="ako-overline">
								{ __( 'Updated', 'ako-multilang' ) }
							</span>
							<span role="columnheader">
								<span className="screen-reader-text">
									{ __( 'Actions', 'ako-multilang' ) }
								</span>
							</span>
						</div>

						<Rows
							data={ data }
							loading={ loading }
							loadError={ loadError }
							rows={ rows }
							targets={ targets }
							search={ search }
							filter={ query.filter }
							config={ config }
							selected={ selected }
							linking={ linking }
							setLinking={ setLinking }
							toggleRow={ toggleRow }
							onLinked={ onLinked }
							onUnlinkRow={ unlinkRow }
							onUnlinkTranslation={ unlinkTranslation }
							duplicating={ duplicating }
							onDuplicate={ ( row, code ) =>
								duplicatePairs( [ [ row, code ] ] )
							}
							busy={ busy }
							onClearSearch={ () => setSearchText( '' ) }
							onRetry={ reload }
							notify={ notify }
						/>
					</div>
				</div>

				{ data && data.total > 0 && (
					<div className="ako-table__footer">
						<span>
							{ 'review' === query.filter &&
								__(
									'One-way: a translation doesn’t link back. Fix both sides on the SEO screen.',
									'ako-multilang'
								) }
						</span>
						<span>
							{ sprintf(
								/* translators: 1: First row number, 2: Last row number, 3: Total rows. */
								__(
									'Showing %1$d to %2$d of %3$d',
									'ako-multilang'
								),
								( data.page - 1 ) * PER_PAGE + 1,
								( data.page - 1 ) * PER_PAGE + rows.length,
								data.total
							) }
						</span>
						<span className="ako-table__pager">
							<Button
								size="small"
								onClick={ () => setPage( data.page - 1 ) }
								disabled={ data.page <= 1 || loading }
							>
								{ __( 'Previous', 'ako-multilang' ) }
							</Button>
							<Button
								size="small"
								onClick={ () => setPage( data.page + 1 ) }
								disabled={
									data.page >= data.total_pages || loading
								}
							>
								{ __( 'Next', 'ako-multilang' ) }
							</Button>
						</span>
					</div>
				) }
			</section>
		</>
	);
}

/**
 * Table body: skeleton, empty states or rows.
 *
 * @param {Object} props See Translations.
 */
function Rows( props ) {
	const { data, loading, loadError, rows, targets, search, filter, config } =
		props;

	if ( loading ) {
		return Array.from( { length: 6 } ).map( ( unused, index ) => (
			<SkeletonRow
				key={ index }
				columns={ Math.max( targets.length, 1 ) }
			/>
		) );
	}

	let empty = null;
	if ( loadError ) {
		empty = (
			<EmptyState
				icon="alert"
				title={ __( 'Couldn’t load translations', 'ako-multilang' ) }
				description={ loadError }
				actions={
					<Button variant="primary" onClick={ props.onRetry }>
						{ __( 'Try again', 'ako-multilang' ) }
					</Button>
				}
			/>
		);
	} else if ( data && ! targets.length ) {
		empty = (
			<EmptyState
				icon="plus"
				title={ __( 'Add a second language', 'ako-multilang' ) }
				description={
					config.canManage
						? __(
								'Translations show up here once your site has at least two languages.',
								'ako-multilang'
							)
						: __(
								'Translations show up here once a site administrator adds a second language.',
								'ako-multilang'
							)
				}
				actions={
					config.canManage &&
					config.urls.languages && (
						<Button
							variant="primary"
							icon="plus"
							href={ config.urls.languages }
						>
							{ __( 'Add language', 'ako-multilang' ) }
						</Button>
					)
				}
			/>
		);
	} else if ( data && ! rows.length ) {
		if ( search ) {
			let title;
			if ( 'page' === data.post_type ) {
				title = sprintf(
					/* translators: %s: Search text. */
					__( 'No pages match “%s”', 'ako-multilang' ),
					search
				);
			} else if ( 'post' === data.post_type ) {
				title = sprintf(
					/* translators: %s: Search text. */
					__( 'No posts match “%s”', 'ako-multilang' ),
					search
				);
			} else {
				title = sprintf(
					/* translators: %s: Search text. */
					__( 'Nothing matches “%s”', 'ako-multilang' ),
					search
				);
			}
			empty = (
				<EmptyState
					icon="search"
					title={ title }
					description={ __(
						'Check the spelling, or search in another content type.',
						'ako-multilang'
					) }
					actions={
						<Button
							variant="primary"
							onClick={ props.onClearSearch }
						>
							{ __( 'Clear search', 'ako-multilang' ) }
						</Button>
					}
				/>
			);
		} else if ( 'missing' === filter ) {
			empty = (
				<EmptyState
					icon="circle-check"
					title={ __( 'Nothing is missing', 'ako-multilang' ) }
					description={ __(
						'Every page here has a translation in every language.',
						'ako-multilang'
					) }
				/>
			);
		} else if ( 'drafts' === filter ) {
			empty = (
				<EmptyState
					icon="circle-check"
					title={ __( 'No drafts', 'ako-multilang' ) }
					description={ __(
						'Every page and translation here is published.',
						'ako-multilang'
					) }
				/>
			);
		} else {
			empty = (
				<EmptyState
					icon="file-plus"
					title={ __( 'Nothing to translate yet', 'ako-multilang' ) }
					description={ __(
						'Content you write in your default language shows up here, ready to link.',
						'ako-multilang'
					) }
				/>
			);
		}
	}

	if ( empty ) {
		return (
			<div role="row" className="ako-row is-empty">
				<div role="cell">{ empty }</div>
			</div>
		);
	}

	return rows.map( ( row ) => (
		<Row key={ row.id } row={ row } { ...props } />
	) );
}

/**
 * One source page and its translations.
 *
 * @param {Object}   props
 * @param {Object}   props.row
 * @param {Array}    props.targets
 * @param {Object}   props.selected
 * @param {Object}   props.linking
 * @param {Function} props.setLinking
 * @param {Function} props.toggleRow
 * @param {Function} props.onLinked
 * @param {Function} props.onUnlinkRow
 * @param {Function} props.onUnlinkTranslation
 * @param {Function} props.notify
 * @param {Object}   props.data
 * @param {Object}   props.duplicating
 * @param {Function} props.onDuplicate
 * @param {boolean}  props.busy
 */
function Row( {
	row,
	targets,
	selected,
	linking,
	setLinking,
	toggleRow,
	onLinked,
	onUnlinkRow,
	onUnlinkTranslation,
	notify,
	data,
	duplicating,
	onDuplicate,
	busy,
} ) {
	const isSelected = selected.has( row.id );
	const linkedCodes = Object.keys( row.translations );

	const moreItems = [];
	if ( row.editUrl ) {
		moreItems.push( {
			id: 'edit',
			label: __( 'Edit page', 'ako-multilang' ),
			onSelect: () => window.location.assign( row.editUrl ),
		} );
	}
	targets.forEach( ( language ) => {
		const translation = row.translations[ language.code ];
		if ( translation && translation.canEdit ) {
			moreItems.push( {
				id: `unlink-${ language.code }`,
				label: sprintf(
					/* translators: %s: Language name in that language, e.g. "فارسی". */
					__( 'Unlink %s', 'ako-multilang' ),
					language.native
				),
				onSelect: () => onUnlinkTranslation( row, language.code ),
			} );
		}
	} );
	if ( linkedCodes.length > 1 ) {
		moreItems.push( {
			id: 'unlink-all',
			label: __( 'Unlink all translations', 'ako-multilang' ),
			onSelect: () => onUnlinkRow( row ),
		} );
	}

	return (
		<div
			role="row"
			className={ `ako-row ${ isSelected ? 'is-selected' : '' }` }
		>
			<span role="cell">
				<Checkbox
					checked={ isSelected }
					label={ sprintf(
						/* translators: %s: Page title. */
						__( 'Select “%s”', 'ako-multilang' ),
						row.title
					) }
					onChange={ ( on ) => toggleRow( row.id, on ) }
				/>
			</span>
			<span role="cell" className="ako-row__page">
				<span className="ako-row__title">
					<span dir="auto">{ row.title }</span>
					{ DRAFT_STATUSES.includes( row.status ) && (
						<Badge tone="draft">
							{ __( 'Draft', 'ako-multilang' ) }
						</Badge>
					) }
				</span>
				<span className="ako-row__slug" dir="ltr">
					{ row.path }
				</span>
			</span>
			{ targets.map( ( language ) => (
				<span role="cell" key={ language.code }>
					<TranslationCell
						row={ row }
						language={ language }
						postType={ data.post_type }
						translation={ row.translations[ language.code ] }
						isLinking={
							linking &&
							linking.rowId === row.id &&
							linking.code === language.code
						}
						onStartLinking={ () =>
							setLinking( { rowId: row.id, code: language.code } )
						}
						onCancelLinking={ () => setLinking( null ) }
						onLinked={ ( candidate ) =>
							onLinked( row, language.code, candidate )
						}
						isDuplicating={ duplicating.has(
							`${ row.id }:${ language.code }`
						) }
						onDuplicate={ () => onDuplicate( row, language.code ) }
						busy={ busy }
						notify={ notify }
					/>
				</span>
			) ) }
			<span role="cell" className="ako-row__date">
				{ row.modified }
			</span>
			<span role="cell" className="ako-row__actions">
				{ row.viewUrl && (
					<Button
						variant="ghost"
						size="small"
						icon="external-link"
						href={ row.viewUrl }
						target="_blank"
						rel="noopener noreferrer"
						aria-label={ sprintf(
							/* translators: %s: Page title. */
							__(
								'View “%s” (opens in a new tab)',
								'ako-multilang'
							),
							row.title
						) }
					/>
				) }
				{ moreItems.length > 0 && (
					<Menu
						align="end"
						label={ sprintf(
							/* translators: %s: Page title. */
							__( 'Actions for “%s”', 'ako-multilang' ),
							row.title
						) }
						renderTrigger={ ( props ) => (
							<Button
								variant="ghost"
								size="small"
								icon="more"
								aria-label={ sprintf(
									/* translators: %s: Page title. */
									__(
										'More actions for “%s”',
										'ako-multilang'
									),
									row.title
								) }
								{ ...props }
							/>
						) }
						items={ moreItems }
					/>
				) }
			</span>
		</div>
	);
}

/**
 * Translation cell. Status=Linked|Draft|Missing, plus the inline Linking state.
 *
 * @param {Object}   props
 * @param {Object}   props.row
 * @param {Object}   props.language
 * @param {string}   props.postType
 * @param {Object}   props.translation
 * @param {boolean}  props.isLinking
 * @param {Function} props.onStartLinking
 * @param {Function} props.onCancelLinking
 * @param {Function} props.onLinked
 * @param {Function} props.notify
 * @param {boolean}  props.isDuplicating
 * @param {Function} props.onDuplicate
 * @param {boolean}  props.busy
 */
function TranslationCell( {
	row,
	language,
	postType,
	translation,
	isLinking,
	onStartLinking,
	onCancelLinking,
	onLinked,
	notify,
	isDuplicating,
	onDuplicate,
	busy,
} ) {
	if ( isDuplicating ) {
		return (
			<span className="ako-cell is-working" aria-live="polite">
				<span className="ako-cell__dot" aria-hidden="true" />
				{ __( 'Duplicating…', 'ako-multilang' ) }
			</span>
		);
	}

	if ( isLinking ) {
		return (
			<LinkSearch
				row={ row }
				language={ language }
				postType={ postType }
				onCancel={ onCancelLinking }
				onLinked={ onLinked }
				notify={ notify }
			/>
		);
	}

	if ( ! translation ) {
		if ( ! row.canEdit ) {
			return (
				<span className="ako-cell is-empty">
					{ __( 'Not translated', 'ako-multilang' ) }
				</span>
			);
		}
		return (
			<Menu
				variant="rich"
				label={ sprintf(
					/* translators: %s: Language name. */
					__( 'Add a %s translation', 'ako-multilang' ),
					language.name
				) }
				renderTrigger={ ( props ) => (
					<button
						type="button"
						className="ako-cell is-missing"
						aria-label={ sprintf(
							/* translators: 1: Language name, 2: Page title. Starts with the visible "Add translation" label. */
							__(
								'Add translation: %1$s, for “%2$s”',
								'ako-multilang'
							),
							language.name,
							row.title
						) }
						{ ...props }
					>
						<Icon name="plus" size={ 14 } />
						<span>
							{ __( 'Add translation', 'ako-multilang' ) }
						</span>
					</button>
				) }
				items={ [
					{
						id: 'duplicate',
						icon: 'file-plus',
						label: __( 'Duplicate as draft', 'ako-multilang' ),
						description: __(
							'Copies the content, images, SEO fields, and builder layout.',
							'ako-multilang'
						),
						disabled: busy,
						onSelect: onDuplicate,
					},
					{
						id: 'link',
						icon: 'link',
						label: __( 'Link an existing page', 'ako-multilang' ),
						description: __(
							'Search pages in this language and connect one.',
							'ako-multilang'
						),
						onSelect: onStartLinking,
					},
				] }
			/>
		);
	}

	const isDraft = DRAFT_STATUSES.includes( translation.status );
	const isReview = !! row.review;
	const title = translation.editUrl ? (
		<a
			href={ translation.editUrl }
			dir="auto"
			lang={ language.code }
			className="ako-cell__title"
		>
			{ translation.title }
		</a>
	) : (
		<span dir="auto" lang={ language.code } className="ako-cell__title">
			{ translation.title }
		</span>
	);

	return (
		<span className={ `ako-cell ${ isDraft ? 'is-draft' : 'is-linked' }` }>
			{ isReview && (
				<Icon
					name="alert"
					className="ako-cell__warn"
					label={ __( 'Needs review', 'ako-multilang' ) }
				/>
			) }
			{ ! isReview && isDraft && (
				<span className="ako-cell__dot" aria-hidden="true" />
			) }
			{ ! isReview && ! isDraft && (
				<Icon name="circle-check" className="ako-cell__check" />
			) }
			{ title }
			{ isReview && (
				<span className="ako-cell__badge">
					{ __( 'One-way', 'ako-multilang' ) }
				</span>
			) }
			{ isDraft && (
				<span className="ako-cell__badge">
					{ __( 'Draft', 'ako-multilang' ) }
				</span>
			) }
		</span>
	);
}

/**
 * Inline combobox to pick an existing page as the translation.
 *
 * @param {Object}   props
 * @param {Object}   props.row
 * @param {Object}   props.language
 * @param {string}   props.postType
 * @param {Function} props.onCancel
 * @param {Function} props.onLinked
 * @param {Function} props.notify
 */
function LinkSearch( { row, language, postType, onCancel, onLinked, notify } ) {
	const [ text, setText ] = useState( '' );
	const term = useDebounced( text.trim(), 250 );
	const [ results, setResults ] = useState( null );
	const [ active, setActive ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const inputRef = useRef();
	const wrapRef = useRef();
	const listRef = useRef();
	const listStyle = usePopoverStyle( wrapRef, listRef, !! results );
	const listId = useId();

	useEffect( () => {
		inputRef.current.focus();
	}, [] );

	useEffect( () => {
		let ignore = false;
		getCandidates( {
			post_type: postType,
			language: language.code,
			search: term,
			skip: row.id,
		} )
			.then( ( list ) => {
				if ( ! ignore ) {
					const sorted = sortCandidates( list );
					setResults( sorted );
					setActive(
						Math.max(
							0,
							sorted.findIndex(
								( candidate ) => ! candidate.linked
							)
						)
					);
				}
			} )
			.catch( () => ! ignore && setResults( [] ) );
		return () => {
			ignore = true;
		};
	}, [ term, postType, language.code, row.id ] );

	useEffect( () => {
		const onPointer = ( event ) => {
			if (
				wrapRef.current &&
				! wrapRef.current.contains( event.target )
			) {
				onCancel();
			}
		};
		document.addEventListener( 'mousedown', onPointer );
		return () => document.removeEventListener( 'mousedown', onPointer );
	}, [ onCancel ] );

	const choose = async ( candidate ) => {
		if ( ! candidate || candidate.linked || saving ) {
			return;
		}
		setSaving( true );
		try {
			await linkPost( row.id, candidate.id, language.code );
			onLinked( candidate );
		} catch ( error ) {
			setSaving( false );
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Those pages couldn’t be linked.', 'ako-multilang' )
				),
			} );
		}
	};

	const onKeyDown = ( event ) => {
		const count = results ? results.length : 0;
		if ( 'ArrowDown' === event.key && count ) {
			event.preventDefault();
			setActive( ( active + 1 ) % count );
		} else if ( 'ArrowUp' === event.key && count ) {
			event.preventDefault();
			setActive( ( active - 1 + count ) % count );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			choose( results && results[ active ] );
		} else if ( 'Escape' === event.key ) {
			event.preventDefault();
			onCancel();
		}
	};

	const optionId = ( index ) => `${ listId }-${ index }`;

	return (
		<div className="ako-link-search" ref={ wrapRef }>
			<TextInput
				search
				ref={ inputRef }
				value={ text }
				onChange={ ( event ) => setText( event.target.value ) }
				onKeyDown={ onKeyDown }
				role="combobox"
				aria-expanded={ !! results }
				aria-controls={ listId }
				aria-autocomplete="list"
				aria-activedescendant={
					results && results.length ? optionId( active ) : undefined
				}
				aria-busy={ saving || undefined }
				placeholder={ sprintf(
					/* translators: %s: Language name in that language. */
					__( 'Search %s pages…', 'ako-multilang' ),
					language.native
				) }
				aria-label={ sprintf(
					/* translators: %s: Language name. */
					__( 'Search %s pages to link', 'ako-multilang' ),
					language.name
				) }
			/>
			<ul
				ref={ listRef }
				style={ listStyle }
				hidden={ ! results }
				className="ako-menu is-list ako-link-search__list"
				id={ listId }
				role="listbox"
				aria-label={ __( 'Pages', 'ako-multilang' ) }
			>
				{ results && ! results.length && (
					<li className="ako-menu__empty" role="presentation">
						{ __(
							'No pages found. Pages already in another language aren’t listed.',
							'ako-multilang'
						) }
					</li>
				) }
				{ results &&
					results.map( ( candidate, index ) => (
						// Keyboard interaction lives on the combobox input (aria-activedescendant).
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events
						<li
							key={ candidate.id }
							id={ optionId( index ) }
							role="option"
							aria-selected={ index === active }
							aria-disabled={ candidate.linked || undefined }
							className={ `ako-menu__item ${ index === active ? 'is-active' : '' } ${ candidate.linked ? 'is-disabled' : '' }` }
							onMouseEnter={ () => setActive( index ) }
							onMouseDown={ ( event ) => event.preventDefault() }
							onClick={ () => choose( candidate ) }
						>
							<span className="ako-menu__label" dir="auto">
								{ candidate.title }
							</span>
							<span className="ako-menu__meta">
								{ candidateMeta( candidate ) }
							</span>
						</li>
					) ) }
			</ul>
		</div>
	);
}

/**
 * SEO health card (Figma: Data display / SEO health card).
 *
 * @param {Object} props
 * @param {Object} props.health { score, oneWay }.
 * @param {string} props.url    SEO screen URL.
 */
function HealthCard( { health, url } ) {
	const hasScore = null !== health.score;
	let caption = __( 'hreflang starts after the first link', 'ako-multilang' );
	if ( hasScore ) {
		caption = health.oneWay
			? sprintf(
					/* translators: %d: Number of one-way links. */
					_n(
						'%d one-way link',
						'%d one-way links',
						health.oneWay,
						'ako-multilang'
					),
					health.oneWay
				)
			: __( 'All links are reciprocal', 'ako-multilang' );
	}

	return (
		<div className="ako-health-card">
			<div className="ako-health-card__top">
				<span className="ako-health-card__title">
					<Icon name="circle-check" />
					{ __( 'SEO health', 'ako-multilang' ) }
				</span>
				<span className="ako-health-card__value">
					{ hasScore
						? sprintf(
								/* translators: %d: Percentage. */
								__( '%d%%', 'ako-multilang' ),
								health.score
							)
						: __( 'None yet', 'ako-multilang' ) }
				</span>
			</div>
			<div
				className="ako-progress is-on-brand"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ hasScore ? health.score : 0 }
				aria-label={ __( 'SEO health', 'ako-multilang' ) }
			>
				<span
					className="ako-progress__value"
					style={ {
						inlineSize: `${ hasScore ? health.score : 0 }%`,
					} }
				/>
			</div>
			<div className="ako-health-card__foot">
				<span>{ caption }</span>
				{ url && (
					<a href={ url }>
						{ __( 'View SEO', 'ako-multilang' ) }
						<Icon name="external-link" size={ 12 } />
					</a>
				) }
			</div>
		</div>
	);
}

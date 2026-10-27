/**
 * "Language · Ako" panel (Figma: Editor patterns / Editor language panel,
 * Translation card). Shared by the block-editor sidebar and the classic
 * metabox, so both editors behave the same.
 */
import {
	useCallback,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import Icon from '../components/Icon';
import {
	Badge,
	Button,
	Field,
	LanguageTag,
	Select,
	TextInput,
} from '../components/controls';
import {
	duplicatePost,
	errorMessage,
	getCandidates,
	getDetail,
	linkPost,
	setPostLanguage,
	unlinkPost,
} from '../admin/api';
import { candidateMeta, sortCandidates } from '../admin/candidates';

const STATUS = {
	publish: [ 'linked', () => __( 'Published', 'ako-multilang' ) ],
	future: [ 'info', () => __( 'Scheduled', 'ako-multilang' ) ],
	draft: [ 'draft', () => __( 'Draft', 'ako-multilang' ) ],
	pending: [ 'draft', () => __( 'Pending', 'ako-multilang' ) ],
	private: [ 'draft', () => __( 'Private', 'ako-multilang' ) ],
};

/**
 * Status badge for a post.
 *
 * @param {Object} props
 * @param {string} props.status Post status.
 */
function StatusBadge( { status } ) {
	const [ tone, label ] = STATUS[ status ] || STATUS.draft;
	return (
		<Badge tone={ tone } dot>
			{ label() }
		</Badge>
	);
}

/**
 * @param {Object}   props
 * @param {number}   props.postId      Post being edited.
 * @param {number}   props.reloadKey   Changes when the post was saved.
 * @param {Function} props.notify      ( { tone, message, action } ) => void.
 * @param {Function} props.onDirection Called with "ltr" or "rtl" for the page language (block editor only).
 */
export default function LanguagePanel( {
	postId,
	reloadKey,
	notify,
	onDirection,
} ) {
	const [ detail, setDetail ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ working, setWorking ] = useState( null );
	const [ linking, setLinking ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const load = useCallback( () => {
		setLoadError( null );
		getDetail( postId )
			.then( setDetail )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'Translations couldn’t be loaded.',
							'ako-multilang'
						)
					)
				)
			);
	}, [ postId ] );

	useEffect( load, [ load, reloadKey ] );

	// Tell the editor which way this page's language is written.
	const pageCode = detail ? detail.language || detail.default : '';
	const pageLanguage = detail
		? detail.languages.find( ( entry ) => entry.code === pageCode )
		: null;
	const dir = pageLanguage ? pageLanguage.dir : '';
	useEffect( () => {
		if ( onDirection && dir ) {
			onDirection( dir );
		}
	}, [ onDirection, dir ] );

	if ( loadError ) {
		return (
			<div className="ako-ml ako-lp">
				<p className="ako-lp__note is-error">{ loadError }</p>
				<Button size="small" onClick={ load }>
					{ __( 'Try again', 'ako-multilang' ) }
				</Button>
			</div>
		);
	}

	if ( ! detail ) {
		return (
			<div className="ako-ml ako-lp" aria-busy="true">
				<span className="ako-skeleton is-title" />
			</div>
		);
	}

	const others = detail.languages.filter(
		( language ) => language.code !== detail.language
	);
	const linkedCount = others.filter(
		( language ) => detail.translations[ language.code ]
	).length;

	const changeLanguage = async ( code ) => {
		setSaving( true );
		try {
			setDetail( await setPostLanguage( postId, code ) );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The language couldn’t be changed.', 'ako-multilang' )
				),
			} );
		}
		setSaving( false );
	};

	const duplicate = async ( language ) => {
		setWorking( language.code );
		try {
			const response = await duplicatePost( postId, language.code );
			setDetail( response.detail );
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Language name. */
					__( '%s draft created.', 'ako-multilang' ),
					language.name
				),
				action: response.created.editUrl
					? {
							label: __( 'Edit draft', 'ako-multilang' ),
							url: response.created.editUrl,
						}
					: null,
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The draft couldn’t be created.', 'ako-multilang' )
				),
			} );
		}
		setWorking( null );
	};

	const unlink = async ( language, translation ) => {
		try {
			await unlinkPost( translation.id, 'post' );
			load();
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Language name. */
					__( '%s translation unlinked.', 'ako-multilang' ),
					language.name
				),
				action: {
					label: __( 'Undo', 'ako-multilang' ),
					onClick: async () => {
						try {
							await linkPost(
								postId,
								translation.id,
								language.code
							);
						} catch ( error ) {
							notify( {
								tone: 'error',
								message: errorMessage(
									error,
									__(
										'The link couldn’t be restored.',
										'ako-multilang'
									)
								),
							} );
						}
						load();
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
	};

	const defaultLabel = ( name ) =>
		/* translators: %s: Language name. */
		sprintf( __( '%s (default)', 'ako-multilang' ), name );
	const options = detail.languages.map( ( language ) => ( {
		value: language.code,
		label:
			language.code === detail.default
				? defaultLabel( language.name )
				: language.name,
	} ) );
	if ( ! detail.language ) {
		options.unshift( {
			value: '',
			label: __( 'Choose a language…', 'ako-multilang' ),
		} );
	}

	return (
		<div className="ako-ml ako-lp">
			<Field label={ __( 'This page is in', 'ako-multilang' ) }>
				{ ( { id } ) => (
					<Select
						id={ id }
						value={ detail.language || '' }
						options={ options }
						disabled={ saving }
						onChange={ ( event ) =>
							changeLanguage( event.target.value )
						}
					/>
				) }
			</Field>

			{ ! detail.language ? (
				<p className="ako-lp__note is-info">
					<Icon name="arrows-left-right" size={ 14 } />
					<span>
						{ __(
							'Pick a language to link translations and add hreflang to this page.',
							'ako-multilang'
						) }
					</span>
				</p>
			) : (
				<>
					<div className="ako-lp__head">
						<span>{ __( 'Translations', 'ako-multilang' ) }</span>
						<span>
							{ sprintf(
								/* translators: 1: Linked translations, 2: Possible translations. */
								__( '%1$d of %2$d', 'ako-multilang' ),
								linkedCount,
								others.length
							) }
						</span>
					</div>

					{ others.map( ( language ) => (
						<TranslationCard
							key={ language.code }
							postId={ postId }
							postType={ detail.postType }
							language={ language }
							translation={ detail.translations[ language.code ] }
							working={ working === language.code }
							busy={ !! working }
							linking={ linking === language.code }
							onDuplicate={ () => duplicate( language ) }
							onStartLinking={ () => setLinking( language.code ) }
							onCancelLinking={ () => setLinking( null ) }
							onLinked={ ( response ) => {
								setLinking( null );
								load();
								notify( {
									tone: 'success',
									message: sprintf(
										/* translators: %s: Language name. */
										__(
											'%s translation linked.',
											'ako-multilang'
										),
										language.name
									),
								} );
								return response;
							} }
							onUnlink={ ( translation ) =>
								unlink( language, translation )
							}
							notify={ notify }
						/>
					) ) }

					<HreflangStatus detail={ detail } />
				</>
			) }
		</div>
	);
}

/**
 * One language: linked, missing, duplicating or linking.
 *
 * @param {Object}   props
 * @param {number}   props.postId
 * @param {string}   props.postType
 * @param {Object}   props.language
 * @param {Object}   props.translation
 * @param {boolean}  props.working
 * @param {boolean}  props.busy
 * @param {boolean}  props.linking
 * @param {Function} props.onDuplicate
 * @param {Function} props.onStartLinking
 * @param {Function} props.onCancelLinking
 * @param {Function} props.onLinked
 * @param {Function} props.onUnlink
 * @param {Function} props.notify
 */
function TranslationCard( {
	postId,
	postType,
	language,
	translation,
	working,
	busy,
	linking,
	onDuplicate,
	onStartLinking,
	onCancelLinking,
	onLinked,
	onUnlink,
	notify,
} ) {
	const head = ( badge ) => (
		<div className="ako-lp-card__head">
			<LanguageTag code={ language.code } name={ language.native } />
			{ badge }
		</div>
	);

	if ( translation ) {
		return (
			<div className="ako-lp-card">
				{ head( <StatusBadge status={ translation.status } /> ) }
				<p
					className="ako-lp-card__title"
					dir="auto"
					lang={ language.code }
				>
					{ translation.title }
				</p>
				<div className="ako-lp-card__actions">
					{ translation.editUrl && (
						<Button
							variant="ghost"
							size="small"
							icon="external-link"
							href={ translation.editUrl }
							target="_blank"
							rel="noopener noreferrer"
							aria-label={ sprintf(
								/* translators: %s: Language name. */
								__(
									'Edit the %s translation (opens in a new tab)',
									'ako-multilang'
								),
								language.name
							) }
						>
							{ __( 'Edit', 'ako-multilang' ) }
						</Button>
					) }
					{ translation.canEdit && (
						<Button
							variant="ghost"
							size="small"
							icon="close"
							onClick={ () => onUnlink( translation ) }
							aria-label={ sprintf(
								/* translators: %s: Language name. */
								__(
									'Unlink the %s translation',
									'ako-multilang'
								),
								language.name
							) }
						>
							{ __( 'Unlink', 'ako-multilang' ) }
						</Button>
					) }
				</div>
			</div>
		);
	}

	if ( working ) {
		return (
			<div className="ako-lp-card is-dashed" aria-busy="true">
				{ head(
					<Badge tone="info" dot>
						{ __( 'Working…', 'ako-multilang' ) }
					</Badge>
				) }
				<div
					className="ako-progress is-indeterminate"
					role="progressbar"
					aria-label={ __( 'Creating the draft', 'ako-multilang' ) }
				>
					<span className="ako-progress__value" />
				</div>
				<p className="ako-lp-card__note">
					{ __(
						'Copying the content, images, and SEO fields…',
						'ako-multilang'
					) }
				</p>
			</div>
		);
	}

	if ( linking ) {
		return (
			<div className="ako-lp-card is-dashed">
				{ head(
					<Badge tone="missing" dot>
						{ __( 'Linking…', 'ako-multilang' ) }
					</Badge>
				) }
				<LinkPicker
					postId={ postId }
					postType={ postType }
					language={ language }
					onCancel={ onCancelLinking }
					onLinked={ onLinked }
					notify={ notify }
				/>
			</div>
		);
	}

	const missingNote = sprintf(
		/* translators: %s: Language name. */
		__( 'No %s version yet.', 'ako-multilang' ),
		language.name
	);

	return (
		<div className="ako-lp-card is-dashed">
			{ head(
				<Badge tone="missing" dot>
					{ __( 'Missing', 'ako-multilang' ) }
				</Badge>
			) }
			<p className="ako-lp-card__note">{ missingNote }</p>
			<Button
				variant="primary"
				size="small"
				icon="file-plus"
				onClick={ onDuplicate }
				disabled={ busy }
			>
				{ __( 'Duplicate as draft', 'ako-multilang' ) }
			</Button>
			<Button
				size="small"
				icon="link"
				onClick={ onStartLinking }
				disabled={ busy }
			>
				{ __( 'Link existing page', 'ako-multilang' ) }
			</Button>
		</div>
	);
}

/**
 * Inline search to link an existing page (Translation card / Linking).
 *
 * @param {Object}   props
 * @param {number}   props.postId
 * @param {string}   props.postType
 * @param {Object}   props.language
 * @param {Function} props.onCancel
 * @param {Function} props.onLinked
 * @param {Function} props.notify
 */
function LinkPicker( {
	postId,
	postType,
	language,
	onCancel,
	onLinked,
	notify,
} ) {
	const [ text, setText ] = useState( '' );
	const [ term, setTerm ] = useState( '' );
	const [ results, setResults ] = useState( null );
	const [ active, setActive ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const inputRef = useRef();
	const listId = useId();

	useEffect( () => inputRef.current.focus(), [] );

	useEffect( () => {
		const timer = setTimeout( () => setTerm( text.trim() ), 250 );
		return () => clearTimeout( timer );
	}, [ text ] );

	useEffect( () => {
		let ignore = false;
		getCandidates( {
			post_type: postType,
			language: language.code,
			search: term,
			skip: postId,
		} )
			.then( ( list ) => {
				if ( ! ignore ) {
					const sorted = sortCandidates( list ).slice( 0, 6 );
					setResults( sorted );
					setActive(
						sorted.findIndex( ( candidate ) => ! candidate.linked )
					);
				}
			} )
			.catch( () => ! ignore && setResults( [] ) );
		return () => {
			ignore = true;
		};
	}, [ term, postType, language.code, postId ] );

	const choose = async ( candidate ) => {
		if ( ! candidate || candidate.linked || saving ) {
			return;
		}
		setSaving( true );
		try {
			onLinked( await linkPost( postId, candidate.id, language.code ) );
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

	const move = ( step ) => {
		const count = results ? results.length : 0;
		for ( let i = 1; i <= count; i++ ) {
			const next = ( active + step * i + count * i ) % count;
			if ( ! results[ next ].linked ) {
				setActive( next );
				return;
			}
		}
	};

	const onKeyDown = ( event ) => {
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			move( 'ArrowDown' === event.key ? 1 : -1 );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			choose( results && results[ active ] );
		} else if ( 'Escape' === event.key ) {
			event.preventDefault();
			onCancel();
		}
	};

	return (
		<>
			<TextInput
				search
				ref={ inputRef }
				value={ text }
				onChange={ ( event ) => setText( event.target.value ) }
				onKeyDown={ onKeyDown }
				role="combobox"
				aria-expanded={ !! results && results.length > 0 }
				aria-controls={ listId }
				aria-autocomplete="list"
				aria-activedescendant={
					results && active >= 0
						? `${ listId }-${ active }`
						: undefined
				}
				aria-busy={ saving || undefined }
				aria-label={ sprintf(
					/* translators: %s: Language name. */
					__( 'Search %s pages to link', 'ako-multilang' ),
					language.name
				) }
			/>
			<ul
				className="ako-lp-results"
				id={ listId }
				role="listbox"
				aria-label={ __( 'Pages', 'ako-multilang' ) }
			>
				{ results && ! results.length && (
					<li className="ako-lp-results__empty" role="presentation">
						{ __( 'No pages found.', 'ako-multilang' ) }
					</li>
				) }
				{ results &&
					results.map( ( candidate, index ) => (
						// Keyboard interaction lives on the combobox input (aria-activedescendant).
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events
						<li
							key={ candidate.id }
							id={ `${ listId }-${ index }` }
							role="option"
							aria-selected={ index === active }
							aria-disabled={ candidate.linked || undefined }
							className={ `ako-menu__item ${ index === active ? 'is-active' : '' } ${ candidate.linked ? 'is-disabled' : '' }` }
							onMouseEnter={ () =>
								! candidate.linked && setActive( index )
							}
							onMouseDown={ ( event ) => event.preventDefault() }
							onClick={ () => choose( candidate ) }
						>
							<span
								className="ako-menu__label"
								dir="auto"
								lang={ language.code }
							>
								{ candidate.title }
							</span>
							<span className="ako-menu__meta">
								{ candidateMeta( candidate ) }
							</span>
						</li>
					) ) }
			</ul>
			<button type="button" className="ako-link" onClick={ onCancel }>
				{ __( 'Cancel', 'ako-multilang' ) }
			</button>
		</>
	);
}

/**
 * hreflang status at the bottom of the panel.
 *
 * @param {Object} props
 * @param {Object} props.detail Post detail.
 */
function HreflangStatus( { detail } ) {
	if ( detail.hreflang.length ) {
		return (
			<div className="ako-lp-hreflang">
				<p className="ako-lp-hreflang__title">
					<Icon name="circle-check" size={ 14 } />
					{ __( 'hreflang is valid', 'ako-multilang' ) }
				</p>
				<code dir="ltr">{ detail.hreflang.join( ' · ' ) }</code>
			</div>
		);
	}

	return (
		<p className="ako-lp__note is-info">
			<Icon name="arrows-left-right" size={ 14 } />
			<span>
				{ 'publish' === detail.status
					? __(
							'hreflang starts once a translation is published.',
							'ako-multilang'
						)
					: __(
							'hreflang starts once this page is published.',
							'ako-multilang'
						) }
			</span>
		</p>
	);
}

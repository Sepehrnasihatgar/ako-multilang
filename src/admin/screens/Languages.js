/**
 * Languages screen (Figma: Admin screens / Daily work / Languages — *).
 */
import {
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import {
	Badge,
	Button,
	Field,
	LanguageTag,
	Select,
	TextInput,
	Toggle,
} from '../../components/controls';
import { EmptyState, PageTitle } from '../../components/display';
import { Dialog } from '../../components/overlays';
import {
	addLanguage,
	errorMessage,
	getCatalog,
	getLanguages,
	removeLanguage,
	reorderLanguages,
	updateLanguage,
} from '../api';
import { searchCatalog } from '../catalog';

const CODE_PATTERN = /^[a-z]{2,3}(-[a-z0-9]{2,4})?$/;
const LOCALE_PATTERN = /^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/;
const HREFLANG_PATTERN = /^[a-z]{2,3}(-[A-Za-z]{2,4})?$/;

export default function Languages( { notify } ) {
	const [ data, setData ] = useState( null );
	const [ catalog, setCatalog ] = useState( [] );
	const [ loadError, setLoadError ] = useState( null );
	// Setup links here with #custom when a language isn't in the list.
	const [ panel, setPanel ] = useState( () =>
		'#custom' === window.location.hash ? { mode: 'custom' } : null
	);
	const [ removing, setRemoving ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ announcement, setAnnouncement ] = useState( '' );
	const addButtonRef = useRef();

	const load = () => {
		setLoadError( null );
		Promise.all( [ getLanguages(), getCatalog() ] )
			.then( ( [ languages, list ] ) => {
				setData( languages );
				setCatalog( list );
			} )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'Your languages couldn’t be loaded.',
							'ako-multilang'
						)
					)
				)
			);
	};

	useEffect( load, [] );

	const languages = data ? data.languages : [];

	const closePanel = () => {
		setPanel( null );
		if ( addButtonRef.current ) {
			addButtonRef.current.focus();
		}
	};

	const move = async ( from, to ) => {
		if ( to < 0 || to >= languages.length || from === to ) {
			return;
		}
		const previous = data;
		const next = [ ...languages ];
		const [ item ] = next.splice( from, 1 );
		next.splice( to, 0, item );
		setData( { ...data, languages: next } );
		setAnnouncement(
			sprintf(
				/* translators: 1: Language name, 2: New position, 3: Number of languages. */
				__( '%1$s moved to position %2$d of %3$d.', 'ako-multilang' ),
				item.name,
				to + 1,
				next.length
			)
		);
		try {
			setData(
				await reorderLanguages(
					next.map( ( language ) => language.code )
				)
			);
		} catch ( error ) {
			setData( previous );
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The new order couldn’t be saved.', 'ako-multilang' )
				),
			} );
		}
	};

	const confirmRemove = async () => {
		setBusy( true );
		try {
			setData( await removeLanguage( removing.code ) );
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Language name. */
					__( '%s removed.', 'ako-multilang' ),
					removing.name
				),
			} );
			setRemoving( null );
			closePanel();
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The language couldn’t be removed.', 'ako-multilang' )
				),
			} );
			setRemoving( null );
		}
		setBusy( false );
	};

	if ( loadError ) {
		return (
			<>
				<PageTitle
					title={ __( 'Languages', 'ako-multilang' ) }
					description=""
				/>
				<div className="ako-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load languages',
							'ako-multilang'
						) }
						description={ loadError }
						actions={
							<Button variant="primary" onClick={ load }>
								{ __( 'Try again', 'ako-multilang' ) }
							</Button>
						}
					/>
				</div>
			</>
		);
	}

	const editing =
		panel && 'edit' === panel.mode
			? languages.find( ( language ) => language.code === panel.code )
			: null;

	return (
		<>
			<PageTitle
				title={ __( 'Languages', 'ako-multilang' ) }
				description={ __(
					'Drag to reorder. The switcher shows languages in this order.',
					'ako-multilang'
				) }
				actions={
					<Button
						ref={ addButtonRef }
						variant="primary"
						icon="plus"
						onClick={ () => setPanel( { mode: 'add' } ) }
						aria-expanded={ !! panel && 'edit' !== panel.mode }
					>
						{ __( 'Add language', 'ako-multilang' ) }
					</Button>
				}
			/>

			<div className={ `ako-columns ${ panel ? 'has-panel' : '' }` }>
				<div className="ako-language-list">
					{ ! data && (
						<div
							className="ako-language-row is-skeleton"
							aria-hidden="true"
						>
							<span className="ako-skeleton is-title" />
						</div>
					) }
					{ data && ! languages.length && (
						<EmptyState
							icon="plus"
							title={ __( 'No languages yet', 'ako-multilang' ) }
							description={ __(
								'Add the language your site is written in first, then the languages you translate into.',
								'ako-multilang'
							) }
						/>
					) }
					{ languages.length > 0 && (
						<LanguageList
							languages={ languages }
							selected={ editing ? editing.code : null }
							onMove={ move }
							onEdit={ ( code ) =>
								setPanel( { mode: 'edit', code } )
							}
						/>
					) }
					<p className="ako-language-list__note">
						{ __(
							'Removing a language never deletes pages. It only unlinks them.',
							'ako-multilang'
						) }
					</p>
				</div>

				{ panel && 'add' === panel.mode && (
					<AddPanel
						catalog={ catalog }
						languages={ languages }
						onClose={ closePanel }
						onCustom={ () => setPanel( { mode: 'custom' } ) }
						onAdded={ ( response, added ) => {
							setData( response );
							notify( {
								tone: 'success',
								message: sprintf(
									/* translators: %s: Language name. */
									__( '%s added.', 'ako-multilang' ),
									added
								),
							} );
						} }
						notify={ notify }
					/>
				) }
				{ panel && 'custom' === panel.mode && (
					<CustomPanel
						languages={ languages }
						onClose={ closePanel }
						onBack={ () => setPanel( { mode: 'add' } ) }
						onAdded={ ( response, code, added ) => {
							setData( response );
							setPanel( { mode: 'edit', code } );
							notify( {
								tone: 'success',
								message: sprintf(
									/* translators: %s: Language name. */
									__( '%s added.', 'ako-multilang' ),
									added
								),
							} );
						} }
						notify={ notify }
					/>
				) }
				{ editing && (
					<EditPanel
						key={ editing.code }
						language={ editing }
						catalog={ catalog }
						onClose={ closePanel }
						onSaved={ ( response ) => {
							setData( response );
							notify( {
								tone: 'success',
								message: sprintf(
									/* translators: %s: Language name. */
									__( '%s saved.', 'ako-multilang' ),
									editing.name
								),
							} );
						} }
						onRemove={ () => setRemoving( editing ) }
						notify={ notify }
					/>
				) }
			</div>

			<p className="screen-reader-text" aria-live="polite">
				{ announcement }
			</p>

			{ removing && (
				<Dialog
					tone="danger"
					icon="alert"
					title={ sprintf(
						/* translators: %s: Language name. */
						__( 'Remove %s?', 'ako-multilang' ),
						removing.name
					) }
					description={ sprintf(
						/* translators: 1: Number of pages, 2: Language name. */
						_n(
							'%1$d %2$s page stays published but loses its links, hreflang, and switcher entry. You can add %2$s back anytime.',
							'%1$d %2$s pages stay published but lose their links, hreflang, and switcher entry. You can add %2$s back anytime.',
							removing.count,
							'ako-multilang'
						),
						removing.count,
						removing.name
					) }
					confirmLabel={ sprintf(
						/* translators: %s: Language name. */
						__( 'Remove %s', 'ako-multilang' ),
						removing.name
					) }
					onConfirm={ confirmRemove }
					onCancel={ () => setRemoving( null ) }
					busy={ busy }
				/>
			) }
		</>
	);
}

/**
 * Sortable list. Mouse: drag rows. Keyboard: focus a handle, then ↑/↓.
 *
 * @param {Object}   props
 * @param {Array}    props.languages
 * @param {Object}   props.selected
 * @param {Function} props.onMove
 * @param {Function} props.onEdit
 */
function LanguageList( { languages, selected, onMove, onEdit } ) {
	const [ dragging, setDragging ] = useState( null );
	const handleRefs = useRef( {} );
	const pendingFocus = useRef( null );

	useEffect( () => {
		if (
			pendingFocus.current &&
			handleRefs.current[ pendingFocus.current ]
		) {
			handleRefs.current[ pendingFocus.current ].focus();
			pendingFocus.current = null;
		}
	} );

	return (
		<ol className="ako-language-list__rows">
			{ languages.map( ( language, index ) => (
				<li
					key={ language.code }
					className={ `ako-language-row ${ selected === language.code ? 'is-selected' : '' } ${
						dragging === index ? 'is-dragging' : ''
					}` }
					draggable
					onDragStart={ ( event ) => {
						event.dataTransfer.effectAllowed = 'move';
						event.dataTransfer.setData(
							'text/plain',
							language.code
						);
						setDragging( index );
					} }
					onDragOver={ ( event ) => event.preventDefault() }
					onDrop={ ( event ) => {
						event.preventDefault();
						if ( null !== dragging ) {
							onMove( dragging, index );
						}
						setDragging( null );
					} }
					onDragEnd={ () => setDragging( null ) }
				>
					<button
						type="button"
						className="ako-language-row__handle"
						ref={ ( node ) =>
							( handleRefs.current[ language.code ] = node )
						}
						aria-label={ sprintf(
							/* translators: 1: Language name, 2: Position, 3: Number of languages. */
							__(
								'Reorder %1$s, position %2$d of %3$d. Use the up and down arrow keys.',
								'ako-multilang'
							),
							language.name,
							index + 1,
							languages.length
						) }
						onKeyDown={ ( event ) => {
							if (
								'ArrowUp' === event.key ||
								'ArrowDown' === event.key
							) {
								event.preventDefault();
								pendingFocus.current = language.code;
								onMove(
									index,
									'ArrowUp' === event.key
										? index - 1
										: index + 1
								);
							}
						} }
					>
						<Icon name="arrows-left-right" size={ 14 } />
					</button>
					<span className="ako-language-row__lang">
						<LanguageTag
							code={ language.code }
							name={ language.native }
							size="large"
						>
							{ language.default && (
								<Badge tone="linked">
									{ __( 'Default', 'ako-multilang' ) }
								</Badge>
							) }
						</LanguageTag>
					</span>
					<code className="ako-language-row__locale">
						{ language.locale } · { language.hreflang }
					</code>
					<span className="ako-language-row__dir">
						<Badge
							tone={ 'rtl' === language.dir ? 'info' : 'draft' }
						>
							{ 'rtl' === language.dir
								? __( 'RTL', 'ako-multilang' )
								: __( 'LTR', 'ako-multilang' ) }
						</Badge>
					</span>
					<span className="ako-language-row__count">
						<span aria-hidden="true">{ language.count }</span>
						<span className="screen-reader-text">
							{ sprintf(
								/* translators: %d: Number of pages. */
								_n(
									'%d page',
									'%d pages',
									language.count,
									'ako-multilang'
								),
								language.count
							) }
						</span>
					</span>
					<Button
						variant="ghost"
						size="small"
						onClick={ () => onEdit( language.code ) }
						aria-label={ sprintf(
							/* translators: %s: Language name. */
							__( 'Edit %s', 'ako-multilang' ),
							language.name
						) }
						aria-pressed={ selected === language.code }
					>
						{ __( 'Edit', 'ako-multilang' ) }
					</Button>
				</li>
			) ) }
		</ol>
	);
}

/**
 * Side panel frame.
 *
 * @param {Object}   props
 * @param {*}        props.title    Heading content.
 * @param {Function} props.onClose  Close handler.
 * @param {*}        props.children Body.
 */
function Panel( { title, onClose, children } ) {
	const headingId = useId();
	return (
		<section className="ako-panel" aria-labelledby={ headingId }>
			<div className="ako-panel__head">
				<h2 id={ headingId } className="ako-panel__title">
					{ title }
				</h2>
				<Button
					variant="ghost"
					size="small"
					icon="close"
					onClick={ onClose }
				>
					{ __( 'Close', 'ako-multilang' ) }
				</Button>
			</div>
			{ children }
		</section>
	);
}

/**
 * Searches the built-in catalog ("Add a language").
 *
 * @param {Object}   props
 * @param {Array}    props.catalog
 * @param {Array}    props.languages
 * @param {Function} props.onClose
 * @param {Function} props.onCustom
 * @param {Function} props.onAdded
 * @param {Function} props.notify
 */
function AddPanel( {
	catalog,
	languages,
	onClose,
	onCustom,
	onAdded,
	notify,
} ) {
	const [ text, setText ] = useState( '' );
	const [ active, setActive ] = useState( 0 );
	const [ saving, setSaving ] = useState( false );
	const inputRef = useRef();
	const listId = useId();
	const helpId = useId();

	useEffect( () => inputRef.current.focus(), [] );

	const taken = useMemo(
		() => new Set( languages.map( ( language ) => language.locale ) ),
		[ languages ]
	);

	const matches = useMemo(
		() => searchCatalog( catalog, text ),
		[ text, catalog ]
	);

	const add = async ( entry ) => {
		if ( ! entry || taken.has( entry.locale ) || saving ) {
			return;
		}
		setSaving( true );
		try {
			onAdded(
				await addLanguage( { locale: entry.locale } ),
				entry.name
			);
			setText( '' );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The language couldn’t be added.', 'ako-multilang' )
				),
			} );
		}
		setSaving( false );
		inputRef.current.focus();
	};

	const onKeyDown = ( event ) => {
		if ( 'ArrowDown' === event.key && matches.length ) {
			event.preventDefault();
			setActive( ( active + 1 ) % matches.length );
		} else if ( 'ArrowUp' === event.key && matches.length ) {
			event.preventDefault();
			setActive( ( active - 1 + matches.length ) % matches.length );
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			add( matches[ active ] );
		}
	};

	const optionId = ( index ) => `${ listId }-${ index }`;

	return (
		<Panel
			title={ __( 'Add a language', 'ako-multilang' ) }
			onClose={ onClose }
		>
			<div className="ako-field">
				<label
					className="ako-field__label"
					htmlFor={ `${ listId }-input` }
				>
					{ sprintf(
						/* translators: %d: Number of languages in the list. */
						_n(
							'Search %d language',
							'Search %d languages',
							catalog.length,
							'ako-multilang'
						),
						catalog.length
					) }
				</label>
				<TextInput
					search
					ref={ inputRef }
					id={ `${ listId }-input` }
					value={ text }
					onChange={ ( event ) => {
						setText( event.target.value );
						setActive( 0 );
					} }
					onKeyDown={ onKeyDown }
					role="combobox"
					aria-expanded={ matches.length > 0 }
					aria-controls={ listId }
					aria-autocomplete="list"
					aria-describedby={ helpId }
					aria-activedescendant={
						matches.length ? optionId( active ) : undefined
					}
				/>
				<p className="ako-field__help" id={ helpId }>
					{ __(
						'Type in English or in the language itself.',
						'ako-multilang'
					) }
				</p>
			</div>

			<ul
				className="ako-menu is-list is-static"
				id={ listId }
				role="listbox"
				aria-label={ __( 'Languages', 'ako-multilang' ) }
				hidden={ ! matches.length }
			>
				{ matches.map( ( entry, index ) => {
					const isTaken = taken.has( entry.locale );
					return (
						// Keyboard interaction lives on the combobox input (aria-activedescendant).
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events
						<li
							key={ entry.locale }
							id={ optionId( index ) }
							role="option"
							aria-selected={ index === active }
							aria-disabled={ isTaken || undefined }
							className={ `ako-menu__item ${ index === active ? 'is-active' : '' } ${ isTaken ? 'is-disabled' : '' }` }
							onMouseEnter={ () => setActive( index ) }
							onMouseDown={ ( event ) => event.preventDefault() }
							onClick={ () => add( entry ) }
						>
							<LanguageTag code={ entry.code } />
							<span
								className="ako-menu__label"
								dir="auto"
								lang={ entry.locale.replace( '_', '-' ) }
							>
								{ entry.native }
							</span>
							<span className="ako-menu__meta">
								{ entry.name } · { entry.locale }
							</span>
							<span className="ako-menu__end">
								{ isTaken && (
									<>
										<Icon name="check" size={ 14 } />
										{ __( 'Added', 'ako-multilang' ) }
									</>
								) }
								{ ! isTaken && index === active && (
									<>
										{ __( 'Enter', 'ako-multilang' ) }
										<Icon
											name="corner-down-left"
											size={ 12 }
										/>
									</>
								) }
							</span>
						</li>
					);
				} ) }
			</ul>

			<p className="ako-panel__foot">
				{ __( 'Not listed?', 'ako-multilang' ) }{ ' ' }
				<button type="button" className="ako-link" onClick={ onCustom }>
					{ __( 'Add a custom language', 'ako-multilang' ) }
				</button>
			</p>
		</Panel>
	);
}

/**
 * Shared form for the custom and edit panels.
 *
 * @param {Object}   props
 * @param {Object}   props.values    Current values.
 * @param {Function} props.setValue  ( key, value ) => void.
 * @param {Object}   props.errors    Errors keyed by field.
 * @param {Array}    [props.catalog] Catalog (edit mode shows a locale select).
 * @param {boolean}  [props.custom]  Custom mode (code + free locale input).
 */
function LanguageFields( {
	values,
	setValue,
	errors,
	catalog,
	custom = false,
} ) {
	const dirLabelId = useId();
	const dirHelpId = useId();

	const localeOptions = useMemo( () => {
		if ( ! catalog ) {
			return [];
		}
		const options = catalog.map( ( entry ) => ( {
			value: entry.locale,
			label: `${ entry.locale } (${ entry.native })`,
		} ) );
		if ( ! catalog.some( ( entry ) => entry.locale === values.locale ) ) {
			options.unshift( { value: values.locale, label: values.locale } );
		}
		return options;
	}, [ catalog, values.locale ] );

	return (
		<>
			{ custom && (
				<Field
					label={ __( 'Code', 'ako-multilang' ) }
					help={ __(
						'Short and permanent, like “ar” or “pt-br”.',
						'ako-multilang'
					) }
					error={ errors.code }
				>
					{ ( { id, describedBy, invalid } ) => (
						<TextInput
							id={ id }
							aria-describedby={ describedBy }
							invalid={ invalid }
							value={ values.code }
							onChange={ ( event ) =>
								setValue(
									'code',
									event.target.value.trim().toLowerCase()
								)
							}
						/>
					) }
				</Field>
			) }
			<Field
				label={ __( 'Native name', 'ako-multilang' ) }
				help={ __(
					'Shown in the switcher, written the way speakers write it.',
					'ako-multilang'
				) }
				error={ errors.native }
			>
				{ ( { id, describedBy, invalid } ) => (
					<TextInput
						id={ id }
						dir="auto"
						aria-describedby={ describedBy }
						invalid={ invalid }
						value={ values.native }
						onChange={ ( event ) =>
							setValue( 'native', event.target.value )
						}
					/>
				) }
			</Field>
			<Field
				label={ __( 'English name', 'ako-multilang' ) }
				error={ errors.name }
			>
				{ ( { id, describedBy, invalid } ) => (
					<TextInput
						id={ id }
						aria-describedby={ describedBy }
						invalid={ invalid }
						value={ values.name }
						onChange={ ( event ) =>
							setValue( 'name', event.target.value )
						}
					/>
				) }
			</Field>
			<Field
				label={ __( 'WordPress locale', 'ako-multilang' ) }
				help={ __(
					'Loads WordPress and theme translations for these pages.',
					'ako-multilang'
				) }
				error={ errors.locale }
			>
				{ ( { id, describedBy, invalid } ) =>
					custom ? (
						<TextInput
							id={ id }
							aria-describedby={ describedBy }
							invalid={ invalid }
							value={ values.locale }
							onChange={ ( event ) =>
								setValue( 'locale', event.target.value.trim() )
							}
							placeholder="xx_XX"
						/>
					) : (
						<Select
							id={ id }
							aria-describedby={ describedBy }
							invalid={ invalid }
							value={ values.locale }
							options={ localeOptions }
							onChange={ ( event ) =>
								setValue( 'locale', event.target.value )
							}
						/>
					)
				}
			</Field>
			<Field
				label={ __( 'hreflang code', 'ako-multilang' ) }
				help={ __(
					'Add a region only if you target one, like ar-SA.',
					'ako-multilang'
				) }
				error={ errors.hreflang }
			>
				{ ( { id, describedBy, invalid } ) => (
					<TextInput
						id={ id }
						aria-describedby={ describedBy }
						invalid={ invalid }
						value={ values.hreflang }
						onChange={ ( event ) =>
							setValue( 'hreflang', event.target.value.trim() )
						}
					/>
				) }
			</Field>
			<div className="ako-setting">
				<span className="ako-setting__copy">
					<span className="ako-setting__label" id={ dirLabelId }>
						{ __( 'Right-to-left', 'ako-multilang' ) }
					</span>
					<span className="ako-setting__help" id={ dirHelpId }>
						{ custom
							? __(
									'For scripts such as Arabic or Hebrew.',
									'ako-multilang'
								)
							: __(
									'Detected from the locale.',
									'ako-multilang'
								) }
					</span>
				</span>
				<Toggle
					checked={ 'rtl' === values.dir }
					onChange={ ( on ) => setValue( 'dir', on ? 'rtl' : 'ltr' ) }
					labelId={ dirLabelId }
					describedBy={ dirHelpId }
				/>
			</div>
		</>
	);
}

/**
 * Validates a language form. Messages match the server's.
 *
 * @param {Object}  values Values.
 * @param {boolean} custom Whether code/locale are free text.
 * @return {Object} Errors keyed by field.
 */
function validate( values, custom ) {
	const errors = {};
	if ( custom && ! CODE_PATTERN.test( values.code ) ) {
		errors.code = __(
			'Use a language code like “ar” or “pt-br”.',
			'ako-multilang'
		);
	}
	if ( ! values.native.trim() ) {
		errors.native = __( 'Enter the name speakers use.', 'ako-multilang' );
	}
	if ( ! values.name.trim() ) {
		errors.name = __( 'Enter the English name.', 'ako-multilang' );
	}
	if ( ! LOCALE_PATTERN.test( values.locale ) ) {
		errors.locale = __(
			'Use a WordPress locale like “ar” or “fa_IR”.',
			'ako-multilang'
		);
	}
	if ( ! HREFLANG_PATTERN.test( values.hreflang ) ) {
		errors.hreflang = __(
			'Use a code like “ar” or “ar-SA”.',
			'ako-multilang'
		);
	}
	return errors;
}

/**
 * Edit an existing language.
 *
 * @param {Object}   props
 * @param {Object}   props.language
 * @param {Array}    props.catalog
 * @param {Function} props.onClose
 * @param {Function} props.onSaved
 * @param {Function} props.onRemove
 * @param {Function} props.notify
 */
function EditPanel( {
	language,
	catalog,
	onClose,
	onSaved,
	onRemove,
	notify,
} ) {
	const initial = {
		native: language.native,
		name: language.name,
		locale: language.locale,
		hreflang: language.hreflang,
		dir: language.dir,
	};
	const [ values, setValues ] = useState( initial );
	const [ saving, setSaving ] = useState( false );
	const errors = validate( values, false );
	const dirty = Object.keys( initial ).some(
		( key ) => initial[ key ] !== values[ key ]
	);

	const setValue = ( key, value ) =>
		setValues( ( current ) => {
			const next = { ...current, [ key ]: value };
			// Choosing another locale updates what it determines, unless the
			// user changed those fields by hand.
			if ( 'locale' === key ) {
				const before = catalog.find(
					( entry ) => entry.locale === current.locale
				);
				const after = catalog.find(
					( entry ) => entry.locale === value
				);
				if ( after ) {
					next.dir = after.dir;
					if ( ! before || current.hreflang === before.hreflang ) {
						next.hreflang = after.hreflang;
					}
				}
			}
			return next;
		} );

	const save = async ( event ) => {
		event.preventDefault();
		if ( Object.keys( errors ).length || ! dirty ) {
			return;
		}
		setSaving( true );
		try {
			onSaved( await updateLanguage( language.code, values ) );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Your changes couldn’t be saved.', 'ako-multilang' )
				),
			} );
		}
		setSaving( false );
	};

	return (
		<Panel
			title={
				<LanguageTag
					code={ language.code }
					name={ language.native }
					size="large"
				/>
			}
			onClose={ onClose }
		>
			<form className="ako-panel__form" onSubmit={ save } noValidate>
				<LanguageFields
					values={ values }
					setValue={ setValue }
					errors={ errors }
					catalog={ catalog }
				/>
				<div className="ako-panel__actions">
					{ language.default ? (
						<span className="ako-panel__hint">
							{ __(
								'The default language can’t be removed.',
								'ako-multilang'
							) }
						</span>
					) : (
						<Button
							variant="ghost"
							icon="close"
							onClick={ onRemove }
						>
							{ __( 'Remove language', 'ako-multilang' ) }
						</Button>
					) }
					<Button
						variant="primary"
						type="submit"
						disabled={
							saving ||
							! dirty ||
							Object.keys( errors ).length > 0
						}
						aria-busy={ saving || undefined }
					>
						{ __( 'Save', 'ako-multilang' ) }
					</Button>
				</div>
			</form>
		</Panel>
	);
}

/**
 * Add a language that isn't in the catalog.
 *
 * @param {Object}   props
 * @param {Array}    props.languages
 * @param {Function} props.onClose
 * @param {Function} props.onBack
 * @param {Function} props.onAdded
 * @param {Function} props.notify
 */
function CustomPanel( { languages, onClose, onBack, onAdded, notify } ) {
	const [ values, setValues ] = useState( {
		code: '',
		native: '',
		name: '',
		locale: '',
		hreflang: '',
		dir: 'ltr',
	} );
	const [ touched, setTouched ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const errors = validate(
		{ ...values, hreflang: values.hreflang || values.code },
		true
	);
	if (
		! errors.code &&
		languages.some( ( language ) => language.code === values.code )
	) {
		errors.code = __(
			'Each language can only be added once.',
			'ako-multilang'
		);
	}
	const shown = touched ? errors : {};

	const setValue = ( key, value ) =>
		setValues( ( current ) => ( { ...current, [ key ]: value } ) );

	const submit = async ( event ) => {
		event.preventDefault();
		setTouched( true );
		if ( Object.keys( errors ).length ) {
			return;
		}
		setSaving( true );
		try {
			const response = await addLanguage( {
				...values,
				hreflang: values.hreflang || values.code,
			} );
			onAdded( response, values.code, values.name );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The language couldn’t be added.', 'ako-multilang' )
				),
			} );
			setSaving( false );
		}
	};

	return (
		<Panel
			title={ __( 'Add a custom language', 'ako-multilang' ) }
			onClose={ onClose }
		>
			<form className="ako-panel__form" onSubmit={ submit } noValidate>
				<LanguageFields
					values={ values }
					setValue={ setValue }
					errors={ shown }
					custom
				/>
				<div className="ako-panel__actions">
					<Button variant="ghost" onClick={ onBack }>
						{ __( 'Back to search', 'ako-multilang' ) }
					</Button>
					<Button
						variant="primary"
						type="submit"
						disabled={ saving }
						aria-busy={ saving || undefined }
					>
						{ __( 'Add language', 'ako-multilang' ) }
					</Button>
				</div>
			</form>
		</Panel>
	);
}

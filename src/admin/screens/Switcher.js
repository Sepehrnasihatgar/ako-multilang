/**
 * Switcher designer (Figma: Admin screens / Configure / Switcher — *).
 */
import {
	useCallback,
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import {
	Button,
	Select,
	Tabs,
	TextInput,
	Toggle,
} from '../../components/controls';
import { EmptyState, PageTitle, SaveBar } from '../../components/display';
import { Dialog, Menu } from '../../components/overlays';
import SwitcherMarkup from '../../components/SwitcherMarkup';
import { errorMessage, getLanguages } from '../api';
import apiFetch from '@wordpress/api-fetch';

const BASE = '/cedar/v1/switchers';

const STYLES = [
	{ value: 'link', label: () => __( 'Link', 'cedar-multilang' ) },
	{ value: 'inline', label: () => __( 'Inline', 'cedar-multilang' ) },
	{ value: 'segmented', label: () => __( 'Segmented', 'cedar-multilang' ) },
	{ value: 'dropdown', label: () => __( 'Dropdown', 'cedar-multilang' ) },
	{ value: 'floating', label: () => __( 'Floating', 'cedar-multilang' ) },
];

const EDITABLE = [
	'style',
	'show',
	'icon',
	'mobile',
	'missing',
	'styling',
	'accent',
	'shape',
	'linkLook',
];

/**
 * Sample items for previews: the site's languages, one of them current.
 *
 * @param {Array}  languages Languages from the API.
 * @param {string} current   Current language code.
 * @param {string} missing   Profile `missing` setting (preview shows the third language as missing).
 * @return {Array} Items.
 */
function sampleItems( languages, current, missing ) {
	return languages
		.map( ( language, index ) => ( {
			code: language.code,
			hreflang: language.hreflang,
			native: language.native,
			dir: language.dir,
			current: language.code === current,
			available: ! ( index >= 2 && 'disabled' === missing ),
			hidden:
				index >= 2 && 'hide' === missing && language.code !== current,
		} ) )
		.filter( ( item ) => ! item.hidden );
}

export default function Switcher( { config, notify } ) {
	const [ switchers, setSwitchers ] = useState( null );
	const [ languages, setLanguages ] = useState( [] );
	const [ loadError, setLoadError ] = useState( null );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ dialog, setDialog ] = useState( null );

	const load = useCallback( () => {
		setLoadError( null );
		Promise.all( [ apiFetch( { path: BASE } ), getLanguages() ] )
			.then( ( [ response, langs ] ) => {
				setSwitchers( response.switchers );
				setLanguages( langs.languages );
				const first = response.switchers[ 0 ];
				setSelectedId( first.id );
				setDraft( first );
			} )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'Your switchers couldn’t be loaded.',
							'cedar-multilang'
						)
					)
				)
			);
	}, [] );

	useEffect( load, [ load ] );

	const saved = switchers
		? switchers.find( ( switcher ) => switcher.id === selectedId )
		: null;
	const dirty =
		!! saved &&
		!! draft &&
		EDITABLE.some( ( key ) => saved[ key ] !== draft[ key ] );

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const onBeforeUnload = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', onBeforeUnload );
	}, [ dirty ] );

	const set = ( key, value ) =>
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );

	const select = ( id ) => {
		const next = switchers.find( ( switcher ) => switcher.id === id );
		if ( dirty ) {
			setDialog( { type: 'discard', next } );
			return;
		}
		setSelectedId( next.id );
		setDraft( next );
	};

	const save = async () => {
		setSaving( true );
		try {
			const payload = Object.fromEntries(
				EDITABLE.map( ( key ) => [ key, draft[ key ] ] )
			);
			const response = await apiFetch( {
				path: `${ BASE }/${ draft.id }`,
				method: 'PUT',
				data: payload,
			} );
			setSwitchers( response.switchers );
			setDraft( response.switcher );
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Switcher name. */
					__( '%s saved.', 'cedar-multilang' ),
					response.switcher.name
				),
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Your changes couldn’t be saved.', 'cedar-multilang' )
				),
			} );
		}
		setSaving( false );
	};

	const create = async ( name ) => {
		setSaving( true );
		try {
			const response = await apiFetch( {
				path: BASE,
				method: 'POST',
				data: { name, style: 'dropdown' },
			} );
			setSwitchers( response.switchers );
			setSelectedId( response.switcher.id );
			setDraft( response.switcher );
			setDialog( null );
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Switcher name. */
					__( '%s created.', 'cedar-multilang' ),
					response.switcher.name
				),
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The switcher couldn’t be created.', 'cedar-multilang' )
				),
			} );
		}
		setSaving( false );
	};

	const remove = async () => {
		setSaving( true );
		try {
			const response = await apiFetch( {
				path: `${ BASE }/${ selectedId }`,
				method: 'DELETE',
			} );
			setSwitchers( response.switchers );
			setSelectedId( response.switchers[ 0 ].id );
			setDraft( response.switchers[ 0 ] );
			notify( {
				tone: 'success',
				message: sprintf(
					/* translators: %s: Switcher name. */
					__( '%s deleted.', 'cedar-multilang' ),
					saved.name
				),
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The switcher couldn’t be deleted.', 'cedar-multilang' )
				),
			} );
		}
		setDialog( null );
		setSaving( false );
	};

	if ( loadError ) {
		return (
			<>
				<PageTitle
					title={ __( 'Language switcher', 'cedar-multilang' ) }
					description=""
				/>
				<div className="cedar-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load switchers',
							'cedar-multilang'
						) }
						description={ loadError }
						actions={
							<Button variant="primary" onClick={ load }>
								{ __( 'Try again', 'cedar-multilang' ) }
							</Button>
						}
					/>
				</div>
			</>
		);
	}

	if ( ! draft ) {
		return (
			<PageTitle
				title={ __( 'Language switcher', 'cedar-multilang' ) }
				description=""
			/>
		);
	}

	return (
		<>
			<PageTitle
				title={ __( 'Language switcher', 'cedar-multilang' ) }
				description={ __(
					'Design it once, place it anywhere. The preview updates as you go.',
					'cedar-multilang'
				) }
				actions={
					<>
						<span className="cedar-editing">
							<span
								className="cedar-editing__label"
								aria-hidden="true"
							>
								{ __( 'Editing', 'cedar-multilang' ) }
							</span>
							<Menu
								align="end"
								label={ __( 'Switchers', 'cedar-multilang' ) }
								renderTrigger={ ( props ) => (
									<button
										type="button"
										className="cedar-input is-select is-trigger"
										aria-label={ sprintf(
											/* translators: %s: Switcher name. */
											__(
												'Editing: %s',
												'cedar-multilang'
											),
											saved.name
										) }
										{ ...props }
									>
										<span>{ saved.name }</span>
										<Icon name="chevron-down" />
									</button>
								) }
								items={ [
									...switchers.map( ( switcher ) => ( {
										id: switcher.id,
										label: switcher.name,
										meta: STYLES.find(
											( style ) =>
												style.value === switcher.style
										).label(),
										checked: switcher.id === selectedId,
										onSelect: () => select( switcher.id ),
									} ) ),
									...( switchers.length > 1
										? [
												{
													id: 'delete',
													label: sprintf(
														/* translators: %s: Switcher name. */
														__(
															'Delete “%s”…',
															'cedar-multilang'
														),
														saved.name
													),
													onSelect: () =>
														setDialog( {
															type: 'delete',
														} ),
												},
											]
										: [] ),
								] }
							/>
						</span>
						<Button
							variant="ghost"
							icon="plus"
							onClick={ () => setDialog( { type: 'create' } ) }
						>
							{ __( 'New switcher', 'cedar-multilang' ) }
						</Button>
					</>
				}
			/>

			<div className="cedar-designer">
				<div className="cedar-designer__column">
					<section
						className="cedar-card"
						aria-labelledby="cedar-style-title"
					>
						<h2
							id="cedar-style-title"
							className="cedar-card__title"
						>
							{ __( 'Style', 'cedar-multilang' ) }
						</h2>
						<StyleTiles
							value={ draft.style }
							onChange={ ( style ) => set( 'style', style ) }
							languages={ languages }
						/>
					</section>

					<section
						className="cedar-card"
						aria-labelledby="cedar-content-title"
					>
						<h2
							id="cedar-content-title"
							className="cedar-card__title"
						>
							{ __( 'Content', 'cedar-multilang' ) }
						</h2>
						<SettingRow label={ __( 'Show', 'cedar-multilang' ) }>
							{ ( id ) => (
								<Select
									id={ id }
									value={ draft.show }
									onChange={ ( event ) =>
										set( 'show', event.target.value )
									}
									options={ [
										{
											value: 'code',
											label: __(
												'Code (EN · FA)',
												'cedar-multilang'
											),
										},
										{
											value: 'native',
											label: __(
												'Native name',
												'cedar-multilang'
											),
										},
										{
											value: 'both',
											label: __(
												'Name and code',
												'cedar-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						<SettingRow
							label={ __( 'Globe icon', 'cedar-multilang' ) }
							toggle
						>
							{ ( id, labelId ) => (
								<Toggle
									checked={ draft.icon }
									onChange={ ( on ) => set( 'icon', on ) }
									labelId={ labelId }
								/>
							) }
						</SettingRow>
						<SettingRow
							label={ __(
								'On small screens',
								'cedar-multilang'
							) }
						>
							{ ( id ) => (
								<Select
									id={ id }
									value={ draft.mobile }
									onChange={ ( event ) =>
										set( 'mobile', event.target.value )
									}
									options={ [
										{
											value: 'same',
											label: __(
												'Same as desktop',
												'cedar-multilang'
											),
										},
										{
											value: 'code',
											label: __(
												'Codes only',
												'cedar-multilang'
											),
										},
										{
											value: 'icon',
											label: __(
												'Globe only',
												'cedar-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						<SettingRow
							label={ __( 'No translation', 'cedar-multilang' ) }
							help={ __(
								'Where the link goes when a page isn’t translated.',
								'cedar-multilang'
							) }
						>
							{ ( id, labelId, helpId ) => (
								<Select
									id={ id }
									aria-describedby={ helpId }
									value={ draft.missing }
									onChange={ ( event ) =>
										set( 'missing', event.target.value )
									}
									options={ [
										{
											value: 'home',
											label: __(
												'Language homepage',
												'cedar-multilang'
											),
										},
										{
											value: 'hide',
											label: __(
												'Hide the language',
												'cedar-multilang'
											),
										},
										{
											value: 'disabled',
											label: __(
												'Show it disabled',
												'cedar-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
					</section>
				</div>

				<div className="cedar-designer__column">
					<Preview
						profile={ draft }
						languages={ languages }
						siteName={ config.siteName }
					/>

					<section
						className="cedar-card"
						aria-labelledby="cedar-look-title"
					>
						<h2 id="cedar-look-title" className="cedar-card__title">
							{ __( 'Look', 'cedar-multilang' ) }
						</h2>
						<SettingRow
							label={ __( 'Styling', 'cedar-multilang' ) }
						>
							{ ( id ) => (
								<Select
									id={ id }
									value={ draft.styling }
									onChange={ ( event ) =>
										set( 'styling', event.target.value )
									}
									options={ [
										{
											value: 'cedar',
											label: __(
												'Cedar style',
												'cedar-multilang'
											),
										},
										{
											value: 'inherit',
											label: __(
												'Inherit from theme',
												'cedar-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						{ 'inherit' === draft.styling ? (
							<p className="cedar-note is-info">
								{ __(
									'Uses your theme’s font, colors and spacing. Fine-tune with --cedar-switcher-* in your CSS.',
									'cedar-multilang'
								) }
							</p>
						) : (
							<>
								<SettingRow
									label={ __(
										'Accent color',
										'cedar-multilang'
									) }
								>
									{ ( id ) => (
										<ColorInput
											id={ id }
											value={ draft.accent }
											onChange={ ( color ) =>
												set( 'accent', color )
											}
										/>
									) }
								</SettingRow>
								<SettingRow
									label={ __( 'Shape', 'cedar-multilang' ) }
								>
									{ ( id ) => (
										<Select
											id={ id }
											value={ draft.shape }
											onChange={ ( event ) =>
												set(
													'shape',
													event.target.value
												)
											}
											options={ [
												{
													value: 'pill',
													label: __(
														'Pill',
														'cedar-multilang'
													),
												},
												{
													value: 'rounded',
													label: __(
														'Rounded',
														'cedar-multilang'
													),
												},
												{
													value: 'square',
													label: __(
														'Square',
														'cedar-multilang'
													),
												},
											] }
										/>
									) }
								</SettingRow>
								{ 'link' === draft.style && (
									<SettingRow
										label={ __(
											'Link look',
											'cedar-multilang'
										) }
									>
										{ ( id ) => (
											<Select
												id={ id }
												value={ draft.linkLook }
												onChange={ ( event ) =>
													set(
														'linkLook',
														event.target.value
													)
												}
												options={ [
													{
														value: 'plain',
														label: __(
															'Plain',
															'cedar-multilang'
														),
													},
													{
														value: 'outline',
														label: __(
															'Outline',
															'cedar-multilang'
														),
													},
													{
														value: 'soft',
														label: __(
															'Soft',
															'cedar-multilang'
														),
													},
													{
														value: 'solid',
														label: __(
															'Solid',
															'cedar-multilang'
														),
													},
												] }
											/>
										) }
									</SettingRow>
								) }
							</>
						) }
					</section>

					<EmbedCard profile={ draft } notify={ notify } />
				</div>
			</div>

			{ dirty && (
				<SaveBar
					saving={ saving }
					onSave={ save }
					onDiscard={ () => setDraft( saved ) }
				/>
			) }

			{ dialog && 'create' === dialog.type && (
				<CreateDialog
					onCreate={ create }
					onCancel={ () => setDialog( null ) }
					busy={ saving }
				/>
			) }
			{ dialog && 'delete' === dialog.type && (
				<Dialog
					tone="danger"
					icon="alert"
					title={ sprintf(
						/* translators: %s: Switcher name. */
						__( 'Delete %s?', 'cedar-multilang' ),
						saved.name
					) }
					description={ __(
						'Blocks, shortcodes and menu items that use it will show your first switcher instead.',
						'cedar-multilang'
					) }
					confirmLabel={ __( 'Delete switcher', 'cedar-multilang' ) }
					onConfirm={ remove }
					onCancel={ () => setDialog( null ) }
					busy={ saving }
				/>
			) }
			{ dialog && 'discard' === dialog.type && (
				<Dialog
					tone="danger"
					icon="alert"
					title={ __(
						'Discard unsaved changes?',
						'cedar-multilang'
					) }
					description={ sprintf(
						/* translators: %s: Switcher name. */
						__(
							'Your changes to %s will be lost.',
							'cedar-multilang'
						),
						saved.name
					) }
					confirmLabel={ __( 'Discard changes', 'cedar-multilang' ) }
					onConfirm={ () => {
						setSelectedId( dialog.next.id );
						setDraft( dialog.next );
						setDialog( null );
					} }
					onCancel={ () => setDialog( null ) }
				/>
			) }
		</>
	);
}

/**
 * Setting row (Figma: Settings patterns / Setting row).
 *
 * @param {Object}   props
 * @param {string}   props.label    Label.
 * @param {string}   [props.help]   Help text.
 * @param {boolean}  [props.toggle] Control is a switch (labelled by id, not <label for>).
 * @param {Function} props.children ( id, labelId, helpId ) => control.
 */
function SettingRow( { label, help, toggle = false, children } ) {
	const id = useId();
	const labelId = `${ id }-label`;
	const helpId = help ? `${ id }-help` : undefined;
	return (
		<div className="cedar-setting-row">
			<span className="cedar-setting-row__copy">
				{ toggle ? (
					<span className="cedar-setting-row__label" id={ labelId }>
						{ label }
					</span>
				) : (
					<label
						className="cedar-setting-row__label"
						id={ labelId }
						htmlFor={ id }
					>
						{ label }
					</label>
				) }
				{ help && (
					<span className="cedar-setting-row__help" id={ helpId }>
						{ help }
					</span>
				) }
			</span>
			<span className="cedar-setting-row__control">
				{ children( id, labelId, helpId ) }
			</span>
		</div>
	);
}

/**
 * Style tiles (Figma: Onboarding patterns / Preset tile) as a radio group.
 *
 * @param {Object}   props
 * @param {string}   props.value     Selected style.
 * @param {Function} props.onChange  Called with a style.
 * @param {Array}    props.languages Languages for the thumbnails.
 */
function StyleTiles( { value, onChange, languages } ) {
	const refs = useRef( {} );
	const items = useMemo(
		() =>
			sampleItems(
				languages.slice( 0, 2 ),
				languages[ 0 ] && languages[ 0 ].code,
				'home'
			),
		[ languages ]
	);

	const onKeyDown = ( event, index ) => {
		const moves = {
			ArrowRight: 1,
			ArrowDown: 1,
			ArrowLeft: -1,
			ArrowUp: -1,
		};
		if ( ! ( event.key in moves ) ) {
			return;
		}
		event.preventDefault();
		const rtl = 'rtl' === document.documentElement.dir;
		let step = moves[ event.key ];
		if (
			rtl &&
			( 'ArrowRight' === event.key || 'ArrowLeft' === event.key )
		) {
			step = -step;
		}
		const next =
			STYLES[ ( index + step + STYLES.length ) % STYLES.length ].value;
		onChange( next );
		refs.current[ next ].focus();
	};

	return (
		<div
			className="cedar-tiles"
			role="radiogroup"
			aria-labelledby="cedar-style-title"
		>
			{ STYLES.map( ( style, index ) => {
				const selected = style.value === value;
				return (
					<button
						type="button"
						key={ style.value }
						ref={ ( node ) =>
							( refs.current[ style.value ] = node )
						}
						role="radio"
						aria-checked={ selected }
						tabIndex={ selected ? 0 : -1 }
						className={ `cedar-tile ${ selected ? 'is-selected' : '' }` }
						onClick={ () => onChange( style.value ) }
						onKeyDown={ ( event ) => onKeyDown( event, index ) }
					>
						<span className="cedar-tile__thumb" inert="">
							{ items.length > 1 && (
								<SwitcherMarkup
									profile={ {
										style: style.value,
										shape: 'pill',
										show:
											'link' === style.value ||
											'inline' === style.value
												? 'native'
												: 'code',
										icon: [
											'link',
											'dropdown',
											'floating',
										].includes( style.value ),
										mobile: 'same',
										styling: 'cedar',
										accent: '',
										linkLook: 'outline',
									} }
									items={ items }
								/>
							) }
						</span>
						<span className="cedar-tile__label">
							<span>{ style.label() }</span>
							{ selected && (
								<Icon name="circle-check" size={ 14 } />
							) }
						</span>
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * Live preview with Desktop/Mobile, Light/Dark and page-language toggles.
 *
 * @param {Object} props
 * @param {Object} props.profile   Draft profile.
 * @param {Array}  props.languages Languages.
 * @param {string} props.siteName  Site title for the mock header.
 */
function Preview( { profile, languages, siteName } ) {
	const [ device, setDevice ] = useState( 'desktop' );
	const [ scheme, setScheme ] = useState( 'light' );
	const [ page, setPage ] = useState(
		languages[ 0 ] ? languages[ 0 ].code : ''
	);

	if ( languages.length < 2 ) {
		return (
			<section className="cedar-card">
				<p className="cedar-note is-info">
					{ __(
						'Add a second language to preview the switcher.',
						'cedar-multilang'
					) }
				</p>
			</section>
		);
	}

	const current =
		languages.find( ( language ) => language.code === page ) ||
		languages[ 0 ];
	const items = sampleItems( languages, current.code, profile.missing );
	const pageOptions = languages.slice( 0, 2 ).map( ( language ) => ( {
		value: language.code,
		label: sprintf(
			/* translators: %s: Language code, e.g. "EN". */
			__( '%s page', 'cedar-multilang' ),
			language.code.toUpperCase()
		),
	} ) );

	return (
		<section
			className="cedar-card cedar-preview-card"
			aria-label={ __( 'Live preview', 'cedar-multilang' ) }
		>
			<div className="cedar-preview-card__bar">
				<span className="cedar-preview-card__live">
					<span
						className="cedar-preview-card__dot"
						aria-hidden="true"
					/>
					{ __( 'Live preview', 'cedar-multilang' ) }
				</span>
				<span className="cedar-preview-card__toggles">
					<Tabs
						label={ __( 'Device', 'cedar-multilang' ) }
						value={ device }
						onChange={ setDevice }
						items={ [
							{
								value: 'desktop',
								label: __( 'Desktop', 'cedar-multilang' ),
							},
							{
								value: 'mobile',
								label: __( 'Mobile', 'cedar-multilang' ),
							},
						] }
					/>
					<Tabs
						label={ __( 'Color scheme', 'cedar-multilang' ) }
						value={ scheme }
						onChange={ setScheme }
						items={ [
							{
								value: 'light',
								label: __( 'Light', 'cedar-multilang' ),
							},
							{
								value: 'dark',
								label: __( 'Dark', 'cedar-multilang' ),
							},
						] }
					/>
					<Tabs
						label={ __( 'Page language', 'cedar-multilang' ) }
						value={ current.code }
						onChange={ setPage }
						items={ pageOptions }
					/>
				</span>
			</div>
			<div
				className={ `cedar-preview is-${ device } is-${ scheme }` }
				inert=""
			>
				<div
					className="cedar-preview__site"
					dir={ current.dir }
					lang={ current.hreflang }
				>
					<span className="cedar-preview__brand">{ siteName }</span>
					<span className="cedar-preview__nav">
						{ 'desktop' === device && (
							<>
								<span className="cedar-preview__link" />
								<span className="cedar-preview__link" />
							</>
						) }
						{ 'floating' !== profile.style && (
							<SwitcherMarkup
								profile={ profile }
								items={ items }
								small={ 'mobile' === device }
							/>
						) }
					</span>
				</div>
				{ 'floating' === profile.style && (
					<div
						className="cedar-preview__floating"
						dir={ current.dir }
					>
						<SwitcherMarkup
							profile={ profile }
							items={ items }
							small={ 'mobile' === device }
						/>
					</div>
				) }
			</div>
		</section>
	);
}

/**
 * Color input (Figma: Settings patterns / Color input): native picker
 * behind the swatch, hex text field beside it. Empty = default accent.
 *
 * @param {Object}   props
 * @param {string}   props.id       Text field id.
 * @param {string}   props.value    Hex or ''.
 * @param {Function} props.onChange Called with a valid hex or ''.
 */
function ColorInput( { id, value, onChange } ) {
	const [ text, setText ] = useState( value || '#181815' );
	useEffect( () => setText( value || '#181815' ), [ value ] );
	const valid = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test( text );

	return (
		<span className={ `cedar-color ${ valid ? '' : 'is-invalid' }` }>
			<span
				className="cedar-color__swatch"
				style={ { background: valid ? text : 'transparent' } }
			>
				<input
					type="color"
					aria-label={ __( 'Pick a color', 'cedar-multilang' ) }
					value={ valid && 7 === text.length ? text : '#181815' }
					onChange={ ( event ) => {
						setText( event.target.value );
						onChange( event.target.value );
					} }
				/>
			</span>
			<input
				id={ id }
				type="text"
				className="cedar-color__hex"
				value={ text }
				maxLength={ 7 }
				spellCheck={ false }
				aria-invalid={ ! valid || undefined }
				onChange={ ( event ) => {
					const next = event.target.value.trim();
					setText( next );
					if ( /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test( next ) ) {
						onChange( next.toLowerCase() );
					}
				} }
			/>
		</span>
	);
}

/**
 * "Add it to your site" (Figma: Settings patterns / Embed card).
 *
 * @param {Object}   props
 * @param {Object}   props.profile Profile.
 * @param {Function} props.notify  Toast.
 */
function EmbedCard( { profile, notify } ) {
	const [ method, setMethod ] = useState( 'shortcode' );
	const [ copied, setCopied ] = useState( false );

	useEffect( () => setCopied( false ), [ method, profile.id ] );

	if ( 'floating' === profile.style ) {
		return (
			<section className="cedar-card" aria-labelledby="cedar-embed-title">
				<h2 id="cedar-embed-title" className="cedar-card__title">
					{ __( 'Add it to your site', 'cedar-multilang' ) }
				</h2>
				<p className="cedar-card__text">
					{ __(
						'Floating switchers appear on every page automatically, in the bottom corner. Nothing to add.',
						'cedar-multilang'
					) }
				</p>
			</section>
		);
	}

	const methods = {
		block: {
			label: __( 'Block', 'cedar-multilang' ),
			text: __(
				'Add the “Language Switcher” block in the editor or Site Editor, then pick this design in its settings.',
				'cedar-multilang'
			),
			code: null,
		},
		menu: {
			label: __( 'Menu item', 'cedar-multilang' ),
			text: __(
				'In Appearance → Menus, open “Language switcher” (Screen Options). In the Site Editor, add a custom link to:',
				'cedar-multilang'
			),
			code: `#cedar-switcher-${ profile.id }`,
		},
		shortcode: {
			label: __( 'Shortcode', 'cedar-multilang' ),
			text: __(
				'Works in Elementor, widgets and any text field.',
				'cedar-multilang'
			),
			code: `[cedar_switcher id="${ profile.id }"]`,
		},
		php: {
			label: __( 'PHP', 'cedar-multilang' ),
			text: __( 'For theme templates.', 'cedar-multilang' ),
			code: `<?php cedar_ml_switcher( '${ profile.id }' ); ?>`,
		},
	};
	const current = methods[ method ];

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( current.code );
			setCopied( true );
			notify( {
				tone: 'success',
				message: __( 'Copied to the clipboard.', 'cedar-multilang' ),
			} );
		} catch {
			notify( {
				tone: 'error',
				message: __(
					'Couldn’t copy. Select the code and copy it instead.',
					'cedar-multilang'
				),
			} );
		}
	};

	return (
		<section className="cedar-card" aria-labelledby="cedar-embed-title">
			<div className="cedar-card__head">
				<h2 id="cedar-embed-title" className="cedar-card__title">
					{ __( 'Add it to your site', 'cedar-multilang' ) }
				</h2>
				<Tabs
					label={ __( 'Method', 'cedar-multilang' ) }
					value={ method }
					onChange={ setMethod }
					items={ Object.entries( methods ).map(
						( [ key, entry ] ) => ( {
							value: key,
							label: entry.label,
						} )
					) }
				/>
			</div>
			<p className="cedar-card__text">{ current.text }</p>
			{ current.code && (
				<div className="cedar-code">
					<code dir="ltr">{ current.code }</code>
					<Button
						variant="ghost"
						size="small"
						icon={ copied ? 'check' : 'copy' }
						onClick={ copy }
					>
						{ copied
							? __( 'Copied', 'cedar-multilang' )
							: __( 'Copy', 'cedar-multilang' ) }
					</Button>
				</div>
			) }
		</section>
	);
}

/**
 * Asks for a name for a new switcher.
 *
 * @param {Object}   props
 * @param {Function} props.onCreate Called with the name.
 * @param {Function} props.onCancel Cancel.
 * @param {boolean}  props.busy     Saving.
 */
function CreateDialog( { onCreate, onCancel, busy } ) {
	const [ name, setName ] = useState( '' );
	const id = useId();
	const valid = name.trim().length > 0;

	return (
		<Dialog
			icon="plus"
			title={ __( 'New switcher', 'cedar-multilang' ) }
			description={ __(
				'Give it a name you’ll recognize, like “Footer switcher”.',
				'cedar-multilang'
			) }
			confirmLabel={ __( 'Create switcher', 'cedar-multilang' ) }
			onConfirm={ () => valid && onCreate( name.trim() ) }
			onCancel={ onCancel }
			busy={ busy }
			initialFocus="input"
		>
			<div className="cedar-field">
				<label className="cedar-field__label" htmlFor={ id }>
					{ __( 'Name', 'cedar-multilang' ) }
				</label>
				<TextInput
					id={ id }
					value={ name }
					maxLength={ 60 }
					onChange={ ( event ) => setName( event.target.value ) }
					onKeyDown={ ( event ) => {
						if ( 'Enter' === event.key && valid ) {
							onCreate( name.trim() );
						}
					} }
				/>
			</div>
		</Dialog>
	);
}

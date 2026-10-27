/**
 * Switcher designer (Figma: Admin screens / Configure / Switcher — *).
 */
import { useCallback, useEffect, useId, useState } from '@wordpress/element';
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
import StyleTiles, { sampleItems, STYLES } from '../../components/StyleTiles';
import SwitcherMarkup from '../../components/SwitcherMarkup';
import { errorMessage, getLanguages } from '../api';
import apiFetch from '@wordpress/api-fetch';

const BASE = '/ako/v1/switchers';

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
							'ako-multilang'
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
					__( '%s saved.', 'ako-multilang' ),
					response.switcher.name
				),
			} );
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
					__( '%s created.', 'ako-multilang' ),
					response.switcher.name
				),
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The switcher couldn’t be created.', 'ako-multilang' )
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
					__( '%s deleted.', 'ako-multilang' ),
					saved.name
				),
			} );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'The switcher couldn’t be deleted.', 'ako-multilang' )
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
					title={ __( 'Language switcher', 'ako-multilang' ) }
					description=""
				/>
				<div className="ako-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load switchers',
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

	if ( ! draft ) {
		return (
			<PageTitle
				title={ __( 'Language switcher', 'ako-multilang' ) }
				description=""
			/>
		);
	}

	return (
		<>
			<PageTitle
				title={ __( 'Language switcher', 'ako-multilang' ) }
				description={ __(
					'Design it once, place it anywhere. The preview updates as you go.',
					'ako-multilang'
				) }
				actions={
					<>
						<span className="ako-editing">
							<span
								className="ako-editing__label"
								aria-hidden="true"
							>
								{ __( 'Editing', 'ako-multilang' ) }
							</span>
							<Menu
								align="end"
								label={ __( 'Switchers', 'ako-multilang' ) }
								renderTrigger={ ( props ) => (
									<button
										type="button"
										className="ako-input is-select is-trigger"
										aria-label={ sprintf(
											/* translators: %s: Switcher name. */
											__(
												'Editing: %s',
												'ako-multilang'
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
															'ako-multilang'
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
							{ __( 'New switcher', 'ako-multilang' ) }
						</Button>
					</>
				}
			/>

			<div className="ako-designer">
				<div className="ako-designer__column">
					<section
						className="ako-card"
						aria-labelledby="ako-style-title"
					>
						<h2 id="ako-style-title" className="ako-card__title">
							{ __( 'Style', 'ako-multilang' ) }
						</h2>
						<StyleTiles
							value={ draft.style }
							onChange={ ( style ) => set( 'style', style ) }
							languages={ languages }
							labelledBy="ako-style-title"
						/>
					</section>

					<section
						className="ako-card"
						aria-labelledby="ako-content-title"
					>
						<h2 id="ako-content-title" className="ako-card__title">
							{ __( 'Content', 'ako-multilang' ) }
						</h2>
						<SettingRow label={ __( 'Show', 'ako-multilang' ) }>
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
												'ako-multilang'
											),
										},
										{
											value: 'native',
											label: __(
												'Native name',
												'ako-multilang'
											),
										},
										{
											value: 'both',
											label: __(
												'Name and code',
												'ako-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						<SettingRow
							label={ __( 'Globe icon', 'ako-multilang' ) }
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
							label={ __( 'On small screens', 'ako-multilang' ) }
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
												'ako-multilang'
											),
										},
										{
											value: 'code',
											label: __(
												'Codes only',
												'ako-multilang'
											),
										},
										{
											value: 'icon',
											label: __(
												'Globe only',
												'ako-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						<SettingRow
							label={ __( 'No translation', 'ako-multilang' ) }
							help={ __(
								'Where the link goes when a page isn’t translated.',
								'ako-multilang'
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
												'ako-multilang'
											),
										},
										{
											value: 'hide',
											label: __(
												'Hide the language',
												'ako-multilang'
											),
										},
										{
											value: 'disabled',
											label: __(
												'Show it disabled',
												'ako-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
					</section>
				</div>

				<div className="ako-designer__column">
					<Preview
						profile={ draft }
						languages={ languages }
						siteName={ config.siteName }
					/>

					<section
						className="ako-card"
						aria-labelledby="ako-look-title"
					>
						<h2 id="ako-look-title" className="ako-card__title">
							{ __( 'Look', 'ako-multilang' ) }
						</h2>
						<SettingRow label={ __( 'Styling', 'ako-multilang' ) }>
							{ ( id ) => (
								<Select
									id={ id }
									value={ draft.styling }
									onChange={ ( event ) =>
										set( 'styling', event.target.value )
									}
									options={ [
										{
											value: 'ako',
											label: __(
												'Ako style',
												'ako-multilang'
											),
										},
										{
											value: 'inherit',
											label: __(
												'Inherit from theme',
												'ako-multilang'
											),
										},
									] }
								/>
							) }
						</SettingRow>
						{ 'inherit' === draft.styling ? (
							<p className="ako-note is-info">
								{ __(
									'Uses your theme’s font, colors, and spacing. Fine-tune it with --ako-switcher-* in your CSS.',
									'ako-multilang'
								) }
							</p>
						) : (
							<>
								<SettingRow
									label={ __(
										'Accent color',
										'ako-multilang'
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
									label={ __( 'Shape', 'ako-multilang' ) }
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
														'ako-multilang'
													),
												},
												{
													value: 'rounded',
													label: __(
														'Rounded',
														'ako-multilang'
													),
												},
												{
													value: 'square',
													label: __(
														'Square',
														'ako-multilang'
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
											'ako-multilang'
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
															'ako-multilang'
														),
													},
													{
														value: 'outline',
														label: __(
															'Outline',
															'ako-multilang'
														),
													},
													{
														value: 'soft',
														label: __(
															'Soft',
															'ako-multilang'
														),
													},
													{
														value: 'solid',
														label: __(
															'Solid',
															'ako-multilang'
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
						__( 'Delete %s?', 'ako-multilang' ),
						saved.name
					) }
					description={ __(
						'Blocks, shortcodes, and menu items that use it will show your first switcher instead.',
						'ako-multilang'
					) }
					confirmLabel={ __( 'Delete switcher', 'ako-multilang' ) }
					onConfirm={ remove }
					onCancel={ () => setDialog( null ) }
					busy={ saving }
				/>
			) }
			{ dialog && 'discard' === dialog.type && (
				<Dialog
					tone="danger"
					icon="alert"
					title={ __( 'Discard unsaved changes?', 'ako-multilang' ) }
					description={ sprintf(
						/* translators: %s: Switcher name. */
						__(
							'Your changes to %s will be lost.',
							'ako-multilang'
						),
						saved.name
					) }
					confirmLabel={ __( 'Discard changes', 'ako-multilang' ) }
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
		<div className="ako-setting-row">
			<span className="ako-setting-row__copy">
				{ toggle ? (
					<span className="ako-setting-row__label" id={ labelId }>
						{ label }
					</span>
				) : (
					<label
						className="ako-setting-row__label"
						id={ labelId }
						htmlFor={ id }
					>
						{ label }
					</label>
				) }
				{ help && (
					<span className="ako-setting-row__help" id={ helpId }>
						{ help }
					</span>
				) }
			</span>
			<span className="ako-setting-row__control">
				{ children( id, labelId, helpId ) }
			</span>
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
			<section className="ako-card">
				<p className="ako-note is-info">
					{ __(
						'Add a second language to preview the switcher.',
						'ako-multilang'
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
			__( '%s page', 'ako-multilang' ),
			language.code.toUpperCase()
		),
	} ) );

	return (
		<section
			className="ako-card ako-preview-card"
			aria-label={ __( 'Live preview', 'ako-multilang' ) }
		>
			<div className="ako-preview-card__bar">
				<span className="ako-preview-card__live">
					<span
						className="ako-preview-card__dot"
						aria-hidden="true"
					/>
					{ __( 'Live preview', 'ako-multilang' ) }
				</span>
				<span className="ako-preview-card__toggles">
					<Tabs
						label={ __( 'Device', 'ako-multilang' ) }
						value={ device }
						onChange={ setDevice }
						items={ [
							{
								value: 'desktop',
								label: __( 'Desktop', 'ako-multilang' ),
							},
							{
								value: 'mobile',
								label: __( 'Mobile', 'ako-multilang' ),
							},
						] }
					/>
					<Tabs
						label={ __( 'Color scheme', 'ako-multilang' ) }
						value={ scheme }
						onChange={ setScheme }
						items={ [
							{
								value: 'light',
								label: __( 'Light', 'ako-multilang' ),
							},
							{
								value: 'dark',
								label: __( 'Dark', 'ako-multilang' ),
							},
						] }
					/>
					<Tabs
						label={ __( 'Page language', 'ako-multilang' ) }
						value={ current.code }
						onChange={ setPage }
						items={ pageOptions }
					/>
				</span>
			</div>
			<div
				className={ `ako-preview is-${ device } is-${ scheme }` }
				inert=""
			>
				<div
					className="ako-preview__site"
					dir={ current.dir }
					lang={ current.hreflang }
				>
					<span className="ako-preview__brand">{ siteName }</span>
					<span className="ako-preview__nav">
						{ 'desktop' === device && (
							<>
								<span className="ako-preview__link" />
								<span className="ako-preview__link" />
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
					<div className="ako-preview__floating" dir={ current.dir }>
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
		<span className={ `ako-color ${ valid ? '' : 'is-invalid' }` }>
			<span
				className="ako-color__swatch"
				style={ { background: valid ? text : 'transparent' } }
			>
				<input
					type="color"
					aria-label={ __( 'Pick a color', 'ako-multilang' ) }
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
				className="ako-color__hex"
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
			<section className="ako-card" aria-labelledby="ako-embed-title">
				<h2 id="ako-embed-title" className="ako-card__title">
					{ __( 'Add it to your site', 'ako-multilang' ) }
				</h2>
				<p className="ako-card__text">
					{ __(
						'Floating switchers appear on every page automatically, in the bottom corner. Nothing to add.',
						'ako-multilang'
					) }
				</p>
			</section>
		);
	}

	const methods = {
		block: {
			label: __( 'Block', 'ako-multilang' ),
			text: __(
				'Add the “Language Switcher” block in the editor or Site Editor, then pick this design in its settings.',
				'ako-multilang'
			),
			code: null,
		},
		menu: {
			label: __( 'Menu item', 'ako-multilang' ),
			text: __(
				'In Appearance → Menus, add it from the “Language switcher” box. If you don’t see the box, turn it on in Screen Options. On block themes, add the “Language Switcher” block to your navigation in the Site Editor.',
				'ako-multilang'
			),
			code: null,
		},
		shortcode: {
			label: __( 'Shortcode', 'ako-multilang' ),
			text: __(
				'Works in Elementor, widgets, and any text field.',
				'ako-multilang'
			),
			code: `[ako_switcher id="${ profile.id }"]`,
		},
		php: {
			label: __( 'PHP', 'ako-multilang' ),
			text: __( 'For theme templates.', 'ako-multilang' ),
			code: `<?php ako_ml_switcher( '${ profile.id }' ); ?>`,
		},
	};
	const current = methods[ method ];

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( current.code );
			setCopied( true );
			notify( {
				tone: 'success',
				message: __( 'Copied to the clipboard.', 'ako-multilang' ),
			} );
		} catch {
			notify( {
				tone: 'error',
				message: __(
					'Couldn’t copy. Select the code and copy it instead.',
					'ako-multilang'
				),
			} );
		}
	};

	return (
		<section className="ako-card" aria-labelledby="ako-embed-title">
			<div className="ako-card__head">
				<h2 id="ako-embed-title" className="ako-card__title">
					{ __( 'Add it to your site', 'ako-multilang' ) }
				</h2>
				<Tabs
					label={ __( 'Method', 'ako-multilang' ) }
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
			<p className="ako-card__text">{ current.text }</p>
			{ current.code && (
				<div className="ako-code">
					<code dir="ltr">{ current.code }</code>
					<Button
						variant="ghost"
						size="small"
						icon={ copied ? 'check' : 'copy' }
						onClick={ copy }
					>
						{ copied
							? __( 'Copied', 'ako-multilang' )
							: __( 'Copy', 'ako-multilang' ) }
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
			title={ __( 'New switcher', 'ako-multilang' ) }
			description={ __(
				'Give it a name you’ll recognize, like “Footer switcher”.',
				'ako-multilang'
			) }
			confirmLabel={ __( 'Create switcher', 'ako-multilang' ) }
			onConfirm={ () => valid && onCreate( name.trim() ) }
			onCancel={ onCancel }
			busy={ busy }
			initialFocus="input"
		>
			<div className="ako-field">
				<label className="ako-field__label" htmlFor={ id }>
					{ __( 'Name', 'ako-multilang' ) }
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

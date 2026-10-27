/**
 * Settings (Figma: Admin screens / Configure / Settings — Default, Module
 * off (unsaved), Delete data on uninstall, Saved, Import settings).
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Badge, Button, Checkbox } from '../../components/controls';
import {
	Callout,
	EmptyState,
	PageTitle,
	SaveBar,
} from '../../components/display';
import { Dialog } from '../../components/overlays';
import { ToggleRow } from '../../components/rows';
import { errorMessage } from '../api';

const BASE = '/ako/v1/settings';

/**
 * Largest settings file accepted (they're a few KB).
 */
const MAX_FILE_BYTES = 1024 * 1024;

const MODULES = [
	{
		key: 'seo',
		label: () => __( 'SEO', 'ako-multilang' ),
		help: () =>
			__(
				'hreflang, lang and dir, og:locale, and sitemap entries.',
				'ako-multilang'
			),
	},
	{
		key: 'rtl',
		label: () => __( 'RTL support', 'ako-multilang' ),
		help: () =>
			__(
				'Switches the locale per page so your theme’s RTL styles load.',
				'ako-multilang'
			),
	},
	{
		key: 'switcher',
		label: () => __( 'Language switcher', 'ako-multilang' ),
		help: () =>
			__( 'Block, menu item, shortcode, and PHP tag.', 'ako-multilang' ),
	},
	{
		key: 'import',
		label: () => __( 'Import tools', 'ako-multilang' ),
		help: () =>
			__(
				'Import links from WPML and Polylang on the Translations screen.',
				'ako-multilang'
			),
	},
];

/**
 * Saves a JSON download in the browser (no request to the server).
 *
 * @param {Object} data     Data.
 * @param {string} filename File name.
 */
function download( data, filename ) {
	const blob = new window.Blob( [ JSON.stringify( data, null, '\t' ) ], {
		type: 'application/json',
	} );
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.URL.revokeObjectURL( url );
}

export default function Settings( { notify } ) {
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ pending, setPending ] = useState( null );
	const [ importing, setImporting ] = useState( false );
	const fileRef = useRef();

	const apply = useCallback( ( response ) => {
		setData( response );
		setDraft( response.settings );
	}, [] );

	const load = useCallback( () => {
		setLoadError( null );
		apiFetch( { path: BASE } )
			.then( apply )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__( 'Settings couldn’t be loaded.', 'ako-multilang' )
					)
				)
			);
	}, [ apply ] );

	useEffect( load, [ load ] );

	const title = (
		<PageTitle
			title={ __( 'Settings', 'ako-multilang' ) }
			description={ __(
				'Turn off anything you don’t need. Parts you turn off load no code on your site.',
				'ako-multilang'
			) }
		/>
	);

	if ( loadError ) {
		return (
			<>
				{ title }
				<div className="ako-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load settings',
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

	if ( ! data ) {
		return title;
	}

	const dirty = JSON.stringify( data.settings ) !== JSON.stringify( draft );
	const set = ( key, value ) =>
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );
	const setIn = ( group, key, value ) =>
		setDraft( ( current ) => ( {
			...current,
			[ group ]: { ...current[ group ], [ key ]: value },
		} ) );

	const save = async () => {
		const modulesChanged =
			JSON.stringify( data.settings.modules ) !==
			JSON.stringify( draft.modules );
		setSaving( true );
		try {
			apply(
				await apiFetch( { path: BASE, method: 'PUT', data: draft } )
			);
			notify( {
				tone: 'success',
				message: __( 'Settings saved.', 'ako-multilang' ),
			} );
			// Screens come and go with their modules: refresh the menu.
			if ( modulesChanged ) {
				window.location.reload();
			}
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

	const exportSettings = async () => {
		try {
			const file = await apiFetch( { path: `${ BASE }/export` } );
			download(
				file,
				`ako-settings-${ new Date().toISOString().slice( 0, 10 ) }.json`
			);
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Your settings couldn’t be exported.', 'ako-multilang' )
				),
			} );
		}
	};

	const invalidFile = () =>
		notify( {
			tone: 'error',
			message: __( 'This isn’t a Ako settings file.', 'ako-multilang' ),
		} );

	const readFile = ( event ) => {
		const file = event.target.files[ 0 ];
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		if ( file.size > MAX_FILE_BYTES ) {
			invalidFile();
			return;
		}
		file.text()
			.then( ( text ) => {
				const parsed = JSON.parse( text );
				if (
					! parsed ||
					'ako-multilang' !== parsed.plugin ||
					! parsed.languages ||
					! Array.isArray( parsed.languages.languages )
				) {
					throw new Error( 'invalid' );
				}
				setPending( { name: file.name, data: parsed } );
			} )
			.catch( invalidFile );
	};

	const importSettings = async () => {
		setImporting( true );
		try {
			apply(
				await apiFetch( {
					path: `${ BASE }/import`,
					method: 'POST',
					data: { data: pending.data },
				} )
			);
			setPending( null );
			notify( {
				tone: 'success',
				message: __( 'Settings imported.', 'ako-multilang' ),
			} );
			window.location.reload();
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Your settings couldn’t be imported.', 'ako-multilang' )
				),
			} );
		}
		setImporting( false );
	};

	const checkedTypes = draft.post_types;
	const toggleType = ( slug, on ) =>
		set(
			'post_types',
			on
				? [ ...checkedTypes, slug ]
				: checkedTypes.filter( ( type ) => type !== slug )
		);

	return (
		<>
			{ title }

			<div className="ako-designer is-settings">
				<div className="ako-designer__column">
					<section
						className="ako-card"
						aria-labelledby="ako-modules-title"
					>
						<div className="ako-card__head">
							<h2
								id="ako-modules-title"
								className="ako-card__title"
							>
								{ __( 'Modules', 'ako-multilang' ) }
							</h2>
							<p className="ako-card__text">
								{ __(
									'Each part of Ako works on its own.',
									'ako-multilang'
								) }
							</p>
						</div>
						{ MODULES.map( ( module ) => (
							<ToggleRow
								key={ module.key }
								label={ module.label() }
								help={ module.help() }
								checked={ !! draft.modules[ module.key ] }
								onChange={ ( on ) =>
									setIn( 'modules', module.key, on )
								}
							/>
						) ) }
					</section>

					<section
						className="ako-card"
						aria-labelledby="ako-types-title"
					>
						<div className="ako-card__head">
							<h2
								id="ako-types-title"
								className="ako-card__title"
							>
								{ __( 'Content types', 'ako-multilang' ) }
							</h2>
							<p className="ako-card__text">
								{ __(
									'Where the language panel and Translations screen appear.',
									'ako-multilang'
								) }
							</p>
						</div>
						<ul className="ako-type-list">
							{ data.postTypes.map( ( type ) => {
								const checked = checkedTypes.includes(
									type.slug
								);
								const last =
									checked && 1 === checkedTypes.length;
								return (
									<li key={ type.slug } className="ako-type">
										<Checkbox
											checked={ checked }
											disabled={ last }
											label={ type.label }
											aria-describedby={
												last
													? 'ako-types-last'
													: undefined
											}
											onChange={ ( on ) =>
												toggleType( type.slug, on )
											}
										/>
										<span className="ako-type__copy">
											<span
												className="ako-type__label"
												aria-hidden="true"
											>
												{ type.label }
												{ type.plugin && (
													<Badge tone="linked">
														{ type.plugin }
													</Badge>
												) }
											</span>
											{ ! type.builtin &&
												! type.plugin && (
													<span className="ako-type__help">
														{ __(
															'Custom content type.',
															'ako-multilang'
														) }
													</span>
												) }
										</span>
									</li>
								);
							} ) }
						</ul>
						{ 1 === checkedTypes.length && (
							<p id="ako-types-last" className="ako-card__text">
								{ __(
									'Keep at least one content type.',
									'ako-multilang'
								) }
							</p>
						) }
					</section>
				</div>

				<div className="ako-designer__column">
					{ data.integrations.length > 0 && (
						<section
							className="ako-card"
							aria-labelledby="ako-rtl-title"
						>
							<div className="ako-card__head">
								<h2
									id="ako-rtl-title"
									className="ako-card__title"
								>
									{ __(
										'RTL compatibility',
										'ako-multilang'
									) }
								</h2>
								<p className="ako-card__text">
									{ draft.modules.rtl
										? __(
												'Small fixes for popular themes. Only the fix for your theme loads.',
												'ako-multilang'
											)
										: __(
												'Turn on RTL support to use these fixes.',
												'ako-multilang'
											) }
								</p>
							</div>
							{ data.integrations.map( ( integration ) => {
								let badge = null;
								if ( integration.detected ) {
									badge = (
										<Badge tone="linked">
											{ 'theme' === integration.type
												? __(
														'Active theme',
														'ako-multilang'
													)
												: __(
														'Detected',
														'ako-multilang'
													) }
										</Badge>
									);
								}
								return (
									<ToggleRow
										key={ integration.key }
										label={ integration.name }
										badge={ badge }
										disabled={ ! draft.modules.rtl }
										checked={
											!! draft.rtl_compat[
												integration.key
											]
										}
										onChange={ ( on ) =>
											setIn(
												'rtl_compat',
												integration.key,
												on
											)
										}
									/>
								);
							} ) }
						</section>
					) }

					<section
						className="ako-card"
						aria-labelledby="ako-data-title"
					>
						<h2 id="ako-data-title" className="ako-card__title">
							{ __( 'Data', 'ako-multilang' ) }
						</h2>
						<ToggleRow
							label={ __(
								'Delete Ako data on uninstall',
								'ako-multilang'
							) }
							help={ __(
								'Links, languages, and switcher styles. Your pages are never touched.',
								'ako-multilang'
							) }
							checked={ draft.delete_on_uninstall }
							onChange={ ( on ) =>
								set( 'delete_on_uninstall', on )
							}
						/>
						{ draft.delete_on_uninstall && (
							<Callout
								tone="danger"
								icon="alert"
								title={ __(
									'This can’t be undone',
									'ako-multilang'
								) }
								action={
									<Button onClick={ exportSettings }>
										{ __( 'Export now', 'ako-multilang' ) }
									</Button>
								}
							>
								{ sprintf(
									/* translators: 1: Number of linked pages, 2: Number of languages. */
									_n(
										'Uninstalling removes the links on %1$d page and your %2$d languages. Export first if you might come back.',
										'Uninstalling removes the links on %1$d pages and your %2$d languages. Export first if you might come back.',
										data.counts.linked,
										'ako-multilang'
									),
									data.counts.linked,
									data.counts.languages
								) }
							</Callout>
						) }
						<div className="ako-data-actions">
							<Button
								icon="external-link"
								onClick={ exportSettings }
							>
								{ __( 'Export settings', 'ako-multilang' ) }
							</Button>
							<Button
								variant="ghost"
								onClick={ () => fileRef.current.click() }
							>
								{ __( 'Import settings', 'ako-multilang' ) }
							</Button>
							<input
								ref={ fileRef }
								type="file"
								accept="application/json,.json"
								className="screen-reader-text"
								tabIndex={ -1 }
								aria-hidden="true"
								onChange={ readFile }
							/>
						</div>
					</section>
				</div>
			</div>

			{ pending && (
				<Dialog
					icon="arrows-left-right"
					title={ __( 'Replace your settings?', 'ako-multilang' ) }
					description={ sprintf(
						/* translators: 1: File name, 2: Number of languages, 3: Number of switchers. */
						__(
							'%1$s has %2$d languages and %3$d switcher styles. Your current settings will be replaced. Links between pages stay.',
							'ako-multilang'
						),
						pending.name,
						pending.data.languages.languages.length,
						Array.isArray( pending.data.switchers )
							? pending.data.switchers.length
							: 0
					) }
					confirmLabel={ __( 'Replace settings', 'ako-multilang' ) }
					onConfirm={ importSettings }
					onCancel={ () => setPending( null ) }
					busy={ importing }
				/>
			) }

			{ dirty && (
				<SaveBar
					saving={ saving }
					onSave={ save }
					onDiscard={ () => setDraft( data.settings ) }
				/>
			) }
		</>
	);
}

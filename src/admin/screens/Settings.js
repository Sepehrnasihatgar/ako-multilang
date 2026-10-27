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

const BASE = '/cedar/v1/settings';

/**
 * Largest settings file accepted (they're a few KB).
 */
const MAX_FILE_BYTES = 1024 * 1024;

const MODULES = [
	{
		key: 'seo',
		label: () => __( 'SEO', 'cedar-multilang' ),
		help: () =>
			__(
				'hreflang, lang/dir, og:locale and sitemap entries.',
				'cedar-multilang'
			),
	},
	{
		key: 'rtl',
		label: () => __( 'RTL support', 'cedar-multilang' ),
		help: () =>
			__(
				'Switches the locale per page so your theme’s RTL styles load.',
				'cedar-multilang'
			),
	},
	{
		key: 'switcher',
		label: () => __( 'Language switcher', 'cedar-multilang' ),
		help: () =>
			__( 'Block, menu item, shortcode and PHP tag.', 'cedar-multilang' ),
	},
	{
		key: 'import',
		label: () => __( 'Import tools', 'cedar-multilang' ),
		help: () =>
			__(
				'Import links from WPML and Polylang on the Translations screen.',
				'cedar-multilang'
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
						__( 'Settings couldn’t be loaded.', 'cedar-multilang' )
					)
				)
			);
	}, [ apply ] );

	useEffect( load, [ load ] );

	const title = (
		<PageTitle
			title={ __( 'Settings', 'cedar-multilang' ) }
			description={ __(
				'Turn off anything you don’t need — it then loads no code on your site.',
				'cedar-multilang'
			) }
		/>
	);

	if ( loadError ) {
		return (
			<>
				{ title }
				<div className="cedar-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load settings',
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
				message: __( 'Settings saved.', 'cedar-multilang' ),
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
					__( 'Your changes couldn’t be saved.', 'cedar-multilang' )
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
				`cedar-settings-${ new Date().toISOString().slice( 0, 10 ) }.json`
			);
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__(
						'Your settings couldn’t be exported.',
						'cedar-multilang'
					)
				),
			} );
		}
	};

	const invalidFile = () =>
		notify( {
			tone: 'error',
			message: __(
				'This isn’t a Cedar settings file.',
				'cedar-multilang'
			),
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
					'cedar-multilang' !== parsed.plugin ||
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
				message: __( 'Settings imported.', 'cedar-multilang' ),
			} );
			window.location.reload();
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__(
						'Your settings couldn’t be imported.',
						'cedar-multilang'
					)
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

			<div className="cedar-designer is-settings">
				<div className="cedar-designer__column">
					<section
						className="cedar-card"
						aria-labelledby="cedar-modules-title"
					>
						<div className="cedar-card__head">
							<h2
								id="cedar-modules-title"
								className="cedar-card__title"
							>
								{ __( 'Modules', 'cedar-multilang' ) }
							</h2>
							<p className="cedar-card__text">
								{ __(
									'Each part of Cedar works on its own.',
									'cedar-multilang'
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
						className="cedar-card"
						aria-labelledby="cedar-types-title"
					>
						<div className="cedar-card__head">
							<h2
								id="cedar-types-title"
								className="cedar-card__title"
							>
								{ __( 'Content types', 'cedar-multilang' ) }
							</h2>
							<p className="cedar-card__text">
								{ __(
									'Where the language panel and Translations screen appear.',
									'cedar-multilang'
								) }
							</p>
						</div>
						<ul className="cedar-type-list">
							{ data.postTypes.map( ( type ) => {
								const checked = checkedTypes.includes(
									type.slug
								);
								const last =
									checked && 1 === checkedTypes.length;
								return (
									<li
										key={ type.slug }
										className="cedar-type"
									>
										<Checkbox
											checked={ checked }
											disabled={ last }
											label={ type.label }
											aria-describedby={
												last
													? 'cedar-types-last'
													: undefined
											}
											onChange={ ( on ) =>
												toggleType( type.slug, on )
											}
										/>
										<span className="cedar-type__copy">
											<span
												className="cedar-type__label"
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
													<span className="cedar-type__help">
														{ __(
															'Custom content type.',
															'cedar-multilang'
														) }
													</span>
												) }
										</span>
									</li>
								);
							} ) }
						</ul>
						{ 1 === checkedTypes.length && (
							<p
								id="cedar-types-last"
								className="cedar-card__text"
							>
								{ __(
									'Keep at least one content type.',
									'cedar-multilang'
								) }
							</p>
						) }
					</section>
				</div>

				<div className="cedar-designer__column">
					<section
						className="cedar-card"
						aria-labelledby="cedar-rtl-title"
					>
						<div className="cedar-card__head">
							<h2
								id="cedar-rtl-title"
								className="cedar-card__title"
							>
								{ __( 'RTL compatibility', 'cedar-multilang' ) }
							</h2>
							<p className="cedar-card__text">
								{ draft.modules.rtl
									? __(
											'Small fixes for builders and popular themes. Only the ones you use load.',
											'cedar-multilang'
										)
									: __(
											'Turn on RTL support to use these fixes.',
											'cedar-multilang'
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
													'cedar-multilang'
												)
											: __(
													'Detected',
													'cedar-multilang'
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
										!! draft.rtl_compat[ integration.key ]
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

					<section
						className="cedar-card"
						aria-labelledby="cedar-data-title"
					>
						<h2 id="cedar-data-title" className="cedar-card__title">
							{ __( 'Data', 'cedar-multilang' ) }
						</h2>
						<ToggleRow
							label={ __(
								'Delete Cedar data on uninstall',
								'cedar-multilang'
							) }
							help={ __(
								'Links, languages and switcher styles. Your pages are never touched.',
								'cedar-multilang'
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
									'cedar-multilang'
								) }
								action={
									<Button onClick={ exportSettings }>
										{ __(
											'Export now',
											'cedar-multilang'
										) }
									</Button>
								}
							>
								{ sprintf(
									/* translators: 1: Number of linked pages, 2: Number of languages. */
									_n(
										'Uninstalling removes the links on %1$d page and your %2$d languages. Export first if you might come back.',
										'Uninstalling removes the links on %1$d pages and your %2$d languages. Export first if you might come back.',
										data.counts.linked,
										'cedar-multilang'
									),
									data.counts.linked,
									data.counts.languages
								) }
							</Callout>
						) }
						<div className="cedar-data-actions">
							<Button
								icon="external-link"
								onClick={ exportSettings }
							>
								{ __( 'Export settings', 'cedar-multilang' ) }
							</Button>
							<Button
								variant="ghost"
								onClick={ () => fileRef.current.click() }
							>
								{ __( 'Import settings', 'cedar-multilang' ) }
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
					title={ __( 'Replace your settings?', 'cedar-multilang' ) }
					description={ sprintf(
						/* translators: 1: File name, 2: Number of languages, 3: Number of switchers. */
						__(
							'%1$s has %2$d languages and %3$d switcher styles. Your current settings will be replaced; links between pages are kept.',
							'cedar-multilang'
						),
						pending.name,
						pending.data.languages.languages.length,
						Array.isArray( pending.data.switchers )
							? pending.data.switchers.length
							: 0
					) }
					confirmLabel={ __( 'Replace settings', 'cedar-multilang' ) }
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

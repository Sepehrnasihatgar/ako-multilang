/**
 * SEO screen (Figma: Admin screens / Configure / SEO — *).
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import Icon from '../../components/Icon';
import { Badge, Button, LanguageTag, Select } from '../../components/controls';
import {
	Callout,
	EmptyState,
	PageTitle,
	SaveBar,
	timeAgo,
} from '../../components/display';
import { Menu } from '../../components/overlays';
import { Row, ToggleRow } from '../../components/rows';
import { duplicatePost, errorMessage } from '../api';

const BASE = '/ako/v1/seo';

/**
 * Readable URL for the preview: site origin shortened to "…", path decoded
 * ("…/درباره-ما/"). The real tags keep the encoded URL.
 *
 * @param {string} url URL.
 * @return {string} Display text.
 */
function displayUrl( url ) {
	try {
		const parsed = new window.URL( url );
		return '…' + decodeURIComponent( parsed.pathname + parsed.search );
	} catch {
		return url;
	}
}

/**
 * Decoded path ("/درباره-ما/").
 *
 * @param {string} path Encoded path.
 * @return {string} Decoded path.
 */
function displayPath( path ) {
	try {
		return decodeURIComponent( path );
	} catch {
		return path;
	}
}

export default function Seo( { config, notify } ) {
	const [ data, setData ] = useState( null );
	const [ draft, setDraft ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ working, setWorking ] = useState( null );

	const apply = useCallback( ( response ) => {
		setData( response );
		setDraft( {
			...response.settings,
			hreflang: Object.fromEntries(
				response.languages.map( ( language ) => [
					language.code,
					language.hreflang,
				] )
			),
		} );
	}, [] );

	const load = useCallback( () => {
		setLoadError( null );
		apiFetch( { path: BASE } )
			.then( apply )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'SEO settings couldn’t be loaded.',
							'ako-multilang'
						)
					)
				)
			);
	}, [ apply ] );

	useEffect( load, [ load ] );

	if ( loadError ) {
		return (
			<>
				<PageTitle
					title={ __( 'SEO', 'ako-multilang' ) }
					description=""
				/>
				<div className="ako-card">
					<EmptyState
						icon="alert"
						title={ __(
							'Couldn’t load SEO settings',
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
		return (
			<PageTitle title={ __( 'SEO', 'ako-multilang' ) } description="" />
		);
	}

	const saved = {
		...data.settings,
		hreflang: Object.fromEntries(
			data.languages.map( ( language ) => [
				language.code,
				language.hreflang,
			] )
		),
	};
	const dirty = JSON.stringify( saved ) !== JSON.stringify( draft );
	const set = ( key, value ) =>
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );

	const save = async () => {
		setSaving( true );
		try {
			apply(
				await apiFetch( { path: BASE, method: 'PUT', data: draft } )
			);
			notify( {
				tone: 'success',
				message: __( 'SEO settings saved.', 'ako-multilang' ),
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

	const run = async ( key, request, message ) => {
		setWorking( key );
		try {
			const response = await request();
			if ( response && response.settings ) {
				setData( response );
			}
			notify( { tone: 'success', message: message( response ) } );
		} catch ( error ) {
			notify( {
				tone: 'error',
				message: errorMessage(
					error,
					__( 'Something went wrong. Try again.', 'ako-multilang' )
				),
			} );
		}
		setWorking( null );
	};

	const fixOneWay = () =>
		run(
			'fix',
			() =>
				apiFetch( {
					path: `${ BASE }/fix`,
					method: 'POST',
					data: { check: 'one-way' },
				} ),
			( response ) =>
				sprintf(
					/* translators: %d: Number of fixed groups. */
					_n(
						'%d one-way link fixed.',
						'%d one-way links fixed.',
						response.fixed,
						'ako-multilang'
					),
					response.fixed
				)
		);

	const rescan = () =>
		run(
			'scan',
			() => apiFetch( { path: `${ BASE }/scan`, method: 'POST' } ),
			() => __( 'Scan complete.', 'ako-multilang' )
		);

	const duplicateExample = ( page, code ) =>
		run(
			'duplicate',
			async () => {
				await duplicatePost( page.id, code );
				return apiFetch( { path: `${ BASE }/scan`, method: 'POST' } );
			},
			() =>
				sprintf(
					/* translators: %s: Page title. */
					__( 'Draft created for “%s”.', 'ako-multilang' ),
					page.title
				)
		);

	const byCode = Object.fromEntries(
		data.languages.map( ( language ) => [ language.code, language ] )
	);
	const defaultLanguage = data.languages.find(
		( language ) => language.default
	);
	const activePlugin = data.plugins.find( ( plugin ) => plugin.active );

	return (
		<>
			<PageTitle
				title={ __( 'SEO', 'ako-multilang' ) }
				description={ __(
					'Ako adds hreflang, lang, and dir for you. Here’s exactly what search engines see.',
					'ako-multilang'
				) }
			/>

			<div className="ako-designer is-seo">
				<div className="ako-designer__column">
					<section
						className="ako-card"
						aria-labelledby="ako-hreflang-title"
					>
						<h2 id="ako-hreflang-title" className="ako-card__title">
							hreflang
						</h2>
						<Row
							label={ __( 'x-default', 'ako-multilang' ) }
							help={ __(
								'Where visitors land when no language matches.',
								'ako-multilang'
							) }
						>
							{ ( id, helpId ) => (
								<Select
									id={ id }
									aria-describedby={ helpId }
									value={ draft.x_default }
									onChange={ ( event ) =>
										set( 'x_default', event.target.value )
									}
									options={ [
										{
											value: '',
											label: defaultLanguage
												? sprintf(
														/* translators: %s: Language name. */
														__(
															'%s (default)',
															'ako-multilang'
														),
														defaultLanguage.name
													)
												: __(
														'Default language',
														'ako-multilang'
													),
										},
										...data.languages
											.filter(
												( language ) =>
													! language.default
											)
											.map( ( language ) => ( {
												value: language.code,
												label: language.name,
											} ) ),
									] }
								/>
							) }
						</Row>
						{ data.languages.map( ( language ) => (
							<Row
								key={ language.code }
								label={
									<LanguageTag
										code={ language.code }
										name={ language.name }
									/>
								}
								labelText={ sprintf(
									/* translators: %s: Language name. */
									__( 'hreflang for %s', 'ako-multilang' ),
									language.name
								) }
							>
								{ ( id ) => (
									<Select
										id={ id }
										value={
											draft.hreflang[ language.code ]
										}
										onChange={ ( event ) =>
											set( 'hreflang', {
												...draft.hreflang,
												[ language.code ]:
													event.target.value,
											} )
										}
										options={ language.options.map(
											( option ) => ( {
												value: option,
												label: option,
											} )
										) }
									/>
								) }
							</Row>
						) ) }
						<ToggleRow
							label={ __(
								'Add hreflang to the XML sitemap',
								'ako-multilang'
							) }
							badge={
								'yoast' === data.sitemap ? (
									<Badge tone="linked">Yoast</Badge>
								) : null
							}
							help={
								'yoast' === data.sitemap
									? null
									: __(
											'Your sitemap can’t list hreflang without Yoast SEO. Ako keeps it in each page’s <head>, which Google reads the same way.',
											'ako-multilang'
										)
							}
							checked={
								'yoast' === data.sitemap && draft.sitemap
							}
							disabled={ 'yoast' !== data.sitemap }
							onChange={ ( on ) => set( 'sitemap', on ) }
						/>
						<ToggleRow
							label={ __(
								'Social locale tags (og:locale)',
								'ako-multilang'
							) }
							checked={ draft.og_locale }
							onChange={ ( on ) => set( 'og_locale', on ) }
						/>
						<ToggleRow
							label={ __(
								'Skip noindex and draft pages',
								'ako-multilang'
							) }
							help={ __(
								'Pages your SEO plugin marks noindex or canonicalizes elsewhere stay out of hreflang.',
								'ako-multilang'
							) }
							checked={ draft.skip_noindex }
							onChange={ ( on ) => set( 'skip_noindex', on ) }
						/>
					</section>

					<section
						className="ako-card"
						aria-labelledby="ako-plugins-title"
					>
						<div>
							<h2
								id="ako-plugins-title"
								className="ako-card__title"
							>
								{ __( 'SEO plugins', 'ako-multilang' ) }
							</h2>
							<p className="ako-card__text">
								{ __(
									'Ako works alongside them without adding duplicate tags.',
									'ako-multilang'
								) }
							</p>
						</div>
						<ul className="ako-plugins">
							{ data.plugins.map( ( plugin ) => (
								<li key={ plugin.key }>
									<span className="ako-plugins__name">
										{ plugin.name }
									</span>
									<span className="ako-plugins__status">
										{ plugin.active
											? sprintf(
													/* translators: %s: Plugin version. */
													__(
														'Connected · %s',
														'ako-multilang'
													),
													plugin.version
												)
											: __(
													'Not installed',
													'ako-multilang'
												) }
									</span>
									{ plugin.active ? (
										<Icon
											name="circle-check"
											size={ 14 }
											className="is-success"
											label={ __(
												'Connected',
												'ako-multilang'
											) }
										/>
									) : (
										<Icon
											name="minus"
											size={ 14 }
											className="is-muted"
										/>
									) }
								</li>
							) ) }
						</ul>
						{ ! activePlugin && (
							<Callout
								icon="arrows-left-right"
								title={ __(
									'No SEO plugin? No problem.',
									'ako-multilang'
								) }
							>
								{ __(
									'Ako adds hreflang, og:locale, and the right lang and dir to every linked page.',
									'ako-multilang'
								) }
							</Callout>
						) }
					</section>
				</div>

				<div className="ako-designer__column">
					<SourcePreview pages={ data.pages } />

					<section
						className="ako-card"
						aria-labelledby="ako-health-title"
					>
						<div className="ako-card__head">
							<div>
								<h2
									id="ako-health-title"
									className="ako-card__title"
								>
									{ __( 'Health checks', 'ako-multilang' ) }
								</h2>
								<p className="ako-card__text is-small">
									{ sprintf(
										/* translators: %s: Relative time, e.g. "2 minutes ago". */
										__( 'Last scan %s', 'ako-multilang' ),
										timeAgo( data.health.scannedAt )
									) }
								</p>
							</div>
							<Button
								size="small"
								variant="ghost"
								onClick={ rescan }
								disabled={ !! working }
							>
								{ __( 'Scan again', 'ako-multilang' ) }
							</Button>
						</div>
						<HealthChecks
							health={ data.health }
							byCode={ byCode }
							working={ working }
							onFixOneWay={ fixOneWay }
							onDuplicate={ duplicateExample }
							translationsUrl={ config.urls.translations }
						/>
					</section>
				</div>
			</div>

			{ dirty && (
				<SaveBar
					saving={ saving }
					onSave={ save }
					onDiscard={ () => setDraft( saved ) }
				/>
			) }
		</>
	);
}

/**
 * "What search engines see" (Figma: Settings patterns / hreflang output).
 *
 * @param {Object} props
 * @param {Array}  props.pages Linked pages to choose from.
 */
function SourcePreview( { pages } ) {
	const [ pageId, setPageId ] = useState( pages.length ? pages[ 0 ].id : 0 );
	const [ preview, setPreview ] = useState( null );

	useEffect( () => {
		if ( ! pageId ) {
			return undefined;
		}
		let ignore = false;
		apiFetch( {
			path: addQueryArgs( `${ BASE }/preview`, { post: pageId } ),
		} )
			.then( ( response ) => ! ignore && setPreview( response ) )
			.catch( () => ! ignore && setPreview( null ) );
		return () => {
			ignore = true;
		};
	}, [ pageId ] );

	const page = pages.find( ( entry ) => entry.id === pageId );

	if ( ! pages.length ) {
		return (
			<section className="ako-source">
				<div className="ako-source__head">
					<span className="ako-source__title">
						{ __( 'What search engines see', 'ako-multilang' ) }
					</span>
				</div>
				<p className="ako-source__empty">
					{ __(
						'Link a published page to its translation to see its hreflang here.',
						'ako-multilang'
					) }
				</p>
			</section>
		);
	}

	const links = preview ? Object.entries( preview.links ) : [];
	const attr = ( name, value ) => (
		<>
			<span className="is-attr">{ name }</span>=
			<span className="is-value">&quot;{ value }&quot;</span>
		</>
	);

	return (
		<section
			className="ako-source"
			aria-label={ __( 'What search engines see', 'ako-multilang' ) }
		>
			<div className="ako-source__head">
				<span className="ako-source__title">
					{ __( 'What search engines see', 'ako-multilang' ) }
					{ preview && (
						<span className="ako-source__path" dir="ltr">
							{ displayPath( preview.path ) }
						</span>
					) }
				</span>
				<Menu
					align="end"
					label={ __( 'Page', 'ako-multilang' ) }
					renderTrigger={ ( props ) => (
						<button
							type="button"
							className="ako-source__picker"
							{ ...props }
						>
							<span dir="auto">{ page ? page.title : '' }</span>
							<Icon name="chevron-down" size={ 14 } />
						</button>
					) }
					items={ pages.map( ( entry ) => ( {
						id: String( entry.id ),
						label: entry.title,
						checked: entry.id === pageId,
						onSelect: () => setPageId( entry.id ),
					} ) ) }
				/>
			</div>
			<pre className="ako-source__code" dir="ltr">
				{ preview && (
					<>
						&lt;html { attr( 'lang', preview.lang ) }{ ' ' }
						{ attr( 'dir', preview.dir ) }&gt;
						{ '\n\n' }
						{ links.map( ( [ hreflang, url ] ) => (
							<span key={ hreflang }>
								&lt;link { attr( 'rel', 'alternate' ) }{ ' ' }
								{ attr( 'hreflang', hreflang ) }{ ' ' }
								{ attr( 'href', displayUrl( url ) ) } /&gt;
								{ '\n' }
							</span>
						) ) }
						{ ! links.length && (
							<span className="is-muted">
								{ preview.indexable
									? __(
											'<!-- No hreflang yet: link a published translation. -->',
											'ako-multilang'
										)
									: __(
											'<!-- No hreflang: this page is noindex or canonicalized elsewhere. -->',
											'ako-multilang'
										) }
								{ '\n' }
							</span>
						) }
						{ '\n' }
						&lt;meta { attr( 'property', 'og:locale' ) }{ ' ' }
						{ attr( 'content', preview.ogLocale ) } /&gt;
						{ preview.alternates.map( ( locale ) => (
							<span key={ locale }>
								{ '\n' }
								&lt;meta{ ' ' }
								{ attr(
									'property',
									'og:locale:alternate'
								) }{ ' ' }
								{ attr( 'content', locale ) } /&gt;
							</span>
						) ) }
					</>
				) }
			</pre>
			{ preview && links.length > 0 && (
				<p className="ako-source__foot">
					<Icon name="circle-check" size={ 14 } />
					{ sprintf(
						/* translators: %d: Number of pages in the group. */
						_n(
							'Reciprocal on %d page · self-referencing · canonical untouched',
							'Reciprocal on all %d pages · self-referencing · canonical untouched',
							links.length -
								( preview.links[ 'x-default' ] ? 1 : 0 ),
							'ako-multilang'
						),
						links.length - ( preview.links[ 'x-default' ] ? 1 : 0 )
					) }
				</p>
			) }
		</section>
	);
}

/**
 * Health check rows (Figma: Settings patterns / Health check row).
 *
 * @param {Object}   props
 * @param {Object}   props.health          Scan result.
 * @param {Object}   props.byCode          Languages by code.
 * @param {string}   props.working         Running action.
 * @param {Function} props.onFixOneWay     Repair one-way links.
 * @param {Function} props.onDuplicate     Duplicate an example page.
 * @param {string}   props.translationsUrl Translations screen URL.
 */
function HealthChecks( {
	health,
	byCode,
	working,
	onFixOneWay,
	onDuplicate,
	translationsUrl,
} ) {
	const rows = [];

	if ( health.oneWayAll > 0 ) {
		const first = health.oneWay[ 0 ];
		const names = first
			? first.posts.map( ( post ) => `“${ post.title }”` ).join( ', ' )
			: '';
		rows.push(
			<CheckRow
				key="one-way"
				tone="warning"
				icon="alert"
				title={ sprintf(
					/* translators: %d: Number of groups. */
					_n(
						'%d one-way link',
						'%d one-way links',
						health.oneWayAll,
						'ako-multilang'
					),
					health.oneWayAll
				) }
				description={ sprintf(
					/* translators: %s: Page titles. */
					__(
						'%s don’t all point back to each other. Fixing rebuilds the links from your pages.',
						'ako-multilang'
					),
					names
				) }
				action={
					<Button
						size="small"
						onClick={ onFixOneWay }
						disabled={ !! working }
						aria-busy={ 'fix' === working || undefined }
					>
						{ __( 'Fix both', 'ako-multilang' ) }
					</Button>
				}
			/>
		);
	}

	health.missing.forEach( ( entry ) => {
		const language = byCode[ entry.code ];
		if ( ! language ) {
			return;
		}
		const example = entry.examples[ 0 ];
		rows.push(
			<CheckRow
				key={ `missing-${ entry.code }` }
				tone="info"
				icon="file-plus"
				title={ sprintf(
					/* translators: 1: Number of pages, 2: Language name. */
					_n(
						'%1$d page without a %2$s version',
						'%1$d pages without a %2$s version',
						entry.count,
						'ako-multilang'
					),
					entry.count,
					language.name
				) }
				description={
					1 === entry.count && example
						? sprintf(
								/* translators: %s: Page title. */
								__(
									'Visitors to “%s” get the language homepage instead.',
									'ako-multilang'
								),
								example.title
							)
						: __(
								'Visitors get the language homepage instead.',
								'ako-multilang'
							)
				}
				action={
					1 === entry.count && example ? (
						<Button
							size="small"
							onClick={ () => onDuplicate( example, entry.code ) }
							disabled={ !! working }
						>
							{ __( 'Duplicate', 'ako-multilang' ) }
						</Button>
					) : (
						<Button
							size="small"
							href={ `${ translationsUrl }#missing` }
						>
							{ __( 'Review', 'ako-multilang' ) }
						</Button>
					)
				}
			/>
		);
	} );

	if ( health.excluded && health.excluded.count > 0 ) {
		rows.push(
			<CheckRow
				key="excluded"
				tone="info"
				icon="arrows-left-right"
				title={ sprintf(
					/* translators: %d: Number of pages. */
					_n(
						'%d page left out of hreflang',
						'%d pages left out of hreflang',
						health.excluded.count,
						'ako-multilang'
					),
					health.excluded.count
				) }
				description={ __(
					'They’re set to noindex, or their canonical points to another page. That’s fine if you meant it.',
					'ako-multilang'
				) }
			/>
		);
	}

	if ( ! rows.length ) {
		rows.push(
			<CheckRow
				key="ok"
				tone="success"
				icon="circle-check"
				title={ __( 'Everything checks out', 'ako-multilang' ) }
				description={ sprintf(
					/* translators: %d: Number of linked pages. */
					_n(
						'%d linked page. All hreflang links are reciprocal.',
						'%d linked pages. All hreflang links are reciprocal.',
						health.linked,
						'ako-multilang'
					),
					health.linked
				) }
			/>
		);
	} else if ( 0 === health.oneWayAll ) {
		rows.push(
			<CheckRow
				key="reciprocal"
				tone="success"
				icon="circle-check"
				title={ __( 'hreflang links are reciprocal', 'ako-multilang' ) }
				description={ sprintf(
					/* translators: %d: Number of linked pages. */
					_n(
						'%d linked page passes.',
						'All %d linked pages pass.',
						health.linked,
						'ako-multilang'
					),
					health.linked
				) }
			/>
		);
	}

	return <ul className="ako-checks">{ rows }</ul>;
}

/**
 * One health check row.
 *
 * @param {Object} props
 * @param {string} props.tone        warning|info|success.
 * @param {string} props.icon        Icon name.
 * @param {string} props.title       Title.
 * @param {string} props.description Description.
 * @param {*}      [props.action]    Button.
 */
function CheckRow( { tone, icon, title, description, action } ) {
	return (
		<li className="ako-check">
			<span
				className={ `ako-check__tile is-${ tone }` }
				aria-hidden="true"
			>
				<Icon name={ icon } />
			</span>
			<span className="ako-check__copy">
				<span className="ako-check__title">{ title }</span>
				<span className="ako-check__description" dir="auto">
					{ description }
				</span>
			</span>
			{ action }
		</li>
	);
}

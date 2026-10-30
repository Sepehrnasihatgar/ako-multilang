/**
 * About (Figma: Admin screens / Configure / About — Default, Update
 * available, System warning).
 */
import apiFetch from '@wordpress/api-fetch';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import { Badge, Button } from '../../components/controls';
import { Callout, EmptyState, Logo } from '../../components/display';
import { errorMessage } from '../api';

const REVIEWS_URL =
	'https://wordpress.org/support/plugin/ako-multilang/reviews/#new-post';

const CHANGELOG = [
	{
		version: '1.0.2',
		date: '2026-10-29',
		changes: () => [
			__(
				'Unlinking or relinking a page checks permission for every page it affects',
				'ako-multilang'
			),
			__( 'The shortcode is now [akoml_switcher]', 'ako-multilang' ),
		],
	},
	{
		version: '1.0.1',
		date: '2026-10-27',
		changes: () => [
			__(
				'The floating switcher’s arrow now points up, toward its menu',
				'ako-multilang'
			),
		],
	},
	{
		version: '1.0.0',
		date: '2026-10-27',
		changes: () => [
			__( 'Any number of languages, every post type', 'ako-multilang' ),
			__(
				'Language panel for the Block and Classic editors',
				'ako-multilang'
			),
			__( 'Five switcher styles plus inherit mode', 'ako-multilang' ),
			__(
				'hreflang and og:locale that work with Yoast SEO, Rank Math, and AIOSEO',
				'ako-multilang'
			),
			__( 'Import from WPML and Polylang', 'ako-multilang' ),
		],
	},
];

/**
 * "Oct 2026" in the admin's language.
 *
 * @param {string} date ISO date.
 * @return {string} Month and year.
 */
function monthYear( date ) {
	try {
		return new Intl.DateTimeFormat( document.documentElement.lang || 'en', {
			month: 'short',
			year: 'numeric',
		} ).format( new Date( `${ date }T12:00:00Z` ) );
	} catch {
		return date.slice( 0, 7 );
	}
}

const STATUS_ICONS = {
	ok: { name: 'circle-check', className: 'is-success' },
	warning: { name: 'alert', className: 'is-warning' },
	none: { name: 'minus', className: 'is-muted' },
};

/**
 * Plain-text report for "Copy system info".
 *
 * @param {Object} system System data.
 * @return {string} Report.
 */
function report( system ) {
	return [
		`Ako Multilang ${ system.version }`,
		...system.checks.map(
			( check ) => `${ check.label }: ${ check.value }`
		),
	].join( '\n' );
}

export default function About( { config, notify } ) {
	const [ system, setSystem ] = useState( null );
	const [ loadError, setLoadError ] = useState( null );

	const load = useCallback( () => {
		setLoadError( null );
		apiFetch( { path: '/akoml/v1/system' } )
			.then( setSystem )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'System status couldn’t be loaded.',
							'ako-multilang'
						)
					)
				)
			);
	}, [] );

	useEffect( load, [ load ] );

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( report( system ) );
			notify( {
				tone: 'success',
				message: __( 'System info copied.', 'ako-multilang' ),
			} );
		} catch {
			notify( {
				tone: 'error',
				message: __(
					'Your browser blocked copying. Select the rows and copy them instead.',
					'ako-multilang'
				),
			} );
		}
	};

	const warnings = system
		? system.checks.filter( ( check ) => 'warning' === check.status )
		: [];

	return (
		<div className="ako-designer is-about">
			<div className="ako-designer__column">
				<section className="ako-card ako-product">
					<div className="ako-product__head">
						<Logo size={ 56 } className="ako-product__logo" />
						<div>
							<div className="ako-product__title">
								<h1>
									{ __( 'Ako Multilang', 'ako-multilang' ) }
								</h1>
								{ system &&
									( system.update ? (
										<Badge tone="info" dot>
											{ sprintf(
												/* translators: %s: Version number. */
												__(
													'%s available',
													'ako-multilang'
												),
												system.update.version
											) }
										</Badge>
									) : (
										<Badge tone="linked" dot>
											{ __(
												'Up to date',
												'ako-multilang'
											) }
										</Badge>
									) ) }
							</div>
							<p className="ako-product__meta">
								{ sprintf(
									/* translators: %s: Version number. */
									__(
										'Version %s · GPLv2 or later',
										'ako-multilang'
									),
									config.version
								) }
							</p>
						</div>
					</div>
					{ system && system.update && (
						<Callout
							icon="arrows-left-right"
							title={ sprintf(
								/* translators: %s: Version number. */
								__( 'Version %s is ready', 'ako-multilang' ),
								system.update.version
							) }
							action={
								system.update.url && (
									<Button href={ system.update.url }>
										{ __( 'Update now', 'ako-multilang' ) }
									</Button>
								)
							}
						>
							{ system.update.notice }
						</Callout>
					) }
					<div className="ako-product__links">
						<Button
							icon="external-link"
							href={ REVIEWS_URL }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Rate on WordPress.org', 'ako-multilang' ) }
							<span className="screen-reader-text">
								{ __(
									'(opens in a new tab)',
									'ako-multilang'
								) }
							</span>
						</Button>
					</div>
				</section>

				<section
					className="ako-card"
					aria-labelledby="ako-changes-title"
				>
					<h2 id="ako-changes-title" className="ako-card__title">
						{ __( 'What’s new', 'ako-multilang' ) }
					</h2>
					{ CHANGELOG.map( ( release ) => (
						<div key={ release.version } className="ako-release">
							<h3 className="ako-release__head">
								{ release.version }
								<span className="ako-release__date">
									{ monthYear( release.date ) }
								</span>
							</h3>
							<ul className="ako-release__list">
								{ release.changes().map( ( change ) => (
									<li key={ change }>{ change }</li>
								) ) }
							</ul>
						</div>
					) ) }
				</section>
			</div>

			<div className="ako-designer__column">
				<section
					className="ako-card"
					aria-labelledby="ako-system-title"
				>
					<div className="ako-card__head">
						<h2 id="ako-system-title" className="ako-card__title">
							{ __( 'System status', 'ako-multilang' ) }
						</h2>
						{ system && (
							<p className="ako-card__text">
								{ warnings.length
									? sprintf(
											/* translators: %d: Number of problems. */
											_n(
												'%d thing needs attention',
												'%d things need attention',
												warnings.length,
												'ako-multilang'
											),
											warnings.length
										)
									: __(
											'Everything Ako needs is in place.',
											'ako-multilang'
										) }
							</p>
						) }
					</div>
					{ loadError && (
						<EmptyState
							icon="alert"
							title={ __(
								'Couldn’t load system status',
								'ako-multilang'
							) }
							description={ loadError }
							actions={
								<Button variant="primary" onClick={ load }>
									{ __( 'Try again', 'ako-multilang' ) }
								</Button>
							}
						/>
					) }
					{ system && (
						<>
							<dl className="ako-status">
								{ system.checks.map( ( check ) => {
									const icon =
										STATUS_ICONS[ check.status ] ||
										STATUS_ICONS.none;
									return (
										<div
											key={ check.key }
											className="ako-status__row"
										>
											<dt>{ check.label }</dt>
											<dd>
												<span>{ check.value }</span>
												<Icon
													name={ icon.name }
													className={ icon.className }
													label={
														'warning' ===
														check.status
															? __(
																	'Needs attention',
																	'ako-multilang'
																)
															: undefined
													}
												/>
											</dd>
										</div>
									);
								} ) }
							</dl>
							{ warnings.map( ( check ) => (
								<Callout
									key={ check.key }
									tone="warning"
									icon="alert"
									title={ check.title }
								>
									{ check.message }
								</Callout>
							) ) }
							<div>
								<Button icon="copy" onClick={ copy }>
									{ __(
										'Copy system info',
										'ako-multilang'
									) }
								</Button>
							</div>
						</>
					) }
				</section>

				<section
					className="ako-card"
					aria-labelledby="ako-credits-title"
				>
					<h2 id="ako-credits-title" className="ako-card__title">
						{ __( 'Credits', 'ako-multilang' ) }
					</h2>
					<p className="ako-card__text">
						{ __( 'Built by Sepehr Nasihatgar', 'ako-multilang' ) }
					</p>
					<p className="ako-card__text">
						{ __(
							'Icons: Lucide (ISC) · Fonts: Inter and Vazirmatn (OFL)',
							'ako-multilang'
						) }
					</p>
				</section>
			</div>
		</div>
	);
}

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
	'https://wordpress.org/support/plugin/cedar-multilang/reviews/#new-post';

const CHANGELOG = [
	{
		version: '1.0.0',
		date: '2026-10-01',
		changes: () => [
			__( 'Any number of languages, every post type', 'cedar-multilang' ),
			__(
				'Language panel for the Block and Classic editors',
				'cedar-multilang'
			),
			__( 'Five switcher styles plus inherit mode', 'cedar-multilang' ),
			__(
				'hreflang and og:locale that work with Yoast SEO, Rank Math and AIOSEO',
				'cedar-multilang'
			),
			__( 'Import from WPML, Polylang and Cedar 6.2', 'cedar-multilang' ),
		],
	},
	{
		version: '6.2',
		date: '2025-07-01',
		changes: () => [
			__(
				'Private release for one Persian/English site. Data migrates automatically.',
				'cedar-multilang'
			),
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
		`Cedar Multilang ${ system.version }`,
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
		apiFetch( { path: '/cedar/v1/system' } )
			.then( setSystem )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__(
							'System status couldn’t be loaded.',
							'cedar-multilang'
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
				message: __( 'System info copied.', 'cedar-multilang' ),
			} );
		} catch {
			notify( {
				tone: 'error',
				message: __(
					'Your browser blocked copying. Select the rows and copy them instead.',
					'cedar-multilang'
				),
			} );
		}
	};

	const warnings = system
		? system.checks.filter( ( check ) => 'warning' === check.status )
		: [];

	return (
		<div className="cedar-designer is-about">
			<div className="cedar-designer__column">
				<section className="cedar-card cedar-product">
					<div className="cedar-product__head">
						<Logo size={ 56 } className="cedar-product__logo" />
						<div>
							<div className="cedar-product__title">
								<h1>
									{ __(
										'Cedar Multilang',
										'cedar-multilang'
									) }
								</h1>
								{ system &&
									( system.update ? (
										<Badge tone="info" dot>
											{ sprintf(
												/* translators: %s: Version number. */
												__(
													'%s available',
													'cedar-multilang'
												),
												system.update.version
											) }
										</Badge>
									) : (
										<Badge tone="linked" dot>
											{ __(
												'Up to date',
												'cedar-multilang'
											) }
										</Badge>
									) ) }
							</div>
							<p className="cedar-product__meta">
								{ sprintf(
									/* translators: %s: Version number. */
									__(
										'Version %s · GPLv2 or later',
										'cedar-multilang'
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
								__( 'Version %s is ready', 'cedar-multilang' ),
								system.update.version
							) }
							action={
								system.update.url && (
									<Button href={ system.update.url }>
										{ __(
											'Update now',
											'cedar-multilang'
										) }
									</Button>
								)
							}
						>
							{ system.update.notice }
						</Callout>
					) }
					<div className="cedar-product__links">
						<Button
							icon="external-link"
							href={ REVIEWS_URL }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Rate on WordPress.org', 'cedar-multilang' ) }
							<span className="screen-reader-text">
								{ __(
									'(opens in a new tab)',
									'cedar-multilang'
								) }
							</span>
						</Button>
					</div>
				</section>

				<section
					className="cedar-card"
					aria-labelledby="cedar-changes-title"
				>
					<h2 id="cedar-changes-title" className="cedar-card__title">
						{ __( 'What’s new', 'cedar-multilang' ) }
					</h2>
					{ CHANGELOG.map( ( release ) => (
						<div key={ release.version } className="cedar-release">
							<h3 className="cedar-release__head">
								{ release.version }
								<span className="cedar-release__date">
									{ monthYear( release.date ) }
								</span>
							</h3>
							<ul className="cedar-release__list">
								{ release.changes().map( ( change ) => (
									<li key={ change }>{ change }</li>
								) ) }
							</ul>
						</div>
					) ) }
				</section>
			</div>

			<div className="cedar-designer__column">
				<section
					className="cedar-card"
					aria-labelledby="cedar-system-title"
				>
					<div className="cedar-card__head">
						<h2
							id="cedar-system-title"
							className="cedar-card__title"
						>
							{ __( 'System status', 'cedar-multilang' ) }
						</h2>
						{ system && (
							<p className="cedar-card__text">
								{ warnings.length
									? sprintf(
											/* translators: %d: Number of problems. */
											_n(
												'%d thing needs attention',
												'%d things need attention',
												warnings.length,
												'cedar-multilang'
											),
											warnings.length
										)
									: __(
											'Everything Cedar needs is in place.',
											'cedar-multilang'
										) }
							</p>
						) }
					</div>
					{ loadError && (
						<EmptyState
							icon="alert"
							title={ __(
								'Couldn’t load system status',
								'cedar-multilang'
							) }
							description={ loadError }
							actions={
								<Button variant="primary" onClick={ load }>
									{ __( 'Try again', 'cedar-multilang' ) }
								</Button>
							}
						/>
					) }
					{ system && (
						<>
							<dl className="cedar-status">
								{ system.checks.map( ( check ) => {
									const icon =
										STATUS_ICONS[ check.status ] ||
										STATUS_ICONS.none;
									return (
										<div
											key={ check.key }
											className="cedar-status__row"
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
																	'cedar-multilang'
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
										'cedar-multilang'
									) }
								</Button>
							</div>
						</>
					) }
				</section>

				<section
					className="cedar-card"
					aria-labelledby="cedar-credits-title"
				>
					<h2 id="cedar-credits-title" className="cedar-card__title">
						{ __( 'Credits', 'cedar-multilang' ) }
					</h2>
					<p className="cedar-card__text">
						{ __(
							'Built by Sepehr Nasihatgar',
							'cedar-multilang'
						) }
					</p>
					<p className="cedar-card__text">
						{ __(
							'Icons: Lucide (ISC) · Fonts: Inter and Vazirmatn (OFL)',
							'cedar-multilang'
						) }
					</p>
				</section>
			</div>
		</div>
	);
}

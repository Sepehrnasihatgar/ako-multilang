/**
 * Setup step 4 (Figma: Onboarding / 4 · Done — All set, Pages skipped).
 * Reaching this step finishes setup.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import { Button } from '../../components/controls';
import { Callout } from '../../components/display';
import { completeSetup, errorMessage, getSetupSummary } from '../api';

function Stat( { value, label, tone } ) {
	return (
		<div className={ `cedar-stat is-${ tone }` }>
			<span className="cedar-stat__value">{ value }</span>
			<span className="cedar-stat__label">{ label }</span>
		</div>
	);
}

function NextStep( { icon, title, description, action, href } ) {
	return (
		<li className="cedar-check">
			<span className="cedar-check__tile is-neutral" aria-hidden="true">
				<Icon name={ icon } />
			</span>
			<span className="cedar-check__copy">
				<span className="cedar-check__title">{ title }</span>
				<span className="cedar-check__description">
					{ description }
				</span>
			</span>
			<Button size="small" href={ href }>
				{ action }
			</Button>
		</li>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.config App config.
 * @param {Function} props.notify Toast.
 */
export default function DoneStep( { config, notify } ) {
	const [ summary, setSummary ] = useState( null );

	useEffect( () => {
		completeSetup()
			.then( getSetupSummary )
			.then( setSummary )
			.catch( ( error ) => {
				notify( {
					tone: 'error',
					message: errorMessage(
						error,
						__( 'Setup couldn’t be finished.', 'cedar-multilang' )
					),
				} );
				setSummary( false );
			} );
	}, [] );

	if ( null === summary ) {
		return (
			<p className="cedar-setup__loading" role="status">
				{ __( 'Wrapping up…', 'cedar-multilang' ) }
			</p>
		);
	}

	const urls = config.urls;
	const live = summary && summary.linked > 0;
	const zeroTone = live ? 'success' : 'warning';

	return (
		<>
			<div className="cedar-setup__intro">
				<h1 tabIndex={ -1 }>
					{ live
						? sprintf(
								/* translators: %d: Number of languages. */
								_n(
									'You’re live in %d language',
									'You’re live in %d languages',
									summary.languages,
									'cedar-multilang'
								),
								summary.languages
							)
						: __( 'Almost there', 'cedar-multilang' ) }
				</h1>
				<p>
					{ live
						? __(
								'hreflang, lang and dir, and the switcher are on. Here’s what Cedar set up and what’s left to do.',
								'cedar-multilang'
							)
						: __(
								'Languages and the switcher are set. Link at least two pages and hreflang turns on automatically.',
								'cedar-multilang'
							) }
				</p>
			</div>

			{ summary && (
				<div className="cedar-stats">
					<Stat
						value={ summary.languages }
						label={ _n(
							'language',
							'languages',
							summary.languages,
							'cedar-multilang'
						) }
						tone={ live ? 'success' : 'neutral' }
					/>
					<Stat
						value={ summary.linked }
						label={ _n(
							'page linked',
							'pages linked',
							summary.linked,
							'cedar-multilang'
						) }
						tone={ zeroTone }
					/>
					<Stat
						value={ summary.hreflang }
						label={ _n(
							'hreflang link',
							'hreflang links',
							summary.hreflang,
							'cedar-multilang'
						) }
						tone={ zeroTone }
					/>
					<Stat
						value={ summary.switchers }
						label={ _n(
							'switcher placed',
							'switchers placed',
							summary.switchers,
							'cedar-multilang'
						) }
						tone={ live ? 'success' : 'neutral' }
					/>
				</div>
			) }

			{ summary && ! live && (
				<Callout
					tone="warning"
					icon="alert"
					title={ __( 'No pages are linked yet', 'cedar-multilang' ) }
				>
					{ __(
						'Until two pages are linked, no hreflang is written and the switcher sends every language to your home page.',
						'cedar-multilang'
					) }
				</Callout>
			) }

			<div className="cedar-field">
				<h2 className="cedar-field__label">
					{ __( 'Next steps', 'cedar-multilang' ) }
				</h2>
				<ul className="cedar-checks cedar-next-steps">
					{ summary && ! live && (
						<NextStep
							icon="link"
							title={ __(
								'Connect your first pages',
								'cedar-multilang'
							) }
							description={ __(
								'Pick a page and its translation. It takes about a minute.',
								'cedar-multilang'
							) }
							action={ __( 'Connect', 'cedar-multilang' ) }
							href={ urls.translations }
						/>
					) }
					{ summary && summary.missing && (
						<NextStep
							icon="file-plus"
							title={ sprintf(
								/* translators: 1: Number of pages, 2: Language name. */
								_n(
									'%1$d page has no %2$s version',
									'%1$d pages have no %2$s version',
									summary.missing.count,
									'cedar-multilang'
								),
								summary.missing.count,
								summary.missing.name
							) }
							description={ __(
								'Duplicate them as drafts in one click.',
								'cedar-multilang'
							) }
							action={ __( 'Review', 'cedar-multilang' ) }
							href={ `${ urls.translations }#missing` }
						/>
					) }
					{ summary && summary.oneWay > 0 && urls.seo && (
						<NextStep
							icon="alert"
							title={ __(
								'Check SEO health',
								'cedar-multilang'
							) }
							description={ sprintf(
								/* translators: %d: Number of one-way links. */
								_n(
									'%d one-way link was found.',
									'%d one-way links were found.',
									summary.oneWay,
									'cedar-multilang'
								),
								summary.oneWay
							) }
							action={ __( 'Open SEO', 'cedar-multilang' ) }
							href={ urls.seo }
						/>
					) }
					{ urls.switcher && (
						<NextStep
							icon="settings-2"
							title={ __(
								'Make the switcher yours',
								'cedar-multilang'
							) }
							description={ __(
								'Change colors, corners, and labels with a live preview.',
								'cedar-multilang'
							) }
							action={ __( 'Customize', 'cedar-multilang' ) }
							href={ urls.switcher }
						/>
					) }
				</ul>
			</div>

			<div className="cedar-setup__footer">
				<span />
				<Button variant="primary" href={ urls.translations }>
					{ __( 'Go to Translations', 'cedar-multilang' ) }
				</Button>
			</div>
		</>
	);
}

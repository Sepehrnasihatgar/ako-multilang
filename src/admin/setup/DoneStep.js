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
		<div className={ `ako-stat is-${ tone }` }>
			<span className="ako-stat__value">{ value }</span>
			<span className="ako-stat__label">{ label }</span>
		</div>
	);
}

function NextStep( { icon, title, description, action, href } ) {
	return (
		<li className="ako-check">
			<span className="ako-check__tile is-neutral" aria-hidden="true">
				<Icon name={ icon } />
			</span>
			<span className="ako-check__copy">
				<span className="ako-check__title">{ title }</span>
				<span className="ako-check__description">{ description }</span>
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
						__( 'Setup couldn’t be finished.', 'ako-multilang' )
					),
				} );
				setSummary( false );
			} );
	}, [] );

	if ( null === summary ) {
		return (
			<p className="ako-setup__loading" role="status">
				{ __( 'Wrapping up…', 'ako-multilang' ) }
			</p>
		);
	}

	const urls = config.urls;
	const live = summary && summary.linked > 0;
	const zeroTone = live ? 'success' : 'warning';

	return (
		<>
			<div className="ako-setup__intro">
				<h1 tabIndex={ -1 }>
					{ live
						? sprintf(
								/* translators: %d: Number of languages. */
								_n(
									'You’re live in %d language',
									'You’re live in %d languages',
									summary.languages,
									'ako-multilang'
								),
								summary.languages
							)
						: __( 'Almost there', 'ako-multilang' ) }
				</h1>
				<p>
					{ live
						? __(
								'hreflang, lang and dir, and the switcher are on. Here’s what Ako set up and what’s left to do.',
								'ako-multilang'
							)
						: __(
								'Languages and the switcher are set. Link at least two pages and hreflang turns on automatically.',
								'ako-multilang'
							) }
				</p>
			</div>

			{ summary && (
				<div className="ako-stats">
					<Stat
						value={ summary.languages }
						label={ _n(
							'language',
							'languages',
							summary.languages,
							'ako-multilang'
						) }
						tone={ live ? 'success' : 'neutral' }
					/>
					<Stat
						value={ summary.linked }
						label={ _n(
							'page linked',
							'pages linked',
							summary.linked,
							'ako-multilang'
						) }
						tone={ zeroTone }
					/>
					<Stat
						value={ summary.hreflang }
						label={ _n(
							'hreflang link',
							'hreflang links',
							summary.hreflang,
							'ako-multilang'
						) }
						tone={ zeroTone }
					/>
					<Stat
						value={ summary.switchers }
						label={ _n(
							'switcher placed',
							'switchers placed',
							summary.switchers,
							'ako-multilang'
						) }
						tone={ live ? 'success' : 'neutral' }
					/>
				</div>
			) }

			{ summary && ! live && (
				<Callout
					tone="warning"
					icon="alert"
					title={ __( 'No pages are linked yet', 'ako-multilang' ) }
				>
					{ __(
						'Until two pages are linked, no hreflang is written and the switcher sends every language to your home page.',
						'ako-multilang'
					) }
				</Callout>
			) }

			<div className="ako-field">
				<h2 className="ako-field__label">
					{ __( 'Next steps', 'ako-multilang' ) }
				</h2>
				<ul className="ako-checks ako-next-steps">
					{ summary && ! live && (
						<NextStep
							icon="link"
							title={ __(
								'Connect your first pages',
								'ako-multilang'
							) }
							description={ __(
								'Pick a page and its translation. It takes about a minute.',
								'ako-multilang'
							) }
							action={ __( 'Connect', 'ako-multilang' ) }
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
									'ako-multilang'
								),
								summary.missing.count,
								summary.missing.name
							) }
							description={ __(
								'Duplicate them as drafts in one click.',
								'ako-multilang'
							) }
							action={ __( 'Review', 'ako-multilang' ) }
							href={ `${ urls.translations }#missing` }
						/>
					) }
					{ summary && summary.oneWay > 0 && urls.seo && (
						<NextStep
							icon="alert"
							title={ __( 'Check SEO health', 'ako-multilang' ) }
							description={ sprintf(
								/* translators: %d: Number of one-way links. */
								_n(
									'%d one-way link was found.',
									'%d one-way links were found.',
									summary.oneWay,
									'ako-multilang'
								),
								summary.oneWay
							) }
							action={ __( 'Open SEO', 'ako-multilang' ) }
							href={ urls.seo }
						/>
					) }
					{ urls.switcher && (
						<NextStep
							icon="settings-2"
							title={ __(
								'Make the switcher yours',
								'ako-multilang'
							) }
							description={ __(
								'Change colors, corners, and labels with a live preview.',
								'ako-multilang'
							) }
							action={ __( 'Customize', 'ako-multilang' ) }
							href={ urls.switcher }
						/>
					) }
				</ul>
			</div>

			<div className="ako-setup__footer">
				<span />
				<Button variant="primary" href={ urls.translations }>
					{ __( 'Go to Translations', 'ako-multilang' ) }
				</Button>
			</div>
		</>
	);
}

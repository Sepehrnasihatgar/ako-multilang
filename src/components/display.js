/**
 * Display — Figma sections "Data display", "Feedback / Empty state" and
 * "Layout & brand / Cedar / Header".
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from './Icon';
import { Button, LanguageTag } from './controls';

/**
 * Cedar / Header: logo, product name and version.
 *
 * @param {Object} props
 * @param {string} props.version Plugin version.
 */
export function Header( { version } ) {
	return (
		<header className="cedar-header">
			<div className="cedar-header__brand">
				<svg
					className="cedar-header__logo"
					width="32"
					height="32"
					viewBox="0 0 32 32"
					aria-hidden="true"
					focusable="false"
				>
					<rect
						width="32"
						height="32"
						rx="8"
						className="cedar-logo__tile"
					/>
					<path
						className="cedar-logo__mark"
						d="M16 5.5 10.5 12h3L9 17.5h3.5l-5 6h17l-5-6H23L18.5 12h3L16 5.5Z"
					/>
					<path
						className="cedar-logo__mark"
						d="M15.55 23.5h.9c.44 0 .8.36.8.8v1.9c0 .44-.36.8-.8.8h-.9a.8.8 0 0 1-.8-.8v-1.9c0-.44.36-.8.8-.8Z"
					/>
				</svg>
				<span className="cedar-header__name">
					<span className="cedar-header__product">
						{ __( 'Cedar', 'cedar-multilang' ) }
					</span>
					<span className="cedar-header__version">
						{ sprintf(
							/* translators: %s: Plugin version number. */
							__( 'Multilang · %s', 'cedar-multilang' ),
							version.replace( /\.0$/, '' )
						) }
					</span>
				</span>
			</div>
		</header>
	);
}

/**
 * Page title row: title, description and optional actions.
 *
 * @param {Object} props
 * @param {string} props.title       Page title (the screen's only h1).
 * @param {string} props.description One-line description.
 * @param {*}      [props.actions]   Buttons on the end side.
 */
export function PageTitle( { title, description, actions } ) {
	return (
		<div className="cedar-page-title">
			<div className="cedar-page-title__copy">
				<h1>{ title }</h1>
				<p>{ description }</p>
			</div>
			{ actions && (
				<div className="cedar-page-title__actions">{ actions }</div>
			) }
		</div>
	);
}

/**
 * Coverage card: a language and how much of the source it covers.
 *
 * @param {Object}  props
 * @param {Object}  props.language { code, native }.
 * @param {number}  props.value    Linked (or complete) count.
 * @param {number}  props.total    Source total.
 * @param {string}  props.caption  Caption under the bar.
 * @param {boolean} [props.source] Source language card.
 */
export function CoverageCard( {
	language,
	value,
	total,
	caption,
	source = false,
} ) {
	const percent = total ? Math.floor( ( value / total ) * 100 ) : 0;

	return (
		<div className="cedar-coverage-card">
			<div className="cedar-coverage-card__top">
				<LanguageTag code={ language.code } name={ language.native } />
				<span className="cedar-coverage-card__value">
					{ source
						? __( 'Source', 'cedar-multilang' )
						: sprintf(
								/* translators: %d: Percentage. */
								__( '%d%%', 'cedar-multilang' ),
								percent
							) }
				</span>
			</div>
			<div
				className="cedar-progress"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ total }
				aria-valuenow={ value }
				aria-label={ caption }
			>
				<span
					className="cedar-progress__value"
					style={ { inlineSize: `${ percent }%` } }
				/>
			</div>
			<p className="cedar-coverage-card__caption">{ caption }</p>
		</div>
	);
}

/**
 * Empty state.
 *
 * @param {Object} props
 * @param {string} props.icon        Icon name.
 * @param {string} props.title       Title.
 * @param {string} props.description Description.
 * @param {*}      [props.actions]   Buttons.
 */
export function EmptyState( { icon, title, description, actions } ) {
	return (
		<div className="cedar-empty">
			<span className="cedar-empty__tile" aria-hidden="true">
				<Icon name={ icon } size={ 24 } />
			</span>
			<h2 className="cedar-empty__title">{ title }</h2>
			<p className="cedar-empty__description">{ description }</p>
			{ actions && (
				<div className="cedar-empty__actions">{ actions }</div>
			) }
		</div>
	);
}

/**
 * Skeleton row (loading).
 *
 * @param {Object} props
 * @param {number} props.columns Number of language columns.
 */
export function SkeletonRow( { columns } ) {
	return (
		<div className="cedar-row is-skeleton" aria-hidden="true">
			<span className="cedar-skeleton is-box" />
			<span className="cedar-row__page">
				<span className="cedar-skeleton is-title" />
				<span className="cedar-skeleton is-slug" />
			</span>
			{ Array.from( { length: columns } ).map( ( unused, index ) => (
				<span key={ index } className="cedar-skeleton is-cell" />
			) ) }
			<span className="cedar-skeleton is-date" />
			<span />
		</div>
	);
}

/**
 * Save bar (Figma: Settings patterns / Save bar). Shown while there are
 * unsaved changes.
 *
 * @param {Object}   props
 * @param {boolean}  props.saving    Saving now.
 * @param {Function} props.onSave    Save.
 * @param {Function} props.onDiscard Discard.
 */
export function SaveBar( { saving, onSave, onDiscard } ) {
	return (
		<div
			className="cedar-savebar"
			role="region"
			aria-label={ __( 'Unsaved changes', 'cedar-multilang' ) }
		>
			<Icon name="alert" />
			<span className="cedar-savebar__message">
				{ saving
					? __( 'Saving…', 'cedar-multilang' )
					: __( 'You have unsaved changes', 'cedar-multilang' ) }
			</span>
			<Button size="small" onClick={ onDiscard } disabled={ saving }>
				{ __( 'Discard', 'cedar-multilang' ) }
			</Button>
			<Button
				size="small"
				onClick={ onSave }
				disabled={ saving }
				aria-busy={ saving || undefined }
			>
				{ __( 'Save changes', 'cedar-multilang' ) }
			</Button>
		</div>
	);
}

/**
 * "2 minutes ago" from a Unix timestamp.
 *
 * @param {number} timestamp Seconds since epoch.
 * @return {string} Relative time.
 */
export function timeAgo( timestamp ) {
	const seconds = Math.max( 0, Math.round( Date.now() / 1000 - timestamp ) );
	if ( seconds < 60 ) {
		return __( 'just now', 'cedar-multilang' );
	}
	const minutes = Math.round( seconds / 60 );
	if ( minutes < 60 ) {
		return sprintf(
			/* translators: %d: Number of minutes. */
			_n( '%d minute ago', '%d minutes ago', minutes, 'cedar-multilang' ),
			minutes
		);
	}
	const hours = Math.round( minutes / 60 );
	if ( hours < 24 ) {
		return sprintf(
			/* translators: %d: Number of hours. */
			_n( '%d hour ago', '%d hours ago', hours, 'cedar-multilang' ),
			hours
		);
	}
	const days = Math.round( hours / 24 );
	return sprintf(
		/* translators: %d: Number of days. */
		_n( '%d day ago', '%d days ago', days, 'cedar-multilang' ),
		days
	);
}

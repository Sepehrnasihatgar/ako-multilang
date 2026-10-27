/**
 * Setup step 1 (Figma: Onboarding / 1 · Languages — Default, Searching,
 * No match, Nothing added yet).
 */
import { useId, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import { Badge, Button, LanguageTag, Select } from '../../components/controls';
import { Callout } from '../../components/display';
import { errorMessage, saveSetupLanguages } from '../api';
import { searchCatalog } from '../catalog';

/**
 * Starting languages: the saved ones, or the site language as the default
 * plus languages Cedar found in the site's pages (titles or a /fa/ folder).
 *
 * @param {Object} state   Setup state.
 * @param {Array}  catalog Catalog.
 * @return {Object} { defaultLocale, others, found }.
 */
function initial( state, catalog ) {
	const saved = state.languages.languages;
	const current = saved.find( ( language ) => language.default );
	if ( current ) {
		return {
			defaultLocale: current.locale,
			others: saved.filter( ( language ) => ! language.default ),
			found: [],
		};
	}
	const site = catalog.find( ( entry ) => entry.locale === state.siteLocale );
	const defaultLocale = site ? site.locale : 'en_US';
	const found = ( state.suggested || [] )
		.filter( ( suggestion ) => 'installed' !== suggestion.reason )
		.map( ( suggestion ) =>
			catalog.find( ( entry ) => entry.locale === suggestion.locale )
		)
		.filter( ( entry ) => entry && entry.locale !== defaultLocale );
	return {
		defaultLocale,
		others: found,
		found: found.map( ( e ) => e.locale ),
	};
}

/**
 * @param {Object}   props
 * @param {Object}   props.state        Setup state.
 * @param {Array}    props.catalog      Language catalog.
 * @param {string}   props.languagesUrl Languages screen URL.
 * @param {Function} props.onSaved      Called with the saved languages.
 */
export default function LanguagesStep( {
	state,
	catalog,
	languagesUrl,
	onSaved,
} ) {
	const start = useMemo( () => initial( state, catalog ), [] );
	const [ defaultLocale, setDefaultLocale ] = useState( start.defaultLocale );
	const [ others, setOthers ] = useState( start.others );
	const [ text, setText ] = useState( '' );
	const [ active, setActive ] = useState( 0 );
	const [ touched, setTouched ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const inputRef = useRef();
	const listId = useId();
	const defaultId = useId();
	const defaultHelpId = useId();
	const searchHelpId = useId();

	const savedDefault = state.languages.languages.find(
		( language ) => language.default
	);
	const locked = state.defaultLocked && !! savedDefault;

	const defaultOptions = useMemo( () => {
		const options = [ ...catalog ]
			.sort( ( a, b ) => a.name.localeCompare( b.name ) )
			.map( ( entry ) => ( {
				value: entry.locale,
				label: `${ entry.name } (${ entry.locale })`,
			} ) );
		if (
			savedDefault &&
			! catalog.some( ( entry ) => entry.locale === savedDefault.locale )
		) {
			options.unshift( {
				value: savedDefault.locale,
				label: `${ savedDefault.name } (${ savedDefault.locale })`,
			} );
		}
		return options;
	}, [ catalog ] );

	const taken = new Set( others.map( ( language ) => language.locale ) );
	const matches = searchCatalog( catalog, text, 6 ).filter(
		( entry ) => entry.locale !== defaultLocale
	);
	const open = '' !== text.trim();
	const empty = 0 === others.length;
	const showError = empty && touched;
	const foundShown = others.filter( ( language ) =>
		start.found.includes( language.locale )
	);
	const offers = ( state.suggested || [] )
		.map( ( suggestion ) =>
			catalog.find( ( entry ) => entry.locale === suggestion.locale )
		)
		.filter(
			( entry ) =>
				entry &&
				entry.locale !== defaultLocale &&
				! taken.has( entry.locale )
		)
		.slice( 0, 4 );

	const add = ( entry ) => {
		if ( taken.has( entry.locale ) ) {
			return;
		}
		setOthers( [ ...others, entry ] );
		setText( '' );
		setActive( 0 );
		setError( '' );
		inputRef.current.focus();
	};

	const remove = ( locale ) => {
		setOthers(
			others.filter( ( language ) => language.locale !== locale )
		);
		setTouched( true );
		inputRef.current.focus();
	};

	const changeDefault = ( locale ) => {
		setDefaultLocale( locale );
		setOthers(
			others.filter( ( language ) => language.locale !== locale )
		);
	};

	const onKeyDown = ( event ) => {
		if ( ! open ) {
			return;
		}
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			if ( matches.length ) {
				const step = 'ArrowDown' === event.key ? 1 : -1;
				setActive(
					( active + step + matches.length ) % matches.length
				);
			}
		} else if ( 'Enter' === event.key ) {
			event.preventDefault();
			if ( matches[ active ] ) {
				add( matches[ active ] );
			}
		} else if ( 'Escape' === event.key ) {
			event.preventDefault();
			setText( '' );
		}
	};

	const save = async () => {
		setSaving( true );
		setError( '' );
		try {
			const languages = await saveSetupLanguages(
				defaultLocale,
				others.map( ( language ) => language.locale )
			);
			setSaving( false );
			return languages;
		} catch ( failure ) {
			setError(
				errorMessage(
					failure,
					__( 'Your languages couldn’t be saved.', 'cedar-multilang' )
				)
			);
			setSaving( false );
			return null;
		}
	};

	const next = async () => {
		if ( empty ) {
			setTouched( true );
			return;
		}
		const languages = await save();
		if ( languages ) {
			onSaved( languages );
		}
	};

	// Custom languages are added on the Languages screen; keep what's picked so far.
	const addCustom = async () => {
		if ( await save() ) {
			window.location.assign( `${ languagesUrl }#custom` );
		}
	};

	const activeMatch = matches[ active ];

	return (
		<>
			<div className="cedar-setup__intro">
				<h1 tabIndex={ -1 }>
					{ __(
						'Which languages does your site speak?',
						'cedar-multilang'
					) }
				</h1>
				<p>
					{ __(
						'Pick your main language, then add the others. Text direction is automatic, so Farsi and Arabic pages switch to right-to-left.',
						'cedar-multilang'
					) }
				</p>
			</div>

			<div className="cedar-field">
				<label className="cedar-field__label" htmlFor={ defaultId }>
					{ __( 'Default language', 'cedar-multilang' ) }
				</label>
				<Select
					id={ defaultId }
					value={ defaultLocale }
					disabled={ locked }
					aria-describedby={ defaultHelpId }
					onChange={ ( event ) =>
						changeDefault( event.target.value )
					}
					options={ defaultOptions }
				/>
				<p className="cedar-field__help" id={ defaultHelpId }>
					<span>
						{ locked
							? __(
									'Your pages already have languages, so the default stays as it is.',
									'cedar-multilang'
								)
							: __(
									'From Settings → General. Pages without a language count as this one.',
									'cedar-multilang'
								) }
					</span>
				</p>
			</div>

			<div
				className={ `cedar-field cedar-setup-languages ${
					showError ? 'is-error' : ''
				}` }
			>
				<span className="cedar-field__label" id={ `${ listId }-label` }>
					{ __( 'Other languages', 'cedar-multilang' ) }
				</span>
				{ others.length > 0 && (
					<ul
						className="cedar-setup-languages__list"
						aria-labelledby={ `${ listId }-label` }
					>
						{ others.map( ( language ) => (
							<li
								key={ language.locale }
								className="cedar-setup-language"
							>
								<LanguageTag
									code={ language.code }
									size="large"
								/>
								<span
									className="cedar-setup-language__native"
									lang={ language.hreflang }
									dir={ language.dir }
								>
									{ language.native }
								</span>
								<span className="cedar-setup-language__meta">
									{ `${ language.name } · ${ language.locale }` }
								</span>
								{ 'rtl' === language.dir && (
									<Badge tone="info">
										{ __( 'RTL', 'cedar-multilang' ) }
									</Badge>
								) }
								<Button
									variant="ghost"
									size="small"
									icon="close"
									aria-label={ sprintf(
										/* translators: %s: Language name. */
										__( 'Remove %s', 'cedar-multilang' ),
										language.name
									) }
									onClick={ () => remove( language.locale ) }
								/>
							</li>
						) ) }
					</ul>
				) }

				<div className="cedar-combobox">
					<span
						className={ `cedar-input cedar-combobox__field ${
							showError ? 'is-invalid' : ''
						}` }
					>
						<Icon name="search" />
						<input
							ref={ inputRef }
							type="text"
							role="combobox"
							aria-labelledby={ `${ listId }-label` }
							aria-expanded={ open && matches.length > 0 }
							aria-controls={ listId }
							aria-autocomplete="list"
							aria-activedescendant={
								open && activeMatch
									? `${ listId }-${ active }`
									: undefined
							}
							aria-describedby={ searchHelpId }
							aria-invalid={ showError || undefined }
							placeholder={ __(
								'Add a language, like “German” or “Deutsch”',
								'cedar-multilang'
							) }
							value={ text }
							onChange={ ( event ) => {
								setText( event.target.value );
								setActive( 0 );
							} }
							onKeyDown={ onKeyDown }
							onBlur={ () => empty && setTouched( true ) }
						/>
					</span>
					{ open && matches.length > 0 && (
						<ul
							id={ listId }
							role="listbox"
							className="cedar-combobox__list"
							aria-labelledby={ `${ listId }-label` }
						>
							{ matches.map( ( entry, index ) => {
								const added = taken.has( entry.locale );
								return (
									// The input owns keyboard handling (combobox pattern).
									// eslint-disable-next-line jsx-a11y/click-events-have-key-events
									<li
										key={ entry.locale }
										id={ `${ listId }-${ index }` }
										role="option"
										aria-selected={ index === active }
										aria-disabled={ added || undefined }
										className={ `cedar-combobox__option ${
											index === active ? 'is-active' : ''
										}` }
										onMouseDown={ ( event ) =>
											event.preventDefault()
										}
										onClick={ () => add( entry ) }
									>
										<LanguageTag code={ entry.code } />
										<span
											className="cedar-combobox__native"
											lang={ entry.hreflang }
											dir={ entry.dir }
										>
											{ entry.native }
										</span>
										<span className="cedar-combobox__meta">
											{ `${ entry.name } · ${ entry.locale }` }
										</span>
										<span className="cedar-combobox__end">
											{ added && (
												<>
													<Icon
														name="check"
														size={ 14 }
													/>
													{ __(
														'Added',
														'cedar-multilang'
													) }
												</>
											) }
											{ ! added && index === active && (
												<>
													{ __(
														'Enter',
														'cedar-multilang'
													) }
													<Icon
														name="corner-down-left"
														size={ 14 }
													/>
												</>
											) }
										</span>
									</li>
								);
							} ) }
						</ul>
					) }
					{ open && 0 === matches.length && (
						<div className="cedar-combobox__empty" role="status">
							<strong>
								{ sprintf(
									/* translators: %s: Search text. */
									__(
										'No language matches “%s”',
										'cedar-multilang'
									),
									text.trim()
								) }
							</strong>
							<span>
								{ __(
									'Add it as a custom language with its own locale and hreflang code.',
									'cedar-multilang'
								) }
							</span>
							<Button
								size="small"
								onClick={ addCustom }
								disabled={ saving }
							>
								{ __(
									'Add custom language',
									'cedar-multilang'
								) }
							</Button>
						</div>
					) }
				</div>
				{ offers.length > 0 && (
					<div className="cedar-setup-suggest">
						<span className="cedar-setup-suggest__label">
							{ __( 'Suggested', 'cedar-multilang' ) }
						</span>
						{ offers.map( ( entry ) => (
							<Button
								key={ entry.locale }
								size="small"
								icon="plus"
								onClick={ () => add( entry ) }
							>
								<span lang={ entry.hreflang } dir={ entry.dir }>
									{ entry.native }
								</span>
								{ entry.name !== entry.native && (
									<span className="cedar-setup-suggest__name">
										{ entry.name }
									</span>
								) }
							</Button>
						) ) }
					</div>
				) }
				<p className="cedar-field__help" id={ searchHelpId }>
					{ showError && <Icon name="alert" size={ 14 } /> }
					<span>
						{ empty &&
							__(
								'Add at least one more language to continue.',
								'cedar-multilang'
							) }
						{ ! empty &&
							foundShown.length > 0 &&
							sprintf(
								/* translators: %s: Language names, comma separated. */
								__(
									'Found in your pages: %s. Remove it if that’s not right.',
									'cedar-multilang'
								),
								foundShown
									.map( ( language ) => language.name )
									.join( ', ' )
							) }
						{ ! empty &&
							0 === foundShown.length &&
							__(
								'Use ↑ and ↓ to pick a match, Enter to add it.',
								'cedar-multilang'
							) }
					</span>
				</p>
			</div>

			{ error && (
				<Callout
					tone="danger"
					icon="alert"
					role="alert"
					title={ __(
						'Your languages couldn’t be saved',
						'cedar-multilang'
					) }
				>
					{ error }
				</Callout>
			) }

			<div className="cedar-setup__footer">
				<span />
				<Button
					variant="primary"
					onClick={ next }
					disabled={ empty || saving }
					aria-busy={ saving || undefined }
				>
					{ __( 'Next: Connect pages', 'cedar-multilang' ) }
				</Button>
			</div>
		</>
	);
}

/**
 * Suggested pairs (Figma: Onboarding / 2 · Connect pages — suggested pairs).
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import {
	Badge,
	Button,
	Checkbox,
	LanguageTag,
} from '../../components/controls';

const CONFIDENCE = {
	high: {
		tone: 'linked',
		label: () => __( 'High match', 'ako-multilang' ),
	},
	medium: {
		tone: 'info',
		label: () => __( 'Medium match', 'ako-multilang' ),
	},
	low: { tone: 'missing', label: () => __( 'Low match', 'ako-multilang' ) },
};

/**
 * Stable key of a pair.
 *
 * @param {Object} pair Suggested pair.
 * @return {string} Key.
 */
export const pairKey = ( pair ) => `${ pair.source.id }-${ pair.target.id }`;

/**
 * Pairs checked by default: high and medium confidence.
 *
 * @param {Array} pairs Suggested pairs.
 * @return {Set} Keys.
 */
export const defaultSelection = ( pairs ) =>
	new Set(
		pairs.filter( ( pair ) => 'low' !== pair.confidence ).map( pairKey )
	);

/**
 * Decoded path for display.
 *
 * @param {string} path Encoded path.
 * @return {string} Readable path.
 */
function readablePath( path ) {
	try {
		return decodeURIComponent( path );
	} catch {
		return path;
	}
}

function Page( { page, code } ) {
	return (
		<span className="ako-pair__page">
			<LanguageTag code={ code } />
			<span className="ako-pair__copy">
				<span className="ako-pair__title" dir="auto">
					{ page.title }
				</span>
				<span className="ako-pair__path" dir="auto">
					{ readablePath( page.path ) }
				</span>
			</span>
		</span>
	);
}

/**
 * @param {Object}   props
 * @param {Array}    props.pairs       Suggested pairs.
 * @param {Set}      props.selected    Selected keys.
 * @param {Function} props.onChange    Called with the new Set.
 * @param {string}   props.defaultCode Default language code.
 * @param {boolean}  [props.disabled]  Read-only (while importing).
 */
export default function PairList( {
	pairs,
	selected,
	onChange,
	defaultCode,
	disabled = false,
} ) {
	const all =
		pairs.length > 0 &&
		pairs.every( ( pair ) => selected.has( pairKey( pair ) ) );

	const toggle = ( pair, on ) => {
		const next = new Set( selected );
		if ( on ) {
			next.add( pairKey( pair ) );
		} else {
			next.delete( pairKey( pair ) );
		}
		onChange( next );
	};

	return (
		<section
			className={ `ako-pairs ${ disabled ? 'is-disabled' : '' }` }
			aria-labelledby="ako-pairs-title"
		>
			<div className="ako-pairs__head">
				<h3 id="ako-pairs-title" className="ako-pairs__title">
					{ __( 'Suggested pairs', 'ako-multilang' ) }
				</h3>
				<Badge tone="linked">
					{ sprintf(
						/* translators: %d: Number of suggested pairs. */
						_n(
							'%d matched by slug & title',
							'%d matched by slug & title',
							pairs.length,
							'ako-multilang'
						),
						pairs.length
					) }
				</Badge>
				<Button
					variant="ghost"
					size="small"
					className="ako-pairs__all"
					disabled={ disabled }
					onClick={ () =>
						onChange(
							all ? new Set() : new Set( pairs.map( pairKey ) )
						)
					}
				>
					{ all
						? __( 'Clear all', 'ako-multilang' )
						: __( 'Select all', 'ako-multilang' ) }
				</Button>
			</div>
			<ul className="ako-pairs__list">
				{ pairs.map( ( pair ) => {
					const confidence =
						CONFIDENCE[ pair.confidence ] || CONFIDENCE.low;
					return (
						<li key={ pairKey( pair ) } className="ako-pair">
							<Checkbox
								checked={ selected.has( pairKey( pair ) ) }
								disabled={ disabled }
								label={ sprintf(
									/* translators: 1: Page title, 2: Translation title. */
									__(
										'Connect “%1$s” and “%2$s”',
										'ako-multilang'
									),
									pair.source.title,
									pair.target.title
								) }
								onChange={ ( on ) => toggle( pair, on ) }
							/>
							<Page
								page={ pair.source }
								code={ pair.source.language || defaultCode }
							/>
							<Icon
								name="arrows-left-right"
								className="ako-pair__arrows"
							/>
							<Page page={ pair.target } code={ pair.language } />
							<Badge tone={ confidence.tone } dot>
								{ confidence.label() }
							</Badge>
						</li>
					);
				} ) }
			</ul>
		</section>
	);
}

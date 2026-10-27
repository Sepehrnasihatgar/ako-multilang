/**
 * Settings rows (Figma: Settings patterns / Row) shared by the SEO and
 * Settings screens.
 */
import { useId } from '@wordpress/element';
import { Toggle } from './controls';

/**
 * Setting row with a select (label on the start side).
 *
 * @param {Object}   props
 * @param {*}        props.label       Visible label (string or element).
 * @param {string}   [props.labelText] Accessible label when `label` is an element.
 * @param {string}   [props.help]      Help text.
 * @param {Function} props.children    ( id, helpId ) => control.
 */
export function Row( { label, labelText, help, children } ) {
	const id = useId();
	const helpId = help ? `${ id }-help` : undefined;
	return (
		<div className="ako-setting-row">
			<span className="ako-setting-row__copy">
				<label
					className="ako-setting-row__label"
					htmlFor={ id }
					aria-label={ labelText }
				>
					{ label }
				</label>
				{ help && (
					<span className="ako-setting-row__help" id={ helpId }>
						{ help }
					</span>
				) }
			</span>
			<span className="ako-setting-row__control">
				{ children( id, helpId ) }
			</span>
		</div>
	);
}

/**
 * Setting row with a switch.
 *
 * @param {Object}   props
 * @param {string}   props.label      Label.
 * @param {*}        [props.badge]    Badge after the label.
 * @param {string}   [props.help]     Help text.
 * @param {boolean}  props.checked    On.
 * @param {boolean}  [props.disabled] Disabled.
 * @param {Function} props.onChange   Change handler.
 */
export function ToggleRow( {
	label,
	badge,
	help,
	checked,
	disabled = false,
	onChange,
} ) {
	const id = useId();
	return (
		<div className="ako-setting-row">
			<span className="ako-setting-row__copy">
				<span className="ako-setting-row__title">
					<span
						className="ako-setting-row__label"
						id={ `${ id }-label` }
					>
						{ label }
					</span>
					{ badge }
				</span>
				{ help && (
					<span
						className="ako-setting-row__help"
						id={ `${ id }-help` }
					>
						{ help }
					</span>
				) }
			</span>
			<Toggle
				checked={ checked }
				disabled={ disabled }
				onChange={ onChange }
				labelId={ `${ id }-label` }
				describedBy={ help ? `${ id }-help` : undefined }
			/>
		</div>
	);
}

/**
 * Controls — Figma section "Controls": Button, Badge, Language tag, Tab,
 * Input, Field, Checkbox, Toggle.
 */
import { forwardRef, useEffect, useId, useRef } from '@wordpress/element';
import Icon from './Icon';

/**
 * Button. Variant=Primary|Secondary|Ghost|Danger, Size=Medium|Small.
 * Renders an <a> when `href` is given.
 */
export const Button = forwardRef( function CedarButton(
	{
		variant = 'secondary',
		size = 'medium',
		icon,
		children,
		href,
		className = '',
		type = 'button',
		...rest
	},
	ref
) {
	const classes = `cedar-button is-${ variant } is-${ size } ${
		icon && ! children ? 'is-icon-only' : ''
	} ${ className }`;
	const content = (
		<>
			{ icon && (
				<Icon name={ icon } size={ size === 'small' ? 14 : 16 } />
			) }
			{ children && (
				<span className="cedar-button__label">{ children }</span>
			) }
		</>
	);

	if ( href ) {
		return (
			<a ref={ ref } className={ classes } href={ href } { ...rest }>
				{ content }
			</a>
		);
	}
	return (
		<button ref={ ref } type={ type } className={ classes } { ...rest }>
			{ content }
		</button>
	);
} );

/**
 * Badge. Tone=Linked|Missing|Draft|Info|Error|Pro.
 *
 * @param {Object}  props
 * @param {string}  props.tone     Tone.
 * @param {boolean} [props.dot]    Show the leading dot.
 * @param {*}       props.children Label.
 */
export function Badge( { tone = 'draft', dot = false, children } ) {
	return (
		<span className={ `cedar-badge is-${ tone }` }>
			{ dot && <span className="cedar-badge__dot" aria-hidden="true" /> }
			{ children }
		</span>
	);
}

/**
 * Language tag. Size=Small|Large. The name is rendered with dir="auto" so
 * native names (e.g. فارسی) read correctly in any admin language.
 *
 * @param {Object} props
 * @param {string} props.code       Language code.
 * @param {string} [props.name]     Name (hidden when empty).
 * @param {string} [props.size]     small|large.
 * @param {*}      [props.children] Extra content after the name.
 */
export function LanguageTag( { code, name, size = 'small', children } ) {
	return (
		<span className={ `cedar-language-tag is-${ size }` }>
			<span
				className="cedar-language-tag__code"
				aria-hidden={ name ? true : undefined }
			>
				{ code.toUpperCase() }
			</span>
			{ name && (
				<span className="cedar-language-tag__name" dir="auto">
					{ name }
				</span>
			) }
			{ children }
		</span>
	);
}

/**
 * Segmented filter tabs (Figma "Tab" inside "Table toolbar"). Buttons with
 * aria-pressed: they filter the table below, they don't swap panels.
 *
 * @param {Object}   props
 * @param {string}   props.label    Group label for assistive tech.
 * @param {Array}    props.items    [{ value, label, count }].
 * @param {string}   props.value    Selected value.
 * @param {Function} props.onChange Called with the new value.
 */
export function Tabs( { label, items, value, onChange } ) {
	return (
		<div className="cedar-tabs" role="group" aria-label={ label }>
			{ items.map( ( item ) => (
				<button
					key={ item.value }
					type="button"
					className={ `cedar-tab ${ item.value === value ? 'is-active' : '' }` }
					aria-pressed={ item.value === value }
					onClick={ () => onChange( item.value ) }
				>
					<span>{ item.label }</span>
					{ typeof item.count === 'number' && (
						<span className="cedar-tab__count">{ item.count }</span>
					) }
				</button>
			) ) }
		</div>
	);
}

/**
 * Text input. Type=Text|Search; State=Default|Focus|Error|Disabled via props.
 */
export const TextInput = forwardRef( function CedarTextInput(
	{ search = false, invalid = false, className = '', ...rest },
	ref
) {
	return (
		<span
			className={ `cedar-input ${ search ? 'is-search' : '' } ${
				invalid ? 'is-invalid' : ''
			} ${ rest.disabled ? 'is-disabled' : '' } ${ className }` }
		>
			{ search && <Icon name="search" /> }
			<input
				ref={ ref }
				type={ search ? 'search' : 'text' }
				aria-invalid={ invalid || undefined }
				{ ...rest }
			/>
		</span>
	);
} );

/**
 * Native select styled as Figma "Input / Type=Select".
 *
 * @param {Object} props
 * @param {Array}  props.options [{ value, label }].
 * @param {Object} props.invalid
 */
export function Select( { options, invalid = false, ...rest } ) {
	return (
		<span
			className={ `cedar-input is-select ${ invalid ? 'is-invalid' : '' }` }
		>
			<select aria-invalid={ invalid || undefined } { ...rest }>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
			<Icon name="chevron-down" />
		</span>
	);
}

/**
 * Field: label, control, help or error. Wires ids for aria-describedby.
 *
 * @param {Object}   props
 * @param {string}   props.label    Label text.
 * @param {string}   [props.help]   Help text.
 * @param {string}   [props.error]  Error text (replaces help).
 * @param {Function} props.children Render prop: ( { id, describedBy, invalid } ) => control.
 */
export function Field( { label, help, error, children } ) {
	const id = useId();
	const helpId = `${ id }-help`;
	const message = error || help;

	return (
		<div className={ `cedar-field ${ error ? 'is-error' : '' }` }>
			<label className="cedar-field__label" htmlFor={ id }>
				{ label }
			</label>
			{ children( {
				id,
				describedBy: message ? helpId : undefined,
				invalid: !! error,
			} ) }
			{ message && (
				<p className="cedar-field__help" id={ helpId }>
					{ error && <Icon name="alert" size={ 14 } /> }
					<span>{ message }</span>
				</p>
			) }
		</div>
	);
}

/**
 * Checkbox. Value=Checked|Unchecked|Mixed. A real <input> keeps native
 * keyboard and screen-reader behavior; only the look is custom.
 *
 * @param {Object}   props
 * @param {boolean}  props.checked         Checked.
 * @param {boolean}  [props.indeterminate] Mixed state.
 * @param {string}   props.label           Accessible label.
 * @param {Function} props.onChange        Called with the new boolean.
 */
export function Checkbox( {
	checked,
	indeterminate = false,
	label,
	onChange,
	...rest
} ) {
	const ref = useRef();
	useEffect( () => {
		if ( ref.current ) {
			ref.current.indeterminate = indeterminate;
		}
	}, [ indeterminate ] );

	return (
		<span className="cedar-checkbox">
			<input
				ref={ ref }
				type="checkbox"
				checked={ checked }
				aria-label={ label }
				onChange={ ( event ) => onChange( event.target.checked ) }
				{ ...rest }
			/>
			<span className="cedar-checkbox__box" aria-hidden="true">
				<Icon name={ indeterminate ? 'minus' : 'check' } size={ 12 } />
			</span>
		</span>
	);
}

/**
 * Toggle (switch). State=On|Off, Enabled=Yes|No.
 *
 * @param {Object}   props
 * @param {boolean}  props.checked       On.
 * @param {Function} props.onChange      Called with the new boolean.
 * @param {string}   [props.labelId]     Id of the visible label.
 * @param {string}   [props.describedBy] Id of the description.
 * @param {boolean}  [props.disabled]    Disabled.
 */
export function Toggle( {
	checked,
	onChange,
	labelId,
	describedBy,
	disabled = false,
} ) {
	return (
		<button
			type="button"
			role="switch"
			className="cedar-toggle"
			aria-checked={ checked }
			aria-labelledby={ labelId }
			aria-describedby={ describedBy }
			disabled={ disabled }
			onClick={ () => onChange( ! checked ) }
		>
			<span className="cedar-toggle__knob" aria-hidden="true" />
		</button>
	);
}

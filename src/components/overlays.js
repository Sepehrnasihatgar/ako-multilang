/**
 * Overlays — Figma sections "Controls / Menu item", "Feedback / Add translation
 * menu, Dialog" and "Settings patterns / Toast". Keyboard behavior follows the
 * WAI-ARIA Authoring Practices (menu button, modal dialog, status message).
 */
import {
	useCallback,
	useEffect,
	useId,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import { Button } from './controls';

/**
 * Positions a popover next to its anchor with `position: fixed`, so it is
 * never clipped by a scrolling container (the table scrolls sideways when a
 * site has many languages). Opens upward when there is no room below, and
 * follows scrolling and resizing.
 *
 * @param {Object}  anchorRef  Ref of the element the popover belongs to.
 * @param {Object}  popoverRef Ref of the popover.
 * @param {boolean} open       Whether the popover is shown.
 * @param {string}  [align]    start|end edge of the anchor to align with.
 * @return {Object} Inline style for the popover.
 */
export function usePopoverStyle(
	anchorRef,
	popoverRef,
	open,
	align = 'start'
) {
	const [ style, setStyle ] = useState( {
		position: 'fixed',
		top: 0,
		left: 0,
		opacity: 0,
	} );

	useLayoutEffect( () => {
		if ( ! open || ! anchorRef.current ) {
			return undefined;
		}
		const view = anchorRef.current.ownerDocument.defaultView;
		const gap = 6;

		const place = () => {
			const anchor = anchorRef.current;
			const popover = popoverRef.current;
			if ( ! anchor || ! popover ) {
				return;
			}
			const rect = anchor.getBoundingClientRect();
			const height = popover.offsetHeight;
			const rtl = 'rtl' === view.getComputedStyle( anchor ).direction;
			const below = rect.bottom + gap;
			const fitsBelow = below + height <= view.innerHeight;
			const fitsAbove = rect.top - gap - height >= 0;
			const next = {
				position: 'fixed',
				top: ! fitsBelow && fitsAbove ? rect.top - gap - height : below,
				minWidth: rect.width,
			};
			if ( ( 'start' === align ) !== rtl ) {
				next.left = rect.left;
			} else {
				next.right = view.innerWidth - rect.right;
			}
			setStyle( next );
		};

		place();
		view.addEventListener( 'scroll', place, true );
		view.addEventListener( 'resize', place );
		return () => {
			view.removeEventListener( 'scroll', place, true );
			view.removeEventListener( 'resize', place );
		};
	}, [ open, align, anchorRef, popoverRef ] );

	return style;
}

/**
 * Menu button.
 *
 * @param {Object}   props
 * @param {Function} props.renderTrigger ( triggerProps ) => element. Spread the props on a button.
 * @param {Array}    props.items         [{ id, label, meta, description, icon, checked, disabled, onSelect }].
 * @param {string}   props.label         Accessible menu label.
 * @param {string}   [props.variant]     list|rich.
 * @param {string}   [props.align]       start|end.
 */
export function Menu( {
	renderTrigger,
	items,
	label,
	variant = 'list',
	align = 'start',
} ) {
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( 0 );
	const menuId = useId();
	const wrapRef = useRef();
	const triggerRef = useRef();
	const itemRefs = useRef( [] );
	const menuRef = useRef();
	const menuStyle = usePopoverStyle( wrapRef, menuRef, open, align );

	const close = useCallback( ( returnFocus = true ) => {
		setOpen( false );
		if ( returnFocus && triggerRef.current ) {
			triggerRef.current.focus();
		}
	}, [] );

	const openAt = ( index ) => {
		setActive( index );
		setOpen( true );
	};

	const firstIndex = () => {
		const checked = items.findIndex( ( item ) => item.checked );
		return checked >= 0 ? checked : 0;
	};

	useLayoutEffect( () => {
		if ( open && itemRefs.current[ active ] ) {
			itemRefs.current[ active ].focus( { preventScroll: true } );
		}
	}, [ open, active ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onPointer = ( event ) => {
			if (
				wrapRef.current &&
				! wrapRef.current.contains( event.target )
			) {
				close( false );
			}
		};
		document.addEventListener( 'mousedown', onPointer );
		return () => document.removeEventListener( 'mousedown', onPointer );
	}, [ open, close ] );

	const onTriggerKeyDown = ( event ) => {
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			openAt( 'ArrowUp' === event.key ? items.length - 1 : firstIndex() );
		}
	};

	const onMenuKeyDown = ( event ) => {
		const last = items.length - 1;
		const moves = {
			ArrowDown: active === last ? 0 : active + 1,
			ArrowUp: 0 === active ? last : active - 1,
			Home: 0,
			End: last,
		};
		if ( event.key in moves ) {
			event.preventDefault();
			setActive( moves[ event.key ] );
		} else if ( 'Escape' === event.key ) {
			event.preventDefault();
			close();
		} else if ( 'Tab' === event.key ) {
			close( false );
		}
	};

	const choose = ( item ) => {
		if ( item.disabled ) {
			return;
		}
		close();
		item.onSelect();
	};

	return (
		<div className="ako-menu-wrap" ref={ wrapRef }>
			{ renderTrigger( {
				ref: triggerRef,
				'aria-haspopup': 'menu',
				'aria-expanded': open,
				'aria-controls': open ? menuId : undefined,
				onClick: () =>
					open ? close( false ) : openAt( firstIndex() ),
				onKeyDown: onTriggerKeyDown,
			} ) }
			{ open && (
				<div
					ref={ menuRef }
					id={ menuId }
					role="menu"
					tabIndex={ -1 }
					style={ menuStyle }
					aria-label={ label }
					className={ `ako-menu is-${ variant } is-align-${ align }` }
					onKeyDown={ onMenuKeyDown }
				>
					{ items.map( ( item, index ) => (
						<div
							key={ item.id }
							ref={ ( node ) =>
								( itemRefs.current[ index ] = node )
							}
							role={
								undefined === item.checked
									? 'menuitem'
									: 'menuitemradio'
							}
							aria-checked={
								undefined === item.checked
									? undefined
									: item.checked
							}
							aria-disabled={ item.disabled || undefined }
							tabIndex={ index === active ? 0 : -1 }
							className={ `ako-menu__item ${ item.checked ? 'is-selected' : '' } ${
								item.disabled ? 'is-disabled' : ''
							}` }
							onClick={ () => choose( item ) }
							onKeyDown={ ( event ) => {
								if (
									'Enter' === event.key ||
									' ' === event.key
								) {
									event.preventDefault();
									choose( item );
								}
							} }
							onMouseEnter={ () => setActive( index ) }
						>
							{ 'rich' === variant ? (
								<>
									<span
										className="ako-menu__tile"
										aria-hidden="true"
									>
										<Icon name={ item.icon } />
									</span>
									<span className="ako-menu__copy">
										<span className="ako-menu__label">
											{ item.label }
										</span>
										<span className="ako-menu__description">
											{ item.description }
										</span>
									</span>
								</>
							) : (
								<>
									{ item.icon && <Icon name={ item.icon } /> }
									<span
										className="ako-menu__label"
										dir="auto"
									>
										{ item.label }
									</span>
									{ item.meta && (
										<span className="ako-menu__meta">
											{ item.meta }
										</span>
									) }
									{ item.checked && (
										<Icon name="check" size={ 14 } />
									) }
								</>
							) }
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * Modal dialog. Tone=Default|Danger. Focus moves to the least destructive
 * action, Tab is trapped, Escape cancels, and focus returns on close.
 *
 * @param {Object}   props
 * @param {string}   [props.tone]         default|danger.
 * @param {string}   props.icon           Icon name.
 * @param {string}   props.title          Title.
 * @param {string}   props.description    Description.
 * @param {string}   [props.confirmLabel] Confirm button label.
 * @param {Function} [props.onConfirm]    Confirm handler (none: close only).
 * @param {Function} props.onCancel       Cancel handler.
 * @param {boolean}  [props.busy]         Disable actions while working.
 * @param {Object}   [props.children]     Extra content, e.g. a field.
 * @param {string}   [props.initialFocus] Selector to focus first.
 * @param {string}   [props.cancelLabel]  Cancel label ("Close" without a confirm).
 * @param {boolean}  [props.wide]         Wider panel for lists.
 */
export function Dialog( {
	tone = 'default',
	icon,
	title,
	description,
	confirmLabel,
	onConfirm,
	onCancel,
	busy = false,
	children,
	initialFocus,
	cancelLabel,
	wide = false,
} ) {
	const titleId = useId();
	const descId = useId();
	const panelRef = useRef();
	const cancelRef = useRef();

	useEffect( () => {
		const previous = panelRef.current.ownerDocument.activeElement;
		const first = initialFocus
			? panelRef.current.querySelector( initialFocus )
			: null;
		( first || cancelRef.current ).focus();
		return () => {
			if ( previous && previous.focus ) {
				previous.focus();
			}
		};
	}, [] );

	const onKeyDown = ( event ) => {
		if ( 'Escape' === event.key && ! busy ) {
			event.stopPropagation();
			onCancel();
			return;
		}
		if ( 'Tab' !== event.key ) {
			return;
		}
		const focusable = panelRef.current.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled])'
		);
		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		const current = panelRef.current.ownerDocument.activeElement;
		if ( event.shiftKey && current === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && current === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	return (
		<div className="ako-dialog-backdrop">
			<div
				ref={ panelRef }
				className={ `ako-dialog is-${ tone } ${ wide ? 'is-wide' : '' }` }
				role={ 'danger' === tone ? 'alertdialog' : 'dialog' }
				aria-modal="true"
				aria-labelledby={ titleId }
				aria-describedby={ descId }
				onKeyDown={ onKeyDown }
			>
				<div className="ako-dialog__head">
					<span className="ako-dialog__tile" aria-hidden="true">
						<Icon name={ icon } size={ 18 } />
					</span>
					<div className="ako-dialog__copy">
						<h2 id={ titleId } className="ako-dialog__title">
							{ title }
						</h2>
						<p id={ descId } className="ako-dialog__description">
							{ description }
						</p>
					</div>
				</div>
				{ children }
				<div className="ako-dialog__actions">
					<Button
						ref={ cancelRef }
						variant="ghost"
						onClick={ onCancel }
						disabled={ busy }
					>
						{ cancelLabel ||
							( onConfirm
								? __( 'Cancel', 'ako-multilang' )
								: __( 'Close', 'ako-multilang' ) ) }
					</Button>
					{ onConfirm && (
						<Button
							variant={ 'danger' === tone ? 'danger' : 'primary' }
							onClick={ onConfirm }
							disabled={ busy }
							aria-busy={ busy || undefined }
						>
							{ confirmLabel }
						</Button>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * Toast. Tone=Success|Error|Info. Announced politely (errors assertively),
 * dismissed by the close button or after a delay. An optional action
 * (e.g. Undo) keeps it open longer.
 *
 * @param {Object}   props
 * @param {Object}   props.toast     { tone, message, action: { label, onClick } } or null.
 * @param {Function} props.onDismiss Called when it should disappear.
 */
export function Toast( { toast, onDismiss } ) {
	useEffect( () => {
		if ( ! toast ) {
			return undefined;
		}
		const timer = setTimeout( onDismiss, toast.action ? 10000 : 5000 );
		return () => clearTimeout( timer );
	}, [ toast, onDismiss ] );

	const icons = {
		success: 'circle-check',
		error: 'alert',
		info: 'arrows-left-right',
	};

	return (
		<div
			className="ako-toast-region"
			role={ toast && 'error' === toast.tone ? 'alert' : 'status' }
			aria-live={
				toast && 'error' === toast.tone ? 'assertive' : 'polite'
			}
		>
			{ toast && (
				<div className={ `ako-toast is-${ toast.tone }` }>
					<Icon name={ icons[ toast.tone ] } />
					<span className="ako-toast__message">
						{ toast.message }
					</span>
					{ toast.action && toast.action.url && (
						<a
							className="ako-toast__action"
							href={ toast.action.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ toast.action.label }
						</a>
					) }
					{ toast.action && ! toast.action.url && (
						<button
							type="button"
							className="ako-toast__action"
							onClick={ () => {
								onDismiss();
								toast.action.onClick();
							} }
						>
							{ toast.action.label }
						</button>
					) }
					<button
						type="button"
						className="ako-toast__close"
						onClick={ onDismiss }
						aria-label={ __( 'Dismiss', 'ako-multilang' ) }
					>
						<Icon name="close" size={ 14 } />
					</button>
				</div>
			) }
		</div>
	);
}

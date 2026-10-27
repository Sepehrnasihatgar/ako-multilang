/**
 * Icons from Lucide (ISC License, https://lucide.dev), inlined so nothing is
 * loaded from a third-party server. Names match the Figma "Icon/*" components.
 */

const PATHS = {
	alert: (
		<>
			<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
			<path d="M12 9v4" />
			<path d="M12 17h.01" />
		</>
	),
	'arrows-left-right': (
		<>
			<path d="M8 3 4 7l4 4" />
			<path d="M4 7h16" />
			<path d="m16 21 4-4-4-4" />
			<path d="M20 17H4" />
		</>
	),
	check: <path d="M20 6 9 17l-5-5" />,
	'chevron-down': <path d="m6 9 6 6 6-6" />,
	'circle-check': (
		<>
			<circle cx="12" cy="12" r="10" />
			<path d="m9 12 2 2 4-4" />
		</>
	),
	close: (
		<>
			<path d="M18 6 6 18" />
			<path d="m6 6 12 12" />
		</>
	),
	copy: (
		<>
			<rect width="14" height="14" x="8" y="8" rx="2" ry="2" />
			<path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />
		</>
	),
	'corner-down-left': (
		<>
			<path d="M20 4v7a4 4 0 0 1-4 4H4" />
			<path d="m9 10-5 5 5 5" />
		</>
	),
	'external-link': (
		<>
			<path d="M15 3h6v6" />
			<path d="M10 14 21 3" />
			<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
		</>
	),
	'file-plus': (
		<>
			<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
			<path d="M14 2v4a2 2 0 0 0 2 2h4" />
			<path d="M9 15h6" />
			<path d="M12 18v-6" />
		</>
	),
	link: (
		<>
			<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
			<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
		</>
	),
	minus: <path d="M5 12h14" />,
	more: (
		<>
			<circle cx="12" cy="12" r="1" />
			<circle cx="19" cy="12" r="1" />
			<circle cx="5" cy="12" r="1" />
		</>
	),
	plus: (
		<>
			<path d="M5 12h14" />
			<path d="M12 5v14" />
		</>
	),
	'settings-2': (
		<>
			<path d="M20 7h-9" />
			<path d="M14 17H5" />
			<circle cx="17" cy="17" r="3" />
			<circle cx="7" cy="7" r="3" />
		</>
	),
	search: (
		<>
			<circle cx="11" cy="11" r="8" />
			<path d="m21 21-4.3-4.3" />
		</>
	),
};

/**
 * Decorative by default (aria-hidden). Pass `label` when the icon carries
 * meaning on its own.
 *
 * @param {Object} props
 * @param {string} props.name        Icon name.
 * @param {number} [props.size]      Pixel size.
 * @param {string} [props.label]     Accessible label.
 * @param {string} [props.className] Extra class.
 */
export default function Icon( { name, size = 16, label, className = '' } ) {
	return (
		<svg
			className={ `cedar-icon ${ className }` }
			width={ size }
			height={ size }
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			strokeLinejoin="round"
			focusable="false"
			aria-hidden={ label ? undefined : true }
			role={ label ? 'img' : undefined }
			aria-label={ label }
		>
			{ PATHS[ name ] }
		</svg>
	);
}

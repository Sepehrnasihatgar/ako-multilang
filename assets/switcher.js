/**
 * Cedar switcher: keyboard support for the Dropdown and Floating styles.
 *
 * They work without JavaScript (<details>/<summary>); this adds Escape to
 * close, arrow keys to move between languages and click-outside to close.
 */
( function () {
	'use strict';

	function menuLinks( details ) {
		return Array.prototype.slice.call(
			details.querySelectorAll( '.cedar-switcher__menu a' )
		);
	}

	document.addEventListener( 'keydown', function ( event ) {
		var details = event.target.closest && event.target.closest( '.cedar-switcher__details' );
		var links, index;

		if ( ! details ) {
			return;
		}

		if ( 'Escape' === event.key && details.open ) {
			details.open = false;
			details.querySelector( 'summary' ).focus();
			return;
		}

		if ( 'ArrowDown' !== event.key && 'ArrowUp' !== event.key ) {
			return;
		}

		links = menuLinks( details );
		if ( ! links.length ) {
			return;
		}

		event.preventDefault();
		details.open = true;
		index = links.indexOf( document.activeElement );
		if ( 'ArrowDown' === event.key ) {
			index = ( index + 1 ) % links.length;
		} else {
			index = index <= 0 ? links.length - 1 : index - 1;
		}
		links[ index ].focus();
	} );

	// Near the edge of the screen (end of a menu), open the list toward the
	// other side so it stays visible.
	document.addEventListener(
		'toggle',
		function ( event ) {
			var details = event.target;
			var menu, rect;

			if ( ! details.classList || ! details.classList.contains( 'cedar-switcher__details' ) || ! details.open ) {
				return;
			}
			menu = details.querySelector( '.cedar-switcher__menu' );
			if ( ! menu ) {
				return;
			}
			menu.classList.remove( 'is-end' );
			rect = menu.getBoundingClientRect();
			if ( rect.right > document.documentElement.clientWidth || rect.left < 0 ) {
				menu.classList.add( 'is-end' );
			}
		},
		true
	);

	document.addEventListener( 'click', function ( event ) {
		var open = document.querySelectorAll( '.cedar-switcher__details[open]' );
		Array.prototype.forEach.call( open, function ( details ) {
			if ( ! details.contains( event.target ) ) {
				details.open = false;
			}
		} );
	} );
}() );

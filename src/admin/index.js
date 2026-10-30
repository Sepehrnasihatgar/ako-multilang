/**
 * Ako admin app entry.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from './App';
import './admin.css';

domReady( () => {
	const root = document.getElementById( 'akoml-root' );
	if ( ! root ) {
		return;
	}

	let config = {};
	try {
		config = JSON.parse( root.dataset.config || '{}' );
	} catch {
		config = {};
	}

	createRoot( root ).render( <App config={ config } /> );
} );

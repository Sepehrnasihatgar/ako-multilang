/**
 * Cedar admin app entry.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from './App';
import './style.css';

domReady( () => {
	const root = document.getElementById( 'cedar-ml-root' );
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

/**
 * Classic editor: the same panel inside the "Language · Ako" metabox.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot, useCallback, useState } from '@wordpress/element';
import { Toast } from '../components/overlays';
import LanguagePanel from '../editor/LanguagePanel';
import '../editor/editor.css';

function ClassicPanel( { postId } ) {
	const [ toast, setToast ] = useState( null );
	const dismiss = useCallback( () => setToast( null ), [] );

	// The Toast handles both URL and callback actions.
	const notify = setToast;

	return (
		<>
			<LanguagePanel
				postId={ postId }
				reloadKey={ 0 }
				notify={ notify }
			/>
			<div className="ako-ml">
				<Toast toast={ toast } onDismiss={ dismiss } />
			</div>
		</>
	);
}

domReady( () => {
	const root = document.getElementById( 'akoml-classic' );
	const postId = root ? parseInt( root.dataset.postId, 10 ) : 0;
	if ( postId > 0 ) {
		createRoot( root ).render( <ClassicPanel postId={ postId } /> );
	}
} );

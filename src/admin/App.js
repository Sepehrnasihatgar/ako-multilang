/**
 * App shell: Cedar header + the current screen. (#wpbody already has role="main".)
 */
import { useCallback, useState } from '@wordpress/element';
import { Header } from '../components/display';
import { Toast } from '../components/overlays';
import Translations from './screens/Translations';
import Languages from './screens/Languages';

const SCREENS = {
	translations: Translations,
	languages: Languages,
};

export default function App( { config } ) {
	const [ toast, setToast ] = useState( null );
	const dismiss = useCallback( () => setToast( null ), [] );
	const Screen = SCREENS[ config.screen ] || Translations;

	return (
		<>
			<Header version={ config.version || '' } />
			<div className="cedar-body">
				<Screen config={ config } notify={ setToast } />
			</div>
			<Toast toast={ toast } onDismiss={ dismiss } />
		</>
	);
}

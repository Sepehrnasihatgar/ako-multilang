/**
 * App shell: Ako header + the current screen. (#wpbody already has role="main".)
 */
import { useCallback, useState } from '@wordpress/element';
import { Header } from '../components/display';
import { Toast } from '../components/overlays';
import Translations from './screens/Translations';
import Languages from './screens/Languages';
import Switcher from './screens/Switcher';
import Seo from './screens/Seo';
import Settings from './screens/Settings';
import About from './screens/About';
import Setup from './screens/Setup';

const SCREENS = {
	translations: Translations,
	languages: Languages,
	switcher: Switcher,
	seo: Seo,
	settings: Settings,
	about: About,
	setup: Setup,
};

export default function App( { config } ) {
	const [ toast, setToast ] = useState( null );
	const dismiss = useCallback( () => setToast( null ), [] );
	const Screen = SCREENS[ config.screen ] || Translations;

	return (
		<>
			{ 'setup' !== config.screen && (
				<Header version={ config.version || '' } />
			) }
			<div className="ako-body">
				<Screen config={ config } notify={ setToast } />
			</div>
			<Toast toast={ toast } onDismiss={ dismiss } />
		</>
	);
}

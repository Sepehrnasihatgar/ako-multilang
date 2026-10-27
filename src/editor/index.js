/**
 * Block editor: "Language · Ako" panel in the document sidebar.
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import LanguagePanel from './LanguagePanel';
import './editor.css';

const DIRECTION_SOURCE = 'ako-ml-direction';

function AkoLanguagePanel() {
	const { postId, isSaving, isAutosaving } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			isSaving: editor.isSavingPost(),
			isAutosaving: editor.isAutosavingPost(),
		};
	}, [] );
	const { createSuccessNotice, createErrorNotice, createInfoNotice } =
		useDispatch( 'core/notices' );

	// Refresh after each manual save: status and hreflang may have changed.
	const [ reloadKey, setReloadKey ] = useState( 0 );
	const wasSaving = useRef( false );
	useEffect( () => {
		if ( wasSaving.current && ! isSaving && ! isAutosaving ) {
			setReloadKey( ( key ) => key + 1 );
		}
		wasSaving.current = isSaving && ! isAutosaving;
	}, [ isSaving, isAutosaving ] );

	const notify = useCallback(
		( { tone, message, action } ) => {
			const create =
				{ success: createSuccessNotice, error: createErrorNotice }[
					tone
				] || createInfoNotice;
			const actions = [];
			if ( action && action.url ) {
				actions.push( { label: action.label, url: action.url } );
			} else if ( action ) {
				actions.push( {
					label: action.label,
					onClick: action.onClick,
				} );
			}
			create( message, { type: 'snackbar', actions } );
		},
		[ createSuccessNotice, createErrorNotice, createInfoNotice ]
	);

	// Write right-to-left languages right-to-left in the editor canvas.
	const { updateEditorSettings } = useDispatch( 'core/editor' );
	const getStyles = useSelect(
		( select ) => () => select( 'core/editor' ).getEditorSettings().styles,
		[]
	);
	const onDirection = useCallback(
		( dir ) => {
			const styles = ( getStyles() || [] ).filter(
				( style ) => DIRECTION_SOURCE !== style.source
			);
			if ( 'rtl' === dir ) {
				styles.push( {
					css: '.editor-styles-wrapper { direction: rtl; }',
					source: DIRECTION_SOURCE,
				} );
			}
			updateEditorSettings( { styles } );
		},
		[ getStyles, updateEditorSettings ]
	);

	if ( ! postId ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="ako-ml-language"
			title={ __( 'Language · Ako', 'ako-multilang' ) }
			className="ako-ml-document-panel"
		>
			<LanguagePanel
				postId={ postId }
				reloadKey={ reloadKey }
				notify={ notify }
				onDirection={ onDirection }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'ako-ml-language', { render: AkoLanguagePanel } );

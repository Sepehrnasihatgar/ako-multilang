/**
 * Language Switcher block (editor side). The block is dynamic: the preview
 * and the front end come from the same PHP renderer.
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

function Edit( { attributes, setAttributes } ) {
	const [ switchers, setSwitchers ] = useState( [] );
	const blockProps = useBlockProps();

	useEffect( () => {
		apiFetch( { path: '/ako/v1/switchers' } )
			.then( ( response ) => setSwitchers( response.switchers ) )
			.catch( () => setSwitchers( [] ) );
	}, [] );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Switcher', 'ako-multilang' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Design', 'ako-multilang' ) }
						help={ __(
							'Edit designs in Ako → Switcher.',
							'ako-multilang'
						) }
						value={ attributes.switcher }
						options={ [
							{
								value: '',
								label: __( 'First switcher', 'ako-multilang' ),
							},
							...switchers.map( ( switcher ) => ( {
								value: switcher.id,
								label: switcher.name,
							} ) ),
						] }
						onChange={ ( switcher ) =>
							setAttributes( { switcher } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="ako-multilang/switcher"
				attributes={ attributes }
				EmptyResponsePlaceholder={ () => (
					<p>
						{ __(
							'Add a second language in Ako → Languages to show the switcher.',
							'ako-multilang'
						) }
					</p>
				) }
			/>
		</div>
	);
}

registerBlockType( 'ako-multilang/switcher', {
	edit: Edit,
	save: () => null,
} );

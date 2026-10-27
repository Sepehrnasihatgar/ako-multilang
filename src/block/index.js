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
		apiFetch( { path: '/cedar/v1/switchers' } )
			.then( ( response ) => setSwitchers( response.switchers ) )
			.catch( () => setSwitchers( [] ) );
	}, [] );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Switcher', 'cedar-multilang' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Design', 'cedar-multilang' ) }
						help={ __(
							'Edit designs in Cedar → Switcher.',
							'cedar-multilang'
						) }
						value={ attributes.switcher }
						options={ [
							{
								value: '',
								label: __(
									'First switcher',
									'cedar-multilang'
								),
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
				block="cedar-multilang/switcher"
				attributes={ attributes }
				EmptyResponsePlaceholder={ () => (
					<p>
						{ __(
							'Add a second language in Cedar → Languages to show the switcher.',
							'cedar-multilang'
						) }
					</p>
				) }
			/>
		</div>
	);
}

registerBlockType( 'cedar-multilang/switcher', {
	edit: Edit,
	save: () => null,
} );

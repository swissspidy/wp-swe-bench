import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

import metadata from './block.json';
import ServerPreview from '../shared/server-preview';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Dateline', 'acme-newsroom' ) }>
						<TextControl
							label={ __( 'City (leave empty for the newsroom default)', 'acme-newsroom' ) }
							value={ attributes.city }
							onChange={ ( city ) => setAttributes( { city } ) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				</InspectorControls>
				<ServerPreview name={ metadata.name } attributes={ attributes } emptyLabel={ __( 'Dateline', 'acme-newsroom' ) } />
			</>
		);
	},
	save: () => null,
} );

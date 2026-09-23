import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { BlockControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { ToolbarDropdownMenu } from '@wordpress/components';

import metadata from './block.json';

const LABELS = {
	breaking: __( 'Breaking:', 'acme-newsroom' ),
	update: __( 'Update:', 'acme-newsroom' ),
};

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const { text, level } = attributes;
		return (
			<>
				<BlockControls>
					<ToolbarDropdownMenu
						icon="flag"
						label={ __( 'Banner type', 'acme-newsroom' ) }
						controls={ Object.keys( LABELS ).map( ( key ) => ( {
							title: LABELS[ key ],
							isActive: key === level,
							onClick: () => setAttributes( { level: key } ),
						} ) ) }
					/>
				</BlockControls>
				<div { ...useBlockProps( { className: `is-level-${ level }` } ) }>
					<strong className="acme-breaking-banner__label">{ LABELS[ level ] }</strong>{ ' ' }
					<RichText
						tagName="span"
						value={ text }
						onChange={ ( value ) => setAttributes( { text: value } ) }
						placeholder={ __( 'What happened?', 'acme-newsroom' ) }
						allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
					/>
				</div>
			</>
		);
	},
	save: () => null,
} );

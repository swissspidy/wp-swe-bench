/**
 * Editor UI for the callout block.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

import { getTypes } from './types';

export default function Edit( { attributes, setAttributes } ) {
	const { type, title, content } = attributes;
	const blockProps = useBlockProps( {
		className: `acme-callout acme-callout--${ type }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Callout', 'acme-callouts' ) }>
					<SelectControl
						label={ __( 'Type', 'acme-callouts' ) }
						value={ type }
						options={ getTypes() }
						onChange={ ( value ) => setAttributes( { type: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="p"
					className="acme-callout__title"
					value={ title }
					onChange={ ( value ) => setAttributes( { title: value } ) }
					placeholder={ __( 'Callout title (optional)', 'acme-callouts' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
				<RichText
					tagName="div"
					className="acme-callout__content"
					value={ content }
					onChange={ ( value ) => setAttributes( { content: value } ) }
					placeholder={ __( 'Write the callout…', 'acme-callouts' ) }
				/>
			</div>
		</>
	);
}

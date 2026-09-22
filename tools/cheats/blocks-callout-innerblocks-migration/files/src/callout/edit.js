/**
 * Editor UI for the callout block.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

import { getTypes, getTypeLabel } from './types';

export const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/list' ];
const TEMPLATE = [ [ 'core/paragraph' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { type, title } = attributes;
	const blockProps = useBlockProps( {
		className: `is-type-${ type }`,
		role: 'note',
		'aria-label': getTypeLabel( type ),
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wp-block-acme-callout__body' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

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
			<aside { ...blockProps }>
				<RichText
					tagName="p"
					className="wp-block-acme-callout__title"
					value={ title }
					onChange={ ( value ) => setAttributes( { title: value } ) }
					placeholder={ __( 'Callout title (optional)', 'acme-callouts' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
					disableLineBreaks
				/>
				<div { ...innerBlocksProps } />
			</aside>
		</>
	);
}

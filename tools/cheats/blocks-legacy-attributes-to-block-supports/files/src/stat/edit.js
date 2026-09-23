/**
 * Editor UI for the statistic block. Colour and size come from block supports.
 */
import { __ } from '@wordpress/i18n';
import {
	AlignmentControl,
	BlockControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';

import { alignmentClass } from './save';

export default function Edit( { attributes, setAttributes } ) {
	const { value, label, alignment } = attributes;

	return (
		<>
			<BlockControls group="block">
				<AlignmentControl
					value={ alignment }
					onChange={ ( next ) =>
						setAttributes( { alignment: next || 'left' } )
					}
				/>
			</BlockControls>
			<div
				{ ...useBlockProps( { className: alignmentClass( alignment ) } ) }
			>
				<RichText
					tagName="span"
					className="wp-block-acme-stat__value"
					value={ value }
					onChange={ ( next ) => setAttributes( { value: next } ) }
					placeholder={ __( '42%', 'acme-content-blocks' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="span"
					className="wp-block-acme-stat__label"
					value={ label }
					onChange={ ( next ) => setAttributes( { label: next } ) }
					placeholder={ __( 'Label', 'acme-content-blocks' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
			</div>
		</>
	);
}

/**
 * Saved markup (2.0+).
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

export function alignmentClass( alignment ) {
	return alignment && alignment !== 'left'
		? `has-text-align-${ alignment }`
		: undefined;
}

export default function save( { attributes } ) {
	const { value, label, alignment } = attributes;
	return (
		<div
			{ ...useBlockProps.save( {
				className: alignmentClass( alignment ),
			} ) }
		>
			<RichText.Content
				tagName="span"
				className="wp-block-acme-stat__value"
				value={ value }
			/>
			<RichText.Content
				tagName="span"
				className="wp-block-acme-stat__label"
				value={ label }
			/>
		</div>
	);
}

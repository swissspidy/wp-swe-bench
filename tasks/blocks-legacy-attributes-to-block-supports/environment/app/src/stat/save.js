/**
 * Saved markup.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import statProps from './props';

export default function save( { attributes } ) {
	const { value, label } = attributes;
	return (
		<div { ...useBlockProps.save( statProps( attributes ) ) }>
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

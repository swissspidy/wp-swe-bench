/**
 * Plausible-but-incomplete: static 2.0 markup saved in post content. Old posts
 * are only upgraded once someone opens and re-saves them in the editor; the
 * front end of untouched posts and the [callout] shortcode keep the 1.x markup.
 */
import { InnerBlocks, RichText, useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { type, title } = attributes;
	return (
		<aside { ...useBlockProps.save( { className: `is-type-${ type }`, role: 'note' } ) }>
			{ title && (
				<RichText.Content
					tagName="p"
					className="wp-block-acme-callout__title"
					value={ title }
				/>
			) }
			<div className="wp-block-acme-callout__body">
				<InnerBlocks.Content />
			</div>
		</aside>
	);
}

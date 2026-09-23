/**
 * Saved markup (2.0+). Colours, padding and font size come from block supports.
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { tone, showIcon } = attributes;
	const blockProps = useBlockProps.save( {
		className: `is-tone-${ tone }`,
		role: 'note',
	} );

	return (
		<div { ...blockProps }>
			{ showIcon && (
				<span
					className="wp-block-acme-notice-box__icon"
					aria-hidden="true"
				/>
			) }
			<div className="wp-block-acme-notice-box__body">
				<InnerBlocks.Content />
			</div>
		</div>
	);
}

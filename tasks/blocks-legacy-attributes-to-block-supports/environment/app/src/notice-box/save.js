/**
 * Saved markup (1.3+).
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

import noticeStyle from './styles';

export default function save( { attributes } ) {
	const { tone, showIcon, bordered } = attributes;
	const blockProps = useBlockProps.save( {
		className: [ `is-tone-${ tone }`, bordered ? 'is-bordered' : '' ]
			.filter( Boolean )
			.join( ' ' ),
		style: noticeStyle( attributes ),
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

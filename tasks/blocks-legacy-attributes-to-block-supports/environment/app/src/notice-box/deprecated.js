/**
 * Deprecated versions of the notice box.
 */
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * 1.0 – 1.2: `<div class="acme-notice acme-notice--{tone}" style="…">`, always
 * with the colours, padding and font size inline (defaults included).
 */
const v1 = {
	attributes: {
		tone: {
			type: 'string',
			default: 'info',
		},
		bgColor: {
			type: 'string',
			default: '#fff8e1',
		},
		textColor: {
			type: 'string',
			default: '#3e2723',
		},
		padding: {
			type: 'number',
			default: 20,
		},
		fontSize: {
			type: 'number',
			default: 16,
		},
	},
	supports: {
		html: false,
	},
	save( { attributes } ) {
		const { tone, bgColor, textColor, padding, fontSize } = attributes;
		return (
			<div
				className={ `acme-notice acme-notice--${ tone }` }
				style={ {
					backgroundColor: bgColor,
					color: textColor,
					padding: `${ padding }px`,
					fontSize: `${ fontSize }px`,
				} }
			>
				<InnerBlocks.Content />
			</div>
		);
	},
};

export default [ v1 ];

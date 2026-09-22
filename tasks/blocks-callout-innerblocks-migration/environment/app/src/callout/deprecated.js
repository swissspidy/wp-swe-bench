/**
 * Deprecated versions of the callout block.
 */
import { RichText } from '@wordpress/block-editor';

/**
 * v1.0 – v1.2: `<div class="callout callout-{type}"><strong>title</strong><p>content</p></div>`
 */
const v1 = {
	attributes: {
		type: {
			type: 'string',
			default: 'info',
		},
		title: {
			type: 'string',
			source: 'html',
			selector: 'strong',
		},
		content: {
			type: 'string',
			source: 'html',
			selector: 'p',
		},
	},
	supports: {
		html: false,
	},
	save( { attributes } ) {
		const { type, title, content } = attributes;
		return (
			<div className={ `callout callout-${ type }` }>
				<strong>{ title }</strong>
				<RichText.Content tagName="p" value={ content } />
			</div>
		);
	},
};

export default [ v1 ];

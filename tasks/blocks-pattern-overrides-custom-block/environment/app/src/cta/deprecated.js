/**
 * Deprecated versions of the CTA block.
 */
import { RichText } from '@wordpress/block-editor';

const LEGACY_COLORS = {
	blue: 'primary',
	green: 'secondary',
	black: 'dark',
};

/**
 * 1.x: `<div class="acme-cta acme-cta--{color}"><h2 class="acme-cta__heading">…</h2><a class="acme-cta__button">…</a></div>`
 */
const v1 = {
	attributes: {
		title: {
			type: 'string',
			default: '',
		},
		label: {
			type: 'string',
			default: '',
		},
		url: {
			type: 'string',
			default: '',
		},
		color: {
			type: 'string',
			default: 'blue',
		},
	},
	supports: {
		html: false,
	},
	save( { attributes } ) {
		const { title, label, url, color } = attributes;
		return (
			<div className={ `acme-cta acme-cta--${ color }` }>
				<RichText.Content
					tagName="h2"
					className="acme-cta__heading"
					value={ title }
				/>
				<a className="acme-cta__button" href={ url }>
					{ label }
				</a>
			</div>
		);
	},
	migrate( { title, label, url, color } ) {
		return {
			heading: title,
			headingLevel: 2,
			buttonText: label,
			buttonUrl: url,
			variant: LEGACY_COLORS[ color ] || 'primary',
		};
	},
};

export default [ v1 ];

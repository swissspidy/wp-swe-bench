/**
 * Deprecated versions of the callout block.
 *
 * Both 1.x formats stored the text as a single HTML string; 2.0 stores it as
 * inner blocks, so the migrations move it into a paragraph.
 */
import { createBlock } from '@wordpress/blocks';
import { RichText, useBlockProps } from '@wordpress/block-editor';

function migrateToInnerBlocks( attributes ) {
	const { content, ...rest } = attributes;
	return [
		{ ...rest, title: rest.title || '' },
		[ createBlock( 'core/paragraph', { content: content || '' } ) ],
	];
}

/**
 * v1.3 – v1.4: BEM markup with `acme-callout__title` / `acme-callout__content`.
 */
const v13 = {
	apiVersion: 3,
	attributes: {
		type: {
			type: 'string',
			default: 'info',
		},
		title: {
			type: 'string',
			source: 'html',
			selector: '.acme-callout__title',
		},
		content: {
			type: 'string',
			source: 'html',
			selector: '.acme-callout__content',
		},
		anchor: {
			type: 'string',
			source: 'attribute',
			attribute: 'id',
			selector: '*',
		},
	},
	supports: {
		html: false,
		anchor: true,
	},
	save( { attributes } ) {
		const { type, title, content } = attributes;
		const blockProps = useBlockProps.save( {
			className: `acme-callout acme-callout--${ type }`,
		} );

		return (
			<div { ...blockProps }>
				{ title && (
					<RichText.Content
						tagName="p"
						className="acme-callout__title"
						value={ title }
					/>
				) }
				<RichText.Content
					tagName="div"
					className="acme-callout__content"
					value={ content }
				/>
			</div>
		);
	},
	migrate: migrateToInnerBlocks,
};

/**
 * v1.0 – v1.2: `<div class="callout callout-{type}"><strong>title</strong><p>content</p></div>`
 */
const v10 = {
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
	migrate: migrateToInnerBlocks,
};

export default [ v13, v10 ];

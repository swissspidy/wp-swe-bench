/**
 * Earlier saved formats of the TOC block.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * 1.0 – 1.2: <div> wrapper with an <h2> title and an unordered list.
 */
const v1 = {
	attributes: {
		title: {
			type: 'string',
			source: 'html',
			selector: '.acme-toc__title',
			default: 'Contents',
		},
		maxLevel: {
			type: 'number',
			default: 3,
		},
		headings: {
			type: 'array',
			default: [],
		},
	},
	supports: {
		html: false,
		multiple: false,
	},
	save( { attributes } ) {
		const { title, headings } = attributes;
		return (
			<div { ...useBlockProps.save( { className: 'acme-toc' } ) }>
				<RichText.Content
					tagName="h2"
					className="acme-toc__title"
					value={ title }
				/>
				<ul className="acme-toc__list">
					{ headings.map( ( heading, index ) => (
						<li key={ index }>
							<a href={ `#${ heading.anchor }` }>
								{ heading.text }
							</a>
						</li>
					) ) }
				</ul>
			</div>
		);
	},
	migrate( attributes ) {
		return {
			...attributes,
			excludedBlocks: [],
		};
	},
};

export default [ v1 ];

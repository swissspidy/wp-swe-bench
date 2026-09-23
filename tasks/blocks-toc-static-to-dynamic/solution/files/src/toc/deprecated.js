/**
 * Earlier saved formats of the TOC block. They all migrate to the dynamic
 * block (2.0): the stored heading list is dropped and an untouched default
 * title becomes "use the default title".
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

const supports13 = {
	html: false,
	multiple: false,
	spacing: {
		margin: true,
		padding: true,
	},
	color: {
		background: true,
		text: true,
		link: true,
	},
};

function migrateTitle( attributes, legacyDefault ) {
	const { title, headings, ...rest } = attributes;
	if ( title === legacyDefault || title === undefined ) {
		return rest;
	}
	return { ...rest, title };
}

/**
 * 1.3 – 1.4: <nav> with a paragraph title and an ordered list.
 */
const v13 = {
	attributes: {
		title: {
			type: 'string',
			source: 'html',
			selector: '.acme-toc__title',
			default: 'Table of contents',
		},
		maxLevel: {
			type: 'number',
			default: 3,
		},
		excludedBlocks: {
			type: 'array',
			default: [],
		},
		headings: {
			type: 'array',
			default: [],
		},
	},
	supports: supports13,
	save( { attributes } ) {
		const { title, headings } = attributes;
		const blockProps = useBlockProps.save( {
			'aria-label': title || 'Table of contents',
		} );

		return (
			<nav { ...blockProps }>
				{ ! RichText.isEmpty( title ) && (
					<RichText.Content
						tagName="p"
						className="acme-toc__title"
						value={ title }
					/>
				) }
				<ol className="acme-toc__list">
					{ headings.map( ( heading, index ) => (
						<li
							key={ index }
							className={ `acme-toc__item acme-toc__item--h${ heading.level }` }
						>
							<a href={ `#${ heading.anchor }` }>
								{ heading.text }
							</a>
						</li>
					) ) }
				</ol>
			</nav>
		);
	},
	migrate( attributes ) {
		return migrateTitle( attributes, 'Table of contents' );
	},
};

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
		return { ...migrateTitle( attributes, 'Contents' ), excludedBlocks: [] };
	},
};

export default [ v13, v1 ];

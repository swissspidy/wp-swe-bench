/**
 * Earlier saved formats of the testimonial block.
 *
 * Newest first.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

/**
 * 2.x: author split into name + role, rating stored as a string ("4").
 */
const v2 = {
	apiVersion: 2,
	attributes: {
		quote: {
			type: 'string',
			source: 'html',
			selector: '.acme-testimonial__quote p',
		},
		authorName: {
			type: 'string',
			source: 'html',
			selector: '.acme-testimonial__name',
		},
		authorRole: {
			type: 'string',
			source: 'html',
			selector: '.acme-testimonial__role',
		},
		rating: {
			type: 'string',
			default: '',
		},
	},
	supports: {
		html: false,
		align: [ 'left', 'right', 'wide' ],
	},
	save( { attributes } ) {
		const { quote, authorName, authorRole, rating } = attributes;
		const count = Math.round( parseFloat( rating ) || 0 );
		return (
			<figure { ...useBlockProps.save() }>
				<blockquote className="acme-testimonial__quote">
					<RichText.Content tagName="p" value={ quote } />
				</blockquote>
				<figcaption className="acme-testimonial__byline">
					<RichText.Content
						tagName="span"
						className="acme-testimonial__name"
						value={ authorName }
					/>
					{ authorRole && (
						<RichText.Content
							tagName="span"
							className="acme-testimonial__role"
							value={ authorRole }
						/>
					) }
				</figcaption>
				{ rating && (
					<div className="acme-testimonial__rating" data-rating={ rating }>
						{ '★'.repeat( count ) + '☆'.repeat( Math.max( 0, 5 - count ) ) }
					</div>
				) }
			</figure>
		);
	},
	migrate( attributes ) {
		return {
			...attributes,
			rating: parseInt( attributes.rating, 10 ) || 0,
		};
	},
};

/**
 * 1.x: a single "author" field.
 *
 * TODO: some 1.x testimonials still show "This block contains unexpected or invalid content".
 */
const v1 = {
	attributes: {
		quote: {
			type: 'string',
			source: 'html',
			selector: '.acme-testimonial__quote',
		},
		author: {
			type: 'string',
			source: 'html',
			selector: '.acme-testimonial__author',
		},
	},
	supports: {
		html: false,
	},
	save( { attributes } ) {
		return (
			<blockquote className="acme-testimonial">
				<RichText.Content
					tagName="p"
					className="acme-testimonial__quote"
					value={ attributes.quote }
				/>
				<RichText.Content
					tagName="p"
					className="acme-testimonial__author"
					value={ attributes.author }
				/>
			</blockquote>
		);
	},
	migrate( { quote, author } ) {
		return { quote, authorName: author };
	},
};

export default [ v2, v1 ];

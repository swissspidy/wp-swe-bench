/**
 * Earlier saved formats of the testimonial block.
 *
 * Newest first. Every migration returns 4.x attributes: a numeric rating from 0 to 5 in
 * half steps (0 = not rated), author name and role as separate fields.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { normalizeRating, splitAuthor, stars, stripTags } from './utils';

const v3Attributes = {
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
		type: 'number',
		default: 0,
	},
	avatarId: {
		type: 'number',
	},
	avatarUrl: {
		type: 'string',
		source: 'attribute',
		selector: '.acme-testimonial__avatar',
		attribute: 'src',
	},
};

const v3Supports = {
	html: false,
	align: [ 'left', 'right', 'wide' ],
};

/**
 * 3.x save(): avatar above the quote, name in a <span>, whole stars as text.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Attributes (rating is a whole number).
 */
function saveV3( { attributes } ) {
	const { quote, authorName, authorRole, rating, avatarId, avatarUrl } = attributes;
	return (
		<figure { ...useBlockProps.save( { className: rating > 0 ? 'has-rating' : undefined } ) }>
			{ avatarUrl && (
				<img
					className={ `acme-testimonial__avatar wp-image-${ avatarId }` }
					src={ avatarUrl }
					alt={ stripTags( authorName ) }
				/>
			) }
			<blockquote className="acme-testimonial__quote">
				<RichText.Content tagName="p" value={ quote } />
			</blockquote>
			<figcaption className="acme-testimonial__byline">
				<RichText.Content
					tagName="span"
					className="acme-testimonial__name"
					value={ authorName }
				/>
				{ ! RichText.isEmpty( authorRole ) && (
					<RichText.Content
						tagName="span"
						className="acme-testimonial__role"
						value={ authorRole }
					/>
				) }
			</figcaption>
			{ rating > 0 && (
				<div
					className="acme-testimonial__rating"
					data-rating={ rating }
					aria-label={ `${ rating } out of 5 stars` }
				>
					{ stars( rating ) }
				</div>
			) }
		</figure>
	);
}

const migrateRating = ( attributes ) => ( {
	...attributes,
	rating: normalizeRating( attributes.rating ),
} );

/**
 * 3.x (blocks saved in the editor).
 */
const v3 = {
	apiVersion: 3,
	attributes: v3Attributes,
	supports: v3Supports,
	save: saveV3,
	migrate: migrateRating,
};

/**
 * 2.x: author split into name + role, rating stored as a string ("4", "4.5").
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
	supports: v3Supports,
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
	migrate: migrateRating,
};

/**
 * 1.x: <blockquote> with the quote paragraph and a single "Name, Role" <cite>.
 *
 * Saved with block API version 1: the wp-block-acme-testimonial class and custom
 * class names were added to the root element automatically.
 */
const v1 = {
	apiVersion: 1,
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
					tagName="cite"
					className="acme-testimonial__author"
					value={ attributes.author }
				/>
			</blockquote>
		);
	},
	migrate( { author, ...attributes } ) {
		return { ...attributes, ...splitAuthor( author ), rating: 0 };
	},
};

export default [ v3, v2, v1 ];

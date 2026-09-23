/**
 * Saved markup (4.x).
 *
 * Keep includes/class-markup.php (CSV importer) in sync with this.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import Rating from './rating';
import { normalizeRating } from './utils';

export default function save( { attributes } ) {
	const { quote, authorName, authorRole, avatarUrl } = attributes;
	const rating = normalizeRating( attributes.rating );

	return (
		<figure { ...useBlockProps.save( { className: rating > 0 ? 'has-rating' : undefined } ) }>
			<blockquote className="acme-testimonial__quote">
				<RichText.Content tagName="p" value={ quote } />
			</blockquote>
			<figcaption className="acme-testimonial__byline">
				{ avatarUrl && (
					<img
						className="acme-testimonial__avatar"
						src={ avatarUrl }
						alt=""
						width="48"
						height="48"
					/>
				) }
				<RichText.Content
					tagName="cite"
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
			{ rating > 0 && <Rating rating={ rating } /> }
		</figure>
	);
}

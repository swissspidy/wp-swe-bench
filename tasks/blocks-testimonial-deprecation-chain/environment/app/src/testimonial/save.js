/**
 * Saved markup (3.x).
 *
 * Keep includes/class-markup.php (CSV importer) in sync with this.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { stars, stripTags } from './utils';

export default function save( { attributes } ) {
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
				// Not translatable: save() output must not depend on the editor language.
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

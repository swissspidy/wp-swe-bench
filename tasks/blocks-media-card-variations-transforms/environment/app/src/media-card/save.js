/**
 * Saved markup (1.1+).
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

import CardImage from './image';

export default function save( { attributes } ) {
	const { mediaUrl, heading, text, ctaText, ctaUrl } = attributes;
	return (
		<div { ...useBlockProps.save() }>
			{ mediaUrl && (
				<figure className="wp-block-acme-media-card__media">
					<CardImage { ...attributes } />
				</figure>
			) }
			<div className="wp-block-acme-media-card__content">
				{ ! RichText.isEmpty( heading ) && (
					<RichText.Content tagName="h3" className="wp-block-acme-media-card__heading" value={ heading } />
				) }
				{ ! RichText.isEmpty( text ) && (
					<RichText.Content tagName="p" className="wp-block-acme-media-card__text" value={ text } />
				) }
				{ ctaText && ctaUrl && (
					<RichText.Content tagName="a" className="wp-block-acme-media-card__cta" href={ ctaUrl } value={ ctaText } />
				) }
			</div>
		</div>
	);
}

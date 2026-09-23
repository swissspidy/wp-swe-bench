/**
 * Saved markup (2.0): layout + card type classes and the optional meta line.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

import CardImage from './image';

/**
 * Classes for the card wrapper.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} Class names.
 */
export function cardClassName( { layout, cardType } ) {
	return [ `has-layout-${ layout || 'stacked' }`, cardType ? `is-${ cardType }-card` : '' ].filter( Boolean ).join( ' ' );
}

export default function save( { attributes } ) {
	const { mediaUrl, meta, heading, text, ctaText, ctaUrl } = attributes;
	return (
		<div { ...useBlockProps.save( { className: cardClassName( attributes ) } ) }>
			{ mediaUrl && (
				<figure className="wp-block-acme-media-card__media">
					<CardImage { ...attributes } />
				</figure>
			) }
			<div className="wp-block-acme-media-card__content">
				{ ! RichText.isEmpty( meta ) && (
					<RichText.Content tagName="p" className="wp-block-acme-media-card__meta" value={ meta } />
				) }
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

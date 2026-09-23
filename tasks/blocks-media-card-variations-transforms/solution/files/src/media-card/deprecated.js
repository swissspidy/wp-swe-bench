/**
 * Deprecations.
 *
 * 1.1–1.2: no layout / card type classes, no meta line.
 * 1.0: the heading was an <h2 class="…__title">, the CTA used "…__button" and images could not be linked.
 *
 * Attributes added in 2.0 (layout, cardType) get their defaults when an old card is upgraded.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

import CardImage from './image';

const v1 = {
	attributes: {
		mediaId: { type: 'number' },
		mediaUrl: { type: 'string', source: 'attribute', selector: 'img', attribute: 'src' },
		mediaAlt: { type: 'string', source: 'attribute', selector: 'img', attribute: 'alt', default: '' },
		mediaLink: { type: 'string', source: 'attribute', selector: '.wp-block-acme-media-card__media a', attribute: 'href' },
		heading: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__heading' },
		text: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__text' },
		ctaText: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__cta' },
		ctaUrl: { type: 'string', source: 'attribute', selector: '.wp-block-acme-media-card__cta', attribute: 'href' },
	},
	supports: {
		anchor: true,
		align: [ 'wide', 'full' ],
		html: false,
		color: { background: true, text: true, link: true },
		spacing: { padding: true },
	},
	migrate( attributes ) {
		return { ...attributes, layout: 'stacked', cardType: '' };
	},
	save( { attributes } ) {
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
	},
};

const v0 = {
	migrate( attributes ) {
		return { ...attributes, layout: 'stacked', cardType: '' };
	},
	attributes: {
		mediaId: { type: 'number' },
		mediaUrl: { type: 'string', source: 'attribute', selector: 'img', attribute: 'src' },
		mediaAlt: { type: 'string', source: 'attribute', selector: 'img', attribute: 'alt', default: '' },
		heading: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__title' },
		text: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__text' },
		ctaText: { type: 'string', source: 'html', selector: '.wp-block-acme-media-card__button' },
		ctaUrl: { type: 'string', source: 'attribute', selector: '.wp-block-acme-media-card__button', attribute: 'href' },
	},
	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		color: { background: true, text: true },
	},
	save( { attributes } ) {
		const { mediaId, mediaUrl, mediaAlt, heading, text, ctaText, ctaUrl } = attributes;
		return (
			<div { ...useBlockProps.save() }>
				{ mediaUrl && (
					<figure className="wp-block-acme-media-card__media">
						<img src={ mediaUrl } alt={ mediaAlt } className={ mediaId ? `wp-image-${ mediaId }` : undefined } />
					</figure>
				) }
				<div className="wp-block-acme-media-card__content">
					{ ! RichText.isEmpty( heading ) && (
						<RichText.Content tagName="h2" className="wp-block-acme-media-card__title" value={ heading } />
					) }
					{ ! RichText.isEmpty( text ) && (
						<RichText.Content tagName="p" className="wp-block-acme-media-card__text" value={ text } />
					) }
					{ ctaText && ctaUrl && (
						<a className="wp-block-acme-media-card__button" href={ ctaUrl }>{ ctaText }</a>
					) }
				</div>
			</div>
		);
	},
};

export default [ v1, v0 ];

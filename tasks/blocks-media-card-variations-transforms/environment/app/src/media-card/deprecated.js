/**
 * Deprecations.
 *
 * 1.0: the heading was an <h2 class="…__title">, the CTA used "…__button" and images could not be linked.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

const v0 = {
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

export default [ v0 ];

/**
 * Transforms between the media card and core/image, core/media-text.
 */
import { createBlock } from '@wordpress/blocks';

const NAME = 'acme/media-card';

/** Rich text values (RichTextData in recent WordPress) as HTML strings. */
const html = ( value ) => ( value === undefined || value === null ? undefined : String( value ) );

/** Depth-first list of blocks. */
const flatten = ( blocks = [] ) => blocks.flatMap( ( block ) => [ block, ...flatten( block.innerBlocks ) ] );

const transforms = {
	from: [
		{
			type: 'block',
			blocks: [ 'core/image' ],
			isMatch: ( { url } ) => !! url,
			transform: ( { id, url, alt, href, caption } ) =>
				createBlock( NAME, {
					mediaId: id,
					mediaUrl: url,
					mediaAlt: alt || '',
					mediaLink: href || undefined,
					text: html( caption ) || undefined,
				} ),
		},
		{
			type: 'block',
			blocks: [ 'core/media-text' ],
			isMatch: ( { mediaType, mediaUrl } ) => ( ! mediaType || mediaType === 'image' ) && !! mediaUrl,
			transform: ( { mediaId, mediaUrl, mediaAlt, href, mediaPosition }, innerBlocks ) => {
				const all = flatten( innerBlocks );
				const heading = all.find( ( b ) => b.name === 'core/heading' );
				const paragraph = all.find( ( b ) => b.name === 'core/paragraph' && html( b.attributes.content ) );
				const button = all.find( ( b ) => b.name === 'core/button' );
				return createBlock( NAME, {
					mediaId,
					mediaUrl,
					mediaAlt: mediaAlt || '',
					mediaLink: href || undefined,
					heading: heading ? html( heading.attributes.content ) : undefined,
					text: paragraph ? html( paragraph.attributes.content ) : undefined,
					ctaText: button ? html( button.attributes.text ) : undefined,
					ctaUrl: button ? button.attributes.url : undefined,
					layout: mediaPosition === 'right' ? 'media-right' : 'media-left',
				} );
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: [ 'core/media-text' ],
			transform: ( { mediaId, mediaUrl, mediaAlt, mediaLink, heading, text, ctaText, ctaUrl, layout } ) => {
				const inner = [];
				if ( heading ) {
					inner.push( createBlock( 'core/heading', { level: 3, content: heading } ) );
				}
				if ( text ) {
					inner.push( createBlock( 'core/paragraph', { content: text } ) );
				}
				if ( ctaText && ctaUrl ) {
					inner.push( createBlock( 'core/buttons', {}, [ createBlock( 'core/button', { text: ctaText, url: ctaUrl } ) ] ) );
				}
				if ( ! inner.length ) {
					inner.push( createBlock( 'core/paragraph' ) );
				}
				return createBlock(
					'core/media-text',
					{
						mediaId,
						mediaUrl,
						mediaAlt,
						mediaType: mediaUrl ? 'image' : undefined,
						href: mediaLink,
						mediaPosition: layout === 'media-right' ? 'right' : 'left',
					},
					inner
				);
			},
		},
	],
};

export default transforms;

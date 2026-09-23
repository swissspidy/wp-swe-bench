/**
 * The card image (optionally linked), shared by save() and the deprecations.
 */
export default function CardImage( { mediaId, mediaUrl, mediaAlt, mediaLink } ) {
	const image = (
		<img
			src={ mediaUrl }
			alt={ mediaAlt }
			className={ mediaId ? `wp-image-${ mediaId }` : undefined }
		/>
	);
	return mediaLink ? <a href={ mediaLink }>{ image }</a> : image;
}

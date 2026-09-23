/**
 * Testimonial block: editor.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { Button, PanelBody, RangeControl } from '@wordpress/components';

import Rating from './rating';
import { normalizeRating } from './utils';

/**
 * URL of the size used for avatars: the thumbnail when there is one.
 *
 * @param {Object} media Media object from the media library.
 * @return {string} URL.
 */
const avatarSrc = ( media ) => media?.sizes?.thumbnail?.url || media?.url || '';

export default function Edit( { attributes, setAttributes } ) {
	const { quote, authorName, authorRole, avatarId, avatarUrl } = attributes;
	const rating = normalizeRating( attributes.rating );
	const blockProps = useBlockProps( { className: rating > 0 ? 'has-rating' : undefined } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Rating', 'acme-testimonials' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Rating', 'acme-testimonials' ) }
						help={ __( '0 = not rated. Half stars are allowed.', 'acme-testimonials' ) }
						value={ rating }
						min={ 0 }
						max={ 5 }
						step={ 0.5 }
						onChange={ ( value ) => setAttributes( { rating: normalizeRating( value ) } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Photo', 'acme-testimonials' ) }>
					<MediaUploadCheck>
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ avatarId }
							onSelect={ ( media ) =>
								setAttributes( { avatarId: media.id, avatarUrl: avatarSrc( media ) } )
							}
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
									{ avatarUrl
										? __( 'Replace avatar', 'acme-testimonials' )
										: __( 'Choose avatar', 'acme-testimonials' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					{ avatarUrl && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => setAttributes( { avatarId: undefined, avatarUrl: undefined } ) }
						>
							{ __( 'Remove avatar', 'acme-testimonials' ) }
						</Button>
					) }
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				<blockquote className="acme-testimonial__quote">
					<RichText
						tagName="p"
						value={ quote }
						placeholder={ __( 'What did the customer say?', 'acme-testimonials' ) }
						onChange={ ( value ) => setAttributes( { quote: value } ) }
					/>
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
					<RichText
						tagName="cite"
						className="acme-testimonial__name"
						value={ authorName }
						placeholder={ __( 'Name', 'acme-testimonials' ) }
						allowedFormats={ [ 'core/bold', 'core/italic' ] }
						onChange={ ( value ) => setAttributes( { authorName: value } ) }
					/>
					<RichText
						tagName="span"
						className="acme-testimonial__role"
						value={ authorRole }
						placeholder={ __( 'Role, company', 'acme-testimonials' ) }
						allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
						onChange={ ( value ) => setAttributes( { authorRole: value } ) }
					/>
				</figcaption>
				{ rating > 0 && <Rating rating={ rating } /> }
			</figure>
		</>
	);
}

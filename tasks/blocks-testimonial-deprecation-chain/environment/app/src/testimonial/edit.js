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

import { stars, stripTags } from './utils';

export default function Edit( { attributes, setAttributes } ) {
	const { quote, authorName, authorRole, rating, avatarId, avatarUrl } = attributes;
	const blockProps = useBlockProps( { className: rating > 0 ? 'has-rating' : undefined } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Rating', 'acme-testimonials' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Rating', 'acme-testimonials' ) }
						help={ __( '0 = no rating.', 'acme-testimonials' ) }
						value={ rating }
						min={ 0 }
						max={ 5 }
						step={ 1 }
						onChange={ ( value ) => setAttributes( { rating: value || 0 } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Photo', 'acme-testimonials' ) }>
					<MediaUploadCheck>
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							value={ avatarId }
							onSelect={ ( media ) =>
								setAttributes( { avatarId: media.id, avatarUrl: media.url } )
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
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				{ avatarUrl && (
					<img
						className={ `acme-testimonial__avatar wp-image-${ avatarId }` }
						src={ avatarUrl }
						alt={ stripTags( authorName ) }
					/>
				) }
				<blockquote className="acme-testimonial__quote">
					<RichText
						tagName="p"
						value={ quote }
						placeholder={ __( 'What did the customer say?', 'acme-testimonials' ) }
						onChange={ ( value ) => setAttributes( { quote: value } ) }
					/>
				</blockquote>
				<figcaption className="acme-testimonial__byline">
					<RichText
						tagName="span"
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
				{ rating > 0 && (
					<div className="acme-testimonial__rating" data-rating={ rating }>
						{ stars( rating ) }
					</div>
				) }
			</figure>
		</>
	);
}

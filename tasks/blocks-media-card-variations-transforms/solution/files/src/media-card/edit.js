/**
 * Editor UI of the media card.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	MediaPlaceholder,
	MediaReplaceFlow,
	BlockControls,
	InspectorControls,
	__experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, TextareaControl, ToolbarButton, Popover } from '@wordpress/components';
import { useState } from '@wordpress/element';

import { cardClassName } from './save';
import { META_PLACEHOLDERS } from './variations';

const ALLOWED_MEDIA_TYPES = [ 'image' ];

export default function Edit( { attributes, setAttributes, isSelected } ) {
	const { mediaId, mediaUrl, mediaAlt, mediaLink, meta, heading, text, ctaText, ctaUrl, layout, cardType } = attributes;
	const [ isEditingLink, setIsEditingLink ] = useState( false );
	const blockProps = useBlockProps( { className: cardClassName( attributes ) } );

	const onSelectMedia = ( media ) =>
		setAttributes( {
			mediaId: media?.id,
			mediaUrl: media?.url,
			mediaAlt: media?.alt || '',
		} );

	return (
		<>
			<BlockControls group="other">
				{ mediaUrl && (
					<MediaReplaceFlow
						mediaId={ mediaId }
						mediaURL={ mediaUrl }
						allowedTypes={ ALLOWED_MEDIA_TYPES }
						accept="image/*"
						onSelect={ onSelectMedia }
						name={ __( 'Replace image', 'acme-media-card' ) }
					/>
				) }
				{ mediaUrl && (
					<ToolbarButton
						icon="admin-links"
						label={ __( 'Link image', 'acme-media-card' ) }
						isPressed={ !! mediaLink }
						onClick={ () => setIsEditingLink( true ) }
					/>
				) }
			</BlockControls>
			{ isEditingLink && (
				<Popover placement="bottom" onClose={ () => setIsEditingLink( false ) }>
					<LinkControl
						value={ { url: mediaLink } }
						onChange={ ( { url } ) => setAttributes( { mediaLink: url } ) }
						onRemove={ () => setAttributes( { mediaLink: undefined } ) }
					/>
				</Popover>
			) }
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'acme-media-card' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'acme-media-card' ) }
						value={ layout || 'stacked' }
						options={ [
							{ value: 'stacked', label: __( 'Image above text', 'acme-media-card' ) },
							{ value: 'media-left', label: __( 'Image on the left', 'acme-media-card' ) },
							{ value: 'media-right', label: __( 'Image on the right', 'acme-media-card' ) },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Image', 'acme-media-card' ) }>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Alternative text', 'acme-media-card' ) }
						value={ mediaAlt }
						onChange={ ( value ) => setAttributes( { mediaAlt: value } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Call to action', 'acme-media-card' ) }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Link URL', 'acme-media-card' ) }
						value={ ctaUrl || '' }
						onChange={ ( value ) => setAttributes( { ctaUrl: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<figure className="wp-block-acme-media-card__media">
					{ mediaUrl ? (
						<img src={ mediaUrl } alt={ mediaAlt } className={ mediaId ? `wp-image-${ mediaId }` : undefined } />
					) : (
						<MediaPlaceholder
							icon="format-image"
							labels={ { title: __( 'Card image', 'acme-media-card' ) } }
							allowedTypes={ ALLOWED_MEDIA_TYPES }
							accept="image/*"
							onSelect={ onSelectMedia }
						/>
					) }
				</figure>
				<div className="wp-block-acme-media-card__content">
					{ ( cardType || meta ) && (
						<RichText
							tagName="p"
							className="wp-block-acme-media-card__meta"
							placeholder={ META_PLACEHOLDERS[ cardType ] || __( 'Meta', 'acme-media-card' ) }
							value={ meta }
							onChange={ ( value ) => setAttributes( { meta: value } ) }
						/>
					) }
					<RichText
						tagName="h3"
						className="wp-block-acme-media-card__heading"
						placeholder={ __( 'Heading', 'acme-media-card' ) }
						value={ heading }
						onChange={ ( value ) => setAttributes( { heading: value } ) }
					/>
					<RichText
						tagName="p"
						className="wp-block-acme-media-card__text"
						placeholder={ __( 'Write the card text…', 'acme-media-card' ) }
						value={ text }
						onChange={ ( value ) => setAttributes( { text: value } ) }
					/>
					{ ( isSelected || ctaText ) && (
						<RichText
							tagName="span"
							className="wp-block-acme-media-card__cta"
							placeholder={ __( 'Call to action', 'acme-media-card' ) }
							value={ ctaText }
							allowedFormats={ [] }
							withoutInteractiveFormatting
							onChange={ ( value ) => setAttributes( { ctaText: value } ) }
						/>
					) }
				</div>
			</div>
		</>
	);
}

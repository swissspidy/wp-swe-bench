/**
 * Editor UI for the CTA block.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	HeadingLevelDropdown,
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { getVariants, isTrackingEnabled } from './variants';

export default function Edit( { attributes, setAttributes } ) {
	const {
		heading,
		headingLevel,
		buttonText,
		buttonUrl,
		variant,
		opensInNewTab,
		campaign,
	} = attributes;
	const blockProps = useBlockProps( {
		className: `is-variant-${ variant }`,
	} );

	return (
		<>
			<BlockControls group="block">
				<HeadingLevelDropdown
					options={ [ 2, 3, 4 ] }
					value={ headingLevel }
					onChange={ ( level ) =>
						setAttributes( { headingLevel: level } )
					}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Button', 'acme-cta' ) }>
					<TextControl
						label={ __( 'Button link', 'acme-cta' ) }
						type="url"
						value={ buttonUrl }
						onChange={ ( value ) =>
							setAttributes( { buttonUrl: value } )
						}
						help={ __(
							'Where the button takes visitors.',
							'acme-cta'
						) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Open in new tab', 'acme-cta' ) }
						checked={ !! opensInNewTab }
						onChange={ ( value ) =>
							setAttributes( { opensInNewTab: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Appearance', 'acme-cta' ) }>
					<SelectControl
						label={ __( 'Variant', 'acme-cta' ) }
						value={ variant }
						options={ getVariants() }
						onChange={ ( value ) =>
							setAttributes( { variant: value } )
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				{ isTrackingEnabled() && (
					<PanelBody title={ __( 'Tracking', 'acme-cta' ) }>
						<TextControl
							label={ __( 'Campaign', 'acme-cta' ) }
							value={ campaign }
							onChange={ ( value ) =>
								setAttributes( { campaign: value } )
							}
							help={ __(
								'Sent as utm_campaign for links to other sites.',
								'acme-cta'
							) }
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				) }
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					identifier="heading"
					tagName={ `h${ headingLevel }` }
					className="wp-block-acme-cta__heading"
					value={ heading }
					onChange={ ( value ) => setAttributes( { heading: value } ) }
					placeholder={ __( 'CTA heading', 'acme-cta' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
				<RichText
					identifier="buttonText"
					tagName="span"
					className="wp-block-acme-cta__button wp-element-button"
					value={ buttonText }
					onChange={ ( value ) =>
						setAttributes( { buttonText: value } )
					}
					placeholder={ __( 'Button text', 'acme-cta' ) }
					allowedFormats={ [] }
					withoutInteractiveFormatting
				/>
			</div>
		</>
	);
}

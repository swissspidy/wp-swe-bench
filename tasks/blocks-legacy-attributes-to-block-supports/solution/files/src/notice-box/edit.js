/**
 * Editor UI for the notice box. Colours, padding and font size are handled
 * by the standard block supports (sidebar "Color", "Dimensions", "Typography").
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import { getTones } from './tones';

const TEMPLATE = [ [ 'core/paragraph', {} ] ];

export default function Edit( { attributes, setAttributes } ) {
	const { tone, showIcon } = attributes;
	const blockProps = useBlockProps( {
		className: `is-tone-${ tone }`,
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wp-block-acme-notice-box__body' },
		{ template: TEMPLATE, templateLock: false }
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Notice', 'acme-content-blocks' ) }>
					<SelectControl
						label={ __( 'Tone', 'acme-content-blocks' ) }
						value={ tone }
						options={ getTones() }
						onChange={ ( value ) => setAttributes( { tone: value } ) }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show icon', 'acme-content-blocks' ) }
						checked={ showIcon }
						onChange={ ( value ) =>
							setAttributes( { showIcon: value } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showIcon && (
					<span
						className="wp-block-acme-notice-box__icon"
						aria-hidden="true"
					/>
				) }
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}

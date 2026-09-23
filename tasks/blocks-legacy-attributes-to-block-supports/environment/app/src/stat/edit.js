/**
 * Editor UI for the statistic block.
 */
import { __ } from '@wordpress/i18n';
import {
	AlignmentControl,
	BlockControls,
	InspectorControls,
	PanelColorSettings,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';

import statProps from './props';

export default function Edit( { attributes, setAttributes } ) {
	const { value, label, alignment, color, fontSize, boxed } = attributes;

	return (
		<>
			<BlockControls group="block">
				<AlignmentControl
					value={ alignment }
					onChange={ ( next ) =>
						setAttributes( { alignment: next || 'left' } )
					}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Statistic', 'acme-content-blocks' ) }>
					<RangeControl
						label={ __( 'Number size (px)', 'acme-content-blocks' ) }
						value={ fontSize }
						onChange={ ( next ) =>
							setAttributes( { fontSize: next ?? 48 } )
						}
						min={ 24 }
						max={ 96 }
						allowReset
						resetFallbackValue={ 48 }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show as a box', 'acme-content-blocks' ) }
						checked={ boxed }
						onChange={ ( next ) => setAttributes( { boxed: next } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelColorSettings
					title={ __( 'Color', 'acme-content-blocks' ) }
					colorSettings={ [
						{
							value: color,
							onChange: ( next ) => setAttributes( { color: next } ),
							label: __( 'Number color', 'acme-content-blocks' ),
						},
					] }
				/>
			</InspectorControls>
			<div { ...useBlockProps( statProps( attributes ) ) }>
				<RichText
					tagName="span"
					className="wp-block-acme-stat__value"
					value={ value }
					onChange={ ( next ) => setAttributes( { value: next } ) }
					placeholder={ __( '42%', 'acme-content-blocks' ) }
					allowedFormats={ [] }
				/>
				<RichText
					tagName="span"
					className="wp-block-acme-stat__label"
					value={ label }
					onChange={ ( next ) => setAttributes( { label: next } ) }
					placeholder={ __( 'Label', 'acme-content-blocks' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
			</div>
		</>
	);
}

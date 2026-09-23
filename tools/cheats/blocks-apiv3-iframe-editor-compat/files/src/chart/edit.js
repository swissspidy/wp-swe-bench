/**
 * Chart block: editor.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';

import SeriesEditor from './series-editor';
import useChartPreview from './use-chart-preview';

export default function Edit( { attributes, setAttributes } ) {
	const { title, series, height, showValues, anchor } = attributes;

	// Draw the preview with the shared renderer, and redraw when the canvas is resized.
	const canvasRef = useChartPreview( { series, height, showValues } );

	const blockProps = useBlockProps( {
		className: 'acme-chart',
		// Lets legends in the canvas find this chart.
		'data-acme-anchor': anchor || undefined,
	} );

	return (
		<>
			<InspectorControls>
				<SeriesEditor
					series={ series }
					onChange={ ( next ) => setAttributes( { series: next } ) }
				/>
				<PanelBody title={ __( 'Display', 'acme-charts' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Height (px)', 'acme-charts' ) }
						value={ height }
						min={ 80 }
						max={ 800 }
						step={ 10 }
						onChange={ ( value ) => setAttributes( { height: value || 240 } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show values above bars', 'acme-charts' ) }
						checked={ showValues }
						onChange={ ( value ) => setAttributes( { showValues: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<figure { ...blockProps }>
				{ series.length ? (
					<div
						ref={ canvasRef }
						className="acme-chart__canvas"
						style={ { height: `${ height }px` } }
					/>
				) : (
					<div className="acme-chart-placeholder">
						{ __( 'Add data in the block settings to draw the chart.', 'acme-charts' ) }
					</div>
				) }
				<RichText
					tagName="figcaption"
					className="acme-chart__title"
					value={ title }
					placeholder={ __( 'Chart title (optional)', 'acme-charts' ) }
					onChange={ ( value ) => setAttributes( { title: value } ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
				/>
			</figure>
		</>
	);
}

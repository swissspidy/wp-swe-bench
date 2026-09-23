/**
 * Chart block: editor.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import $ from 'jquery';

import SeriesEditor from './series-editor';

export default function Edit( { attributes, setAttributes, className, clientId } ) {
	const { title, series, height, showValues, anchor } = attributes;
	const previewId = `acme-chart-preview-${ clientId }`;

	// Draw the preview with the shared renderer, and redraw on resize.
	useEffect( () => {
		if ( ! series.length ) {
			return;
		}
		const redraw = () => {
			try {
				window.AcmeCharts.draw( document.getElementById( previewId ), {
					series,
					height,
					showValues,
				} );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Acme Charts: could not draw the chart preview.', error );
			}
		};
		redraw();
		$( window ).on( `resize.${ previewId }`, redraw );
		return () => {
			$( window ).off( `resize.${ previewId }` );
		};
	}, [ previewId, series, height, showValues ] );

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
			<figure
				className={ `${ className || '' } acme-chart`.trim() }
				data-acme-anchor={ anchor || undefined }
			>
				{ series.length ? (
					<div
						id={ previewId }
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

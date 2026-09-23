/**
 * Chart legend block: editor.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

import { useCharts } from './charts';

const colorFor = ( point, index ) =>
	window.AcmeCharts ? window.AcmeCharts.colorFor( point, index ) : point.color;

export default function Edit( { attributes, setAttributes } ) {
	const { chartId, layout } = attributes;
	const charts = useCharts();
	const chart = charts.find( ( block ) => block.attributes.anchor === chartId );
	const series = chart ? chart.attributes.series || [] : [];

	const classes = [ 'acme-legend' ];
	if ( 'vertical' === layout ) {
		classes.push( 'is-vertical' );
	}
	if ( ! chart ) {
		classes.push( 'is-unlinked' );
	}
	const blockProps = useBlockProps( {
		className: classes.join( ' ' ),
		'data-chart': chartId || undefined,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Legend', 'acme-charts' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Chart', 'acme-charts' ) }
						help={ __( 'Only charts with an HTML anchor can have a legend.', 'acme-charts' ) }
						value={ chartId }
						options={ [
							{ value: '', label: __( '— Select —', 'acme-charts' ) },
							...charts.map( ( block ) => ( {
								value: block.attributes.anchor,
								label: block.attributes.anchor,
							} ) ),
						] }
						onChange={ ( value ) => setAttributes( { chartId: value } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Layout', 'acme-charts' ) }
						value={ layout }
						options={ [
							{ value: 'horizontal', label: __( 'Horizontal', 'acme-charts' ) },
							{ value: 'vertical', label: __( 'Vertical', 'acme-charts' ) },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<ul { ...blockProps }>
				{ chart ? (
					series.map( ( point, index ) => (
						<li key={ index }>
							<button
								type="button"
								className="acme-legend__item"
								data-index={ index }
								aria-pressed="true"
							>
								<span
									className="acme-legend__swatch"
									style={ { backgroundColor: colorFor( point, index ) } }
								/>
								<span className="acme-legend__label">{ point.label }</span>
							</button>
						</li>
					) )
				) : (
					<li>{ __( 'Choose a chart in the block settings.', 'acme-charts' ) }</li>
				) }
			</ul>
		</>
	);
}

/**
 * Sidebar editor for the chart data.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ColorPalette,
	Dropdown,
	TextControl,
	TextareaControl,
	PanelBody,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

import { parseCsv, toCsv } from './csv';

const palette = () =>
	( ( window.acmeChartsSettings && window.acmeChartsSettings.palette ) || [] ).map(
		( color ) => ( { name: color, color } )
	);

export default function SeriesEditor( { series, onChange } ) {
	const [ csv, setCsv ] = useState( '' );

	const update = ( index, changes ) =>
		onChange(
			series.map( ( point, i ) =>
				i === index ? { ...point, ...changes } : point
			)
		);

	const remove = ( index ) => onChange( series.filter( ( _p, i ) => i !== index ) );

	return (
		<>
			<PanelBody title={ __( 'Data', 'acme-charts' ) }>
				{ series.length > 0 && (
					<div className="acme-charts-series">
						<span className="acme-charts-series__head">
							{ __( 'Label', 'acme-charts' ) }
						</span>
						<span className="acme-charts-series__head">
							{ __( 'Value', 'acme-charts' ) }
						</span>
						<span className="acme-charts-series__head" />
						<span className="acme-charts-series__head" />
						{ series.map( ( point, index ) => (
							<SeriesRow
								key={ index }
								index={ index }
								point={ point }
								onChange={ ( changes ) => update( index, changes ) }
								onRemove={ () => remove( index ) }
							/>
						) ) }
					</div>
				) }
				<Button
					variant="secondary"
					onClick={ () =>
						onChange( [
							...series,
							{
								/* translators: %d: bar number. */
								label: sprintf( __( 'Bar %d', 'acme-charts' ), series.length + 1 ),
								value: 0,
							},
						] )
					}
				>
					{ __( 'Add bar', 'acme-charts' ) }
				</Button>
			</PanelBody>
			<PanelBody title={ __( 'Paste CSV', 'acme-charts' ) } initialOpen={ false }>
				<div className="acme-charts-csv">
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'CSV (label, value, optional colour)', 'acme-charts' ) }
						value={ csv || toCsv( series ) }
						onChange={ setCsv }
						rows={ 6 }
					/>
				</div>
				<Button
					variant="secondary"
					disabled={ ! csv }
					onClick={ () => {
						onChange( parseCsv( csv ) );
						setCsv( '' );
					} }
				>
					{ __( 'Replace data', 'acme-charts' ) }
				</Button>
			</PanelBody>
		</>
	);
}

function SeriesRow( { index, point, onChange, onRemove } ) {
	return (
		<>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				hideLabelFromVision
				/* translators: %d: bar number. */
				label={ sprintf( __( 'Label of bar %d', 'acme-charts' ), index + 1 ) }
				value={ point.label }
				onChange={ ( label ) => onChange( { label } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				hideLabelFromVision
				type="number"
				/* translators: %d: bar number. */
				label={ sprintf( __( 'Value of bar %d', 'acme-charts' ), index + 1 ) }
				value={ point.value }
				onChange={ ( value ) => onChange( { value: parseFloat( value ) || 0 } ) }
			/>
			<Dropdown
				popoverProps={ { placement: 'left-start' } }
				renderToggle={ ( { onToggle } ) => (
					<button
						type="button"
						className="acme-charts-series__swatch"
						style={ {
							backgroundColor: window.AcmeCharts
								? window.AcmeCharts.colorFor( point, index )
								: point.color,
						} }
						onClick={ onToggle }
						aria-label={ __( 'Bar colour', 'acme-charts' ) }
					/>
				) }
				renderContent={ () => (
					<ColorPalette
						colors={ palette() }
						value={ point.color || undefined }
						onChange={ ( color ) => onChange( { color: color || '' } ) }
					/>
				) }
			/>
			<Button
				icon="no-alt"
				size="small"
				label={ __( 'Remove bar', 'acme-charts' ) }
				onClick={ onRemove }
			/>
		</>
	);
}

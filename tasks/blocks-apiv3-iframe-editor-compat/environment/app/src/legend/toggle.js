/**
 * Clicking a legend item in the editor shows/hides the bar in the chart
 * preview, like on the front end.
 */
import $ from 'jquery';

$( document ).on( 'click', '.acme-legend__item', function () {
	const $item = $( this );
	const chartId = $item.closest( '.acme-legend' ).attr( 'data-chart' );
	const chart = document.querySelector( `[data-acme-anchor="${ chartId }"]` );
	if ( ! chart || ! window.AcmeCharts ) {
		return;
	}
	const visible = window.AcmeCharts.toggle( chart, $item.attr( 'data-index' ) );
	$item.attr( 'aria-pressed', visible ? 'true' : 'false' );
} );

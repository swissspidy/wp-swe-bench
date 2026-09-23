/**
 * Front-end bootstrap: draw every chart and legend on the page, redraw on
 * resize, and make legend items toggle their bar.
 */
/* global jQuery, AcmeCharts */
jQuery( function ( $ ) {
	'use strict';

	var timer;

	AcmeCharts.init( document );

	$( window ).on( 'resize', function () {
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			$( '.acme-chart' ).each( function () {
				AcmeCharts.render( this );
			} );
		}, 100 );
	} );

	$( document ).on( 'click', '.acme-legend__item', function ( event ) {
		event.preventDefault();
		AcmeCharts.toggleFromLegend( this );
	} );
} );

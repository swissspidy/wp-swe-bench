/**
 * Acme Charts renderer.
 *
 * Draws the SVG for a chart and the items of a chart legend. Shared by the
 * front end (assets/js/frontend.js) and the block editor preview, so charts
 * look the same everywhere.
 *
 * Exposes `window.AcmeCharts`.
 *
 * Settings (palette, default height, strings) come from `window.acmeChartsSettings`.
 */
/* global jQuery */
( function ( $, window, document ) {
	'use strict';

	var SVG_NS = 'http://www.w3.org/2000/svg';

	// Geometry (px). Changing these changes every chart on the site.
	var LABEL_AREA = 20;
	var TOP_PADDING = 8;
	var GAP = 8;

	function settings() {
		return window.acmeChartsSettings || { palette: [ '#3858e9' ], defaultHeight: 240, i18n: {} };
	}

	function round( n ) {
		return Math.round( n * 100 ) / 100;
	}

	function sprintf( format, value ) {
		return String( format || '%s' ).replace( '%s', value );
	}

	/**
	 * Normalise a chart configuration.
	 *
	 * @param {Object} config Raw configuration ({ series, height, showValues }).
	 * @return {Object} Normalised configuration.
	 */
	function normalize( config ) {
		var s = settings();
		config = config || {};
		return {
			series: $.map( config.series || [], function ( point ) {
				return {
					label: String( ( point && point.label ) || '' ),
					value: Math.max( 0, parseFloat( point && point.value ) || 0 ),
					color: ( point && point.color ) || '',
				};
			} ),
			height: parseInt( config.height, 10 ) || s.defaultHeight || 240,
			showValues: !! config.showValues,
		};
	}

	/**
	 * Read the configuration stored in a saved chart element.
	 *
	 * Supports the 1.3+ markup (`data-chart` JSON) and the 1.0 markup
	 * (`data-series` JSON + `data-height`).
	 *
	 * @param {Element} chart The `.acme-chart` element.
	 * @return {Object|null} Configuration, or null.
	 */
	function readConfig( chart ) {
		var $chart = $( chart );
		var raw = $chart.attr( 'data-chart' );
		try {
			if ( raw ) {
				return normalize( JSON.parse( raw ) );
			}
			raw = $chart.attr( 'data-series' );
			if ( raw ) {
				return normalize( {
					series: JSON.parse( raw ),
					height: $chart.attr( 'data-height' ),
				} );
			}
		} catch ( e ) {
			// Broken JSON: treat as empty chart.
		}
		return null;
	}

	/**
	 * Colour of a bar: its own colour, or the palette colour for its position.
	 *
	 * @param {Object} point Series point.
	 * @param {number} index Position.
	 * @return {string} Colour.
	 */
	function colorFor( point, index ) {
		var palette = settings().palette || [];
		return point.color || palette[ index % palette.length ] || '#3858e9';
	}

	function svgEl( name, attrs ) {
		var node = document.createElementNS( SVG_NS, name );
		$.each( attrs || {}, function ( key, value ) {
			node.setAttribute( key, value );
		} );
		return node;
	}

	/**
	 * Draw a chart into its canvas element.
	 *
	 * Hidden bars (toggled through a legend) stay hidden when the chart is redrawn.
	 *
	 * @param {Element} canvas The `.acme-chart__canvas` element.
	 * @param {Object}  config Chart configuration.
	 */
	function draw( canvas, config ) {
		var width = canvas.clientWidth;
		var hidden = [];
		var svg, plotHeight, max, barWidth, height, n;

		config = normalize( config );
		height = config.height;
		n = config.series.length;

		$( canvas )
			.find( '.acme-chart__bar.is-hidden' )
			.each( function () {
				hidden.push( parseInt( this.getAttribute( 'data-index' ), 10 ) );
			} );
		$( canvas ).empty().css( 'height', height + 'px' );

		svg = svgEl( 'svg', {
			class: 'acme-chart__svg',
			width: width,
			height: height,
			viewBox: '0 0 ' + width + ' ' + height,
			'aria-hidden': 'true',
			focusable: 'false',
		} );

		if ( ! n || ! width ) {
			canvas.appendChild( svg );
			return;
		}

		plotHeight = height - LABEL_AREA - TOP_PADDING;
		max = Math.max.apply( null, $.map( config.series, function ( p ) {
			return p.value;
		} ) );
		barWidth = round( ( width - GAP * ( n + 1 ) ) / n );

		$.each( config.series, function ( i, point ) {
			var barHeight = max > 0 ? round( ( point.value / max ) * plotHeight ) : 0;
			var x = round( GAP + i * ( barWidth + GAP ) );
			var rect = svgEl( 'rect', {
				class: 'acme-chart__bar' + ( hidden.indexOf( i ) > -1 ? ' is-hidden' : '' ),
				'data-index': i,
				x: x,
				y: round( TOP_PADDING + plotHeight - barHeight ),
				width: barWidth,
				height: barHeight,
				fill: colorFor( point, i ),
			} );
			var label = svgEl( 'text', {
				class: 'acme-chart__label',
				x: round( x + barWidth / 2 ),
				y: height - 6,
				'text-anchor': 'middle',
			} );
			label.textContent = point.label;
			svg.appendChild( rect );
			svg.appendChild( label );

			if ( config.showValues ) {
				var value = svgEl( 'text', {
					class: 'acme-chart__value',
					x: round( x + barWidth / 2 ),
					y: round( TOP_PADDING + plotHeight - barHeight - 4 ),
					'text-anchor': 'middle',
				} );
				value.textContent = String( point.value );
				svg.appendChild( value );
			}
		} );

		canvas.appendChild( svg );
	}

	/**
	 * Render a saved chart element (front end).
	 *
	 * @param {Element} chart The `.acme-chart` element.
	 */
	function render( chart ) {
		var config = readConfig( chart );
		var canvas = $( chart ).find( '.acme-chart__canvas' )[ 0 ];
		if ( ! config || ! canvas ) {
			return;
		}
		draw( canvas, config );
	}

	/**
	 * Show or hide a bar.
	 *
	 * @param {Element} chart   Element containing the chart canvas.
	 * @param {number}  index   Bar index.
	 * @param {boolean} visible Optional. Force a state.
	 * @return {boolean} Whether the bar is visible now.
	 */
	function toggle( chart, index, visible ) {
		var $bar = $( chart ).find( '.acme-chart__bar[data-index="' + index + '"]' );
		if ( typeof visible === 'undefined' ) {
			visible = $bar.hasClass( 'is-hidden' );
		}
		$bar.toggleClass( 'is-hidden', ! visible );
		return visible;
	}

	/**
	 * Build the items of a legend (`.acme-legend[data-chart]`) from the chart it points to.
	 *
	 * @param {Element} list   The legend `<ul>`.
	 * @param {Object}  config Optional chart configuration (defaults to the linked chart's).
	 */
	function renderLegend( list, config ) {
		var chartId = list.getAttribute( 'data-chart' );
		var chart = chartId ? document.getElementById( chartId ) : null;
		var i18n = settings().i18n || {};
		var $list = $( list ).empty();

		config = config ? normalize( config ) : chart && readConfig( chart );
		if ( ! config ) {
			return;
		}
		$.each( config.series, function ( i, point ) {
			var $button = $( '<button type="button" class="acme-legend__item" aria-pressed="true"></button>' )
				.attr( 'data-index', i )
				.attr( 'title', sprintf( i18n.toggle, point.label ) );
			$( '<span class="acme-legend__swatch"></span>' ).css( 'background-color', colorFor( point, i ) ).appendTo( $button );
			$( '<span class="acme-legend__label"></span>' ).text( point.label ).appendTo( $button );
			$( '<li></li>' ).append( $button ).appendTo( $list );
		} );
	}

	/**
	 * Click handler logic for a legend item (front end).
	 *
	 * @param {Element} button The `.acme-legend__item` button.
	 */
	function toggleFromLegend( button ) {
		var list = $( button ).closest( '.acme-legend' )[ 0 ];
		var chart = list ? document.getElementById( list.getAttribute( 'data-chart' ) ) : null;
		var visible;
		if ( ! chart ) {
			return;
		}
		visible = toggle( chart, $( button ).attr( 'data-index' ) );
		$( button ).attr( 'aria-pressed', visible ? 'true' : 'false' );
	}

	/**
	 * Render all charts and legends in a context.
	 *
	 * @param {Element|Document} context Where to look.
	 */
	function init( context ) {
		$( '.acme-chart', context || document ).each( function () {
			render( this );
		} );
		$( '.acme-legend[data-chart]', context || document ).each( function () {
			renderLegend( this );
		} );
	}

	window.AcmeCharts = {
		version: '1.6.0',
		normalize: normalize,
		readConfig: readConfig,
		colorFor: colorFor,
		draw: draw,
		render: render,
		toggle: toggle,
		renderLegend: renderLegend,
		toggleFromLegend: toggleFromLegend,
		init: init,
	};
} )( jQuery, window, document );

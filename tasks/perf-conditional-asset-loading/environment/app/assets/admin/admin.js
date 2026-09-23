/* Acme UI Kit – settings screen: colour picker and live preview of the accent colour and speed. */
( function ( $ ) {
	'use strict';
	$( function () {
		var $color = $( '#acme-ui-accent' );
		var $speed = $( '#acme-ui-speed' );
		var preview = document.querySelector( '.acme-ui-preview' );

		function apply() {
			if ( preview ) {
				preview.style.setProperty( '--acme-accent', $color.val() );
				preview.style.setProperty( '--acme-speed', $speed.val() + 'ms' );
			}
			$( '.acme-ui-speed-value' ).text( $speed.val() + ' ms' );
		}

		if ( $.fn.wpColorPicker ) {
			$color.wpColorPicker( { change: function () {
				window.setTimeout( apply, 0 );
			} } );
		}
		$speed.on( 'input change', apply );
		apply();
	} );
} )( window.jQuery );

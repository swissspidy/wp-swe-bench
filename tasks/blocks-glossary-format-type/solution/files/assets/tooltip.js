/**
 * Glossary tooltips: the definition is rendered in the markup and shown with
 * CSS on hover/focus. This script only lets keyboard users dismiss an open
 * tooltip with Escape (WCAG 1.4.13).
 */
( function () {
	'use strict';

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}
		var open = document.querySelectorAll( '.acme-glossary-term:hover, .acme-glossary-term:focus' );
		Array.prototype.forEach.call( open, function ( el ) {
			el.classList.add( 'is-dismissed' );
		} );
	} );

	function reset( event ) {
		var el = event.target.closest && event.target.closest( '.acme-glossary-term' );
		if ( el ) {
			el.classList.remove( 'is-dismissed' );
		}
	}
	document.addEventListener( 'mouseover', reset );
	document.addEventListener( 'focusin', reset );
} )();

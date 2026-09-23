/**
 * Glossary tooltips (legacy): shows the `title` of `.acme-glossary-term`
 * elements in a small bubble on hover.
 */
( function () {
	'use strict';

	var bubble = null;

	function show( el ) {
		var text = el.getAttribute( 'data-definition' ) || el.getAttribute( 'title' );
		if ( ! text ) {
			return;
		}
		// Move the title out of the way so the browser doesn't show its own tooltip.
		el.setAttribute( 'data-definition', text );
		el.removeAttribute( 'title' );

		bubble = document.createElement( 'span' );
		bubble.className = 'acme-glossary-bubble';
		bubble.textContent = text;
		document.body.appendChild( bubble );

		var rect = el.getBoundingClientRect();
		bubble.style.left = window.scrollX + rect.left + 'px';
		bubble.style.top = window.scrollY + rect.bottom + 6 + 'px';
	}

	function hide() {
		if ( bubble && bubble.parentNode ) {
			bubble.parentNode.removeChild( bubble );
		}
		bubble = null;
	}

	document.addEventListener( 'mouseover', function ( event ) {
		var el = event.target.closest && event.target.closest( '.acme-glossary-term' );
		if ( el ) {
			show( el );
		}
	} );
	document.addEventListener( 'mouseout', function ( event ) {
		if ( event.target.closest && event.target.closest( '.acme-glossary-term' ) ) {
			hide();
		}
	} );
} )();

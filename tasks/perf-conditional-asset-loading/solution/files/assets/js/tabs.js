/**
 * Acme UI Kit – Tabs component.
 */
( function ( window, document ) {
	'use strict';

	var AcmeUI = window.AcmeUI;

	AcmeUI.register( 'tabs', function ( el ) {
		var tabs = el.querySelectorAll( '.acme-tabs__tab' );
		var panels = el.querySelectorAll( '.acme-tabs__panel' );

		function select( index, focus ) {
			for ( var i = 0; i < tabs.length; i++ ) {
				var active = i === index;
				tabs[ i ].setAttribute( 'aria-selected', active ? 'true' : 'false' );
				tabs[ i ].setAttribute( 'tabindex', active ? '0' : '-1' );
				tabs[ i ].classList.toggle( 'is-active', active );
				if ( panels[ i ] ) {
					panels[ i ].hidden = ! active;
				}
			}
			if ( focus ) {
				tabs[ index ].focus();
			}
		}

		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				select( i, false );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'ArrowRight' ) {
					select( ( i + 1 ) % tabs.length, true );
				} else if ( event.key === 'ArrowLeft' ) {
					select( ( i - 1 + tabs.length ) % tabs.length, true );
				}
			} );
		} );
	} );
} )( window, document );

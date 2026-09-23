/**
 * Acme UI Kit – Accordion component.
 */
( function ( window, document ) {
	'use strict';

	var AcmeUI = window.AcmeUI;

	AcmeUI.register( 'accordion', function ( el, config ) {
		var single = el.getAttribute( 'data-single' ) === 'true';
		var toggles = el.querySelectorAll( '.acme-accordion__toggle' );

		Array.prototype.forEach.call( toggles, function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var panel = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
				var open = toggle.getAttribute( 'aria-expanded' ) !== 'true';
				if ( single && open ) {
					Array.prototype.forEach.call( toggles, function ( other ) {
						if ( other !== toggle ) {
							other.setAttribute( 'aria-expanded', 'false' );
							var otherPanel = document.getElementById( other.getAttribute( 'aria-controls' ) );
							if ( otherPanel ) {
								otherPanel.hidden = true;
							}
						}
					} );
				}
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				if ( panel ) {
					panel.hidden = ! open;
				}
			} );
		} );
		el.setAttribute( 'data-speed', String( config.animationSpeed || 200 ) );
	} );
} )( window, document );

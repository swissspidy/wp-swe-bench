/**
 * Acme UI Kit – components: tabs, accordion, carousel.
 * The carousel animates with the bundled AcmeMotion library.
 */
( function ( window, document ) {
	'use strict';

	var AcmeUI = window.AcmeUI;

	/* Tabs -------------------------------------------------------------- */
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

	/* Accordion --------------------------------------------------------- */
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

	/* Carousel ---------------------------------------------------------- */
	AcmeUI.register( 'carousel', function ( el, config ) {
		var track = el.querySelector( '.acme-carousel__track' );
		var slides = el.querySelectorAll( '.acme-carousel__slide' );
		var status = el.querySelector( '.acme-carousel__status' );
		var current = 0;
		var busy = false;

		function show( index ) {
			if ( busy || ! slides.length ) {
				return;
			}
			var next = ( index + slides.length ) % slides.length;
			var from = -current * 100;
			var to = -next * 100;
			busy = true;
			window.AcmeMotion.tween( {
				from: from,
				to: to,
				duration: config.animationSpeed || 200,
				easing: 'easeOutCubic',
				step: function ( value ) {
					track.style.transform = 'translateX(' + value + '%)';
				},
				done: function () {
					busy = false;
					current = next;
					el.setAttribute( 'data-current', String( current ) );
					if ( status ) {
						status.textContent = current + 1 + ' / ' + slides.length;
					}
				},
			} );
		}

		var prev = el.querySelector( '.acme-carousel__prev' );
		var nextButton = el.querySelector( '.acme-carousel__next' );
		if ( prev ) {
			prev.addEventListener( 'click', function () {
				show( current - 1 );
			} );
		}
		if ( nextButton ) {
			nextButton.addEventListener( 'click', function () {
				show( current + 1 );
			} );
		}
		if ( config.carouselAutoplay && slides.length > 1 ) {
			window.setInterval( function () {
				show( current + 1 );
			}, 6000 );
		}
	} );
} )( window, document );

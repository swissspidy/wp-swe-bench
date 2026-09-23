/**
 * Acme UI Kit – Carousel component. Animated with the bundled AcmeMotion library.
 */
( function ( window, document ) {
	'use strict';

	var AcmeUI = window.AcmeUI;

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

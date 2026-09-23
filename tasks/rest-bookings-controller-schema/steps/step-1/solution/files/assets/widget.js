/**
 * Availability widget ([acme_availability]).
 *
 * - Lists the booked date ranges of the room for the next 60 days (public).
 * - Logged-in customers can request a booking (created as "pending").
 *
 * No dependencies: this runs on every theme, including ones without jQuery.
 */
( function () {
	'use strict';

	const cfg = window.acmeBookingsWidget || {};

	function ymd( date ) {
		return date.toISOString().slice( 0, 10 );
	}

	function request( path, options ) {
		const opts = Object.assign( { method: 'GET' }, options || {} );
		opts.headers = Object.assign( {}, opts.headers || {} );
		opts.headers[ 'X-WP-Nonce' ] = cfg.nonce;
		opts.credentials = 'same-origin';
		return fetch( cfg.root + path, opts ).then( ( res ) =>
			res.json().then( ( body ) => ( { status: res.status, body } ) )
		);
	}

	function renderBooked( widget, booked ) {
		const list = widget.querySelector( '.acme-availability__booked' );
		list.innerHTML = '';
		if ( ! booked.length ) {
			const li = document.createElement( 'li' );
			li.className = 'is-empty';
			li.textContent = cfg.i18n.nothing;
			list.appendChild( li );
			return;
		}
		booked.forEach( ( range ) => {
			const li = document.createElement( 'li' );
			li.dataset.start = range.start;
			li.dataset.end = range.end;
			li.textContent = range.start + ' → ' + range.end + ' (' + cfg.i18n.booked + ')';
			list.appendChild( li );
		} );
	}

	function loadAvailability( widget ) {
		const from = new Date();
		const to = new Date( from.getTime() + 60 * 86400000 );
		const query = new URLSearchParams( { room: widget.dataset.room, from: ymd( from ), to: ymd( to ) } );
		return request( 'availability?' + query.toString() ).then( ( res ) => {
			if ( res.status === 200 ) {
				renderBooked( widget, res.body.booked || [] );
			}
		} );
	}

	function say( widget, text, isError ) {
		const el = widget.querySelector( '.acme-availability__message' );
		el.textContent = text;
		el.classList.toggle( 'is-error', !! isError );
	}

	function onSubmit( widget, event ) {
		event.preventDefault();
		const form = event.target;
		const body = JSON.stringify( {
			room: parseInt( widget.dataset.room, 10 ),
			start: form.start.value + 'T' + ( cfg.checkin || '14:00:00' ),
			end: form.end.value + 'T' + ( cfg.checkout || '11:00:00' ),
			guests: parseInt( form.guests.value, 10 ) || 1,
		} );
		request( 'bookings', { method: 'POST', body, headers: { 'Content-Type': 'application/json' } } )
			.then( ( res ) => {
				if ( res.status === 201 && res.body && res.body.id ) {
					say( widget, cfg.i18n.thanks.replace( '%d', res.body.id ) );
					form.reset();
					loadAvailability( widget );
				} else if ( res.status === 409 ) {
					say( widget, cfg.i18n.unavailable, true );
				} else if ( res.body && res.body.message ) {
					say( widget, res.body.message, true );
				} else {
					say( widget, cfg.i18n.error, true );
				}
			} )
			.catch( () => say( widget, cfg.i18n.error, true ) );
	}

	function init() {
		document.querySelectorAll( '.acme-availability' ).forEach( ( widget ) => {
			loadAvailability( widget );
			const form = widget.querySelector( '.acme-availability__form' );
			if ( form ) {
				form.addEventListener( 'submit', ( e ) => onSubmit( widget, e ) );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

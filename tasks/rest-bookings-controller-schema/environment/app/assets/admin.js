/**
 * Office screen: list, filter, create, confirm/cancel/delete bookings.
 *
 * Talks to the plugin's REST routes (acme-bookings/v1) with wp.apiFetch, which
 * adds the REST nonce for the logged-in user.
 */
( function () {
	'use strict';

	const apiFetch = window.wp.apiFetch;
	const { __, sprintf } = window.wp.i18n;
	const settings = window.acmeBookingsAdmin || {};

	const state = { page: 1, pages: 1, room: '', status: '' };

	const $ = ( sel, ctx ) => ( ctx || document ).querySelector( sel );

	function formatDate( mysql ) {
		// "2026-10-01 14:00:00" -> "2026-10-01 14:00"
		return String( mysql || '' ).slice( 0, 16 );
	}

	function message( text, isError ) {
		const el = $( '.acme-bookings-message' );
		el.textContent = text;
		el.classList.toggle( 'is-error', !! isError );
	}

	function cell( tr, className, text ) {
		const td = document.createElement( 'td' );
		td.className = className;
		td.textContent = text;
		tr.appendChild( td );
		return td;
	}

	function button( label, className ) {
		const b = document.createElement( 'button' );
		b.type = 'button';
		b.className = className;
		b.textContent = label;
		return b;
	}

	function renderRows( bookings ) {
		const tbody = $( '#acme-bookings-table tbody' );
		tbody.innerHTML = '';
		if ( ! bookings.length ) {
			const tr = document.createElement( 'tr' );
			tr.className = 'acme-bookings-empty';
			const td = cell( tr, '', __( 'No bookings found.', 'acme-bookings' ) );
			td.colSpan = 9;
			tbody.appendChild( tr );
			return;
		}
		bookings.forEach( ( b ) => {
			const tr = document.createElement( 'tr' );
			tr.dataset.id = b.id;
			tr.dataset.status = b.status;
			cell( tr, 'column-id', '#' + b.id );
			cell( tr, 'column-room', b.room_title );
			cell( tr, 'column-customer', b.customer_name );
			cell( tr, 'column-start', formatDate( b.start_date ) );
			cell( tr, 'column-end', formatDate( b.end_date ) );
			cell( tr, 'column-guests', String( b.guests ) );
			cell( tr, 'column-status', b.status_label );
			cell( tr, 'column-total', b.total_formatted );
			const actions = cell( tr, 'column-actions', '' );
			if ( b.status !== 'confirmed' && b.status !== 'cancelled' ) {
				actions.appendChild( button( __( 'Confirm', 'acme-bookings' ), 'button acme-confirm' ) );
			}
			if ( b.status !== 'cancelled' ) {
				actions.appendChild( button( __( 'Cancel', 'acme-bookings' ), 'button acme-cancel' ) );
			}
			actions.appendChild( button( __( 'Delete', 'acme-bookings' ), 'button-link-delete acme-delete' ) );
			tbody.appendChild( tr );
		} );
	}

	function load() {
		const params = new URLSearchParams( { paged: state.page, per_page: settings.perPage || 20 } );
		if ( state.room ) {
			params.set( 'room', state.room );
		}
		if ( state.status ) {
			params.set( 'status', state.status );
		}
		return apiFetch( { path: '/acme-bookings/v1/bookings?' + params.toString() } )
			.then( ( res ) => {
				state.pages = Math.max( 1, res.pages );
				renderRows( res.bookings );
				$( '#acme-bookings-page-info' ).textContent = sprintf(
					/* translators: 1: current page, 2: total pages */
					__( 'Page %1$d of %2$d', 'acme-bookings' ),
					state.page,
					state.pages
				);
				$( '#acme-bookings-prev' ).disabled = state.page <= 1;
				$( '#acme-bookings-next' ).disabled = state.page >= state.pages;
			} )
			.catch( ( err ) => message( ( err && err.message ) || __( 'Could not load bookings.', 'acme-bookings' ), true ) );
	}

	function update( id, data ) {
		return apiFetch( { path: '/acme-bookings/v1/bookings/' + id, method: 'POST', data } ).then( load );
	}

	function onTableClick( event ) {
		const tr = event.target.closest( 'tr[data-id]' );
		if ( ! tr ) {
			return;
		}
		const id = tr.dataset.id;
		if ( event.target.classList.contains( 'acme-confirm' ) ) {
			update( id, { status: 'confirmed' } );
		} else if ( event.target.classList.contains( 'acme-cancel' ) ) {
			update( id, { status: 'cancelled' } );
		} else if ( event.target.classList.contains( 'acme-delete' ) ) {
			// eslint-disable-next-line no-alert
			if ( window.confirm( __( 'Delete this booking permanently?', 'acme-bookings' ) ) ) {
				apiFetch( { path: '/acme-bookings/v1/bookings/' + id, method: 'DELETE' } ).then( load );
			}
		}
	}

	function onCreate( event ) {
		event.preventDefault();
		const form = event.target;
		const data = {
			room_id: form.room.value,
			customer_id: form.customer.value,
			start_date: form.start.value,
			end_date: form.end.value,
			guests: form.guests.value,
			status: form.status.value,
			notes: form.notes.value,
		};
		apiFetch( { path: '/acme-bookings/v1/bookings', method: 'POST', data } )
			.then( ( res ) => {
				if ( ! res.success ) {
					message( res.message, true );
					return;
				}
				/* translators: %d: booking ID */
				message( sprintf( __( 'Booking #%d added.', 'acme-bookings' ), res.booking.id ) );
				form.reset();
				state.page = 1;
				load();
			} )
			.catch( ( err ) => message( ( err && err.message ) || __( 'Could not save the booking.', 'acme-bookings' ), true ) );
	}

	function init() {
		const customers = $( '#acme-new-customer' );
		( settings.customers || [] ).forEach( ( c ) => {
			const o = document.createElement( 'option' );
			o.value = c.id;
			o.textContent = c.name;
			customers.appendChild( o );
		} );

		$( '#acme-bookings-filter-room' ).addEventListener( 'change', ( e ) => {
			state.room = e.target.value;
			state.page = 1;
			load();
		} );
		$( '#acme-bookings-filter-status' ).addEventListener( 'change', ( e ) => {
			state.status = e.target.value;
			state.page = 1;
			load();
		} );
		$( '#acme-bookings-prev' ).addEventListener( 'click', () => {
			state.page = Math.max( 1, state.page - 1 );
			load();
		} );
		$( '#acme-bookings-next' ).addEventListener( 'click', () => {
			state.page = Math.min( state.pages, state.page + 1 );
			load();
		} );
		$( '#acme-bookings-table' ).addEventListener( 'click', onTableClick );
		$( '#acme-bookings-new' ).addEventListener( 'submit', onCreate );
		load();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

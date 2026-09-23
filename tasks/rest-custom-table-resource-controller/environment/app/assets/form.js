/**
 * "Talk to sales" form: posts to /acme-leads/v1/leads.
 */
( function () {
	'use strict';

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target.closest( '.acme-lead-form' );
		if ( ! form ) {
			return;
		}
		event.preventDefault();

		var status = form.querySelector( '.acme-lead-form__status' );
		var data = {};
		new FormData( form ).forEach( function ( value, key ) {
			data[ key ] = value;
		} );

		form.querySelector( 'button' ).disabled = true;
		window
			.fetch( window.acmeLeadForm.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( data ),
			} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				form.reset();
				status.textContent = window.acmeLeadForm.thanks;
			} )
			.catch( function () {
				status.textContent = window.acmeLeadForm.error;
			} )
			.finally( function () {
				form.querySelector( 'button' ).disabled = false;
			} );
	} );
} )();

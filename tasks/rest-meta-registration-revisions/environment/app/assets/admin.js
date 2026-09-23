/**
 * Product specifications box: "Add certification" clones the last row.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.acme-specs-box__add-cert' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();

		var tbody = document.querySelector( '.acme-specs-box__certs tbody' );
		var rows = tbody.querySelectorAll( '.acme-specs-box__cert' );
		var last = rows[ rows.length - 1 ];
		var clone = last.cloneNode( true );
		var index = rows.length;

		clone.querySelectorAll( 'select, input' ).forEach( function ( field ) {
			field.name = field.name.replace( /\[certs\]\[\d+\]/, '[certs][' + index + ']' );
			field.value = '';
		} );
		tbody.appendChild( clone );
	} );
} )();

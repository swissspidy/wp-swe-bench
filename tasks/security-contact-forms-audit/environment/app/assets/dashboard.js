/**
 * "Latest form submissions" dashboard widget.
 *
 * Loads the latest submissions over admin-ajax and shows a submission's fields when its row is
 * clicked. Everything is rendered with textContent.
 */
( function () {
	'use strict';

	var config = window.acmeFormsWidget;
	if ( ! config ) {
		return;
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	function request( params ) {
		var query = new URLSearchParams( params );
		query.set( 'action', config.action );
		query.set( 'nonce', config.nonce );
		return fetch( config.ajaxUrl + '?' + query.toString(), {
			credentials: 'same-origin',
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok || ! body || ! body.success ) {
					throw new Error( 'request failed' );
				}
				return body.data;
			} );
		} );
	}

	function showMessage( container, message ) {
		container.textContent = '';
		container.appendChild( el( 'p', '', message ) );
	}

	function showList( container ) {
		showMessage( container, config.i18n.loading );
		request( { limit: container.getAttribute( 'data-limit' ) || 5 } )
			.then( function ( data ) {
				container.textContent = '';
				if ( ! data.submissions.length ) {
					showMessage( container, config.i18n.empty );
					return;
				}
				var list = el( 'ul', 'acme-forms-widget__list' );
				data.submissions.forEach( function ( item ) {
					var li = el( 'li', 'acme-forms-widget__item is-' + item.status );
					var button = el( 'button', 'button-link' );
					button.type = 'button';
					button.appendChild( el( 'strong', '', item.form ) );
					button.appendChild( document.createTextNode( ' — ' + ( item.email || item.summary ) ) );
					button.addEventListener( 'click', function () {
						showDetail( container, item.id );
					} );
					li.appendChild( button );
					li.appendChild( el( 'span', 'acme-forms-widget__date', new Date( item.created ).toLocaleString() ) );
					list.appendChild( li );
				} );
				container.appendChild( list );
			} )
			.catch( function () {
				showMessage( container, config.i18n.error );
			} );
	}

	function showDetail( container, id ) {
		showMessage( container, config.i18n.loading );
		request( { submission: id } )
			.then( function ( data ) {
				var item = data.submission;
				container.textContent = '';
				var back = el( 'button', 'button-link', '← ' + config.i18n.back );
				back.type = 'button';
				back.addEventListener( 'click', function () {
					showList( container );
				} );
				container.appendChild( back );
				var table = el( 'table', 'widefat striped' );
				item.fields.forEach( function ( field ) {
					var tr = el( 'tr' );
					tr.appendChild( el( 'th', '', field.label ) );
					tr.appendChild( el( 'td', '', field.value ) );
					table.appendChild( tr );
				} );
				container.appendChild( table );
				var link = el( 'a', 'button', '#' + item.id );
				link.href = config.viewUrl + item.id;
				container.appendChild( el( 'p' ) ).appendChild( link );
			} )
			.catch( function () {
				showMessage( container, config.i18n.error );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.acme-forms-widget' ).forEach( showList );
	} );
} )();

/* global jQuery, acmeInventory */
/**
 * Inventory screen: list, search, inline stock edit, bulk adjust, delete.
 *
 * Uses the REST API (acme-inventory/v1) through wp.apiFetch, which adds the
 * REST nonce of the logged-in user.
 */
( function ( $, apiFetch ) {
	'use strict';

	var cfg = window.acmeInventory || {};
	var state = { page: 1, pages: 1, search: '', lowStock: false };
	var searchTimer = null;
	var requestId = 0;

	function notice( message, type ) {
		var $n = $( '#acme-inv-notice' );
		$n.removeClass( 'notice-success notice-error' ).addClass( 'error' === type ? 'notice-error' : 'notice-success' );
		$n.find( 'p' ).text( message );
		$n.prop( 'hidden', false );
	}

	function errorMessage( err ) {
		if ( err && err.data && err.data.params ) {
			var details = Object.keys( err.data.params ).map( function ( k ) {
				return err.data.params[ k ];
			} ).filter( function ( v ) {
				return 'string' === typeof v;
			} );
			if ( details.length ) {
				return details.join( ' ' );
			}
		}
		return ( err && err.message ) || cfg.i18n.error;
	}

	function apiError( err ) {
		if ( err && 'function' === typeof err.json ) {
			return err.json().then( function ( body ) {
				notice( errorMessage( body ), 'error' );
			}, function () {
				notice( cfg.i18n.error, 'error' );
			} );
		}
		notice( errorMessage( err ), 'error' );
	}

	function formatUpdated( value ) {
		if ( ! value ) {
			return '—';
		}
		return value.substr( 0, 16 ).replace( 'T', ' ' );
	}

	function renderRow( item ) {
		var $tr = $( '<tr/>' ).attr( 'data-id', item.id ).toggleClass( 'is-low-stock', !! item.low_stock );
		$tr.data( 'name', item.name );
		$tr.append(
			$( '<th scope="row" class="check-column"/>' ).append(
				$( '<input type="checkbox" class="acme-inv-select"/>' ).val( item.id )
			)
		);
		$tr.append( $( '<td class="column-sku"/>' ).text( item.sku ) );
		$tr.append( $( '<td class="column-name"/>' ).text( item.name ) );
		$tr.append(
			$( '<td class="column-stock"/>' ).append(
				$( '<button type="button" class="button-link acme-inv-stock"/>' ).text( item.stock )
			)
		);
		$tr.append( $( '<td class="column-location"/>' ).text( item.location ) );
		$tr.append( $( '<td class="column-updated"/>' ).text( formatUpdated( item.updated_at ) ) );
		$tr.append(
			$( '<td class="column-actions"/>' ).append(
				$( '<button type="button" class="button-link button-link-delete acme-inv-delete"/>' ).text( cfg.i18n.delete )
			)
		);
		return $tr;
	}

	function render( items ) {
		var $body = $( '#acme-inv-table tbody' ).empty();
		if ( ! items.length ) {
			$body.append( $( '<tr class="acme-inv-empty"/>' ).append( $( '<td colspan="7"/>' ).text( cfg.i18n.noItems ) ) );
		}
		$.each( items, function ( i, item ) {
			$body.append( renderRow( item ) );
		} );
		$( '#acme-inv-select-all' ).prop( 'checked', false );
		$( '#acme-inv-page-info' ).text( cfg.i18n.pageOf.replace( '%1$d', state.page ).replace( '%2$d', state.pages ) );
		$( '#acme-inv-prev' ).prop( 'disabled', state.page <= 1 );
		$( '#acme-inv-next' ).prop( 'disabled', state.page >= state.pages );
	}

	function load() {
		var query = new URLSearchParams( { page: state.page, per_page: cfg.perPage || 20 } );
		var mine = ++requestId;
		if ( state.search ) {
			query.set( 'search', state.search );
		}
		if ( state.lowStock ) {
			query.set( 'low_stock', 'true' );
		}
		return apiFetch( { path: cfg.path + '?' + query.toString(), parse: false } )
			.then( function ( res ) {
				return res.json().then( function ( items ) {
					if ( mine !== requestId ) {
						return; // A newer request is on its way.
					}
					state.pages = Math.max( 1, parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
					if ( state.page > state.pages ) {
						state.page = state.pages;
						load();
						return;
					}
					render( items );
				} );
			} )
			.catch( apiError );
	}

	function selectedIds() {
		return $( '.acme-inv-select:checked' ).map( function () {
			return parseInt( $( this ).val(), 10 );
		} ).get();
	}

	function startEdit( $button ) {
		var $cell = $button.closest( 'td' );
		var id = $button.closest( 'tr' ).data( 'id' );
		var current = $button.text();
		var $input = $( '<input type="number" class="acme-inv-stock-input small-text" min="0" step="1"/>' ).val( current );
		$cell.empty().append( $input );
		$input.trigger( 'focus' ).trigger( 'select' );

		function restore( value ) {
			$cell.empty().append( $( '<button type="button" class="button-link acme-inv-stock"/>' ).text( value ) );
		}

		$input.on( 'keydown', function ( e ) {
			var raw;
			if ( 'Escape' === e.key ) {
				restore( current );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				raw = $input.val();
				apiFetch( {
					path: cfg.path + '/' + id,
					method: 'PATCH',
					data: { stock: /^-?\d+$/.test( raw ) ? parseInt( raw, 10 ) : raw },
				} )
					.then( function ( item ) {
						restore( item.stock );
						$cell.closest( 'tr' ).toggleClass( 'is-low-stock', !! item.low_stock );
						notice( cfg.i18n.saved );
					} )
					.catch( function ( err ) {
						restore( current );
						apiError( err );
					} );
			}
		} );
	}

	$( function () {
		$( '#acme-inv-search' ).on( 'input', function () {
			var value = $( this ).val();
			clearTimeout( searchTimer );
			searchTimer = setTimeout( function () {
				state.search = value;
				state.page = 1;
				load();
			}, 300 );
		} );

		$( '#acme-inv-low-stock' ).on( 'change', function () {
			state.lowStock = $( this ).is( ':checked' );
			state.page = 1;
			load();
		} );

		$( '#acme-inv-prev' ).on( 'click', function () {
			state.page = Math.max( 1, state.page - 1 );
			load();
		} );
		$( '#acme-inv-next' ).on( 'click', function () {
			state.page = Math.min( state.pages, state.page + 1 );
			load();
		} );

		$( '#acme-inv-select-all' ).on( 'change', function () {
			$( '.acme-inv-select' ).prop( 'checked', $( this ).is( ':checked' ) );
		} );

		$( '#acme-inv-table' ).on( 'click', '.acme-inv-stock', function () {
			startEdit( $( this ) );
		} );

		$( '#acme-inv-table' ).on( 'click', '.acme-inv-delete', function () {
			var $tr = $( this ).closest( 'tr' );
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( cfg.i18n.confirmDelete.replace( '%s', $tr.data( 'name' ) || '' ) ) ) {
				return;
			}
			apiFetch( { path: cfg.path + '/' + $tr.data( 'id' ), method: 'DELETE' } )
				.then( function () {
					notice( cfg.i18n.deleted );
					load();
				} )
				.catch( apiError );
		} );

		$( '#acme-inv-bulk-apply' ).on( 'click', function () {
			var ids = selectedIds();
			var raw = $( '#acme-inv-bulk-delta' ).val();
			if ( ! ids.length ) {
				notice( cfg.i18n.selectItems, 'error' );
				return;
			}
			apiFetch( {
				path: cfg.path + '/bulk-adjust',
				method: 'POST',
				data: {
					ids: ids,
					delta: /^-?\d+$/.test( raw ) ? parseInt( raw, 10 ) : raw,
					reason: $( '#acme-inv-bulk-reason' ).val(),
				},
			} )
				.then( function ( res ) {
					notice( cfg.i18n.adjusted.replace( '%d', res.items.length ) );
					load();
				} )
				.catch( apiError );
		} );

		load();
	} );
}( jQuery, window.wp.apiFetch ) );

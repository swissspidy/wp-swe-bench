/* global jQuery, acmeInventory */
/**
 * Inventory screen: list, search, inline stock edit, bulk adjust, delete.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.acmeInventory || {};
	var state = { page: 1, pages: 1, search: '', lowStock: false };
	var searchTimer = null;

	function notice( message, type ) {
		var $n = $( '#acme-inv-notice' );
		$n.removeClass( 'notice-success notice-error' ).addClass( 'error' === type ? 'notice-error' : 'notice-success' );
		$n.find( 'p' ).text( message );
		$n.prop( 'hidden', false );
	}

	function ajaxError( xhr ) {
		var msg = cfg.i18n.error;
		if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			msg = xhr.responseJSON.data.message;
		}
		notice( msg, 'error' );
	}

	function formatUpdated( value ) {
		if ( ! value || '0000-00-00 00:00:00' === value ) {
			return '—';
		}
		return value.substr( 0, 16 );
	}

	function renderRow( item ) {
		var $tr = $( '<tr/>' ).attr( 'data-id', item.id );
		if ( parseInt( item.stock, 10 ) <= parseInt( item.low_stock_threshold, 10 ) ) {
			$tr.addClass( 'is-low-stock' );
		}
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

	function render( res ) {
		var $body = $( '#acme-inv-table tbody' ).empty();
		state.pages = Math.max( 1, res.pages );
		if ( ! res.items.length ) {
			$body.append( $( '<tr class="acme-inv-empty"/>' ).append( $( '<td colspan="7"/>' ).text( cfg.i18n.noItems ) ) );
		}
		$.each( res.items, function ( i, item ) {
			$body.append( renderRow( item ) );
		} );
		$( '#acme-inv-select-all' ).prop( 'checked', false );
		$( '#acme-inv-page-info' ).text( cfg.i18n.pageOf.replace( '%1$d', state.page ).replace( '%2$d', state.pages ) );
		$( '#acme-inv-prev' ).prop( 'disabled', state.page <= 1 );
		$( '#acme-inv-next' ).prop( 'disabled', state.page >= state.pages );
	}

	function load() {
		var data = { nonce: cfg.nonce, page: state.page };
		if ( state.lowStock ) {
			data.low_stock = 1;
		}
		if ( state.search ) {
			data.action = 'acme_inv_search';
			data.q = state.search;
		} else {
			data.action = 'acme_inv_list';
		}
		return $.getJSON( cfg.ajaxUrl, data ).done( render ).fail( ajaxError );
	}

	function selectedIds() {
		return $( '.acme-inv-select:checked' ).map( function () {
			return $( this ).val();
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
			if ( 'Escape' === e.key ) {
				restore( current );
			} else if ( 'Enter' === e.key ) {
				e.preventDefault();
				$.post( cfg.ajaxUrl, { action: 'acme_inv_update_stock', nonce: cfg.nonce, id: id, stock: $input.val() }, null, 'json' )
					.done( function ( res ) {
						restore( res.item.stock );
						$cell.closest( 'tr' ).toggleClass( 'is-low-stock', parseInt( res.item.stock, 10 ) <= parseInt( res.item.low_stock_threshold, 10 ) );
						notice( cfg.i18n.saved );
					} )
					.fail( function ( xhr ) {
						restore( current );
						ajaxError( xhr );
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
			var id = $( this ).closest( 'tr' ).data( 'id' );
			$.post( cfg.ajaxUrl, { action: 'acme_inv_delete', nonce: cfg.nonce, id: id }, null, 'json' )
				.done( function () {
					notice( cfg.i18n.deleted );
					load();
				} )
				.fail( ajaxError );
		} );

		$( '#acme-inv-bulk-apply' ).on( 'click', function () {
			var ids = selectedIds();
			if ( ! ids.length ) {
				notice( cfg.i18n.selectItems, 'error' );
				return;
			}
			$.post(
				cfg.ajaxUrl,
				{
					action: 'acme_inv_bulk_adjust',
					nonce: cfg.nonce,
					ids: ids,
					delta: $( '#acme-inv-bulk-delta' ).val(),
					reason: $( '#acme-inv-bulk-reason' ).val(),
				},
				null,
				'json'
			)
				.done( function ( res ) {
					notice( cfg.i18n.adjusted.replace( '%d', res.updated ) );
					load();
				} )
				.fail( ajaxError );
		} );

		load();
	} );
}( jQuery ) );

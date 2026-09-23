/**
 * Quick Edit: fill the price / stock fields from the row.
 */
( function ( $ ) {
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	const originalEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( id ) {
		originalEdit.apply( this, arguments );

		const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );
		if ( ! postId ) {
			return;
		}

		const $data = $( '#post-' + postId ).find( '.acme-pf-inline' );
		const $editRow = $( '#edit-' + postId );

		$editRow.find( '.acme-pf-price' ).val( $data.data( 'price' ) );
		$editRow
			.find( '.acme-pf-in-stock' )
			.prop( 'checked', String( $data.data( 'in-stock' ) ) === '1' );
	};
} )( jQuery );

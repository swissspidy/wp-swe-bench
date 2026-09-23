/**
 * Editor workarounds (see includes/class-editor-workarounds.php).
 *
 * Press releases: insert the standard structure into new, empty press releases.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! config || ! config.template ) {
		return;
	}

	var inserted = false;

	function build( spec ) {
		return wp.blocks.createBlock( spec[ 0 ], spec[ 1 ] || {}, ( spec[ 2 ] || [] ).map( build ) );
	}

	function maybeInsert() {
		if ( inserted ) {
			return;
		}
		var editor = wp.data.select( 'core/editor' );
		var blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		if ( ! editor.getCurrentPostId() ) {
			return;
		}
		inserted = true;
		var isEmpty =
			blocks.length === 0 ||
			( blocks.length === 1 && wp.blocks.isUnmodifiedDefaultBlock( blocks[ 0 ] ) );
		if ( editor.isEditedPostNew() && isEmpty ) {
			wp.data.dispatch( 'core/block-editor' ).resetBlocks( config.template.map( build ) );
		}
	}

	wp.domReady( function () {
		var unsubscribe = wp.data.subscribe( function () {
			maybeInsert();
			if ( inserted ) {
				unsubscribe();
			}
		} );
	} );
} )( window.wp, window.acmeNewsroomWorkarounds );

/* Acme Related – counts one view per page load of a single post. */
( function () {
	var cfg = window.acmeRelatedViews;
	if ( ! cfg || ! cfg.endpoint || ! window.fetch ) {
		return;
	}
	try {
		if ( window.sessionStorage.getItem( 'acme-related-viewed:' + cfg.endpoint ) ) {
			return;
		}
		window.sessionStorage.setItem( 'acme-related-viewed:' + cfg.endpoint, '1' );
	} catch ( e ) {}
	window.fetch( cfg.endpoint, { method: 'POST', credentials: 'omit', keepalive: true } );
} )();

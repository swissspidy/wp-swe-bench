/**
 * Acme UI Kit – core runtime.
 *
 * window.AcmeUI.register( name, init ) registers a component; every element with
 * data-acme-component="<name>" is initialised once, on DOMContentLoaded (or right away
 * when the document is already parsed). Other plugins and themes register their own
 * components the same way.
 */
( function ( window, document ) {
	'use strict';

	if ( window.AcmeUI && window.AcmeUI.register ) {
		return;
	}

	var registry = {};
	var config = window.AcmeUIConfig || {};

	function initElement( el ) {
		var name = el.getAttribute( 'data-acme-component' );
		if ( ! name || ! registry[ name ] || el.__acmeUiInitialised ) {
			return;
		}
		el.__acmeUiInitialised = true;
		try {
			registry[ name ]( el, config );
			el.classList.add( 'is-acme-ready' );
		} catch ( e ) {
			if ( window.console ) {
				window.console.error( '[AcmeUI] ' + name + ':', e );
			}
		}
	}

	function init( root ) {
		var scope = root || document;
		var els = scope.querySelectorAll( '[data-acme-component]' );
		for ( var i = 0; i < els.length; i++ ) {
			initElement( els[ i ] );
		}
	}

	var ready = document.readyState !== 'loading';

	window.AcmeUI = {
		version: config.version || '0',
		config: config,
		components: registry,
		register: function ( name, fn ) {
			registry[ name ] = fn;
			if ( ready ) {
				init();
			}
		},
		init: init,
		uid: ( function () {
			var n = 0;
			return function ( prefix ) {
				n += 1;
				return ( prefix || 'acme' ) + '-' + n;
			};
		} )(),
	};

	if ( ready ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', function () {
			ready = true;
			init();
		} );
	}
} )( window, document );

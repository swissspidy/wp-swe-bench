/**
 * Front end of the FAQ accordion.
 *
 * The server renders the initial state (open/closed questions); this module
 * only reacts to clicks, keyboard navigation and deep links.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const NAV_KEYS = [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ];

function setOpen( context, id, open ) {
	const others = context.open.filter( ( x ) => x !== id );
	if ( ! open ) {
		context.open = others;
	} else {
		context.open = context.allowMultiple ? [ ...others, id ] : [ id ];
	}
}

const { state } = store( 'acme/faq', {
	state: {
		get isOpen() {
			const context = getContext();
			return context.open.includes( context.id );
		},
	},
	actions: {
		toggle() {
			const context = getContext();
			setOpen( context, context.id, ! state.isOpen );
		},
		navigate( event ) {
			if ( ! NAV_KEYS.includes( event.key ) ) {
				return;
			}
			const { ref } = getElement();
			const root = ref.closest( '.wp-block-acme-faq' );
			const toggles = Array.from(
				root.querySelectorAll( '.acme-faq__toggle' )
			).filter(
				( button ) => button.closest( '.wp-block-acme-faq' ) === root
			);
			const index = toggles.indexOf( ref );
			let next = index;
			if ( event.key === 'ArrowDown' ) {
				next = ( index + 1 ) % toggles.length;
			} else if ( event.key === 'ArrowUp' ) {
				next = ( index - 1 + toggles.length ) % toggles.length;
			} else if ( event.key === 'Home' ) {
				next = 0;
			} else if ( event.key === 'End' ) {
				next = toggles.length - 1;
			}
			event.preventDefault();
			toggles[ next ].focus();
		},
	},
	callbacks: {
		openFromHash() {
			const context = getContext();
			let id = '';
			try {
				id = decodeURIComponent( window.location.hash.slice( 1 ) );
			} catch ( e ) {
				return;
			}
			if ( ! id || ! context.ids.includes( id ) ) {
				return;
			}
			setOpen( context, id, true );
			const target = document.getElementById( id );
			if ( target ) {
				target.scrollIntoView();
			}
		},
	},
} );

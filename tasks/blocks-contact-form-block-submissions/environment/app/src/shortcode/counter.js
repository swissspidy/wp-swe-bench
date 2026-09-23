/**
 * Character counter under the message field of the [acme_contact] form.
 */
import { __, sprintf } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';

domReady( () => {
	document.querySelectorAll( 'textarea[data-acme-counter]' ).forEach( ( textarea ) => {
		const max = Number( textarea.getAttribute( 'maxlength' ) ) || 5000;
		const counter = document.createElement( 'span' );
		counter.className = 'acme-contact__counter';
		counter.setAttribute( 'aria-live', 'polite' );
		const update = () => {
			counter.textContent = sprintf(
				/* translators: 1: characters used, 2: maximum */
				__( '%1$d / %2$d characters', 'acme-contact' ),
				textarea.value.length,
				max
			);
		};
		textarea.addEventListener( 'input', update );
		textarea.after( counter );
		update();
	} );
} );

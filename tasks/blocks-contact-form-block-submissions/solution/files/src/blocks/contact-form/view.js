/**
 * Contact form block: submit without reloading the page. Without JavaScript the form posts
 * normally and the server redirects back.
 */

/**
 * Field name from an input name ("acme_fields[email]" → "email").
 *
 * @param {string} name Input name.
 * @return {string|null} Field name.
 */
function fieldName( name ) {
	const match = /^acme_fields\[([^\]]+)\]$/.exec( name || '' );
	return match ? match[ 1 ] : null;
}

function clearMessages( form ) {
	const status = form.querySelector( '.acme-contact-form__status' );
	const errors = form.querySelector( '.acme-contact-form__errors' );
	status.textContent = '';
	errors.textContent = '';
	errors.hidden = true;
	form.querySelectorAll( '.acme-contact-field' ).forEach( ( wrapper ) => {
		wrapper.classList.remove( 'has-error' );
		const control = wrapper.querySelector( 'input, select, textarea' );
		const error = wrapper.querySelector( '.acme-contact-field__error' );
		if ( control ) {
			control.removeAttribute( 'aria-invalid' );
			control.removeAttribute( 'aria-describedby' );
		}
		if ( error ) {
			error.textContent = '';
			error.hidden = true;
		}
	} );
}

function showSuccess( form, message ) {
	const status = form.querySelector( '.acme-contact-form__status' );
	const p = document.createElement( 'p' );
	p.textContent = message;
	status.appendChild( p );
}

function showErrors( form, message, fieldErrors ) {
	const errors = form.querySelector( '.acme-contact-form__errors' );
	const intro = document.createElement( 'p' );
	intro.textContent = message;
	errors.appendChild( intro );
	const list = document.createElement( 'ul' );
	let first = null;
	form.querySelectorAll( '.acme-contact-field' ).forEach( ( wrapper ) => {
		const control = wrapper.querySelector( 'input, select, textarea' );
		const name = control ? fieldName( control.name ) : null;
		if ( ! name || ! fieldErrors[ name ] ) {
			return;
		}
		const error = wrapper.querySelector( '.acme-contact-field__error' );
		wrapper.classList.add( 'has-error' );
		control.setAttribute( 'aria-invalid', 'true' );
		if ( error ) {
			error.textContent = fieldErrors[ name ];
			error.hidden = false;
			control.setAttribute( 'aria-describedby', error.id );
		}
		const label = wrapper.querySelector( 'label' );
		const item = document.createElement( 'li' );
		const link = document.createElement( 'a' );
		link.href = '#' + control.id;
		link.textContent = ( label ? label.textContent.replace( /\s*\*\s*$/, '' ) + ': ' : '' ) + fieldErrors[ name ];
		item.appendChild( link );
		list.appendChild( item );
		first = first || control;
	} );
	if ( list.childNodes.length ) {
		errors.appendChild( list );
	}
	errors.hidden = false;
	if ( first ) {
		first.focus();
	}
}

function init( form ) {
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const button = form.querySelector( '[type="submit"]' );
		const fields = {};
		form.querySelectorAll( '[name^="acme_fields["]' ).forEach( ( control ) => {
			const name = fieldName( control.name );
			if ( name ) {
				fields[ name ] = 'checkbox' === control.type ? ( control.checked ? '1' : '' ) : control.value;
			}
		} );
		const honeypot = form.querySelector( '[name="acme_website"]' );

		button.disabled = true;
		let response;
		let data;
		try {
			response = await window.fetch( form.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify( {
					post_id: Number( form.dataset.postId ),
					form_id: form.dataset.formId,
					fields,
					acme_website: honeypot ? honeypot.value : '',
				} ),
			} );
			data = await response.json();
		} catch ( e ) {
			// Network problem or unexpected response: fall back to a normal submission.
			form.submit();
			return;
		} finally {
			button.disabled = false;
		}

		clearMessages( form );
		if ( response.ok ) {
			showSuccess( form, data.message );
			form.reset();
		} else {
			showErrors( form, data.message || '', ( data.data && data.data.errors ) || {} );
		}
	} );
}

function run() {
	document.querySelectorAll( 'form[data-acme-contact-form]' ).forEach( init );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', run );
} else {
	run();
}

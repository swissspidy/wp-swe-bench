/**
 * Mobile menu toggle and keyboard-accessible submenus.
 */
( function () {
	const nav = document.getElementById( 'site-navigation' );
	if ( ! nav ) {
		return;
	}
	const toggle = nav.querySelector( '.menu-toggle' );
	if ( toggle ) {
		toggle.addEventListener( 'click', () => {
			const expanded = nav.classList.toggle( 'toggled' );
			toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}
	nav.querySelectorAll( '.submenu-toggle' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			const open = button.getAttribute( 'aria-expanded' ) === 'true';
			button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			button.parentElement.classList.toggle( 'is-open', ! open );
		} );
	} );
} )();

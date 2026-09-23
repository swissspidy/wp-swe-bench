/**
 * Classic meta box: add/remove ingredient rows.
 */
import './metabox.scss';

function renumber( tbody ) {
	tbody.querySelectorAll( 'tr.acme-recipe-ingredient' ).forEach( ( row, index ) => {
		row.querySelectorAll( 'input' ).forEach( ( input ) => {
			input.name = input.name.replace( /\[ingredients\]\[\d+\]/, `[ingredients][${ index }]` );
		} );
	} );
}

function init() {
	const table = document.querySelector( '.acme-recipe-ingredients' );
	const add = document.querySelector( '.acme-recipe-ingredient__add' );
	if ( ! table || ! add ) {
		return;
	}
	const tbody = table.querySelector( 'tbody' );

	add.addEventListener( 'click', () => {
		const template = tbody.querySelector( 'tr.acme-recipe-ingredient' );
		const row = template.cloneNode( true );
		row.querySelectorAll( 'input' ).forEach( ( input ) => {
			input.value = '';
		} );
		tbody.appendChild( row );
		renumber( tbody );
		row.querySelector( 'input' ).focus();
	} );

	tbody.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.acme-recipe-ingredient__remove' );
		if ( ! button ) {
			return;
		}
		const rows = tbody.querySelectorAll( 'tr.acme-recipe-ingredient' );
		const row = button.closest( 'tr' );
		if ( rows.length === 1 ) {
			row.querySelectorAll( 'input' ).forEach( ( input ) => {
				input.value = '';
			} );
		} else {
			row.remove();
		}
		renumber( tbody );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}

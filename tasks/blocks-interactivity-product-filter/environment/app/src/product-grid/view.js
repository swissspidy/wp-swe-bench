/**
 * Front end: client-side filtering of the product grid.
 *
 * Reads the product data from the JSON blob rendered with the grid, applies
 * the initially selected category and updates the list when a filter button
 * is clicked or the search text changes.
 */
import $ from 'jquery';

const strings = window.acmeCatalogGrid || {
	countPlural: 'Showing %1$s of %2$s products',
	countSingular: 'Showing %1$s of %2$s product',
};

/**
 * Does a product match the search text? Every word must appear in the
 * product name or SKU (case-insensitive).
 *
 * @param {Object} product Product data.
 * @param {string} query   Search text.
 * @return {boolean} Match.
 */
function matchesQuery( product, query ) {
	const words = query.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
	const haystack = `${ product.title } ${ product.sku }`.toLowerCase();
	return words.every( ( word ) => haystack.includes( word ) );
}

function matchesCategory( product, category ) {
	return ! category || product.categories.includes( category );
}

function countText( shown, total ) {
	const template = total === 1 ? strings.countSingular : strings.countPlural;
	return template.replace( '%1$s', shown ).replace( '%2$s', total );
}

function initGrid( element ) {
	const $grid = $( element );
	let config;
	try {
		config = JSON.parse( $grid.find( '.acme-grid__data' ).text() );
	} catch ( e ) {
		return;
	}
	const byId = {};
	config.products.forEach( ( product ) => {
		byId[ product.id ] = product;
	} );

	const state = {
		category: config.defaultCategory || '',
		query: '',
	};

	function update() {
		let shown = 0;
		$grid.find( '.acme-grid__item' ).each( function () {
			const product = byId[ $( this ).data( 'product-id' ) ];
			const visible =
				product &&
				matchesCategory( product, state.category ) &&
				matchesQuery( product, state.query );
			$( this ).toggle( !! visible );
			if ( visible ) {
				shown++;
			}
		} );
		$grid.find( '.acme-grid__filter' ).each( function () {
			$( this ).toggleClass(
				'is-active',
				String( $( this ).data( 'category' ) || '' ) === state.category
			);
		} );
		$grid
			.find( '.acme-grid__count' )
			.text( countText( shown, config.products.length ) );
		$grid.find( '.acme-grid__empty' ).toggle( shown === 0 );
	}

	$grid.on( 'click', '.acme-grid__filter', function () {
		state.category = String( $( this ).data( 'category' ) || '' );
		update();
	} );

	$grid.on( 'input', '.acme-grid__search', function () {
		state.query = String( $( this ).val() );
		update();
	} );

	update();
}

$( function () {
	$( '[data-acme-grid]' ).each( function () {
		initGrid( this );
	} );
} );

/**
 * Front end of the product grid.
 *
 * The server renders the initial state with the same rules (see
 * includes/class-grid.php); this module keeps it up to date when visitors
 * pick a category or type in the search field. Every grid has its own state.
 */
import { store, getContext } from '@wordpress/interactivity';

/**
 * Does a product match the selected category and the search text? Every
 * word must appear in the product name or SKU (case-insensitive).
 *
 * @param {Object} product  Context product { id, c: categories, h: haystack }.
 * @param {string} category Selected category ('' = all).
 * @param {string} query    Search text.
 * @return {boolean} Match.
 */
function matches( product, category, query ) {
	if ( category && ! product.c.includes( category ) ) {
		return false;
	}
	const words = query.toLowerCase().trim().split( /\s+/ ).filter( Boolean );
	return words.every( ( word ) => product.h.includes( word ) );
}

function matching( context ) {
	return context.products.filter( ( product ) =>
		matches( product, context.category, context.query )
	);
}

store( 'acme/catalog', {
	state: {
		get isVisible() {
			const context = getContext();
			const product = context.products.find(
				( p ) => p.id === context.productId
			);
			return (
				!! product &&
				matches( product, context.category, context.query )
			);
		},
		get isActiveFilter() {
			const context = getContext();
			return context.category === context.slug;
		},
		get shownCount() {
			return matching( getContext() ).length;
		},
		get hasResults() {
			return matching( getContext() ).length > 0;
		},
		get countText() {
			const context = getContext();
			return context.countTemplate
				.replace( '%1$s', String( matching( context ).length ) )
				.replace( '%2$s', String( context.products.length ) );
		},
	},
	actions: {
		selectCategory() {
			const context = getContext();
			context.category = context.slug;
		},
		search( event ) {
			const context = getContext();
			context.query = event.target.value;
		},
	},
} );

/**
 * Front end of the product grid.
 *
 * The server renders the initial state with the same rules (see
 * includes/class-grid.php); this module keeps it up to date when visitors
 * pick a category, type in the search field or change the page. Every grid
 * has its own state; the first grid of the page also keeps it in the URL.
 */
import { store, getContext } from '@wordpress/interactivity';

const PARAM_CATEGORY = 'acme_cat';
const PARAM_QUERY = 'acme_q';
const PARAM_PAGE = 'acme_page';

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

function pageCount( context ) {
	if ( context.perPage < 1 ) {
		return 1;
	}
	return Math.max( 1, Math.ceil( matching( context ).length / context.perPage ) );
}

function visibleIds( context ) {
	let ids = matching( context ).map( ( product ) => product.id );
	if ( context.perPage > 0 ) {
		const start = ( Math.max( 1, context.page ) - 1 ) * context.perPage;
		ids = ids.slice( start, start + context.perPage );
	}
	return ids;
}

/**
 * Write the state of the URL-synced grid into the address bar.
 *
 * @param {Object}  context Grid context.
 * @param {boolean} push    Add a history entry (false: replace the current one).
 */
function writeUrl( context, push ) {
	if ( ! context.syncUrl ) {
		return;
	}
	const url = new URL( window.location.href );
	const params = url.searchParams;
	if ( context.category !== context.defaultCategory ) {
		params.set( PARAM_CATEGORY, context.category || 'all' );
	} else {
		params.delete( PARAM_CATEGORY );
	}
	if ( context.query.trim() ) {
		params.set( PARAM_QUERY, context.query );
	} else {
		params.delete( PARAM_QUERY );
	}
	if ( context.page > 1 ) {
		params.set( PARAM_PAGE, String( context.page ) );
	} else {
		params.delete( PARAM_PAGE );
	}
	if ( url.href === window.location.href ) {
		return;
	}
	// Keep the existing history state: WordPress' interactivity runtime
	// reloads the page on popstate for entries it doesn't recognise.
	const historyState = { ...( window.history.state || {} ) };
	if ( push ) {
		window.history.pushState( historyState, '', url );
	} else {
		window.history.replaceState( historyState, '', url );
	}
}

/**
 * Read the state from the URL (same rules as the server).
 *
 * @param {Object} context Grid context (mutated).
 */
function readUrl( context ) {
	const params = new URL( window.location.href ).searchParams;
	const category = params.get( PARAM_CATEGORY );
	if ( category === null ) {
		context.category = context.defaultCategory;
	} else if ( category === 'all' ) {
		context.category = '';
	} else if ( context.slugs.includes( category ) ) {
		context.category = category;
	} else {
		context.category = context.defaultCategory;
	}
	context.query = params.get( PARAM_QUERY ) ?? '';
	const page = params.get( PARAM_PAGE );
	context.page = 1;
	if ( page !== null && /^\d+$/.test( page ) ) {
		const number = parseInt( page, 10 );
		if ( number >= 1 && number <= pageCount( context ) ) {
			context.page = number;
		}
	}
}

store( 'acme/catalog', {
	state: {
		get isVisible() {
			const context = getContext();
			return visibleIds( context ).includes( context.productId );
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
		get hasPages() {
			return pageCount( getContext() ) > 1;
		},
		get isFirstPage() {
			return getContext().page <= 1;
		},
		get isLastPage() {
			const context = getContext();
			return context.page >= pageCount( context );
		},
		get pageText() {
			const context = getContext();
			return context.pageTemplate
				.replace( '%1$s', String( context.page ) )
				.replace( '%2$s', String( pageCount( context ) ) );
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
			if ( context.category === context.slug ) {
				return;
			}
			context.category = context.slug;
			context.page = 1;
			writeUrl( context, true );
		},
		search( event ) {
			const context = getContext();
			context.query = event.target.value;
			context.page = 1;
			writeUrl( context, false );
		},
		prevPage() {
			const context = getContext();
			if ( context.page > 1 ) {
				context.page--;
				writeUrl( context, true );
			}
		},
		nextPage() {
			const context = getContext();
			if ( context.page < pageCount( context ) ) {
				context.page++;
				writeUrl( context, true );
			}
		},
	},
	callbacks: {
		restoreFromUrl() {
			const context = getContext();
			if ( context.syncUrl ) {
				readUrl( context );
			}
		},
	},
} );

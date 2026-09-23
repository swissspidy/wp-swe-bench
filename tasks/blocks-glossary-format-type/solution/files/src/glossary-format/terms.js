/**
 * Published glossary terms (passed from PHP) and shortcode term resolution,
 * mirroring Acme\Glossary\Shortcodes::resolve() / acme_glossary_find_term().
 */
import { cleanForSlug } from '@wordpress/url';

export function getTerms() {
	return window.acmeGlossary?.terms || [];
}

const lower = ( value ) => String( value ).toLocaleLowerCase();

/**
 * Find a term by ID, slug or title (case-insensitive).
 *
 * @param {string|number} ref Reference.
 * @return {Object|undefined} Term { id, slug, title }.
 */
export function findTerm( ref ) {
	const terms = getTerms();
	const value = String( ref ?? '' ).trim();
	if ( value === '' ) {
		return undefined;
	}
	if ( /^\d+$/.test( value ) ) {
		return terms.find( ( term ) => term.id === Number( value ) );
	}
	const slug = cleanForSlug( value );
	return (
		terms.find( ( term ) => term.slug === slug ) ||
		terms.find( ( term ) => lower( term.title ) === lower( value ) )
	);
}

/**
 * Resolve the term of a [glossary] shortcode.
 *
 * @param {Object} attrs Named shortcode attributes.
 * @param {string} text  Enclosed text (plain).
 * @return {Object|undefined} Term.
 */
export function resolveShortcodeTerm( attrs, text ) {
	if ( attrs.id ) {
		const id = parseInt( attrs.id, 10 );
		return id > 0 ? findTerm( id ) : undefined;
	}
	return findTerm( attrs.term ? attrs.term : text );
}

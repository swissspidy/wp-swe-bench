<?php
/**
 * Public helper functions (used by themes – keep the signatures stable).
 *
 * @package Acme\Glossary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post type of glossary terms.
 */
const ACME_GLOSSARY_POST_TYPE = 'glossary_term';

/**
 * Meta key of the short definition (shown in tooltips and the index).
 */
const ACME_GLOSSARY_SHORT_META = '_acme_glossary_short';

/**
 * Short definition of a term, as plain text.
 *
 * Uses the "Short definition" field; falls back to the excerpt, then to the
 * first 30 words of the content.
 *
 * @param int|WP_Post $term Term post or ID.
 * @return string Plain text (not escaped).
 */
function acme_glossary_get_definition( $term ) {
	$term = get_post( $term );
	if ( ! $term || ACME_GLOSSARY_POST_TYPE !== $term->post_type ) {
		return '';
	}

	$definition = (string) get_post_meta( $term->ID, ACME_GLOSSARY_SHORT_META, true );
	if ( '' === trim( $definition ) ) {
		$definition = $term->post_excerpt;
	}
	if ( '' === trim( $definition ) ) {
		$definition = wp_trim_words( strip_shortcodes( $term->post_content ), 30, '…' );
	}
	$definition = trim( wp_strip_all_tags( $definition ) );

	/**
	 * Filters the short definition of a glossary term.
	 *
	 * @param string  $definition Plain-text definition.
	 * @param WP_Post $term       Term post.
	 */
	return (string) apply_filters( 'acme_glossary_definition', $definition, $term );
}

/**
 * Find a published glossary term by ID, slug or title (case-insensitive).
 *
 * This is how the [glossary] shortcode resolves `id="…"` and `term="…"`.
 *
 * @param int|string $ref Term ID, slug or title.
 * @return WP_Post|null
 */
function acme_glossary_find_term( $ref ) {
	if ( is_int( $ref ) || ( is_string( $ref ) && ctype_digit( $ref ) ) ) {
		$post = get_post( (int) $ref );
		return ( $post && ACME_GLOSSARY_POST_TYPE === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
	}

	$ref = trim( wp_strip_all_tags( (string) $ref ) );
	if ( '' === $ref ) {
		return null;
	}

	$map  = Acme\Glossary\Term_Cache::map();
	$slug = sanitize_title( $ref );
	if ( isset( $map['slugs'][ $slug ] ) ) {
		return get_post( $map['slugs'][ $slug ] );
	}
	$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $ref ) : strtolower( $ref );
	if ( isset( $map['titles'][ $title ] ) ) {
		return get_post( $map['titles'][ $title ] );
	}
	return null;
}

/**
 * URL of the glossary archive.
 *
 * @return string
 */
function acme_glossary_archive_url() {
	return (string) get_post_type_archive_link( ACME_GLOSSARY_POST_TYPE );
}

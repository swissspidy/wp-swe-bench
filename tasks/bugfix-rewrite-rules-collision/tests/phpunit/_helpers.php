<?php
/**
 * Helpers for the Acme Docs routing tests. Seeded content is looked up with plain SQL so that the
 * lookups don't depend on the plugin code under test.
 */

namespace WPSB\Docs;

/** Term ID by taxonomy + slug. */
function term_id( string $taxonomy, string $slug ): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.slug = %s",
			$taxonomy,
			$slug
		)
	);
}

/** Term slugs of a post in a taxonomy. */
function post_terms( int $post_id, string $taxonomy ): array {
	global $wpdb;
	return $wpdb->get_col(
		$wpdb->prepare(
			"SELECT t.slug FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id = %d AND tt.taxonomy = %s",
			$post_id,
			$taxonomy
		)
	);
}

/** Seeded doc ID by product, version ('' = current) and path. */
function doc( string $product, string $path, string $version = '' ): int {
	global $wpdb;
	$parent = 0;
	$id     = 0;
	foreach ( explode( '/', $path ) as $segment ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'doc' AND post_name = %s AND post_parent = %d ORDER BY ID", $segment, $parent ) );
		$id  = 0;
		foreach ( $ids as $candidate ) {
			$v = post_terms( (int) $candidate, 'doc_version' );
			if ( in_array( $product, post_terms( (int) $candidate, 'product' ), true ) && ( $version ? array( $version ) : array() ) === $v ) {
				$id = (int) $candidate;
				break;
			}
		}
		if ( ! $id ) {
			throw new \RuntimeException( "Seeded doc $product/$version/$path not found" );
		}
		$parent = $id;
	}
	return $id;
}

/** Seeded doc ID by title (for docs without a slug). */
function doc_by_title( string $title ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'doc' AND post_title = %s ORDER BY ID LIMIT 1", $title ) );
}

/** Page ID by path. */
function page( string $path ): int {
	global $wpdb;
	$parent = 0;
	$id     = 0;
	foreach ( explode( '/', $path ) as $segment ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_name = %s AND post_parent = %d", $segment, $parent ) );
		if ( ! $id ) {
			throw new \RuntimeException( "Seeded page $path not found" );
		}
		$parent = $id;
	}
	return $id;
}

/**
 * What a rendered page shows, from core's body classes.
 *
 * @return array{kind:string, id:int, paged:int}
 */
function shown( string $html ): array {
	if ( ! preg_match( '/<body[^>]*class="([^"]*)"/', $html, $m ) ) {
		return array( 'kind' => 'none', 'id' => 0, 'paged' => 0 );
	}
	$classes = preg_split( '/\s+/', $m[1] );
	$paged   = 0;
	foreach ( $classes as $c ) {
		if ( preg_match( '/^paged-(\d+)$/', $c, $pm ) ) {
			$paged = (int) $pm[1];
		}
	}
	foreach ( $classes as $c ) {
		if ( 'error404' === $c ) {
			return array( 'kind' => '404', 'id' => 0, 'paged' => $paged );
		}
	}
	foreach ( $classes as $c ) {
		if ( preg_match( '/^postid-(\d+)$/', $c, $pm ) ) {
			return array( 'kind' => 'doc', 'id' => (int) $pm[1], 'paged' => $paged );
		}
		if ( preg_match( '/^page-id-(\d+)$/', $c, $pm ) ) {
			return array( 'kind' => 'page', 'id' => (int) $pm[1], 'paged' => $paged );
		}
	}
	if ( in_array( 'tax-product', $classes, true ) ) {
		foreach ( $classes as $c ) {
			if ( preg_match( '/^term-(\d+)$/', $c, $pm ) ) {
				return array( 'kind' => 'product', 'id' => (int) $pm[1], 'paged' => $paged );
			}
		}
	}
	if ( in_array( 'post-type-archive-doc', $classes, true ) ) {
		return array( 'kind' => 'archive', 'id' => 0, 'paged' => $paged );
	}
	return array( 'kind' => 'other', 'id' => 0, 'paged' => $paged );
}

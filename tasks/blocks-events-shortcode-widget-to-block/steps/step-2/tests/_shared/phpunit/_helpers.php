<?php
/**
 * Helpers for the Acme Events tests: render listings and compare their markup.
 */

namespace WPSB\Events;

const BLOCK = 'acme/upcoming-events';

/** Rendered [acme_events] shortcode. */
function shortcode( string $atts = '' ): string {
	return do_shortcode( '[acme_events' . ( '' !== $atts ? ' ' . $atts : '' ) . ']' );
}

/** Rendered acme/upcoming-events block with the given attributes. */
function block( array $attrs = array() ): string {
	$json = $attrs ? ' ' . serialize_block_attributes( $attrs ) : '';
	return do_blocks( '<!-- wp:' . BLOCK . $json . ' /-->' );
}

/** Render post content through the_content like the front end would. */
function render_post( \WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function post_by_slug( string $slug, string $type = 'post' ): \WP_Post {
	global $wpdb;
	// Direct lookup: drafts are not visible to anonymous singular queries.
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s ORDER BY ID LIMIT 1", $slug, $type ) );
	$post = $id ? get_post( $id ) : null;
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $type '$slug' not found" );
	}
	return $post;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/**
 * All event listings (div.acme-events) in some HTML, in document order.
 *
 * @return array<int, array{classes:string[], inner:string, titles:string[], items:int, heading:?string, node:\DOMElement}>
 */
function listings( string $html, ?\DOMXPath $xpath = null, ?\DOMNode $context = null ): array {
	$xpath = $xpath ?? dom( $html );
	$out   = array();
	$nodes = $context ? $xpath->query( './/*[' . has_class( 'acme-events' ) . ']', $context ) : $xpath->query( '//*[' . has_class( 'acme-events' ) . ']' );
	foreach ( $nodes as $node ) {
		$inner = '';
		foreach ( $node->childNodes as $child ) {
			$inner .= $node->ownerDocument->saveHTML( $child );
		}
		$titles = array();
		foreach ( $xpath->query( './/*[' . has_class( 'acme-event__link' ) . ']', $node ) as $a ) {
			$titles[] = trim( $a->textContent );
		}
		$heading = null;
		foreach ( $xpath->query( './/*[' . has_class( 'acme-events__title' ) . ']', $node ) as $h ) {
			$heading = trim( $h->textContent );
		}
		$out[] = array(
			'tag'     => strtolower( $node->nodeName ),
			'classes' => preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ),
			'inner'   => squish_html( $inner ),
			'titles'  => $titles,
			'items'   => $xpath->query( './/*[' . has_class( 'acme-event' ) . ']', $node )->length,
			'heading' => $heading,
			'node'    => $node,
		);
	}
	return $out;
}

/** Collapse insignificant whitespace between tags. */
function squish_html( string $html ): string {
	$html = preg_replace( '/>\s+</', '><', $html );
	$html = preg_replace( '/\s+/', ' ', $html );
	return trim( $html );
}

/** The single listing in some HTML (fails loudly otherwise). */
function single_listing( string $html ): array {
	$all = listings( $html );
	if ( 1 !== count( $all ) ) {
		throw new \RuntimeException( 'Expected exactly one div.acme-events, got ' . count( $all ) . ":\n" . $html );
	}
	return $all[0];
}

/** Every top-level and nested block in parsed blocks, flattened. */
function flatten_blocks( array $blocks ): array {
	$out = array();
	foreach ( $blocks as $b ) {
		if ( null !== $b['blockName'] ) {
			$out[] = $b;
		}
		$out = array_merge( $out, flatten_blocks( $b['innerBlocks'] ?? array() ) );
	}
	return $out;
}

/** Attributes of an acme/upcoming-events block merged with the documented defaults. */
function with_defaults( array $attrs ): array {
	return array_merge(
		array(
			'limit'     => 5,
			'category'  => '',
			'showPast'  => false,
			'layout'    => 'list',
			'title'     => '',
			'showVenue' => true,
		),
		$attrs
	);
}

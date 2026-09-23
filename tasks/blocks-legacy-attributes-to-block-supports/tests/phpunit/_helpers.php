<?php
/**
 * Helpers for the Acme Content Blocks tests.
 */

namespace WPSB\ContentBlocks;

function render_slug( string $slug, string $post_type = 'post' ): string {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function post_id( string $slug, string $post_type = 'post' ): int {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	return (int) $post->ID;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class_xpath( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/** Parse a style attribute into property => value (lowercased property, trimmed value). */
function style_map( string $style ): array {
	$out = array();
	foreach ( explode( ';', $style ) as $decl ) {
		$parts = explode( ':', $decl, 2 );
		if ( 2 === count( $parts ) && '' !== trim( $parts[0] ) ) {
			$out[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
		}
	}
	return $out;
}

/**
 * Rendered wrappers of a block (by its root class) in document order.
 *
 * @return array<int, array{classes:string[], style:array<string,string>, attrs:array<string,string>, node:\DOMElement, text:string}>
 */
function blocks( string $html, string $root_class ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( $root_class ) . ']' ) as $node ) {
		$attrs = array();
		foreach ( $node->attributes as $a ) {
			$attrs[ $a->name ] = $a->value;
		}
		$out[] = array(
			'classes' => preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ),
			'style'   => style_map( $node->getAttribute( 'style' ) ),
			'attrs'   => $attrs,
			'node'    => $node,
			'xpath'   => $xpath,
			'text'    => trim( preg_replace( '/\s+/', ' ', $node->textContent ) ),
		);
	}
	return $out;
}

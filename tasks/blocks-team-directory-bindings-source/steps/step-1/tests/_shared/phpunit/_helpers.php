<?php
/**
 * Helpers for the Acme Team binding tests.
 */

namespace WPSB\Team;

/** ID of a seeded post of any status. */
function id_of( string $slug, string $post_type = 'acme_member' ): int {
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s ORDER BY ID ASC LIMIT 1", $slug, $post_type ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	return (int) $id;
}

/** Render a post's content the way the front end does (global post set). */
function render_post( \WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	unset( $GLOBALS['post'] );
	return (string) $html;
}

function render_slug( string $slug, string $post_type = 'page' ): string {
	return render_post( get_post( id_of( $slug, $post_type ) ) );
}

/** Render arbitrary block markup inside a (fake, published) page. */
function render_content( string $content, int $post_id = 0, string $post_type = 'page' ): string {
	$post = new \WP_Post(
		(object) array(
			'ID'           => $post_id,
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_content' => $content,
			'filter'       => 'raw',
		)
	);
	$GLOBALS['post'] = $post;
	$html            = apply_filters( 'the_content', $content );
	unset( $GLOBALS['post'] );
	return (string) $html;
}

/** A bound block. $bindings: attribute => args. */
function bound( string $block, array $bindings, string $html, array $attrs = array() ): string {
	$map = array();
	foreach ( $bindings as $attribute => $args ) {
		$map[ $attribute ] = array(
			'source' => 'acme/team-member',
			'args'   => $args,
		);
	}
	$attrs['metadata'] = array( 'bindings' => $map );
	return serialize_block(
		array(
			'blockName'    => $block,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerContent' => array( $html ),
		)
	);
}

function p( array $args, string $fallback = 'FALLBACK' ): string {
	return bound( 'core/paragraph', array( 'content' => $args ), "\n<p>$fallback</p>\n" );
}

function button( array $text_args, ?array $url_args, string $fallback = 'BUTTON' ): string {
	$bindings = array( 'text' => $text_args );
	if ( $url_args ) {
		$bindings['url'] = $url_args;
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">" . bound( 'core/button', $bindings, "\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\">$fallback</a></div>\n" ) . "</div>\n<!-- /wp:buttons -->";
}

function image( array $args ): string {
	return bound( 'core/image', array( 'url' => $args, 'alt' => $args ), "\n<figure class=\"wp-block-image\"><img alt=\"\"/></figure>\n" );
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

/** Text of all nodes matching an XPath, in order. */
function texts( string $html, string $xpath ): array {
	$out = array();
	foreach ( dom( $html )->query( $xpath ) as $node ) {
		$out[] = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
	}
	return $out;
}

/** Attribute values of all nodes matching an XPath (null when missing). */
function attrs( string $html, string $xpath, string $attr ): array {
	$out = array();
	foreach ( dom( $html )->query( $xpath ) as $node ) {
		$out[] = $node->hasAttribute( $attr ) ? $node->getAttribute( $attr ) : null;
	}
	return $out;
}

/** Buttons: [ [text, href|null], … ]. */
function buttons( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//a[contains(@class,"wp-block-button__link")]' ) as $a ) {
		$out[] = array( trim( $a->textContent ), $a->hasAttribute( 'href' ) ? $a->getAttribute( 'href' ) : null );
	}
	return $out;
}

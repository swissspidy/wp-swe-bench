<?php
/**
 * Helpers for the callout tests: render seeded content and inspect callout markup.
 */

namespace WPSB\Callouts;

/** Render a seeded post's content through the_content, as the front end would. */
function render_slug( string $slug, string $post_type = 'post' ): string {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	return render_post( $post );
}

function render_post( \WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function render_content( string $content ): string {
	$post = new \WP_Post(
		(object) array(
			'ID'           => 0,
			'post_type'    => 'post',
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

function inner_html( \DOMNode $node ): string {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/**
 * All rendered callouts in document order.
 *
 * @return array<int, array{tag:string, classes:string[], attrs:array<string,string>, title:?string, title_html:?string, body:?\DOMElement, body_html:?string, node:\DOMElement}>
 */
function callouts( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( 'wp-block-acme-callout' ) . ']' ) as $node ) {
		$attrs = array();
		foreach ( $node->attributes as $a ) {
			$attrs[ $a->name ] = $a->value;
		}
		$title = null;
		$body  = null;
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$cls = ' ' . $child->getAttribute( 'class' ) . ' ';
			if ( null === $title && false !== strpos( $cls, ' wp-block-acme-callout__title ' ) ) {
				$title = $child;
			}
			if ( null === $body && false !== strpos( $cls, ' wp-block-acme-callout__body ' ) ) {
				$body = $child;
			}
		}
		$out[] = array(
			'tag'        => strtolower( $node->nodeName ),
			'classes'    => preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ),
			'attrs'      => $attrs,
			'title'      => $title ? trim( $title->textContent ) : null,
			'title_html' => $title ? inner_html( $title ) : null,
			'title_tag'  => $title ? strtolower( $title->nodeName ) : null,
			'body'       => $body,
			'body_html'  => $body ? inner_html( $body ) : null,
			'node'       => $node,
		);
	}
	return $out;
}

/** Normalize whitespace for comparisons. */
function squish( string $s ): string {
	return trim( preg_replace( '/\s+/', ' ', $s ) );
}

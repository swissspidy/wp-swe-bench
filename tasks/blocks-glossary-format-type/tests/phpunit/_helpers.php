<?php
/**
 * Helpers for the glossary tests: render content and inspect glossary terms.
 */

namespace WPSB\Glossary;

function term_id( string $slug ): int {
	$p = get_page_by_path( $slug, OBJECT, 'glossary_term' );
	if ( ! $p ) {
		throw new \RuntimeException( "Seeded glossary term '$slug' not found" );
	}
	return (int) $p->ID;
}

function render_post( \WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function render_slug( string $slug, string $type = 'post' ): string {
	$post = get_page_by_path( $slug, OBJECT, $type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $type '$slug' not found" );
	}
	return render_post( $post );
}

function render_content( string $content ): string {
	$post            = new \WP_Post(
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

/** Stored markup of a marked term, as the editor writes it. */
function mark( int $id, string $text ): string {
	return '<span class="acme-glossary-term" data-term-id="' . $id . '">' . $text . '</span>';
}

function paragraph( string $html ): string {
	return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->";
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function squish( string $s ): string {
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * Rendered glossary terms in document order.
 *
 * @return array<int, array{attrs: array<string,string>, text: string, inner_html: string, tooltip: ?array{attrs: array<string,string>, text: string, children: int}}>
 */
function terms( string $html ): array {
	$xp  = dom( $html );
	$out = array();
	foreach ( $xp->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' acme-glossary-term ')]" ) as $el ) {
		$attrs = array();
		foreach ( $el->attributes as $a ) {
			$attrs[ $a->name ] = $a->value;
		}
		$tooltip = null;
		if ( ! empty( $attrs['aria-describedby'] ) ) {
			foreach ( preg_split( '/\s+/', trim( $attrs['aria-describedby'] ) ) as $ref ) {
				$t = $xp->query( '//*[@id="' . $ref . '"]' )->item( 0 );
				if ( $t ) {
					$tattrs = array();
					foreach ( $t->attributes as $a ) {
						$tattrs[ $a->name ] = $a->value;
					}
					$children = 0;
					foreach ( $t->childNodes as $c ) {
						if ( XML_ELEMENT_NODE === $c->nodeType ) {
							++$children;
						}
					}
					$tooltip = array(
						'attrs'    => $tattrs,
						'text'     => squish( $t->textContent ),
						'children' => $children,
						'node'     => $t,
					);
					break;
				}
			}
		}
		// Visible text = text without the tooltip element.
		$clone = $el->cloneNode( true );
		foreach ( iterator_to_array( $clone->getElementsByTagName( '*' ) ) as $c ) {
			if ( ' ' !== ' ' . $c->getAttribute( 'role' ) && 'tooltip' === $c->getAttribute( 'role' ) ) {
				$c->parentNode->removeChild( $c );
			}
		}
		$inner = '';
		foreach ( $clone->childNodes as $c ) {
			$inner .= $el->ownerDocument->saveHTML( $c );
		}
		$out[] = array(
			'attrs'      => $attrs,
			'text'       => squish( $clone->textContent ),
			'inner_html' => $inner,
			'tooltip'    => $tooltip,
		);
	}
	return $out;
}

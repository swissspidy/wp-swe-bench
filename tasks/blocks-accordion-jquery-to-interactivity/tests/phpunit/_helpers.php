<?php
/**
 * Helpers for the FAQ tests: parse rendered accordion markup.
 */

namespace WPSB\Faq;

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . ( false === stripos( $html, '<html' ) ? '<html><body>' . $html . '</body></html>' : $html ) );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class_xpath( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function squish( string $s ): string {
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

function inner_html( \DOMNode $node ): string {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/**
 * All FAQs of a page with their items.
 *
 * @return array<int, array{node:\DOMElement, classes:string[], items:array}>
 */
function faqs( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( 'wp-block-acme-faq' ) . ']' ) as $faq ) {
		$items = array();
		foreach ( $xpath->query( './/*[' . has_class_xpath( 'wp-block-acme-faq-item' ) . ']', $faq ) as $item ) {
			$button = $xpath->query( './/button[' . has_class_xpath( 'acme-faq__toggle' ) . ']', $item )->item( 0 );
			$panel  = $xpath->query( './/*[' . has_class_xpath( 'acme-faq__panel' ) . ']', $item )->item( 0 );
			$items[] = array(
				'node'          => $item,
				'id'            => $item->getAttribute( 'id' ),
				'button'        => $button,
				'heading_tag'   => $button && $button->parentNode ? strtolower( $button->parentNode->nodeName ) : null,
				'heading_class' => $button && $button->parentNode instanceof \DOMElement ? $button->parentNode->getAttribute( 'class' ) : null,
				'question'      => $button ? squish( $button->textContent ) : null,
				'question_html' => $button ? inner_html( $button ) : null,
				'expanded'      => $button && $button->hasAttribute( 'aria-expanded' ) ? $button->getAttribute( 'aria-expanded' ) : null,
				'controls'      => $button ? $button->getAttribute( 'aria-controls' ) : null,
				'button_id'     => $button ? $button->getAttribute( 'id' ) : null,
				'button_type'   => $button ? $button->getAttribute( 'type' ) : null,
				'panel'         => $panel,
				'panel_id'      => $panel ? $panel->getAttribute( 'id' ) : null,
				'role'          => $panel ? $panel->getAttribute( 'role' ) : null,
				'labelledby'    => $panel ? $panel->getAttribute( 'aria-labelledby' ) : null,
				'hidden'        => $panel ? $panel->hasAttribute( 'hidden' ) : null,
				'answer_html'   => $panel ? inner_html( $panel ) : null,
				'answer'        => $panel ? squish( $panel->textContent ) : null,
			);
		}
		$out[] = array(
			'node'    => $faq,
			'classes' => preg_split( '/\s+/', trim( $faq->getAttribute( 'class' ) ) ),
			'items'   => $items,
		);
	}
	return $out;
}

/** All id values in the page. */
function ids( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//*[@id]' ) as $el ) {
		$out[] = $el->getAttribute( 'id' );
	}
	return $out;
}

/** FAQPage JSON-LD objects in the page. */
function json_ld( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//script[@type="application/ld+json"]' ) as $s ) {
		$data = json_decode( $s->textContent, true );
		if ( is_array( $data ) && 'FAQPage' === ( $data['@type'] ?? null ) ) {
			$out[] = $data;
		}
	}
	return $out;
}

/** Script src URLs of the page. */
function script_srcs( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//script[@src]' ) as $s ) {
		$out[] = $s->getAttribute( 'src' );
	}
	return $out;
}

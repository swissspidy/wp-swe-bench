<?php
/**
 * Helpers for the testimonial tests.
 */

namespace WPSB\Testimonials;

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function cls( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function inner_html( \DOMNode $node ): string {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/** Extract the JSON-LD Review items from a page. */
function reviews( string $html ): ?array {
	if ( ! preg_match( '#<script[^>]*class="acme-testimonials-schema"[^>]*>(.*?)</script>#s', $html, $m ) ) {
		return null;
	}
	$data = json_decode( $m[1], true );
	return $data['@graph'] ?? null;
}

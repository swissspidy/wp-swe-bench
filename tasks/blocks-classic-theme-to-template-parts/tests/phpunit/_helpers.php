<?php
/**
 * Helpers for the Acme Corporate tests: split served pages into header / footer regions.
 */

namespace WPSB\Corp;

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function text( ?\DOMNode $n ): string {
	return $n ? trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $n->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) : '';
}

/** Markup between <body> and the main content. */
function header_html( string $page ): string {
	$b = stripos( $page, '<body' );
	$m = stripos( $page, '<main id="primary"' );
	if ( false === $b || false === $m ) {
		throw new \RuntimeException( 'Page has no <body> or <main id="primary">' );
	}
	$b = strpos( $page, '>', $b ) + 1;
	return substr( $page, $b, $m - $b );
}

/** Markup after the main content (up to </body>). */
function footer_html( string $page ): string {
	$m = strripos( $page, '</main>' );
	if ( false === $m ) {
		throw new \RuntimeException( 'Page has no </main>' );
	}
	$e = strripos( $page, '</body>' );
	return substr( $page, $m + 7, ( false === $e ? strlen( $page ) : $e ) - $m - 7 );
}

/** Normalizes a URL for comparison: absolute, no trailing slash differences on the home URL. */
function norm_url( string $href ): string {
	$href = html_entity_decode( trim( $href ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( 0 === strpos( $href, '/' ) && 0 !== strpos( $href, '//' ) ) {
		$href = rtrim( home_url(), '/' ) . $href;
	}
	if ( rtrim( $href, '/' ) === rtrim( home_url(), '/' ) ) {
		return rtrim( home_url(), '/' ) . '/';
	}
	return $href;
}

/**
 * Links inside <nav> elements of a region: list of [label, url].
 *
 * @return array<int, array{0:string, 1:string}>
 */
function nav_links( string $region ): array {
	$x   = dom( $region );
	$out = array();
	foreach ( $x->query( '//nav//a[@href]' ) as $a ) {
		$out[] = array( text( $a ), norm_url( $a->getAttribute( 'href' ) ) );
	}
	return $out;
}

/** All hrefs of a region. */
function hrefs( string $region ): array {
	$out = array();
	foreach ( dom( $region )->query( '//a[@href]' ) as $a ) {
		$out[] = html_entity_decode( $a->getAttribute( 'href' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	return $out;
}

/** The first link with the given (visible) text. */
function link_by_text( string $region, string $label ): ?\DOMElement {
	foreach ( dom( $region )->query( '//a' ) as $a ) {
		if ( text( $a ) === $label ) {
			return $a;
		}
	}
	return null;
}

function cli( string $args ): string {
	$proc = proc_open( 'wp ' . $args, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/wordpress' );
	$out  = stream_get_contents( $pipes[1] );
	$err  = stream_get_contents( $pipes[2] );
	if ( 0 !== proc_close( $proc ) ) {
		throw new \RuntimeException( "wp $args failed: $out $err" );
	}
	return trim( $out );
}

/** Menu ID by name. */
function menu_id( string $name ): int {
	$menu = wp_get_nav_menu_object( $name );
	if ( ! $menu ) {
		throw new \RuntimeException( "Menu $name not found" );
	}
	return (int) $menu->term_id;
}

<?php
/**
 * Helpers for the Acme Courses tests: DOM inspection of served pages, theme switching.
 */

namespace WPSB\Courses;

const THEME         = 'twentytwentyfive';
const CLASSIC_THEME = 'acme-classic';
const CHILD_THEME   = 'wpsb-course-child';

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . ( false === stripos( $html, '<html' ) ? '<html><body>' . $html . '</body></html>' : $html ) );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function cls( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/** Elements with the given class. */
function by_class( \DOMXPath $x, string $class, ?\DOMNode $ctx = null ): array {
	$list = $ctx ? $x->query( './/*[' . cls( $class ) . ']', $ctx ) : $x->query( '//*[' . cls( $class ) . ']' );
	return iterator_to_array( $list );
}

function text( ?\DOMNode $n ): string {
	return $n ? trim( preg_replace( '/\s+/u', ' ', $n->textContent ) ) : '';
}

function outer( \DOMNode $n ): string {
	return $n->ownerDocument->saveHTML( $n );
}

function course_id( string $slug ): int {
	$p = get_page_by_path( $slug, OBJECT, 'acme_course' );
	if ( ! $p ) {
		throw new \RuntimeException( "Seeded course '$slug' not found" );
	}
	return (int) $p->ID;
}

/**
 * Walks the <main> of a listing page in document order and returns, for every course title
 * link, the texts of the price / duration blocks that follow it (up to the next course link).
 *
 * @return array<string, array{price:?string, duration:?string, count:int}> keyed by permalink path
 */
function listing( string $html ): array {
	$x     = dom( $html );
	$out   = array();
	$cur   = null;
	$nodes = $x->query( '//*' );
	foreach ( $nodes as $n ) {
		if ( 'a' === strtolower( $n->nodeName ) && preg_match( '#/courses/([a-z0-9-]+)/?$#', (string) $n->getAttribute( 'href' ), $m ) ) {
			// Only title links (inside a heading).
			$p = $n->parentNode;
			if ( $p && preg_match( '/^h[1-6]$/', strtolower( $p->nodeName ) ) ) {
				$cur = $m[1];
				if ( ! isset( $out[ $cur ] ) ) {
					$out[ $cur ] = array( 'price' => null, 'duration' => null, 'count' => 0 );
				}
				++$out[ $cur ]['count'];
			}
			continue;
		}
		if ( null === $cur || ! $n instanceof \DOMElement ) {
			continue;
		}
		$c = ' ' . $n->getAttribute( 'class' ) . ' ';
		if ( false !== strpos( $c, ' wp-block-acme-courses-course-price ' ) && null === $out[ $cur ]['price'] ) {
			$out[ $cur ]['price'] = text( $n );
		}
		if ( false !== strpos( $c, ' wp-block-acme-courses-course-duration ' ) && null === $out[ $cur ]['duration'] ) {
			$out[ $cur ]['duration'] = text( $n );
		}
	}
	return $out;
}

/** Run WP-CLI (native) and fail loudly. */
function cli( string $args ): string {
	$proc = proc_open( 'wp ' . $args, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/wordpress' );
	$out  = stream_get_contents( $pipes[1] );
	$err  = stream_get_contents( $pipes[2] );
	if ( 0 !== proc_close( $proc ) ) {
		throw new \RuntimeException( "wp $args failed: $out $err" );
	}
	return trim( $out );
}

/** Activate a theme for the HTTP server (the PHPUnit process keeps its own). */
function activate_theme( string $stylesheet ): void {
	cli( 'theme activate ' . escapeshellarg( $stylesheet ) );
}

/** A throw-away block child theme of Twenty Twenty-Five with the given files. */
function make_child_theme( array $files ): void {
	$dir = WP_CONTENT_DIR . '/themes/' . CHILD_THEME;
	remove_child_theme();
	mkdir( $dir . '/templates', 0777, true );
	mkdir( $dir . '/parts', 0777, true );
	file_put_contents( $dir . '/style.css', "/*\nTheme Name: WPSB Course Child\nTemplate: twentytwentyfive\nVersion: 1.0\n*/\n" );
	file_put_contents( $dir . '/theme.json', '{"version":3,"$schema":"https://schemas.wp.org/trunk/theme.json"}' );
	foreach ( $files as $rel => $content ) {
		file_put_contents( $dir . '/' . $rel, $content );
	}
}

function remove_child_theme(): void {
	$dir = WP_CONTENT_DIR . '/themes/' . CHILD_THEME;
	if ( is_dir( $dir ) ) {
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}

/** Delete all saved (customized) templates and template parts. */
function delete_customizations(): void {
	foreach ( get_posts( array( 'post_type' => array( 'wp_template', 'wp_template_part' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		wp_delete_post( $p->ID, true );
	}
}

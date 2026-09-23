<?php
/**
 * Helpers for the WordPress Importer URL rewriting tests.
 */

namespace WPSB\Importer;

const OLD  = 'https://oldblog.example';
const SITE = 'http://127.0.0.1:9400';

const FIXTURES = __DIR__ . '/../fixtures';

/** Posts in the full fixture: slug => [post type, content file]. */
function posts(): array {
	return array(
		'main-menu'          => array( 'wp_navigation', 'main-menu.html' ),
		'follow-us'          => array( 'post', 'follow-us.html' ),
		'summer-in-the-alps' => array( 'post', 'cover-parallax.html' ),
		'granite-pattern'    => array( 'post', 'cover-repeated.html' ),
		'old-landing-page'   => array( 'post', 'html-backgrounds.html' ),
		'newsletter'         => array( 'post', 'newsletter.html' ),
		'packing-tips'       => array( 'post', 'links.html' ),
	);
}

function original( string $slug ): string {
	return rtrim( file_get_contents( FIXTURES . '/content/' . posts()[ $slug ][1] ) );
}

function imported_row( string $slug ): ?array {
	global $wpdb;
	$wpdb->flush();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s ORDER BY ID DESC LIMIT 1", $slug, posts()[ $slug ][0] ), ARRAY_A );
	return $row ?: null;
}

function imported( string $slug ): string {
	$row = imported_row( $slug );
	if ( ! $row ) {
		throw new \RuntimeException( "Post $slug was not imported" );
	}
	return $row['post_content'];
}

/** Import a WXR file the way the importer's admin screen does (without downloading attachments). */
function import_command( string $file, bool $rewrite ): string {
	return "--exec='define( \"WP_LOAD_IMPORTERS\", true );' eval-file /tests/import.php " . escapeshellarg( $file ) . ' ' . ( $rewrite ? '1' : '0' );
}

/** Block tree shape: [ [name, children], ... ] (whitespace-only freeform chunks ignored). */
function shape( array $blocks ): array {
	$out = array();
	foreach ( $blocks as $block ) {
		if ( null === $block['blockName'] ) {
			if ( '' !== trim( $block['innerHTML'] ) ) {
				$out[] = array( '#html', array() );
			}
			continue;
		}
		$out[] = array( $block['blockName'], shape( $block['innerBlocks'] ) );
	}
	return $out;
}

/** All blocks of a name, depth first. */
function find_blocks( array $blocks, string $name ): array {
	$out = array();
	foreach ( $blocks as $block ) {
		if ( $block['blockName'] === $name ) {
			$out[] = $block;
		}
		$out = array_merge( $out, find_blocks( $block['innerBlocks'], $name ) );
	}
	return $out;
}

/** Self-closing block comments per block name: name => count. */
function self_closing( string $content ): array {
	preg_match_all( '#<!--\s+wp:([a-z0-9_/-]+)(?:\s+\{.*?\})?\s+/-->#s', $content, $m );
	$counts = array_count_values( $m[1] );
	ksort( $counts );
	return $counts;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

/** URLs referenced with url(...) in a CSS declaration list. */
function css_urls( string $css ): array {
	preg_match_all( '/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)/i', $css, $m, PREG_SET_ORDER );
	return array_map( static fn( $x ) => $x[3] ?? ( '' !== ( $x[2] ?? '' ) ? $x[2] : $x[1] ), $m );
}

/** class => url() values of the style attribute of every element with a class in $html. */
function style_urls_by_class( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//*[@style]' ) as $el ) {
		$key         = $el->getAttribute( 'class' ) ?: $el->nodeName;
		$out[ $key ] = css_urls( $el->getAttribute( 'style' ) );
	}
	return $out;
}

/** The background image URL in a cover block's markup. */
function cover_background( array $cover ): ?string {
	$urls = style_urls_by_class( $cover['innerHTML'] );
	foreach ( $urls as $class => $list ) {
		if ( false !== strpos( $class, 'wp-block-cover__image-background' ) ) {
			return $list[0] ?? null;
		}
	}
	return null;
}

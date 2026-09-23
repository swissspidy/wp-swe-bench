<?php
/**
 * Helpers for the upcoming events tests.
 *
 * Time is controlled through the plugin's documented clock filter (acme_events_now),
 * pinned by the grading mu-plugin to the `wpsb_events_now` option.
 */

namespace WPSB\Events;

const TZ = 'Pacific/Auckland';

/** Timestamp of a local (site timezone) date/time. */
function local( string $datetime ): int {
	return ( new \DateTimeImmutable( $datetime, new \DateTimeZone( TZ ) ) )->getTimestamp();
}

function set_now( string $local ): void {
	update_option( 'wpsb_events_now', local( $local ), false );
}

function clear_now(): void {
	delete_option( 'wpsb_events_now' );
}

/** All seeded upcoming events at 2031-06-15 10:30 (local), in order. */
const UPCOMING_AT_1030 = array(
	'Winter festival',
	'Sunday market',
	'Afternoon talk',
	'Late show',
	'Board games night',
	'Winter meetup',
	'Harbour cleanup',
	'Kayak trip',
	'Spring conference',
);

/** Query Loop markup as saved by the editor for the "Upcoming events" variation (namespace-only query). */
function upcoming_loop( int $query_id, int $per_page, string $class = 'upcoming-loop', array $query_extra = array() ): string {
	$attrs = array(
		'queryId'   => $query_id,
		'query'     => array_merge(
			array(
				'perPage'  => $per_page,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => 'acme_event',
				'order'    => 'asc',
				'orderBy'  => 'date',
				'author'   => '',
				'search'   => '',
				'exclude'  => array(),
				'sticky'   => '',
				'inherit'  => false,
			),
			$query_extra
		),
		'namespace' => 'acme/upcoming-events',
		'className' => $class,
	);
	return '<!-- wp:query ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->
<div class="wp-block-query ' . $class . '"><!-- wp:post-template -->
<!-- wp:post-title {"isLink":true,"level":3} /-->

<!-- wp:acme/event-date /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No upcoming events.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->';
}

/** A plain Query Loop listing events (not the variation). */
function plain_loop( int $query_id, string $class = 'plain-loop' ): string {
	$attrs = array(
		'queryId'   => $query_id,
		'query'     => array(
			'perPage'  => 50,
			'pages'    => 0,
			'offset'   => 0,
			'postType' => 'acme_event',
			'order'    => 'asc',
			'orderBy'  => 'title',
			'author'   => '',
			'search'   => '',
			'exclude'  => array(),
			'sticky'   => '',
			'inherit'  => false,
		),
		'className' => $class,
	);
	return '<!-- wp:query ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' -->
<div class="wp-block-query ' . $class . '"><!-- wp:post-template -->
<!-- wp:post-title /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->';
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function squish( string $s ): string {
	$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$s = str_replace( array( "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}" ), array( "'", "'", '"', '"' ), $s );
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/** The loop container with the given class. */
function loop_node( string $html, string $class ): ?\DOMElement {
	$x = dom( $html );
	return $x->query( '//*[' . has_class( 'wp-block-query' ) . ' and ' . has_class( $class ) . ']' )->item( 0 );
}

/** Titles listed by a loop, in order. */
function loop_titles( string $html, string $class ): array {
	$node = loop_node( $html, $class );
	if ( ! $node ) {
		throw new \RuntimeException( "Loop .$class not found" );
	}
	$x   = new \DOMXPath( $node->ownerDocument );
	$out = array();
	foreach ( $x->query( './/*[' . has_class( 'wp-block-post-title' ) . ']', $node ) as $t ) {
		$out[] = squish( $t->textContent );
	}
	return $out;
}

/**
 * Event date blocks inside a loop (in order): ['text' => ..., 'datetime' => ...].
 */
function loop_dates( string $html, string $class ): array {
	$node = loop_node( $html, $class );
	$x    = new \DOMXPath( $node->ownerDocument );
	return event_dates_in( $x, $node );
}

function event_dates_in( \DOMXPath $x, ?\DOMNode $context = null ): array {
	$out   = array();
	$query = './/*[' . has_class( 'wp-block-acme-event-date' ) . ']';
	foreach ( $x->query( $query, $context ) as $el ) {
		$time = 'time' === strtolower( $el->nodeName ) ? $el : $x->query( './/time', $el )->item( 0 );
		$out[] = array(
			'text'     => squish( $el->textContent ),
			'datetime' => $time ? $time->getAttribute( 'datetime' ) : null,
		);
	}
	return $out;
}

/** Pagination info of a loop. */
function loop_pagination( string $html, string $class ): array {
	$node = loop_node( $html, $class );
	$x    = new \DOMXPath( $node->ownerDocument );
	$nums = array();
	foreach ( $x->query( './/*[' . has_class( 'wp-block-query-pagination-numbers' ) . ']//*[' . has_class( 'page-numbers' ) . ']', $node ) as $n ) {
		$t = squish( $n->textContent );
		if ( ctype_digit( $t ) ) {
			$nums[] = (int) $t;
		}
	}
	$next = $x->query( './/a[' . has_class( 'wp-block-query-pagination-next' ) . ']', $node )->item( 0 );
	$prev = $x->query( './/a[' . has_class( 'wp-block-query-pagination-previous' ) . ']', $node )->item( 0 );
	return array(
		'numbers' => $nums,
		'next'    => $next ? html_entity_decode( $next->getAttribute( 'href' ) ) : null,
		'prev'    => $prev ? html_entity_decode( $prev->getAttribute( 'href' ) ) : null,
	);
}

function event_id( string $slug ): int {
	$p = get_page_by_path( $slug, OBJECT, 'acme_event' );
	if ( ! $p ) {
		throw new \RuntimeException( "Seeded event '$slug' not found" );
	}
	return (int) $p->ID;
}

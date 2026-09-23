<?php
/**
 * Helpers for the Acme Related tests.
 */

namespace WPSB\Related;

/** ID of the seeded post "... (n)". */
function seed_id( int $n ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_title LIKE %s", '% (' . $n . ')' ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded post ($n) not found" );
	}
	return $id;
}

function fixture( string $name ): array {
	static $cache = array();
	if ( ! isset( $cache[ $name ] ) ) {
		$cache[ $name ] = json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name . '.json' ), true );
	}
	return $cache[ $name ];
}

/** The related list HTML of a post via the public template tag. */
function list_html( int $post_id, array $args = array() ): string {
	ob_start();
	acme_related_the_list( $post_id, $args );
	return (string) ob_get_clean();
}

/** All related sections in an HTML document, in order. */
function sections( string $html ): array {
	preg_match_all( '#<section class="[^"]*acme-related.*?</section>#s', $html, $m );
	return $m[0];
}

/** Post IDs of the items of a related section, in order. */
function section_ids( string $section ): array {
	preg_match_all( '#data-post-id="(\d+)"#', $section, $m );
	return array_map( 'intval', $m[1] );
}

/** Update the plugin settings (merged into the stored ones). */
function set_settings( array $changes ): void {
	$s = get_option( 'acme_related_settings', array() );
	update_option( 'acme_related_settings', array_merge( is_array( $s ) ? $s : array(), $changes ) );
}

/** Queries caused by related-posts code: everything except a known baseline is too fuzzy, so we compare runs. */
function query_summary( array $queries, int $max = 40 ): string {
	$c = array();
	foreach ( $queries as $q ) {
		$k       = substr( preg_replace( '/\s+/', ' ', preg_replace( '/\d+/', 'N', $q ) ), 0, 160 );
		$c[ $k ] = ( $c[ $k ] ?? 0 ) + 1;
	}
	arsort( $c );
	$out = '';
	foreach ( array_slice( $c, 0, $max, true ) as $k => $v ) {
		$out .= "  {$v}x {$k}\n";
	}
	return $out;
}

/**
 * Base class for tests that fetch pages from the Playground server and read the number of
 * database queries of the request (appended by the grading mu-plugin).
 */
abstract class HttpTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_settings;
	private $saved_ppp;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_settings = get_option( 'acme_related_settings' );
		$this->saved_ppp      = get_option( 'posts_per_page' );
	}

	protected function tearDown(): void {
		update_option( 'acme_related_settings', $this->saved_settings );
		update_option( 'posts_per_page', $this->saved_ppp );
		parent::tearDown();
	}

	/** @return array{count:int, queries:string[], body:string} */
	protected function page( string $path, bool $warm = true ): array {
		if ( $warm ) {
			$this->http( 'GET', $path, array( 'headers' => array( 'X-WPSB-Count-Queries' => '1' ) ) );
		}
		$r = $this->http( 'GET', $path, array( 'headers' => array( 'X-WPSB-Count-Queries' => '1' ) ) );
		$this->assertSame( 200, $r['status'], "GET $path failed: " . substr( $r['body'], 0, 500 ) );
		$this->assertMatchesRegularExpression( '/<!-- wpsb-queries:(\d+) -->/', $r['body'], "No query count in $path" );
		preg_match( '/<!-- wpsb-queries:(\d+) -->/', $r['body'], $m );
		preg_match( '/<!-- wpsb-query-log:([A-Za-z0-9+\/=]*) -->/', $r['body'], $l );
		$queries = isset( $l[1] ) ? (array) json_decode( base64_decode( $l[1] ), true ) : array();
		return array(
			'count'   => (int) $m[1],
			'queries' => $queries,
			'body'    => $r['body'],
		);
	}

	/** Queries of a page with the list on, minus the same page with the list switched off. */
	protected function related_queries( string $path ): array {
		set_settings( array( 'display' => 'none' ) );
		$off = $this->page( $path );
		set_settings( array( 'display' => 'everywhere' ) );
		$on = $this->page( $path );
		return array(
			'delta'   => $on['count'] - $off['count'],
			'on'      => $on,
			'off'     => $off,
			'summary' => "with list: {$on['count']} queries, without: {$off['count']}\n" . query_summary( $on['queries'] ),
		);
	}
}

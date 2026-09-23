<?php
/**
 * Helpers for the Acme Dashboard Stats tests.
 */

namespace WPSB\Stats;

/** Scopes computed in this PHP process (acme_stats_before_compute). */
final class Probe {
	public static array $computes = array();
}
add_action(
	'acme_stats_before_compute',
	static function ( $scope ) {
		Probe::$computes[] = (string) $scope;
	}
);

function uid( string $login ): int {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "User $login not found" );
	}
	return (int) $u->ID;
}

function ext_cache(): bool {
	return (bool) wp_using_ext_object_cache();
}

/**
 * Simulate the start of a new request: the in-memory cache is gone (a persistent object
 * cache keeps its data, like Redis would).
 */
function new_request(): void {
	global $wp_object_cache;
	if ( ext_cache() && method_exists( $wp_object_cache, 'wpsb_new_request' ) ) {
		$wp_object_cache->wpsb_new_request();
	} else {
		wp_cache_flush();
	}
}

/** The stats of a scope, computed independently from the database. */
function expected( int $author = 0 ): array {
	global $wpdb;
	$where = "post_type = 'post' AND post_status = 'publish'" . ( $author ? $wpdb->prepare( ' AND post_author = %d', $author ) : '' );
	$posts = $wpdb->get_results( "SELECT ID, post_author, post_date, post_content, comment_count FROM {$wpdb->posts} WHERE $where ORDER BY ID ASC" );

	$by_author = array();
	$by_month  = array();
	$words     = 0;
	$longest   = null;
	foreach ( $posts as $p ) {
		$by_author[ $p->post_author ] = ( $by_author[ $p->post_author ] ?? 0 ) + 1;
		$m                            = substr( $p->post_date, 0, 7 );
		$by_month[ $m ]               = ( $by_month[ $m ] ?? 0 ) + 1;
		$w                            = str_word_count( wp_strip_all_tags( $p->post_content ) );
		$words                       += $w;
		if ( null === $longest || $w > $longest['words'] ) {
			$longest = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( (int) $p->ID ),
				'words' => $w,
			);
		}
	}
	ksort( $by_month );
	$sort    = static function ( $a, $b ) {
		return $a['count'] !== $b['count'] ? $b['count'] - $a['count'] : strcasecmp( $a['name'], $b['name'] );
	};
	$authors = array();
	foreach ( $by_author as $id => $count ) {
		$authors[] = array(
			'id'    => (int) $id,
			'name'  => (string) $wpdb->get_var( $wpdb->prepare( "SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $id ) ),
			'count' => $count,
		);
	}
	usort( $authors, $sort );

	$ids        = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
	$categories = array();
	$comments   = array(
		'approved'  => 0,
		'pending'   => 0,
		'spam'      => 0,
		'top_posts' => array(),
	);
	if ( $ids ) {
		$in   = implode( ',', $ids );
		$rows = $wpdb->get_results(
			"SELECT t.term_id, t.name, t.slug, COUNT(*) AS c FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			WHERE tr.object_id IN ($in) GROUP BY t.term_id, t.name, t.slug"
		);
		foreach ( $rows as $r ) {
			$categories[] = array(
				'id'    => (int) $r->term_id,
				'name'  => $r->name,
				'slug'  => $r->slug,
				'count' => (int) $r->c,
			);
		}
		usort( $categories, $sort );
		foreach ( array( 'approved' => '1', 'pending' => '0', 'spam' => 'spam' ) as $k => $v ) {
			$comments[ $k ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s AND comment_post_ID IN ($in)", $v ) );
		}
		$commented = array_values( array_filter( $posts, static fn( $p ) => (int) $p->comment_count > 0 ) );
		usort( $commented, static fn( $a, $b ) => (int) $a->comment_count !== (int) $b->comment_count ? (int) $b->comment_count - (int) $a->comment_count : (int) $b->ID - (int) $a->ID );
		$settings = get_option( 'acme_stats_settings' );
		$top      = max( 1, (int) ( is_array( $settings ) && isset( $settings['top_posts'] ) ? $settings['top_posts'] : 5 ) );
		foreach ( array_slice( $commented, 0, $top ) as $p ) {
			$comments['top_posts'][] = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( (int) $p->ID ),
				'count' => (int) $p->comment_count,
			);
		}
	}

	return array(
		'posts'    => array(
			'total'       => count( $posts ),
			'by_author'   => $authors,
			'by_category' => $categories,
			'by_month'    => $by_month,
		),
		'words'    => array(
			'total'   => $words,
			'average' => $posts ? (int) round( $words / count( $posts ) ) : 0,
			'longest' => $longest,
		),
		'comments' => $comments,
	);
}

/** Only the numbers (drops scope, generated_at and any other bookkeeping keys). */
function numbers( array $stats ): array {
	return array(
		'posts'    => $stats['posts'] ?? null,
		'words'    => $stats['words'] ?? null,
		'comments' => $stats['comments'] ?? null,
	);
}

/**
 * Base class: loads stats through the REST endpoint in-process and counts computations.
 */
abstract class StatsTestCase extends \WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		Probe::$computes = array();
	}

	/** GET /acme-stats/v1/summary as a user; returns the response. */
	protected function summary( int $user_id, ?int $author = null ): \WP_REST_Response {
		$prev = get_current_user_id();
		wp_set_current_user( $user_id );
		$res = $this->rest( 'GET', '/acme-stats/v1/summary', null === $author ? array() : array( 'author' => $author ) );
		wp_set_current_user( $prev );
		return $res;
	}

	/** Load stats as a user and return [data, computations, query count]. */
	protected function load( int $user_id, ?int $author = null ): array {
		Probe::$computes = array();
		$run             = $this->count_queries( fn() => $this->summary( $user_id, $author ) );
		$this->assertSame( 200, $run['result']->get_status(), wp_json_encode( $run['result']->get_data() ) );
		return array( $run['result']->get_data(), Probe::$computes, $run['count'], $run['queries'] );
	}

	protected function assertFresh( int $user_id, ?int $author, int $scope_author, string $message = '' ): array {
		list( $data ) = $this->load( $user_id, $author );
		$this->assertSame( expected( $scope_author ), numbers( $data ), $message ?: 'Stats must match the database' );
		return $data;
	}
}

/**
 * Base class for tests against the running site (committed data, other PHP processes).
 */
abstract class HttpStatsTestCase extends StatsTestCase {

	protected bool $use_transactions = false;

	/** @var callable[] */
	protected array $cleanup = array();

	protected const PROBE = WP_CONTENT_DIR . '/wpsb-probe';

	protected function setUp(): void {
		parent::setUp();
		@mkdir( self::PROBE, 0777, true );
		foreach ( array( 'computes.log', 'block', 'release', 'started' ) as $f ) {
			@unlink( self::PROBE . '/' . $f );
		}
	}

	protected function tearDown(): void {
		foreach ( array_reverse( $this->cleanup ) as $fn ) {
			$fn();
		}
		foreach ( array( 'block', 'release', 'started' ) as $f ) {
			@unlink( self::PROBE . '/' . $f );
		}
		parent::tearDown();
	}

	/** Computations logged by all processes since the last reset. */
	protected function computes(): array {
		$f = self::PROBE . '/computes.log';
		return is_file( $f ) ? array_values( array_filter( array_map( 'trim', file( $f ) ) ) ) : array();
	}

	protected function reset_computes(): void {
		@unlink( self::PROBE . '/computes.log' );
	}

	/** GET the summary over HTTP as a user. */
	protected function http_summary( int $user_id, array $query = array() ): array {
		$login = $this->http_login( $user_id );
		$r     = $this->http( 'GET', '/wp-json/acme-stats/v1/summary' . ( $query ? '?' . http_build_query( $query ) : '' ), array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		return $r['json'];
	}
}

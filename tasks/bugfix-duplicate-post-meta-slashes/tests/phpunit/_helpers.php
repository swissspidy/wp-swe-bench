<?php
/**
 * Helpers for the Duplicate Post regression tests.
 *
 * Everything is read straight from the database (committed rows, no object cache), so
 * that the comparison is byte-for-byte on what the plugin actually stored.
 */

namespace WPSB\DuplicatePost;

/** Raw post row. */
function raw_post( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
	return $row ?: null;
}

/**
 * Raw meta of a post: meta_key => list of meta_value strings in insertion order.
 *
 * @param string[] $prefixes Only keys starting with one of these prefixes.
 */
function raw_meta( int $id, array $prefixes = array( '_acme_' ) ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id ASC", $id ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		foreach ( $prefixes as $p ) {
			if ( 0 === strpos( $row['meta_key'], $p ) ) {
				$out[ $row['meta_key'] ][] = $row['meta_value'];
				break;
			}
		}
	}
	ksort( $out );
	return $out;
}

/** Term slugs of a post in a taxonomy (sorted), straight from the relationships table. */
function term_slugs( int $id, string $taxonomy ): array {
	global $wpdb;
	$slugs = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT t.slug FROM {$wpdb->term_relationships} tr
			 JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE tr.object_id = %d AND tt.taxonomy = %s",
			$id,
			$taxonomy
		)
	);
	sort( $slugs );
	return $slugs;
}

/** IDs of the posts whose `_dp_original` points to $id (i.e. copies made by the plugin). */
function copies_of( int $id ): array {
	global $wpdb;
	return array_map(
		'intval',
		$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_dp_original' AND meta_value = %s ORDER BY post_id ASC", (string) $id ) )
	);
}

/** Seeded post by slug and type (any status). */
function seeded( string $slug, string $type = 'post' ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s ORDER BY ID ASC LIMIT 1", $slug, $type ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded $type '$slug' not found" );
	}
	return $id;
}

/**
 * Makes an independent published twin of a seeded post (same content bytes, same raw meta rows,
 * same terms) so that tests which republish over an original don't touch the seeded posts.
 */
function twin_of( int $source_id, array $taxonomies ): int {
	global $wpdb;
	$src = raw_post( $source_id );
	$id  = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => $src['post_type'],
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_title'   => $src['post_title'],
				'post_excerpt' => $src['post_excerpt'],
				'post_content' => $src['post_content'],
				'post_date'    => $src['post_date'],
			)
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new \RuntimeException( $id->get_error_message() );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $id, '\_acme\_%' ) );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id ASC", $source_id ), ARRAY_A );
	foreach ( $rows as $row ) {
		if ( 0 === strpos( $row['meta_key'], '_acme_' ) ) {
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => $id,
					'meta_key'   => $row['meta_key'],
					'meta_value' => $row['meta_value'],
				)
			);
		}
	}
	foreach ( $taxonomies as $tax ) {
		wp_set_object_terms( $id, term_slugs( $source_id, $tax ), $tax );
	}
	wp_cache_flush();
	return (int) $id;
}

/** Query args of a Location header. */
function location_args( array $res ): array {
	$loc = $res['headers']['location'] ?? '';
	$q   = array();
	parse_str( (string) wp_parse_url( $loc, PHP_URL_QUERY ), $q );
	return $q;
}

/**
 * Base class: HTTP flows against the Playground server as a logged-in administrator.
 */
abstract class DuplicatePostTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	protected int $admin;
	protected array $login;

	/** Posts to delete (with all their copies) after the test. */
	protected array $cleanup = array();

	/** Options to restore after the test. */
	private array $saved_options = array();

	protected function setUp(): void {
		parent::setUp();
		$this->admin = $this->create_user( 'administrator' );
		$this->login = $this->http_login( $this->admin );
		foreach ( array( 'post', 'acme_recipe', 'acme_release' ) as $type ) {
			foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
				$this->cleanup[] = (int) $id;
			}
		}
		$this->cleanup = array_values( array_unique( $this->cleanup ) );
	}

	protected function tearDown(): void {
		foreach ( $this->saved_options as $name => $value ) {
			update_option( $name, $value );
		}
		$seeded = array( 'pricing-cheatsheet', 'team-offsite-notes', 'pesto', 'acme-3-2' );
		foreach ( $this->cleanup as $id ) {
			foreach ( copies_of( $id ) as $copy ) {
				wp_delete_post( $copy, true );
			}
			$post = get_post( $id );
			if ( $post && ! in_array( $post->post_name, $seeded, true ) && ! in_array( $post->post_name, array( 'hello-world' ), true ) ) {
				wp_delete_post( $id, true );
			}
		}
		wp_cache_flush();
		parent::tearDown();
	}

	protected function set_option( string $name, $value ): void {
		if ( ! array_key_exists( $name, $this->saved_options ) ) {
			$this->saved_options[ $name ] = get_option( $name );
		}
		update_option( $name, $value );
	}

	/** Runs one of the plugin's admin link actions (as in the row actions / admin bar links). */
	protected function link_action( string $action, int $post_id, ?array $login = null, ?string $nonce = null ): array {
		$login = $login ?? $this->login;
		$nonce = $nonce ?? $this->nonce_for( $login['user_id'], $action . '_' . $post_id, $login['logged_in'] );
		return $this->http(
			'GET',
			'/wp-admin/admin.php?' . http_build_query(
				array(
					'action'   => $action,
					'post'     => $post_id,
					'_wpnonce' => $nonce,
				)
			),
			array( 'login' => $login )
		);
	}

	/** Runs a Duplicate Post link action and returns the ID of the single new copy. */
	protected function duplicate_via( string $action, int $post_id ): int {
		$before = copies_of( $post_id );
		$res    = $this->link_action( $action, $post_id );
		$this->assertContains( $res['status'], array( 301, 302, 303 ), "$action did not redirect: HTTP {$res['status']} " . substr( strip_tags( $res['body'] ), 0, 500 ) );
		$new = array_values( array_diff( copies_of( $post_id ), $before ) );
		$this->assertCount( 1, $new, "$action should create exactly one copy" );
		wp_cache_flush();
		return $new[0];
	}

	protected function expected_meta( int $id ): array {
		$meta = raw_meta( $id );
		foreach ( array_keys( $meta ) as $key ) {
			if ( 0 === strpos( $key, '_acme_cache_' ) ) {
				unset( $meta[ $key ] );
			}
		}
		return $meta;
	}

	protected function assertSameMeta( array $expected, int $copy, string $what ): void {
		$actual = raw_meta( $copy );
		foreach ( $expected as $key => $values ) {
			$this->assertArrayHasKey( $key, $actual, "$what: meta $key missing on the copy" );
			$this->assertSame( $values, $actual[ $key ], "$what: meta $key differs" );
		}
		$this->assertSame( array_keys( $expected ), array_keys( $actual ), "$what: unexpected set of meta keys" );
	}

	protected function assertSameTerms( int $orig, int $copy, array $taxonomies, string $what ): void {
		foreach ( $taxonomies as $tax ) {
			$this->assertNotEmpty( term_slugs( $orig, $tax ), "fixture: $tax terms expected on the original" );
			$this->assertSame( term_slugs( $orig, $tax ), term_slugs( $copy, $tax ), "$what: $tax terms differ" );
		}
	}

	/** Republishes a Rewrite & Republish copy the way the block editor does (REST update to "publish"). */
	protected function republish_via_rest( int $copy, string $rest_base, array $body = array() ): array {
		$res = $this->http(
			'POST',
			"/wp-json/wp/v2/$rest_base/$copy",
			array(
				'login'      => $this->login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array_merge( array( 'status' => 'publish' ), $body ),
			)
		);
		$this->assertSame( 200, $res['status'], 'REST republish failed: ' . substr( $res['body'], 0, 800 ) );
		wp_cache_flush();
		return $res;
	}
}

<?php
/**
 * Helpers for the Acme Migrate search & replace tests.
 *
 * Everything is read straight from the database (no object cache, no unserializing), so the
 * comparisons are byte for byte on what the plugin stored, and reading a value never
 * instantiates a stored object.
 */

namespace WPSB\Migrate;

const OLD_URL = 'http://old-shop.test';
const NEW_URL = 'https://shop.acme.example';

/** Seeded fixtures with their exact expected values (see ../fixtures/build-fixtures.php). */
function fixtures(): array {
	static $fixtures = null;
	if ( null === $fixtures ) {
		$fixtures = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/fixtures.json' ), true );
	}
	return $fixtures;
}

function fixture( string $key ): array {
	foreach ( fixtures() as $f ) {
		if ( $f['key'] === $key ) {
			return $f;
		}
	}
	throw new \RuntimeException( "Unknown fixture $key" );
}

function post_id( string $name, string $type ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s", $name, $type ) );
}

function term_taxonomy_row( string $slug, string $taxonomy ): array {
	global $wpdb;
	return (array) $wpdb->get_row( $wpdb->prepare( "SELECT tt.* FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE t.slug = %s AND tt.taxonomy = %s", $slug, $taxonomy ), ARRAY_A );
}

function user_id( string $login ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $login ) );
}

/** The raw stored value a fixture lives in. */
function stored_value( array $f ): ?string {
	global $wpdb;
	switch ( $f['table'] ) {
		case 'posts':
			return $wpdb->get_var( $wpdb->prepare( "SELECT `{$f['column']}` FROM {$wpdb->posts} WHERE ID = %d", post_id( $f['post_name'], $f['post_type'] ) ) );
		case 'postmeta':
			return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", post_id( $f['post_name'], $f['post_type'] ), $f['meta_key'] ) );
		case 'options':
			return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $f['option_name'] ) );
		case 'term_taxonomy':
			return term_taxonomy_row( $f['term_slug'], $f['taxonomy'] )['description'] ?? null;
		case 'termmeta':
			$term = term_taxonomy_row( $f['term_slug'], $f['taxonomy'] );
			return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s", $term['term_id'], $f['meta_key'] ) );
		case 'users':
			return $wpdb->get_var( $wpdb->prepare( "SELECT user_url FROM {$wpdb->users} WHERE ID = %d", user_id( $f['user_login'] ) ) );
		case 'usermeta':
			return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", user_id( $f['user_login'] ), $f['meta_key'] ) );
		case 'comments':
			return $wpdb->get_var( $wpdb->prepare( "SELECT `{$f['column']}` FROM {$wpdb->comments} WHERE comment_author = %s", $f['comment_author'] ) );
		case 'acme_redirects':
			return $wpdb->get_var( $wpdb->prepare( "SELECT target FROM {$wpdb->prefix}acme_redirects WHERE source = %s", $f['source'] ) );
	}
	throw new \RuntimeException( 'Unknown fixture table ' . $f['table'] );
}

/**
 * Expected report of a run over all tables: "table.column" => [rows, replacements].
 */
function expected_report( bool $include_guids = false ): array {
	global $wpdb;
	$out = array();
	foreach ( fixtures() as $f ) {
		if ( ! $f['count'] || ( ! empty( $f['guid'] ) && ! $include_guids ) ) {
			continue;
		}
		$key = $wpdb->prefix . $f['table'] . '.' . $f['column'];
		$out[ $key ] = array( ( $out[ $key ][0] ?? 0 ) + 1, ( $out[ $key ][1] ?? 0 ) + $f['count'] );
	}
	ksort( $out );
	return $out;
}

/** Report JSON (list of {table, column, rows, replacements}) => same shape as expected_report(). */
function normalize_report( $items ): array {
	$out = array();
	foreach ( (array) $items as $item ) {
		$key         = $item['table'] . '.' . $item['column'];
		$out[ $key ] = array( (int) $item['rows'], (int) $item['replacements'] );
	}
	ksort( $out );
	return $out;
}

function totals( array $report ): array {
	$rows = 0;
	$repl = 0;
	foreach ( $report as $r ) {
		$rows += $r[0];
		$repl += $r[1];
	}
	return array( $rows, $repl );
}

/**
 * Checksum of every row of every table a migration may touch (volatile bookkeeping rows excluded:
 * transients, cron, the plugin's own run history, login sessions).
 */
function db_checksum(): string {
	global $wpdb;
	$tables = array(
		$wpdb->posts              => 'ID',
		$wpdb->postmeta           => 'meta_id',
		$wpdb->options            => 'option_id',
		$wpdb->comments           => 'comment_ID',
		$wpdb->commentmeta        => 'meta_id',
		$wpdb->term_taxonomy      => 'term_taxonomy_id',
		$wpdb->termmeta           => 'meta_id',
		$wpdb->users              => 'ID',
		$wpdb->usermeta           => 'umeta_id',
		$wpdb->links              => 'link_id',
		$wpdb->prefix . 'acme_redirects' => 'id',
	);
	$hash = hash_init( 'sha256' );
	foreach ( $tables as $table => $pk ) {
		$where = '';
		if ( $table === $wpdb->options ) {
			$where = "WHERE option_name NOT LIKE '%transient%' AND option_name NOT IN ('cron','acme_migrate_history')";
		} elseif ( $table === $wpdb->usermeta ) {
			$where = "WHERE meta_key <> 'session_tokens'";
		}
		foreach ( (array) $wpdb->get_results( "SELECT * FROM `$table` $where ORDER BY `$pk`", ARRAY_N ) as $row ) {
			hash_update( $hash, $table . "\0" . implode( "\1", array_map( 'strval', $row ) ) . "\2" );
		}
	}
	return hash_final( $hash );
}

/** Log written whenever a stored Acme_Legacy_Cache_Item object is instantiated. */
function wakeup_log(): string {
	$uploads = wp_upload_dir( null, false );
	return trailingslashit( $uploads['basedir'] ) . 'acme-legacy-cache.log';
}

function refresh(): void {
	global $wpdb;
	$wpdb->flush();
	wp_cache_flush();
}

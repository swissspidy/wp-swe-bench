<?php
/**
 * Helpers for the Acme Activity Log tests.
 */

namespace WPSB\ActivityLog;

const PROBE_ACTION = 'wpsb_probe';

function table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_activity_log';
}

function table_exists(): bool {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', table() ) );
}

function table_count(): int {
	global $wpdb;
	return table_exists() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . table() ) : 0;
}

/**
 * Expected entries (generated from Acme Activity Log 2.3.1 on the seeded site), newest first.
 *
 * @return array<int, array>
 */
function expected_entries(): array {
	static $entries = null;
	if ( null === $entries ) {
		$entries = json_decode( gzdecode( file_get_contents( __DIR__ . '/fixtures/expected-entries.json.gz' ) ), true );
	}
	return $entries;
}

/** Expected entries keyed by ID. */
function expected_by_id(): array {
	static $by_id = null;
	if ( null === $by_id ) {
		$by_id = array();
		foreach ( expected_entries() as $entry ) {
			$by_id[ $entry['id'] ] = $entry;
		}
	}
	return $by_id;
}

/** Expected per-user screen state (2.3.1), keyed by user ID. */
function expected_user_state(): array {
	return json_decode( file_get_contents( __DIR__ . '/fixtures/expected-user-state.json' ), true );
}

/**
 * Reference implementation of the documented query semantics over the fixture.
 */
function expected_query( array $args ): array {
	$args = array_merge(
		array(
			'action'      => '',
			'user_id'     => 0,
			'object_type' => '',
			'object_id'   => 0,
			'since'       => 0,
			'until'       => 0,
			'search'      => '',
			'order'       => 'DESC',
			'per_page'    => 20,
			'page'        => 1,
		),
		$args
	);
	$actions = array_values( array_filter( (array) $args['action'] ) );
	$search  = trim( (string) $args['search'] );
	$out     = array();
	foreach ( expected_entries() as $e ) {
		if ( $actions && ! in_array( $e['action'], $actions, true ) ) {
			continue;
		}
		if ( $args['user_id'] && $e['user_id'] !== (int) $args['user_id'] ) {
			continue;
		}
		if ( '' !== $args['object_type'] && $e['object_type'] !== $args['object_type'] ) {
			continue;
		}
		if ( $args['object_id'] && $e['object_id'] !== (int) $args['object_id'] ) {
			continue;
		}
		if ( $args['since'] && $e['time'] < $args['since'] ) {
			continue;
		}
		if ( $args['until'] && $e['time'] > $args['until'] ) {
			continue;
		}
		if ( '' !== $search && false === stripos( $e['message'], $search ) ) {
			continue;
		}
		$out[] = $e;
	}
	if ( 'ASC' === strtoupper( $args['order'] ) ) {
		$out = array_reverse( $out );
	}
	$total = count( $out );
	if ( $args['per_page'] > 0 ) {
		$out = array_slice( $out, ( max( 1, $args['page'] ) - 1 ) * $args['per_page'], $args['per_page'] );
	}
	return array(
		'entries' => $out,
		'total'   => $total,
	);
}

/** IDs of entries. */
function ids( array $entries ): array {
	return array_map( static fn( $e ) => (int) $e['id'], array_values( $entries ) );
}

/** Autoloaded options as the site loads them. */
function autoloaded_options(): array {
	wp_cache_flush();
	return wp_load_alloptions( true );
}

/** Total size of autoloaded options whose name starts with a prefix. */
function autoload_bytes( string $prefix = '' ): int {
	$bytes = 0;
	foreach ( autoloaded_options() as $name => $value ) {
		if ( '' === $prefix || 0 === strpos( $name, $prefix ) ) {
			$bytes += strlen( $name ) + strlen( (string) $value );
		}
	}
	return $bytes;
}

/** Names of all stored options starting with `acme_activity`. */
function plugin_option_names(): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like( 'acme_activity' ) . '%' ) );
}

/** Seeded user by login. */
function user_id( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		throw new \RuntimeException( "Seeded user '$login' not found" );
	}
	return (int) $user->ID;
}

/** Delete test probe entries through the public API. */
function delete_probes(): void {
	wp_cache_flush();
	$probes = acme_activity_get_entries(
		array(
			'action'   => PROBE_ACTION,
			'per_page' => -1,
		)
	);
	if ( $probes ) {
		acme_activity_delete_entries( ids( $probes ) );
	}
}

/**
 * Run WP-CLI in a subprocess with extra environment variables.
 *
 * @return array{exit:int, stdout:string, stderr:string}
 */
function wp_cli_env( string $args, array $env = array() ): array {
	$full = array_merge( getenv(), $env );
	$proc = proc_open( 'wp ' . $args, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/wordpress', $full );
	$out  = stream_get_contents( $pipes[1] );
	$err  = stream_get_contents( $pipes[2] );
	$code = proc_close( $proc );
	return array(
		'exit'   => $code,
		'stdout' => $out,
		'stderr' => $err,
	);
}

const KILL_MARKER = '/tmp/wpsb-activity-kill.json';

/** Install the mu-plugin that simulates a killed PHP worker (armed via environment variables only). */
function install_kill_switch(): void {
	$code = <<<'PHP'
<?php
/**
 * wp-swe-bench test helper: simulates a PHP worker that is killed while it writes
 * to the activity log table. Only active when WPSB_KILL_* environment variables are set.
 */
$wpsb_kill_after  = getenv( 'WPSB_KILL_AFTER_ROWS' );
$wpsb_kill_before = getenv( 'WPSB_KILL_BEFORE_ROWS' );
if ( false !== $wpsb_kill_after || false !== $wpsb_kill_before ) {
	add_filter(
		'query',
		static function ( $query ) use ( $wpsb_kill_after, $wpsb_kill_before ) {
			static $rows = 0, $armed = false;
			global $wpdb;
			$die = static function ( $why ) use ( &$rows ) {
				file_put_contents( '/tmp/wpsb-activity-kill.json', json_encode( array( 'killed' => true, 'rows' => $rows, 'why' => $why ) ) );
				if ( function_exists( 'posix_kill' ) ) {
					posix_kill( getmypid(), 9 );
				}
				exit( 137 );
			};
			if ( $armed ) {
				$die( 'after' );
			}
			$table = preg_quote( $wpdb->prefix . 'acme_activity_log', '/' );
			if ( preg_match( '/^\s*(INSERT|REPLACE)\b[^(]*?`?\b' . $table . '`?[\s(]/i', $query ) ) {
				$n = preg_match_all( '/\)\s*,\s*\(/', $query ) + 1;
				if ( false !== $wpsb_kill_before && $rows + $n > (int) $wpsb_kill_before ) {
					$die( 'before' );
				}
				$rows += $n;
				file_put_contents( '/tmp/wpsb-activity-kill.json', json_encode( array( 'killed' => false, 'rows' => $rows ) ) );
				if ( false !== $wpsb_kill_after && $rows >= (int) $wpsb_kill_after ) {
					$armed = true;
				}
			}
			return $query;
		}
	);
}
PHP;
	file_put_contents( WPMU_PLUGIN_DIR . '/wpsb-kill-switch.php', $code );
	@unlink( KILL_MARKER );
}

function remove_kill_switch(): void {
	@unlink( WPMU_PLUGIN_DIR . '/wpsb-kill-switch.php' );
	@unlink( KILL_MARKER );
}

function kill_marker(): ?array {
	if ( ! is_file( KILL_MARKER ) ) {
		return null;
	}
	return json_decode( (string) file_get_contents( KILL_MARKER ), true );
}

/**
 * Parse the rows of the log list table: entry IDs in order, plus the classes of each row.
 *
 * @return array{ids:int[], classes:array<int,string>, total:?int}
 */
function parse_list_table( string $html ): array {
	preg_match_all( '/<tr id="activity-entry-(\d+)" class="([^"]*)"/', $html, $m );
	$total = null;
	if ( preg_match( '/<span class="displaying-num">([\d,.\s]+)items?<\/span>/', $html, $t ) ) {
		$total = (int) preg_replace( '/\D/', '', $t[1] );
	}
	return array(
		'ids'     => array_map( 'intval', $m[1] ),
		'classes' => array_combine( array_map( 'intval', $m[1] ), $m[2] ) ?: array(),
		'total'   => $total,
	);
}

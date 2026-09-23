<?php
/**
 * Helpers for the Acme Directory multisite tests.
 *
 * All state is read straight from the database (per-site tables/options/cron), so the
 * tests don't depend on how the plugin is organised internally.
 */

namespace WPSB\Directory;

const PLUGIN   = 'acme-directory/acme-directory.php';
const BASE_URL = 'http://127.0.0.1:9400';
const DB_FILE  = '/wordpress/wp-content/database/.ht.sqlite';
const PRISTINE = '/opt/wpsb/pristine/.ht.sqlite';
const CLEANUP  = 'acme_directory_daily_cleanup';

/** Seeded sites: id => path. Site 5 (/west/) is archived. */
const SITES = array(
	1 => '/',
	2 => '/north/',
	3 => '/south/',
	4 => '/east/',
	5 => '/west/',
	6 => '/harbour/',
);

/**
 * Restore the pristine database in place (same file, so every open connection, in this
 * process and in the Playground server, sees the restored content).
 */
function reset_db(): void {
	global $wpdb;
	$out = array();
	$rc  = 0;
	exec( 'sqlite3 ' . escapeshellarg( DB_FILE ) . ' ' . escapeshellarg( '.restore ' . PRISTINE ) . ' 2>&1', $out, $rc );
	if ( 0 !== $rc ) {
		throw new \RuntimeException( 'DB restore failed: ' . implode( "\n", $out ) );
	}
	$wpdb->flush();
	wp_cache_flush();
	@unlink( WP_CONTENT_DIR . '/wpsb-mail.log' ); // phpcs:ignore
}

function prefix( int $site_id ): string {
	global $wpdb;
	return $wpdb->get_blog_prefix( $site_id );
}

function table_exists( string $table ): bool {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
}

function listings_table( int $site_id ): string {
	return prefix( $site_id ) . 'acme_dir_listings';
}

function categories_table( int $site_id ): string {
	return prefix( $site_id ) . 'acme_dir_categories';
}

/** An option of any site, read from the DB (null when missing). */
function raw_option( int $site_id, string $name ) {
	global $wpdb;
	if ( ! table_exists( prefix( $site_id ) . 'options' ) ) {
		return null;
	}
	$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . prefix( $site_id ) . 'options WHERE option_name = %s', $name ) );
	return null === $value ? null : maybe_unserialize( $value );
}

/** Scheduled events of a site: hook => number of events. */
function cron_hooks( int $site_id ): array {
	$cron = raw_option( $site_id, 'cron' );
	$out  = array();
	if ( ! is_array( $cron ) ) {
		return $out;
	}
	foreach ( $cron as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook => $events ) {
			$out[ $hook ] = ( $out[ $hook ] ?? 0 ) + count( (array) $events );
		}
	}
	return $out;
}

function cron_count( int $site_id, string $hook = CLEANUP ): int {
	return cron_hooks( $site_id )[ $hook ] ?? 0;
}

/** All site IDs of the network. */
function site_ids(): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs} ORDER BY blog_id" ) );
}

function site_path( int $site_id ): string {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT path FROM {$wpdb->blogs} WHERE blog_id = %d", $site_id ) );
}

/** Category slugs of a site (with duplicates). */
function category_slugs( int $site_id ): array {
	global $wpdb;
	if ( ! table_exists( categories_table( $site_id ) ) ) {
		return array();
	}
	return $wpdb->get_col( 'SELECT slug FROM ' . categories_table( $site_id ) . ' ORDER BY slug, id' );
}

/** All rows of one of a site's directory tables. */
function rows( string $table ): array {
	global $wpdb;
	if ( ! table_exists( $table ) ) {
		return array();
	}
	return $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A );
}

/** Everything that makes up "the directory is set up on this site". */
function state( int $site_id ): array {
	$slugs = category_slugs( $site_id );
	return array(
		'listings_table'   => table_exists( listings_table( $site_id ) ),
		'categories_table' => table_exists( categories_table( $site_id ) ),
		'db_version'       => raw_option( $site_id, 'acme_directory_db_version' ),
		'settings'         => raw_option( $site_id, 'acme_directory_settings' ),
		'installed_at'     => raw_option( $site_id, 'acme_directory_installed_at' ),
		'general'          => count( array_keys( $slugs, 'general', true ) ),
		'partners'         => count( array_keys( $slugs, 'network-partners', true ) ),
		'installed_hook'   => raw_option( $site_id, 'acme_network_directory_site' ),
		'cleanup_events'   => cron_count( $site_id ),
	);
}

function is_set_up( int $site_id ): bool {
	$s = state( $site_id );
	return $s['listings_table'] && $s['categories_table'] && 3 === (int) $s['db_version'] && is_array( $s['settings'] );
}

/** Whether the plugin is in the site's own active_plugins list. */
function individually_active( int $site_id ): bool {
	return in_array( PLUGIN, (array) raw_option( $site_id, 'active_plugins' ), true );
}

function network_active(): bool {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s", 'active_sitewide_plugins' ) );
	$v = maybe_unserialize( $v );
	return is_array( $v ) && isset( $v[ PLUGIN ] );
}

/** Byte offset of the debug log (to inspect only what a step added). */
function debug_log_offset(): int {
	$f = WP_CONTENT_DIR . '/debug.log';
	clearstatcache();
	return is_file( $f ) ? (int) filesize( $f ) : 0;
}

function debug_log_since( int $offset ): string {
	$f = WP_CONTENT_DIR . '/debug.log';
	clearstatcache();
	if ( ! is_file( $f ) ) {
		return '';
	}
	return (string) file_get_contents( $f, false, null, $offset );
}

/** Notices/deprecations/DB errors in a debug.log excerpt (ignoring the offline update checks). */
function php_problems( string $log ): array {
	$out = array();
	foreach ( preg_split( '/\R/', $log ) as $line ) {
		if ( '' === trim( $line ) || false !== strpos( $line, 'wp_update_plugins' ) || false !== strpos( $line, 'wp_update_themes' ) || false !== strpos( $line, 'wp_version_check' ) ) {
			continue;
		}
		if ( preg_match( '/PHP (Deprecated|Notice|Warning|Fatal)|WordPress database error|deprecated/i', $line ) ) {
			$out[] = $line;
		}
	}
	return $out;
}

/** All directory tables anywhere in the database. */
function all_directory_tables(): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%' . $wpdb->esc_like( 'acme_dir_' ) . '%' ) );
}

/** Plugin options/transients left on any site, and plugin keys left in the network meta. */
function leftover_options(): array {
	global $wpdb;
	$left = array();
	foreach ( site_ids() as $id ) {
		$names = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM ' . prefix( $id ) . 'options WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s',
				$wpdb->esc_like( 'acme_directory' ) . '%',
				$wpdb->esc_like( '_transient_acme_directory' ) . '%',
				$wpdb->esc_like( '_transient_timeout_acme_directory' ) . '%'
			)
		);
		foreach ( $names as $n ) {
			$left[] = "site $id: $n";
		}
	}
	$meta = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", '%' . $wpdb->esc_like( 'acme_directory' ) . '%', '%' . $wpdb->esc_like( 'acme_dir_' ) . '%' ) );
	foreach ( $meta as $m ) {
		$left[] = "network: $m";
	}
	return $left;
}

/** Plugin cron hooks (anything mentioning acme_dir) left on any site. */
function leftover_events(): array {
	$left = array();
	foreach ( site_ids() as $id ) {
		foreach ( cron_hooks( $id ) as $hook => $n ) {
			if ( false !== stripos( $hook, 'acme_dir' ) ) {
				$left[] = "site $id: $hook";
			}
		}
	}
	return $left;
}

/**
 * Base class: every test starts from the pristine network (as if 2.5.0 was just deployed).
 */
abstract class NetworkTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function setUp(): void {
		reset_db();
		parent::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		wp_cache_flush();
	}

	/** Run WP-CLI and assert success. */
	protected function cli( string $args, string $message = '' ): array {
		$r = $this->wp_cli( $args );
		$this->assertSame( 0, $r['exit'], ( $message ? $message . ': ' : '' ) . "wp $args failed:\n" . $r['stdout'] . $r['stderr'] );
		wp_cache_flush();
		return $r;
	}

	protected function url( string $path ): string {
		return BASE_URL . $path;
	}

	/** Network-activate through Network Admin → Plugins (the real admin flow, over HTTP). */
	protected function network_activate_http(): void {
		$login = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'activate-plugin_' . PLUGIN, $login['logged_in'] );
		$r     = $this->http( 'GET', '/wp-admin/network/plugins.php?action=activate&plugin=' . rawurlencode( PLUGIN ) . '&_wpnonce=' . $nonce, array( 'login' => $login ) );
		$this->assertSame( 302, $r['status'], 'Network activation request failed: ' . substr( $r['body'], 0, 2000 ) );
		$this->assertStringContainsString( 'activate=true', $r['headers']['location'] ?? '', 'Network activation did not succeed (plugin error?): ' . ( $r['headers']['location'] ?? '' ) );
		wp_cache_flush();
		$this->assertTrue( network_active(), 'The plugin should be network-active' );
	}

	/** Network-deactivate through Network Admin → Plugins (HTTP). */
	protected function network_deactivate_http(): void {
		$login = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'deactivate-plugin_' . PLUGIN, $login['logged_in'] );
		$r     = $this->http( 'GET', '/wp-admin/network/plugins.php?action=deactivate&plugin=' . rawurlencode( PLUGIN ) . '&_wpnonce=' . $nonce, array( 'login' => $login ) );
		$this->assertSame( 302, $r['status'], 'Network deactivation request failed: ' . substr( $r['body'], 0, 2000 ) );
		wp_cache_flush();
		$this->assertFalse( network_active(), 'The plugin should no longer be network-active' );
	}

	/** Assert that a site is set up exactly like a single-site activation does it. */
	protected function assertSetUp( int $site_id, string $context = '' ): void {
		$s   = state( $site_id );
		$msg = "site $site_id" . ( $context ? " ($context)" : '' ) . ': ' . wp_json_encode( $s );
		$this->assertTrue( $s['listings_table'], "listings table missing on $msg" );
		$this->assertTrue( $s['categories_table'], "categories table missing on $msg" );
		$this->assertSame( 3, (int) $s['db_version'], "acme_directory_db_version missing on $msg" );
		$this->assertIsArray( $s['settings'], "acme_directory_settings missing on $msg" );
		$this->assertNotEmpty( $s['installed_at'], "acme_directory_installed_at missing on $msg" );
		$this->assertSame( 1, $s['general'], "exactly one General category expected on $msg" );
		$this->assertSame( 1, $s['partners'], "the acme_directory_installed action must have run once, in the site's context, on $msg" );
		$this->assertSame( $site_id, (int) $s['installed_hook'], "the acme_directory_installed action must run in the context of $msg" );
		$this->assertSame( 1, $s['cleanup_events'], "exactly one daily cleanup event expected on $msg" );
	}

	protected function assertNotSetUp( int $site_id, string $context = '' ): void {
		$s = state( $site_id );
		$this->assertFalse( $s['listings_table'] || $s['categories_table'] || null !== $s['db_version'] || 0 !== $s['cleanup_events'], "site $site_id must not be set up" . ( $context ? " ($context)" : '' ) . ': ' . wp_json_encode( $s ) );
	}

	/** Snapshot of the seeded directory data (sites 1 and 3). */
	protected function seeded_data(): array {
		return array(
			'main_listings'    => rows( listings_table( 1 ) ),
			'main_categories'  => rows( categories_table( 1 ) ),
			'south_listings'   => rows( listings_table( 3 ) ),
			'south_categories' => rows( categories_table( 3 ) ),
			'south_settings'   => raw_option( 3, 'acme_directory_settings' ),
			'main_settings'    => raw_option( 1, 'acme_directory_settings' ),
			'south_installed'  => raw_option( 3, 'acme_directory_installed_at' ),
			'main_installed'   => raw_option( 1, 'acme_directory_installed_at' ),
		);
	}
}

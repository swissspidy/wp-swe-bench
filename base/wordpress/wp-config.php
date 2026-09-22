<?php
/**
 * wp-swe-bench wp-config.php
 *
 * The same file is used by WordPress Playground (PHP-WASM) and by native PHP
 * (WP-CLI, PHPUnit), which both load the site from /wordpress. The database is
 * SQLite (wp-content/database/.ht.sqlite) via the sqlite-database-integration
 * drop-in at wp-content/db.php, so the DB_* constants below are unused.
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'wpsb-auth-key' );
define( 'SECURE_AUTH_KEY', 'wpsb-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'wpsb-logged-in-key' );
define( 'NONCE_KEY', 'wpsb-nonce-key' );
define( 'AUTH_SALT', 'wpsb-auth-salt' );
define( 'SECURE_AUTH_SALT', 'wpsb-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'wpsb-logged-in-salt' );
define( 'NONCE_SALT', 'wpsb-nonce-salt' );

$table_prefix = 'wp_';

// SQLite is shared by PHP-WASM (Playground, several worker threads) and native PHP
// (WP-CLI, PHPUnit). WAL mode is not reliable across both runtimes, and switching the
// journal mode on every new connection causes "database is locked" errors under
// concurrency. Pin the rollback journal.
define( 'SQLITE_JOURNAL_MODE', 'DELETE' );

// Fixed site URL so that Playground (HTTP) and native PHP (CLI) agree.
if ( ! defined( 'WP_HOME' ) ) {
	define( 'WP_HOME', 'http://127.0.0.1:9400' );
}
if ( ! defined( 'WP_SITEURL' ) ) {
	define( 'WP_SITEURL', 'http://127.0.0.1:9400' );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '/wordpress/wp-content/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );

// Deterministic, offline-friendly site.
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', '127.0.0.1,localhost' );

/* Add any custom values between this line and the "stop editing" line. */

/* That's all, stop editing! Happy publishing. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';

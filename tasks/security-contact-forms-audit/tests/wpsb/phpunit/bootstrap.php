<?php
/**
 * PHPUnit bootstrap: loads the live /wordpress site (SQLite) in native PHP.
 *
 * WordPress is loaded ONCE for the whole run in a front-end request context, with
 * the task's plugins/themes active as they are on the site. Use WPSB\TestCase.
 */

$_SERVER['HTTP_HOST']       = '127.0.0.1:9400';
$_SERVER['SERVER_NAME']     = '127.0.0.1';
$_SERVER['SERVER_PORT']     = '9400';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['HTTP_USER_AGENT'] = 'wpsb-phpunit';

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once __DIR__ . '/TestCase.php';

// Optional per-task helpers: /tests/phpunit/_helpers.php (or next to the test dir).
foreach ( array( '/tests/phpunit/_helpers.php', getenv( 'WPSB_PHPUNIT_HELPERS' ) ?: '' ) as $helpers ) {
	if ( $helpers && is_file( $helpers ) ) {
		require_once $helpers;
	}
}

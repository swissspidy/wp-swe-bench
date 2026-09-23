<?php
/**
 * Plugin Name:       Acme Library
 * Description:       Books and authors for the Acme Publishing website, with a many-to-many relation between them.
 * Version:           2.3.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Acme Publishing
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-library
 * Domain Path:       /languages
 *
 * @package Acme\Library
 */

namespace Acme\Library;

defined( 'ABSPATH' ) || exit;

const VERSION        = '2.3.1';
const DB_VERSION     = 3;
const PLUGIN_FILE    = __FILE__;
const REST_NAMESPACE = 'acme-library/v1';

require_once __DIR__ . '/includes/class-post-types.php';
require_once __DIR__ . '/includes/class-installer.php';
require_once __DIR__ . '/includes/class-relationships.php';
require_once __DIR__ . '/includes/class-book-counts.php';
require_once __DIR__ . '/includes/class-rest-relations-controller.php';
require_once __DIR__ . '/includes/template-tags.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/admin/class-book-authors-metabox.php';
require_once __DIR__ . '/admin/class-list-columns.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli-command.php';
}

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );

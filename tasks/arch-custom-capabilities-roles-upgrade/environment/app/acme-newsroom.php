<?php
/**
 * Plugin Name:       Acme Newsroom
 * Plugin URI:        https://example.org/acme-newsroom
 * Description:       Stories, desks and the editorial approval workflow for the Acme Daily newsroom.
 * Version:           2.3.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Acme Digital
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-newsroom
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

const VERSION = '2.3.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-story-post-type.php';
require_once __DIR__ . '/includes/class-approval.php';
require_once __DIR__ . '/includes/class-rest-approval-controller.php';
require_once __DIR__ . '/includes/class-freelancers.php';
require_once __DIR__ . '/includes/class-credits.php';
require_once __DIR__ . '/includes/class-plugin.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin/class-stories-list.php';
	require_once __DIR__ . '/includes/admin/class-approval-meta-box.php';
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Activation: register the post type so rewrite rules include it, remember the version.
 */
function activate() {
	( new Story_Post_Type() )->register_post_type();
	flush_rewrite_rules();
	update_option( 'acme_newsroom_version', VERSION );
}

add_action( 'plugins_loaded', array( Plugin::class, 'instance' ) );

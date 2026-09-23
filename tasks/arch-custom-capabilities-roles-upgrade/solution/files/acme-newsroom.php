<?php
/**
 * Plugin Name:       Acme Newsroom
 * Plugin URI:        https://example.org/acme-newsroom
 * Description:       Stories, desks and the editorial approval workflow for the Acme Daily newsroom.
 * Version:           3.0.0
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

const VERSION = '3.0.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-story-post-type.php';
require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-upgrader.php';
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
 * Activation: capabilities/roles (fresh install or upgrade), rewrite rules.
 */
function activate() {
	if ( false === get_option( Upgrader::VERSION_OPTION ) ) {
		// Fresh install: nothing to migrate, but roles need the story capabilities.
		update_option( Upgrader::VERSION_OPTION, '0' );
	}
	( new Upgrader() )->maybe_upgrade();
	( new Story_Post_Type() )->register_post_type();
	flush_rewrite_rules();
}

add_action( 'plugins_loaded', array( Plugin::class, 'instance' ) );

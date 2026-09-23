<?php
/**
 * Plugin Name:       Acme Members
 * Description:       Member directory for the Acme Makers community: profile fields, per-profile and per-field visibility, member profile pages, directory block/shortcode.
 * Version:           2.3.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-members
 * Domain Path:       /languages
 *
 * @package Acme\Members
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_MEMBERS_VERSION', '2.3.0' );
define( 'ACME_MEMBERS_FILE', __FILE__ );
define( 'ACME_MEMBERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_MEMBERS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_MEMBERS_DIR . 'includes/class-fields.php';
require_once ACME_MEMBERS_DIR . 'includes/class-visibility.php';
require_once ACME_MEMBERS_DIR . 'includes/class-connections.php';
require_once ACME_MEMBERS_DIR . 'includes/class-members.php';
require_once ACME_MEMBERS_DIR . 'includes/class-profile-page.php';
require_once ACME_MEMBERS_DIR . 'includes/class-directory.php';
require_once ACME_MEMBERS_DIR . 'includes/class-rest-fields.php';
require_once ACME_MEMBERS_DIR . 'includes/class-feeds.php';
require_once ACME_MEMBERS_DIR . 'includes/class-sitemaps.php';
require_once ACME_MEMBERS_DIR . 'includes/class-admin.php';
require_once ACME_MEMBERS_DIR . 'includes/class-profile-service.php';
require_once ACME_MEMBERS_DIR . 'includes/class-rest-controller.php';
require_once ACME_MEMBERS_DIR . 'includes/class-profile-form.php';
require_once ACME_MEMBERS_DIR . 'includes/template-tags.php';
require_once ACME_MEMBERS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Acme\\Members\\Members', 'activate' ) );

Acme\Members\Plugin::instance()->boot();

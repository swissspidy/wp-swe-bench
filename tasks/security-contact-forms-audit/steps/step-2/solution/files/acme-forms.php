<?php
/**
 * Plugin Name:       Acme Forms
 * Plugin URI:        https://example.org/acme-forms
 * Description:       Contact and application forms with a submissions inbox, CSV export, file uploads and e-mail notifications.
 * Version:           2.5.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-forms
 * Domain Path:       /languages
 *
 * @package Acme\Forms
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_FORMS_VERSION', '2.5.0' );
define( 'ACME_FORMS_FILE', __FILE__ );
define( 'ACME_FORMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_FORMS_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_FORMS_DIR . 'includes/functions.php';
require_once ACME_FORMS_DIR . 'includes/class-installer.php';
require_once ACME_FORMS_DIR . 'includes/class-forms.php';
require_once ACME_FORMS_DIR . 'includes/class-submissions.php';
require_once ACME_FORMS_DIR . 'includes/class-uploads.php';
require_once ACME_FORMS_DIR . 'includes/class-audit-log.php';
require_once ACME_FORMS_DIR . 'includes/class-formatter.php';
require_once ACME_FORMS_DIR . 'includes/class-renderer.php';
require_once ACME_FORMS_DIR . 'includes/class-submission-handler.php';
require_once ACME_FORMS_DIR . 'includes/class-notifications.php';
require_once ACME_FORMS_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once ACME_FORMS_DIR . 'includes/admin/class-admin.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-submissions-page.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-settings-page.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-export.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-dashboard-widget.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-downloads.php';
	require_once ACME_FORMS_DIR . 'includes/admin/class-audit-log-page.php';
}

register_activation_hook( __FILE__, array( 'Acme\Forms\Installer', 'activate' ) );

Acme\Forms\Plugin::instance()->boot();

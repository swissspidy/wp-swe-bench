<?php
/**
 * Plugin Name:       Acme Tasks
 * Description:       Shared to-do lists for the Acme team, with the REST API used by the Acme mobile app.
 * Version:           1.6.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Acme Corp
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-tasks
 * Domain Path:       /languages
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

const VERSION        = '1.6.0';
const DB_VERSION     = 4;
const PLUGIN_FILE    = __FILE__;
const REST_NAMESPACE = 'acme-tasks/v1';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-installer.php';
require_once __DIR__ . '/includes/class-post-type.php';
require_once __DIR__ . '/includes/class-access.php';
require_once __DIR__ . '/includes/class-task-repository.php';
require_once __DIR__ . '/includes/class-activity.php';
require_once __DIR__ . '/includes/class-lists-controller.php';
require_once __DIR__ . '/includes/class-tasks-controller.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/admin/class-admin-page.php';

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );

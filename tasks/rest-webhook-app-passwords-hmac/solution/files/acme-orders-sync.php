<?php
/**
 * Plugin Name:       Acme Orders Sync
 * Description:       Receives order webhooks from the Acme Shop storefronts and keeps a local copy of every order for fulfilment and accounting.
 * Version:           1.4.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Acme Corp
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-orders-sync
 * Domain Path:       /languages
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

const VERSION        = '1.4.0';
const PLUGIN_FILE    = __FILE__;
const REST_NAMESPACE = 'acme-orders/v1';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-redactor.php';
require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-installer.php';
require_once __DIR__ . '/includes/class-order-post-type.php';
require_once __DIR__ . '/includes/class-order-processor.php';
require_once __DIR__ . '/includes/class-delivery-store.php';
require_once __DIR__ . '/includes/class-signature.php';
require_once __DIR__ . '/includes/class-rate-limiter.php';
require_once __DIR__ . '/includes/class-queue.php';
require_once __DIR__ . '/includes/class-webhook-controller.php';
require_once __DIR__ . '/includes/class-deliveries-controller.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/admin/class-settings-page.php';
require_once __DIR__ . '/admin/class-log-page.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli-command.php';
}

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );

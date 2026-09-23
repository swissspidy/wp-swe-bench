<?php
/**
 * Plugin Name:       Acme Catalog
 * Description:       Product catalog: a "Product" post type, product categories and a filterable "Product grid" block.
 * Version:           2.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Inc.
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-catalog
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

const VERSION = '2.1.0';
const FILE    = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-post-types.php';
require_once __DIR__ . '/includes/class-products.php';
require_once __DIR__ . '/includes/class-grid.php';
require_once __DIR__ . '/includes/class-block.php';
require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-plugin.php';

Plugin::instance()->boot();

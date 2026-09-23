<?php
/**
 * Plugin Name:       Acme SEO
 * Description:       Titles, meta descriptions, robots rules, social tags, sitemaps and site verification for Acme sites.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Acme Corp
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-seo
 * Domain Path:       /languages
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

const VERSION     = '2.0.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/class-legacy-options.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-options.php';
require_once __DIR__ . '/includes/class-upgrader.php';
require_once __DIR__ . '/includes/class-post-meta.php';
require_once __DIR__ . '/includes/class-title.php';
require_once __DIR__ . '/includes/class-head.php';
require_once __DIR__ . '/includes/class-robots.php';
require_once __DIR__ . '/includes/class-sitemap.php';
require_once __DIR__ . '/includes/class-editor.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/admin/class-settings-page.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );

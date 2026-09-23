<?php
/**
 * Plugin Name:       Acme FAQ
 * Description:       Collapsible FAQ (accordion) block with FAQPage structured data.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Inc.
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-faq
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

const VERSION = '2.0.0';
const FILE    = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-schema.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-plugin.php';

Plugin::instance()->boot();

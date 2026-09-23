<?php
/**
 * Plugin Name:       Acme TOC
 * Description:       A table of contents block for long-form articles and documentation.
 * Version:           1.4.2
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Inc.
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-toc
 * Domain Path:       /languages
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.4.2';
const FILE    = __FILE__;

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-block.php';
require_once __DIR__ . '/includes/class-plugin.php';

Plugin::instance()->boot();

<?php
/**
 * Plugin Name:       Acme Courses
 * Plugin URI:        https://academy.example.org/
 * Description:       Course catalog for Acme Academy: courses, topics, pricing, durations and enrollment links.
 * Version:           1.6.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Acme Academy
 * License:           GPL-2.0-or-later
 * Text Domain:       acme-courses
 * Domain Path:       /languages
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.6.2';

define( 'ACME_COURSES_FILE', __FILE__ );
define( 'ACME_COURSES_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_COURSES_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_COURSES_DIR . 'includes/class-course.php';
require_once ACME_COURSES_DIR . 'includes/class-settings.php';
require_once ACME_COURSES_DIR . 'includes/functions.php';
require_once ACME_COURSES_DIR . 'includes/template-tags.php';
require_once ACME_COURSES_DIR . 'includes/class-post-types.php';
require_once ACME_COURSES_DIR . 'includes/class-content-filter.php';
require_once ACME_COURSES_DIR . 'includes/class-template-loader.php';
require_once ACME_COURSES_DIR . 'includes/class-blocks.php';
require_once ACME_COURSES_DIR . 'includes/class-plugin.php';

Plugin::instance()->boot();

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

<?php
/**
 * Uninstall: remove settings. Course posts are kept on purpose.
 *
 * @package Acme\Courses
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_courses_settings' );

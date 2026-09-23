<?php
/**
 * Uninstall: events (posts) and their meta are kept on purpose.
 *
 * @package Acme\Events
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

flush_rewrite_rules();

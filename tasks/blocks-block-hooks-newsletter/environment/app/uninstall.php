<?php
/**
 * Uninstall: remove options. Subscribers are kept on purpose (legal retention);
 * drop the table manually if needed.
 *
 * @package Acme\Newsletter
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_newsletter_settings' );
delete_option( 'acme_newsletter_db_version' );

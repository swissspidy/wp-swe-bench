<?php
/**
 * Uninstall: remove options and the role. Products are kept.
 *
 * @package Acme\Importer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_importer_settings' );
delete_option( 'acme_importer_last_run' );
delete_option( 'acme_importer_db_version' );
remove_role( 'acme_shop_manager' );

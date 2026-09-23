<?php
/**
 * Uninstall Acme Link Previews.
 *
 * @package Acme\LinkPreviews
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acme_lp_cache' );

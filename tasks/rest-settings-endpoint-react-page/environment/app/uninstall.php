<?php
/**
 * Remove all settings.
 *
 * @package Acme\SEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'title_separator', 'home_title', 'home_description', 'noindex_post_types', 'noindex_archives', 'og_enabled', 'og_default_image', 'twitter_handle', 'social_profiles', 'sitemap_enabled', 'sitemap_exclude', 'verification', 'version' ) as $acme_seo_key ) {
	delete_option( 'acme_seo_' . $acme_seo_key );
}

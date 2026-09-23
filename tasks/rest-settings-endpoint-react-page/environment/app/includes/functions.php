<?php
/**
 * Public API for themes and other plugins.
 *
 * @package Acme\SEO
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'acme_seo_get_option' ) ) {
	/**
	 * Get an Acme SEO setting (normalized).
	 *
	 * Keys: title_separator (string), home_title (string template), home_description (string),
	 * noindex_post_types (string[]), noindex_archives (array{author:bool,date:bool,tag:bool}),
	 * og_enabled (bool), og_default_image (int attachment ID, 0 = none), twitter_handle
	 * (string without "@"), social_profiles (array{facebook,instagram,linkedin,youtube: string}),
	 * sitemap_enabled (bool), sitemap_exclude (int[]), verification (array{google,bing: string}).
	 *
	 * @since 1.3.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Returned for unknown keys.
	 * @return mixed
	 */
	function acme_seo_get_option( $key, $default = null ) {
		$value = \Acme\SEO\Options::get( $key );
		return null === $value ? $default : $value;
	}
}

if ( ! function_exists( 'acme_seo_title_template' ) ) {
	/**
	 * Render a title template with %%sitename%%, %%tagline%% and %%sep%% placeholders.
	 *
	 * @since 1.4.0
	 *
	 * @param string $template Template.
	 * @return string
	 */
	function acme_seo_title_template( $template ) {
		return \Acme\SEO\Title::render_template( $template );
	}
}

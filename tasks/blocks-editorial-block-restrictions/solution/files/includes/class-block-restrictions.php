<?php
/**
 * Enforces the editorial rules in the block editor.
 *
 * - Allowed blocks per post type and role are passed to the editor as its
 *   allowed block types, so they apply to the inserter, the slash inserter,
 *   paste, transforms and patterns alike. Blocks that are already in a post
 *   stay registered, so existing content keeps loading and rendering.
 * - Disabled design tools switch off the custom color/gradient pickers and
 *   custom font sizes in the editor settings of the user.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Editor restrictions.
 */
class Block_Restrictions {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_filter( 'allowed_block_types_all', array( $this, 'allowed_block_types' ), 20, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'editor_settings' ), 20, 2 );
		// The editor also reads the theme's global settings (theme.json) through the REST API.
		add_filter( 'wp_theme_json_data_theme', array( $this, 'theme_json' ) );
	}

	/**
	 * Theme.json settings overrides for the design tools switched off for the current user.
	 *
	 * @return array Theme.json "settings" fragment (empty when nothing is switched off).
	 */
	public static function settings_overrides() {
		$tools    = Editorial_Rules::disabled_design_tools( wp_get_current_user() );
		$settings = array();
		if ( in_array( 'custom_colors', $tools, true ) ) {
			$settings['color'] = array(
				'custom'         => false,
				'customGradient' => false,
				'customDuotone'  => false,
			);
		}
		if ( in_array( 'custom_font_sizes', $tools, true ) ) {
			$settings['typography'] = array( 'customFontSize' => false );
		}
		return $settings;
	}

	/**
	 * Switch off custom colors / font sizes in the theme's global settings for the current user.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function theme_json( $theme_json ) {
		if ( ! did_action( 'set_current_user' ) ) {
			return $theme_json;
		}
		$settings = self::settings_overrides();
		if ( ! $settings ) {
			return $theme_json;
		}
		return $theme_json->update_with(
			array(
				'version'  => \WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => $settings,
			)
		);
	}

	/**
	 * Allowed block types for the post being edited.
	 *
	 * @param bool|string[]            $allowed Allowed block types (true = all).
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|string[]
	 */
	public function allowed_block_types( $allowed, $context ) {
		if ( ! $context instanceof \WP_Block_Editor_Context || empty( $context->post ) ) {
			return $allowed;
		}
		$ours = Editorial_Rules::allowed_blocks( get_post_type( $context->post ), wp_get_current_user() );
		if ( null === $ours ) {
			return $allowed;
		}
		if ( is_array( $allowed ) ) {
			return array_values( array_intersect( $allowed, $ours ) );
		}
		return false === $allowed ? false : $ours;
	}

	/**
	 * Switch off custom colors / font sizes for the current user.
	 *
	 * @param array                    $settings Editor settings.
	 * @param \WP_Block_Editor_Context $context  Editor context.
	 * @return array
	 */
	public function editor_settings( $settings, $context ) {
		$tools = Editorial_Rules::disabled_design_tools( wp_get_current_user() );
		if ( ! $tools ) {
			return $settings;
		}

		$features = isset( $settings['__experimentalFeatures'] ) && is_array( $settings['__experimentalFeatures'] ) ? $settings['__experimentalFeatures'] : array();

		$paths = array();
		if ( in_array( 'custom_colors', $tools, true ) ) {
			$settings['disableCustomColors']    = true;
			$settings['disableCustomGradients'] = true;
			$paths[]                            = array( 'color', 'custom' );
			$paths[]                            = array( 'color', 'customGradient' );
			$paths[]                            = array( 'color', 'customDuotone' );
		}
		if ( in_array( 'custom_font_sizes', $tools, true ) ) {
			$settings['disableCustomFontSizes'] = true;
			$paths[]                            = array( 'typography', 'customFontSize' );
		}

		foreach ( $paths as $path ) {
			$features = self::set_path( $features, $path );
			// Per-block settings in theme.json override the global ones.
			if ( isset( $features['blocks'] ) && is_array( $features['blocks'] ) ) {
				foreach ( $features['blocks'] as $block => $block_features ) {
					if ( is_array( $block_features ) && isset( $block_features[ $path[0] ] ) && array_key_exists( $path[1], (array) $block_features[ $path[0] ] ) ) {
						$features['blocks'][ $block ] = self::set_path( $block_features, $path );
					}
				}
			}
		}
		$settings['__experimentalFeatures'] = $features;

		return $settings;
	}

	/**
	 * Set $array[a][b] = false.
	 *
	 * @param array    $data Array.
	 * @param string[] $path Two keys.
	 * @return array
	 */
	private static function set_path( array $data, array $path ) {
		if ( ! isset( $data[ $path[0] ] ) || ! is_array( $data[ $path[0] ] ) ) {
			$data[ $path[0] ] = array();
		}
		$data[ $path[0] ][ $path[1] ] = false;
		return $data;
	}
}

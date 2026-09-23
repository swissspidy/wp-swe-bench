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
		// Everything that the old CSS workaround used to hide is now disallowed.
		$hidden = Editorial_Rules::hidden_blocks( get_post_type( $context->post ), wp_get_current_user() );
		if ( ! $hidden ) {
			return $allowed;
		}
		$all = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
		return array_values( array_diff( $all, $hidden ) );
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

		if ( in_array( 'custom_colors', $tools, true ) ) {
			$settings['disableCustomColors']    = true;
			$settings['disableCustomGradients'] = true;
		}
		if ( in_array( 'custom_font_sizes', $tools, true ) ) {
			$settings['disableCustomFontSizes'] = true;
		}

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

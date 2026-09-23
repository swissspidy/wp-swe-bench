<?php
/**
 * Editor workarounds for the editorial rules.
 *
 * TODO(newsroom#212): these only hide things. Blocks can still be added with
 * the slash inserter, by pasting, through transforms and patterns, and the
 * press release structure can be broken with keyboard shortcuts.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * CSS/JS that hides disallowed blocks and design tools in the editor.
 */
class Editor_Workarounds {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Current post type in the block editor screen.
	 *
	 * @return string
	 */
	private function current_post_type() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $screen->post_type ? $screen->post_type : '';
	}

	/**
	 * Enqueue the workaround CSS + JS.
	 */
	public function enqueue() {
		$post_type = $this->current_post_type();
		$user      = wp_get_current_user();
		if ( ! $post_type || ! $user->exists() ) {
			return;
		}

		$css = array();

		// 1. Hide disallowed blocks in the inserter.
		foreach ( Editorial_Rules::hidden_blocks( $post_type, $user ) as $name ) {
			$slug  = str_replace( '/', '-', preg_replace( '#^core/#', '', $name ) );
			$css[] = '.editor-block-list-item-' . $slug . ',.block-editor-block-types-list__list-item:has(.editor-block-list-item-' . $slug . ')';
		}
		$css = $css ? array( implode( ',', $css ) . '{display:none!important}' ) : array();

		// 2. Hide the custom color / font size pickers.
		$tools = Editorial_Rules::disabled_design_tools( $user );
		if ( in_array( 'custom_colors', $tools, true ) ) {
			$css[] = '.components-color-palette__custom-color-wrapper,.components-circular-option-picker__custom-clear-wrapper .components-color-palette__custom-color-button{display:none!important}';
		}
		if ( in_array( 'custom_font_sizes', $tools, true ) ) {
			$css[] = '.components-font-size-picker__custom-size-control,.components-font-size-picker__header__hint + button{display:none!important}';
		}

		// 3. Press releases: hide movers and the remove menu item.
		if ( Press_Releases::POST_TYPE === $post_type ) {
			$css[] = '.block-editor-block-mover,.block-editor-block-settings-menu__block-remove{display:none!important}';
		}

		wp_register_style( 'acme-newsroom-workarounds', false, array(), ACME_NEWSROOM_VERSION );
		wp_enqueue_style( 'acme-newsroom-workarounds' );
		wp_add_inline_style( 'acme-newsroom-workarounds', implode( "\n", $css ) );

		wp_enqueue_script(
			'acme-newsroom-workarounds',
			ACME_NEWSROOM_URL . 'assets/editor-workarounds.js',
			array( 'wp-blocks', 'wp-data', 'wp-dom-ready', 'wp-editor', 'wp-block-editor' ),
			ACME_NEWSROOM_VERSION,
			true
		);
		wp_add_inline_script(
			'acme-newsroom-workarounds',
			'window.acmeNewsroomWorkarounds = ' . wp_json_encode(
				array(
					'postType' => $post_type,
					'template' => Press_Releases::POST_TYPE === $post_type ? Press_Releases::template() : null,
				)
			) . ';',
			'before'
		);
	}
}

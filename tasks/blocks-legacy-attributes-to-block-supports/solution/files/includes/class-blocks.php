<?php
/**
 * Block registration and server rendering.
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/notice-box and acme/stat.
 *
 * Both blocks are rendered on the server from their attributes. Blocks saved
 * by 1.x are converted to the block supports representation right before
 * they render, so posts nobody re-saves get the same classes and styles
 * (and therefore the theme's design tokens) as converted ones.
 */
class Blocks {

	/**
	 * Blocks shipped by the plugin (directory names in build/).
	 *
	 * @var string[]
	 */
	const BLOCKS = array( 'notice-box', 'stat' );

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		// Before core's block supports (e.g. block style variations) read the attributes.
		add_filter( 'render_block_data', array( $this, 'migrate_legacy_block' ), 5 );
	}

	/**
	 * Register the blocks from their compiled block.json files.
	 */
	public function register() {
		foreach ( self::BLOCKS as $dir ) {
			$block = register_block_type(
				ACME_CONTENT_BLOCKS_DIR . 'build/' . $dir,
				array( 'render_callback' => array( $this, 'notice-box' === $dir ? 'render_notice' : 'render_stat' ) )
			);
			if ( ! $block ) {
				continue;
			}
			$handle = $block->editor_script_handles[0] ?? '';
			if ( $handle ) {
				wp_set_script_translations( $handle, 'acme-content-blocks', ACME_CONTENT_BLOCKS_DIR . 'languages' );
			}
		}

		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_settings_script' ) );
	}

	/**
	 * Expose tones and the theme presets to the editor scripts.
	 */
	public function editor_settings_script() {
		$settings = wp_json_encode( $this->editor_settings() );
		foreach ( array( 'acme-notice-box-editor-script', 'acme-stat-editor-script' ) as $handle ) {
			wp_add_inline_script( $handle, 'window.acmeContentBlocks = ' . $settings . ';', 'before' );
		}
	}

	/**
	 * Settings exposed to the editor scripts.
	 *
	 * @return array
	 */
	public function editor_settings() {
		$tones = array();
		foreach ( acme_content_blocks_tones() as $slug => $label ) {
			$tones[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}
		return array(
			'tones'   => $tones,
			'presets' => Presets::for_editor(),
		);
	}

	/**
	 * Convert 1.x attributes before the block renders (block supports read
	 * the parsed block, so this must happen at the parsed block level).
	 *
	 * @param array $parsed_block Parsed block.
	 * @return array
	 */
	public function migrate_legacy_block( $parsed_block ) {
		$name = $parsed_block['blockName'] ?? '';
		if ( 'acme/notice-box' !== $name && 'acme/stat' !== $name ) {
			return $parsed_block;
		}
		$attrs                 = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : array();
		$parsed_block['attrs'] = 'acme/stat' === $name ? Migrator::migrate_stat( $attrs ) : Migrator::migrate_notice( $attrs );
		return $parsed_block;
	}

	/**
	 * Render a notice box.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Saved markup (1.x or 2.x).
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public function render_notice( $attributes, $content, $block ) {
		$tone = isset( $attributes['tone'] ) ? sanitize_key( $attributes['tone'] ) : 'info';
		if ( ! array_key_exists( $tone, acme_content_blocks_tones() ) ) {
			$tone = 'info';
		}

		$inner = '';
		foreach ( $block->inner_blocks as $inner_block ) {
			$inner .= $inner_block->render();
		}

		$extra = array(
			'class' => 'is-tone-' . $tone,
			'role'  => 'note',
		);
		$id    = $this->anchor( $content );
		if ( '' !== $id ) {
			$extra['id'] = $id;
		}

		$icon = ( $attributes['showIcon'] ?? true ) ? '<span class="wp-block-acme-notice-box__icon" aria-hidden="true"></span>' : '';

		return sprintf(
			'<div %1$s>%2$s<div class="wp-block-acme-notice-box__body">%3$s</div></div>',
			get_block_wrapper_attributes( $extra ),
			$icon,
			$inner
		);
	}

	/**
	 * Render a statistic.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Saved markup.
	 * @param \WP_Block $block      Block instance.
	 * @return string
	 */
	public function render_stat( $attributes, $content, $block ) {
		$extra     = array();
		$alignment = $attributes['alignment'] ?? 'left';
		if ( in_array( $alignment, array( 'center', 'right' ), true ) ) {
			$extra['class'] = 'has-text-align-' . $alignment;
		}
		$id = $this->anchor( $content );
		if ( '' !== $id ) {
			$extra['id'] = $id;
		}

		return sprintf(
			'<div %1$s><span class="wp-block-acme-stat__value">%2$s</span><span class="wp-block-acme-stat__label">%3$s</span></div>',
			get_block_wrapper_attributes( $extra ),
			wp_kses_post( (string) ( $attributes['value'] ?? '' ) ),
			wp_kses_post( (string) ( $attributes['label'] ?? '' ) )
		);
	}

	/**
	 * The HTML anchor is stored in the saved markup, not in the block comment.
	 *
	 * @param string $content Saved markup.
	 * @return string
	 */
	private function anchor( $content ) {
		$processor = new \WP_HTML_Tag_Processor( (string) $content );
		if ( ! $processor->next_tag() ) {
			return '';
		}
		$id = $processor->get_attribute( 'id' );
		return is_string( $id ) ? $id : '';
	}
}

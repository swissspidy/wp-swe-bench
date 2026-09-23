<?php
/**
 * "Glossary index" block (server-rendered A–Z list).
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the acme/glossary-index block.
 */
class Index_Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the block from its built block.json.
	 */
	public function register() {
		register_block_type(
			ACME_GLOSSARY_DIR . 'build/glossary-index',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$letters = ! isset( $attributes['showLetters'] ) || (bool) $attributes['showLetters'];
		return sprintf(
			'<div %s>%s</div>',
			get_block_wrapper_attributes(),
			Shortcodes::render_index( $letters )
		);
	}
}

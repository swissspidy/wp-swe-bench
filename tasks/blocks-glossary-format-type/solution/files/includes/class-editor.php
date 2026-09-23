<?php
/**
 * Block editor integration: the "Glossary term" text format.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the format script and the data it needs.
 */
class Editor {

	const HANDLE = 'acme-glossary-format';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Published terms for converting [glossary] shortcodes in the editor.
	 *
	 * @return array[] List of { id, slug, title }.
	 */
	public static function terms_for_editor() {
		$terms = array();
		foreach ( Term_Cache::map()['terms'] as $term ) {
			$terms[] = array(
				'id'    => (int) $term['id'],
				'slug'  => (string) $term['slug'],
				'title' => (string) $term['title'],
			);
		}
		return $terms;
	}

	/**
	 * Enqueue the format script in the block editor.
	 */
	public function enqueue() {
		$asset_file = ACME_GLOSSARY_DIR . 'build/glossary-format/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			self::HANDLE,
			ACME_GLOSSARY_URL . 'build/glossary-format/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::HANDLE, 'acme-glossary', ACME_GLOSSARY_DIR . 'languages' );
		wp_add_inline_script(
			self::HANDLE,
			'window.acmeGlossary = ' . wp_json_encode( array( 'terms' => self::terms_for_editor() ) ) . ';',
			'before'
		);

		if ( file_exists( ACME_GLOSSARY_DIR . 'build/glossary-format/index.css' ) ) {
			wp_enqueue_style( self::HANDLE, ACME_GLOSSARY_URL . 'build/glossary-format/index.css', array(), $asset['version'] );
		}
	}
}

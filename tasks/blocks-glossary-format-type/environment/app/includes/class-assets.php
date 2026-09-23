<?php
/**
 * Front-end assets (tooltip script + styles).
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the front-end tooltip assets.
 */
class Assets {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
	}

	/**
	 * Register the assets (enqueued on demand).
	 */
	public function register() {
		wp_register_style( 'acme-glossary', ACME_GLOSSARY_URL . 'assets/glossary.css', array(), ACME_GLOSSARY_VERSION );
		wp_register_script(
			'acme-glossary-tooltip',
			ACME_GLOSSARY_URL . 'assets/tooltip.js',
			array(),
			ACME_GLOSSARY_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueue the tooltip assets (called when a term is rendered).
	 */
	public static function enqueue_front() {
		if ( ! did_action( 'wp_enqueue_scripts' ) ) {
			// Rendered outside a page request (REST, feeds): nothing to enqueue.
			return;
		}
		wp_enqueue_style( 'acme-glossary' );
		wp_enqueue_script( 'acme-glossary-tooltip' );
	}
}

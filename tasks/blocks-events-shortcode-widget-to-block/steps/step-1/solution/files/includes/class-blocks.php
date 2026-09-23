<?php
/**
 * Block registration.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks from their compiled block.json files in build/.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register blocks.
	 */
	public function register() {
		foreach ( array( 'event-details', 'upcoming-events' ) as $dir ) {
			$block = register_block_type( ACME_EVENTS_DIR . 'build/' . $dir );
			if ( $block && ! empty( $block->editor_script_handles[0] ) ) {
				wp_set_script_translations( $block->editor_script_handles[0], 'acme-events', ACME_EVENTS_DIR . 'languages' );
			}
		}
	}

	/**
	 * Render the acme/upcoming-events block: the same markup as the shortcode, plus the
	 * block's wrapper classes (alignment, custom class) on the listing root element.
	 *
	 * @param array          $attributes Block attributes.
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public static function render_upcoming_events( $attributes, $block = null ) {
		$html = Listing::render( Listing::from_block_attributes( $attributes ) );
		if ( ! $block instanceof \WP_Block ) {
			return $html;
		}

		$wrapper = get_block_wrapper_attributes();
		if ( ! preg_match( '/class="([^"]*)"/', $wrapper, $m ) ) {
			return $html;
		}
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( $processor->next_tag() ) {
			foreach ( preg_split( '/\s+/', html_entity_decode( $m[1] ), -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
				$processor->add_class( $class );
			}
			$html = $processor->get_updated_html();
		}
		return $html;
	}
}

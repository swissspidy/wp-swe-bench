<?php
/**
 * Nav menu walker for the primary menu: adds a submenu toggle button for keyboard users.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds `<button class="submenu-toggle">` after parent items.
 */
class Acme_Corporate_Menu_Walker extends Walker_Nav_Menu {

	/**
	 * Starts the element output.
	 *
	 * @param string   $output Output.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Arguments.
	 * @param int      $id     Item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		parent::start_el( $output, $item, $depth, $args, $id );
		if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$output .= sprintf(
				'<button class="submenu-toggle" aria-expanded="false"><span class="screen-reader-text">%s</span></button>',
				/* translators: %s: menu item title. */
				esc_html( sprintf( __( 'Show submenu for %s', 'acme-corporate' ), $item->title ) )
			);
		}
	}
}

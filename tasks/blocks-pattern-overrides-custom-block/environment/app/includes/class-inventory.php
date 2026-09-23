<?php
/**
 * CTA inventory: which CTAs exist where (Tools → CTA inventory, `wp acme-cta list`).
 *
 * Marketing uses this to audit outgoing links before campaigns end.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Collects CTA blocks from stored content.
 */
class Inventory {

	const CACHE_KEY = 'acme_cta_inventory';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'save_post', array( $this, 'flush' ) );
		add_action( 'deleted_post', array( $this, 'flush' ) );
	}

	/**
	 * Post types that are scanned.
	 *
	 * @return string[]
	 */
	public function post_types() {
		$types = array_values( get_post_types( array( 'show_ui' => true ) ) );
		if ( ! in_array( 'wp_block', $types, true ) ) {
			$types[] = 'wp_block';
		}
		/**
		 * Filters the post types scanned for CTAs.
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'acme_cta_inventory_post_types', $types );
	}

	/**
	 * All CTAs on the site.
	 *
	 * @return array[] Rows: post_id, post_type, post_title, heading, button_text, url, variant, campaign.
	 */
	public function rows() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows  = array();
		$query = new \WP_Query(
			array(
				'post_type'              => $this->post_types(),
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( $query->posts as $post ) {
			// Cheap pre-check before parsing (the query can't filter on block names).
			if ( false === strpos( $post->post_content, '<!-- wp:acme/cta' ) ) {
				continue;
			}
			foreach ( $this->rows_for_content( $post->post_content ) as $row ) {
				$rows[] = array_merge(
					array(
						'post_id'    => (int) $post->ID,
						'post_type'  => $post->post_type,
						'post_title' => $post->post_title,
					),
					$row
				);
			}
		}

		set_transient( self::CACHE_KEY, $rows, DAY_IN_SECONDS );
		return $rows;
	}

	/**
	 * CTA rows for a piece of content.
	 *
	 * @param string $content Post content.
	 * @return array[]
	 */
	public function rows_for_content( $content ) {
		$rows = array();
		foreach ( acme_cta_find_blocks( parse_blocks( $content ) ) as $block ) {
			$attrs  = acme_cta_normalize_attributes( $block['attrs'] ?? array() );
			$rows[] = array(
				'heading'     => trim( wp_strip_all_tags( $attrs['heading'] ) ),
				'button_text' => trim( wp_strip_all_tags( $attrs['buttonText'] ) ),
				'url'         => $attrs['buttonUrl'],
				'variant'     => $attrs['variant'],
				'campaign'    => $attrs['campaign'],
			);
		}
		return $rows;
	}

	/**
	 * Drop the cached inventory.
	 */
	public function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Tools → CTA inventory.
	 */
	public function add_page() {
		add_management_page(
			__( 'CTA inventory', 'acme-cta' ),
			__( 'CTA inventory', 'acme-cta' ),
			'edit_others_posts',
			'acme-cta-inventory',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the inventory table.
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$rows = $this->rows();
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No CTAs found.', 'acme-cta' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Content', 'acme-cta' ), __( 'Heading', 'acme-cta' ), __( 'Button', 'acme-cta' ), __( 'Link', 'acme-cta' ), __( 'Variant', 'acme-cta' ) ) as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><td><a href="%1$s">%2$s</a> <code>%3$s</code></td><td>%4$s</td><td>%5$s</td><td><code>%6$s</code></td><td>%7$s</td></tr>',
				esc_url( (string) get_edit_post_link( $row['post_id'] ) ),
				esc_html( $row['post_title'] ? $row['post_title'] : __( '(no title)', 'acme-cta' ) ),
				esc_html( $row['post_type'] ),
				esc_html( $row['heading'] ),
				esc_html( $row['button_text'] ),
				esc_html( $row['url'] ),
				esc_html( $row['variant'] )
			);
		}
		echo '</tbody></table></div>';
	}
}

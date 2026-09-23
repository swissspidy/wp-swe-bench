<?php
/**
 * Automatic placement of the signup block in block themes.
 *
 * The block is hooked into the theme's templates instead of being appended to the
 * rendered HTML, so it shows up in the Site Editor, where it can be moved or
 * removed. WordPress remembers removals (in the saved template), so a removed form
 * is not inserted again, while templates nobody touched keep getting it.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks acme/newsletter-signup into the Single templates and footer template parts.
 */
class Block_Hooks {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'hooked_block_types', array( $this, 'hooked_block_types' ), 10, 4 );
		add_filter( 'hooked_block_' . Block::NAME, array( $this, 'hooked_block' ), 10, 5 );
	}

	/**
	 * Where the block is inserted.
	 *
	 * @param string[]                        $hooked_block_types Hooked block types.
	 * @param string                          $relative_position  before|after|first_child|last_child.
	 * @param string|null                     $anchor_block_type  Anchor block type.
	 * @param \WP_Block_Template|\WP_Post|array $context          Where the blocks live.
	 * @return string[]
	 */
	public function hooked_block_types( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
		$placement = $this->placement_for( $relative_position, $anchor_block_type, $context );
		if ( $placement && is_placement_enabled( $placement ) && ! in_array( Block::NAME, $hooked_block_types, true ) ) {
			$hooked_block_types[] = Block::NAME;
		}
		return $hooked_block_types;
	}

	/**
	 * Mark the inserted block with its placement, so that it records the right
	 * subscription source and follows the after-content rules.
	 *
	 * @param array|null                      $parsed_hooked_block The hooked block (parsed), or null if suppressed.
	 * @param string                          $hooked_block_type   Hooked block type.
	 * @param string                          $relative_position   Relative position.
	 * @param array                           $parsed_anchor_block The anchor block (parsed).
	 * @param \WP_Block_Template|\WP_Post|array $context           Where the blocks live.
	 * @return array|null
	 */
	public function hooked_block( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block, $context ) {
		if ( null === $parsed_hooked_block ) {
			return null;
		}
		$placement = $this->placement_for( $relative_position, $parsed_anchor_block['blockName'] ?? null, $context );
		if ( $placement ) {
			$parsed_hooked_block['attrs']['placement'] = $placement;
		}
		return $parsed_hooked_block;
	}

	/**
	 * Which placement (if any) an anchor position represents.
	 *
	 * @param string                          $relative_position Relative position.
	 * @param string|null                     $anchor_block_type Anchor block type.
	 * @param \WP_Block_Template|\WP_Post|array $context         Where the blocks live.
	 * @return string|null
	 */
	private function placement_for( $relative_position, $anchor_block_type, $context ) {
		if ( ! $context instanceof \WP_Block_Template ) {
			return null;
		}

		if (
			'after' === $relative_position
			&& 'core/post-content' === $anchor_block_type
			&& 'wp_template' === $context->type
			&& self::is_single_template( $context->slug )
		) {
			return 'after_content';
		}

		if (
			'last_child' === $relative_position
			&& 'core/template-part' === $anchor_block_type
			&& 'wp_template_part' === $context->type
			&& self::is_footer_part( $context )
		) {
			return 'footer';
		}

		return null;
	}

	/**
	 * Single templates: `single` and the more specific `single-*` ones.
	 *
	 * @param string $slug Template slug.
	 * @return bool
	 */
	public static function is_single_template( $slug ) {
		return 'single' === $slug || 0 === strpos( (string) $slug, 'single-' );
	}

	/**
	 * Footer template parts (area "footer"; parts without an area are recognized by their slug).
	 *
	 * @param \WP_Block_Template $part Template part.
	 * @return bool
	 */
	public static function is_footer_part( \WP_Block_Template $part ) {
		if ( ! empty( $part->area ) && 'uncategorized' !== $part->area ) {
			return 'footer' === $part->area;
		}
		return 'footer' === $part->slug;
	}
}

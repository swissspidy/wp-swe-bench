<?php
/**
 * Block patterns shipped with the plugin.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Team member card" pattern.
 */
class Patterns {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register pattern category + patterns.
	 */
	public function register() {
		register_block_pattern_category( 'acme-team', array( 'label' => __( 'Team', 'acme-team' ) ) );

		register_block_pattern(
			'acme-team/member-card',
			array(
				'title'       => __( 'Team member card', 'acme-team' ),
				'description' => __( 'Photo, name, role and an email button of the current team member. Use it inside a Query Loop over team members.', 'acme-team' ),
				'categories'  => array( 'acme-team' ),
				'keywords'    => array( 'team', 'member', 'staff' ),
				'blockTypes'  => array( 'core/post-template' ),
				'content'     => $this->member_card(),
			)
		);
	}

	/**
	 * A block connected to the current member.
	 *
	 * @param string $name     Block name.
	 * @param array  $bindings Attribute => field key.
	 * @param string $html     Saved markup.
	 * @param array  $attrs    Extra attributes.
	 * @return string
	 */
	private function bound( $name, array $bindings, $html, array $attrs = array() ) {
		$map = array();
		foreach ( $bindings as $attribute => $key ) {
			$map[ $attribute ] = array(
				'source' => Bindings::SOURCE,
				'args'   => array( 'key' => $key ),
			);
		}
		$attrs['metadata'] = array( 'bindings' => $map );
		return serialize_block(
			array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerContent' => array( $html ),
			)
		);
	}

	/**
	 * Markup of the member card.
	 *
	 * @return string
	 */
	private function member_card() {
		$image   = $this->bound(
			'core/image',
			array(
				'url' => 'photo',
				'alt' => 'photo',
			),
			"\n" . '<figure class="wp-block-image"><img alt=""/></figure>' . "\n"
		);
		$heading = $this->bound( 'core/heading', array( 'content' => 'name' ), "\n" . '<h3 class="wp-block-heading">' . esc_html__( 'Name', 'acme-team' ) . '</h3>' . "\n", array( 'level' => 3 ) );
		$role    = $this->bound( 'core/paragraph', array( 'content' => 'role' ), "\n<p>" . esc_html__( 'Role', 'acme-team' ) . "</p>\n" );
		$button  = $this->bound(
			'core/button',
			array(
				'text' => 'email',
				'url'  => 'email',
			),
			"\n" . '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">' . esc_html__( 'Email', 'acme-team' ) . '</a></div>' . "\n"
		);

		return '<!-- wp:group {"className":"acme-team-member-card","layout":{"type":"constrained"}} -->' . "\n"
			. '<div class="wp-block-group acme-team-member-card">' . $image . "\n\n" . $heading . "\n\n" . $role . "\n\n"
			. "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">" . $button . "</div>\n<!-- /wp:buttons --></div>\n"
			. '<!-- /wp:group -->';
	}
}

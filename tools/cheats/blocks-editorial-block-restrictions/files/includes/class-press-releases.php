<?php
/**
 * Press release post type.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the press_release post type and its meta.
 */
class Press_Releases {

	const POST_TYPE = 'press_release';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'the_title', array( $this, 'embargo_label' ), 10, 2 );
	}

	/**
	 * The standard structure of a press release.
	 *
	 * @return array Block template.
	 */
	public static function template() {
		return array(
			array( 'acme/dateline' ),
			array(
				'core/paragraph',
				array(
					'className'   => 'press-release__lead',
					'placeholder' => __( 'Lead paragraph: who, what, when, where, why…', 'acme-newsroom' ),
				),
			),
			array(
				'core/group',
				array(
					'className'    => 'press-release__body',
					'layout'       => array( 'type' => 'constrained' ),
					// The body is free-form: blocks can be added, moved and removed here.
					'templateLock' => false,
				),
				array(
					array( 'core/paragraph', array( 'placeholder' => __( 'Body of the press release…', 'acme-newsroom' ) ) ),
				),
			),
			array( 'acme/boilerplate' ),
			array( 'acme/media-contact' ),
		);
	}

	/**
	 * Register the post type + meta.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'          => __( 'Press releases', 'acme-newsroom' ),
					'singular_name' => __( 'Press release', 'acme-newsroom' ),
					'add_new_item'  => __( 'Add new press release', 'acme-newsroom' ),
					'edit_item'     => __( 'Edit press release', 'acme-newsroom' ),
					'menu_name'     => __( 'Press releases', 'acme-newsroom' ),
				),
				'public'       => true,
				'has_archive'  => 'press',
				'rewrite'      => array( 'slug' => 'press' ),
				'menu_icon'    => 'dashicons-megaphone',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions', 'custom-fields' ),
				'map_meta_cap' => true,
				// Fixed structure: the top-level blocks can't be added, moved or removed.
				'template'      => self::template(),
				'template_lock' => 'all',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_acme_embargo',
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Prefix the title of embargoed press releases in the admin list.
	 *
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public function embargo_label( $title, $post_id = 0 ) {
		if ( ! is_admin() || ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $title;
		}
		$embargo = get_post_meta( $post_id, '_acme_embargo', true );
		if ( $embargo && strtotime( $embargo ) > time() ) {
			/* translators: %s: title. */
			return sprintf( __( '[Embargoed] %s', 'acme-newsroom' ), $title );
		}
		return $title;
	}
}

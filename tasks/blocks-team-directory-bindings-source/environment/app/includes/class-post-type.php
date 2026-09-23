<?php
/**
 * The acme_member post type and the department taxonomy.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the post type.
 */
class Post_Type {

	const POST_TYPE = 'acme_member';
	const TAXONOMY  = 'acme_department';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Register post type + taxonomy.
	 */
	public function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'          => __( 'Team members', 'acme-team' ),
					'singular_name' => __( 'Team member', 'acme-team' ),
					'add_new_item'  => __( 'Add team member', 'acme-team' ),
					'edit_item'     => __( 'Edit team member', 'acme-team' ),
					'all_items'     => __( 'All team members', 'acme-team' ),
				),
				'public'        => true,
				'has_archive'   => 'team',
				'rewrite'       => array( 'slug' => 'team' ),
				'menu_icon'     => 'dashicons-groups',
				'show_in_rest'  => true,
				'rest_base'     => 'acme-members',
				'supports'      => array( 'title', 'editor', 'author', 'revisions', 'excerpt' ),
				'map_meta_cap'  => true,
				'template'      => array(
					array( 'core/paragraph', array( 'placeholder' => __( 'Short bio…', 'acme-team' ) ) ),
				),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Departments', 'acme-team' ),
					'singular_name' => __( 'Department', 'acme-team' ),
				),
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'department' ),
			)
		);
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['acme_role'] = __( 'Role', 'acme-team' );
		return $columns;
	}

	/**
	 * Admin list column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'acme_role' !== $column ) {
			return;
		}
		$member = Member::get( $post_id );
		echo esc_html( $member ? $member->role() : '' );
	}
}

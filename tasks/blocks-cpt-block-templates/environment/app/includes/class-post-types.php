<?php
/**
 * Course post type, topic taxonomy and course meta.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `acme_course` post type and the `acme_course_topic` taxonomy.
 */
class Post_Types {

	const POST_TYPE = 'acme_course';
	const TAXONOMY  = 'acme_course_topic';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_types' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
	}

	/**
	 * Registers the post type and taxonomy.
	 */
	public function register_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Courses', 'acme-courses' ),
					'singular_name'      => __( 'Course', 'acme-courses' ),
					'add_new_item'       => __( 'Add New Course', 'acme-courses' ),
					'edit_item'          => __( 'Edit Course', 'acme-courses' ),
					'new_item'           => __( 'New Course', 'acme-courses' ),
					'view_item'          => __( 'View Course', 'acme-courses' ),
					'search_items'       => __( 'Search Courses', 'acme-courses' ),
					'not_found'          => __( 'No courses found.', 'acme-courses' ),
					'all_items'          => __( 'All Courses', 'acme-courses' ),
					'archives'           => __( 'Course Catalog', 'acme-courses' ),
					'menu_name'          => __( 'Courses', 'acme-courses' ),
					'item_published'     => __( 'Course published.', 'acme-courses' ),
					'item_updated'       => __( 'Course updated.', 'acme-courses' ),
					'not_found_in_trash' => __( 'No courses found in Trash.', 'acme-courses' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'has_archive'   => 'courses',
				'rewrite'       => array(
					'slug'       => 'courses',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-welcome-learn-more',
				'menu_position' => 21,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'taxonomies'    => array( self::TAXONOMY ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Topics', 'acme-courses' ),
					'singular_name' => __( 'Topic', 'acme-courses' ),
					'search_items'  => __( 'Search Topics', 'acme-courses' ),
					'all_items'     => __( 'All Topics', 'acme-courses' ),
					'edit_item'     => __( 'Edit Topic', 'acme-courses' ),
					'add_new_item'  => __( 'Add New Topic', 'acme-courses' ),
					'menu_name'     => __( 'Topics', 'acme-courses' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'course-topic',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Registers course meta for the REST API / block editor sidebar.
	 *
	 * The legacy 1.x keys (`acme_course_price`, `acme_course_duration`) are
	 * intentionally not registered: they are read-only fallbacks, see Course.
	 */
	public function register_meta() {
		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};

		register_post_meta(
			self::POST_TYPE,
			Course::META_PRICE,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'description'       => __( 'Price in cents. 0 means free.', 'acme-courses' ),
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			Course::META_DURATION,
			array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'value' => array( 'type' => 'integer' ),
							'unit'  => array(
								'type' => 'string',
								'enum' => array_keys( Course::duration_units() ),
							),
						),
					),
				),
				'auth_callback' => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			Course::META_STATUS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'open',
				'sanitize_callback' => array( Course::class, 'sanitize_status' ),
				'auth_callback'     => $auth,
			)
		);

		register_post_meta(
			self::POST_TYPE,
			Course::META_ENROLL_URL,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => $auth,
			)
		);
	}

	/**
	 * Adds price/duration columns to the course list table.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_price']    = __( 'Price', 'acme-courses' );
				$new['acme_duration'] = __( 'Duration', 'acme-courses' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		$course = Course::from_post( $post_id );
		if ( ! $course ) {
			return;
		}
		if ( 'acme_price' === $column ) {
			echo wp_kses_post( acme_courses_format_price( $course->price_cents() ) );
		} elseif ( 'acme_duration' === $column ) {
			echo esc_html( $course->duration_label() );
		}
	}
}

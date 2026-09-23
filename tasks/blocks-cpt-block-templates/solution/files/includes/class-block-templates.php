<?php
/**
 * Block templates and template parts for block themes.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

use WP_Block_Template;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the course templates (single, catalog, topic) and the
 * `course-summary` template part to block themes.
 *
 * Precedence, highest first:
 *   1. customizations saved in the Site Editor (database),
 *   2. templates / parts shipped by the active theme,
 *   3. the defaults shipped with this plugin.
 *
 * Templates go through the core template registry, which already implements
 * that precedence. Core has no registry for template parts yet, so the part is
 * provided through the block template lookup filters.
 */
class Block_Templates {

	const PLUGIN_SLUG = 'acme-courses';

	/**
	 * Plugin templates: slug => [ title, description ].
	 *
	 * @return array<string, array{title:string, description:string}>
	 */
	public static function templates() {
		return array(
			'single-acme_course'          => array(
				'title'       => __( 'Single Course', 'acme-courses' ),
				'description' => __( 'Displays a single course: title, price, duration, enroll button, description and topics.', 'acme-courses' ),
			),
			'archive-acme_course'         => array(
				'title'       => __( 'Course Catalog', 'acme-courses' ),
				'description' => __( 'Displays the list of all courses.', 'acme-courses' ),
			),
			'taxonomy-acme_course_topic' => array(
				'title'       => __( 'Course Topic', 'acme-courses' ),
				'description' => __( 'Displays the courses of one topic.', 'acme-courses' ),
			),
		);
	}

	/**
	 * Plugin template parts: slug => [ title, area ].
	 *
	 * @return array<string, array{title:string, area:string}>
	 */
	public static function template_parts() {
		return array(
			'course-summary' => array(
				'title' => __( 'Course Summary', 'acme-courses' ),
				'area'  => WP_TEMPLATE_PART_AREA_UNCATEGORIZED,
			),
		);
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_templates' ), 20 );
		add_filter( 'get_block_templates', array( $this, 'add_template_parts' ), 10, 3 );
		add_filter( 'get_block_file_template', array( $this, 'get_template_part_file' ), 10, 3 );
	}

	/**
	 * Registers the plugin templates with the template registry.
	 */
	public function register_templates() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		foreach ( self::templates() as $slug => $template ) {
			$name = self::PLUGIN_SLUG . '//' . $slug;
			if ( \WP_Block_Templates_Registry::get_instance()->is_registered( $name ) ) {
				continue;
			}
			register_block_template(
				$name,
				array(
					'title'       => $template['title'],
					'description' => $template['description'],
					'content'     => self::read( 'templates/' . $slug . '.html' ),
					'plugin'      => self::PLUGIN_SLUG,
				)
			);
		}
	}

	/**
	 * Reads a file from block-templates/.
	 *
	 * @param string $file Relative path.
	 * @return string
	 */
	private static function read( $file ) {
		$path = ACME_COURSES_DIR . 'block-templates/' . $file;
		if ( ! is_readable( $path ) ) {
			return '';
		}
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Builds the template part object shipped with the plugin.
	 *
	 * @param string $slug Part slug.
	 * @return WP_Block_Template|null
	 */
	public static function build_template_part( $slug ) {
		$parts = self::template_parts();
		if ( ! isset( $parts[ $slug ] ) || ! wp_is_block_theme() ) {
			return null;
		}
		$theme = get_stylesheet();

		$template                 = new WP_Block_Template();
		$template->id             = $theme . '//' . $slug;
		$template->theme          = $theme;
		$template->slug           = $slug;
		$template->type           = 'wp_template_part';
		$template->source         = 'plugin';
		$template->origin         = 'plugin';
		$template->title          = $parts[ $slug ]['title'];
		$template->description    = '';
		$template->area           = $parts[ $slug ]['area'];
		$template->status         = 'publish';
		$template->has_theme_file = false;
		$template->is_custom      = false;
		$template->author         = null;
		$template->plugin         = self::PLUGIN_SLUG;
		$template->modified       = null;
		$template->content        = self::read( 'parts/' . $slug . '.html' );

		if ( function_exists( 'apply_block_hooks_to_content' ) ) {
			$template->content = apply_block_hooks_to_content( $template->content, $template, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata' );
		}
		return $template;
	}

	/**
	 * Adds the plugin template parts to template part queries (Site Editor,
	 * REST API) unless the theme or the user already provides them.
	 *
	 * @param WP_Block_Template[] $templates     Found templates.
	 * @param array               $query         Query.
	 * @param string              $template_type Template type.
	 * @return WP_Block_Template[]
	 */
	public function add_template_parts( $templates, $query, $template_type ) {
		if ( 'wp_template_part' !== $template_type || ! wp_is_block_theme() || isset( $query['wp_id'] ) ) {
			return $templates;
		}
		if ( isset( $query['theme'] ) && get_stylesheet() !== $query['theme'] ) {
			return $templates;
		}

		$existing = wp_list_pluck( $templates, 'slug' );
		foreach ( array_keys( self::template_parts() ) as $slug ) {
			if ( in_array( $slug, $existing, true ) ) {
				continue;
			}
			if ( ! empty( $query['slug__in'] ) && ! in_array( $slug, $query['slug__in'], true ) ) {
				continue;
			}
			if ( ! empty( $query['slug__not_in'] ) && in_array( $slug, $query['slug__not_in'], true ) ) {
				continue;
			}
			$part = self::build_template_part( $slug );
			if ( ! $part || ( isset( $query['area'] ) && $query['area'] !== $part->area ) ) {
				continue;
			}
			$templates[] = $part;
		}
		return $templates;
	}

	/**
	 * Supplies the plugin template part when neither the database nor the
	 * theme has one (front-end rendering, REST single item, Site Editor).
	 *
	 * @param WP_Block_Template|null $template      Template found so far.
	 * @param string                 $id            Template ID (theme//slug).
	 * @param string                 $template_type Template type.
	 * @return WP_Block_Template|null
	 */
	public function get_template_part_file( $template, $id, $template_type ) {
		if ( 'wp_template_part' !== $template_type ) {
			return $template;
		}
		// The template registry does not look at the type: never hand out a
		// plugin *template* where a template part was requested.
		if ( $template instanceof WP_Block_Template && 'wp_template_part' === $template->type ) {
			return $template;
		}
		$parts = explode( '//', (string) $id, 2 );
		if ( 2 !== count( $parts ) || get_stylesheet() !== $parts[0] ) {
			return $template instanceof WP_Block_Template && 'wp_template_part' === $template->type ? $template : null;
		}
		$part = self::build_template_part( $parts[1] );
		if ( $part ) {
			return $part;
		}
		return $template instanceof WP_Block_Template && 'wp_template_part' === $template->type ? $template : null;
	}
}

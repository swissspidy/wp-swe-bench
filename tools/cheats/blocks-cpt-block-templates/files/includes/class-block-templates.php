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
}

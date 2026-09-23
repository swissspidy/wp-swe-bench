<?php
/**
 * Template loading for course pages.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the plugin's PHP templates for course pages unless the theme has its own.
 *
 * Lookup order (first match wins):
 *   1. theme: single-acme_course.php / archive-acme_course.php / taxonomy-acme_course_topic.php
 *   2. theme: acme-courses/<same name>
 *   3. plugin: templates/<same name>
 */
class Template_Loader {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Picks the template for course requests.
	 *
	 * @param string $template Template chosen by WordPress.
	 * @return string
	 */
	public function template_include( $template ) {
		$name = $this->template_name();
		if ( ! $name ) {
			return $template;
		}

		$theme_template = locate_template( array( $name, 'acme-courses/' . $name ) );
		if ( $theme_template ) {
			return $theme_template;
		}

		$plugin_template = ACME_COURSES_DIR . 'templates/' . $name;
		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	/**
	 * Template file name for the current request, or '' for non-course requests.
	 *
	 * @return string
	 */
	public function template_name() {
		if ( is_singular( Post_Types::POST_TYPE ) ) {
			return 'single-acme_course.php';
		}
		if ( is_tax( Post_Types::TAXONOMY ) ) {
			return 'taxonomy-acme_course_topic.php';
		}
		if ( is_post_type_archive( Post_Types::POST_TYPE ) ) {
			return 'archive-acme_course.php';
		}
		return '';
	}

	/**
	 * Adds `acme-courses-page` to course pages so the stylesheet can scope itself.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( $this->template_name() ) {
			$classes[] = 'acme-courses-page';
		}
		return $classes;
	}
}

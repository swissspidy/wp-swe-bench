<?php
/**
 * Template tags for theme developers (documented in readme.txt).
 *
 * @package Acme\Courses
 */

use Acme\Courses\Course;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the price of a course.
 *
 * @param int|WP_Post|null $post Course (default: current post).
 */
function acme_course_price( $post = null ) {
	$course = Course::from_post( $post );
	if ( $course ) {
		echo wp_kses_post( acme_courses_format_price( $course->price_cents(), $course ) );
	}
}

/**
 * Prints the duration of a course.
 *
 * @param int|WP_Post|null $post Course (default: current post).
 */
function acme_course_duration( $post = null ) {
	$course = Course::from_post( $post );
	if ( $course ) {
		echo esc_html( $course->duration_label() );
	}
}

/**
 * Prints the enroll button of a course.
 *
 * @param int|WP_Post|null $post Course (default: current post).
 */
function acme_course_enroll_button( $post = null ) {
	$course = Course::from_post( $post );
	if ( $course ) {
		echo wp_kses_post( acme_courses_enroll_button_html( $course ) );
	}
}

/**
 * Prints the topics of a course as links.
 *
 * @param int|WP_Post|null $post      Course (default: current post).
 * @param string           $separator Separator.
 */
function acme_course_topics( $post = null, $separator = ', ' ) {
	$course = Course::from_post( $post );
	if ( ! $course ) {
		return;
	}
	$links = array();
	foreach ( $course->topics() as $term ) {
		$links[] = sprintf( '<a href="%s" rel="tag">%s</a>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
	}
	echo wp_kses_post( implode( esc_html( $separator ), $links ) );
}

/**
 * Loads a template part from the theme (acme-courses/<name>.php) or the plugin.
 *
 * @param string $name Part name, e.g. 'course-card'.
 * @param array  $args Arguments passed to the template.
 */
function acme_courses_get_template_part( $name, $args = array() ) {
	$template = locate_template( array( 'acme-courses/' . $name . '.php' ) );
	if ( ! $template ) {
		$template = ACME_COURSES_DIR . 'templates/parts/' . $name . '.php';
	}
	if ( file_exists( $template ) ) {
		load_template( $template, false, $args );
	}
}

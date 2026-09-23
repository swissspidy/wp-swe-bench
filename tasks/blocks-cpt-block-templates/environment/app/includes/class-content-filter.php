<?php
/**
 * Injects course details into the content of single courses and listings.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the summary box to single courses and a meta line to course excerpts.
 *
 * Themes can opt out with remove_filter() via
 * Plugin::instance()->components['content_filter'].
 */
class Content_Filter {

	/**
	 * Guards against recursion when the summary is rendered inside the_content.
	 *
	 * @var bool
	 */
	private $rendering = false;

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'add_summary' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'add_listing_meta' ), 20 );
	}

	/**
	 * Prepends the summary box on single course pages.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function add_summary( $content ) {
		if ( $this->rendering || ! Settings::get( 'show_summary' ) ) {
			return $content;
		}
		if ( ! is_singular( Post_Types::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$course = Course::from_post( get_the_ID() );
		if ( ! $course ) {
			return $content;
		}

		$this->rendering = true;
		$summary         = acme_courses_summary_html( $course );
		$this->rendering = false;

		return $summary . $content;
	}

	/**
	 * Appends "price · duration" to course excerpts in archives and search.
	 *
	 * @param string $excerpt Excerpt HTML.
	 * @return string
	 */
	public function add_listing_meta( $excerpt ) {
		if ( is_admin() || is_singular() ) {
			return $excerpt;
		}
		$course = Course::from_post( get_the_ID() );
		if ( ! $course ) {
			return $excerpt;
		}
		return $excerpt . acme_courses_listing_meta_html( $course );
	}
}

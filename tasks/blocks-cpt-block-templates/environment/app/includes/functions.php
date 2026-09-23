<?php
/**
 * Formatting helpers shared by templates, the summary box and blocks.
 *
 * These are global functions because themes call them directly.
 *
 * @package Acme\Courses
 */

use Acme\Courses\Course;
use Acme\Courses\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Formats a price in cents for display ("$49.00", "Free", "49,00 €").
 *
 * @param int|null    $cents  Price in cents (0 = free, null = no price).
 * @param Course|null $course Course the price belongs to, for the filter.
 * @return string HTML (already escaped). Empty string when there is no price.
 */
function acme_courses_format_price( $cents, $course = null ) {
	if ( null === $cents ) {
		$html = '';
	} elseif ( 0 === (int) $cents ) {
		$html = esc_html__( 'Free', 'acme-courses' );
	} else {
		$settings   = Settings::get();
		$currencies = Settings::currencies();
		$symbol     = $currencies[ $settings['currency'] ] ?? '$';
		$amount     = number_format_i18n( $cents / 100, 2 );
		$html       = esc_html( 'after' === $settings['currency_position'] ? $amount . ' ' . trim( $symbol ) : $symbol . $amount );
	}

	/**
	 * Filters the formatted course price.
	 *
	 * @param string      $html   Escaped HTML.
	 * @param int|null    $cents  Price in cents.
	 * @param Course|null $course Course.
	 */
	return (string) apply_filters( 'acme_courses_price_html', $html, $cents, $course );
}

/**
 * Enroll button markup for a course.
 *
 * Open courses link to the enrollment URL, waitlisted courses link to it with a
 * different label, closed courses output a non-link notice.
 *
 * @param Course $course Course.
 * @return string HTML.
 */
function acme_courses_enroll_button_html( Course $course ) {
	$status = $course->enrollment_status();

	if ( 'closed' === $status ) {
		$html = sprintf(
			'<span class="acme-course-enroll is-closed">%s</span>',
			esc_html__( 'Enrollment closed', 'acme-courses' )
		);
	} else {
		$label = 'waitlist' === $status ? __( 'Join the waitlist', 'acme-courses' ) : __( 'Enroll now', 'acme-courses' );

		/**
		 * Filters the enroll button label.
		 *
		 * @param string $label  Label (unescaped).
		 * @param Course $course Course.
		 */
		$label = (string) apply_filters( 'acme_courses_enroll_label', $label, $course );

		$html = sprintf(
			'<a class="acme-course-enroll button%1$s" href="%2$s">%3$s</a>',
			'waitlist' === $status ? ' is-waitlist' : '',
			esc_url( $course->enroll_url() ),
			esc_html( $label )
		);
	}

	/**
	 * Filters the enroll button markup.
	 *
	 * @param string $html   HTML.
	 * @param Course $course Course.
	 */
	return (string) apply_filters( 'acme_courses_enroll_button_html', $html, $course );
}

/**
 * The "course summary" box shown above the description on single courses:
 * price, duration and the enroll button.
 *
 * @param Course $course Course.
 * @return string HTML.
 */
function acme_courses_summary_html( Course $course ) {
	$price    = acme_courses_format_price( $course->price_cents(), $course );
	$duration = $course->duration_label();

	$html  = '<div class="acme-course-summary">';
	$html .= '<ul class="acme-course-summary__facts">';
	if ( '' !== $price ) {
		$html .= '<li class="acme-course-summary__price"><span class="screen-reader-text">' . esc_html__( 'Price:', 'acme-courses' ) . ' </span>' . $price . '</li>';
	}
	if ( '' !== $duration ) {
		$html .= '<li class="acme-course-summary__duration"><span class="screen-reader-text">' . esc_html__( 'Duration:', 'acme-courses' ) . ' </span>' . esc_html( $duration ) . '</li>';
	}
	$html .= '</ul>';
	$html .= acme_courses_enroll_button_html( $course );
	$html .= '</div>';

	/**
	 * Filters the summary box.
	 *
	 * @param string $html   HTML.
	 * @param Course $course Course.
	 */
	return (string) apply_filters( 'acme_courses_summary_html', $html, $course );
}

/**
 * One-line meta for course listings ("$49.00 · 6 weeks").
 *
 * @param Course $course Course.
 * @return string HTML.
 */
function acme_courses_listing_meta_html( Course $course ) {
	$bits  = array_filter(
		array(
			acme_courses_format_price( $course->price_cents(), $course ),
			esc_html( $course->duration_label() ),
		)
	);
	return $bits ? '<p class="acme-course-excerpt-meta">' . implode( ' &middot; ', $bits ) . '</p>' : '';
}

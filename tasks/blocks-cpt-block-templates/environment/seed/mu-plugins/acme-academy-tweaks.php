<?php
/**
 * Plugin Name: Acme Academy site tweaks
 * Description: Site-specific customizations for academy.example.org.
 */

// Marketing wants a friendlier call to action on open courses.
add_filter(
	'acme_courses_enroll_label',
	static function ( $label, $course ) {
		return 'open' === $course->enrollment_status() ? 'Start learning' : $label;
	},
	10,
	2
);

// Partner courses are sold through the partner shop.
add_filter(
	'acme_courses_enroll_url',
	static function ( $url, $course ) {
		if ( has_term( 'partner', 'acme_course_topic', $course->post() ) ) {
			return add_query_arg( 'ref', 'academy', 'https://partners.example.org/shop/' . $course->post()->post_name );
		}
		return $url;
	},
	10,
	2
);

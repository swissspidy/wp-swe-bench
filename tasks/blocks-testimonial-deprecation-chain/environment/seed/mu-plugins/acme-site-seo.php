<?php
/**
 * Plugin Name: Acme SEO tweaks
 * Description: The reviewed item is the product, not the site.
 */

add_filter(
	'acme_testimonials_schema_review',
	static function ( $review ) {
		$review['itemReviewed'] = array(
			'@type' => 'SoftwareApplication',
			'name'  => 'Acme Suite',
		);
		return $review;
	}
);

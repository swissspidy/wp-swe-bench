<?php
/**
 * Plugin Name: Acme Realty – search tweaks
 * Description: Site-specific search customizations (maintained by the agency's developer).
 */

// People type abbreviations into the city field of the partner portal.
add_filter(
	'acme_re_search_args',
	static function ( $args ) {
		$aliases = array(
			'NYC' => 'New York',
			'SF'  => 'San Francisco',
		);
		if ( isset( $aliases[ $args['city'] ] ) ) {
			$args['city'] = $aliases[ $args['city'] ];
		}
		return $args;
	}
);

// The mobile app shows the area in square metres too.
add_filter(
	'acme_re_listing_data',
	static function ( $data ) {
		$data['sqm'] = (int) round( $data['sqft'] * 0.092903 );
		return $data;
	}
);

<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Site-specific customizations for the Acme blog.
 */

add_filter(
	'acme_callouts_types',
	static function ( $types ) {
		$types['tip'] = 'Tip';
		return $types;
	}
);

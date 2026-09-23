<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Site-specific customizations for the Acme Furniture catalogue (German market).
 */

add_filter(
	'acme_specs_certification_codes',
	static function ( $codes ) {
		$codes['GS'] = 'GS (Geprüfte Sicherheit)';
		return $codes;
	}
);

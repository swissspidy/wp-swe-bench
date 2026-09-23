<?php
/**
 * Plugin Name: Acme docs theme tweaks
 * Description: Site-specific glue code (lives in mu-plugins on production).
 */

// Deprecated glossary terms get a note in their short definition.
add_filter(
	'acme_glossary_definition',
	static function ( $definition, $term ) {
		if ( get_post_meta( $term->ID, '_docs_deprecated', true ) ) {
			$definition .= ' (Deprecated term.)';
		}
		return $definition;
	},
	10,
	2
);

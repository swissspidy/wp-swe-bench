<?php
/**
 * Plugin Name: Acme Reports customizations
 * Description: Brand palette for charts on the Acme Reports site.
 */

add_filter(
	'acme_charts_palette',
	static function ( $palette ) {
		// Brand colours first, then whatever is configured in Settings → Charts.
		return array_merge( array( '#0b3d91', '#fc3d21' ), (array) $palette );
	}
);

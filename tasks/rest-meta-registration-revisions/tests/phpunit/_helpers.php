<?php
/**
 * Helpers for the Acme Specs tests.
 */

namespace WPSB\Specs;

const DIMS  = '_acme_specs_dimensions';
const MATS  = '_acme_specs_materials';
const CERTS = '_acme_specs_certifications';

const LEGACY_KEYS = array( '_acme_width', '_acme_height', '_acme_depth', '_acme_unit', '_acme_materials', '_acme_certs' );

function product_id( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acme_product' AND post_name = %s ORDER BY ID LIMIT 1", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded product '$slug' not found" );
	}
	return $id;
}

function user_id( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		throw new \RuntimeException( "Seeded user '$login' not found" );
	}
	return $user->ID;
}

/** Certifications as comparable tuples [code, issued, expires|''] (order kept). */
function certs( $certs ): array {
	$out = array();
	foreach ( (array) $certs as $cert ) {
		$cert  = (array) $cert;
		$out[] = array( $cert['code'] ?? null, $cert['issued'] ?? null, (string) ( $cert['expires'] ?? '' ) );
	}
	return $out;
}

/** Raw stored meta (bypassing caches). */
function stored( int $post_id, string $key ) {
	wp_cache_delete( $post_id, 'post_meta' );
	return get_post_meta( $post_id, $key, true );
}

/**
 * Parse the specs table(s) in some HTML.
 *
 * @return array<int, array{dimensions: ?string, materials: ?string, certs: array<string, string>}>
 */
function tables( string $html ): array {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	$xpath = new \DOMXPath( $doc );
	$out   = array();
	foreach ( $xpath->query( "//table[contains(concat(' ', normalize-space(@class), ' '), ' acme-specs ')]" ) as $table ) {
		$cell  = static function ( string $mod ) use ( $xpath, $table ) {
			$td = $xpath->query( ".//tr[contains(@class, 'acme-specs__row--$mod')]/td", $table )->item( 0 );
			return $td ? trim( preg_replace( '/\s+/u', ' ', $td->textContent ) ) : null;
		};
		$certs = array();
		foreach ( $xpath->query( ".//li[contains(@class, 'acme-specs__cert')]", $table ) as $li ) {
			$dates = $xpath->query( ".//*[contains(@class, 'acme-specs__cert-dates')]", $li )->item( 0 );
			$certs[ $li->getAttribute( 'data-code' ) ] = $dates ? trim( $dates->textContent ) : '';
		}
		$out[] = array(
			'product'    => (int) $table->getAttribute( 'data-product' ),
			'dimensions' => $cell( 'dimensions' ),
			'materials'  => $cell( 'materials' ),
			'certs'      => $certs,
		);
	}
	return $out;
}

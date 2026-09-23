<?php
/**
 * Helpers for the Acme Bookings API tests (fixture lookups, error inspection).
 */

namespace WPSB\Bookings;

const NS  = '/acme-bookings/v1';
const NS2 = '/acme-bookings/v2';

function table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_bookings';
}

function room( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acme_room' AND post_name = %s", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "room $slug not found" );
	}
	return $id;
}

function user( string $login ): int {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "user $login not found" );
	}
	return (int) $u->ID;
}

/** Seeded booking row by its notes marker. */
function seeded( string $notes ): object {
	global $wpdb;
	$t   = table();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE notes = %s", $notes ) );
	if ( ! $row ) {
		throw new \RuntimeException( "seeded booking '$notes' not found" );
	}
	return $row;
}

function row( int $id ): ?object {
	global $wpdb;
	$t = table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) ?: null;
}

function count_rows( string $where = '1=1' ): int {
	global $wpdb;
	$t = table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$where}" );
}

/** Insert a raw row (like old plugin versions did). */
function insert_row( array $data ): int {
	global $wpdb;
	$data += array(
		'guests'      => 1,
		'status'      => 'pending',
		'notes'       => '',
		'admin_notes' => '',
		'created_at'  => '2026-07-01 08:00:00',
	);
	$wpdb->insert( table(), $data );
	return (int) $wpdb->insert_id;
}

/** Does the error data of a 400 name a field (either as params key or in a list)? */
function error_mentions( $data, string $field ): bool {
	$params = $data['data']['params'] ?? ( $data['params'] ?? null );
	if ( ! is_array( $params ) ) {
		return false;
	}
	if ( array_key_exists( $field, $params ) ) {
		return true;
	}
	return in_array( $field, $params, true );
}

/** Parse a Link header into rel => url. */
function links( string $header ): array {
	$out = array();
	foreach ( preg_split( '/,\s*(?=<)/', $header ) as $part ) {
		if ( preg_match( '/<([^>]+)>\s*;\s*rel="?([^";]+)"?/', $part, $m ) ) {
			$out[ $m[2] ] = $m[1];
		}
	}
	return $out;
}

/** Link header of an in-process response, as the server would send it. */
function link_header( \WP_REST_Response $r ): string {
	$h = $r->get_headers();
	return isset( $h['Link'] ) ? (string) $h['Link'] : '';
}

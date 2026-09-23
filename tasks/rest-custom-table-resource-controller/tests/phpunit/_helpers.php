<?php
/**
 * Helpers for the Acme Leads tests.
 */

namespace WPSB\Leads;

function table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_leads';
}

function user_id( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		throw new \RuntimeException( "Seeded user '$login' not found" );
	}
	return $user->ID;
}

function lead_id( string $email ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . table() . ' WHERE email = %s', $email ) );
	if ( ! $id ) {
		throw new \RuntimeException( "Seeded lead '$email' not found" );
	}
	return $id;
}

function row( int $id ): ?array {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . table() . ' WHERE id = %d', $id ), ARRAY_A );
}

/** IDs from SQL (the expected order). */
function ids( string $sql_tail, array $params = array() ): array {
	global $wpdb;
	$sql = 'SELECT id FROM ' . table() . ' ' . $sql_tail;
	if ( $params ) {
		$sql = $wpdb->prepare( $sql, $params );
	}
	return array_map( 'intval', $wpdb->get_col( $sql ) );
}

/**
 * In-process REST request that also runs the server's response post-processing
 * (`_fields` filtering etc.), like a real request.
 */
function dispatch( string $method, string $route, array $query = array(), $body = null, array $headers = array() ): \WP_REST_Response {
	$request = new \WP_REST_Request( $method, $route );
	foreach ( $headers as $k => $v ) {
		$request->set_header( $k, $v );
	}
	if ( $query ) {
		$request->set_query_params( $query );
	}
	if ( is_array( $body ) ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	$server   = rest_get_server();
	$response = rest_ensure_response( $server->dispatch( $request ) );
	return apply_filters( 'rest_post_dispatch', $response, $server, $request );
}

/** Normalize a header lookup (case-insensitive). */
function hdr( \WP_REST_Response $response, string $name ): ?string {
	foreach ( $response->get_headers() as $key => $value ) {
		if ( strtolower( $key ) === strtolower( $name ) ) {
			return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
	}
	return null;
}

/** Queries touching the leads table. */
function lead_queries( array $queries ): array {
	return array_values( array_filter( $queries, static fn( $q ) => false !== stripos( $q, 'acme_leads' ) ) );
}

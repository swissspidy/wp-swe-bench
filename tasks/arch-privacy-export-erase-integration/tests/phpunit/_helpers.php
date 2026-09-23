<?php
/**
 * Helpers for the Acme Loyalty privacy tests: drive the registered personal data exporters and
 * erasers exactly like core's request screens do (page by page until `done`).
 */

namespace WPSB\Loyalty;

const JANE  = 'jane.doe@example.com';
const GINA  = 'guest.gina@example.net';
const MARCO = 'marco@example.org';

/** Ledger table. */
function ledger(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_loyalty_ledger';
}

/** Subscribers table. */
function subs(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_loyalty_subscribers';
}

function user_id( string $login ): int {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "seeded user $login missing" );
	}
	return (int) $u->ID;
}

/** Order post ID by order number. */
function order_id( string $number ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_acme_order_number' AND meta_value = %s", $number ) );
	if ( ! $id ) {
		throw new \RuntimeException( "seeded order $number missing" );
	}
	return $id;
}

/** Subscriber row by exact (stored) email. */
function subscriber( string $stored_email ): ?array {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . subs() . ' WHERE email = %s', $stored_email ), ARRAY_A );
}

/**
 * Run every registered exporter for an email like core does: page 1, 2, … until done.
 *
 * @return array{items: array[], log: array[]} log: [exporter key, page, item count]
 */
function run_exporters( string $email ): array {
	$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
	\PHPUnit\Framework\Assert::assertIsArray( $exporters );
	$items = array();
	$log   = array();
	foreach ( $exporters as $key => $exporter ) {
		\PHPUnit\Framework\Assert::assertArrayHasKey( 'callback', $exporter, "exporter $key has no callback" );
		\PHPUnit\Framework\Assert::assertArrayHasKey( 'exporter_friendly_name', $exporter, "exporter $key has no friendly name" );
		$page = 1;
		do {
			$response = call_user_func( $exporter['callback'], $email, $page );
			\PHPUnit\Framework\Assert::assertIsArray( $response, "exporter $key page $page: response must be an array" );
			\PHPUnit\Framework\Assert::assertArrayHasKey( 'data', $response, "exporter $key page $page: no data" );
			\PHPUnit\Framework\Assert::assertIsArray( $response['data'], "exporter $key page $page: data must be an array" );
			\PHPUnit\Framework\Assert::assertArrayHasKey( 'done', $response, "exporter $key page $page: no done" );
			foreach ( $response['data'] as $item ) {
				\PHPUnit\Framework\Assert::assertArrayHasKey( 'group_id', $item );
				\PHPUnit\Framework\Assert::assertArrayHasKey( 'item_id', $item );
				\PHPUnit\Framework\Assert::assertArrayHasKey( 'data', $item );
				$item['_exporter'] = $key;
				$items[]           = $item;
			}
			$log[] = array( $key, $page, count( $response['data'] ) );
			++$page;
			\PHPUnit\Framework\Assert::assertLessThan( 200, $page, "exporter $key never finished" );
		} while ( empty( $response['done'] ) );
	}
	return array(
		'items' => $items,
		'log'   => $log,
	);
}

/**
 * Run every registered eraser like core does.
 *
 * @return array{removed: bool, retained: bool, messages: string[], log: array[]}
 */
function run_erasers( string $email, ?callable $after_each = null ): array {
	$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
	$out     = array(
		'removed'  => false,
		'retained' => false,
		'messages' => array(),
		'log'      => array(),
	);
	foreach ( $erasers as $key => $eraser ) {
		\PHPUnit\Framework\Assert::assertArrayHasKey( 'callback', $eraser, "eraser $key has no callback" );
		\PHPUnit\Framework\Assert::assertArrayHasKey( 'eraser_friendly_name', $eraser, "eraser $key has no friendly name" );
		$page = 1;
		do {
			$response = call_user_func( $eraser['callback'], $email, $page );
			\PHPUnit\Framework\Assert::assertIsArray( $response, "eraser $key page $page: response must be an array" );
			foreach ( array( 'items_removed', 'items_retained', 'messages', 'done' ) as $k ) {
				\PHPUnit\Framework\Assert::assertArrayHasKey( $k, $response, "eraser $key page $page: missing $k" );
			}
			\PHPUnit\Framework\Assert::assertIsArray( $response['messages'] );
			$out['removed']  = $out['removed'] || $response['items_removed'];
			$out['retained'] = $out['retained'] || $response['items_retained'];
			$out['messages'] = array_merge( $out['messages'], $response['messages'] );
			$out['log'][]    = array( $key, $page );
			if ( $after_each ) {
				$after_each( $key, $page, $response );
			}
			++$page;
			\PHPUnit\Framework\Assert::assertLessThan( 200, $page, "eraser $key never finished" );
		} while ( empty( $response['done'] ) );
	}
	return $out;
}

/** Items of one group. */
function group( array $items, string $group_id ): array {
	return array_values( array_filter( $items, static fn( $i ) => $i['group_id'] === $group_id ) );
}

/** name => list of values of an export item. */
function pairs( array $item ): array {
	$out = array();
	foreach ( $item['data'] as $pair ) {
		$out[ $pair['name'] ][] = (string) $pair['value'];
	}
	return $out;
}

/** First value for a name (null if absent). */
function value( array $item, string $name ): ?string {
	$p = pairs( $item );
	return isset( $p[ $name ] ) ? $p[ $name ][0] : null;
}

/** Ledger rows as id => row. */
function ledger_rows( string $where = '1=1' ): array {
	global $wpdb;
	$out = array();
	foreach ( $wpdb->get_results( 'SELECT * FROM ' . ledger() . " WHERE $where ORDER BY id", ARRAY_A ) as $r ) {
		$out[ (int) $r['id'] ] = $r;
	}
	return $out;
}

/** Order snapshot: notes, legacy note, email, customer. */
function order_state( int $id ): array {
	wp_cache_delete( $id, 'post_meta' );
	return array(
		'notes'    => get_post_meta( $id, '_acme_order_notes', true ),
		'legacy'   => get_post_meta( $id, '_acme_order_note', true ),
		'email'    => get_post_meta( $id, '_acme_order_email', true ),
		'customer' => (int) get_post_meta( $id, '_acme_order_customer_id', true ),
		'status'   => get_post_meta( $id, '_acme_order_status', true ),
		'total'    => get_post_meta( $id, '_acme_order_total', true ),
		'number'   => get_post_meta( $id, '_acme_order_number', true ),
	);
}

/** Loyalty user meta keys left on an account. */
function loyalty_meta_keys( int $user_id ): array {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, '%acme\_loyalty%' ) );
}

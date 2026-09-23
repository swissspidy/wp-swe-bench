<?php
/**
 * Seeds the 1.3.2-era state: orders received through the (unauthenticated) webhook,
 * and the sync log those deliveries produced (with everything the old logger wrote).
 *
 * Runs with `wp eval-file` at image build time.
 */

$deliver = static function ( string $source, array $event, array $headers = array() ) {
	$request = new WP_REST_Request( 'POST', '/acme-orders/v1/webhook' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_header( 'X-Acme-Source', $source );
	$request->set_header( 'User-Agent', 'AcmeShop-Webhooks/2.4' );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	$request->set_body( wp_json_encode( $event ) );
	$response = rest_do_request( $request );
	echo $source, ' ', $event['id'], ' => ', $response->get_status(), "\n";
};

$order = static function ( string $number, string $status, float $total, string $currency, array $extra = array() ) {
	return array_merge(
		array(
			'number'   => $number,
			'status'   => $status,
			'total'    => $total,
			'currency' => $currency,
			'email'    => strtolower( str_replace( '-', '', $number ) ) . '@customer.example',
			'items'    => array(
				array(
					'sku'   => 'MUG-01',
					'name'  => 'Acme mug',
					'qty'   => 2,
					'price' => 12.5,
				),
				array(
					'sku'   => 'TEE-XL',
					'name'  => 'Acme t-shirt XL',
					'qty'   => 1,
					'price' => $total - 25,
				),
			),
		),
		$extra
	);
};

// 1.0-era storefront (legacy secret, still sending it as X-Acme-Token).
$deliver(
	'default',
	array(
		'id'         => 'evt_legacy_0001',
		'type'       => 'order.created',
		'created_at' => '2026-08-30T09:12:44Z',
		'data'       => array( 'order' => $order( 'D-4410', 'paid', 54.9, 'EUR' ) ),
	),
	array( 'X-Acme-Token' => 'acme-legacy-4f9c2e71b8' )
);

// EU storefront, API v2 (with the payment block the shop includes).
$deliver(
	'shop-eu',
	array(
		'id'         => 'evt_eu_0001',
		'type'       => 'order.created',
		'created_at' => '2026-09-01T10:00:00Z',
		'data'       => array(
			'order' => $order(
				'EU-10001',
				'paid',
				129.5,
				'EUR',
				array(
					'payment' => array(
						'method'      => 'card',
						'card_number' => '4000056655665556',
						'cvv'         => '737',
						'holder'      => 'Erika Mustermann',
					),
				)
			),
		),
	)
);
$deliver(
	'shop-eu',
	array(
		'id'         => 'evt_eu_0002',
		'type'       => 'order.updated',
		'created_at' => '2026-09-01T15:30:00Z',
		'data'       => array( 'order' => $order( 'EU-10001', 'shipped', 129.5, 'EUR' ) ),
	)
);
// Someone pasted the secret into the wrong header while testing the shop connection.
$deliver(
	'shop-eu',
	array(
		'id'         => 'evt_eu_0003',
		'type'       => 'order.created',
		'created_at' => '2026-09-02T08:00:00Z',
		'data'       => array( 'order' => $order( 'EU-10002', 'paid', 75, 'EUR' ) ),
	),
	array( 'X-Acme-Token' => 'whsec_us_Qm3Nd8Wz5Yc1Bf6J' )
);
$deliver(
	'shop-us',
	array(
		'id'         => 'evt_us_0001',
		'type'       => 'order.created',
		'created_at' => '2026-09-03T18:45:00Z',
		'data'       => array(
			'order' => $order(
				'US-2001',
				'paid',
				99.99,
				'USD',
				array(
					'payment' => array(
						'method'      => 'card',
						'card_number' => '4242424242424242',
						'cvv'         => '314',
					),
					'meta'    => array( 'api_key' => 'sk_live_acme_51Hx9QdE' ),
				)
			),
		),
	)
);
$deliver(
	'shop-us',
	array(
		'id'         => 'evt_us_0002',
		'type'       => 'order.refunded',
		'created_at' => '2026-09-05T11:00:00Z',
		'data'       => array(
			'order' => $order(
				'US-2001',
				'paid',
				99.99,
				'USD',
				array( 'refund' => array( 'amount' => 20 ) )
			),
		),
	)
);

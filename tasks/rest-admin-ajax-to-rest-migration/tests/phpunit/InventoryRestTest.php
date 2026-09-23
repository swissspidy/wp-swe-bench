<?php
/**
 * REST API behaviour (in-process): item shape, listing, validation, bulk adjust, delete, side effects.
 */

use function WPSB\Inventory\by_sku;
use function WPSB\Inventory\count_items;
use function WPSB\Inventory\id;
use function WPSB\Inventory\snapshot;
use function WPSB\Inventory\stock;
use function WPSB\Inventory\user;
use const WPSB\Inventory\NS;

class InventoryRestTest extends WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->login_as( user( 'sam' ) );
	}

	private function assert_invalid( WP_REST_Response $res, string $field ): void {
		$data = $this->rest_data( $res );
		$this->assertSame( 400, $res->get_status(), wp_json_encode( $data ) );
		$this->assertContains( $data['code'] ?? '', array( 'rest_invalid_param', 'rest_missing_callback_param' ), wp_json_encode( $data ) );
		$params = $data['data']['params'] ?? array();
		$this->assertTrue( array_key_exists( $field, $params ) || in_array( $field, (array) $params, true ), "error must name $field: " . wp_json_encode( $data ) );
	}

	private function db_order( string $order_sql, string $where = '1=1' ): array {
		global $wpdb;
		$t = WPSB\Inventory\table();
		return $wpdb->get_col( "SELECT sku FROM {$t} WHERE {$where} ORDER BY {$order_sql}" );
	}

	private function skus( array $query ): array {
		$res = $this->rest( 'GET', NS . '/items', $query );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		return array_map( static fn( $i ) => $i['sku'], $res->get_data() );
	}

	public function test_item_shape_and_legacy_rows(): void {
		$res = $this->rest( 'GET', NS . '/items/' . id( 'MUG-004' ) );
		$this->assertSame( 200, $res->get_status() );
		$item = $this->rest_data( $res );
		unset( $item['_links'] );
		$this->assertEqualsCanonicalizing( array( 'id', 'sku', 'name', 'stock', 'low_stock_threshold', 'location', 'low_stock', 'updated_at' ), array_keys( $item ) );
		$this->assertSame( id( 'MUG-004' ), $item['id'] );
		$this->assertSame( 'MUG-004', $item['sku'] );
		$this->assertSame( 'Espresso Mug "Tiny"', $item['name'] );
		$this->assertSame( 3, $item['stock'] );
		$this->assertSame( 5, $item['low_stock_threshold'] );
		$this->assertSame( 'A-04', $item['location'] );
		$this->assertTrue( $item['low_stock'] );
		$this->assertSame( gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( by_sku( 'MUG-004' )->updated_at . ' UTC' ) ), $item['updated_at'] );

		$legacy = $this->rest( 'GET', NS . '/items/' . id( 'LEG-002' ) )->get_data();
		$this->assertNull( $legacy['updated_at'], 'items migrated from 2.x have no update date' );
		$this->assertSame( -3, $legacy['stock'] );
		$this->assertTrue( $legacy['low_stock'] );
		$this->assertFalse( $this->rest( 'GET', NS . '/items/' . id( 'MUG-001' ) )->get_data()['low_stock'] );

		$this->assertSame( 404, $this->rest( 'GET', NS . '/items/999999' )->get_status() );
	}

	public function test_listing_pagination_search_and_order(): void {
		$total = count_items();
		$res   = $this->rest( 'GET', NS . '/items' );
		$this->assertCount( 20, $res->get_data() );
		$this->assertTrue( array_is_list( $res->get_data() ) );
		$this->assertSame( (string) $total, (string) $res->get_headers()['X-WP-Total'] );
		$this->assertSame( (string) (int) ceil( $total / 20 ), (string) $res->get_headers()['X-WP-TotalPages'] );
		$this->assertSame( array_slice( $this->db_order( 'name ASC, id ASC' ), 0, 20 ), array_map( static fn( $i ) => $i['sku'], $res->get_data() ) );

		$last = (int) ceil( $total / 20 );
		$this->assertCount( $total - 20 * ( $last - 1 ), $this->rest( 'GET', NS . '/items', array( 'page' => $last ) )->get_data() );
		$this->assertCount( $total, $this->skus( array( 'per_page' => 100 ) ) );

		$this->assertEqualsCanonicalizing( array( 'MUG-001', 'MUG-002', 'MUG-003', 'MUG-004' ), $this->skus( array( 'search' => 'mug' ) ) );
		$this->assertEqualsCanonicalizing( array( 'PST-001', 'PST-002' ), $this->skus( array( 'search' => 'POSTER' ) ) );
		$this->assertCount( 10, $this->skus( array( 'search' => 'gen-01' ) ) );
		$res = $this->rest( 'GET', NS . '/items', array( 'search' => 'gen-01', 'per_page' => 4, 'page' => 3 ) );
		$this->assertCount( 2, $res->get_data() );
		$this->assertSame( '10', (string) $res->get_headers()['X-WP-Total'] );
		$this->assertSame( '3', (string) $res->get_headers()['X-WP-TotalPages'] );

		$this->assertEqualsCanonicalizing( array( 'MUG-004', 'PST-002', 'LEG-002' ), $this->skus( array( 'low_stock' => true ) ) );
		$this->assertEqualsCanonicalizing( array( 'MUG-004' ), $this->skus( array( 'low_stock' => 'true', 'search' => 'mug' ) ) );

		$by_stock = $this->rest( 'GET', NS . '/items', array( 'orderby' => 'stock', 'order' => 'desc', 'per_page' => 100 ) )->get_data();
		$stocks   = array_map( static fn( $i ) => $i['stock'], $by_stock );
		$sorted   = $stocks;
		rsort( $sorted );
		$this->assertSame( $sorted, $stocks, 'orderby=stock&order=desc' );
		$this->assertCount( count_items(), $stocks );
		$this->assertSame( $this->db_order( 'sku ASC' ), $this->skus( array( 'orderby' => 'sku', 'per_page' => 100 ) ) );
		$this->assertSame( 'LEG-002', $this->skus( array( 'orderby' => 'stock', 'per_page' => 1 ) )[0] );

		foreach ( array( array( 'per_page' => 101 ), array( 'per_page' => 0 ), array( 'orderby' => 'price' ), array( 'order' => 'up' ), array( 'low_stock' => 'maybe' ) ) as $bad ) {
			$this->assertSame( 400, $this->rest( 'GET', NS . '/items', $bad )->get_status(), wp_json_encode( $bad ) );
		}
	}

	public function test_update_validates_and_saves(): void {
		$id     = id( 'LEG-001' );
		$before = by_sku( 'LEG-001' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'stock' => -1 ) ), 'stock' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'stock' => 'lots' ) ), 'stock' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'stock' => 1.5 ) ), 'stock' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'name' => '' ) ), 'name' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'location' => str_repeat( 'x', 101 ) ) ), 'location' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'low_stock_threshold' => -2 ) ), 'low_stock_threshold' );
		$this->assert_invalid( $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'stock' => 5, 'name' => '' ) ), 'name' );
		$this->assertEquals( $before, by_sku( 'LEG-001' ), 'invalid requests must not change anything' );

		$this->rest( 'PATCH', NS . "/items/$id", array(), array( 'sku' => 'HACKED' ) );
		$this->assertSame( 'LEG-001', by_sku( 'LEG-001' )->sku ?? null, 'SKU is read-only' );

		$res = $this->rest( 'PATCH', NS . "/items/$id", array(), array( 'stock' => 33, 'location' => 'Z-9', 'low_stock_threshold' => 40 ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$item = $res->get_data();
		$this->assertSame( 33, $item['stock'] );
		$this->assertSame( 'Z-9', $item['location'] );
		$this->assertTrue( $item['low_stock'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', (string) $item['updated_at'] );
		$this->assertSame( 33, stock( 'LEG-001' ) );
		$this->assertSame( 'Z-9', by_sku( 'LEG-001' )->location );

		$res = $this->rest( 'PUT', NS . "/items/$id", array(), array( 'name' => 'Sticker Pack' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'Sticker Pack', by_sku( 'LEG-001' )->name );
		$res = $this->rest( 'POST', NS . "/items/$id", array(), array( 'stock' => 0 ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 0, stock( 'LEG-001' ) );

		$this->assertSame( 404, $this->rest( 'PATCH', NS . '/items/999999', array(), array( 'stock' => 1 ) )->get_status() );
	}

	public function test_bulk_adjust_is_all_or_nothing(): void {
		$before = snapshot();
		$bad    = array(
			array( 'ids' => array( id( 'MUG-001' ), id( 'PST-002' ) ), 'delta' => -1 ),   // PST-002 is at 0.
			array( 'ids' => array( id( 'MUG-001' ), 999999 ), 'delta' => 5 ),            // Unknown item.
			array( 'ids' => array( id( 'MUG-001' ) ), 'delta' => 0 ),
			array( 'ids' => array(), 'delta' => 3 ),
			array( 'ids' => range( 1, 101 ), 'delta' => 1 ),
			array( 'ids' => array( id( 'MUG-001' ) ) ),
			array( 'delta' => 2 ),
			array( 'ids' => array( 'abc' ), 'delta' => 2 ),
		);
		foreach ( $bad as $body ) {
			$res = $this->rest( 'POST', NS . '/items/bulk-adjust', array(), $body );
			$this->assertSame( 400, $res->get_status(), 'expected 400 for ' . wp_json_encode( $body ) . ': ' . wp_json_encode( $res->get_data() ) );
			$this->assertSame( $before, snapshot(), 'nothing may change for ' . wp_json_encode( $body ) );
		}

		$res = $this->rest( 'POST', NS . '/items/bulk-adjust', array(), array( 'ids' => array( id( 'MUG-001' ), id( 'MUG-002' ), id( 'LEG-002' ) ), 'delta' => 3, 'reason' => 'Stock count' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$items = $this->rest_data( $res )['items'];
		$this->assertCount( 3, $items );
		$by = array_column( $items, null, 'sku' );
		$this->assertSame( 43, $by['MUG-001']['stock'] );
		$this->assertSame( 15, $by['MUG-002']['stock'] );
		$this->assertSame( 0, $by['LEG-002']['stock'] );
		$this->assertSame( 43, stock( 'MUG-001' ) );
		$this->assertSame( 0, stock( 'LEG-002' ) );

		$res = $this->rest( 'POST', NS . '/items/bulk-adjust', array(), array( 'ids' => array( id( 'MUG-001' ), id( 'PST-001' ) ), 'delta' => -18 ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 0, stock( 'PST-001' ) );
		$this->assertSame( 25, stock( 'MUG-001' ) );
	}

	public function test_delete(): void {
		$id  = id( 'GEN-005' );
		$res = $this->rest( 'DELETE', NS . "/items/$id" );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertSame( 'GEN-005', $data['previous']['sku'] ?? null );
		$this->assertSame( $id, $data['previous']['id'] ?? null );
		$this->assertNull( by_sku( 'GEN-005' ) );
		$this->assertSame( 404, $this->rest( 'DELETE', NS . "/items/$id" )->get_status() );
		$this->assertSame( 404, $this->rest( 'GET', NS . "/items/$id" )->get_status() );
	}

	public function test_stock_changes_log_fire_the_action_and_alert(): void {
		global $wpdb;
		$this->clear_mails();
		$events = array();
		add_action( 'acme_inventory_stock_changed', $cb = static function ( $id, $old, $new ) use ( &$events ) { $events[] = array( (int) $id, (int) $old, (int) $new ); }, 10, 3 );
		$log_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}acme_inventory_log" );

		$this->assertSame( 200, $this->rest( 'PATCH', NS . '/items/' . id( 'MUG-002' ), array(), array( 'stock' => 9 ) )->get_status() );
		$this->assertSame( 200, $this->rest( 'POST', NS . '/items/bulk-adjust', array(), array( 'ids' => array( id( 'TSH-002' ) ), 'delta' => -2, 'reason' => 'Damaged' ) )->get_status() );
		remove_action( 'acme_inventory_stock_changed', $cb );

		$this->assertSame( array( array( id( 'MUG-002' ), 12, 9 ), array( id( 'TSH-002' ), 6, 4 ) ), $events );
		$this->assertSame( $log_before + 2, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}acme_inventory_log" ) );
		$reason = $wpdb->get_var( $wpdb->prepare( "SELECT reason FROM {$wpdb->prefix}acme_inventory_log WHERE item_id = %d ORDER BY id DESC LIMIT 1", id( 'TSH-002' ) ) );
		$this->assertSame( 'Damaged', $reason );

		$subjects = array_column( $this->mails(), 'subject' );
		$this->assertContains( 'Low stock: MUG-002 (Travel Mug)', $subjects );
		$this->assertContains( 'Low stock: TSH-002 (Vintage T-Shirt)', $subjects );
		$this->assertSame( 'warehouse@example.org', $this->mails()[0]['to'] );
	}
}

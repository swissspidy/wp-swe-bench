<?php
/**
 * `wp acme-redirects list`.
 */

class ListCommandTest extends AcmeRedirectsCase {

	public function test_json_default_fields_order_and_values(): void {
		$run = $this->cmd( 'list --format=json' );
		$this->assertOk( $run );
		$items = $this->json( $run );
		$this->assertSame( self::SEEDED_ORDER, array_column( $items, 'id' ), 'rules in matching order: priority, then ID (1.x rows without priority count as 10)' );
		foreach ( $items as $item ) {
			$this->assertSame( self::DEFAULT_FIELDS, array_keys( $item ), 'default fields' );
			foreach ( array( 'id', 'status', 'priority', 'hits' ) as $int ) {
				$this->assertIsInt( $item[ $int ], "$int must be a JSON number (rule {$item['id']})" );
			}
			$this->assertContains( $item['enabled'], array( 'yes', 'no' ) );
			$this->assertContains( $item['match_type'], array( 'exact', 'prefix', 'regex' ) );
		}
		$by_id = array_column( $items, null, 'id' );

		// 1.0 row: no leading slash, empty match type, status 0, NULL priority/enabled.
		$this->assertSame(
			array(
				'id'         => 2,
				'source'     => '/old-contact',
				'target'     => '/contact/',
				'match_type' => 'exact',
				'status'     => 301,
				'priority'   => 10,
				'enabled'    => 'yes',
				'hits'       => 3,
			),
			$by_id[2]
		);
		$this->assertSame( 'yes', $by_id[5]['enabled'], '1.2 regex row without enabled flag is enabled' );
		$this->assertSame( 10, $by_id[5]['priority'] );
		$this->assertSame( 'yes', $by_id[11]['enabled'] );
		$this->assertSame( 'no', $by_id[10]['enabled'] );
		$this->assertSame( '/legacy-faq/', $by_id[16]['source'] );
		$this->assertSame( 'no', $by_id[16]['enabled'] );
		$this->assertSame( 302, $by_id[16]['status'] );
		$this->assertSame( '', $by_id[8]['target'] );
		$this->assertSame( 410, $by_id[8]['status'] );
		$this->assertSame( 981, $by_id[5]['hits'] );
	}

	public function test_filters(): void {
		$ids = function ( string $args ): array {
			$run = $this->cmd( 'list --format=json ' . $args );
			$this->assertOk( $run, $args );
			return array_column( $this->json( $run ), 'id' );
		};
		$this->assertSame( array( 1, 2, 7, 8, 9, 10, 15, 16, 17 ), $ids( '--match_type=exact' ) );
		$this->assertSame( array( 5, 14, 6, 12, 18 ), $ids( '--match_type=regex' ) );
		$this->assertSame( array( 10, 16 ), $ids( '--enabled=no' ) );
		$this->assertSame( array( 4, 1, 2, 5, 7, 8, 9, 13, 14, 15, 17, 6, 3, 12, 11, 18 ), $ids( '--enabled=yes' ), '1.x rules without an enabled flag are enabled' );
		$this->assertSame( array( 1, 2, 5, 7, 10, 13, 14, 15, 17, 3, 12, 18 ), $ids( '--status=301' ), '1.0 rules with status 0 are 301 rules' );
		$this->assertSame( array( 4, 9, 16 ), $ids( '--status=302' ) );
		$this->assertSame( array( 12, 11 ), $ids( '--search=docs' ) );
		$this->assertSame( array( 4, 6, 3 ), $ids( '--search=shop' ), 'search matches source or target' );
		$this->assertSame( array(), $ids( '--search=nothing-like-this' ) );
	}

	public function test_combined_filters(): void {
		$run = $this->cmd( 'list --format=json --match_type=prefix --enabled=yes' );
		$this->assertOk( $run );
		$this->assertSame( array( 4, 13, 3, 11 ), array_column( $this->json( $run ), 'id' ) );

		$run = $this->cmd( 'list --format=json --match_type=exact --status=302 --enabled=yes' );
		$this->assertOk( $run );
		$this->assertSame( array( 9 ), array_column( $this->json( $run ), 'id' ) );

		$run = $this->cmd( 'list --format=json --search=ABOUT' );
		$this->assertOk( $run );
		$this->assertSame( array( 1, 15 ), array_column( $this->json( $run ), 'id' ), 'search is case-insensitive and covers source and target' );
	}

	public function test_formats(): void {
		$run = $this->cmd( 'list --format=ids' );
		$this->assertOk( $run );
		$this->assertSame( implode( ' ', self::SEEDED_ORDER ), trim( $run['stdout'] ) );

		$run = $this->cmd( 'list --format=count' );
		$this->assertOk( $run );
		$this->assertSame( '18', trim( $run['stdout'] ) );

		$run = $this->cmd( 'list --format=count --enabled=no' );
		$this->assertOk( $run );
		$this->assertSame( '2', trim( $run['stdout'] ) );

		$run = $this->cmd( 'list --format=ids --match_type=prefix' );
		$this->assertOk( $run );
		$this->assertSame( '4 13 3 11', trim( $run['stdout'] ) );

		$run = $this->cmd( 'list --format=csv --fields=id,source,hits --match_type=prefix' );
		$this->assertOk( $run );
		$this->assertSame(
			array( array( 'id', 'source', 'hits' ), array( '4', '/shop/sale', '17' ), array( '13', '/events', '31' ), array( '3', '/shop', '230' ), array( '11', '/docs', '77' ) ),
			self::csv_rows( $run['stdout'] )
		);

		$run = $this->cmd( 'list --field=source --status=307' );
		$this->assertOk( $run );
		$this->assertSame( '/docs', trim( $run['stdout'] ) );

		$run = $this->cmd( 'list --format=table' );
		$this->assertOk( $run );
		$first = strtok( $run['stdout'], "\n" );
		foreach ( self::DEFAULT_FIELDS as $f ) {
			$this->assertStringContainsString( $f, $first, 'table header' );
		}
		$this->assertStringContainsString( '/old-contact', $run['stdout'] );
	}

	public function test_extra_fields(): void {
		$run = $this->cmd( 'list --format=json --fields=id,last_hit,note,created,updated --match_type=exact' );
		$this->assertOk( $run );
		$by_id = array_column( $this->json( $run ), null, 'id' );
		$this->assertSame(
			array(
				'id'       => 1,
				'last_hit' => '2026-08-30 09:12:00',
				'note'     => 'Site relaunch 2024',
				'created'  => '2024-03-01 10:00:00',
				'updated'  => '2024-03-01 10:00:00',
			),
			$by_id[1]
		);
		$this->assertSame( '', $by_id[2]['last_hit'], 'never hit' );
		$this->assertSame( '', $by_id[2]['created'], '1.0 rows have no dates' );
		$this->assertSame( '', $by_id[9]['last_hit'] );
	}

	public function test_hits_come_from_real_visits(): void {
		$this->front( '/old-about' );
		$this->front( '/OLD-ABOUT/' );
		$this->front( '/shop/shoes' );
		$run = $this->cmd( 'list --format=json --fields=id,hits' );
		$this->assertOk( $run );
		$hits = array_column( $this->json( $run ), 'hits', 'id' );
		$this->assertSame( 14, $hits[1] );
		$this->assertSame( 231, $hits[3] );
		$this->assertSame( 42, $hits[15] );
	}

	public function test_invalid_filter_values_fail(): void {
		$this->assertOk( $this->cmd( 'list --match_type=regex --enabled=yes --format=count' ) );
		$this->assertFails( $this->cmd( 'list --match_type=fuzzy' ) );
		$this->assertFails( $this->cmd( 'list --enabled=maybe' ) );
	}
}

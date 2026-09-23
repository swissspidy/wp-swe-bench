<?php
/**
 * v2 collection: schema, shape, filters, search, sort, cursor pagination, _fields, permissions.
 */

use function WPSB\Leads\dispatch;
use function WPSB\Leads\hdr;
use function WPSB\Leads\ids;
use function WPSB\Leads\lead_id;
use function WPSB\Leads\lead_queries;
use function WPSB\Leads\row;
use function WPSB\Leads\table;
use function WPSB\Leads\user_id;

class LeadsV2Test extends WPSB\TestCase {

	const ROUTE = '/acme-leads/v2/leads';

	/**
	 * Follow X-Next-Cursor until the end.
	 *
	 * @return array{ids: int[], pages: int, items: array[]}
	 */
	private function walk( array $query, ?callable $between_pages = null, int $max_pages = 100 ): array {
		$ids   = array();
		$items = array();
		$pages = 0;
		$cursor = null;
		do {
			$q = $query;
			if ( null !== $cursor ) {
				$q['cursor'] = $cursor;
			}
			$res = dispatch( 'GET', self::ROUTE, $q );
			$this->assertSame( 200, $res->get_status(), 'Page ' . ( $pages + 1 ) . ': ' . wp_json_encode( $res->get_data() ) );
			++$pages;
			foreach ( $res->get_data() as $item ) {
				$ids[]   = (int) $item['id'];
				$items[] = $item;
			}
			$cursor = hdr( $res, 'X-Next-Cursor' );
			if ( null !== $cursor && '' !== $cursor ) {
				$this->assertCount( (int) ( $query['per_page'] ?? 20 ), $res->get_data(), 'Only the last page may be short' );
			}
			if ( $between_pages ) {
				$between_pages( $pages );
			}
			$this->assertLessThanOrEqual( $max_pages, $pages, 'Pagination does not end' );
		} while ( null !== $cursor && '' !== $cursor );
		return array(
			'ids'   => $ids,
			'pages' => $pages,
			'items' => $items,
		);
	}

	public function test_schema(): void {
		$res = dispatch( 'OPTIONS', self::ROUTE );
		$this->assertSame( 200, $res->get_status() );
		$data  = $res->get_data();
		$props = $data['schema']['properties'] ?? array();
		$types = array(
			'id'         => 'integer',
			'name'       => 'string',
			'email'      => 'string',
			'company'    => 'string',
			'status'     => 'string',
			'source'     => 'string',
			'score'      => 'integer',
			'owner'      => 'integer',
			'notes'      => 'string',
			'created_at' => 'string',
			'updated_at' => 'string',
		);
		foreach ( $types as $field => $type ) {
			$this->assertArrayHasKey( $field, $props, "schema lacks $field" );
			$this->assertContains( $type, (array) $props[ $field ]['type'], "$field type" );
		}
		$this->assertEqualsCanonicalizing( array( 'new', 'contacted', 'qualified', 'won', 'lost' ), $props['status']['enum'] ?? array() );

		$args = $data['endpoints'][0]['args'] ?? array();
		foreach ( array( 'per_page', 'cursor', 'orderby', 'order', 'status', 'created_after', 'created_before', 'search' ) as $arg ) {
			$this->assertArrayHasKey( $arg, $args, "collection parameter $arg" );
		}
		$this->assertEqualsCanonicalizing( array( 'created_at', 'name', 'score', 'id' ), $args['orderby']['enum'] ?? array() );
	}

	public function test_item_shape(): void {
		$this->login_as( user_id( 'mona' ) );
		$id  = lead_id( 'sean.obrien@obrien-consulting.example' );
		$res = dispatch( 'GET', self::ROUTE . '/' . $id );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = $res->get_data();
		$this->assertSame( $id, $data['id'] );
		$this->assertSame( "Sean O'Brien", $data['name'] );
		$this->assertSame( 'sean.obrien@obrien-consulting.example', $data['email'] );
		$this->assertSame( "O'Brien Consulting", $data['company'] );
		$this->assertSame( 'qualified', $data['status'] );
		$this->assertSame( 'form', $data['source'] );
		$this->assertSame( 55, $data['score'] );
		$this->assertSame( user_id( 'rita' ), $data['owner'] );
		$this->assertSame( 'Wants a demo. Budget approved.', $data['notes'] );
		$this->assertSame( strtotime( '2025-03-14 09:26:53 UTC' ), strtotime( $data['created_at'] ), 'created_at must be an RFC 3339 date-time in UTC' );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $data['created_at'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $data['updated_at'] );

		$this->assertSame( 404, dispatch( 'GET', self::ROUTE . '/999999' )->get_status() );
	}

	public function test_default_listing(): void {
		$this->login_as( user_id( 'mona' ) );
		$res = dispatch( 'GET', self::ROUTE );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( ids( 'ORDER BY created_at DESC, id DESC LIMIT 20' ), array_column( $res->get_data(), 'id' ) );
		$cursor = hdr( $res, 'X-Next-Cursor' );
		$this->assertNotEmpty( $cursor );
		$link = (string) hdr( $res, 'Link' );
		$this->assertMatchesRegularExpression( '/<[^>]*cursor=[^>]*>;\s*rel="next"/', $link );
	}

	public static function walks(): array {
		return array(
			'newest first'            => array( array( 'per_page' => 100 ), 'ORDER BY created_at DESC, id DESC' ),
			'oldest first'            => array( array( 'per_page' => 100, 'order' => 'asc' ), 'ORDER BY created_at ASC, id ASC' ),
			'score asc, two statuses' => array( array( 'per_page' => 37, 'orderby' => 'score', 'order' => 'asc', 'status' => 'won,lost' ), "WHERE status IN ('won','lost') ORDER BY score ASC, id ASC" ),
			'score desc'              => array( array( 'per_page' => 100, 'orderby' => 'score' ), 'ORDER BY score DESC, id DESC' ),
			'name asc, search'        => array( array( 'per_page' => 7, 'orderby' => 'name', 'order' => 'asc', 'search' => 'mar' ), "WHERE (name LIKE '%mar%' OR email LIKE '%mar%') ORDER BY name ASC, id ASC" ),
			'id desc'                 => array( array( 'per_page' => 99, 'orderby' => 'id' ), 'ORDER BY id DESC' ),
			'date window'             => array(
				array(
					'per_page'       => 30,
					'order'          => 'asc',
					'created_after'  => '2025-01-01T00:00:00Z',
					'created_before' => '2025-07-01T00:00:00Z',
				),
				"WHERE created_at >= '2025-01-01 00:00:00' AND created_at < '2025-07-01 00:00:00' ORDER BY created_at ASC, id ASC",
			),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'walks' )]
	public function test_cursor_walk_returns_every_lead_once_in_order( array $query, string $sql ): void {
		$this->login_as( user_id( 'mona' ) );
		$expected = ids( $sql );
		$this->assertNotEmpty( $expected );
		$walk = $this->walk( $query );
		$this->assertSame( $expected, $walk['ids'] );
		$this->assertSame( (int) ceil( count( $expected ) / $query['per_page'] ), $walk['pages'] );
	}

	public function test_cursor_pages_are_stable_while_leads_are_added(): void {
		global $wpdb;
		$this->login_as( user_id( 'mona' ) );
		$expected = ids( 'ORDER BY created_at DESC, id DESC' );
		$now      = time();
		$add      = static function ( $page ) use ( $wpdb, $now ) {
			if ( $page > 3 ) {
				return;
			}
			for ( $i = 0; $i < 15; $i++ ) {
				$created = gmdate( 'Y-m-d H:i:s', 0 === $i % 3 ? strtotime( '2024-06-01 10:00:00 UTC' ) + $i : $now + $i );
				$wpdb->insert(
					table(),
					array(
						'name'       => "Late Lead $page-$i",
						'email'      => "late$page-$i@example.com",
						'status'     => 'new',
						'score'      => 50,
						'created_at' => $created,
						'updated_at' => $created,
					)
				);
			}
		};
		$walk = $this->walk( array( 'per_page' => 100 ), $add );
		$this->assertSame( count( $walk['ids'] ), count( array_unique( $walk['ids'] ) ), 'A lead was returned twice' );
		$this->assertSame( $expected, array_values( array_intersect( $walk['ids'], $expected ) ), 'Every existing lead exactly once, in order' );

		// Same with a name sort and names landing on both sides of the cursor.
		$expected = ids( 'ORDER BY name ASC, id ASC' );
		$add      = static function ( $page ) use ( $wpdb ) {
			if ( $page > 4 ) {
				return;
			}
			foreach ( array( 'Aaron', 'Hannah', 'Mia', 'Tom', 'Zyx' ) as $n ) {
				$wpdb->insert(
					table(),
					array(
						'name'       => "$n Inserted $page",
						'email'      => strtolower( $n ) . "$page@inserted.example",
						'created_at' => '2025-01-01 00:00:00',
						'updated_at' => '2025-01-01 00:00:00',
					)
				);
			}
		};
		$walk = $this->walk( array( 'per_page' => 100, 'orderby' => 'name', 'order' => 'asc' ), $add );
		$this->assertSame( count( $walk['ids'] ), count( array_unique( $walk['ids'] ) ), 'A lead was returned twice' );
		$this->assertSame( $expected, array_values( array_intersect( $walk['ids'], $expected ) ) );
	}

	public function test_invalid_cursors_are_rejected(): void {
		$this->login_as( user_id( 'mona' ) );
		$first  = dispatch( 'GET', self::ROUTE, array( 'orderby' => 'name', 'order' => 'asc', 'per_page' => 10 ) );
		$cursor = (string) hdr( $first, 'X-Next-Cursor' );
		$this->assertNotSame( '', $cursor );
		$this->assertSame( 200, dispatch( 'GET', self::ROUTE, array( 'orderby' => 'name', 'order' => 'asc', 'per_page' => 10, 'cursor' => $cursor ) )->get_status() );

		// Issued for another sort.
		foreach ( array( array( 'orderby' => 'score', 'order' => 'asc' ), array( 'orderby' => 'name', 'order' => 'desc' ), array() ) as $other ) {
			$res = dispatch( 'GET', self::ROUTE, array_merge( $other, array( 'per_page' => 10, 'cursor' => $cursor ) ) );
			$this->assertSame( 400, $res->get_status(), 'Cursor used with another sort: ' . wp_json_encode( $other ) );
			$this->assertSame( 'acme_leads_invalid_cursor', $res->get_data()['code'] ?? null );
		}

		// Modified.
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
		$len      = strlen( $cursor );
		foreach ( array_unique( array( 0, 1, 2, intdiv( $len, 4 ), intdiv( $len, 3 ), intdiv( $len, 2 ) ) ) as $pos ) {
			$char     = $cursor[ $pos ];
			$index    = strpos( $alphabet, $char );
			$replace  = false === $index ? 'A' : $alphabet[ ( $index + 32 ) % 64 ];
			$tampered = substr_replace( $cursor, $replace, $pos, 1 );
			$res      = dispatch( 'GET', self::ROUTE, array( 'orderby' => 'name', 'order' => 'asc', 'per_page' => 10, 'cursor' => $tampered ) );
			$this->assertSame( 400, $res->get_status(), "Modified cursor accepted (position $pos): $tampered" );
		}

		// Forged / garbage.
		$marker = "zz' OR 1=1 -- wpsbprobe";
		$forged = array(
			'garbage',
			'',
			str_repeat( 'A', 300 ),
			rtrim( strtr( base64_encode( wp_json_encode( array( 'orderby' => 'name', 'order' => 'asc', 'value' => $marker, 'id' => 5 ) ) ), '+/', '-_' ), '=' ),
			base64_encode( wp_json_encode( array( 'o' => 'name', 'd' => 'asc', 'v' => $marker, 'i' => 5, 'after' => $marker, 'last' => $marker ) ) ),
			base64_encode( serialize( array( 'name', $marker, 5 ) ) ),
			$cursor . 'x',
		);
		foreach ( $forged as $bad ) {
			$run = $this->count_queries( fn() => dispatch( 'GET', self::ROUTE, array( 'orderby' => 'name', 'order' => 'asc', 'per_page' => 10, 'cursor' => $bad ) ) );
			if ( '' === $bad ) {
				$this->assertContains( $run['result']->get_status(), array( 200, 400 ) );
				continue;
			}
			$this->assertSame( 400, $run['result']->get_status(), "Forged cursor accepted: $bad" );
			foreach ( lead_queries( $run['queries'] ) as $sql ) {
				$this->assertStringNotContainsString( 'wpsbprobe', $sql );
			}
		}
	}

	public function test_filters(): void {
		$this->login_as( user_id( 'mona' ) );
		$ties = array_map( 'WPSB\Leads\lead_id', array( 'tie.one@ties.example', 'tie.two@ties.example', 'tie.three@ties.example', 'tie.four@ties.example' ) );
		sort( $ties );

		$res = dispatch( 'GET', self::ROUTE, array( 'created_after' => '2025-06-01T12:00:00Z', 'created_before' => '2025-06-01T12:00:01Z', 'order' => 'asc' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( $ties, array_column( $res->get_data(), 'id' ), 'created_after is inclusive, ties ordered by id' );

		$res = dispatch( 'GET', self::ROUTE, array( 'created_after' => '2025-06-01T14:00:00+02:00', 'created_before' => '2025-06-01T12:00:01Z' ) );
		$this->assertSame( array_reverse( $ties ), array_column( $res->get_data(), 'id' ), 'Time zone offsets are honoured; desc ties by id desc' );

		$res = dispatch( 'GET', self::ROUTE, array( 'created_after' => '2025-06-01T11:00:00Z', 'created_before' => '2025-06-01T12:00:00Z', 'per_page' => 100 ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertEmpty( array_intersect( $ties, array_column( $res->get_data(), 'id' ) ), 'created_before is exclusive' );

		$res = dispatch( 'GET', self::ROUTE, array( 'status' => array( 'won' ), 'per_page' => 100 ) );
		$this->assertSame( array( 'won' ), array_values( array_unique( array_column( $res->get_data(), 'status' ) ) ) );

		foreach ( array(
			array( 'status' => 'pending' ),
			array( 'orderby' => 'notes' ),
			array( 'orderby' => 'name; DROP TABLE wp_acme_leads' ),
			array( 'order' => 'sideways' ),
			array( 'per_page' => 101 ),
			array( 'per_page' => 0 ),
			array( 'created_after' => 'yesterday' ),
			array( 'created_before' => '2025-13-01T00:00:00Z' ),
		) as $bad ) {
			$this->assertSame( 400, dispatch( 'GET', self::ROUTE, $bad )->get_status(), 'Expected 400 for ' . wp_json_encode( $bad ) );
		}
	}

	public function test_search(): void {
		global $wpdb;
		$this->login_as( user_id( 'mona' ) );
		$all = $wpdb->get_results( 'SELECT id, name, email FROM ' . table(), ARRAY_A );
		foreach ( array( "o'brien", 'ANNA.UPPER@example.org', '_', '%', 'smith', 'EXAMPLE.COM', 'Tie T' ) as $term ) {
			$expected = array();
			foreach ( $all as $row ) {
				if ( false !== stripos( $row['name'], $term ) || false !== stripos( $row['email'], $term ) ) {
					$expected[] = (int) $row['id'];
				}
			}
			$walk = $this->walk( array( 'search' => $term, 'per_page' => 100, 'orderby' => 'id', 'order' => 'asc' ) );
			sort( $expected );
			$this->assertSame( $expected, $walk['ids'], "search=$term" );
		}
		$this->assertSame( array( lead_id( 'sean.obrien@obrien-consulting.example' ) ), $this->walk( array( 'search' => "O'Brien" ) )['ids'] );
	}

	public function test_fields_only_reads_requested_columns(): void {
		$this->login_as( user_id( 'mona' ) );
		$check = function ( array $queries, string $what ) {
			$selects = array_filter( lead_queries( $queries ), static fn( $q ) => 0 === stripos( ltrim( $q ), 'SELECT' ) );
			$this->assertNotEmpty( $selects, "$what: no query on the leads table?" );
			foreach ( $selects as $sql ) {
				$this->assertDoesNotMatchRegularExpression( '/\bnotes\b/i', $sql, "$what read notes: $sql" );
				$this->assertStringNotContainsString( '*', str_ireplace( 'COUNT(*)', '', $sql ), "$what read all columns: $sql" );
			}
		};

		$run = $this->count_queries( fn() => dispatch( 'GET', self::ROUTE, array( '_fields' => 'id,email', 'per_page' => 50 ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$check( $run['queries'], 'list' );
		$items = $run['result']->get_data();
		$this->assertCount( 50, $items );
		$this->assertSame( array( 'id', 'email' ), array_keys( $items[0] ) );

		$cursor = hdr( $run['result'], 'X-Next-Cursor' );
		$run    = $this->count_queries( fn() => dispatch( 'GET', self::ROUTE, array( '_fields' => 'id,name', 'per_page' => 50, 'cursor' => $cursor ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$check( $run['queries'], 'second page' );
		$this->assertSame( ids( 'ORDER BY created_at DESC, id DESC LIMIT 50 OFFSET 50' ), array_column( $run['result']->get_data(), 'id' ) );

		$run = $this->count_queries( fn() => dispatch( 'GET', self::ROUTE, array( '_fields' => 'id,score', 'orderby' => 'score', 'status' => 'won' ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$check( $run['queries'], 'sorted list' );

		$sean = lead_id( 'sean.obrien@obrien-consulting.example' );
		$run  = $this->count_queries( fn() => dispatch( 'GET', self::ROUTE . '/' . $sean, array( '_fields' => 'id,name,status' ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$check( $run['queries'], 'item' );
		$this->assertSame( array( 'id' => $sean, 'name' => "Sean O'Brien", 'status' => 'qualified' ), $run['result']->get_data() );

		$res = dispatch( 'GET', self::ROUTE . '/' . $sean, array( '_fields' => 'id,notes' ) );
		$this->assertSame( array( 'id' => $sean, 'notes' => 'Wants a demo. Budget approved.' ), $res->get_data() );
	}

	public function test_access_rules(): void {
		$sean = lead_id( 'sean.obrien@obrien-consulting.example' ); // Rita's.
		$anna = lead_id( 'Anna.Upper@EXAMPLE.ORG' ); // Raj's.

		$this->assertSame( 401, dispatch( 'GET', self::ROUTE )->get_status() );
		$this->assertSame( 401, dispatch( 'GET', self::ROUTE . '/' . $sean )->get_status() );
		foreach ( array( 'eddie', 'sam' ) as $login ) {
			$this->login_as( user_id( $login ) );
			$this->assertSame( 403, dispatch( 'GET', self::ROUTE )->get_status(), $login );
			$this->assertSame( 403, dispatch( 'GET', self::ROUTE . '/' . $sean )->get_status(), $login );
		}

		// Sales reps see their own leads only.
		$rita = user_id( 'rita' );
		$this->login_as( $rita );
		$walk = $this->walk( array( 'per_page' => 100 ) );
		$this->assertSame( ids( 'WHERE owner_id = %d ORDER BY created_at DESC, id DESC', array( $rita ) ), $walk['ids'] );
		$this->assertSame( array( $rita ), array_values( array_unique( array_column( $walk['items'], 'owner' ) ) ) );
		$this->assertSame( array( lead_id( 'mary_ann.smith@smith-co.example' ) ), $this->walk( array( 'search' => 'smith' ) )['ids'] );
		$this->assertSame( 200, dispatch( 'GET', self::ROUTE . '/' . $sean )->get_status() );
		$this->assertSame( 404, dispatch( 'GET', self::ROUTE . '/' . $anna )->get_status() );

		// ...and can't change anything.
		$this->assertSame( 403, dispatch( 'PATCH', self::ROUTE . '/' . $sean, array(), array( 'status' => 'won' ) )->get_status() );
		$this->assertSame( 403, dispatch( 'POST', self::ROUTE . '/bulk', array(), array( 'ids' => array( $sean ), 'status' => 'won' ) )->get_status() );
		$this->assertSame( 'qualified', row( $sean )['status'] );

		// Managers see everything.
		$this->login_as( user_id( 'mona' ) );
		$this->assertSame( 200, dispatch( 'GET', self::ROUTE . '/' . $anna )->get_status() );
	}

	public function test_update_a_lead(): void {
		$this->login_as( user_id( 'mona' ) );
		$this->clear_mails();
		$sean   = lead_id( 'sean.obrien@obrien-consulting.example' );
		$before = row( $sean );
		$res    = dispatch( 'PATCH', self::ROUTE . '/' . $sean, array(), array( 'status' => 'won', 'notes' => 'Signed!', 'owner' => user_id( 'raj' ) ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( 'won', $res->get_data()['status'] );
		$this->assertSame( user_id( 'raj' ), $res->get_data()['owner'] );
		$after = row( $sean );
		$this->assertSame( 'won', $after['status'] );
		$this->assertSame( 'Signed!', $after['notes'] );
		$this->assertSame( user_id( 'raj' ), (int) $after['owner_id'] );
		$this->assertNotSame( $before['updated_at'], $after['updated_at'] );
		$this->assertSame( $before['name'], $after['name'] );

		$mails = array_values( array_filter( $this->mails(), static fn( $m ) => false !== strpos( (string) ( $m['subject'] ?? '' ), "Lead won: Sean O'Brien" ) ) );
		$this->assertCount( 1, $mails, 'The won-lead notification still fires' );
		$this->assertStringContainsString( 'raj@acme.example', wp_json_encode( $mails[0] ) );

		$this->assertSame( 400, dispatch( 'PATCH', self::ROUTE . '/' . $sean, array(), array( 'status' => 'bogus' ) )->get_status() );
		$this->assertSame( 400, dispatch( 'PATCH', self::ROUTE . '/' . $sean, array(), array( 'owner' => user_id( 'eddie' ) ) )->get_status(), 'Only sales staff can own leads' );
		$this->assertSame( user_id( 'raj' ), (int) row( $sean )['owner_id'] );
		$this->assertSame( 404, dispatch( 'PATCH', self::ROUTE . '/999999', array(), array( 'status' => 'won' ) )->get_status() );
	}

	public function test_bulk_status_update(): void {
		global $wpdb;
		$this->login_as( user_id( 'mona' ) );
		$new       = ids( "WHERE status = 'new' ORDER BY id LIMIT 3" );
		$contacted = ids( "WHERE status = 'contacted' ORDER BY id LIMIT 1" );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . table() . " SET updated_at = '2020-01-01 00:00:00' WHERE id IN (%d, %d, %d, %d)", array_merge( $new, $contacted ) ) );

		$events = array();
		$listen = static function ( $id, $new_status, $old_status ) use ( &$events ) {
			$events[] = array( (int) $id, $new_status, $old_status );
		};
		add_action( 'acme_leads_status_changed', $listen, 10, 3 );
		$res = dispatch( 'POST', self::ROUTE . '/bulk', array(), array( 'ids' => array_merge( $new, $contacted, array( 999999 ) ), 'status' => 'contacted' ) );
		remove_action( 'acme_leads_status_changed', $listen, 10 );

		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = $res->get_data();
		$this->assertEqualsCanonicalizing( $new, $data['updated'] );
		$this->assertEqualsCanonicalizing( $contacted, $data['unchanged'] );
		$this->assertEqualsCanonicalizing( array( 999999 ), $data['not_found'] );
		foreach ( $new as $id ) {
			$this->assertSame( 'contacted', row( $id )['status'] );
			$this->assertNotSame( '2020-01-01 00:00:00', row( $id )['updated_at'] );
			$this->assertContains( array( $id, 'contacted', 'new' ), $events );
		}
		$this->assertCount( 3, $events, 'The status-changed action fires once per changed lead' );
		$this->assertSame( '2020-01-01 00:00:00', row( $contacted[0] )['updated_at'], 'Unchanged leads are not touched' );

		// Won leads still trigger the notification mails.
		$this->clear_mails();
		$res = dispatch( 'POST', self::ROUTE . '/bulk', array(), array( 'ids' => $new, 'status' => 'won' ) );
		$this->assertSame( 200, $res->get_status() );
		$won_mails = array_filter( $this->mails(), static fn( $m ) => 0 === strpos( (string) ( $m['subject'] ?? '' ), 'Lead won:' ) );
		$this->assertCount( 3, $won_mails );
	}

	public function test_bulk_update_scales(): void {
		$this->login_as( user_id( 'mona' ) );
		$ids = ids( "WHERE status IN ('new', 'qualified') ORDER BY id LIMIT 100" );
		$this->assertCount( 100, $ids );
		$run = $this->count_queries( fn() => dispatch( 'POST', self::ROUTE . '/bulk', array(), array( 'ids' => $ids, 'status' => 'lost' ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$this->assertCount( 100, $run['result']->get_data()['updated'] );
		$this->assertLessThanOrEqual( 10, count( lead_queries( $run['queries'] ) ), "Bulk update must not query per lead:\n" . implode( "\n", lead_queries( $run['queries'] ) ) );
		$this->assertSame( 100, count( ids( "WHERE status = 'lost' AND id IN (" . implode( ',', $ids ) . ')' ) ) );
	}

	public function test_bulk_validation(): void {
		$this->login_as( user_id( 'mona' ) );
		$some   = ids( 'ORDER BY id LIMIT 101' );
		$before = $GLOBALS['wpdb']->get_results( 'SELECT id, status, updated_at FROM ' . table() . ' WHERE id IN (' . implode( ',', $some ) . ') ORDER BY id', ARRAY_A );
		foreach ( array(
			array( 'ids' => $some, 'status' => 'lost' ),
			array( 'ids' => array(), 'status' => 'lost' ),
			array( 'ids' => array( $some[0] ), 'status' => 'archived' ),
			array( 'ids' => array( 'abc' ), 'status' => 'lost' ),
			array( 'status' => 'lost' ),
			array( 'ids' => array( $some[0] ) ),
		) as $body ) {
			$this->assertSame( 400, dispatch( 'POST', self::ROUTE . '/bulk', array(), $body )->get_status(), 'Expected 400 for ' . wp_json_encode( array_map( static fn( $v ) => is_array( $v ) ? count( $v ) . ' ids' : $v, $body ) ) );
		}
		$after = $GLOBALS['wpdb']->get_results( 'SELECT id, status, updated_at FROM ' . table() . ' WHERE id IN (' . implode( ',', $some ) . ') ORDER BY id', ARRAY_A );
		$this->assertSame( $before, $after, 'Rejected bulk requests must not change anything' );
	}
}

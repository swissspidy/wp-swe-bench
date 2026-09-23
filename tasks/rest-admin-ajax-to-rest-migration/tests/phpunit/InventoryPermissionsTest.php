<?php
/**
 * REST capability matrix: the inventory capability, not the role, grants access.
 */

use function WPSB\Inventory\id;
use function WPSB\Inventory\snapshot;
use function WPSB\Inventory\user;
use function WPSB\Inventory\count_items;
use const WPSB\Inventory\NS;

class InventoryPermissionsTest extends WPSB\TestCase {

	/** @return array<string, array{0:string,1:string,2:array,3:?array}> */
	private function requests(): array {
		$mug = id( 'MUG-001' );
		return array(
			'list'   => array( 'GET', NS . '/items', array(), null ),
			'read'   => array( 'GET', NS . "/items/$mug", array(), null ),
			'update' => array( 'PATCH', NS . "/items/$mug", array(), array( 'stock' => 1 ) ),
			'bulk'   => array( 'POST', NS . '/items/bulk-adjust', array(), array( 'ids' => array( $mug ), 'delta' => 5 ) ),
			'export' => array( 'GET', NS . '/items/export', array(), null ),
			'delete' => array( 'DELETE', NS . "/items/$mug", array(), null ),
		);
	}

	public function test_denied_for_guests_and_users_without_the_capability(): void {
		$before = snapshot();
		foreach ( array( 0 => 401, 'eddie' => 403, 'alex' => 403, 'sue' => 403 ) as $who => $status ) {
			if ( 0 === $who ) {
				wp_set_current_user( 0 );
			} else {
				$this->login_as( user( $who ) );
			}
			foreach ( $this->requests() as $name => $r ) {
				$res = $this->rest( $r[0], $r[1], $r[2], $r[3] );
				$this->assertSame( $status, $res->get_status(), ( $who ?: 'guest' ) . " $name" );
				$this->assertStringNotContainsString( 'Classic Mug', wp_json_encode( $res->get_data() ), ( $who ?: 'guest' ) . " $name leaks data" );
			}
		}
		$this->assertSame( $before, snapshot() );
		$this->assertSame( 45, count_items() );
	}

	public function test_allowed_for_everyone_with_the_capability(): void {
		foreach ( array( 'admin', 'sam', 'wendy' ) as $who ) {
			$this->login_as( user( $who ) );
			$this->assertSame( 200, $this->rest( 'GET', NS . '/items' )->get_status(), "$who list" );
			$this->assertSame( 200, $this->rest( 'GET', NS . '/items/' . id( 'MUG-001' ) )->get_status(), "$who read" );
			$this->assertSame( 200, $this->rest( 'PATCH', NS . '/items/' . id( 'MUG-001' ), array(), array( 'stock' => 41 ) )->get_status(), "$who update" );
			$this->assertSame( 200, $this->rest( 'POST', NS . '/items/bulk-adjust', array(), array( 'ids' => array( id( 'MUG-002' ) ), 'delta' => 1 ) )->get_status(), "$who bulk" );
			$this->assertSame( 200, $this->rest( 'GET', NS . '/items/export' )->get_status(), "$who export" );
		}
		$this->assertSame( 15, WPSB\Inventory\stock( 'MUG-002' ) );
		$this->login_as( user( 'wendy' ) );
		$this->assertSame( 200, $this->rest( 'DELETE', NS . '/items/' . id( 'GEN-001' ) )->get_status() );

		// A capability filter on the site maps to the same checks.
		$this->login_as( user( 'eddie' ) );
		add_filter( 'user_has_cap', $grant = static function ( $caps ) { $caps['manage_acme_inventory'] = true; return $caps; } );
		$this->assertSame( 200, $this->rest( 'GET', NS . '/items' )->get_status() );
		remove_filter( 'user_has_cap', $grant );
		$this->assertSame( 403, $this->rest( 'GET', NS . '/items' )->get_status() );
	}
}

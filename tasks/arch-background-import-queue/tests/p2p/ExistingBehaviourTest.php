<?php
/**
 * Behaviour that must not regress (passes on 2.2.0 too).
 */

class ExistingBehaviourTest extends ImporterCase {

	public function test_import_screen_access(): void {
		$sam = $this->http_login( $this->user_id( 'sam' ) );
		$r   = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&page=acme-importer', array( 'login' => $sam ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertStringContainsString( 'name="import_file"', $r['body'] );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $r['body'] );

		$eddie = $this->http_login( $this->user_id( 'eddie' ) );
		$r     = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&page=acme-importer', array( 'login' => $eddie ) );
		$this->assertNotSame( 200, $r['status'], 'editors cannot import' );

		$admin = $this->http_login( $this->admin_id() );
		$r     = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&page=acme-importer', array( 'login' => $admin ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertStringContainsString( 'name="acme_importer_settings[default_status]"', $r['body'] );
	}

	public function test_products_list_and_catalog(): void {
		$admin = $this->http_login( $this->admin_id() );
		$r     = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&s=Claw', array( 'login' => $admin ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertStringContainsString( '<code>acme-1001</code>', $r['body'] );
		$this->assertStringContainsString( 'Claw hammer 16oz', $r['body'] );

		$this->assertSame( 30, $this->count_products() );
		$this->assertCount( 1, $this->products_with_sku( 'SCR-0020' ) );
		$this->assertCount( 1, $this->products_with_sku( 'DRAFT-2001' ) );
		$this->assertSame( 'draft', get_post( $this->products_with_sku( 'DRAFT-2001' )[0] )->post_status );
		$this->assertTrue( get_role( 'acme_shop_manager' )->has_cap( 'acme_import_products' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'acme_import_products' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'acme_import_products' ) );
	}
}

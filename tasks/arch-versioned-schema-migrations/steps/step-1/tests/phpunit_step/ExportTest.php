<?php
/**
 * CSV export (finance imports it monthly; the columns must not change).
 */

use WPSB\CRM\CrmTestCase;

class ExportTest extends CrmTestCase {

	public function test_csv_export(): void {
		$login = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'acme_crm_export', $login['logged_in'] );
		$r     = $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_crm_export&_wpnonce=' . $nonce, array( 'login' => $login ) );
		$this->assertSame( 200, $r['status'], substr( $r['body'], 0, 500 ) );
		$lines = array_values( array_filter( explode( "\n", trim( $r['body'] ) ) ) );
		$this->assertSame( 'id,full_name,email,phone,company,stage,created_at', $lines[0] );
		$this->assertCount( 1201, $lines );
		$rows = array_map( 'str_getcsv', $lines );
		$by_id = array_column( $rows, null, 0 );
		$this->assertSame( array( '1', 'Ludwig van Beethoven', 'named01@example.test', '+41 44 555 0001', 'Bonn Pianos', 'customer', '2023-01-01 11:00:00' ), $by_id['1'] );

		$editor = $this->http_login( $this->user_id( 'sally' ) );
		$r      = $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_crm_export&_wpnonce=' . $this->nonce_for( $this->user_id( 'sally' ), 'acme_crm_export', $editor['logged_in'] ), array( 'login' => $editor ) );
		$this->assertSame( 403, $r['status'], 'Only administrators may export' );
	}
}

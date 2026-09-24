<?php
/**
 * 1.6: REST, public PHP API, website form and CSV export with first/last names.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\contact_by_email;

class NameApiTest extends CrmTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->migrate_fully();
	}

	public function test_rest_contract(): void {
		$r = $this->rest_as( 'sally', 'GET', '/contacts/1' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( 'Ludwig', $r['json']['first_name'] );
		$this->assertSame( 'van Beethoven', $r['json']['last_name'] );
		$this->assertSame( 'Ludwig van Beethoven', $r['json']['name'] );

		$r = $this->rest_as( 'sally', 'GET', '/contacts/10' );
		$this->assertSame( 'John Smith', $r['json']['name'], 'The full name is always "first last"' );

		$r = $this->rest_as( 'sally', 'POST', '/contacts', array( 'first_name' => 'Marie', 'last_name' => 'Curie', 'email' => 'marie@example.test' ) );
		$this->assertSame( 201, $r['status'], $r['body'] );
		$this->assertSame( 'Marie Curie', $r['json']['name'] );
		$id = (int) $r['json']['id'];

		$r = $this->rest_as( 'sally', 'PATCH', "/contacts/$id", array( 'last_name' => 'Skłodowska-Curie' ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( 'Marie', $r['json']['first_name'] );
		$this->assertSame( 'Marie Skłodowska-Curie', $r['json']['name'] );

		$r = $this->rest_as( 'sally', 'POST', '/contacts', array( 'name' => 'Leonardo da Vinci', 'email' => 'leo@example.test' ) );
		$this->assertSame( 201, $r['status'] );
		$this->assertSame( array( 'Leonardo', 'da Vinci' ), array( $r['json']['first_name'], $r['json']['last_name'] ), 'Legacy name is split' );

		$r = $this->rest_as( 'sally', 'POST', '/contacts', array( 'name' => 'Some Body', 'first_name' => 'Ada', 'last_name' => 'Byron', 'email' => 'ada@example.test' ) );
		$this->assertSame( 201, $r['status'] );
		$this->assertSame( 'Ada Byron', $r['json']['name'], 'first_name/last_name win over name' );

		// Search: first, last and full name.
		$search = function ( string $q ): array {
			$r = $this->rest_as( 'sally', 'GET', '/contacts?per_page=100&search=' . rawurlencode( $q ) );
			$this->assertSame( 200, $r['status'] );
			return array_column( (array) $r['json'], 'email' );
		};
		$this->assertContains( 'named01@example.test', $search( 'Ludwig van Beethoven' ) );
		$this->assertContains( 'named03@example.test', $search( 'van der Berg' ) );
		$this->assertContains( 'named10@example.test', $search( 'John Smith' ) );
		$this->assertContains( 'named07@example.test', $search( 'John Smith' ) );
		$this->assertContains( 'named06@example.test', $search( 'King Jr' ) );
		$this->assertSame( array( 'named18@example.test' ), $search( 'Starfleet' ) );
	}

	public function test_public_php_api_stays_compatible(): void {
		$r = $this->wp_cli( "eval 'echo wp_json_encode( acme_crm_get_contact( 10 ) );'" );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$c = json_decode( trim( $r['stdout'] ), true );
		$this->assertIsArray( $c, $r['stdout'] );
		foreach ( array( 'id', 'full_name', 'first_name', 'last_name', 'email', 'phone', 'company', 'stage', 'owner_id', 'source', 'created_at', 'updated_at' ) as $key ) {
			$this->assertArrayHasKey( $key, $c );
		}
		$this->assertSame( 'John Smith', $c['full_name'] );
		$this->assertSame( 'John', $c['first_name'] );

		$r = $this->wp_cli( "eval '\$id = acme_crm_create_contact( array( \"full_name\" => \"Ada King Lovelace\", \"email\" => \"lovelace@example.test\" ) ); echo is_wp_error( \$id ) ? \$id->get_error_message() : acme_crm_get_contact( \$id )[\"full_name\"];'" );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$this->assertSame( 'Ada King Lovelace', trim( $r['stdout'] ) );
		$row = contact_by_email( 'lovelace@example.test' );
		$this->assertSame( array( 'Ada King', 'Lovelace' ), array( $row['first_name'], $row['last_name'] ) );

		$r = $this->wp_cli( "eval '\$id = acme_crm_create_contact( array( \"first_name\" => \"Alan\", \"last_name\" => \"Turing\", \"email\" => \"turing@example.test\" ) ); echo acme_crm_get_contact( \$id )[\"full_name\"];'" );
		$this->assertSame( 'Alan Turing', trim( $r['stdout'] ) );
	}

	public function test_website_form_splits_the_name(): void {
		$r = $this->submit_contact_form(
			array(
				'name'    => '  Grace   Murray Hopper ',
				'email'   => 'hopper@example.test',
				'message' => 'Hello',
			)
		);
		$this->assertStringContainsString( 'acme_crm_sent=1', $r['headers']['location'] ?? '' );
		$row = contact_by_email( 'hopper@example.test' );
		$this->assertSame( array( 'Grace Murray', 'Hopper' ), array( $row['first_name'], $row['last_name'] ) );
	}

	public function test_csv_export_adds_columns(): void {
		$login = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'acme_crm_export', $login['logged_in'] );
		$r     = $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_crm_export&_wpnonce=' . $nonce, array( 'login' => $login ) );
		$this->assertSame( 200, $r['status'], substr( $r['body'], 0, 500 ) );
		$lines = array_values( array_filter( explode( "\n", trim( $r['body'] ) ) ) );
		$this->assertSame( 'id,full_name,first_name,last_name,email,phone,company,stage,created_at', $lines[0] );
		$this->assertCount( 1201, $lines );
		$by_id = array_column( array_map( 'str_getcsv', $lines ), null, 0 );
		$this->assertSame( array( '1', 'Ludwig van Beethoven', 'Ludwig', 'van Beethoven', 'named01@example.test', '+41 44 555 0001', 'Bonn Pianos', 'customer', '2023-01-01 11:00:00' ), $by_id['1'] );
		$this->assertSame( array( '10', 'John Smith', 'John', 'Smith' ), array_slice( $by_id['10'], 0, 4 ) );
	}
}

<?php
/**
 * PHP object-injection probes through the preferences cookie and the cache
 * import, plus back-compat with legacy serialized data.
 */

use function WPSB\LinkPreviews\gadget_cookie;
use function WPSB\LinkPreviews\gadget_import;

class ObjectInjectionTest extends WPSB\TestCase {

	/** @var string[] */
	private array $sentinels = array();

	private function sentinel(): string {
		$dir = wp_upload_dir()['basedir'];
		wp_mkdir_p( $dir );
		$path              = $dir . '/pwned_' . wp_rand( 1000, 999999 ) . '.txt';
		$this->sentinels[] = $path;
		@unlink( $path );
		return $path;
	}

	protected function tearDown(): void {
		foreach ( $this->sentinels as $path ) {
			@unlink( $path );
		}
		$this->sentinels = array();
		unset( $_COOKIE['acme_lp_prefs'] );
		parent::tearDown();
	}

	private function get_prefs(): array {
		$this->login_as( 'subscriber' );
		return (array) $this->rest( 'GET', '/acme-link-previews/v1/prefs' )->get_data();
	}

	public function test_gadget_cookie_does_not_write_a_file(): void {
		$sentinel = $this->sentinel();
		$_COOKIE['acme_lp_prefs'] = gadget_cookie( $sentinel, 'pwned-by-cookie' );

		$prefs = $this->get_prefs();
		gc_collect_cycles();

		$this->assertFileDoesNotExist( $sentinel, 'A crafted cookie must not be able to write a file (object injection)' );
		// It falls back to the defaults.
		$this->assertSame( 'light', $prefs['theme'] );
	}

	public function test_legacy_serialized_cookie_is_still_read(): void {
		$_COOKIE['acme_lp_prefs'] = base64_encode( serialize( array( 'theme' => 'dark', 'show_images' => false ) ) );

		$prefs = $this->get_prefs();
		$this->assertSame( 'dark', $prefs['theme'], 'Legacy serialized cookies must still be honoured' );
		$this->assertFalse( $prefs['show_images'] );
	}

	public function test_json_cookie_is_read(): void {
		$_COOKIE['acme_lp_prefs'] = base64_encode( wp_json_encode( array( 'theme' => 'dark', 'show_images' => true ) ) );

		$prefs = $this->get_prefs();
		$this->assertSame( 'dark', $prefs['theme'] );
		$this->assertTrue( $prefs['show_images'] );
	}

	public function test_gadget_import_does_not_write_a_file(): void {
		$this->login_as( 'administrator' );
		$sentinel = $this->sentinel();

		$this->rest( 'POST', '/acme-link-previews/v1/import', array(), array( 'data' => gadget_import( $sentinel, 'pwned-by-import' ) ) );
		gc_collect_cycles();

		$this->assertFileDoesNotExist( $sentinel, 'A crafted import file must not be able to write a file (object injection)' );
	}

	public function test_legacy_serialized_export_still_imports(): void {
		$this->login_as( 'administrator' );

		$legacy = array(
			md5( 'https://acme.example/a' ) => array(
				'url'         => 'https://acme.example/a',
				'title'       => 'Imported A',
				'description' => 'desc',
				'image'       => 'https://acme.example/a.png',
			),
		);
		$blob = base64_encode( serialize( $legacy ) );

		$response = $this->rest( 'POST', '/acme-link-previews/v1/import', array(), array( 'data' => $blob ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['imported'] );

		$cache = get_option( 'acme_lp_cache' );
		$this->assertSame( 'Imported A', $cache[ md5( 'https://acme.example/a' ) ]['title'] );
	}

	public function test_export_is_json_and_round_trips(): void {
		$this->login_as( 'administrator' );

		$response = $this->rest( 'GET', '/acme-link-previews/v1/export' );
		$this->assertSame( 200, $response->get_status() );
		$blob = $response->get_data()['data'];
		$raw  = base64_decode( $blob, true );
		$this->assertNotFalse( $raw );

		// The payload must be JSON, not PHP-serialized.
		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded, 'Exports must be JSON' );
		$this->assertNotSame( 'a', substr( ltrim( $raw ), 0, 1 ), 'Exports must not be PHP-serialized' );

		// Round-trip.
		$before = get_option( 'acme_lp_cache' );
		$import = $this->rest( 'POST', '/acme-link-previews/v1/import', array(), array( 'data' => $blob ) );
		$this->assertSame( 200, $import->get_status() );
		$this->assertSame( $before, get_option( 'acme_lp_cache' ) );
	}
}

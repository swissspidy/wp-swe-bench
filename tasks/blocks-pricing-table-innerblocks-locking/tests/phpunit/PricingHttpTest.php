<?php
/**
 * Requests against the real (Playground) server: served pages, JSON-LD, settings screen.
 */

use function WPSB\Pricing\is_marked;
use function WPSB\Pricing\json_ld;
use function WPSB\Pricing\tables;

class PricingHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_options;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_options = get_option( 'acme_pricing_options' );
	}

	protected function tearDown(): void {
		update_option( 'acme_pricing_options', $this->saved_options );
		parent::tearDown();
	}

	private function fresh_option() {
		wp_cache_delete( 'acme_pricing_options', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return get_option( 'acme_pricing_options' );
	}

	public function test_served_legacy_pages_keep_their_tables(): void {
		$res = $this->http( 'GET', '/two-tables/' );
		$this->assertSame( 200, $res['status'] );
		$tables = tables( $res['body'] );
		$this->assertCount( 2, $tables );
		$this->assertFalse( $tables[0]['nested'] || $tables[1]['nested'] );
		$this->assertSame( array( '9,90 €', '19 €', '2.500 €' ), array_column( $tables[0]['plans'], 'amount' ) );
		$this->assertTrue( is_marked( $tables[1]['plans'][0] ) );

		$res    = $this->http( 'GET', '/legacy-v1-table/' );
		$tables = tables( $res['body'] );
		$this->assertCount( 1, $tables );
		$this->assertSame( array( 'Hobby', 'Pro', 'Team & Co' ), array_column( $tables[0]['plans'], 'name' ) );
		$this->assertSame( array( false, false, true ), array_map( 'WPSB\Pricing\is_marked', $tables[0]['plans'] ) );
	}

	public function test_structured_data_of_legacy_tables(): void {
		$res = $this->http( 'GET', '/two-tables/' );
		$ld  = json_ld( $res['body'] );
		$this->assertCount( 1, $ld, 'Exactly one Product JSON-LD expected' );
		$this->assertSame( 'Licences and workshops', $ld[0]['name'] );
		$this->assertSame(
			array(
				array( '@type' => 'Offer', 'name' => 'Basis', 'price' => '9.90', 'priceCurrency' => 'EUR' ),
				array( '@type' => 'Offer', 'name' => 'Team', 'price' => '19.00', 'priceCurrency' => 'EUR' ),
				array( '@type' => 'Offer', 'name' => 'Konzern', 'price' => '2500.00', 'priceCurrency' => 'EUR' ),
				array( '@type' => 'Offer', 'name' => 'Annual pass', 'price' => '99.00', 'priceCurrency' => 'GBP' ),
			),
			$ld[0]['offers']
		);

		$ld = json_ld( $this->http( 'GET', '/legacy-v1-table/' )['body'] );
		$this->assertCount( 1, $ld );
		$this->assertSame( array( 'Hobby', 'Pro', 'Team & Co' ), array_column( $ld[0]['offers'], 'name' ) );
		$this->assertSame( array( '0.00', '19.00', '49.00' ), array_column( $ld[0]['offers'], 'price' ) );

		$ld = json_ld( $this->http( 'GET', '/swiss-offers/' )['body'] );
		$this->assertSame( array( 'CHF', 'CHF' ), array_column( $ld[0]['offers'], 'priceCurrency' ) );
		$this->assertSame( array( '49.00', '1290.50' ), array_column( $ld[0]['offers'], 'price' ) );

		$this->assertSame( array(), json_ld( $this->http( 'GET', '/price-shortcode/' )['body'] ) );
	}

	public function test_structured_data_can_be_disabled(): void {
		update_option( 'acme_pricing_options', array( 'default_currency' => 'USD', 'schema' => false ) );
		$this->assertSame( array(), json_ld( $this->http( 'GET', '/hosting-plans/' )['body'] ) );
	}

	public function test_schema_filter_is_still_applied(): void {
		$filter = static function ( $data ) {
			$data['brand'] = 'Acme';
			return $data;
		};
		add_filter( 'acme_pricing_schema', $filter );
		try {
			$data = Acme\Pricing\Schema::for_post( get_page_by_path( 'hosting-plans' ) );
		} finally {
			remove_filter( 'acme_pricing_schema', $filter );
		}
		$this->assertSame( 'Acme', $data['brand'] ?? null );
		$this->assertCount( 3, $data['offers'] );
	}

	private function settings_nonce( array $login ): string {
		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-pricing', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'] );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'], $m );
		return $m[1];
	}

	private function post_settings( array $login, string $nonce, array $values ): array {
		return $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'          => 'acme-pricing',
					'action'               => 'update',
					'_wpnonce'             => $nonce,
					'_wp_http_referer'     => '/wp-admin/options-general.php?page=acme-pricing',
					'acme_pricing_options' => $values,
				),
			)
		);
	}

	public function test_settings_screen_has_the_lock_checkbox(): void {
		$login = $this->http_login( $this->create_user( 'administrator' ) );
		$page  = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-pricing', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/<input[^>]+type="checkbox"[^>]+name="acme_pricing_options\[lock_tables\]"|<input[^>]+name="acme_pricing_options\[lock_tables\]"[^>]+type="checkbox"/', $page['body'] );
		$this->assertStringContainsString( 'Lock pricing tables for non-administrators', $page['body'] );
		$this->assertStringContainsString( 'name="acme_pricing_options[default_currency]"', $page['body'] );
		$this->assertStringContainsString( 'name="acme_pricing_options[schema]"', $page['body'] );
	}

	public function test_settings_screen_saves_lock_and_keeps_other_settings(): void {
		$login = $this->http_login( $this->create_user( 'administrator' ) );
		$nonce = $this->settings_nonce( $login );

		$res = $this->post_settings( $login, $nonce, array( 'default_currency' => 'CHF', 'schema' => '1', 'lock_tables' => '1' ) );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$opt = $this->fresh_option();
		$this->assertSame( 'CHF', $opt['default_currency'] );
		$this->assertTrue( $opt['schema'] );
		$this->assertTrue( $opt['lock_tables'] );

		$res = $this->post_settings( $login, $nonce, array( 'default_currency' => 'EUR' ) );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$opt = $this->fresh_option();
		$this->assertSame( 'EUR', $opt['default_currency'] );
		$this->assertFalse( $opt['schema'] );
		$this->assertFalse( $opt['lock_tables'] );

		// Invalid currency falls back to USD (as before).
		$this->post_settings( $login, $nonce, array( 'default_currency' => '<b>XXX</b>', 'lock_tables' => 'yes' ) );
		$opt = $this->fresh_option();
		$this->assertSame( 'USD', $opt['default_currency'] );
		$this->assertTrue( $opt['lock_tables'] );
	}

	public function test_editors_cannot_change_the_settings(): void {
		$admin = $this->http_login( $this->create_user( 'administrator' ) );
		$nonce = $this->settings_nonce( $admin );
		$ed    = $this->create_user( 'editor' );
		$login = $this->http_login( $ed );
		$res   = $this->post_settings( $login, $this->nonce_for( $ed, 'acme-pricing-options', $login['logged_in'] ), array( 'lock_tables' => '0', 'default_currency' => 'GBP' ) );
		$this->assertNotContains( $res['status'], array( 302, 303 ), 'Editors must not be able to save the pricing settings' );
		$opt = $this->fresh_option();
		$this->assertNotSame( 'GBP', $opt['default_currency'] ?? null );
	}
}

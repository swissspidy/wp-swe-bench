<?php
/**
 * `wp acme-migrate search-replace` over the seeded shop: dry run first, then the real run.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use function WPSB\Migrate\db_checksum;
use function WPSB\Migrate\expected_report;
use function WPSB\Migrate\fixture;
use function WPSB\Migrate\fixtures;
use function WPSB\Migrate\normalize_report;
use function WPSB\Migrate\refresh;
use function WPSB\Migrate\stored_value;
use function WPSB\Migrate\totals;
use function WPSB\Migrate\wakeup_log;
use const WPSB\Migrate\NEW_URL;
use const WPSB\Migrate\OLD_URL;

class CliMigrationTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array|null Results of the runs (done once for the whole class). */
	private static $runs = null;

	private function runs(): array {
		if ( null !== self::$runs ) {
			return self::$runs;
		}
		@unlink( wakeup_log() );
		refresh();
		$runs                   = array( 'checksum_before' => db_checksum() );
		$runs['dry_json']       = $this->wp_cli( 'acme-migrate search-replace ' . OLD_URL . ' ' . NEW_URL . ' --dry-run --format=json' );
		$runs['dry_table']      = $this->wp_cli( 'acme-migrate search-replace ' . OLD_URL . ' ' . NEW_URL . ' --dry-run' );
		refresh();
		$runs['checksum_dry']   = db_checksum();
		$runs['wakeups_dry']    = file_exists( wakeup_log() );
		$runs['real']           = $this->wp_cli( 'acme-migrate search-replace ' . OLD_URL . ' ' . NEW_URL );
		refresh();
		$runs['wakeups_real']   = file_exists( wakeup_log() );
		$runs['checksum_after'] = db_checksum();
		$runs['again']          = $this->wp_cli( 'acme-migrate search-replace ' . OLD_URL . ' ' . NEW_URL . ' --format=count' );
		refresh();
		$runs['checksum_again'] = db_checksum();
		self::$runs             = $runs;
		return $runs;
	}

	/** Parse the table printed by the default output format into the normalized report shape. */
	private static function parse_table( string $stdout ): array {
		$items = array();
		foreach ( preg_split( '/\R/', trim( $stdout ) ) as $line ) {
			$cells = array_values( array_filter( array_map( 'trim', preg_split( '/\t|\|/', $line ) ), 'strlen' ) );
			if ( 4 === count( $cells ) && ctype_digit( $cells[2] ) && ctype_digit( $cells[3] ) ) {
				$items[] = array( 'table' => $cells[0], 'column' => $cells[1], 'rows' => $cells[2], 'replacements' => $cells[3] );
			}
		}
		return normalize_report( $items );
	}

	public function test_dry_run_does_not_change_the_database(): void {
		$runs = $this->runs();
		$this->assertSame( 0, $runs['dry_json']['exit'], $runs['dry_json']['stderr'] . $runs['dry_json']['stdout'] );
		$this->assertSame( 0, $runs['dry_table']['exit'], $runs['dry_table']['stderr'] . $runs['dry_table']['stdout'] );
		$this->assertSame( $runs['checksum_before'], $runs['checksum_dry'], 'A dry run changed the database' );
		$this->assertFalse( $runs['wakeups_dry'], 'A stored object was instantiated during the dry run' );
	}

	public function test_dry_run_reports_exactly_what_the_run_changes(): void {
		$runs     = $this->runs();
		$expected = expected_report();
		$this->assertSame( $expected, normalize_report( json_decode( trim( $runs['dry_json']['stdout'] ), true ) ), 'Dry run JSON report' );

		list( $rows, $replacements ) = totals( $expected );
		$this->assertStringContainsString( "Success: $replacements replacements in $rows rows would be made (dry run).", $runs['dry_table']['stdout'] );
		$this->assertSame( $expected, self::parse_table( $runs['dry_table']['stdout'] ), 'Dry run table output' );
	}

	public function test_real_run_report(): void {
		$runs = $this->runs();
		$this->assertSame( 0, $runs['real']['exit'], $runs['real']['stderr'] . $runs['real']['stdout'] );
		$expected                    = expected_report();
		list( $rows, $replacements ) = totals( $expected );
		$this->assertStringContainsString( "Success: Made $replacements replacements in $rows rows.", $runs['real']['stdout'] );
		$this->assertSame( $expected, self::parse_table( $runs['real']['stdout'] ) );
		$this->assertNotSame( $runs['checksum_before'], $runs['checksum_after'] );
	}

	public static function fixture_keys(): array {
		$out = array();
		foreach ( fixtures() as $f ) {
			$out[ $f['key'] ] = array( $f['key'] );
		}
		return $out;
	}

	#[DataProvider( 'fixture_keys' )]
	public function test_stored_value_after_the_run( string $key ): void {
		$this->runs();
		$f        = fixture( $key );
		$expected = empty( $f['guid'] ) ? $f['expected'] : $f['input'];
		$this->assertSame( $expected, stored_value( $f ), "Stored value of fixture $key after the run" );
	}

	public function test_guids_are_not_replaced_by_default(): void {
		global $wpdb;
		$this->runs();
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE guid LIKE %s", '%shop.acme.example%' ) ) );
		$this->assertSame( OLD_URL . '/?p=4711', stored_value( fixture( 'post-guid' ) ) );
	}

	public function test_no_stored_object_was_instantiated(): void {
		$runs = $this->runs();
		$this->assertFalse( $runs['wakeups_real'], 'A stored Acme_Legacy_Cache_Item was instantiated (its __wakeup() ran) during the run' );
	}

	public function test_settings_are_still_readable_after_the_run(): void {
		$this->runs();
		$widgets = get_option( 'widget_text' );
		$this->assertIsArray( $widgets );
		$this->assertStringContainsString( NEW_URL . '/hours/', $widgets[2]['text'] );
		$this->assertStringContainsString( '\\n', $widgets[3]['text'] );

		$banner = get_post_meta( WPSB\Migrate\post_id( 'summer-sale', 'post' ), '_acme_banner', true );
		$this->assertIsArray( $banner );
		$this->assertSame( 'C:\\Shop\\banner.psd', $banner['cta']['links'][2] );
		$this->assertSame( 'Shop "now" \\ save', $banner['cta']['label'] );

		$blocks = parse_blocks( get_post( WPSB\Migrate\post_id( 'summer-sale', 'post' ) )->post_content );
		$this->assertSame( 'acme/promo', $blocks[0]['blockName'] );
		$this->assertSame( 'Summer <b>sale</b> & more', $blocks[0]['attrs']['title'] );
		$this->assertSame( NEW_URL . '/summer/', $blocks[0]['attrs']['url'] );
		$map = array_values( array_filter( $blocks, static fn( $b ) => 'acme/store-map' === $b['blockName'] ) )[0];
		$this->assertSame( NEW_URL . '/stores/zurich/', $map['attrs']['markers'][0]['url'] );
		$this->assertSame( '^\\d{4}$', $map['attrs']['regex'] );
	}

	public function test_own_history_is_not_rewritten(): void {
		global $wpdb;
		$this->runs();
		$history = unserialize( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'acme_migrate_history'" ), array( 'allowed_classes' => false ) );
		$this->assertIsArray( $history );
		$seeded = end( $history );
		$this->assertSame( 'http://staging.old-shop.test', $seeded['search'] );
		$this->assertSame( OLD_URL, $seeded['replace'] );
	}

	public function test_second_run_finds_nothing(): void {
		$runs = $this->runs();
		$this->assertSame( 0, $runs['again']['exit'], $runs['again']['stderr'] );
		$this->assertSame( '0', trim( $runs['again']['stdout'] ) );
		$this->assertSame( $runs['checksum_after'], $runs['checksum_again'] );
	}
}

<?php
/**
 * Existing CLI behaviour that must keep working.
 */

use function WPSB\Migrate\db_checksum;
use function WPSB\Migrate\refresh;

class CliBasicsTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_tables_option_limits_the_run(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'acme_redirects';
		$res   = $this->wp_cli( "acme-migrate search-replace https://careers.example.org https://jobs.acme.example --tables=$table --format=count" );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$this->assertSame( '1', trim( $res['stdout'] ) );
		refresh();
		$this->assertSame( 'https://jobs.acme.example/acme', $wpdb->get_var( "SELECT target FROM $table WHERE source = '/jobs/'" ) );

		$res = $this->wp_cli( "acme-migrate search-replace https://jobs.acme.example https://careers.example.org --tables={$wpdb->posts} --format=json" );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$this->assertSame( array(), json_decode( trim( $res['stdout'] ), true ) );
		refresh();
		$this->assertSame( 'https://jobs.acme.example/acme', $wpdb->get_var( "SELECT target FROM $table WHERE source = '/jobs/'" ) );
	}

	public function test_plain_values_are_replaced_with_the_success_line(): void {
		global $wpdb;
		$id  = $this->create_post( array( 'post_title' => 'Plain', 'post_content' => 'Visit https://plain-host.test/about/ and https://plain-host.test/.' ) );
		$res = $this->wp_cli( 'acme-migrate search-replace https://plain-host.test https://plain.acme.example' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$this->assertStringContainsString( 'Success: Made 2 replacements in 1 rows.', $res['stdout'] );
		refresh();
		$this->assertSame( 'Visit https://plain.acme.example/about/ and https://plain.acme.example/.', $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) ) );
		wp_delete_post( $id, true );
	}

	public function test_invalid_arguments_are_rejected_without_changes(): void {
		refresh();
		$before = db_checksum();
		$res    = $this->wp_cli( 'acme-migrate search-replace "" https://x.example' );
		$this->assertNotSame( 0, $res['exit'] );
		$res = $this->wp_cli( 'acme-migrate search-replace http://old-shop.test http://old-shop.test' );
		$this->assertNotSame( 0, $res['exit'] );
		refresh();
		$this->assertSame( $before, db_checksum() );
	}

	public function test_history_lists_runs(): void {
		$res = $this->wp_cli( 'acme-migrate history --format=json' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$runs = json_decode( trim( $res['stdout'] ), true );
		$this->assertIsArray( $runs );
		$this->assertContains( 'http://staging.old-shop.test', array_column( $runs, 'search' ) );
	}
}

<?php
/**
 * WP-CLI: list/count (unchanged) and prune (new). Runs on a freshly migrated site.
 */

use function WPSB\ActivityLog\expected_entries;
use function WPSB\ActivityLog\expected_query;
use function WPSB\ActivityLog\ids;

class RetentionCliTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function keep_count( int $days ): int {
		$cutoff = time() - $days * DAY_IN_SECONDS;
		return count( array_filter( expected_entries(), static fn( $e ) => $e['time'] >= $cutoff ) );
	}

	private function cli_count(): int {
		$res = $this->wp_cli( 'acme-activity count' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		return (int) trim( $res['stdout'] );
	}

	private function newest_ids( int $n, string $order = 'DESC' ): array {
		$res = $this->wp_cli( 'acme-activity list --format=ids --per-page=' . $n );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		return array_map( 'intval', preg_split( '/\s+/', trim( $res['stdout'] ), -1, PREG_SPLIT_NO_EMPTY ) );
	}

	private function days_until( int $index ): int {
		return (int) ceil( ( time() - expected_entries()[ $index ]['time'] ) / DAY_IN_SECONDS );
	}

	public function test_list_and_count_commands(): void {
		$res = $this->wp_cli( 'acme-activity list --format=ids --per-page=12 --page=3 --action=form_submitted' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$this->assertSame( implode( ' ', ids( expected_query( array( 'action' => 'form_submitted', 'per_page' => 12, 'page' => 3 ) )['entries'] ) ), trim( $res['stdout'] ) );

		$res = $this->wp_cli( 'acme-activity count --action=user_login' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$this->assertSame( (string) expected_query( array( 'action' => 'user_login' ) )['total'], trim( $res['stdout'] ) );
	}

	public function test_prune(): void {
		$total = count( expected_entries() );

		// Retention disabled (default after the update): nothing is deleted.
		$res = $this->wp_cli( 'acme-activity prune' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertSame( $total, $this->cli_count() );

		// Explicit --days.
		$days = $this->days_until( 3000 );
		$keep = $this->keep_count( $days );
		$this->assertGreaterThan( 0, $keep );
		$this->assertLessThan( $total, $keep );
		$res = $this->wp_cli( 'acme-activity prune --days=' . $days );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertStringContainsString( (string) ( $total - $keep ), $res['stdout'], 'Reports how many entries were deleted' );
		$this->assertSame( $keep, $this->cli_count() );
		$this->assertSame( ids( array_slice( expected_entries(), 0, 20 ) ), $this->newest_ids( 20 ), 'Newest entries are kept' );

		// The setting.
		$settings = get_option( 'acme_activity_settings' );
		$days2    = $this->days_until( 1000 );
		update_option( 'acme_activity_settings', array_merge( is_array( $settings ) ? $settings : array(), array( 'retention_days' => $days2 ) ) );
		$res = $this->wp_cli( 'acme-activity prune' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertSame( $this->keep_count( $days2 ), $this->cli_count() );

		// Entries logged now are never pruned.
		$res = $this->wp_cli( 'eval "echo acme_activity_log( \'wpsb_probe\', array( \'message\' => \'fresh\' ) );"' );
		$id  = (int) trim( $res['stdout'] );
		$this->assertGreaterThan( 0, $id, $res['stderr'] );
		$this->assertSame( 0, $this->wp_cli( 'acme-activity prune --days=1' )['exit'] );
		$this->assertSame( array( $id ), $this->newest_ids( 20 ) );
	}
}

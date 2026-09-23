<?php
/**
 * `wp acme-redirects add|update|delete`.
 */

class CrudCommandTest extends AcmeRedirectsCase {

	private function created_id( array $run ): int {
		$this->assertOk( $run );
		$this->assertMatchesRegularExpression( '/^Success: Created redirect (\d+)\.$/', $this->last_line( $run ), $run['stdout'] );
		preg_match( '/Created redirect (\d+)\./', $run['stdout'], $m );
		return (int) $m[1];
	}

	public function test_add_takes_effect_immediately(): void {
		$this->warm_front_end();
		$this->assertNoFrontRedirect( '/spring-sale' );

		$id  = $this->created_id( $this->cmd( 'add /spring-sale /deals/spring/ --status=302 --priority=3 --note="Spring campaign"' ) );
		$row = $this->row( $id );
		$this->assertNotNull( $row );
		$this->assertSame( '/spring-sale', $row['source'] );
		$this->assertSame( '/deals/spring/', $row['target'] );
		$this->assertSame( 'exact', $row['match_type'] );
		$this->assertSame( 302, (int) $row['status_code'] );
		$this->assertSame( 3, (int) $row['priority'] );
		$this->assertSame( 1, (int) $row['enabled'] );
		$this->assertSame( 'Spring campaign', $row['note'] );
		$this->assertSame( 0, (int) $row['hits'] );
		$this->assertContains( 'insert:' . $id, $this->purge_log(), 'CDN purge integration must be notified' );

		$this->assertFrontRedirect( '/spring-sale/?utm_source=mail', 302, self::h( '/deals/spring/?utm_source=mail' ) );
		$this->assertSame( 1, (int) $this->row( $id )['hits'] );
	}

	public function test_add_defaults_porcelain_and_other_types(): void {
		$run = $this->cmd( 'add \'^/kb/(\d+)-.*$\' \'/help/article/$1/\' --match_type=regex --porcelain' );
		$this->assertOk( $run );
		$this->assertMatchesRegularExpression( '/^\d+$/', trim( $run['stdout'] ), 'porcelain prints only the ID' );
		$id  = (int) trim( $run['stdout'] );
		$row = $this->row( $id );
		$this->assertSame( '^/kb/(\d+)-.*$', $row['source'] );
		$this->assertSame( 'regex', $row['match_type'] );
		$this->assertSame( 301, (int) $row['status_code'] );
		$this->assertSame( 10, (int) $row['priority'] );

		$gone = $this->created_id( $this->cmd( 'add /gone-page --status=410' ) );
		$this->assertSame( 410, (int) $this->row( $gone )['status_code'] );
		$this->assertSame( '', (string) $this->row( $gone )['target'] );

		$paused = $this->created_id( $this->cmd( 'add /paused-page /elsewhere/ --disabled' ) );
		$this->assertSame( 0, (int) $this->row( $paused )['enabled'] );

		$this->assertFrontRedirect( '/kb/42-reset-password', 301, self::h( '/help/article/42/' ) );
		$this->assertSame( 410, $this->front( '/gone-page' )['status'] );
		$this->assertNoFrontRedirect( '/paused-page' );
	}

	public function test_add_rejects_what_the_admin_screen_rejects(): void {
		$before = $this->rows();
		$log    = $this->purge_log();
		$cases  = array(
			'no leading slash'         => 'add no-slash /x/',
			'full URL source'          => 'add http://127.0.0.1:9400/abc /x/',
			'broken regex'             => 'add "^/(unclosed" /x/ --match_type=regex',
			'unknown match type'       => 'add /abc /x/ --match_type=fuzzy',
			'unsupported status'       => 'add /abc /x/ --status=404',
			'missing target'           => 'add /abc',
			'bad target'               => "add /abc 'javascript:alert(1)'",
			'self redirect'            => 'add /loop /loop/',
			'priority out of range'    => 'add /abc /x/ --priority=101',
			'duplicate source'         => 'add /team /elsewhere/',
			'duplicate of a 1.0 rule'  => 'add /old-contact /elsewhere/',
			'site validation rule'     => 'add /partner https://evil.example/landing',
		);
		foreach ( $cases as $label => $args ) {
			$this->assertFails( $this->cmd( $args ), $label );
		}
		$this->assertSame( $before, $this->rows(), 'nothing may be saved' );
		$this->assertSame( $log, $this->purge_log() );
	}

	public function test_update(): void {
		$this->warm_front_end();
		$run = $this->cmd( 'update 1 --target=/about-us/ --priority=2' );
		$this->assertOk( $run );
		$this->assertSame( 'Success: Updated redirect 1.', $this->last_line( $run ) );
		$row = $this->row( 1 );
		$this->assertSame( '/about-us/', $row['target'] );
		$this->assertSame( 2, (int) $row['priority'] );
		$this->assertSame( '/old-about', $row['source'] );
		$this->assertSame( 301, (int) $row['status_code'] );
		$this->assertSame( 'Site relaunch 2024', $row['note'] );
		$this->assertSame( 12, (int) $row['hits'], 'hits are kept' );
		$this->assertContains( 'update:1', $this->purge_log() );
		$this->assertFrontRedirect( '/old-about', 301, self::h( '/about-us/' ) );

		// Enable a disabled 1.2 rule.
		$this->assertNoFrontRedirect( '/legacy-faq/' );
		$this->assertOk( $this->cmd( 'update 16 --enabled=yes' ) );
		$this->assertSame( 1, (int) $this->row( 16 )['enabled'] );
		$this->assertFrontRedirect( '/legacy-faq', 302, self::h( '/help/faq/' ) );

		// Disable.
		$this->assertOk( $this->cmd( 'update 15 --enabled=no' ) );
		$this->assertNoFrontRedirect( '/team' );
		$this->assertSame( 42, (int) $this->row( 15 )['hits'] );
	}

	public function test_update_legacy_rule_keeps_its_behaviour(): void {
		$this->assertOk( $this->cmd( 'update 2 --note="Contact page moved in 2019"' ) );
		$this->assertFrontRedirect( '/old-contact/', 301, self::h( '/contact/' ) );
		$run = $this->cmd( 'list --format=json --fields=id,source,status,priority,enabled,hits --match_type=exact' );
		$this->assertOk( $run );
		$this->assertSame(
			array(
				'id'       => 2,
				'source'   => '/old-contact',
				'status'   => 301,
				'priority' => 10,
				'enabled'  => 'yes',
				'hits'     => 4,
			),
			array_column( $this->json( $run ), null, 'id' )[2]
		);
	}

	public function test_update_errors(): void {
		$before = $this->rows();
		$run    = $this->cmd( 'update 999 --status=302' );
		$this->assertFails( $run );
		$this->assertStringContainsString( 'Redirect 999 not found.', $run['stderr'] );
		$this->assertFails( $this->cmd( 'update 1 --status=404' ), 'invalid status' );
		$this->assertFails( $this->cmd( 'update 1 --source=/team' ), 'duplicate source' );
		$this->assertFails( $this->cmd( 'update 6 --source="^/(broken"' ), 'broken regex' );
		$this->assertSame( $before, $this->rows(), 'nothing may change' );
	}

	public function test_delete(): void {
		$this->warm_front_end();
		$run = $this->cmd( 'delete 9 15' );
		$this->assertOk( $run );
		$this->assertSame( 'Success: Deleted 2 redirect(s).', $this->last_line( $run ) );
		$rows = $this->rows();
		$this->assertArrayNotHasKey( 9, $rows );
		$this->assertArrayNotHasKey( 15, $rows );
		$this->assertCount( 16, $rows );
		$log = $this->purge_log();
		$this->assertContains( 'delete:9', $log );
		$this->assertContains( 'delete:15', $log );
		$this->assertNoFrontRedirect( '/promo' );
		$this->assertNoFrontRedirect( '/team' );
	}

	public function test_delete_with_unknown_ids(): void {
		$run = $this->cmd( 'delete 13 999' );
		$this->assertSame( 1, $run['exit'], $run['stdout'] . $run['stderr'] );
		$this->assertStringContainsString( 'Warning: Redirect 999 not found.', $run['stderr'] );
		$rows = $this->rows();
		$this->assertArrayNotHasKey( 13, $rows, 'known IDs are still deleted' );
		$this->assertCount( 17, $rows );
		$this->assertFrontRedirect( '/events/summer-fest', 301, self::h( '/whats-on/summer-fest' ) );
	}
}

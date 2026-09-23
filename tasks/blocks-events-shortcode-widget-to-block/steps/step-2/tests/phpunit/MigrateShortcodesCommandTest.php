<?php
/**
 * Step 2: `wp acme-events migrate-shortcodes [--dry-run]`.
 */

use function WPSB\Events\flatten_blocks;
use function WPSB\Events\listings;
use function WPSB\Events\post_by_slug;
use function WPSB\Events\render_post;
use function WPSB\Events\with_defaults;

class MigrateShortcodesCommandTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array<int,string> post ID => original content (all posts incl. revisions). */
	private array $snapshot = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->snapshot = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts}", OBJECT_K );
		$this->snapshot = array_map( static fn( $r ) => $r->post_content, $this->snapshot );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->snapshot as $id => $content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $id ) );
			clean_post_cache( $id );
		}
		parent::tearDown();
	}

	private function contents(): array {
		global $wpdb;
		wp_cache_flush();
		$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts}", OBJECT_K );
		return array_map( static fn( $r ) => $r->post_content, $rows );
	}

	private function summary( array $run ): string {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $run['stdout'] ) ) ) );
		return (string) end( $lines );
	}

	/** Listings rendered by the migratable content (normalized), keyed by post slug. */
	private function rendered(): array {
		wp_cache_flush();
		// Core texturizes block output but not shortcode output ("--" => "&#8212;"); that core
		// difference is not what we compare here.
		$texturize = remove_filter( 'the_content', 'wptexturize' );
		$out = array();
		foreach ( array( array( 'whats-on', 'page' ), array( 'nested-shortcodes', 'post' ), array( 'draft-events', 'post' ), array( 'conferences', 'post' ) ) as list( $slug, $type ) ) {
			$out[ $slug ] = array_map(
				static fn( $l ) => array(
					'mods'  => array_values( array_filter( $l['classes'], static fn( $c ) => 0 === strpos( $c, 'acme-events--' ) ) ),
					'inner' => $l['inner'],
				),
				listings( render_post( post_by_slug( $slug, $type ) ) )
			);
		}
		if ( $texturize ) {
			add_filter( 'the_content', 'wptexturize' );
		}
		return $out;
	}

	private function event_blocks( string $slug, string $type = 'post' ): array {
		clean_post_cache( post_by_slug( $slug, $type )->ID );
		$blocks = flatten_blocks( parse_blocks( post_by_slug( $slug, $type )->post_content ) );
		return array_values( array_filter( $blocks, static fn( $b ) => 'acme/upcoming-events' === $b['blockName'] ) );
	}

	public function test_dry_run_then_migrate_then_idempotent(): void {
		$before_render  = $this->rendered();
		$before_content = $this->contents();
		$this->assertSame( 4, count( $before_render['nested-shortcodes'] ), 'seed sanity: 4 listings in nested-shortcodes' );

		// Dry run: report, change nothing.
		$dry = $this->wp_cli( 'acme-events migrate-shortcodes --dry-run' );
		$this->assertSame( 0, $dry['exit'], $dry['stderr'] . $dry['stdout'] );
		$this->assertSame( 'Success: Dry run: 6 shortcode(s) in 4 post(s) would be migrated.', $this->summary( $dry ), $dry['stdout'] );
		$this->assertSame( $before_content, $this->contents(), 'A dry run must not change any content' );

		// Real run.
		$run = $this->wp_cli( 'acme-events migrate-shortcodes' );
		$this->assertSame( 0, $run['exit'], $run['stderr'] . $run['stdout'] );
		$this->assertSame( 'Success: Migrated 6 shortcode(s) in 4 post(s).', $this->summary( $run ), $run['stdout'] );

		$after_content = $this->contents();
		$changed       = array_keys( array_diff_assoc( $after_content, $before_content ) );
		sort( $changed );
		$expected = array(
			post_by_slug( 'whats-on', 'page' )->ID,
			post_by_slug( 'nested-shortcodes' )->ID,
			post_by_slug( 'draft-events' )->ID,
			post_by_slug( 'sidebar-events', 'wp_block' )->ID,
		);
		sort( $expected );
		$this->assertSame( $expected, $changed, 'Exactly the posts with convertible Shortcode blocks must change (no revisions, no classic/inline/escaped usage)' );

		// Listings render exactly as before.
		$this->assertEquals( $before_render, $this->rendered(), 'Migrated listings must render exactly like the shortcodes did' );

		// Block structure and typed attributes.
		$b = $this->event_blocks( 'whats-on', 'page' );
		$this->assertCount( 1, $b );
		$a = with_defaults( $b[0]['attrs'] );
		$this->assertSame( 3, $a['limit'] );
		$this->assertSame( 'grid', $a['layout'] );
		$this->assertSame( 'Next up', $a['title'] );

		$b = $this->event_blocks( 'nested-shortcodes' );
		$this->assertCount( 3, $b );
		$a0 = with_defaults( $b[0]['attrs'] );
		$this->assertFalse( $a0['showVenue'] );
		$this->assertStringContainsString( 'Talks -- Q&', $a0['title'] );
		$this->assertSame( 2, with_defaults( $b[1]['attrs'] )['limit'] );
		$a2 = with_defaults( $b[2]['attrs'] );
		$this->assertTrue( $a2['showPast'] );
		$this->assertIsInt( $a2['limit'] );

		$nested = post_by_slug( 'nested-shortcodes' )->post_content;
		$this->assertStringContainsString( "[acme_events limit=\"2\"] and also [gallery]", $nested, 'Mixed Shortcode blocks stay' );
		$this->assertStringContainsString( '<p>From our community:</p>', $nested );
		$this->assertStringContainsString( '<!-- wp:group {"className":"events-box","layout":{"type":"constrained"}} -->', $nested );
		$all = flatten_blocks( parse_blocks( $nested ) );
		$this->assertSame( 1, count( array_filter( $all, static fn( $x ) => 'core/shortcode' === $x['blockName'] ) ) );
		$group = array_values( array_filter( $all, static fn( $x ) => 'core/group' === $x['blockName'] ) )[0];
		$this->assertSame( array( 'core/paragraph', 'acme/upcoming-events' ), array_map( static fn( $x ) => $x['blockName'], $group['innerBlocks'] ), 'The shortcode must be converted in place inside the group' );

		$b = $this->event_blocks( 'draft-events' );
		$this->assertCount( 1, $b );
		$a = with_defaults( $b[0]['attrs'] );
		$this->assertSame( 4, $a['limit'] );
		$this->assertTrue( $a['showPast'] );
		$this->assertSame( 'The "Big" recap', html_entity_decode( $a['title'], ENT_QUOTES ) );
		$this->assertSame( 'draft', post_by_slug( 'draft-events' )->post_status );

		$b = $this->event_blocks( 'sidebar-events', 'wp_block' );
		$this->assertCount( 1, $b );
		$this->assertSame( 'conferences', with_defaults( $b[0]['attrs'] )['category'] );

		// Second run: nothing to do.
		$again = $this->wp_cli( 'acme-events migrate-shortcodes' );
		$this->assertSame( 0, $again['exit'], $again['stderr'] );
		$this->assertSame( 'Success: Migrated 0 shortcode(s) in 0 post(s).', $this->summary( $again ), $again['stdout'] );
		$this->assertSame( $after_content, $this->contents(), 'A second run must not change anything' );

		// Not migrated: still rendered by the shortcode.
		$this->assertCount( 1, listings( render_post( post_by_slug( 'meetups-roundup' ) ) ) );
		$this->assertCount( 1, listings( render_post( post_by_slug( 'inline-shortcode' ) ) ) );
		$this->assertStringContainsString( '[acme_events]', render_post( post_by_slug( 'escaped-shortcode' ) ) );
	}
}

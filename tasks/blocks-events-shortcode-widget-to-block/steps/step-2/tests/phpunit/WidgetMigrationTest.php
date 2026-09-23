<?php
/**
 * Step 2: classic widgets were converted to block widgets on deploy.
 *
 * test.sh restores the pre-deploy database and then starts the web server (which requests the
 * site); the conversion must have happened by then, without any admin visit or command.
 */

use function WPSB\Events\dom;
use function WPSB\Events\flatten_blocks;
use function WPSB\Events\listings;
use function WPSB\Events\with_defaults;

class WidgetMigrationTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function options(): array {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'sidebars_widgets', 'options' );
		wp_cache_delete( 'widget_block', 'options' );
		return array( get_option( 'sidebars_widgets' ), get_option( 'widget_block' ) );
	}

	/** The single acme/upcoming-events block of a block widget (attributes with defaults). */
	private function widget_block_attrs( array $widget_block, string $widget_id ): array {
		$this->assertMatchesRegularExpression( '/^block-\d+$/', $widget_id, 'Converted widgets must be block widgets' );
		$n = (int) substr( $widget_id, 6 );
		$this->assertArrayHasKey( $n, $widget_block, "widget_block has no instance $n" );
		$blocks = flatten_blocks( parse_blocks( $widget_block[ $n ]['content'] ?? '' ) );
		$events = array_values( array_filter( $blocks, static fn( $b ) => 'acme/upcoming-events' === $b['blockName'] ) );
		$this->assertCount( 1, $events, "Block widget $widget_id must contain one Upcoming Events block: " . ( $widget_block[ $n ]['content'] ?? '' ) );
		return with_defaults( $events[0]['attrs'] );
	}

	public function test_legacy_widget_type_is_gone(): void {
		$ids = array_map( static fn( $w ) => $w->id_base, array_values( $GLOBALS['wp_widget_factory']->widgets ) );
		$this->assertNotContains( 'acme_upcoming_events', $ids, 'The classic widget must not be registered any more' );

		$this->login_as( 'administrator' );
		$res = $this->rest( 'GET', '/wp/v2/widget-types' );
		$this->assertSame( 200, $res->get_status() );
		$this->assertNotContains( 'acme_upcoming_events', wp_list_pluck( $res->get_data(), 'id' ) );
		$this->assertContains( 'block', wp_list_pluck( $res->get_data(), 'id' ) );
	}

	public function test_widgets_were_converted_in_place(): void {
		list( $sidebars, $widget_block ) = $this->options();

		$this->assertCount( 2, $sidebars['sidebar-1'] );
		$this->assertSame( 'block-2', $sidebars['sidebar-1'][0], 'Existing block widget must stay first' );
		$this->assertCount( 2, $sidebars['footer-1'] );
		$this->assertSame( 'block-3', $sidebars['footer-1'][1], 'Existing block widget must stay second' );
		$this->assertCount( 1, $sidebars['wp_inactive_widgets'] );

		$new = array( $sidebars['sidebar-1'][1], $sidebars['footer-1'][0], $sidebars['wp_inactive_widgets'][0] );
		$this->assertCount( 3, array_unique( $new ), 'Each widget needs its own block widget' );
		foreach ( $new as $id ) {
			$this->assertNotContains( $id, array( 'block-2', 'block-3' ), 'Existing block widgets must not be overwritten' );
		}
		$this->assertSame( "<!-- wp:paragraph -->\n<p>Welcome to the Acme site.</p>\n<!-- /wp:paragraph -->", $widget_block[2]['content'] );
		$this->assertSame( "<!-- wp:paragraph -->\n<p>© Acme Inc.</p>\n<!-- /wp:paragraph -->", $widget_block[3]['content'] );

		foreach ( $sidebars as $sidebar => $ids ) {
			foreach ( (array) $ids as $id ) {
				$this->assertStringStartsNotWith( 'acme_upcoming_events', (string) $id, "Legacy widget left in $sidebar" );
			}
		}
	}

	public function test_settings_are_mapped(): void {
		list( $sidebars, $widget_block ) = $this->options();

		$a = $this->widget_block_attrs( $widget_block, $sidebars['sidebar-1'][1] );
		$this->assertSame( 3, $a['limit'] );
		$this->assertSame( 'workshops', $a['category'], 'The category term ID must be mapped to its slug' );
		$this->assertSame( 'Workshops', $a['title'] );
		$this->assertTrue( $a['showVenue'] );
		$this->assertFalse( $a['showPast'] );
		$this->assertSame( 'list', $a['layout'] );

		$b = $this->widget_block_attrs( $widget_block, $sidebars['footer-1'][0] );
		$this->assertSame( 10, $b['limit'] );
		$this->assertSame( '', $b['category'] );
		$this->assertSame( 'Meetups & "Talks"', html_entity_decode( $b['title'], ENT_QUOTES ) );
		$this->assertFalse( $b['showVenue'] );

		$c = $this->widget_block_attrs( $widget_block, $sidebars['wp_inactive_widgets'][0] );
		$this->assertSame( 2, $c['limit'] );
		$this->assertSame( '', $c['category'], 'A deleted category means all categories' );
		$this->assertSame( '', $c['title'] );
		$this->assertTrue( $c['showVenue'] );
	}

	public function test_converted_widgets_render_the_same_events(): void {
		$res = $this->http( 'GET', '/no-events/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringNotContainsString( 'widget_acme_upcoming_events', $res['body'] );
		$xpath = dom( $res['body'] );

		$sidebar = $xpath->query( '//*[@id="secondary"]' )->item( 0 );
		$this->assertNotNull( $sidebar );
		$l = listings( '', $xpath, $sidebar );
		$this->assertCount( 1, $l, 'One listing in the sidebar' );
		$this->assertSame( array( 'Intro to Gutenberg', 'Block Themes Deep Dive', 'Performance Clinic' ), $l[0]['titles'] );
		$this->assertSame( 'Workshops', $l[0]['heading'] );
		$this->assertStringContainsString( 'acme-event__venue', $l[0]['inner'] );
		$this->assertStringContainsString( 'Welcome to the Acme site.', $sidebar->textContent );

		$footer = $xpath->query( '//*[@id="footer-widgets"]' )->item( 0 );
		$this->assertNotNull( $footer );
		$l = listings( '', $xpath, $footer );
		$this->assertCount( 1, $l );
		$this->assertSame( 7, $l[0]['items'] );
		$this->assertSame( 'Meetups & "Talks"', $l[0]['heading'] );
		$this->assertStringNotContainsString( 'acme-event__venue', $l[0]['inner'] );
		$this->assertStringNotContainsString( 'Summer Social', $l[0]['inner'] );
		// Footer order: converted widget first, then the existing block widget.
		$this->assertLessThan( strpos( $footer->textContent, '© Acme Inc.' ), strpos( $footer->textContent, 'Holiday Meetup' ) );
	}

	public function test_conversion_happens_only_once(): void {
		list( $sidebars, $widget_block ) = $this->options();
		$count = static function ( $wb ) {
			$n = 0;
			foreach ( $wb as $k => $inst ) {
				if ( is_array( $inst ) && false !== strpos( (string) ( $inst['content'] ?? '' ), 'wp:acme/upcoming-events' ) ) {
					++$n;
				}
			}
			return $n;
		};
		$this->assertSame( 3, $count( $widget_block ) );

		// An editor removes the converted sidebar widget afterwards: it must not come back.
		$removed                 = $sidebars['sidebar-1'][1];
		$edited                  = $sidebars;
		$edited['sidebar-1']     = array( 'block-2' );
		update_option( 'sidebars_widgets', $edited );
		try {
			$this->http( 'GET', '/' );
			$this->http( 'GET', '/wp-login.php' );
			$cli = $this->wp_cli( 'eval "echo get_bloginfo( \'name\' );"' );
			$this->assertSame( 0, $cli['exit'], $cli['stderr'] );
			$this->http( 'GET', '/no-events/' );

			list( $after, $after_blocks ) = $this->options();
			$this->assertSame( array( 'block-2' ), $after['sidebar-1'], 'A removed widget must not be re-created' );
			$this->assertSame( $edited['footer-1'], $after['footer-1'] );
			$this->assertSame( $edited['wp_inactive_widgets'], $after['wp_inactive_widgets'] );
			$this->assertSame( 3, $count( $after_blocks ), 'Widgets must not be converted twice' );
		} finally {
			update_option( 'sidebars_widgets', $sidebars );
		}
		$this->assertStringStartsWith( 'block-', $removed );
	}
}

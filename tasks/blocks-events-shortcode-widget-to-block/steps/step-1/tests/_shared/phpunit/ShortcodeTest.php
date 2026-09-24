<?php
/**
 * The [acme_events] shortcode keeps working (pass-to-pass in every step).
 */

use function WPSB\Events\listings;
use function WPSB\Events\post_by_slug;
use function WPSB\Events\render_post;
use function WPSB\Events\shortcode;
use function WPSB\Events\single_listing;

class ShortcodeTest extends WPSB\TestCase {

	public function test_default_listing(): void {
		$l = single_listing( shortcode() );
		$this->assertSame( array( 'acme-events', 'acme-events--list' ), $l['classes'] );
		$this->assertSame( array( 'Intro to Gutenberg', 'WordPress Meetup Zürich', 'Block Themes Deep Dive', 'WordCamp Acme 2031', 'Performance Clinic' ), $l['titles'] );
		$this->assertStringContainsString( '<ul class="acme-events__items"><li class="acme-event acme-event--', $l['inner'] );
		$this->assertStringContainsString( '<span class="acme-event__venue">Café "Zürich" &amp; Co</span>', $l['inner'] );
		$this->assertStringContainsString( '<time class="acme-event__date" datetime="2031-03-10T18:00">', $l['inner'] );
		$this->assertNull( $l['heading'] );
	}

	public function test_options(): void {
		$l = single_listing( shortcode( 'layout="grid" limit="3" title="Next up"' ) );
		$this->assertContains( 'acme-events--grid', $l['classes'] );
		$this->assertSame( 'Next up', $l['heading'] );
		$this->assertSame( 3, $l['items'] );
		$this->assertStringContainsString( 'acme-event__cta', $l['inner'] );

		$l = single_listing( shortcode( 'category="meetups" show_past="yes" show_venue="no"' ) );
		$this->assertSame( array( 'Holiday Meetup', 'Performance Clinic', 'WordPress Meetup Zürich', 'Launch Party' ), $l['titles'] );
		$this->assertStringNotContainsString( 'acme-event__venue', $l['inner'] );

		$l = single_listing( shortcode( 'category="nope"' ) );
		$this->assertSame( '<p class="acme-events__empty">No upcoming events.</p>', $l['inner'] );
	}

	public function test_classic_post_with_shortcode(): void {
		$html = render_post( post_by_slug( 'meetups-roundup' ) );
		$all  = listings( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertSame( array( 'Holiday Meetup', 'Performance Clinic', 'WordPress Meetup Zürich', 'Launch Party' ), $all[0]['titles'] );
		$this->assertStringContainsString( '<code>[acme_events]</code>', $html );
	}

	public function test_inline_and_escaped_shortcodes(): void {
		$html = render_post( post_by_slug( 'inline-shortcode' ) );
		$all  = listings( $html );
		$this->assertCount( 1, $all );
		$this->assertSame( array( 'Intro to Gutenberg' ), $all[0]['titles'] );

		$html = render_post( post_by_slug( 'escaped-shortcode' ) );
		$this->assertCount( 0, listings( $html ) );
		$this->assertStringContainsString( '[acme_events]', $html );
	}
}

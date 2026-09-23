<?php
/**
 * The acme/upcoming-events block: registration, parity with the shortcode, filters, hardening.
 */

use function WPSB\Events\block;
use function WPSB\Events\listings;
use function WPSB\Events\shortcode;
use function WPSB\Events\single_listing;

class UpcomingEventsBlockTest extends WPSB\TestCase {

	private function assertSameListing( string $shortcode_html, string $block_html, string $label = '' ): void {
		$s = single_listing( $shortcode_html );
		$b = single_listing( $block_html );
		$this->assertSame( 'div', $b['tag'], "$label: the block's listing root must be a div" );
		foreach ( $s['classes'] as $class ) {
			$this->assertContains( $class, $b['classes'], "$label: class '$class' missing on the block's listing root" );
		}
		foreach ( $b['classes'] as $class ) {
			if ( 0 === strpos( $class, 'acme-events--' ) ) {
				$this->assertContains( $class, $s['classes'], "$label: unexpected modifier class '$class' on the block's listing root" );
			}
		}
		$this->assertSame( $s['inner'], $b['inner'], "$label: block markup differs from the shortcode markup" );
	}

	public function test_block_is_registered_on_the_server_with_the_documented_attributes(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme/upcoming-events' );
		$this->assertNotNull( $type, 'acme/upcoming-events must be registered on the server' );
		$this->assertTrue( $type->is_dynamic(), 'The block must be rendered on the server' );
		$expected = array(
			'limit'     => array( 'integer', 5 ),
			'category'  => array( 'string', '' ),
			'showPast'  => array( 'boolean', false ),
			'layout'    => array( 'string', 'list' ),
			'title'     => array( 'string', '' ),
			'showVenue' => array( 'boolean', true ),
		);
		foreach ( $expected as $name => list( $type_name, $default ) ) {
			$this->assertArrayHasKey( $name, $type->attributes, "Attribute $name missing" );
			$this->assertSame( $type_name, $type->attributes[ $name ]['type'] ?? null, "Attribute $name must be of type $type_name" );
			$this->assertSame( $default, $type->attributes[ $name ]['default'] ?? null, "Default of $name" );
		}
	}

	public static function parity_cases(): array {
		return array(
			'defaults'               => array( '', array() ),
			'limit'                  => array( 'limit="2"', array( 'limit' => 2 ) ),
			'grid (theme override)'  => array( 'layout="grid"', array( 'layout' => 'grid' ) ),
			'one category'           => array( 'category="workshops"', array( 'category' => 'workshops' ) ),
			'two categories'         => array( 'category="workshops,meetups" limit="10"', array( 'category' => 'workshops,meetups', 'limit' => 10 ) ),
			'past events'            => array( 'show_past="yes"', array( 'showPast' => true ) ),
			'no venue'               => array( 'show_venue="no"', array( 'showVenue' => false ) ),
			'title'                  => array( 'title="Next up"', array( 'title' => 'Next up' ) ),
			'empty result'           => array( 'category="does-not-exist"', array( 'category' => 'does-not-exist' ) ),
			'unknown layout'         => array( 'layout="carousel"', array( 'layout' => 'carousel' ) ),
			'everything'             => array( 'limit="3" layout="grid" category="meetups" show_past="1" show_venue="no" title="Meetups"', array( 'limit' => 3, 'layout' => 'grid', 'category' => 'meetups', 'showPast' => true, 'showVenue' => false, 'title' => 'Meetups' ) ),
		);
	}

	/**
	 * @dataProvider parity_cases
	 */
	public function test_block_output_is_identical_to_the_shortcode( string $atts, array $attrs ): void {
		$this->assertSameListing( shortcode( $atts ), block( $attrs ), "[acme_events $atts]" );
	}

	public function test_block_lists_the_expected_events(): void {
		$l = single_listing( block( array() ) );
		$this->assertSame( array( 'Intro to Gutenberg', 'WordPress Meetup Zürich', 'Block Themes Deep Dive', 'WordCamp Acme 2031', 'Performance Clinic' ), $l['titles'] );

		$l = single_listing( block( array( 'category' => 'meetups', 'showPast' => true ) ) );
		$this->assertSame( array( 'Holiday Meetup', 'Performance Clinic', 'WordPress Meetup Zürich', 'Launch Party' ), $l['titles'], 'Past listing: newest first; cancelled events are hidden by the site filter' );

		$l = single_listing( block( array( 'layout' => 'grid', 'limit' => 2 ) ) );
		$this->assertContains( 'acme-events--grid', $l['classes'] );
		$this->assertStringContainsString( 'acme-event__cta', $l['inner'], "The theme's grid template override must be used" );

		$html = block( array( 'limit' => 20 ) );
		$this->assertStringNotContainsString( 'Summer Social', $html, 'Cancelled events are removed by the site\'s acme_events_query_args filter' );
		$this->assertStringContainsString( 'acme-event--members-only', $html, 'The site\'s acme_events_item_html filter must apply' );
		$this->assertStringNotContainsString( 'Secret Planning Session', $html, 'Drafts are never listed' );
	}

	public function test_filters_receive_the_same_options_as_for_the_shortcode(): void {
		$seen   = array();
		$query  = static function ( $args, $options = null ) use ( &$seen ) {
			$seen[] = array( 'query', $options );
			return $args;
		};
		$item   = static function ( $html, $event = null, $options = null ) use ( &$seen ) {
			$seen[] = array( 'item', $options );
			return str_replace( '<a class="acme-event__link"', '<a data-options="' . esc_attr( $options['layout'] . '/' . $options['limit'] . '/' . ( $options['show_venue'] ? 'venue' : 'novenue' ) ) . '" class="acme-event__link"', $html );
		};
		$empty  = static function ( $message, $options = null ) {
			return 'Nothing here (' . $options['category'] . ')';
		};
		add_filter( 'acme_events_query_args', $query, 5, 2 );
		add_filter( 'acme_events_item_html', $item, 5, 3 );
		add_filter( 'acme_events_empty_message', $empty, 10, 2 );
		try {
			$s_html = shortcode( 'limit="3" layout="grid" show_venue="no" category="workshops"' );
			$s_seen = $seen;
			$seen   = array();
			$b_html = block( array( 'limit' => 3, 'layout' => 'grid', 'showVenue' => false, 'category' => 'workshops' ) );
			$b_seen = $seen;
			$this->assertSameListing( $s_html, $b_html, 'with filters' );
			$this->assertStringContainsString( 'data-options="grid/3/novenue"', $b_html );
			$this->assertNotEmpty( $b_seen );
			$this->assertEquals( $s_seen, $b_seen, 'Filters must receive the same normalized options for the block and the shortcode' );

			$this->assertSameListing( shortcode( 'category="nothing"' ), block( array( 'category' => 'nothing' ) ), 'empty message filter' );
			$this->assertStringContainsString( 'Nothing here (nothing)', block( array( 'category' => 'nothing' ) ) );
		} finally {
			remove_filter( 'acme_events_query_args', $query, 5 );
			remove_filter( 'acme_events_item_html', $item, 5 );
			remove_filter( 'acme_events_empty_message', $empty, 10 );
		}
	}

	public function test_limits_are_enforced_like_the_shortcode(): void {
		$workshops = get_term_by( 'slug', 'workshops', 'acme_event_category' );
		for ( $i = 1; $i <= 22; $i++ ) {
			$id = $this->create_post( array( 'post_type' => 'acme_event', 'post_title' => "Bulk event $i" ) );
			update_post_meta( $id, '_acme_event_start', sprintf( '2030-01-%02d 10:00', $i ) );
			wp_set_object_terms( $id, array( $workshops->term_id ), 'acme_event_category' );
		}
		$this->assertSame( 20, single_listing( block( array( 'limit' => 100 ) ) )['items'], 'At most 20 events' );
		$this->assertSame( 20, single_listing( shortcode( 'limit="100"' ) )['items'] );
		$this->assertSame( 1, single_listing( block( array( 'limit' => 0 ) ) )['items'], 'At least one event' );
		$this->assertSameListing( shortcode( 'limit="-3"' ), block( array( 'limit' => -3 ) ), 'negative limit' );
		$this->assertSameListing( shortcode( 'limit="7" category="workshops"' ), block( array( 'limit' => 7, 'category' => 'workshops' ) ), 'many events' );
	}

	public function test_block_attributes_cannot_inject_markup(): void {
		$html = block(
			array(
				'layout'   => 'list" onmouseover="alert(1)',
				'title'    => '<script>alert(2)</script><img src=x onerror=alert(3)>',
				'category' => '"><svg onload=alert(4)>',
				'limit'    => '2" onclick="alert(5)',
			)
		);
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		foreach ( array( 'onmouseover', 'onerror', 'onload', 'onclick' ) as $attr ) {
			$this->assertDoesNotMatchRegularExpression( '/<[^>]+\s' . $attr . '\s*=/i', $html, "Injected $attr attribute" );
		}
		$all = listings( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertContains( 'acme-events--list', $all[0]['classes'] );
	}

	public function test_titles_with_shortcode_syntax_are_rendered_verbatim(): void {
		$title = 'Tom\'s "big" ] night [/acme_events] & more';
		$l     = single_listing( block( array( 'title' => $title, 'limit' => 2 ) ) );
		$this->assertSame( $title, $l['heading'] );
		$this->assertSame( 2, $l['items'] );
	}

	public function test_block_renderer_endpoint_previews_the_listing(): void {
		$this->login_as( 'editor' );
		$res = $this->rest( 'GET', '/wp/v2/block-renderer/acme/upcoming-events', array( 'context' => 'edit', 'attributes' => array( 'layout' => 'grid', 'limit' => 2, 'showVenue' => false ) ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = $res->get_data();
		$l    = single_listing( $data['rendered'] );
		$this->assertContains( 'acme-events--grid', $l['classes'] );
		$this->assertSame( array( 'Intro to Gutenberg', 'WordPress Meetup Zürich' ), $l['titles'] );
		$this->assertStringNotContainsString( 'acme-event__venue', $data['rendered'] );
	}

	public function test_no_php_warnings_or_notices(): void {
		$errors = array();
		set_error_handler(
			static function ( $no, $str, $file, $line ) use ( &$errors ) {
				if ( false !== strpos( $file, 'acme-events' ) ) {
					$errors[] = "$str in $file:$line";
				}
				return false;
			}
		);
		try {
			block( array() );
			block( array( 'layout' => 'grid', 'showPast' => true, 'title' => 'x' ) );
			block( array( 'limit' => '4', 'showVenue' => 'maybe', 'layout' => 7, 'category' => 'workshops,,meetups' ) );
			shortcode( 'layout="grid"' );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $errors );
		$this->assertNoDoingItWrong();
	}
}

<?php
/**
 * Course pages served by the real server with Twenty Twenty-Five (block theme) active.
 */

use function WPSB\Courses\by_class;
use function WPSB\Courses\dom;
use function WPSB\Courses\listing;
use function WPSB\Courses\text;

class BlockThemeFrontEndTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static array $pages = array();

	private function page( string $path ): array {
		if ( ! isset( self::$pages[ $path ] ) ) {
			$res = $this->http( 'GET', $path );
			$this->assertSame( 200, $res['status'], "GET $path" );
			self::$pages[ $path ] = $res['body'];
		}
		return array( self::$pages[ $path ], dom( self::$pages[ $path ] ) );
	}

	private function one( \DOMXPath $x, string $class, string $msg = '' ): \DOMElement {
		$els = by_class( $x, $class );
		$this->assertCount( 1, $els, $msg ?: "Expected exactly one .$class" );
		return $els[0];
	}

	private function assertBlockThemeChrome( string $html, \DOMXPath $x ): void {
		$this->assertStringContainsString( 'wp-site-blocks', $html, 'The page must be rendered by a block template' );
		$this->assertGreaterThanOrEqual( 1, $x->query( '//header[' . WPSB\Courses\cls( 'wp-block-template-part' ) . ']' )->length, 'Theme header template part missing' );
		$this->assertGreaterThanOrEqual( 1, $x->query( '//footer[' . WPSB\Courses\cls( 'wp-block-template-part' ) . ']' )->length, 'Theme footer template part missing' );
		$this->assertStringNotContainsString( 'acme-course-summary"', $html, 'The 1.x summary box must not be injected with block themes' );
		$this->assertSame( 0, count( by_class( $x, 'acme-course-summary' ) ), 'The 1.x summary box must not be injected with block themes' );
		$this->assertSame( 0, count( by_class( $x, 'acme-course-excerpt-meta' ) ), 'The listing meta line must not be injected with block themes' );
	}

	public function test_single_course_uses_block_template_and_summary_part(): void {
		$log_before = is_file( WP_CONTENT_DIR . '/debug.log' ) ? filesize( WP_CONTENT_DIR . '/debug.log' ) : 0;
		self::$pages = array();
		list( $html, $x ) = $this->page( '/courses/intro-to-php/' );
		$this->assertBlockThemeChrome( $html, $x );

		$h1 = $x->query( '//h1' );
		$this->assertSame( 1, $h1->length, 'Exactly one <h1> expected' );
		$this->assertSame( 'Introduction to PHP', text( $h1->item( 0 ) ) );

		$this->assertSame( '$49.00', text( $this->one( $x, 'wp-block-acme-courses-course-price' ) ) );
		$this->assertSame( '6 weeks', text( $this->one( $x, 'wp-block-acme-courses-course-duration' ) ) );
		$enroll = $this->one( $x, 'wp-block-acme-courses-enroll-button' );
		$a      = $x->query( './/a', $enroll );
		$this->assertSame( 1, $a->length );
		$this->assertStringContainsString( 'acme-course-enroll', $a->item( 0 )->getAttribute( 'class' ) );
		$this->assertSame( 'https://academy.example.org/enroll?course=intro-to-php', $a->item( 0 )->getAttribute( 'href' ) );
		$this->assertSame( 'Start learning', text( $a->item( 0 ) ), 'acme_courses_enroll_label filter (mu-plugin) must apply' );

		// The summary lives in the course-summary template part.
		$price = $this->one( $x, 'wp-block-acme-courses-course-price' );
		$in_part = false;
		for ( $p = $price->parentNode; $p instanceof \DOMElement; $p = $p->parentNode ) {
			if ( false !== strpos( ' ' . $p->getAttribute( 'class' ) . ' ', ' wp-block-template-part ' ) ) {
				$in_part = true;
				break;
			}
		}
		$this->assertTrue( $in_part, 'Price must be rendered from a template part' );

		// Description + topics.
		$this->assertStringContainsString( 'Course description for <strong>intro-to-php</strong>', $html );
		$topic_links = $x->query( '//a[@href="' . home_url( '/course-topic/php/' ) . '"]' );
		$this->assertGreaterThanOrEqual( 1, $topic_links->length, 'Link to the PHP topic missing' );

		clearstatcache();
		$log = is_file( WP_CONTENT_DIR . '/debug.log' ) ? (string) file_get_contents( WP_CONTENT_DIR . '/debug.log', false, null, $log_before ) : '';
		$this->assertStringNotContainsString( 'without header.php', $log );
		$this->assertStringNotContainsString( 'without footer.php', $log );
	}

	/**
	 * @dataProvider provide_courses
	 */
	public function test_course_details_on_single_pages( string $slug, ?string $price, ?string $duration, array $enroll ): void {
		list( $html, $x ) = $this->page( "/courses/$slug/" );
		$this->assertBlockThemeChrome( $html, $x );

		$prices = by_class( $x, 'wp-block-acme-courses-course-price' );
		if ( null === $price ) {
			$this->assertCount( 0, $prices, 'No price block output expected for a course without price' );
		} else {
			$this->assertCount( 1, $prices );
			$this->assertSame( $price, text( $prices[0] ) );
		}
		$durations = by_class( $x, 'wp-block-acme-courses-course-duration' );
		if ( null === $duration ) {
			$this->assertCount( 0, $durations, 'No duration block output expected for a course without duration' );
		} else {
			$this->assertCount( 1, $durations );
			$this->assertSame( $duration, text( $durations[0] ) );
		}

		$buttons = by_class( $x, 'wp-block-acme-courses-enroll-button' );
		$this->assertCount( 1, $buttons );
		$links = $x->query( './/a', $buttons[0] );
		if ( 'closed' === $enroll['type'] ) {
			$this->assertSame( 0, $links->length, 'Closed courses must not link to the enrollment page' );
			$closed = by_class( $x, 'is-closed', $buttons[0] );
			$this->assertCount( 1, $closed );
			$this->assertSame( 'Enrollment closed', text( $closed[0] ) );
		} else {
			$this->assertSame( 1, $links->length );
			$this->assertSame( $enroll['label'], text( $links->item( 0 ) ) );
			if ( isset( $enroll['href'] ) ) {
				$this->assertSame( $enroll['href'], $links->item( 0 )->getAttribute( 'href' ) );
			}
		}
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public static function provide_courses(): array {
		return array(
			'1.0 free-text meta'      => array( 'wordpress-basics', '$29.00', '3 weeks', array( 'type' => 'link', 'label' => 'Start learning', 'href' => 'https://academy.example.org/enroll?course=wordpress-basics' ) ),
			'free course'             => array( 'git-workshop', 'Free', '90 minutes', array( 'type' => 'link', 'label' => 'Start learning' ) ),
			'closed enrollment'       => array( 'advanced-php-patterns', '$129.00', '8 weeks', array( 'type' => 'closed' ) ),
			'waitlist + own url'      => array( 'block-themes-deep-dive', '$79.00', '1 week', array( 'type' => 'link', 'label' => 'Join the waitlist', 'href' => 'https://academy.example.org/waitlist/block-themes' ) ),
			'legacy + partner filter' => array( 'php-bootcamp', '$1,299.50', '5 days', array( 'type' => 'link', 'label' => 'Start learning', 'href' => 'https://partners.example.org/shop/php-bootcamp?ref=academy' ) ),
			'no price, no duration'   => array( 'accessibility-coming-soon', null, null, array( 'type' => 'link', 'label' => 'Start learning' ) ),
			'unsafe imported url'     => array( 'imported-security-course', '$15.00', null, array( 'type' => 'link', 'label' => 'Start learning' ) ),
		);
	}

	public function test_catalog_lists_all_courses_with_their_own_details(): void {
		list( $html, $x ) = $this->page( '/courses/' );
		$this->assertBlockThemeChrome( $html, $x );
		$list = listing( $html );
		$expected = array(
			'imported-security-course'  => array( '$15.00', null ),
			'accessibility-coming-soon' => array( null, null ),
			'php-bootcamp'              => array( '$1,299.50', '5 days' ),
			'block-themes-deep-dive'    => array( '$79.00', '1 week' ),
			'advanced-php-patterns'     => array( '$129.00', '8 weeks' ),
			'git-workshop'              => array( 'Free', '90 minutes' ),
			'wordpress-basics'          => array( '$29.00', '3 weeks' ),
			'intro-to-php'              => array( '$49.00', '6 weeks' ),
		);
		$this->assertSame( array_keys( $expected ), array_keys( $list ), 'Catalog must list all published courses, newest first (and no drafts)' );
		foreach ( $expected as $slug => list( $price, $duration ) ) {
			$this->assertSame( 1, $list[ $slug ]['count'], "$slug listed once" );
			$this->assertSame( $price, $list[ $slug ]['price'], "Price shown for $slug" );
			$this->assertSame( $duration, $list[ $slug ]['duration'], "Duration shown for $slug" );
		}
		$this->assertCount( 7, by_class( $x, 'wp-block-acme-courses-course-price' ) );
		$this->assertStringNotContainsString( 'Unreleased Course', $html );
		$this->assertStringContainsString( 'Courses', text( $x->query( '//h1' )->item( 0 ) ) );
	}

	public function test_topic_archive_lists_only_that_topic(): void {
		list( $html, $x ) = $this->page( '/course-topic/php/' );
		$this->assertBlockThemeChrome( $html, $x );
		$list = listing( $html );
		$this->assertSame( array( 'php-bootcamp', 'advanced-php-patterns', 'intro-to-php' ), array_keys( $list ) );
		$this->assertSame( '$1,299.50', $list['php-bootcamp']['price'] );
		$this->assertSame( '8 weeks', $list['advanced-php-patterns']['duration'] );
		$this->assertSame( '$49.00', $list['intro-to-php']['price'] );
		$h1 = $x->query( '//h1' );
		$this->assertGreaterThanOrEqual( 1, $h1->length );
		$this->assertStringContainsString( 'PHP', text( $h1->item( 0 ) ) );

		list( $html ) = $this->page( '/course-topic/tools/' );
		$this->assertSame( array( 'imported-security-course', 'git-workshop' ), array_keys( listing( $html ) ) );
	}

	public function test_regular_posts_are_not_affected(): void {
		list( $html, $x ) = $this->page( '/welcome-to-the-academy/' );
		$this->assertStringContainsString( 'Our new catalog is live.', $html );
		$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-course-price' ) );
		$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-enroll-button' ) );
		$this->assertStringNotContainsString( 'acme-course-summary', $html );
	}
}

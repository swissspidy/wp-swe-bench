<?php
/**
 * Classic themes keep the 1.6 behaviour (PHP templates, summary box, listing meta).
 */

use function WPSB\Courses\activate_theme;
use function WPSB\Courses\by_class;
use function WPSB\Courses\dom;
use function WPSB\Courses\text;
use const WPSB\Courses\CLASSIC_THEME;
use const WPSB\Courses\THEME;

class ClassicThemeTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static array $pages = array();

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		activate_theme( CLASSIC_THEME );
	}

	public static function tearDownAfterClass(): void {
		$dir = WP_CONTENT_DIR . '/themes/' . CLASSIC_THEME . '/acme-courses';
		if ( is_dir( $dir ) ) {
			array_map( 'unlink', glob( $dir . '/*' ) );
			rmdir( $dir );
		}
		activate_theme( THEME );
		parent::tearDownAfterClass();
	}

	private function page( string $path ): array {
		if ( ! isset( self::$pages[ $path ] ) ) {
			$res = $this->http( 'GET', $path );
			$this->assertSame( 200, $res['status'], "GET $path" );
			self::$pages[ $path ] = $res['body'];
		}
		return array( self::$pages[ $path ], dom( self::$pages[ $path ] ) );
	}

	public function test_single_course_uses_php_template_with_summary_box(): void {
		list( $html, $x ) = $this->page( '/courses/intro-to-php/' );
		$this->assertCount( 1, by_class( $x, 'acme-classic-header' ), 'Classic theme header expected' );
		$this->assertStringNotContainsString( 'wp-site-blocks', $html );
		$this->assertSame( 'Introduction to PHP', text( by_class( $x, 'acme-course__title' )[0] ?? null ) );

		$boxes = by_class( $x, 'acme-course-summary' );
		$this->assertCount( 1, $boxes, 'Exactly one summary box expected' );
		$this->assertStringContainsString( '$49.00', text( by_class( $x, 'acme-course-summary__price', $boxes[0] )[0] ?? null ) );
		$this->assertStringContainsString( '6 weeks', text( by_class( $x, 'acme-course-summary__duration', $boxes[0] )[0] ?? null ) );
		$a = $x->query( './/a[' . WPSB\Courses\cls( 'acme-course-enroll' ) . ']', $boxes[0] );
		$this->assertSame( 1, $a->length );
		$this->assertSame( 'Start learning', text( $a->item( 0 ) ) );

		// The summary box is the first thing of the description.
		$content = by_class( $x, 'acme-course__content' )[0] ?? null;
		$this->assertNotNull( $content );
		$this->assertStringStartsWith( 'Price:', text( $content ) );
		$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-course-price' ), 'No block markup with classic themes' );
		$this->assertStringContainsString( 'href="' . home_url( '/course-topic/php/' ) . '"', $html );
	}

	public function test_closed_and_legacy_courses(): void {
		list( $html, $x ) = $this->page( '/courses/advanced-php-patterns/' );
		$box = by_class( $x, 'acme-course-summary' );
		$this->assertCount( 1, $box );
		$this->assertSame( 'Enrollment closed', text( by_class( $x, 'is-closed', $box[0] )[0] ?? null ) );

		list( $html, $x ) = $this->page( '/courses/php-bootcamp/' );
		$box = by_class( $x, 'acme-course-summary' );
		$this->assertStringContainsString( '$1,299.50', text( $box[0] ?? null ) );
		$this->assertStringContainsString( '5 days', text( $box[0] ?? null ) );
		$this->assertStringContainsString( 'https://partners.example.org/shop/php-bootcamp?ref=academy', $html );
	}

	public function test_catalog_and_topic_use_php_templates(): void {
		list( $html, $x ) = $this->page( '/courses/' );
		$this->assertCount( 1, by_class( $x, 'acme-classic-header' ) );
		$this->assertCount( 8, by_class( $x, 'acme-course-card' ) );
		$this->assertCount( 7, by_class( $x, 'acme-course-excerpt-meta' ) );
		$this->assertStringContainsString( '$49.00 &middot; 6 weeks', str_replace( '·', '&middot;', $html ) );

		list( $html, $x ) = $this->page( '/course-topic/php/' );
		$this->assertSame( 'PHP', text( by_class( $x, 'acme-courses-archive__title' )[0] ?? null ) );
		$this->assertCount( 3, by_class( $x, 'acme-course-card' ) );
	}

	public function test_theme_override_folder_still_works(): void {
		$dir = WP_CONTENT_DIR . '/themes/' . CLASSIC_THEME . '/acme-courses';
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/single-acme_course.php', "<?php get_header(); ?><main><p class=\"theme-override\">THEME OVERRIDE</p><?php while ( have_posts() ) { the_post(); the_content(); } ?></main><?php get_footer();\n" );
		$res = $this->http( 'GET', '/courses/git-workshop/' );
		$x   = dom( $res['body'] );
		$this->assertCount( 1, by_class( $x, 'theme-override' ) );
		$this->assertCount( 1, by_class( $x, 'acme-course-summary' ), 'Summary box is still injected into the content' );
		$this->assertStringContainsString( 'Free', text( by_class( $x, 'acme-course-summary' )[0] ?? null ) );
	}
}

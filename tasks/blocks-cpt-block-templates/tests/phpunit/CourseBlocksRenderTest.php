<?php
/**
 * Server rendering of the course blocks (in-process).
 */

use function WPSB\Courses\by_class;
use function WPSB\Courses\course_id;
use function WPSB\Courses\dom;
use function WPSB\Courses\text;

class CourseBlocksRenderTest extends WPSB\TestCase {

	private function render_in( string $markup, int $post_id ): string {
		global $post, $wp_query;
		$post = get_post( $post_id );
		setup_postdata( $post );
		$html = do_blocks( $markup );
		wp_reset_postdata();
		return $html;
	}

	public function test_blocks_render_the_current_course(): void {
		$id   = course_id( 'block-themes-deep-dive' );
		$html = $this->render_in( '<!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/course-duration /--><!-- wp:acme-courses/enroll-button /-->', $id );
		$x    = dom( $html );
		$this->assertSame( '$79.00', text( by_class( $x, 'wp-block-acme-courses-course-price' )[0] ?? null ) );
		$this->assertSame( 'div', strtolower( by_class( $x, 'wp-block-acme-courses-course-price' )[0]->nodeName ) );
		$this->assertSame( '1 week', text( by_class( $x, 'wp-block-acme-courses-course-duration' )[0] ?? null ) );
		$wrap = by_class( $x, 'wp-block-acme-courses-enroll-button' );
		$this->assertCount( 1, $wrap );
		$a = $x->query( './/a', $wrap[0] );
		$this->assertSame( 1, $a->length );
		$this->assertSame( 'acme-course-enroll button is-waitlist', $a->item( 0 )->getAttribute( 'class' ) );
		$this->assertSame( 'Join the waitlist', text( $a->item( 0 ) ) );
	}

	public function test_blocks_render_nothing_outside_courses(): void {
		$post = get_page_by_path( 'welcome-to-the-academy', OBJECT, 'post' );
		$html = $this->render_in( '<!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/course-duration /--><!-- wp:acme-courses/enroll-button /-->', $post->ID );
		$this->assertSame( '', trim( $html ) );

		unset( $GLOBALS['post'] );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/enroll-button /-->' ) ) );
	}

	public function test_blocks_use_the_query_loop_item(): void {
		// A Query Loop on a regular page: every item shows its own course's details.
		$page   = get_page_by_path( 'welcome-to-the-academy', OBJECT, 'post' );
		$markup = '<!-- wp:query {"queryId":7,"query":{"perPage":20,"pages":0,"offset":0,"postType":"acme_course","order":"asc","orderBy":"title","inherit":false}} -->'
			. '<div class="wp-block-query"><!-- wp:post-template -->'
			. '<!-- wp:post-title /--><!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/course-duration /--><!-- wp:acme-courses/enroll-button /-->'
			. '<!-- /wp:post-template --></div><!-- /wp:query -->';
		$html = $this->render_in( $markup, $page->ID );
		$x    = dom( $html );
		$items = by_class( $x, 'wp-block-post' );
		$this->assertCount( 8, $items );
		$seen = array();
		foreach ( $items as $item ) {
			$title          = text( by_class( $x, 'wp-block-post-title', $item )[0] ?? null );
			$price          = by_class( $x, 'wp-block-acme-courses-course-price', $item );
			$seen[ $title ] = $price ? text( $price[0] ) : null;
			$this->assertCount( 1, by_class( $x, 'wp-block-acme-courses-enroll-button', $item ), "Enroll button for $title" );
		}
		$this->assertSame(
			array(
				'Accessibility Fundamentals' => null,
				'Advanced PHP Patterns'      => '$129.00',
				'Block Themes Deep Dive'     => '$79.00',
				'Enterprise PHP Bootcamp'    => '$1,299.50',
				'Git in 90 Minutes'          => 'Free',
				'Introduction to PHP'        => '$49.00',
				'Web Security Basics'        => '$15.00',
				'WordPress Basics'           => '$29.00',
			),
			$seen
		);
	}

	public function test_existing_filters_and_settings_apply(): void {
		$id = course_id( 'intro-to-php' );
		update_option( 'acme_courses_settings', array( 'currency' => 'EUR', 'currency_position' => 'after', 'enroll_base_url' => 'https://academy.example.org/enroll', 'show_summary' => true ) );
		$html = $this->render_in( '<!-- wp:acme-courses/course-price /-->', $id );
		$this->assertSame( '49.00 €', text( by_class( dom( $html ), 'wp-block-acme-courses-course-price' )[0] ?? null ) );

		$price = static fn( $h, $cents ) => '<span class="sale">' . $h . '</span>';
		$dur   = static fn( $label ) => 'About ' . $label;
		$btn   = static fn( $h ) => $h . '<small class="guarantee">30-day guarantee</small>';
		add_filter( 'acme_courses_price_html', $price, 10, 2 );
		add_filter( 'acme_courses_duration_label', $dur );
		add_filter( 'acme_courses_enroll_button_html', $btn );
		try {
			$x = dom( $this->render_in( '<!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/course-duration /--><!-- wp:acme-courses/enroll-button /-->', $id ) );
		} finally {
			remove_filter( 'acme_courses_price_html', $price, 10 );
			remove_filter( 'acme_courses_duration_label', $dur );
			remove_filter( 'acme_courses_enroll_button_html', $btn );
		}
		$this->assertCount( 1, by_class( $x, 'sale', by_class( $x, 'wp-block-acme-courses-course-price' )[0] ) );
		$this->assertSame( 'About 6 weeks', text( by_class( $x, 'wp-block-acme-courses-course-duration' )[0] ?? null ) );
		$this->assertCount( 1, by_class( $x, 'guarantee', by_class( $x, 'wp-block-acme-courses-enroll-button' )[0] ) );
	}

	public function test_untrusted_meta_is_escaped(): void {
		$id = $this->create_post( array( 'post_type' => 'acme_course', 'post_title' => 'Evil', 'post_name' => 'evil-course' ) );
		update_post_meta( $id, '_acme_course_enroll_url', '" onmouseover="alert(1)' );
		update_post_meta( $id, 'acme_course_duration', '<script>alert(1)</script>' );
		update_post_meta( $id, 'acme_course_price', '<img src=x onerror=alert(1)>' );
		$html = $this->render_in( '<!-- wp:acme-courses/course-price /--><!-- wp:acme-courses/course-duration /--><!-- wp:acme-courses/enroll-button /-->', $id );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringNotContainsString( 'onmouseover="', $html );
		$this->assertCount( 1, by_class( dom( $html ), 'wp-block-acme-courses-enroll-button' ) );

		update_post_meta( $id, '_acme_course_enroll_url', 'javascript:alert(1)' );
		$html = $this->render_in( '<!-- wp:acme-courses/enroll-button /-->', $id );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_block_supports_are_applied_to_the_wrapper(): void {
		$id   = course_id( 'intro-to-php' );
		$html = $this->render_in( '<!-- wp:acme-courses/course-price {"className":"is-style-big"} /-->', $id );
		$el   = by_class( dom( $html ), 'wp-block-acme-courses-course-price' );
		$this->assertCount( 1, $el );
		$this->assertStringContainsString( 'is-style-big', $el[0]->getAttribute( 'class' ) );
	}
}

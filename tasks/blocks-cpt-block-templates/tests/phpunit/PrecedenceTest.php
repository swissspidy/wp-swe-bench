<?php
/**
 * Template precedence: user customizations > theme files > plugin defaults.
 */

use function WPSB\Courses\activate_theme;
use function WPSB\Courses\by_class;
use function WPSB\Courses\delete_customizations;
use function WPSB\Courses\dom;
use function WPSB\Courses\listing;
use function WPSB\Courses\make_child_theme;
use function WPSB\Courses\remove_child_theme;
use function WPSB\Courses\text;
use const WPSB\Courses\CHILD_THEME;
use const WPSB\Courses\THEME;

class PrecedenceTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function tearDown(): void {
		delete_customizations();
		activate_theme( THEME );
		remove_child_theme();
		parent::tearDown();
	}

	private function save_via_rest( string $route, string $content ): array {
		$this->login_as( 1 );
		$res = $this->rest( 'POST', $route, array(), array( 'content' => $content ) );
		$this->assertSame( 200, $res->get_status(), "Saving $route in the Site Editor failed: " . wp_json_encode( $res->get_data() ) );
		wp_set_current_user( 0 );
		return $res->get_data();
	}

	public function test_customized_single_template_wins_and_reset_restores_plugin_default(): void {
		$saved = $this->save_via_rest(
			'/wp/v2/templates/' . THEME . '//single-acme_course',
			"<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n<!-- wp:paragraph -->\n<p>CUSTOM-SINGLE-TEMPLATE</p>\n<!-- /wp:paragraph -->\n<!-- wp:post-title {\"level\":1} /-->\n<!-- wp:acme-courses/course-price /-->"
		);
		$this->assertSame( 'custom', $saved['source'] );

		$res = $this->http( 'GET', '/courses/git-workshop/' );
		$x   = dom( $res['body'] );
		$this->assertStringContainsString( 'CUSTOM-SINGLE-TEMPLATE', $res['body'], 'The template saved in the Site Editor must be used' );
		$this->assertSame( 'Free', text( by_class( $x, 'wp-block-acme-courses-course-price' )[0] ?? null ) );
		$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-enroll-button' ), 'Customized template has no enroll button' );
		$this->assertStringNotContainsString( 'acme-course-summary"', $res['body'] );

		// Other course templates are unaffected.
		$res = $this->http( 'GET', '/courses/' );
		$this->assertStringNotContainsString( 'CUSTOM-SINGLE-TEMPLATE', $res['body'] );
		$this->assertCount( 8, listing( $res['body'] ) );

		// "Clear customizations" deletes the post: the plugin default is back.
		$this->login_as( 1 );
		$del = $this->rest( 'DELETE', '/wp/v2/templates/' . THEME . '//single-acme_course', array( 'force' => 'true' ) );
		$this->assertSame( 200, $del->get_status() );
		wp_set_current_user( 0 );
		$res = $this->http( 'GET', '/courses/git-workshop/' );
		$this->assertStringNotContainsString( 'CUSTOM-SINGLE-TEMPLATE', $res['body'] );
		$this->assertCount( 1, by_class( dom( $res['body'] ), 'wp-block-acme-courses-enroll-button' ) );
	}

	public function test_customized_summary_part_is_used_on_all_course_pages(): void {
		$saved = $this->save_via_rest(
			'/wp/v2/template-parts/' . THEME . '//course-summary',
			"<!-- wp:paragraph -->\n<p>CUSTOM-SUMMARY-PART</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:acme-courses/course-duration /-->\n\n<!-- wp:acme-courses/enroll-button /-->"
		);
		$this->assertSame( 'custom', $saved['source'] );

		foreach ( array( 'intro-to-php' => '6 weeks', 'wordpress-basics' => '3 weeks' ) as $slug => $duration ) {
			$res = $this->http( 'GET', "/courses/$slug/" );
			$x   = dom( $res['body'] );
			$this->assertSame( 1, substr_count( $res['body'], 'CUSTOM-SUMMARY-PART' ), "Customized part must be rendered once on $slug" );
			$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-course-price' ), 'Price was removed from the customized part' );
			$this->assertSame( $duration, text( by_class( $x, 'wp-block-acme-courses-course-duration' )[0] ?? null ) );
		}

		// Listed once, as a customized part.
		$this->login_as( 1 );
		$parts = array_values( array_filter( $this->rest_data( $this->rest( 'GET', '/wp/v2/template-parts', array( 'context' => 'edit' ) ) ), static fn( $t ) => 'course-summary' === $t['slug'] ) );
		$this->assertCount( 1, $parts );
		$this->assertSame( 'custom', $parts[0]['source'] );
	}

	public function test_theme_templates_and_parts_win_over_plugin_defaults(): void {
		make_child_theme(
			array(
				'templates/single-acme_course.html' => "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n<!-- wp:paragraph -->\n<p>THEME-SINGLE-TEMPLATE</p>\n<!-- /wp:paragraph -->\n<!-- wp:post-title {\"level\":1} /-->\n<!-- wp:template-part {\"slug\":\"course-summary\"} /-->\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->",
				'parts/course-summary.html'         => "<!-- wp:paragraph -->\n<p>THEME-SUMMARY-PART</p>\n<!-- /wp:paragraph -->\n<!-- wp:acme-courses/course-price /-->",
			)
		);
		activate_theme( CHILD_THEME );

		$res = $this->http( 'GET', '/courses/advanced-php-patterns/' );
		$this->assertSame( 200, $res['status'] );
		$x = dom( $res['body'] );
		$this->assertStringContainsString( 'THEME-SINGLE-TEMPLATE', $res['body'], "The theme's single-acme_course template must win" );
		$this->assertStringContainsString( 'THEME-SUMMARY-PART', $res['body'], "The theme's course-summary part must win" );
		$this->assertSame( '$129.00', text( by_class( $x, 'wp-block-acme-courses-course-price' )[0] ?? null ) );
		$this->assertCount( 0, by_class( $x, 'wp-block-acme-courses-enroll-button' ) );
		$this->assertStringNotContainsString( 'acme-course-summary"', $res['body'] );

		// Templates the theme doesn't ship still come from the plugin.
		$res  = $this->http( 'GET', '/courses/' );
		$list = listing( $res['body'] );
		$this->assertCount( 8, $list );
		$this->assertSame( '$29.00', $list['wordpress-basics']['price'] );

		// A customization of the theme's part still wins over the theme file.
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$save  = $this->http(
			'POST',
			'/wp-json/wp/v2/template-parts/' . CHILD_THEME . '//course-summary',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'content' => "<!-- wp:paragraph -->\n<p>USER-OVER-THEME</p>\n<!-- /wp:paragraph -->" ),
			)
		);
		$this->assertSame( 200, $save['status'], $save['body'] );
		$res = $this->http( 'GET', '/courses/advanced-php-patterns/' );
		$this->assertStringContainsString( 'USER-OVER-THEME', $res['body'] );
		$this->assertStringNotContainsString( 'THEME-SUMMARY-PART', $res['body'] );
		wp_delete_user( $admin );
	}

	public function test_plugin_templates_follow_the_active_block_theme(): void {
		make_child_theme( array() );
		activate_theme( CHILD_THEME );
		$res = $this->http( 'GET', '/courses/intro-to-php/' );
		$x   = dom( $res['body'] );
		$this->assertStringContainsString( 'wp-site-blocks', $res['body'] );
		$this->assertSame( '$49.00', text( by_class( $x, 'wp-block-acme-courses-course-price' )[0] ?? null ) );
		$this->assertCount( 1, by_class( $x, 'wp-block-acme-courses-enroll-button' ) );

		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$list  = $this->http( 'GET', '/wp-json/wp/v2/templates?context=edit', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $list['status'] );
		$ids = array_column( $list['json'], 'id' );
		$this->assertContains( CHILD_THEME . '//single-acme_course', $ids );
		$parts = $this->http( 'GET', '/wp-json/wp/v2/template-parts?context=edit', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertContains( CHILD_THEME . '//course-summary', array_column( $parts['json'], 'id' ) );
		wp_delete_user( $admin );
	}
}

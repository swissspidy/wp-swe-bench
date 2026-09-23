<?php
/**
 * Classic themes keep the old behaviour (form appended to single posts).
 */

use function WPSB\Newsletter\analyse;
use function WPSB\Newsletter\legacy_settings;
use function WPSB\Newsletter\settings_with_placements;
use function WPSB\Newsletter\field_value;
use const WPSB\Newsletter\OPTION;

class ClassicThemeTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_option;
	private string $saved_theme;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_option = get_option( OPTION );
		$this->saved_theme  = get_stylesheet();
		$this->assertTrue( wp_get_theme( 'acme-classic' )->exists() );
		switch_theme( 'acme-classic' );
	}

	protected function tearDown(): void {
		switch_theme( $this->saved_theme );
		update_option( OPTION, $this->saved_option );
		parent::tearDown();
	}

	private function page( string $path ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		$this->assertStringContainsString( 'site-footer', $res['body'], 'classic theme not active?' );
		return analyse( $res['body'] ) + array( 'html' => $res['body'] );
	}

	public function test_single_posts_get_the_form_appended_to_the_content(): void {
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['forms'], 'classic theme: exactly one form on a single post' );
		$this->assertCount( 1, $p['in_content'], 'classic theme: the form is appended to the content' );
		$this->assertSame( 'content', field_value( $p['forms'][0], 'acme_source' ) );
		$this->assertStringContainsString( 'We moved the blog to a new theme', $p['html'] );
	}

	public function test_no_duplicates_and_filters_in_classic_themes(): void {
		$p = $this->page( '/spring-campaign/' );
		$this->assertCount( 1, $p['forms'] );
		$this->assertSame( 'block', field_value( $p['forms'][0], 'acme_source' ) );

		$p = $this->page( '/classic-post/' );
		$this->assertCount( 1, $p['forms'] );
		$this->assertSame( 'shortcode', field_value( $p['forms'][0], 'acme_source' ) );

		$p = $this->page( '/sponsored-review/' );
		$this->assertCount( 0, $p['forms'] );

		$p = $this->page( '/about/' );
		$this->assertCount( 0, $p['forms'], 'pages never got the form' );
	}

	public function test_classic_theme_respects_the_legacy_setting(): void {
		update_option( OPTION, legacy_settings( array( 'auto_insert' => false ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['forms'] );
	}

	public function test_classic_theme_follows_the_new_placement_settings(): void {
		update_option( OPTION, settings_with_placements( array( 'footer' ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['forms'], 'after_content is off' );

		update_option( OPTION, settings_with_placements( array( 'after_content' ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['in_content'] );
		$this->assertCount( 1, $p['forms'] );
	}

	public function test_widget_still_renders(): void {
		ob_start();
		the_widget( 'Acme\Newsletter\Widget', array( 'title' => 'Sign up now' ), array( 'before_widget' => '<section class="widget">', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>' ) );
		$html = ob_get_clean();
		$a    = analyse( '<html><body>' . $html . '</body></html>' );
		$this->assertCount( 1, $a['forms'] );
		$this->assertSame( 'widget', field_value( $a['forms'][0], 'acme_source' ) );
		$this->assertStringContainsString( 'Sign up now', $html );
	}
}

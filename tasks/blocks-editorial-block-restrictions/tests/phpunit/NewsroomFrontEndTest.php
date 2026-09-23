<?php
/**
 * Existing content renders for everybody; settings screen keeps working.
 */

class NewsroomFrontEndTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_rules;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_rules = get_option( 'acme_newsroom_block_rules' );
	}

	protected function tearDown(): void {
		update_option( 'acme_newsroom_block_rules', $this->saved_rules );
		parent::tearDown();
	}

	public function test_published_content_with_restricted_blocks_renders_for_visitors(): void {
		$res = $this->http( 'GET', '/widget-recall/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( '<div class="live-ticker">Live updates below</div>', $res['body'] );
		$this->assertMatchesRegularExpression( '/class="[^"]*wp-block-acme-breaking-banner[^"]*is-level-breaking|class="[^"]*is-level-breaking[^"]*wp-block-acme-breaking-banner/', $res['body'] );
		$this->assertStringContainsString( 'Widget recall announced', $res['body'] );
		$this->assertStringContainsString( '<li>Batch 2026-18</li>', $res['body'] );

		$res = $this->http( 'GET', '/press/q3-results/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'data-symbol="ACME"', $res['body'] );
		$this->assertStringContainsString( 'acme-dateline__city">ZURICH<', $res['body'] );
		$this->assertStringContainsString( 'Acme Corp builds dependable widgets', $res['body'] );
		$this->assertStringContainsString( 'Mira Press', $res['body'] );
		$this->assertStringContainsString( 'press-release__lead', $res['body'] );

		$res = $this->http( 'GET', '/press/new-headquarters/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( '<iframe title="Headquarters tour"', $res['body'] );
	}

	public function test_content_renders_the_same_for_logged_in_restricted_users(): void {
		foreach ( array( 'contributor', 'author' ) as $role ) {
			$login = $this->http_login( $this->create_user( $role ) );
			$res   = $this->http( 'GET', '/widget-recall/', array( 'login' => $login ) );
			$this->assertSame( 200, $res['status'] );
			$this->assertStringContainsString( '<div class="live-ticker">Live updates below</div>', $res['body'], "as $role" );
			$this->assertStringContainsString( 'Widget recall announced', $res['body'], "as $role" );
		}

		// In-process too, with a contributor as the current user (e.g. previews).
		wp_set_current_user( $this->create_user( 'contributor' ) );
		$post = get_page_by_path( 'widget-recall', OBJECT, 'post' );
		$html = do_blocks( $post->post_content );
		$this->assertStringContainsString( 'live-ticker', $html );
		$this->assertStringContainsString( 'Widget recall announced', $html );
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'core/html' ) );
		wp_set_current_user( 0 );
	}

	public function test_rest_api_still_saves_disallowed_blocks_of_existing_content(): void {
		$contributor = get_user_by( 'login', 'contributor1' );
		$draft       = get_posts(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
				'title'       => 'Widget pros and cons',
				'numberposts' => 1,
			)
		)[0];
		$login       = $this->http_login( $contributor->ID );
		$content     = str_replace( 'Draft from our freelance contributor.', 'Draft, edited.', $draft->post_content );
		$res         = $this->http(
			'POST',
			'/wp-json/wp/v2/posts/' . $draft->ID,
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'content' => $content ),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		clean_post_cache( $draft->ID );
		$saved = get_post( $draft->ID )->post_content;
		$this->assertStringContainsString( 'Draft, edited.', $saved );
		$this->assertStringContainsString( 'poll-widget', $saved );
		$this->assertStringContainsString( '<!-- wp:columns -->', $saved );
		wp_update_post( array( 'ID' => $draft->ID, 'post_content' => $draft->post_content ) );
	}

	public function test_settings_screen_saves_rules_in_the_documented_format(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$page  = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-newsroom', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'acme_newsroom_block_rules[post_types][press_release][allowed]', html_entity_decode( $page['body'] ) );
		$this->assertMatchesRegularExpression( '/name=["\']option_page["\'] value=["\']acme-newsroom-rules["\']/', $page['body'] );
		preg_match_all( '/<form[^>]*>.*?<\/form>/s', $page['body'], $forms );
		$rules_form = '';
		foreach ( $forms[0] as $f ) {
			if ( false !== strpos( $f, 'acme-newsroom-rules' ) ) {
				$rules_form = $f;
			}
		}
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([a-f0-9]+)"/', $rules_form );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $rules_form, $m );

		$res = $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'               => 'acme-newsroom-rules',
					'action'                    => 'update',
					'_wpnonce'                  => $m[1],
					'_wp_http_referer'          => '/wp-admin/options-general.php?page=acme-newsroom',
					'acme_newsroom_block_rules' => array(
						'post_types'            => array(
							'post'          => array(
								'allowed' => '',
								'roles'   => array(
									'contributor' => "core/paragraph\nacme/*\nnot a block\n",
									'author'      => '',
								),
							),
							'press_release' => array(
								'restrict' => '1',
								'allowed'  => "core/paragraph\r\ncore/list",
								'roles'    => array( 'contributor' => '' ),
							),
						),
						'disabled_design_tools' => array( 'author' => array( 'custom_font_sizes', 'bogus' ) ),
					),
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ) );
		wp_cache_delete( 'acme_newsroom_block_rules', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertSame(
			array(
				'post_types'            => array(
					'post'          => array(
						'allowed' => null,
						'roles'   => array( 'contributor' => array( 'core/paragraph', 'acme/*' ) ),
					),
					'press_release' => array(
						'allowed' => array( 'core/paragraph', 'core/list' ),
						'roles'   => array(),
					),
				),
				'disabled_design_tools' => array( 'author' => array( 'custom_font_sizes' ) ),
			),
			get_option( 'acme_newsroom_block_rules' )
		);
	}

	public function test_newsroom_blocks_and_post_type_still_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array( 'acme/dateline', 'acme/boilerplate', 'acme/media-contact', 'acme/breaking-banner' ) as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), $name );
		}
		$this->assertTrue( post_type_exists( 'press_release' ) );
		$this->assertTrue( (bool) get_post_type_object( 'press_release' )->show_in_rest );
		$html = do_blocks( '<!-- wp:acme/breaking-banner {"text":"Hello <script>alert(1)</script>","level":"update"} /-->' );
		$this->assertStringContainsString( 'Update:', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}
}

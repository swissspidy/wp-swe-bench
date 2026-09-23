<?php
/**
 * Upgrade from the fourteen 1.x options to acme_social_settings.
 */

class MigrationTest extends AcmeSocialCase {

	public function test_seeded_site_is_migrated(): void {
		// The first requests after the deploy already happened (server start, this process).
		$this->assertSettings( $this->expected_pristine(), $this->stored() );
		$this->assertSame( array(), $this->legacy_rows(), 'The 1.x option rows must be deleted' );
		$this->assertSame( '2.0.0', $this->raw_option( 'acme_social_version' ) );
	}

	public function test_first_front_end_request_after_deploy_upgrades_and_renders_the_same(): void {
		$this->seed_legacy_site( $this->pristine_legacy_rows() );
		$this->assertNull( $this->raw_option( self::OPTION ) );

		// The very first request after the deploy is a visitor on the front end.
		$html = $this->page( '/launch-week/' );
		$this->assertSame( array( 'facebook', 'twitter', 'linkedin', 'mastodon' ), $this->share_networks( $html ) );
		$this->assertStringContainsString( 'acme-social-share--icons_text', $html );
		$this->assertSame( '@AcmeHQ', $this->meta( $html, 'name', 'twitter:site' ) );
		$this->assertSame( '1234567890', $this->meta( $html, 'property', 'fb:app_id' ) );
		$this->assertStringEndsWith( 'acme-share.png', (string) $this->meta( $html, 'property', 'og:image' ) );

		$this->assertSettings( $this->expected_pristine(), $this->stored() );
		$this->assertSame( array(), $this->legacy_rows() );
		$this->assertSame( '2.0.0', $this->raw_option( 'acme_social_version' ) );
	}

	/**
	 * @return array<string, array{0: callable(self): array, 1: callable(self): array}>
	 */
	public static function legacy_sites(): array {
		return array(
			'formats from 1.0 and 1.1'      => array(
				static fn( self $t ) => array(
					'acme_social_share_buttons_enabled' => '0',
					'acme_social_networks'              => array( 'Facebook', 'X', 'whatsapp', 'email', 'digg' ),
					'acme_share_position'               => 'top',
					'acme_social_post_types'            => 'post, event ,nope',
					'acmesocial_button_style'           => 'both',
					'acme_social_twitter'               => 'https://twitter.com/Old_Handle',
					'acme_social_facebook'              => ' https://facebook.com/acme ',
					'acme_social_instagram_url'         => 'javascript:alert(1)',
					'acme_social_youtube_channel'       => 'youtube.com/@acme',
					'acme_og_enabled'                   => '',
					'acme_og_default_image'             => (string) $t->post_id( 'about-us' ),
					'acme_social_fb_app_id'             => 'abc',
					'acme_social_twitter_card'          => 'summary',
				),
				static fn( self $t ) => array(
					'share_enabled'    => false,
					'networks'         => array( 'facebook', 'twitter', 'whatsapp', 'email' ),
					'position'         => 'before',
					'post_types'       => array( 'post', 'event' ),
					'button_style'     => 'icons_text',
					'twitter'          => 'Old_Handle',
					'facebook'         => 'https://facebook.com/acme',
					'instagram'        => '',
					'linkedin'         => '',
					'youtube'          => 'http://youtube.com/@acme',
					'og_enabled'       => false,
					'og_default_image' => 0,
					'fb_app_id'        => '',
					'twitter_card'     => 'summary',
				),
			),
			'only a few options were saved' => array(
				static fn( self $t ) => array(
					'acme_social_twitter'      => 'x.com/@tiny_one',
					'acme_social_networks'     => 'twitter',
					'acme_og_default_image'    => (string) $t->image_id(),
					'acme_social_twitter_card' => 'bogus',
				),
				static fn( self $t ) => array_merge(
					$t->defaults(),
					array(
						'twitter'          => 'tiny_one',
						'networks'         => array( 'twitter' ),
						'og_default_image' => $t->image_id(),
					)
				),
			),
			'nothing was ever saved'        => array(
				static fn( self $t ) => array(),
				static fn( self $t ) => $t->defaults(),
			),
		);
	}

	/**
	 * @dataProvider legacy_sites
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'legacy_sites' )]
	public function test_legacy_formats_are_migrated_as_1_6_interprets_them( callable $rows, callable $expected ): void {
		$this->seed_legacy_site( $rows( $this ), '1.5.0' );
		$this->fresh_request();
		$this->assertSettings( $expected( $this ), $this->stored() );
		$this->assertSame( array(), $this->legacy_rows() );
		$this->assertSame( '2.0.0', $this->raw_option( 'acme_social_version' ) );
	}

	public function test_upgrade_running_again_does_not_touch_current_settings(): void {
		$current = array_merge(
			$this->expected_pristine(),
			array(
				'twitter'    => 'Changed',
				'networks'   => array( 'email' ),
				'og_enabled' => false,
			)
		);
		$this->set_raw_option( self::OPTION, $current );
		// A restored backup brings back the old version number and a stale old row.
		$this->set_raw_option( 'acme_social_version', '1.6.2' );
		$this->set_raw_option( 'acme_social_twitter', '@Stale' );
		$this->set_raw_option( 'acme_share_position', 'top' );

		$this->fresh_request();
		$this->fresh_request();

		$this->assertSettings( $current, $this->stored() );
		$this->assertSame( array(), $this->legacy_rows() );
		$this->assertSame( '2.0.0', $this->raw_option( 'acme_social_version' ) );
		$this->assertSame( '@Changed', $this->cli_json( 'get_option( "acme_social_twitter" )' ) );
	}
}

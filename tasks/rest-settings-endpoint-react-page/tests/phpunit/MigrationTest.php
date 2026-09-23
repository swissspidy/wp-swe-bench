<?php
/**
 * Upgrade from the twelve 1.x options to acme_seo_settings.
 */

class MigrationTest extends AcmeSeoCase {

	public function test_seeded_site_is_migrated(): void {
		// The first request after the deploy already ran (server start / this PHP process).
		$this->assertSettings( $this->expected_pristine(), $this->settings() );
		$this->assertNoOldOptions();
		$this->assertContains( 'acme_seo_settings', $this->stored_option_names() );
	}

	/**
	 * @return array<string, array{0: callable(self): array, 1: callable(self): array}>
	 */
	public static function legacy_sites(): array {
		return array(
			'values from 1.0 and 1.2'             => array(
				static fn( self $t ) => array(
					'acme_seo_version'            => '1.9.2',
					'acme_seo_title_separator'    => '&raquo;',
					'acme_seo_home_title'         => '{site} {sep} {tagline}',
					'acme_seo_noindex_post_types' => 'post, event',
					'acme_seo_og_enabled'         => '1',
					'acme_seo_og_default_image'   => wp_get_attachment_url( $t->attachment_id( 'Legacy share image' ) ),
					'acme_seo_twitter_handle'     => 'https://twitter.com/AcmeCorp',
					'acme_seo_social_profiles'    => array(
						'facebook'  => 'http://fb.com/acme',
						'instagram' => 'javascript:alert(1)',
						'myspace'   => 'https://myspace.com/acme',
					),
					'acme_seo_sitemap_enabled'    => '',
					'acme_seo_sitemap_exclude'    => '12, 15,abc,12',
					'acme_seo_verification'       => array(
						'google' => '<meta name="google-site-verification" content="Zz_-0123456789abcdefGHIJ" />',
						'bing'   => 'nothex',
					),
				),
				static fn( self $t ) => array(
					'titles'       => array(
						'separator'        => '»',
						'home_title'       => '%%sitename%% %%sep%% %%tagline%%',
						'home_description' => '',
					),
					'indexing'     => array(
						'noindex_post_types'      => array( 'post', 'event' ),
						'noindex_author_archives' => false,
						'noindex_date_archives'   => true,
						'noindex_tag_archives'    => false,
					),
					'social'       => array(
						'og_enabled'     => true,
						'default_image'  => $t->attachment_id( 'Legacy share image' ),
						'twitter_handle' => 'AcmeCorp',
						'profiles'       => array(
							'facebook'  => 'http://fb.com/acme',
							'instagram' => '',
							'linkedin'  => '',
							'youtube'   => '',
						),
					),
					'sitemap'      => array(
						'enabled' => true,
						'exclude' => array( 12, 15 ),
					),
					'verification' => array(
						'google' => 'Zz_-0123456789abcdefGHIJ',
						'bing'   => '',
					),
				),
			),
			'only a home title'                   => array(
				static fn( self $t ) => array(
					'acme_seo_version'    => '1.9.2',
					'acme_seo_home_title' => 'Just a <em>title</em> %%sep%% %%sitename%%',
				),
				static fn( self $t ) => array(
					'titles'       => array(
						'separator'        => '-',
						'home_title'       => 'Just a title %%sep%% %%sitename%%',
						'home_description' => '',
					),
					'indexing'     => array(
						'noindex_post_types'      => array(),
						'noindex_author_archives' => false,
						'noindex_date_archives'   => true,
						'noindex_tag_archives'    => false,
					),
					'social'       => array(
						'og_enabled'     => true,
						'default_image'  => 0,
						'twitter_handle' => '',
						'profiles'       => array(
							'facebook'  => '',
							'instagram' => '',
							'linkedin'  => '',
							'youtube'   => '',
						),
					),
					'sitemap'      => array(
						'enabled' => true,
						'exclude' => array(),
					),
					'verification' => array(
						'google' => '',
						'bing'   => '',
					),
				),
			),
			'site still on 1.5 (older upgrades)'  => array(
				static fn( self $t ) => array(
					'acme_seo_version'         => '1.5.0',
					'acme_seo_twitter'         => '@oldhandle',
					'acme_seo_noindex_author'  => '1',
					'acme_seo_noindex_date'    => '0',
					'acme_seo_noindex_tag'     => 'yes',
					'acme_seo_og_enabled'      => 'no',
					'acme_seo_title_separator' => '&ndash;',
					'acme_seo_sitemap_enabled' => '0',
				),
				static fn( self $t ) => array(
					'titles'       => array(
						'separator'        => '–',
						'home_title'       => '%%sitename%% %%sep%% %%tagline%%',
						'home_description' => '',
					),
					'indexing'     => array(
						'noindex_post_types'      => array(),
						'noindex_author_archives' => true,
						'noindex_date_archives'   => false,
						'noindex_tag_archives'    => true,
					),
					'social'       => array(
						'og_enabled'     => false,
						'default_image'  => 0,
						'twitter_handle' => 'oldhandle',
						'profiles'       => array(
							'facebook'  => '',
							'instagram' => '',
							'linkedin'  => '',
							'youtube'   => '',
						),
					),
					'sitemap'      => array(
						'enabled' => false,
						'exclude' => array(),
					),
					'verification' => array(
						'google' => '',
						'bing'   => '',
					),
				),
			),
			'invalid values'                      => array(
				static fn( self $t ) => array(
					'acme_seo_version'            => '1.9.2',
					'acme_seo_title_separator'    => 'X',
					'acme_seo_home_description'   => "  Line <strong>one</strong>\n",
					'acme_seo_noindex_post_types' => array( 'post', 'product', 'post' ),
					'acme_seo_noindex_archives'   => array( 'date' => '' ),
					'acme_seo_og_enabled'         => 'maybe',
					'acme_seo_og_default_image'   => 'https://cdn.elsewhere.example/share.png',
					'acme_seo_twitter_handle'     => '@way_too_long_handle_123',
					'acme_seo_social_profiles'    => 'https://facebook.com/acme',
					'acme_seo_sitemap_exclude'    => array( '3', 0, -4, 'x' ),
					'acme_seo_verification'       => 'abc',
				),
				static fn( self $t ) => array(
					'titles'       => array(
						'separator'        => '-',
						'home_title'       => '%%sitename%% %%sep%% %%tagline%%',
						'home_description' => 'Line one',
					),
					'indexing'     => array(
						'noindex_post_types'      => array( 'post' ),
						'noindex_author_archives' => false,
						'noindex_date_archives'   => false,
						'noindex_tag_archives'    => false,
					),
					'social'       => array(
						'og_enabled'     => false,
						'default_image'  => 0,
						'twitter_handle' => '',
						'profiles'       => array(
							'facebook'  => '',
							'instagram' => '',
							'linkedin'  => '',
							'youtube'   => '',
						),
					),
					'sitemap'      => array(
						'enabled' => true,
						'exclude' => array( 3 ),
					),
					'verification' => array(
						'google' => '',
						'bing'   => '',
					),
				),
			),
		);
	}

	/**
	 * @dataProvider legacy_sites
	 */
	public function test_legacy_formats_are_migrated_like_1_9_reads_them( callable $legacy, callable $expected ): void {
		$this->seed_raw( $legacy( $this ) );

		// First request after the deploy: WP-CLI.
		$this->cli_eval( 'echo "loaded";' );

		$this->assertNoOldOptions();
		$this->assertNotContains( 'acme_seo_twitter', $this->stored_option_names() );
		$this->assertSettings( $expected( $this ), $this->settings() );
	}

	public function test_first_request_can_be_a_front_end_request(): void {
		$this->seed_raw(
			array(
				'acme_seo_version'         => '1.9.2',
				'acme_seo_title_separator' => '·',
				'acme_seo_twitter_handle'  => 'frontend',
			)
		);
		$res = $this->page( '/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( '·', $this->title( $res['body'] ) );
		$this->assertNoOldOptions();
		$settings = $this->settings();
		$this->assertSame( '·', $settings['titles']['separator'] );
		$this->assertSame( 'frontend', $settings['social']['twitter_handle'] );
	}

	public function test_running_the_upgrade_again_changes_nothing(): void {
		$res = $this->save(
			array(
				'social' => array( 'twitter_handle' => 'edited_later' ),
				'titles' => array( 'separator' => '·' ),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$before = $this->settings();
		$this->assertSame( 'edited_later', $before['social']['twitter_handle'] );

		// A restored backup resets the version marker.
		global $wpdb;
		$wpdb->update( $wpdb->options, array( 'option_value' => '1.9.2' ), array( 'option_name' => 'acme_seo_version' ) );
		wp_cache_flush();
		$this->cli_eval( 'echo "loaded";' );
		$this->page( '/' );

		$this->assertSettings( $before, $this->settings() );
		$this->assertNoOldOptions();

		// And once more from a 1.x version marker with nothing else stored.
		$wpdb->update( $wpdb->options, array( 'option_value' => '1.5.0' ), array( 'option_name' => 'acme_seo_version' ) );
		wp_cache_flush();
		$this->cli_eval( 'echo "loaded";' );
		$this->assertSettings( $before, $this->settings() );
	}

	public function test_unset_settings_read_as_defaults(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'acme_seo_settings'" );
		wp_cache_flush();
		$settings = $this->settings();
		$this->assertSettings(
			array(
				'titles'       => array(
					'separator'        => '-',
					'home_title'       => '%%sitename%% %%sep%% %%tagline%%',
					'home_description' => '',
				),
				'indexing'     => array(
					'noindex_post_types'      => array(),
					'noindex_author_archives' => false,
					'noindex_date_archives'   => true,
					'noindex_tag_archives'    => false,
				),
				'social'       => array(
					'og_enabled'     => true,
					'default_image'  => 0,
					'twitter_handle' => '',
					'profiles'       => array(
						'facebook'  => '',
						'instagram' => '',
						'linkedin'  => '',
						'youtube'   => '',
					),
				),
				'sitemap'      => array(
					'enabled' => true,
					'exclude' => array(),
				),
				'verification' => array(
					'google' => '',
					'bing'   => '',
				),
			),
			$settings
		);
	}
}

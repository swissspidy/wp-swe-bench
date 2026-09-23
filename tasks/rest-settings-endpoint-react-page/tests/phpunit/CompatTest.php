<?php
/**
 * Other code keeps working: acme_seo_get_option(), get_option() for three old names,
 * front-end output (incl. the Acme Social Share mu-plugin).
 */

class CompatTest extends AcmeSeoCase {

	public function test_public_accessor_returns_the_same_values_as_1_9(): void {
		$values = $this->cli_json(
			'array_combine( $k = array( "title_separator", "home_title", "home_description", "noindex_post_types", "noindex_archives", "og_enabled", "og_default_image", "twitter_handle", "social_profiles", "sitemap_enabled", "sitemap_exclude", "verification" ), array_map( "acme_seo_get_option", $k ) ) + array( "unknown" => acme_seo_get_option( "nope", "fallback" ) )'
		);
		$this->assertSame(
			array(
				'title_separator'    => '|',
				'home_title'         => 'Welcome to %%sitename%% %%sep%% %%tagline%%',
				'home_description'   => 'The best widgets in town & more',
				'noindex_post_types' => array( 'page' ),
				'noindex_archives'   => array(
					'author' => true,
					'date'   => false,
					'tag'    => true,
				),
				'og_enabled'         => true,
				'og_default_image'   => $this->attachment_id( 'Acme share image' ),
				'twitter_handle'     => 'AcmeCorp',
				'social_profiles'    => array(
					'facebook'  => 'https://www.facebook.com/acmewidgets',
					'instagram' => '',
					'linkedin'  => 'https://www.linkedin.com/company/acme-widgets',
					'youtube'   => '',
				),
				'sitemap_enabled'    => true,
				'sitemap_exclude'    => array( $this->post_id( 'internal-notes' ) ),
				'verification'       => array(
					'google' => 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd',
					'bing'   => '0123456789ABCDEF0123456789ABCDEF',
				),
				'unknown'            => 'fallback',
			),
			$values
		);
		$this->assertSame( 'Hi | there', trim( $this->cli_eval( 'echo acme_seo_title_template( "Hi %%sep%% there" );' ) ) );
	}

	private function old_names(): array {
		return $this->cli_json( 'array( "twitter" => get_option( "acme_seo_twitter_handle" ), "image" => get_option( "acme_seo_og_default_image" ), "profiles" => get_option( "acme_seo_social_profiles" ) )' );
	}

	public function test_old_option_names_return_the_current_settings(): void {
		$image = $this->attachment_id( 'Acme share image' );
		$this->assertSame(
			array(
				'twitter'  => '@AcmeCorp',
				'image'    => $image,
				'profiles' => array(
					'facebook'  => 'https://www.facebook.com/acmewidgets',
					'instagram' => '',
					'linkedin'  => 'https://www.linkedin.com/company/acme-widgets',
					'youtube'   => '',
				),
			),
			$this->old_names()
		);

		$legacy = $this->attachment_id( 'Legacy share image' );
		$res    = $this->save(
			array(
				'social' => array(
					'twitter_handle' => 'acme_news',
					'default_image'  => $legacy,
					'profiles'       => array(
						'facebook'  => '',
						'instagram' => 'https://instagram.com/acme',
					),
				),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame(
			array(
				'twitter'  => '@acme_news',
				'image'    => $legacy,
				'profiles' => array(
					'facebook'  => '',
					'instagram' => 'https://instagram.com/acme',
					'linkedin'  => 'https://www.linkedin.com/company/acme-widgets',
					'youtube'   => '',
				),
			),
			$this->old_names()
		);

		$this->save( array( 'social' => array( 'twitter_handle' => '', 'default_image' => 0 ) ) );
		$names = $this->old_names();
		$this->assertSame( '', $names['twitter'] );
		$this->assertSame( 0, $names['image'] );
		$this->assertNoOldOptions();

		// The share bar (reads those options directly) follows along.
		$post = $this->page( '/widget-launch/' )['body'];
		$this->assertStringContainsString( 'class="acme-share" data-twitter=""', $post );
		$this->assertStringNotContainsString( 'acme-share-image', $post );
		$this->assertStringContainsString( 'acme-follow-instagram', $post );
		$this->assertStringNotContainsString( 'acme-follow-facebook', $post );
	}

	public function test_front_end_output_is_unchanged(): void {
		$res = $this->page( '/' );
		$this->assertSame( 200, $res['status'] );
		$home = $res['body'];
		$this->assertSame( 'Welcome to Acme Widgets | Widgets for everyone', $this->title( $home ) );
		$this->assertSame( 'The best widgets in town & more', $this->meta( $home, 'name', 'description' ) );
		$this->assertSame( 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd', $this->meta( $home, 'name', 'google-site-verification' ) );
		$this->assertSame( '0123456789ABCDEF0123456789ABCDEF', $this->meta( $home, 'name', 'msvalidate.01' ) );
		$this->assertSame( '@AcmeCorp', $this->meta( $home, 'name', 'twitter:site' ) );
		$this->assertSame( 'Welcome to Acme Widgets | Widgets for everyone', $this->meta( $home, 'property', 'og:title' ) );
		$this->assertStringContainsString( 'acme-share', (string) $this->meta( $home, 'property', 'og:image' ) );
		$this->assertMatchesRegularExpression( '#"sameAs":\["https://www.facebook.com/acmewidgets","https://www.linkedin.com/company/acme-widgets"\]#', $home );
		$this->assertStringNotContainsString( 'noindex', $this->robots( $home ) );

		$post = $this->page( '/widget-launch/' )['body'];
		$this->assertSame( 'Our new widget is here.', $this->meta( $post, 'name', 'description' ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots( $post ) );
		$this->assertStringContainsString( 'data-twitter="@AcmeCorp"', $post );
		$this->assertStringContainsString( 'class="acme-share-image"', $post );
		$this->assertStringContainsString( 'acme-follow-facebook', $post );
		$this->assertStringContainsString( 'acme-follow-linkedin', $post );
		$this->assertStringContainsString( 'Widget launch | Acme Widgets', $post );

		$this->assertStringContainsString( 'noindex', $this->robots( $this->page( '/about-us/' )['body'] ) );
		$this->assertStringContainsString( 'noindex', $this->robots( $this->page( '/tag/news/' )['body'] ) );
		$this->assertStringContainsString( 'noindex', $this->robots( $this->page( '/author/admin/' )['body'] ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots( $this->page( '/2026/05/' )['body'] ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots( $this->page( '/event/launch-party/' )['body'] ) );

		$this->assertSame( 200, $this->page( '/wp-sitemap.xml' )['status'] );
		$sitemap = $this->page( '/wp-sitemap-posts-post-1.xml' );
		$this->assertSame( 200, $sitemap['status'] );
		$this->assertStringContainsString( '/widget-launch/', $sitemap['body'] );
		$this->assertStringNotContainsString( '/internal-notes/', $sitemap['body'] );
		$this->assertSame( 404, $this->page( '/wp-sitemap-posts-page-1.xml' )['status'] );
	}
}

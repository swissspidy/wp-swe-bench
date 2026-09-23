<?php
/**
 * acme_seo_settings on /wp/v2/settings: schema, validation, partial updates, access.
 */

class RestSettingsTest extends AcmeSeoCase {

	public function test_only_administrators_can_read_or_write(): void {
		$editor = $this->user_login( 'erin' );
		$res    = $this->http( 'GET', '/wp-json/wp/v2/settings', array( 'login' => $editor, 'rest_nonce' => true ) );
		$this->assertSame( 403, $res['status'] );
		$res = $this->save( array( 'social' => array( 'twitter_handle' => 'editor' ) ), $editor );
		$this->assertSame( 403, $res['status'] );
		$res = $this->http( 'GET', '/wp-json/wp/v2/settings' );
		$this->assertSame( 401, $res['status'] );
		$this->assertStringNotContainsString( 'AcmeCorp', $res['body'] );
	}

	public function test_schema_describes_the_nested_structure(): void {
		$res = $this->http( 'OPTIONS', '/wp-json/wp/v2/settings', array( 'login' => $this->admin(), 'rest_nonce' => true ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$schema = $res['json']['schema']['properties']['acme_seo_settings'] ?? null;
		$this->assertIsArray( $schema, 'acme_seo_settings missing from the settings schema' );
		$this->assertSame( 'object', $schema['type'] );

		$p = $schema['properties'];
		$this->assertSame( array( 'indexing', 'sitemap', 'social', 'titles', 'verification' ), self::sorted_keys( $p ) );
		$this->assertSame( array( 'home_description', 'home_title', 'separator' ), self::sorted_keys( $p['titles']['properties'] ) );
		$this->assertEqualsCanonicalizing( array( '-', '–', '|', '·', '»' ), $p['titles']['properties']['separator']['enum'] );
		$this->assertSame( 'array', $p['indexing']['properties']['noindex_post_types']['type'] );
		$this->assertSame( 'boolean', $p['indexing']['properties']['noindex_date_archives']['type'] );
		$this->assertSame( 'integer', $p['social']['properties']['default_image']['type'] );
		$this->assertSame( 'object', $p['social']['properties']['profiles']['type'] );
		$this->assertSame( array( 'facebook', 'instagram', 'linkedin', 'youtube' ), self::sorted_keys( $p['social']['properties']['profiles']['properties'] ) );
		$this->assertSame( 'array', $p['sitemap']['properties']['exclude']['type'] );
		$this->assertSame( array( 'bing', 'google' ), self::sorted_keys( $p['verification']['properties'] ) );
	}

	private static function sorted_keys( array $a ): array {
		$keys = array_keys( $a );
		sort( $keys );
		return $keys;
	}

	public function test_partial_updates_keep_everything_else(): void {
		$before = $this->settings();

		$res = $this->save( array( 'social' => array( 'twitter_handle' => 'acme_news' ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$expected                             = $before;
		$expected['social']['twitter_handle'] = 'acme_news';
		$this->assertSettings( $expected, $res['json']['acme_seo_settings'] );
		$this->assertSettings( $expected, $this->settings() );

		$res = $this->save(
			array(
				'indexing' => array( 'noindex_post_types' => array( 'event' ) ),
				'sitemap'  => array( 'exclude' => array() ),
				'social'   => array( 'profiles' => array( 'youtube' => 'https://www.youtube.com/@acme' ) ),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$expected['indexing']['noindex_post_types'] = array( 'event' );
		$expected['sitemap']['exclude']             = array();
		$expected['social']['profiles']['youtube']  = 'https://www.youtube.com/@acme';
		$this->assertSettings( $expected, $this->settings() );

		// A full object works too.
		$full                                        = $expected;
		$full['titles']['separator']                 = '»';
		$full['indexing']['noindex_author_archives'] = false;
		$full['social']['og_enabled']                = false;
		$full['sitemap']['exclude']                  = array( 3, 7 );
		$res = $this->save( $full );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSettings( $full, $this->settings() );
	}

	public function test_html_is_stripped_from_texts(): void {
		$res = $this->save(
			array(
				'titles' => array(
					'home_title'       => 'Shop <b>now</b> %%sep%% %%sitename%%',
					'home_description' => '<script>alert(1)</script>Great <a href="https://evil.example">widgets</a>',
				),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$titles = $this->settings()['titles'];
		$this->assertSame( 'Shop now %%sep%% %%sitename%%', $titles['home_title'] );
		$this->assertStringNotContainsString( '<', $titles['home_description'] );
		$this->assertStringContainsString( 'Great widgets', $titles['home_description'] );

		$home = $this->page( '/' );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $home['body'] );
	}

	/**
	 * @return array<string, array{0: array}>
	 */
	public static function invalid_values(): array {
		return array(
			'unknown separator'       => array( array( 'titles' => array( 'separator' => '/' ) ) ),
			'home title too long'     => array( array( 'titles' => array( 'home_title' => str_repeat( 'a', 201 ) ) ) ),
			'description too long'    => array( array( 'titles' => array( 'home_description' => str_repeat( 'a', 321 ) ) ) ),
			'unregistered post type'  => array( array( 'indexing' => array( 'noindex_post_types' => array( 'post', 'product' ) ) ) ),
			'duplicate post types'    => array( array( 'indexing' => array( 'noindex_post_types' => array( 'post', 'post' ) ) ) ),
			'archive flag not bool'   => array( array( 'indexing' => array( 'noindex_tag_archives' => 'sometimes' ) ) ),
			'negative image'          => array( array( 'social' => array( 'default_image' => -1 ) ) ),
			'image not a number'      => array( array( 'social' => array( 'default_image' => 'hero.png' ) ) ),
			'handle with @'           => array( array( 'social' => array( 'twitter_handle' => '@acme' ) ) ),
			'handle too long'         => array( array( 'social' => array( 'twitter_handle' => 'a_really_long_handle' ) ) ),
			'javascript url'          => array( array( 'social' => array( 'profiles' => array( 'facebook' => 'javascript:alert(1)' ) ) ) ),
			'ftp url'                 => array( array( 'social' => array( 'profiles' => array( 'linkedin' => 'ftp://files.example.com/acme' ) ) ) ),
			'unknown network'         => array( array( 'social' => array( 'profiles' => array( 'myspace' => 'https://myspace.com/acme' ) ) ) ),
			'unknown section key'     => array( array( 'social' => array( 'pinterest' => 'acme' ) ) ),
			'unknown top-level key'   => array( array( 'extra' => array( 'x' => 1 ) ) ),
			'zero post id'            => array( array( 'sitemap' => array( 'exclude' => array( 0 ) ) ) ),
			'post id not a number'    => array( array( 'sitemap' => array( 'exclude' => array( 'about-us' ) ) ) ),
			'sitemap flag not bool'   => array( array( 'sitemap' => array( 'enabled' => 'maybe' ) ) ),
			'short google code'       => array( array( 'verification' => array( 'google' => 'abc' ) ) ),
			'bing code not hex'       => array( array( 'verification' => array( 'bing' => str_repeat( 'z', 32 ) ) ) ),
			'section not an object'   => array( array( 'titles' => 'Acme' ) ),
		);
	}

	/**
	 * @dataProvider invalid_values
	 */
	public function test_invalid_values_are_rejected_and_nothing_is_saved( array $value ): void {
		$before = $this->settings();
		// Valid change next to the invalid one: must not be saved either.
		$value['titles'] = is_array( $value['titles'] ?? null ) ? $value['titles'] + array( 'home_description' => 'changed' ) : ( $value['titles'] ?? array( 'home_description' => 'changed' ) );
		$res             = $this->save( $value );
		$this->assertSame( 400, $res['status'], $res['body'] );
		$this->assertSettings( $before, $this->settings() );
	}

	public function test_saving_updates_the_front_end(): void {
		$res = $this->save(
			array(
				'titles'   => array( 'separator' => '»' ),
				'indexing' => array(
					'noindex_post_types'      => array( 'event' ),
					'noindex_author_archives' => false,
					'noindex_date_archives'   => true,
				),
				'social'   => array(
					'og_enabled'     => false,
					'twitter_handle' => 'acme_news',
				),
				'sitemap'  => array( 'enabled' => false ),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );

		$home = $this->page( '/' )['body'];
		$this->assertSame( 'Welcome to Acme Widgets » Widgets for everyone', $this->title( $home ) );
		$this->assertNull( $this->meta( $home, 'property', 'og:title' ) );
		$this->assertSame( '@acme_news', $this->meta( $home, 'name', 'twitter:site' ) );

		$this->assertStringContainsString( 'noindex', $this->robots( $this->page( '/event/launch-party/' )['body'] ) );
		$this->assertStringNotContainsString( 'noindex', $this->robots( $this->page( '/about-us/' )['body'] ) );
		$this->assertStringContainsString( 'noindex', $this->robots( $this->page( '/2026/05/' )['body'] ) );
		$this->assertSame( 404, $this->page( '/wp-sitemap.xml' )['status'] );
	}
}

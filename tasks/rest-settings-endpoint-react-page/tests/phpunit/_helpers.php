<?php
/**
 * Helpers for the Acme SEO tests. State is always read through a fresh process (HTTP
 * request to Playground or a WP-CLI subprocess), never from this long-lived PHP process.
 */

abstract class AcmeSeoCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	const OLD_OPTIONS = array(
		'acme_seo_title_separator',
		'acme_seo_home_title',
		'acme_seo_home_description',
		'acme_seo_noindex_post_types',
		'acme_seo_noindex_archives',
		'acme_seo_og_enabled',
		'acme_seo_og_default_image',
		'acme_seo_twitter_handle',
		'acme_seo_social_profiles',
		'acme_seo_sitemap_enabled',
		'acme_seo_sitemap_exclude',
		'acme_seo_verification',
	);

	/** @var array<int, array{option_name:string, option_value:string, autoload:string}> */
	private array $snapshot = array();

	private ?array $admin_login = null;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->snapshot = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'acme\\_seo\\_%'", ARRAY_A );
	}

	protected function tearDown(): void {
		$this->restore_options( $this->snapshot );
		parent::tearDown();
	}

	/** Replace all acme_seo_* rows with the given raw rows (bypasses every filter). */
	protected function restore_options( array $rows ): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'acme\\_seo\\_%'" );
		foreach ( $rows as $row ) {
			$wpdb->insert( $wpdb->options, $row );
		}
		wp_cache_flush();
	}

	/** Write raw legacy option rows (arrays are serialized like update_option() would). */
	protected function seed_raw( array $options ): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'acme\\_seo\\_%'" );
		foreach ( $options as $name => $value ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => maybe_serialize( $value ),
					'autoload'     => 'on',
				)
			);
		}
		wp_cache_flush();
	}

	/** Names of acme_seo_* option rows in the database. */
	protected function stored_option_names(): array {
		global $wpdb;
		wp_cache_flush();
		return $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'acme\\_seo\\_%' ORDER BY option_name" );
	}

	protected function assertNoOldOptions(): void {
		$left = array_intersect( self::OLD_OPTIONS, $this->stored_option_names() );
		$this->assertSame( array(), array_values( $left ), 'old options must be deleted' );
	}

	/** Load WordPress in a fresh WP-CLI process (runs pending upgrades) and eval PHP. */
	protected function cli_eval( string $php ): string {
		$res = $this->wp_cli( 'eval ' . escapeshellarg( $php ) );
		$this->assertSame( 0, $res['exit'], "wp eval failed:\n" . $res['stdout'] . $res['stderr'] );
		return $res['stdout'];
	}

	/** JSON-decoded result of PHP code run in a fresh WP-CLI process. */
	protected function cli_json( string $php_expression ) {
		$out = $this->cli_eval( 'echo "@@" . wp_json_encode( ' . $php_expression . ' ) . "@@";' );
		$this->assertMatchesRegularExpression( '/@@(.*)@@/s', $out );
		preg_match( '/@@(.*)@@/s', $out, $m );
		return json_decode( $m[1], true );
	}

	protected function admin(): array {
		if ( null === $this->admin_login ) {
			$this->admin_login = $this->http_login( 1 );
		}
		return $this->admin_login;
	}

	protected function user_login( string $login ): array {
		return $this->http_login( get_user_by( 'login', $login )->ID );
	}

	/** GET /wp/v2/settings as admin (fresh request): the acme_seo_settings value. */
	protected function settings(): array {
		$res = $this->http(
			'GET',
			'/wp-json/wp/v2/settings',
			array(
				'login'      => $this->admin(),
				'rest_nonce' => true,
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertArrayHasKey( 'acme_seo_settings', $res['json'], 'acme_seo_settings missing from /wp/v2/settings' );
		$this->assertIsArray( $res['json']['acme_seo_settings'], 'acme_seo_settings must be an object: ' . $res['body'] );
		return $res['json']['acme_seo_settings'];
	}

	/** POST /wp/v2/settings as admin with { acme_seo_settings: $value }. */
	protected function save( $value, ?array $login = null ): array {
		return $this->http(
			'POST',
			'/wp-json/wp/v2/settings',
			array(
				'login'      => $login ?? $this->admin(),
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'acme_seo_settings' => $value ),
			)
		);
	}

	protected function attachment_id( string $title ): int {
		$ids = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'title'       => $title,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $ids, "attachment $title missing" );
		return (int) $ids[0];
	}

	protected function post_id( string $slug, string $type = 'post' ): int {
		$post = get_page_by_path( $slug, OBJECT, $type );
		$this->assertNotNull( $post, "$type $slug missing" );
		return $post->ID;
	}

	/** The settings the seeded 1.9.2 site must end up with. */
	protected function expected_pristine(): array {
		return array(
			'titles'       => array(
				'separator'        => '|',
				'home_title'       => 'Welcome to %%sitename%% %%sep%% %%tagline%%',
				'home_description' => 'The best widgets in town & more',
			),
			'indexing'     => array(
				'noindex_post_types'      => array( 'page' ),
				'noindex_author_archives' => true,
				'noindex_date_archives'   => false,
				'noindex_tag_archives'    => true,
			),
			'social'       => array(
				'og_enabled'     => true,
				'default_image'  => $this->attachment_id( 'Acme share image' ),
				'twitter_handle' => 'AcmeCorp',
				'profiles'       => array(
					'facebook'  => 'https://www.facebook.com/acmewidgets',
					'instagram' => '',
					'linkedin'  => 'https://www.linkedin.com/company/acme-widgets',
					'youtube'   => '',
				),
			),
			'sitemap'      => array(
				'enabled' => true,
				'exclude' => array( $this->post_id( 'internal-notes' ) ),
			),
			'verification' => array(
				'google' => 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd',
				'bing'   => '0123456789ABCDEF0123456789ABCDEF',
			),
		);
	}

	/** Recursively sort associative arrays by key (lists keep their order). */
	protected static function ksort_deep( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value = array_map( array( self::class, 'ksort_deep' ), $value );
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}
		return $value;
	}

	protected function assertSettings( array $expected, array $actual, string $message = '' ): void {
		$this->assertSame( self::ksort_deep( $expected ), self::ksort_deep( $actual ), $message );
	}

	/** Front-end HTML (logged out). */
	protected function page( string $path ): array {
		return $this->http( 'GET', $path );
	}

	protected function meta( string $html, string $attr, string $name ): ?string {
		if ( preg_match( '/<meta\s+' . $attr . '=["\']' . preg_quote( $name, '/' ) . '["\']\s+content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES );
		}
		return null;
	}

	protected function title( string $html ): string {
		preg_match( '#<title>(.*?)</title>#s', $html, $m );
		return html_entity_decode( trim( $m[1] ?? '' ), ENT_QUOTES );
	}

	protected function robots( string $html ): string {
		return (string) $this->meta( $html, 'name', 'robots' );
	}
}

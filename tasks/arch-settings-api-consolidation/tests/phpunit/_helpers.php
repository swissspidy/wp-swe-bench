<?php
/**
 * Helpers for the Acme Social tests.
 *
 * State is read from the database directly or through a fresh process (HTTP request
 * to Playground or a WP-CLI subprocess), never through this long-lived PHP process.
 */

abstract class AcmeSocialCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	const OPTION = 'acme_social_settings';

	const LEGACY_OPTIONS = array(
		'acme_social_share_buttons_enabled',
		'acme_social_networks',
		'acme_share_position',
		'acme_social_post_types',
		'acmesocial_button_style',
		'acme_social_twitter',
		'acme_social_facebook',
		'acme_social_instagram_url',
		'acme_social_linkedin',
		'acme_social_youtube_channel',
		'acme_og_enabled',
		'acme_og_default_image',
		'acme_social_fb_app_id',
		'acme_social_twitter_card',
	);

	const CAP_MU_PLUGIN = WP_CONTENT_DIR . '/mu-plugins/wpsb-acme-social-capability.php';

	/** @var array<int, array> */
	private array $snapshot = array();

	private array $logins = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->snapshot = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE " . self::plugin_rows_where(), ARRAY_A );
	}

	protected function tearDown(): void {
		@unlink( self::CAP_MU_PLUGIN );
		$this->restore_rows( $this->snapshot );
		parent::tearDown();
	}

	/** SQL condition matching every option row this plugin (any version) may write. */
	protected static function plugin_rows_where(): string {
		return "( option_name LIKE 'acme\\_social%' OR option_name LIKE 'acme\\_og\\_%' OR option_name LIKE 'acme\\_share\\_%' OR option_name LIKE 'acmesocial\\_%' OR option_name LIKE '\\_transient\\_acme\\_social%' OR option_name LIKE '\\_transient\\_timeout\\_acme\\_social%' OR option_name IN ('_transient_settings_errors', '_transient_timeout_settings_errors') )";
	}

	protected function restore_rows( array $rows ): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE " . self::plugin_rows_where() );
		foreach ( $rows as $row ) {
			$wpdb->insert( $wpdb->options, $row );
		}
		wp_cache_flush();
	}

	/** Raw option value from the database (null if the row does not exist). */
	protected function raw_option( string $name ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		return null === $value ? null : maybe_unserialize( $value );
	}

	protected function set_raw_option( string $name, $value ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $name,
				'option_value' => maybe_serialize( $value ),
				'autoload'     => 'on',
			)
		);
		wp_cache_flush();
	}

	protected function delete_raw_option( string $name ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		wp_cache_flush();
	}

	/** Names of the 1.x option rows that exist in the database. */
	protected function legacy_rows(): array {
		global $wpdb;
		$in = "'" . implode( "','", self::LEGACY_OPTIONS ) . "'";
		return $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ($in) ORDER BY option_name" );
	}

	/** Puts the database back into the 1.6.2 state with the given legacy rows. */
	protected function seed_legacy_site( array $rows, string $version = '1.6.2' ): void {
		global $wpdb;
		$in = "'" . implode( "','", self::LEGACY_OPTIONS ) . "'";
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ($in) OR option_name = 'acme_social_settings'" );
		foreach ( $rows as $name => $value ) {
			$this->set_raw_option( $name, $value );
		}
		$this->set_raw_option( 'acme_social_version', $version );
	}

	/** The legacy rows of the production site (as seeded). */
	protected function pristine_legacy_rows(): array {
		return array(
			'acme_social_share_buttons_enabled' => 'yes',
			'acme_social_networks'              => 'Facebook, twitter,LinkedIn ,mastodon,myspace',
			'acme_share_position'               => 'bottom',
			'acme_social_post_types'            => array( 'post', 'page', 'event', 'product' ),
			'acmesocial_button_style'           => 'icons+text',
			'acme_social_twitter'               => '@AcmeHQ',
			'acme_social_facebook'              => 'https://www.facebook.com/acmehq',
			'acme_social_instagram_url'         => 'instagram.com/acmehq',
			'acme_social_linkedin'              => '',
			'acme_og_enabled'                   => '1',
			'acme_og_default_image'             => wp_get_attachment_url( $this->image_id() ),
			'acme_social_fb_app_id'             => ' 1234567890 ',
			'acme_social_twitter_card'          => 'large',
		);
	}

	/** The settings the seeded site must end up with. */
	protected function expected_pristine(): array {
		return array(
			'share_enabled'    => true,
			'networks'         => array( 'facebook', 'twitter', 'linkedin', 'mastodon' ),
			'position'         => 'after',
			'post_types'       => array( 'post', 'page', 'event' ),
			'button_style'     => 'icons_text',
			'twitter'          => 'AcmeHQ',
			'facebook'         => 'https://www.facebook.com/acmehq',
			'instagram'        => 'http://instagram.com/acmehq',
			'linkedin'         => '',
			'youtube'          => '',
			'og_enabled'       => true,
			'og_default_image' => $this->image_id(),
			'fb_app_id'        => '1234567890',
			'twitter_card'     => 'summary_large_image',
		);
	}

	protected function defaults(): array {
		return array(
			'share_enabled'    => true,
			'networks'         => array( 'facebook', 'twitter', 'linkedin' ),
			'position'         => 'after',
			'post_types'       => array( 'post' ),
			'button_style'     => 'icons',
			'twitter'          => '',
			'facebook'         => '',
			'instagram'        => '',
			'linkedin'         => '',
			'youtube'          => '',
			'og_enabled'       => true,
			'og_default_image' => 0,
			'fb_app_id'        => '',
			'twitter_card'     => 'summary_large_image',
		);
	}

	/** Stored acme_social_settings (raw, from the database). */
	protected function stored(): array {
		$value = $this->raw_option( self::OPTION );
		$this->assertIsArray( $value, 'acme_social_settings must be stored as an array' );
		return $value;
	}

	protected function assertSettings( array $expected, array $actual, string $message = '' ): void {
		ksort( $expected );
		ksort( $actual );
		$this->assertSame( $expected, $actual, $message );
	}

	protected function image_id(): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title = 'Acme share image'" );
		$this->assertGreaterThan( 0, $id );
		return $id;
	}

	protected function post_id( string $slug ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish'", $slug ) );
		$this->assertGreaterThan( 0, $id, "post $slug missing" );
		return $id;
	}

	protected function user_id( string $login ): int {
		$user = get_user_by( 'login', $login );
		$this->assertNotFalse( $user, "user $login missing" );
		return $user->ID;
	}

	protected function login( string $user ): array {
		if ( ! isset( $this->logins[ $user ] ) ) {
			$this->logins[ $user ] = $this->http_login( 'admin' === $user ? 1 : $this->user_id( $user ) );
		}
		return $this->logins[ $user ];
	}

	/** Runs PHP in a fresh WP-CLI process (like another plugin would) and returns the JSON-decoded result. */
	protected function cli_json( string $php_expression ) {
		$res = $this->wp_cli( 'eval ' . escapeshellarg( 'echo "@@" . wp_json_encode( ' . $php_expression . ' ) . "@@";' ) );
		$this->assertSame( 0, $res['exit'], "wp eval failed:\n" . $res['stdout'] . $res['stderr'] );
		$this->assertMatchesRegularExpression( '/@@(.*)@@/s', $res['stdout'] );
		preg_match( '/@@(.*)@@/s', $res['stdout'], $m );
		return json_decode( $m[1], true );
	}

	/** Loads WordPress once in a fresh process (runs whatever runs on a request). */
	protected function fresh_request(): void {
		$res = $this->wp_cli( 'eval "echo 1;"' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		wp_cache_flush();
	}

	protected function write_capability_mu_plugin( string $capability ): void {
		file_put_contents(
			self::CAP_MU_PLUGIN,
			"<?php\nadd_filter( 'acme_social_admin_capability', static function () { return " . var_export( $capability, true ) . "; } );\n"
		);
	}

	// ---------------------------------------------------------------------
	// Forms (behave like a browser: scrape the form, change fields, submit)
	// ---------------------------------------------------------------------

	/** Admin screen URL. */
	protected static function screen( string $slug ): string {
		return '/wp-admin/admin.php?page=' . $slug;
	}

	/**
	 * GET a screen and return its settings form.
	 *
	 * @return array{action: string, fields: array<int, array{name: string, type: string, value: string, checked: bool}>, html: string}
	 */
	protected function get_form( string $path, array $login ): array {
		$res = $this->http( 'GET', $path, array( 'login' => $login ) );
		$this->assertSame( 200, $res['status'], "GET $path: " . substr( strip_tags( $res['body'] ), 0, 500 ) );
		$form = $this->parse_form( $res['body'], $path );
		$this->assertNotNull( $form, "No form with acme_social_settings[...] fields on $path" );
		return $form;
	}

	protected function parse_form( string $html, string $path ): ?array {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );
		foreach ( $xpath->query( '//form' ) as $form ) {
			$fields = array();
			foreach ( $xpath->query( './/input|.//select|.//textarea|.//button', $form ) as $el ) {
				$name = $el->getAttribute( 'name' );
				if ( '' === $name || $el->hasAttribute( 'disabled' ) ) {
					continue;
				}
				$tag  = strtolower( $el->nodeName );
				$type = 'select' === $tag ? 'select' : ( 'textarea' === $tag ? 'textarea' : strtolower( $el->getAttribute( 'type' ) ?: ( 'button' === $tag ? 'submit' : 'text' ) ) );
				if ( in_array( $type, array( 'button', 'reset', 'image', 'file' ), true ) ) {
					continue;
				}
				if ( 'select' === $type ) {
					$value = null;
					$first = null;
					foreach ( $xpath->query( './/option', $el ) as $opt ) {
						$v = $opt->hasAttribute( 'value' ) ? $opt->getAttribute( 'value' ) : $opt->textContent;
						if ( null === $first ) {
							$first = $v;
						}
						if ( $opt->hasAttribute( 'selected' ) ) {
							$value = $v;
						}
					}
					$value = $value ?? $first ?? '';
				} elseif ( 'textarea' === $type ) {
					$value = $el->textContent;
				} else {
					$value = $el->hasAttribute( 'value' ) ? $el->getAttribute( 'value' ) : ( in_array( $type, array( 'checkbox', 'radio' ), true ) ? 'on' : '' );
				}
				$fields[] = array(
					'name'    => $name,
					'type'    => $type,
					'value'   => $value,
					'checked' => $el->hasAttribute( 'checked' ),
				);
			}
			$names = array_column( $fields, 'name' );
			if ( ! array_filter( $names, static fn( $n ) => str_starts_with( $n, 'acme_social_settings[' ) ) ) {
				continue;
			}
			$action = $form->getAttribute( 'action' );
			if ( '' === $action ) {
				$action = $path;
			} elseif ( ! preg_match( '#^(https?:)?/#', $action ) ) {
				$action = '/wp-admin/' . $action;
			}
			return array(
				'action' => html_entity_decode( $action ),
				'fields' => $fields,
				'html'   => $html,
			);
		}
		return null;
	}

	/** Field names present in a form. */
	protected function field_names( array $form ): array {
		return array_values( array_unique( array_column( $form['fields'], 'name' ) ) );
	}

	/** Values of the checkboxes named $name that exist in the form. */
	protected function checkbox_values( array $form, string $name ): array {
		$out = array();
		foreach ( $form['fields'] as $f ) {
			if ( $f['name'] === $name && 'checkbox' === $f['type'] ) {
				$out[] = $f['value'];
			}
		}
		return $out;
	}

	/**
	 * Builds the request body a browser would send after the user changed $set.
	 *
	 * $set: name => string (text/select/radio value), bool (single checkbox), list (checked values of a checkbox group).
	 * $remove: field names to drop entirely. $extra: raw [name, value] pairs to append.
	 */
	protected function form_body( array $form, array $set = array(), array $remove = array(), array $extra = array() ): string {
		$pairs    = array();
		$submits  = 0;
		foreach ( $form['fields'] as $f ) {
			if ( in_array( $f['name'], $remove, true ) ) {
				continue;
			}
			$has = array_key_exists( $f['name'], $set );
			$ov  = $has ? $set[ $f['name'] ] : null;
			if ( 'checkbox' === $f['type'] || 'radio' === $f['type'] ) {
				$checked = $f['checked'];
				if ( $has ) {
					if ( is_array( $ov ) ) {
						$checked = in_array( $f['value'], array_map( 'strval', $ov ), true );
					} elseif ( is_bool( $ov ) ) {
						$checked = $ov;
					} else {
						$checked = ( (string) $ov === $f['value'] );
					}
				}
				if ( $checked ) {
					$pairs[] = array( $f['name'], $f['value'] );
				}
				continue;
			}
			if ( 'submit' === $f['type'] ) {
				if ( 0 === $submits++ ) {
					$pairs[] = array( $f['name'], $f['value'] );
				}
				continue;
			}
			$pairs[] = array( $f['name'], $has ? (string) $ov : $f['value'] );
		}
		foreach ( $extra as $pair ) {
			$pairs[] = $pair;
		}
		return implode( '&', array_map( static fn( $p ) => rawurlencode( $p[0] ) . '=' . rawurlencode( $p[1] ), $pairs ) );
	}

	/** Submits a form body; returns the response. */
	protected function post_form( array $form, string $body, array $login ): array {
		return $this->http(
			'POST',
			$form['action'],
			array(
				'login'   => $login,
				'body'    => $body,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
	}

	/** Submits and follows the redirect back to the screen; returns the screen HTML. */
	protected function save_screen( string $slug, array $set, string $user = 'admin', array $extra = array() ): string {
		$login = $this->login( $user );
		$form  = $this->get_form( self::screen( $slug ), $login );
		$res   = $this->post_form( $form, $this->form_body( $form, $set, array(), $extra ), $login );
		$this->assertContains( $res['status'], array( 301, 302, 303 ), "Saving $slug should redirect back to the screen, got {$res['status']}: " . substr( strip_tags( $res['body'] ), 0, 500 ) );
		$location = $res['headers']['location'] ?? '';
		$this->assertNotSame( '', $location );
		$this->assertStringContainsString( 'page=' . $slug, $location, 'Saving must return to the same screen' );
		$page = $this->http( 'GET', $location, array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		wp_cache_flush();
		return $page['body'];
	}

	/** Text of admin notices of a kind ("error" or "success") in a page. */
	protected function notices( string $html, string $kind ): array {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );
		$classes = 'error' === $kind ? array( 'notice-error', 'error' ) : array( 'notice-success', 'updated' );
		$cond    = implode( ' or ', array_map( static fn( $c ) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')", $classes ) );
		$out     = array();
		foreach ( $xpath->query( "//div[$cond]|//p[$cond]" ) as $node ) {
			$out[] = trim( preg_replace( '/\s+/', ' ', $node->textContent ) );
		}
		return $out;
	}

	protected function assertNotice( string $html, string $kind, string $needle ): void {
		$notices = $this->notices( $html, $kind );
		foreach ( $notices as $text ) {
			if ( false !== stripos( $text, $needle ) ) {
				$this->addToAssertionCount( 1 );
				return;
			}
		}
		$this->fail( "Expected a $kind notice containing \"$needle\"; notices: " . wp_json_encode( $notices ) );
	}

	// ---------------------------------------------------------------------
	// Front end
	// ---------------------------------------------------------------------

	protected function page( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return $res['body'];
	}

	protected function meta( string $html, string $attr, string $name ): ?string {
		if ( preg_match( '/<meta\s+' . $attr . '=["\']' . preg_quote( $name, '/' ) . '["\']\s+content=["\']([^"\']*)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES );
		}
		return null;
	}

	/** Network slugs of the share buttons in the order they appear (first button block). */
	protected function share_networks( string $html ): array {
		if ( ! preg_match( '#<div class="acme-social-share[^"]*">(.*?)</div>#s', $html, $m ) ) {
			return array();
		}
		preg_match_all( '/acme-social-share__link--([a-z0-9_-]+)/', $m[1], $n );
		return $n[1];
	}

	protected function share_blocks( string $html ): int {
		return preg_match_all( '#<div class="acme-social-share[ "]#', $html );
	}
}

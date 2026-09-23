<?php
/**
 * Helpers for the Acme Newsroom tests.
 *
 * Roles and capabilities are always checked in a fresh process (WP-CLI subprocess or
 * HTTP request), never through this long-lived PHP process.
 */

abstract class NewsroomCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	const STORY_CAPS = array(
		'edit_stories',
		'edit_others_stories',
		'edit_published_stories',
		'edit_private_stories',
		'publish_stories',
		'delete_stories',
		'delete_others_stories',
		'delete_published_stories',
		'delete_private_stories',
		'read_private_stories',
		'approve_stories',
	);

	const USER_META_KEYS = array( 'wp_capabilities', 'wp_user_level', 'acme_freelancer' );

	private array $snapshot_options = array();
	private array $snapshot_usermeta = array();
	protected array $created_posts = array();
	protected array $created_users = array();
	private array $logins = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->snapshot_options  = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE " . self::options_where(), ARRAY_A );
		$this->snapshot_usermeta = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE " . self::usermeta_where(), ARRAY_A );
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->created_posts as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->created_users as $id ) {
			wp_delete_user( $id );
		}
		$this->restore_rows( $this->snapshot_options, $this->snapshot_usermeta );
		parent::tearDown();
	}

	protected static function options_where(): string {
		return "( option_name = 'wp_user_roles' OR option_name LIKE 'acme\\_newsroom%' )";
	}

	protected static function usermeta_where(): string {
		return "meta_key IN ('" . implode( "','", self::USER_META_KEYS ) . "')";
	}

	protected function restore_rows( array $options, array $usermeta ): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE " . self::options_where() );
		foreach ( $options as $row ) {
			$wpdb->insert( $wpdb->options, $row );
		}
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE " . self::usermeta_where() );
		foreach ( $usermeta as $row ) {
			$wpdb->insert( $wpdb->usermeta, $row );
		}
		wp_cache_flush();
	}

	/** Puts roles, user roles/flags and the plugin's options back to the 2.3.0 state of the site. */
	protected function restore_pristine_roles(): void {
		$pdo = new PDO( 'sqlite:/opt/wpsb/pristine/.ht.sqlite' );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$options  = $pdo->query( "SELECT option_name, option_value, autoload FROM wp_options WHERE option_name = 'wp_user_roles' OR option_name LIKE 'acme\\_newsroom%' ESCAPE '\\'" )->fetchAll( PDO::FETCH_ASSOC );
		$usermeta = $pdo->query( 'SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE ' . self::usermeta_where() )->fetchAll( PDO::FETCH_ASSOC );
		$this->assertNotEmpty( $options );
		$names = array_column( $options, 'option_name' );
		$this->assertContains( 'wp_user_roles', $names );
		// Keep the capabilities of users created by the tests.
		global $wpdb;
		foreach ( $this->created_users as $id ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND " . self::usermeta_where(), $id ), ARRAY_A ) as $row ) {
				$usermeta[] = $row;
			}
		}
		$this->restore_rows( $options, $usermeta );
	}

	/** Loads WordPress once in a fresh process (runs whatever runs on a request). */
	protected function fresh_request(): void {
		$res = $this->wp_cli( 'eval "echo 1;"' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		wp_cache_flush();
	}

	/** Runs PHP in a fresh WP-CLI process and returns the JSON-decoded result. */
	protected function cli_json( string $php_expression ) {
		$res = $this->wp_cli( 'eval ' . escapeshellarg( 'echo "@@" . wp_json_encode( ' . $php_expression . ' ) . "@@";' ) );
		$this->assertSame( 0, $res['exit'], "wp eval failed:\n" . $res['stdout'] . $res['stderr'] );
		$this->assertMatchesRegularExpression( '/@@(.*)@@/s', $res['stdout'], $res['stdout'] . $res['stderr'] );
		preg_match( '/@@(.*)@@/s', $res['stdout'], $m );
		return json_decode( $m[1], true );
	}

	/** Raw option value from the database. */
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

	/** Roles from the database: name => [ 'name' => label, 'capabilities' => [...] ]. */
	protected function roles(): array {
		return (array) $this->raw_option( 'wp_user_roles' );
	}

	/** Granted capabilities of a role (sorted), null if the role doesn't exist. */
	protected function role_caps( string $role ): ?array {
		$roles = $this->roles();
		if ( ! isset( $roles[ $role ] ) ) {
			return null;
		}
		$caps = array_keys( array_filter( (array) $roles[ $role ]['capabilities'] ) );
		sort( $caps );
		return $caps;
	}

	/** Roles of a user from the database (sorted). */
	protected function user_roles( string $login ): array {
		global $wpdb;
		$caps  = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $this->user_id( $login ), $wpdb->prefix . 'capabilities' ) ) );
		$roles = array_keys( array_filter( (array) $caps ) );
		$roles = array_values( array_intersect( $roles, array_keys( $this->roles() ) ) );
		sort( $roles );
		return $roles;
	}

	protected function user_id( string $login ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $login ) );
		$this->assertGreaterThan( 0, $id, "user $login missing" );
		return $id;
	}

	protected function story_id( string $title ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'story' AND post_title = %s", $title ) );
		$this->assertGreaterThan( 0, $id, "story $title missing" );
		return $id;
	}

	protected function login( string $user ): array {
		if ( ! isset( $this->logins[ $user ] ) ) {
			$this->logins[ $user ] = $this->http_login( $this->user_id( $user ) );
		}
		return $this->logins[ $user ];
	}

	/** REST request over HTTP as a user (cookie + nonce). */
	protected function api( string $user, string $method, string $route, ?array $body = null ): array {
		$opts = array(
			'login'      => $this->login( $user ),
			'rest_nonce' => true,
		);
		if ( null !== $body ) {
			$opts['json'] = true;
			$opts['body'] = $body;
		}
		$res = $this->http( $method, '/wp-json' . $route, $opts );
		if ( 'POST' === $method && preg_match( '#^/wp/v2/(stories|posts)$#', $route ) && 201 === $res['status'] && isset( $res['json']['id'] ) ) {
			$this->created_posts[] = (int) $res['json']['id'];
		}
		return $res;
	}

	/** Creates a story over HTTP as $user; returns its ID. */
	protected function new_story( string $user, string $status = 'draft', array $extra = array() ): int {
		$res = $this->api( $user, 'POST', '/wp/v2/stories', array_merge( array( 'title' => 'Test story ' . wp_rand(), 'content' => 'Body', 'status' => $status ), $extra ) );
		$this->assertSame( 201, $res['status'], "$user creating a $status story: " . $res['body'] );
		return (int) $res['json']['id'];
	}

	/** Evaluates user_can() checks in a fresh process: list of [login, cap, object id|null] => bool list. */
	protected function user_can_many( array $checks ): array {
		$php = 'array_map( static function ( $c ) { $u = get_user_by( "login", $c[0] ); return null === $c[2] ? user_can( $u, $c[1] ) : user_can( $u, $c[1], $c[2] ); }, ' . var_export( $checks, true ) . ' )';
		return $this->cli_json( $php );
	}

	protected function assertUserCan( array $expectations ): void {
		$checks = array();
		foreach ( $expectations as $e ) {
			$checks[] = array( $e[0], $e[1], $e[2] ?? null );
		}
		$results  = $this->user_can_many( $checks );
		$failures = array();
		foreach ( $expectations as $i => $e ) {
			$expected = $e[3];
			if ( $results[ $i ] !== $expected ) {
				$failures[] = sprintf( 'user_can( %s, %s%s ) should be %s', $e[0], $e[1], null === ( $e[2] ?? null ) ? '' : ', ' . $e[2], var_export( $expected, true ) );
			}
		}
		$this->assertSame( array(), $failures );
	}
}

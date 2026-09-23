<?php
/**
 * Base test case for wp-swe-bench hidden tests.
 *
 * - Each test runs inside a DB transaction that is rolled back afterwards
 *   (set $use_transactions = false for tests that talk to the HTTP server or
 *   spawn WP-CLI subprocesses: those only see COMMITTED data, and an open
 *   transaction holds SQLite's write lock).
 * - Records _doing_it_wrong() / deprecation notices (assertNoDoingItWrong()).
 * - Helpers for users, posts, REST requests, HTTP requests against the
 *   Playground server (with real auth cookies + nonces), captured mail,
 *   WP-CLI subprocesses and query counting.
 */

namespace WPSB;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	/** Wrap each test in START TRANSACTION / ROLLBACK. */
	protected bool $use_transactions = true;

	/** @var array<int, string> */
	protected array $wp_notices = array();

	private bool $in_transaction = false;
	private static int $uniq     = 0;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->suppress_errors( false );
		$wpdb->show_errors( false );
		if ( $this->use_transactions ) {
			$wpdb->query( 'START TRANSACTION' );
			$this->in_transaction = true;
		}
		wp_cache_flush();
		$this->reset_request_globals();
		wp_set_current_user( 0 );
		$this->wp_notices = array();
		add_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10, 3 );
		add_action( 'deprecated_function_run', array( $this, 'record_deprecated' ), 10, 3 );
		add_action( 'deprecated_argument_run', array( $this, 'record_deprecated' ), 10, 3 );
		add_action( 'deprecated_hook_run', array( $this, 'record_deprecated' ), 10, 4 );
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		add_filter( 'deprecated_function_trigger_error', '__return_false' );
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( $this->in_transaction ) {
			$wpdb->query( 'ROLLBACK' );
			$this->in_transaction = false;
		}
		remove_action( 'doing_it_wrong_run', array( $this, 'record_doing_it_wrong' ), 10 );
		remove_action( 'deprecated_function_run', array( $this, 'record_deprecated' ), 10 );
		remove_action( 'deprecated_argument_run', array( $this, 'record_deprecated' ), 10 );
		remove_action( 'deprecated_hook_run', array( $this, 'record_deprecated' ), 10 );
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		remove_filter( 'deprecated_function_trigger_error', '__return_false' );
		wp_cache_flush();
		wp_set_current_user( 0 );
		$this->reset_request_globals();
		unset( $GLOBALS['post'] );
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
		parent::tearDown();
	}

	private function reset_request_globals(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$_FILES   = array();
	}

	/** @internal */
	public function record_doing_it_wrong( $function_name, $message = '', $version = '' ): void {
		$this->wp_notices[] = "doing_it_wrong: $function_name: $message";
	}

	/** @internal */
	public function record_deprecated( $name, ...$rest ): void {
		$this->wp_notices[] = "deprecated: $name";
	}

	protected function assertNoDoingItWrong( string $message = '' ): void {
		$this->assertSame( array(), $this->wp_notices, $message ?: 'Unexpected _doing_it_wrong()/deprecation notices' );
	}

	// -------------------------------------------------------------------------
	// Fixtures
	// -------------------------------------------------------------------------

	protected static function uniq( string $prefix = 'wpsb' ): string {
		return $prefix . '_' . getmypid() . '_' . ( ++self::$uniq ) . '_' . wp_rand( 1000, 9999 );
	}

	/** Create a user with the given role; returns the user ID. */
	protected function create_user( string $role = 'administrator', array $args = array() ): int {
		$login = self::uniq( 'user' );
		$id    = wp_insert_user(
			array_merge(
				array(
					'user_login' => $login,
					'user_email' => $login . '@example.org',
					'user_pass'  => 'password',
					'role'       => $role,
				),
				$args
			)
		);
		$this->assertIsInt( $id, 'create_user failed: ' . ( is_wp_error( $id ) ? $id->get_error_message() : '' ) );
		return $id;
	}

	/** Create a post (defaults: published 'post' by admin); returns the post ID. */
	protected function create_post( array $args = array() ): int {
		$id = wp_insert_post(
			array_merge(
				array(
					'post_title'   => self::uniq( 'Post' ),
					'post_status'  => 'publish',
					'post_type'    => 'post',
					'post_author'  => 1,
					'post_content' => '',
				),
				$args
			),
			true
		);
		$this->assertIsInt( $id, 'create_post failed: ' . ( is_wp_error( $id ) ? $id->get_error_message() : '' ) );
		return $id;
	}

	/** Set the current user by ID or by role (creates a fresh user for a role). */
	protected function login_as( $user_or_role ): int {
		$id = is_int( $user_or_role ) ? $user_or_role : $this->create_user( $user_or_role );
		wp_set_current_user( $id );
		return $id;
	}

	// -------------------------------------------------------------------------
	// REST (in-process)
	// -------------------------------------------------------------------------

	/**
	 * Dispatch an in-process REST request.
	 *
	 * @param array|string|null $body Array => JSON body; string => raw body.
	 */
	protected function rest( string $method, string $route, array $query = array(), $body = null, array $headers = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( strtoupper( $method ), $route );
		foreach ( $headers as $k => $v ) {
			$request->set_header( $k, $v );
		}
		if ( $query ) {
			$request->set_query_params( $query );
		}
		if ( is_array( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		} elseif ( is_string( $body ) ) {
			$request->set_body( $body );
		}
		return rest_do_request( $request );
	}

	/**
	 * Like rest(), but also runs the `rest_post_dispatch` filter (as the real server does), so
	 * headers added there and `_fields` filtering are applied. Returns the filtered response.
	 */
	protected function rest_dispatch( string $method, string $route, array $query = array(), $body = null, array $headers = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( strtoupper( $method ), $route );
		foreach ( $headers as $k => $v ) {
			$request->set_header( $k, $v );
		}
		if ( $query ) {
			$request->set_query_params( $query );
		}
		if ( is_array( $body ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		} elseif ( is_string( $body ) ) {
			$request->set_body( $body );
		}
		$server   = rest_get_server();
		$response = rest_ensure_response( $server->dispatch( $request ) );
		return rest_ensure_response( apply_filters( 'rest_post_dispatch', $response, $server, $request ) );
	}

	/** Response data after REST embedding/linking like the real server (_embed etc.). */
	protected function rest_data( \WP_REST_Response $response, bool $embed = false ) {
		return rest_get_server()->response_to_data( $response, $embed );
	}

	// -------------------------------------------------------------------------
	// HTTP against the Playground server (committed data only!)
	// -------------------------------------------------------------------------

	/** Log a user in for HTTP requests: returns ['cookie' => header, 'rest_nonce' => nonce]. */
	protected function http_login( int $user_id ): array {
		$this->assert_not_in_transaction( 'http_login' );
		$expiration = time() + DAY_IN_SECONDS;
		$manager    = \WP_Session_Tokens::get_instance( $user_id );
		$token      = $manager->create( $expiration );
		$logged_in  = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
		$auth       = wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token );
		$cookie     = LOGGED_IN_COOKIE . '=' . rawurlencode( $logged_in ) . '; ' . AUTH_COOKIE . '=' . rawurlencode( $auth ) . '; wordpress_test_cookie=WP%20Cookie%20check';
		return array(
			'cookie'     => $cookie,
			'user_id'    => $user_id,
			'rest_nonce' => $this->nonce_for( $user_id, 'wp_rest', $logged_in ),
			'logged_in'  => $logged_in,
		);
	}

	/** Create a nonce exactly as the given (HTTP-logged-in) user would get it. */
	protected function nonce_for( int $user_id, $action, ?string $logged_in_cookie = null ): string {
		$prev_user   = get_current_user_id();
		$prev_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		if ( null !== $logged_in_cookie ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
		}
		wp_set_current_user( $user_id );
		$nonce = wp_create_nonce( $action );
		wp_set_current_user( $prev_user );
		if ( null === $prev_cookie ) {
			unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		} else {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $prev_cookie;
		}
		return $nonce;
	}

	/**
	 * HTTP request to the Playground server.
	 *
	 * @param array $opts {
	 *   @type array       $login    Result of http_login() to send auth cookies (+ X-WP-Nonce if 'rest_nonce' => true).
	 *   @type bool        $rest_nonce Send X-WP-Nonce from $login.
	 *   @type array       $headers  Extra headers ['Name' => 'value'].
	 *   @type array|string $body    Array => form-encoded (or JSON if $json), string => raw.
	 *   @type bool        $json     Send $body as JSON.
	 *   @type bool        $follow   Follow redirects (default false).
	 * }
	 * @return array{status:int, headers:array<string,string>, body:string, json:mixed}
	 */
	protected function http( string $method, string $path, array $opts = array() ): array {
		$this->assert_not_in_transaction( 'http' );
		$url = preg_match( '#^https?://#', $path ) ? $path : rtrim( WP_HOME, '/' ) . '/' . ltrim( $path, '/' );
		$ch  = curl_init( $url );
		$hdr = array();
		foreach ( $opts['headers'] ?? array() as $k => $v ) {
			$hdr[] = "$k: $v";
		}
		if ( ! empty( $opts['login'] ) ) {
			$hdr[] = 'Cookie: ' . $opts['login']['cookie'] . ( isset( $opts['cookie'] ) ? '; ' . $opts['cookie'] : '' );
			if ( ! empty( $opts['rest_nonce'] ) ) {
				$hdr[] = 'X-WP-Nonce: ' . $opts['login']['rest_nonce'];
			}
		} elseif ( isset( $opts['cookie'] ) ) {
			$hdr[] = 'Cookie: ' . $opts['cookie'];
		}
		if ( array_key_exists( 'body', $opts ) ) {
			if ( ! empty( $opts['json'] ) ) {
				$hdr[] = 'Content-Type: application/json';
				curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $opts['body'] ) );
			} else {
				curl_setopt( $ch, CURLOPT_POSTFIELDS, is_array( $opts['body'] ) ? http_build_query( $opts['body'] ) : $opts['body'] );
			}
		}
		$resp_headers = array();
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => strtoupper( $method ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => $hdr,
				CURLOPT_FOLLOWLOCATION => ! empty( $opts['follow'] ),
				CURLOPT_TIMEOUT        => 120,
				CURLOPT_PROXY          => '',
				CURLOPT_HEADERFUNCTION => static function ( $ch, $line ) use ( &$resp_headers ) {
					$parts = explode( ':', $line, 2 );
					if ( 2 === count( $parts ) ) {
						$name = strtolower( trim( $parts[0] ) );
						$resp_headers[ $name ] = isset( $resp_headers[ $name ] ) ? $resp_headers[ $name ] . ', ' . trim( $parts[1] ) : trim( $parts[1] );
					}
					return strlen( $line );
				},
			)
		);
		$body = curl_exec( $ch );
		$this->assertNotFalse( $body, 'HTTP request failed: ' . curl_error( $ch ) );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );
		return array(
			'status'  => $status,
			'headers' => $resp_headers,
			'body'    => (string) $body,
			'json'    => json_decode( (string) $body, true ),
		);
	}

	private function assert_not_in_transaction( string $what ): void {
		if ( $this->in_transaction ) {
			$this->fail( "$what() requires committed data: set protected bool \$use_transactions = false in " . static::class );
		}
	}

	// -------------------------------------------------------------------------
	// Misc
	// -------------------------------------------------------------------------

	/** Run native WP-CLI in a subprocess (committed data only). */
	protected function wp_cli( string $args, array $env = array() ): array {
		$this->assert_not_in_transaction( 'wp_cli' );
		$env  = $env ? array_merge( getenv(), $env ) : null;
		$proc = proc_open( 'wp ' . $args, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/wordpress', $env );
		$out  = stream_get_contents( $pipes[1] );
		$err  = stream_get_contents( $pipes[2] );
		$code = proc_close( $proc );
		return array( 'exit' => $code, 'stdout' => $out, 'stderr' => $err );
	}

	/** Mail captured by the environment mu-plugin (list of arrays). */
	protected function mails(): array {
		$file = WP_CONTENT_DIR . '/wpsb-mail.log';
		if ( ! is_file( $file ) ) {
			return array();
		}
		return array_values( array_filter( array_map( static fn( $l ) => json_decode( $l, true ), file( $file ) ) ) );
	}

	protected function clear_mails(): void {
		@unlink( WP_CONTENT_DIR . '/wpsb-mail.log' );
	}

	/** Number of DB queries executed by $fn (and its return value). */
	protected function count_queries( callable $fn ): array {
		global $wpdb;
		$before = $wpdb->num_queries;
		$log_before = is_array( $wpdb->queries ) ? count( $wpdb->queries ) : 0;
		$result = $fn();
		$queries = is_array( $wpdb->queries ) ? array_slice( $wpdb->queries, $log_before ) : array();
		return array(
			'count'   => $wpdb->num_queries - $before,
			'queries' => array_map( static fn( $q ) => is_array( $q ) ? $q[0] : $q, $queries ),
			'result'  => $result,
		);
	}

}

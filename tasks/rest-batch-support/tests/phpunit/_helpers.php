<?php
/**
 * Helpers for the Acme Tasks tests. Everything goes through the real HTTP server
 * (Playground), exactly like the app / admin screen / Zapier would talk to it.
 */

abstract class AcmeTasksCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array<int, array> http_login() results per user ID. */
	private array $logins = array();

	protected function setUp(): void {
		parent::setUp();
		$this->clear_mails();
	}

	/** Create a user with the given role (committed). */
	protected function user( string $role = 'author', string $name = '' ): int {
		return $this->create_user( $role, $name ? array( 'display_name' => $name ) : array() );
	}

	/** Seeded user by login. */
	protected function seeded_user( string $login ): int {
		$user = get_user_by( 'login', $login );
		$this->assertNotFalse( $user, "seeded user $login missing" );
		return $user->ID;
	}

	/** Seeded list ID by slug. */
	protected function seeded_list( string $slug ): int {
		$posts = get_posts(
			array(
				'post_type'   => 'acme_task_list',
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $posts, "seeded list $slug missing" );
		return (int) $posts[0];
	}

	protected function login_for( ?int $user_id ): ?array {
		if ( null === $user_id ) {
			return null;
		}
		if ( ! isset( $this->logins[ $user_id ] ) ) {
			$this->logins[ $user_id ] = $this->http_login( $user_id );
		}
		return $this->logins[ $user_id ];
	}

	/**
	 * Request to /wp-json/acme-tasks/v1{$path} as the given user (null = logged out).
	 *
	 * @param array|null $body JSON body (or form body when $form).
	 */
	protected function api( ?int $user, string $method, string $path, $body = null, bool $form = false, array $headers = array() ): array {
		$opts = array( 'headers' => $headers );
		if ( null !== $user ) {
			$opts['login']      = $this->login_for( $user );
			$opts['rest_nonce'] = true;
		}
		if ( null !== $body ) {
			$opts['body'] = $body;
			$opts['json'] = ! $form;
		}
		return $this->http( $method, '/wp-json/acme-tasks/v1' . $path, $opts );
	}

	/**
	 * Batch request. $requests: list of [method, path (relative to /acme-tasks/v1), body|null].
	 */
	protected function batch( ?int $user, array $requests, ?string $validation = null ): array {
		$payload = array( 'requests' => array() );
		if ( null !== $validation ) {
			$payload['validation'] = $validation;
		}
		foreach ( $requests as $r ) {
			$item = array(
				'method' => $r[0],
				'path'   => '/acme-tasks/v1' . $r[1],
			);
			if ( isset( $r[2] ) ) {
				$item['body'] = $r[2];
			}
			$payload['requests'][] = $item;
		}
		$opts = array(
			'body' => $payload,
			'json' => true,
		);
		if ( null !== $user ) {
			$opts['login']      = $this->login_for( $user );
			$opts['rest_nonce'] = true;
		}
		return $this->http( 'POST', '/wp-json/batch/v1', $opts );
	}

	/** Sub-responses of a batch (asserting the envelope looks like a batch response). */
	protected function responses( array $res, int $expected_count ): array {
		$this->assertSame( 207, $res['status'], 'batch response: ' . substr( $res['body'], 0, 2000 ) );
		$this->assertIsArray( $res['json'] );
		$this->assertArrayHasKey( 'responses', $res['json'], substr( $res['body'], 0, 2000 ) );
		$this->assertCount( $expected_count, $res['json']['responses'], substr( $res['body'], 0, 3000 ) );
		return $res['json']['responses'];
	}

	protected function statuses( array $responses ): array {
		return array_map( static fn( $r ) => is_array( $r ) ? ( $r['status'] ?? null ) : null, $responses );
	}

	protected function make_list( int $owner, string $title, array $extra = array() ): int {
		$res = $this->api( $owner, 'POST', '/lists', array_merge( array( 'title' => $title ), $extra ) );
		$this->assertSame( 201, $res['status'], $res['body'] );
		return (int) $res['json']['id'];
	}

	protected function make_task( int $user, int $list_id, string $title, array $extra = array() ): array {
		$res = $this->api( $user, 'POST', "/lists/$list_id/tasks", array_merge( array( 'title' => $title ), $extra ) );
		$this->assertSame( 201, $res['status'], $res['body'] );
		return $res['json'];
	}

	/** A fresh list owned by $owner with the given task titles; returns [list_id, [title => id]]. */
	protected function list_with_tasks( int $owner, array $titles, array $list_extra = array() ): array {
		$list_id = $this->make_list( $owner, 'List ' . wp_generate_password( 6, false ), $list_extra );
		$ids     = array();
		foreach ( $titles as $title ) {
			$ids[ $title ] = (int) $this->make_task( $owner, $list_id, $title )['id'];
		}
		return array( $list_id, $ids );
	}

	/** Tasks of a list as the given user (default: admin). */
	protected function tasks( int $list_id, int $as = 1 ): array {
		$res = $this->api( $as, 'GET', "/lists/$list_id/tasks" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		return $res['json'];
	}

	/** [title, position] pairs in the order the API returns them. */
	protected function order( int $list_id ): array {
		return array_map( static fn( $t ) => array( $t['title'], $t['position'] ), $this->tasks( $list_id ) );
	}

	/** Assert the list is in the given order with positions 0..n-1. */
	protected function assertOrder( array $titles, int $list_id, string $message = '' ): void {
		$expected = array();
		foreach ( array_values( $titles ) as $i => $title ) {
			$expected[] = array( $title, $i );
		}
		$this->assertSame( $expected, $this->order( $list_id ), $message ?: 'task order/positions' );
	}

	protected function activity( int $list_id ): array {
		$res = $this->api( 1, 'GET', "/lists/$list_id/activity" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		return $res['json'];
	}

	protected function mails_to( int $user_id ): array {
		$email = get_userdata( $user_id )->user_email;
		return array_values(
			array_filter(
				$this->mails(),
				static fn( $m ) => in_array( $email, (array) $m['to'], true ) || $m['to'] === $email
			)
		);
	}
}

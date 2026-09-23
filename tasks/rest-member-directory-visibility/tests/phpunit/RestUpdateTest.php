<?php
/**
 * PATCH /acme-members/v1/members/me (in-process, rolled back).
 */

use PHPUnit\Framework\Attributes\DataProvider;
use function WPSB\Members\ksorted;
use function WPSB\Members\norm;
use function WPSB\Members\uid;
use const WPSB\Members\VALUES;

class RestUpdateTest extends WPSB\TestCase {

	private function fields_as( string $viewer, string $member ): ?array {
		wp_set_current_user( uid( $viewer ) );
		$res = $this->rest( 'GET', '/acme-members/v1/members/' . uid( $member ) );
		if ( 404 === $res->get_status() ) {
			return null;
		}
		$this->assertSame( 200, $res->get_status() );
		return ksorted( (array) norm( $this->rest_data( $res ) )['fields'] );
	}

	private function patch( string $login, array $body, string $method = 'PATCH' ): WP_REST_Response {
		wp_set_current_user( uid( $login ) );
		return $this->rest( $method, '/acme-members/v1/members/me', array(), $body );
	}

	public function test_update_own_profile(): void {
		$res = $this->patch(
			'gina',
			array(
				'job_title'        => 'Firmware Lead',
				'website'          => 'https://gina.example/about',
				'bio'              => '<script>alert(1)</script>Builds <b>tiny</b> computers.',
				'field_visibility' => array( 'city' => 'private' ),
			)
		);
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = norm( $this->rest_data( $res ) );
		$this->assertSame( 'Firmware Lead', $data['fields']['job_title'] );
		$this->assertSame( 'Munich', $data['fields']['city'], 'Own profile: everything visible' );
		$this->assertSame( 'private', $data['visibility']['fields']['city'] );
		$this->assertSame( 'public', $data['visibility']['profile'] );
		$this->assertStringNotContainsString( '<', $data['fields']['bio'], 'HTML is stripped' );
		$this->assertStringContainsString( 'Builds', $data['fields']['bio'] );
		$this->assertStringContainsString( 'computers.', $data['fields']['bio'] );

		$anon = $this->fields_as( '', 'gina' );
		$this->assertSame( array( 'bio', 'job_title', 'website' ), array_keys( $anon ) );
		$this->assertSame( 'Firmware Lead', $anon['job_title'] );
		$this->assertArrayNotHasKey( 'city', (array) $this->fields_as( 'alice', 'gina' ) );
		$this->assertSame( '+49 89 555 0107', $this->fields_as( 'alice', 'gina' )['phone'] ?? null );

		// Profile level.
		$this->assertSame( 200, $this->patch( 'gina', array( 'visibility' => 'members' ), 'POST' )->get_status() );
		$this->assertNull( $this->fields_as( '', 'gina' ) );
		$this->assertNotNull( $this->fields_as( 'alice', 'gina' ) );
		$this->assertSame( 200, $this->patch( 'gina', array( 'visibility' => 'private' ), 'PUT' )->get_status() );
		$this->assertNull( $this->fields_as( 'alice', 'gina' ) );
		$this->assertNotNull( $this->fields_as( 'admin', 'gina' ) );
	}

	/**
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function invalid_bodies(): array {
		return array(
			'job title too long'       => array( array( 'job_title' => str_repeat( 'x', 101 ) ), 'job_title' ),
			'company too long'         => array( array( 'company' => str_repeat( 'é', 101 ) ), 'company' ),
			'city too long'            => array( array( 'city' => str_repeat( 'y', 61 ) ), 'city' ),
			'bio too long'             => array( array( 'bio' => str_repeat( 'z', 1001 ) ), 'bio' ),
			'phone with letters'       => array( array( 'phone' => 'call me maybe' ), 'phone' ),
			'phone too short'          => array( array( 'phone' => '12' ), 'phone' ),
			'javascript url'           => array( array( 'website' => 'javascript:alert(1)' ), 'website' ),
			'ftp url'                  => array( array( 'website' => 'ftp://files.example' ), 'website' ),
			'relative url'             => array( array( 'website' => 'gina.example' ), 'website' ),
			'unknown profile level'    => array( array( 'visibility' => 'friends' ), 'visibility' ),
			'unknown field level'      => array( array( 'field_visibility' => array( 'phone' => 'everyone' ) ), 'field_visibility' ),
			'unknown field'            => array( array( 'field_visibility' => array( 'salary' => 'public' ) ), 'field_visibility' ),
			'valid + invalid together' => array( array( 'job_title' => 'Should not be saved', 'website' => 'javascript:alert(1)' ), 'website' ),
		);
	}

	#[DataProvider( 'invalid_bodies' )]
	public function test_invalid_updates_are_rejected_and_nothing_is_saved( array $body, string $param ): void {
		$before = $this->fields_as( 'gina', 'gina' );
		wp_set_current_user( uid( 'gina' ) );
		$levels_before = norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members/me' ) ) )['visibility'];

		$res = $this->patch( 'gina', $body );
		$this->assertSame( 400, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$err = norm( $res->get_data() );
		$this->assertSame( 'rest_invalid_param', $err['code'] );
		$this->assertArrayHasKey( $param, $err['data']['params'] ?? array() );

		$this->assertSame( $before, $this->fields_as( 'gina', 'gina' ), 'Nothing may be saved' );
		wp_set_current_user( uid( 'gina' ) );
		$this->assertSame( $levels_before, norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members/me' ) ) )['visibility'] );
	}

	public function test_valid_edge_values(): void {
		$res = $this->patch(
			'gina',
			array(
				'phone'     => '+49 (89) 555-0107/12',
				'job_title' => str_repeat( 'ü', 100 ),
				'website'   => '',
				'city'      => '',
			)
		);
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$fields = norm( $this->rest_data( $res ) )['fields'];
		$this->assertSame( '+49 (89) 555-0107/12', $fields['phone'] );
		$this->assertArrayNotHasKey( 'city', $fields, 'Empty values are not listed' );
	}

	public function test_saving_a_1x_profile_keeps_its_data_and_settings(): void {
		$res = $this->patch( 'erin', array( 'job_title' => 'Head of Community' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );

		$expected_public = array(
			'bio'       => VALUES['erin']['bio'],
			'city'      => VALUES['erin']['city'],
			'company'   => VALUES['erin']['company'],
			'job_title' => 'Head of Community',
			'website'   => VALUES['erin']['website'],
		);
		$this->assertSame( $expected_public, $this->fields_as( '', 'erin' ) );
		$this->assertSame( $expected_public, $this->fields_as( 'gina', 'erin' ), 'Her 1.x "hide phone" setting must survive the save' );
		$this->assertSame( VALUES['erin']['phone'], $this->fields_as( 'admin', 'erin' )['phone'] ?? null );

		// A hidden 1.x profile stays hidden.
		$res = $this->patch( 'dave', array( 'city' => 'Kiel' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertNull( $this->fields_as( '', 'dave' ) );
		$this->assertNull( $this->fields_as( 'gina', 'dave' ) );
		$admin = $this->fields_as( 'admin', 'dave' );
		$this->assertSame( 'Kiel', $admin['city'] );
		$this->assertSame( 'Chief Tinkerer', $admin['job_title'] );
		$this->assertSame( 'Hidden legacy profile.', $admin['bio'] );
	}

	public function test_only_members_can_update_and_only_themselves(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'PATCH', '/acme-members/v1/members/me', array(), array( 'job_title' => 'x' ) )->get_status() );
		$this->assertSame( 403, $this->patch( 'sue', array( 'job_title' => 'x' ) )->get_status() );
		$this->assertSame( 403, $this->patch( 'eddie', array( 'job_title' => 'x' ) )->get_status() );

		wp_set_current_user( uid( 'gina' ) );
		$res = $this->rest( 'PATCH', '/acme-members/v1/members/' . uid( 'alice' ), array(), array( 'job_title' => 'Hacked' ) );
		$this->assertContains( $res->get_status(), array( 403, 404, 405 ) );
		$res = $this->patch( 'gina', array( 'job_title' => 'Mine', 'id' => uid( 'alice' ), 'user_id' => uid( 'alice' ) ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'Head of Data', $this->fields_as( 'admin', 'alice' )['job_title'] );
		$this->assertSame( 'Mine', $this->fields_as( 'admin', 'gina' )['job_title'] );
	}
}

<?php
/**
 * [acme_member_edit_profile] on /edit-profile/ over HTTP.
 */

use function WPSB\Members\cls;
use function WPSB\Members\uid;
use function WPSB\Members\xpath;

class ProfileFormTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** Admin view of a member's profile fields (in-process REST, fresh caches). */
	private function stored( string $member ): array {
		wp_cache_flush();
		wp_set_current_user( uid( 'admin' ) );
		$res = $this->rest( 'GET', '/acme-members/v1/members/' . uid( $member ) );
		wp_set_current_user( 0 );
		$this->assertSame( 200, $res->get_status() );
		return json_decode( wp_json_encode( $this->rest_data( $res ) ), true );
	}

	/** Form inputs (name => value) of the profile form on a page. */
	private function form( string $html ): array {
		$x    = xpath( $html );
		$form = $x->query( '//form[' . cls( 'acme-profile-form' ) . ']' );
		$this->assertSame( 1, $form->length, 'Expected the profile form' );
		$out = array( '_action' => $form->item( 0 )->getAttribute( 'action' ) );
		foreach ( $x->query( './/input[@name]', $form->item( 0 ) ) as $input ) {
			$type = strtolower( $input->getAttribute( 'type' ) );
			if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $input->hasAttribute( 'checked' ) ) {
				continue;
			}
			$out[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
		}
		foreach ( $x->query( './/textarea[@name]', $form->item( 0 ) ) as $ta ) {
			$out[ $ta->getAttribute( 'name' ) ] = $ta->textContent;
		}
		foreach ( $x->query( './/select[@name]', $form->item( 0 ) ) as $select ) {
			$selected = $x->query( './/option[@selected]', $select );
			$out[ $select->getAttribute( 'name' ) ] = $selected->length ? $selected->item( 0 )->getAttribute( 'value' ) : $x->query( './/option', $select )->item( 0 )->getAttribute( 'value' );
		}
		return $out;
	}

	/** Submit the form like a browser; follows one redirect (with cookies set on the way). */
	private function submit( array $login, array $fields, string $referer ): array {
		$action = $fields['_action'];
		unset( $fields['_action'] );
		$res = $this->http(
			'POST',
			$action,
			array(
				'login'   => $login,
				'headers' => array( 'Referer' => $referer ),
				'body'    => $fields,
			)
		);
		if ( in_array( $res['status'], array( 301, 302, 303, 307 ), true ) && ! empty( $res['headers']['location'] ) ) {
			$extra = array();
			if ( ! empty( $res['headers']['set-cookie'] ) ) {
				foreach ( preg_split( '/,\s*(?=[^;,=\s]+=)/', $res['headers']['set-cookie'] ) as $cookie ) {
					$extra[] = trim( explode( ';', $cookie )[0] );
				}
			}
			$opts = array( 'login' => $login );
			if ( $extra ) {
				$opts['cookie'] = implode( '; ', $extra );
			}
			$res['followed'] = $this->http( 'GET', $res['headers']['location'], $opts );
		}
		return $res;
	}

	private function page_after( array $res ): string {
		return isset( $res['followed'] ) ? $res['followed']['body'] : $res['body'];
	}

	public function test_form_visibility_by_user(): void {
		$res = $this->http( 'GET', '/edit-profile/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringNotContainsString( 'acme-profile-form"', $res['body'] );
		$this->assertStringNotContainsString( '[acme_member_edit_profile]', $res['body'] );
		$this->assertMatchesRegularExpression( '#href="[^"]*wp-login\.php#', $res['body'], 'Logged-out visitors get a login link' );

		$res = $this->http( 'GET', '/edit-profile/', array( 'login' => $this->http_login( uid( 'sue' ) ) ) );
		$this->assertSame( 0, xpath( $res['body'] )->query( '//form[' . cls( 'acme-profile-form' ) . ']' )->length );
		$this->assertStringNotContainsString( '[acme_member_edit_profile]', $res['body'] );

		$res  = $this->http( 'GET', '/edit-profile/', array( 'login' => $this->http_login( uid( 'carol' ) ) ) );
		$form = $this->form( $res['body'] );
		$this->assertStringEndsWith( '/wp-admin/admin-post.php', $form['_action'] );
		$this->assertSame( 'acme_members_save_profile', $form['action'] );
		$this->assertSame( 'Product Designer', $form['job_title'] );
		$this->assertSame( 'Designs things.', $form['bio'] );
		$this->assertSame( 'public', $form['visibility'] );
		$this->assertSame( 'private', $form['field_visibility[company]'] );
		$this->assertSame( 'members', $form['field_visibility[city]'] );
		$this->assertSame( 'public', $form['field_visibility[phone]'] );

		// 1.x profile + 1.x settings are prefilled too.
		$form = $this->form( $this->http( 'GET', '/edit-profile/', array( 'login' => $this->http_login( uid( 'erin' ) ) ) )['body'] );
		$this->assertSame( 'Community Manager', $form['job_title'] );
		$this->assertSame( 'private', $form['field_visibility[phone]'] );
	}

	public function test_member_saves_own_profile(): void {
		$login = $this->http_login( uid( 'gina' ) );
		$page  = $this->http( 'GET', '/edit-profile/', array( 'login' => $login ) );
		$form  = $this->form( $page['body'] );

		$form['job_title']               = 'Firmware Lead';
		$form['phone']                   = '+49 (89) 555-0199';
		$form['website']                 = 'https://gina.example';
		$form['visibility']              = 'members';
		$form['field_visibility[phone]'] = 'private';
		$form['user_id']                 = (string) uid( 'alice' );
		$res                             = $this->submit( $login, $form, home_url( '/edit-profile/' ) );
		$this->assertContains( $res['status'], array( 200, 302, 303 ) );

		$gina = $this->stored( 'gina' );
		$this->assertSame( 'Firmware Lead', $gina['fields']['job_title'] ?? null );
		$this->assertSame( '+49 (89) 555-0199', $gina['fields']['phone'] ?? null );
		$this->assertSame( 'members', $gina['visibility']['profile'] );
		$this->assertSame( 'private', $gina['visibility']['fields']['phone'] );
		$this->assertSame( 'Head of Data', $this->stored( 'alice' )['fields']['job_title'], 'Only the own profile may be edited' );

		$after = $this->page_after( $res );
		$this->assertStringContainsString( 'Firmware Lead', $after );
		$this->assertStringNotContainsString( 'aria-invalid="true"', $after );

		// The new settings apply everywhere.
		$this->assertSame( 404, $this->http( 'GET', '/members/gina/' )['status'] );
	}

	public function test_invalid_input_is_rejected_with_accessible_errors(): void {
		$login  = $this->http_login( uid( 'erin' ) );
		$before = $this->stored( 'erin' );
		$form   = $this->form( $this->http( 'GET', '/edit-profile/', array( 'login' => $login ) )['body'] );

		$form['job_title'] = 'Head of Community';
		$form['website']   = 'javascript:alert(1)';
		$form['phone']     = 'call me "maybe"';
		$res               = $this->submit( $login, $form, home_url( '/edit-profile/' ) );

		$this->assertSame( $before, $this->stored( 'erin' ), 'Nothing may be saved when something is invalid' );

		$html = $this->page_after( $res );
		$x    = xpath( $html );
		$this->assertGreaterThan( 0, $x->query( '//*[@role="alert"]' )->length, 'Error summary with role="alert"' );
		foreach ( array( 'website', 'phone' ) as $name ) {
			$input = $x->query( '//form[' . cls( 'acme-profile-form' ) . ']//input[@name="' . $name . '"]' );
			$this->assertSame( 1, $input->length );
			$input = $input->item( 0 );
			$this->assertSame( 'true', $input->getAttribute( 'aria-invalid' ), "$name must be marked invalid" );
			$ids = preg_split( '/\s+/', trim( $input->getAttribute( 'aria-describedby' ) ) );
			$this->assertNotEmpty( array_filter( $ids ) );
			$text = '';
			foreach ( $ids as $id ) {
				$el = $x->query( '//*[@id="' . $id . '"]' );
				if ( $el->length ) {
					$text .= trim( $el->item( 0 )->textContent );
				}
			}
			$this->assertNotSame( '', $text, "$name: aria-describedby must point at its error message" );
		}
		$ok = $x->query( '//form[' . cls( 'acme-profile-form' ) . ']//input[@name="job_title"]' )->item( 0 );
		$this->assertNotSame( 'true', $ok->getAttribute( 'aria-invalid' ) );
		// Entered values are kept (and escaped).
		$this->assertSame( 'Head of Community', $ok->getAttribute( 'value' ) );
		$this->assertSame( 'call me "maybe"', $x->query( '//input[@name="phone"]' )->item( 0 )->getAttribute( 'value' ) );
		$this->assertStringNotContainsString( 'value="call me "maybe""', $html );
	}

	public function test_forged_requests_change_nothing(): void {
		$login  = $this->http_login( uid( 'carol' ) );
		$before = $this->stored( 'carol' );
		$form   = $this->form( $this->http( 'GET', '/edit-profile/', array( 'login' => $login ) )['body'] );
		$nonce_fields = array_filter( array_keys( $form ), static fn( $k ) => ! in_array( $k, array( '_action', 'action', 'job_title', 'company', 'city', 'phone', 'website', 'bio', 'visibility', '_wp_http_referer' ), true ) && 0 !== strpos( $k, 'field_visibility' ) );
		$this->assertNotEmpty( $nonce_fields, 'The form must carry a nonce' );

		$form['visibility']                = 'public';
		$form['field_visibility[company]'] = 'public';
		$form['job_title']                 = 'Pwned';
		$forged                            = $form;
		foreach ( $nonce_fields as $k ) {
			unset( $forged[ $k ] );
		}
		$this->submit( $login, $forged, 'https://evil.example/' );
		$this->assertSame( $before, $this->stored( 'carol' ), 'Request without nonce' );

		foreach ( $nonce_fields as $k ) {
			$forged[ $k ] = 'deadbeef00';
		}
		$this->submit( $login, $forged, 'https://evil.example/' );
		$this->assertSame( $before, $this->stored( 'carol' ), 'Request with a wrong nonce' );

		// Someone else's nonce doesn't work either.
		$other = $this->http_login( uid( 'bob' ) );
		$this->submit( $other, $form, home_url( '/edit-profile/' ) );
		$this->assertSame( $before, $this->stored( 'carol' ) );
		$this->assertSame( 'Site Reliability Engineer', $this->stored( 'bob' )['fields']['job_title'] );

		// Logged out.
		$res = $this->http( 'POST', $form['_action'], array( 'body' => array_diff_key( $form, array( '_action' => 1 ) ) ) );
		$this->assertNotSame( 500, $res['status'] );
		$this->assertSame( $before, $this->stored( 'carol' ) );
	}
}

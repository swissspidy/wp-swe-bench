<?php
/**
 * Whatever the editor uses to read/write member data must respect the admin's rules;
 * the member card pattern.
 */

use function WPSB\Team\id_of;
use function WPSB\Team\render_content;
use function WPSB\Team\texts;

class EditorDataTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** All REST responses a user gets for a member (view + edit context, single + collection). */
	private function member_responses( int $user, int $member ): string {
		wp_set_current_user( $user );
		$out = '';
		foreach ( array( 'view', 'edit', 'embed' ) as $context ) {
			$res  = $this->rest( 'GET', '/wp/v2/acme-members/' . $member, array( 'context' => $context ) );
			$out .= wp_json_encode( $this->rest_data( $res ) );
		}
		$res  = $this->rest( 'GET', '/wp/v2/acme-members', array( 'per_page' => 100, 'status' => 'any' ) );
		$out .= wp_json_encode( $this->rest_data( $res ) );
		$res  = $this->rest( 'GET', '/wp/v2/acme-members', array( 'per_page' => 100 ) );
		$out .= wp_json_encode( $this->rest_data( $res ) );
		wp_set_current_user( 0 );
		return $out;
	}

	public function test_internal_notes_are_never_exposed(): void {
		$ada   = id_of( 'ada-lovelace' );
		$admin = get_user_by( 'login', 'admin' )->ID;
		$this->assertStringNotContainsString( 'Salary band', $this->member_responses( $admin, $ada ) );
		$this->assertStringNotContainsString( 'Salary band', $this->member_responses( 0, $ada ) );
		$res = $this->http( 'GET', '/wp-json/wp/v2/acme-members/' . $ada . '?_fields=meta,acme_fields' );
		$this->assertStringNotContainsString( 'Salary band', $res['body'] );
	}

	public function test_non_public_member_data_is_not_readable_by_others(): void {
		$alex = get_user_by( 'login', 'alex' )->ID;
		foreach ( array( 'pete-private' => 'Acquisition Target', 'dora-draft' => 'Secret Project Lead', 'paula-protected' => 'Board Member', 'pat-pending' => 'Incoming CFO' ) as $slug => $secret ) {
			$id = id_of( $slug );
			$this->assertStringNotContainsString( $secret, $this->member_responses( 0, $id ), "$slug leaked to visitors" );
			$this->assertStringNotContainsString( $secret, $this->member_responses( $alex, $id ), "$slug leaked to an author" );
		}
		$res = $this->http( 'GET', '/wp-json/wp/v2/acme-members?per_page=100' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringNotContainsString( 'Board Member', $res['body'] );
	}

	public function test_member_data_is_readable_by_those_who_may_edit(): void {
		// The editor must be able to load values; check it through a real editor-like request.
		$eddie = get_user_by( 'login', 'eddie' )->ID;
		$login = $this->http_login( $eddie );
		$res   = $this->http( 'GET', '/wp-json/wp/v2/acme-members/' . id_of( 'dora-draft' ) . '?context=edit', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Secret Project Lead', $res['body'], 'Editors may see draft members' );
		$res = $this->http( 'GET', '/wp-json/wp/v2/acme-members/' . id_of( 'linus-legacy' ) . '?context=edit', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertStringContainsString( 'Kernel Maintainer', $res['body'], '1.x values must be available too' );
	}

	public function test_members_cannot_be_changed_by_users_who_cannot_edit_them(): void {
		$ada    = id_of( 'ada-lovelace' );
		$before = do_shortcode( '[team_member id="' . $ada . '" fields="role,email"]' );
		$alex   = get_user_by( 'login', 'alex' )->ID;
		$login  = $this->http_login( $alex );
		$attempts = array(
			array( 'meta' => array( '_acme_role' => 'Hacked' ) ),
			array( 'title' => 'Hacked name' ),
		);
		// Any shape a plugin might accept for the fields.
		foreach ( array( 'acme_fields', 'fields', 'member', 'acme_team' ) as $prop ) {
			$attempts[] = array( $prop => array( 'role' => 'Hacked' ) );
		}
		foreach ( $attempts as $body ) {
			$res = $this->http( 'POST', '/wp-json/wp/v2/acme-members/' . $ada, array( 'login' => $login, 'rest_nonce' => true, 'json' => true, 'body' => $body ) );
			$this->assertContains( $res['status'], array( 400, 401, 403 ), 'Author must not update another user\'s member: ' . $res['body'] );
		}
		wp_cache_flush();
		$this->assertSame( $before, do_shortcode( '[team_member id="' . $ada . '" fields="role,email"]' ) );
		$this->assertStringNotContainsString( 'Hacked', get_post( $ada )->post_title );
	}

	public function test_pattern_is_registered_and_renders_in_a_query_loop(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'acme-team/member-card' ), 'Pattern acme-team/member-card missing' );
		$pattern = $registry->get_registered( 'acme-team/member-card' );
		$this->assertSame( 'Team member card', $pattern['title'] );
		$this->assertStringContainsString( 'acme/team-member', $pattern['content'] );
		$this->assertStringNotContainsString( 'memberId', $pattern['content'] );
		$this->assertNotEmpty( $pattern['inserter'] ?? true );

		$names = array_map( static fn( $b ) => $b['blockName'], WPSB_flatten( parse_blocks( $pattern['content'] ) ) );
		foreach ( array( 'core/image', 'core/heading', 'core/paragraph', 'core/button' ) as $block ) {
			$this->assertContains( $block, $names );
		}

		$query = '<!-- wp:query {"queryId":3,"query":{"perPage":10,"pages":0,"offset":0,"postType":"acme_member","order":"asc","orderBy":"title","inherit":false}} -->'
			. '<div class="wp-block-query"><!-- wp:post-template -->' . $pattern['content'] . '<!-- /wp:post-template --></div><!-- /wp:query -->';
		$html  = render_content( $query );
		$this->assertSame( array( 'Ada Lovelace', 'Grace Hopper', 'Hank <em>Hostile</em>', 'Linus Legacy' ), array_slice( texts( $html, '//h3' ), 0, 4 ) );
		$this->assertStringContainsString( 'Head of Engineering', $html );
		$this->assertStringContainsString( 'href="mailto:grace@acme.test"', $html );
		$ada_photo = (int) get_post_meta( id_of( 'ada-lovelace' ), '_acme_photo_id', true );
		$this->assertStringContainsString( 'src="' . wp_get_attachment_url( $ada_photo ) . '"', $html );
		$this->assertStringNotContainsString( 'Board Member', $html );
	}
}

/** Flatten parsed blocks. */
function WPSB_flatten( array $blocks ): array {
	$out = array();
	foreach ( $blocks as $b ) {
		$out[] = $b;
		$out   = array_merge( $out, WPSB_flatten( $b['innerBlocks'] ?? array() ) );
	}
	return $out;
}

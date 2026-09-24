<?php
/**
 * Front-end rendering of core blocks connected to the acme/team-member source.
 */

use function WPSB\Team\attrs;
use function WPSB\Team\button;
use function WPSB\Team\buttons;
use function WPSB\Team\id_of;
use function WPSB\Team\image;
use function WPSB\Team\p;
use function WPSB\Team\render_content;
use function WPSB\Team\render_post;
use function WPSB\Team\render_slug;
use function WPSB\Team\texts;

class BindingsFrontEndTest extends WPSB\TestCase {

	const SECRETS = array( 'Secret Project Lead', 'dora@acme.test', '010 7777', 'Acquisition Target', '010 9999', 'pete@acme.test', 'Pete Private', 'Incoming CFO', 'Board Member', 'paula@acme.test', 'Salary band' );

	private function assertNoSecrets( string $html ): void {
		foreach ( self::SECRETS as $secret ) {
			$this->assertStringNotContainsString( $secret, $html, "Leaked: $secret" );
		}
	}

	public function test_source_is_registered_with_its_label(): void {
		$source = get_block_bindings_source( 'acme/team-member' );
		$this->assertNotNull( $source, 'Binding source acme/team-member is not registered' );
		$this->assertSame( 'Team member', $source->label );
	}

	public function test_explicit_member_fields_render(): void {
		$html = render_slug( 'meet-ada' );
		$ada  = acme_team_get_member( id_of( 'ada-lovelace' ) );

		$this->assertSame( array( 'Ada Lovelace' ), texts( $html, '//h2' ) );
		$this->assertContains( 'Head of Engineering', texts( $html, '//p' ) );

		$photo = (int) get_post_meta( $ada->id(), '_acme_photo_id', true );
		$srcs  = attrs( $html, '//figure[contains(@class,"wp-block-image")]//img', 'src' );
		$alts  = attrs( $html, '//figure[contains(@class,"wp-block-image")]//img', 'alt' );
		$this->assertSame( wp_get_attachment_url( $photo ), $srcs[0] ?? null, 'Image url must be the full-size photo URL' );
		$this->assertSame( 'Portrait of Ada Lovelace', $alts[0] ?? null );

		$this->assertSame(
			array(
				array( 'ada@acme.test', 'mailto:ada@acme.test' ),
				array( '+1 (555) 010-1001', 'tel:+15550101001' ),
				array( 'Ada Lovelace', 'https://profiles.acme.test/ada' ),
			),
			buttons( $html )
		);
	}

	public function test_empty_fields_keep_the_saved_content_and_alt_falls_back_to_the_name(): void {
		$html  = render_slug( 'meet-ada' );
		$grace = id_of( 'grace-hopper' );
		// Grace has no phone: the paragraph keeps its placeholder.
		$this->assertContains( 'Phone', texts( $html, '//p' ) );
		$photo = (int) get_post_meta( $grace, '_acme_photo_id', true );
		$srcs  = attrs( $html, '//figure[contains(@class,"wp-block-image")]//img', 'src' );
		$alts  = attrs( $html, '//figure[contains(@class,"wp-block-image")]//img', 'alt' );
		$this->assertSame( wp_get_attachment_url( $photo ), $srcs[1] ?? null );
		$this->assertSame( 'Grace Hopper', $alts[1] ?? null, 'A photo without alt text uses the member name' );
	}

	public function test_non_public_members_never_leak(): void {
		$html = render_slug( 'internal-members' );
		$this->assertNoSecrets( $html );
		foreach ( array( 'Role of the draft member', 'Phone of the private member', 'Role of the pending member', 'Role of the protected member' ) as $fallback ) {
			$this->assertContains( $fallback, texts( $html, '//p' ), "Fallback '$fallback' must be kept" );
		}
		$this->assertSame( array( 'Name of the private member' ), texts( $html, '//h2' ) );
		$this->assertSame( array( array( 'Email the draft member', null ) ), buttons( $html ) );

		// Not even for administrators.
		$this->login_as( 'administrator' );
		$this->assertNoSecrets( render_slug( 'internal-members' ) );
	}

	public function test_scheduled_and_trashed_members_are_not_shown(): void {
		$future = $this->create_post( array( 'post_type' => 'acme_member', 'post_title' => 'Future Fiona', 'post_status' => 'future', 'post_date' => gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) ) );
		update_post_meta( $future, '_acme_role', 'Future Role' );
		$trashed = $this->create_post( array( 'post_type' => 'acme_member', 'post_title' => 'Trashed Tom' ) );
		update_post_meta( $trashed, '_acme_role', 'Trashed Role' );
		wp_trash_post( $trashed );

		$html = render_content( p( array( 'key' => 'role', 'memberId' => $future ), 'F1' ) . p( array( 'key' => 'role', 'memberId' => $trashed ), 'F2' ) );
		$this->assertSame( array( 'F1', 'F2' ), texts( $html, '//p' ) );
	}

	public function test_query_loop_uses_each_member(): void {
		$html  = render_slug( 'our-team' );
		$cards = array();
		$xpath = WPSB\Team\dom( $html );
		foreach ( $xpath->query( '//*[contains(@class,"team-card")]' ) as $card ) {
			$h3      = $xpath->query( './/h3', $card )->item( 0 );
			$p       = $xpath->query( './/p', $card )->item( 0 );
			$a       = $xpath->query( './/a[contains(@class,"wp-block-button__link")]', $card )->item( 0 );
			$cards[] = array(
				$h3 ? trim( $h3->textContent ) : null,
				$p ? trim( $p->textContent ) : null,
				$a && $a->hasAttribute( 'href' ) ? $a->getAttribute( 'href' ) : null,
			);
		}
		$this->assertSame(
			array(
				array( 'Ada Lovelace', 'Head of Engineering', 'mailto:ada@acme.test' ),
				array( 'Grace Hopper', 'Compiler Lead', 'mailto:grace@acme.test' ),
				array( 'Hank <em>Hostile</em>', 'R&D <script>alert("role")</script><b>Lead</b>', 'mailto:hank@acme.test' ),
				array( 'Linus Legacy', 'Kernel Maintainer', 'mailto:linus@acme.test' ),
				// Password protected: listed by the query, but its fields are not shown.
				array( 'Name', 'Role', null ),
			),
			$cards
		);
		$this->assertNoSecrets( $html );
	}

	public function test_stored_values_are_shown_as_text(): void {
		$hank = id_of( 'hank-hostile' );
		$html = render_content(
			p( array( 'key' => 'role', 'memberId' => $hank ) )
			. p( array( 'key' => 'pronouns', 'memberId' => $hank ) )
			. p( array( 'key' => 'phone', 'memberId' => $hank ) )
			. button( array( 'key' => 'phone', 'memberId' => $hank ), array( 'key' => 'phone', 'memberId' => $hank ), 'Call' )
			. button( array( 'key' => 'name', 'memberId' => $hank ), array( 'key' => 'profile_url', 'memberId' => $hank ), 'Profile' )
			. image( array( 'key' => 'name', 'memberId' => $hank ) )
		);
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>Lead', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html );
		$this->assertStringNotContainsString( '<em>Hostile', $html );

		$this->assertSame(
			array( 'R&D <script>alert("role")</script><b>Lead</b>', 'he/him <script>alert(2)</script>', '+1 555 <img src=x onerror=alert(1)>' ),
			texts( $html, '//p' )
		);
		$buttons = buttons( $html );
		$this->assertSame( '+1 555 <img src=x onerror=alert(1)>', $buttons[0][0] );
		$this->assertSame( 'tel:+15551', $buttons[0][1] );
		$this->assertSame( 'Hank <em>Hostile</em>', $buttons[1][0] );
		$this->assertNull( $buttons[1][1], 'A non-http(s) profile URL must not be linked' );
	}

	public function test_1x_members_work(): void {
		$linus = id_of( 'linus-legacy' );
		$html  = render_content(
			p( array( 'key' => 'role', 'memberId' => (string) $linus ) )
			. button( array( 'key' => 'email', 'memberId' => $linus ), array( 'key' => 'email', 'memberId' => $linus ) )
			. button( array( 'key' => 'phone', 'memberId' => $linus ), array( 'key' => 'phone', 'memberId' => $linus ) )
			. button( array( 'key' => 'name', 'memberId' => $linus ), array( 'key' => 'name', 'memberId' => $linus ) )
		);
		$this->assertSame( array( 'Kernel Maintainer' ), texts( $html, '//p' ) );
		$this->assertSame(
			array(
				array( 'linus@acme.test', 'mailto:linus@acme.test' ),
				array( '+44 20 7946 0000', 'tel:+442079460000' ),
				array( 'Linus Legacy', get_permalink( $linus ) ),
			),
			buttons( $html )
		);
	}

	public function test_only_member_fields_are_reachable(): void {
		$ada  = id_of( 'ada-lovelace' );
		$page = id_of( 'meet-ada', 'page' );
		$post = $this->create_post( array( 'post_title' => 'A regular post' ) );
		update_post_meta( $post, '_acme_role', 'Not a member role' );
		$html = render_content(
			p( array( 'key' => '_acme_notes', 'memberId' => $ada ), 'F1' )
			. p( array( 'key' => 'notes', 'memberId' => $ada ), 'F2' )
			. p( array( 'key' => '_acme_role', 'memberId' => $ada ), 'F3' )
			. p( array( 'key' => 'post_password', 'memberId' => id_of( 'paula-protected' ) ), 'F4' )
			. p( array( 'key' => 'role', 'memberId' => $post ), 'F5' )
			. p( array( 'key' => 'name', 'memberId' => $page ), 'F6' )
			. p( array( 'key' => 'role', 'memberId' => 999999 ), 'F7' )
			. p( array( 'key' => 'role', 'memberId' => 'abc' ), 'F8' )
			. p( array( 'key' => 'role', 'memberId' => -1 ), 'F9' )
			. p( array( 'memberId' => $ada ), 'F10' )
			. p( array( 'key' => array( 'role' ), 'memberId' => $ada ), 'F11' )
		);
		$this->assertSame( array( 'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11' ), texts( $html, '//p' ) );
		$this->assertStringNotContainsString( 'Salary', $html );
		$this->assertStringNotContainsString( 'board', $html );
	}

	public function test_member_from_context(): void {
		// On a member's own page the member is the current post.
		$html = render_post( get_post( id_of( 'ada-lovelace' ) ) );
		$this->assertSame( array( 'Ada leads our engineering teams.', 'Head of Engineering', 'she/her' ), texts( $html, '//p' ) );

		// On a page that isn't a member there is nothing to show.
		$html = render_content( p( array( 'key' => 'role' ), 'Role' ) . p( array( 'key' => 'name' ), 'Name' ), id_of( 'meet-ada', 'page' ) );
		$this->assertSame( array( 'Role', 'Name' ), texts( $html, '//p' ) );

		// On a non-public member's page neither.
		$html = render_content( p( array( 'key' => 'role' ), 'Role' ), id_of( 'dora-draft' ), 'acme_member' );
		$this->assertSame( array( 'Role' ), texts( $html, '//p' ) );
	}

	public function test_shortcodes_still_work(): void {
		$html = render_slug( 'leadership' );
		$this->assertStringContainsString( 'acme-team-card', $html );
		$this->assertStringContainsString( 'Head of Engineering', $html );
		$this->assertStringContainsString( 'href="tel:+15550101001"', $html );
		$this->assertStringContainsString( 'she/her', $html );
		$this->assertStringContainsString( 'Kernel Maintainer', $html );
		$this->assertStringNotContainsString( 'Secret Project Lead', $html );

		$html = render_slug( 'engineering-team' );
		$this->assertSame( 3, substr_count( $html, 'acme-team-card__name' ) );
		$this->assertStringContainsString( 'Linus Legacy', $html );
	}
}

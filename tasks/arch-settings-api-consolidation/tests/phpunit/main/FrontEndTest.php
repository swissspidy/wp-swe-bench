<?php
/**
 * Front-end output of the seeded site stays the same after the update.
 */

class FrontEndTest extends AcmeSocialCase {

	public function test_share_buttons(): void {
		$html = $this->page( '/launch-week/' );
		$this->assertSame( 1, $this->share_blocks( $html ) );
		$this->assertSame( array( 'facebook', 'twitter', 'linkedin', 'mastodon' ), $this->share_networks( $html ) );
		$this->assertStringContainsString( 'acme-social-share--icons_text', $html );
		$this->assertMatchesRegularExpression( '#know\.</p>\s*<div class="acme-social-share#', $html, 'Buttons go after the content' );
		$this->assertStringContainsString( 'https://mastodonshare.com/?url=http%3A%2F%2F127.0.0.1%3A9400%2Flaunch-week%2F', $html );

		$this->assertSame( 1, $this->share_blocks( $this->page( '/events/community-meetup/' ) ), 'events show buttons' );
		$this->assertSame( 1, $this->share_blocks( $this->page( '/about-us/' ) ), 'pages show buttons' );
		$this->assertSame( 0, $this->share_blocks( $this->page( '/internal-memo/' ) ), 'hidden per post' );
	}

	public function test_open_graph_tags(): void {
		$html = $this->page( '/launch-week/' );
		$this->assertSame( 'Launch week', $this->meta( $html, 'property', 'og:title' ) );
		$this->assertSame( 'article', $this->meta( $html, 'property', 'og:type' ) );
		$this->assertSame( wp_get_attachment_url( $this->image_id() ), $this->meta( $html, 'property', 'og:image' ) );
		$this->assertSame( '1234567890', $this->meta( $html, 'property', 'fb:app_id' ) );
		$this->assertSame( 'summary_large_image', $this->meta( $html, 'name', 'twitter:card' ) );
		$this->assertSame( '@AcmeHQ', $this->meta( $html, 'name', 'twitter:site' ) );

		$home = $this->page( '/' );
		$this->assertSame( 'website', $this->meta( $home, 'property', 'og:type' ) );
		$this->assertSame( 'Acme Widgets', $this->meta( $home, 'property', 'og:site_name' ) );
	}

	public function test_profile_links(): void {
		$html = $this->page( '/about-us/' );
		$this->assertMatchesRegularExpression( '#<ul class="acme-social-profiles">.*</ul>#s', $html );
		preg_match( '#<ul class="acme-social-profiles">(.*?)</ul>#s', $html, $m );
		preg_match_all( '#acme-social-profiles__item--([a-z]+)"><a href="([^"]+)"#', $m[1], $links, PREG_SET_ORDER );
		$this->assertSame(
			array(
				'twitter'   => 'https://x.com/AcmeHQ',
				'facebook'  => 'https://www.facebook.com/acmehq',
				'instagram' => 'http://instagram.com/acmehq',
			),
			array_combine( array_column( $links, 1 ), array_column( $links, 2 ) )
		);
	}
}

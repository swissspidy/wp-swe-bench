<?php
/**
 * Member data on the front end over HTTP: directory (incl. its cache), author archives, RSS feed,
 * XML sitemaps, and the API with real cookie authentication.
 */

use function WPSB\Members\author_card;
use function WPSB\Members\cards;
use function WPSB\Members\expected;
use function WPSB\Members\ksorted;
use function WPSB\Members\uid;

class SurfacesTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $logins = array();

	private function login( string $user ): ?array {
		if ( '' === $user ) {
			return null;
		}
		if ( ! isset( $this->logins[ $user ] ) ) {
			$this->logins[ $user ] = $this->http_login( uid( $user ) );
		}
		return $this->logins[ $user ];
	}

	private function get( string $path, string $as = '' ): array {
		$opts = array();
		if ( $as ) {
			$opts['login'] = $this->login( $as );
		}
		return $this->http( 'GET', $path, $opts );
	}

	/** Expected card fields of a directory card (job title, company, city, phone). */
	private function card_expected( string $member, string $class ): ?array {
		$e = expected( $member, $class );
		return null === $e ? null : ksorted( array_intersect_key( $e, array_flip( array( 'job_title', 'company', 'city', 'phone' ) ) ) );
	}

	private function assert_directory( string $html, string $class, string $label ): void {
		$cards = cards( $html );
		foreach ( array( 'alice', 'bob', 'carol', 'dave', 'erin', 'frank', 'henry' ) as $m ) {
			$expected = $this->card_expected( $m, $class );
			if ( null === $expected ) {
				$this->assertArrayNotHasKey( uid( $m ), $cards, "$label: $m must not be listed" );
			} else {
				$this->assertArrayHasKey( uid( $m ), $cards, "$label: $m must be listed" );
				$this->assertSame( $expected, $cards[ uid( $m ) ], "$label: card of $m" );
			}
		}
	}

	public function test_directory_cache_never_leaks_to_other_visitors(): void {
		// An administrator and a member look at the directory first…
		$admin = $this->get( '/members/', 'admin' );
		$this->assertSame( 200, $admin['status'] );
		$this->assertArrayHasKey( uid( 'frank' ), cards( $admin['body'] ), 'Admins see private profiles' );
		$this->assert_directory( $admin['body'], 'all', 'directory as admin' );

		$member = $this->get( '/members/', 'gina' );
		$this->assert_directory( $member['body'], 'members', 'directory as member' );

		// …then anonymous visitors and logged-in non-members.
		foreach ( array( '', 'sue', 'eddie', '' ) as $viewer ) {
			$res = $this->get( '/members/', $viewer );
			$this->assertSame( 200, $res['status'] );
			$this->assert_directory( $res['body'], 'public', 'directory as ' . ( $viewer ?: 'anonymous' ) );
			$this->assertStringNotContainsString( 'Secret Corp', $res['body'] );
			$this->assertStringNotContainsString( '555 0101', $res['body'] );
		}

		// Frank sees himself.
		$this->assertArrayHasKey( uid( 'frank' ), cards( $this->get( '/members/', 'frank' )['body'] ) );
		$this->assertArrayNotHasKey( uid( 'frank' ), cards( $this->get( '/members/', 'gina' )['body'] ) );

		// The block (Berlin page): member first, then anonymous.
		$res = $this->get( '/berlin-members/', 'gina' );
		$this->assertSame( array( uid( 'alice' ), uid( 'bob' ), uid( 'henry' ) ), array_keys( cards( $res['body'] ) ) );
		$res = $this->get( '/berlin-members/' );
		$this->assertSame( array( uid( 'alice' ), uid( 'henry' ) ), array_keys( cards( $res['body'] ) ) );
		$this->assertStringNotContainsString( 'Bobco', $res['body'] );
	}

	public function test_author_archive_cards(): void {
		$cases = array(
			array( 'dave', '' ),
			array( 'dave', 'gina' ),
			array( 'dave', 'admin' ),
			array( 'bob', '' ),
			array( 'bob', 'sue' ),
			array( 'bob', 'gina' ),
			array( 'carol', '' ),
			array( 'carol', 'gina' ),
			array( 'carol', 'admin' ),
			array( 'alice', '' ),
			array( 'alice', 'eddie' ),
			array( 'alice', 'gina' ),
		);
		$classes = array(
			''      => 'public',
			'sue'   => 'public',
			'eddie' => 'public',
			'gina'  => 'members',
			'admin' => 'all',
		);
		foreach ( $cases as list( $author, $viewer ) ) {
			$res   = $this->get( '/author/' . $author . '/', $viewer );
			$label = "author archive of $author as " . ( $viewer ?: 'anonymous' );
			$this->assertSame( 200, $res['status'], $label );
			$this->assertStringContainsString( 'class="page-title"', $res['body'], $label );
			$expected = expected( $author, $classes[ $viewer ] );
			if ( null === $expected ) {
				$this->assertNull( author_card( $res['body'] ), "$label: no card" );
			} else {
				unset( $expected['bio'] );
				$this->assertSame( ksorted( $expected ), author_card( $res['body'] ), $label );
			}
		}
	}

	private function feed_items( string $xml ): array {
		$feed = simplexml_load_string( $xml );
		$this->assertNotFalse( $feed, 'Feed is not valid XML' );
		$out = array();
		foreach ( $feed->channel->item as $item ) {
			$acme    = $item->children( 'https://acme.example/ns/members/1.0' );
			$dc      = $item->children( 'http://purl.org/dc/elements/1.1/' );
			$details = array();
			foreach ( array( 'jobTitle', 'company', 'city' ) as $el ) {
				if ( isset( $acme->{$el} ) ) {
					$details[ $el ] = (string) $acme->{$el};
				}
			}
			$out[ (string) $item->title ] = array(
				'creator' => (string) $dc->creator,
				'acme'    => $details,
			);
		}
		return $out;
	}

	public function test_feed_contains_public_data_only(): void {
		foreach ( array( '', 'admin', 'gina' ) as $viewer ) {
			$res   = $this->get( '/feed/', $viewer );
			$label = 'feed as ' . ( $viewer ?: 'anonymous' );
			$this->assertSame( 200, $res['status'] );
			$items = $this->feed_items( $res['body'] );

			$this->assertSame( 'Alice Archer (Northwind Traders)', $items['Data meetup recap']['creator'], $label );
			$this->assertSame( array( 'jobTitle' => 'Head of Data', 'company' => 'Northwind Traders', 'city' => 'Berlin' ), $items['Data meetup recap']['acme'], $label );

			$this->assertSame( 'Bob Builder', $items['Our new rack']['creator'], "$label: members-only profile" );
			$this->assertSame( array(), $items['Our new rack']['acme'], "$label: members-only profile" );

			$this->assertSame( 'Carol Chen', $items['Design sprint notes']['creator'], "$label: private company" );
			$this->assertSame( array( 'jobTitle' => 'Product Designer' ), $items['Design sprint notes']['acme'], "$label: company private, city members-only" );

			$this->assertSame( 'Dave Dorsey', $items['Soldering 101']['creator'], "$label: hidden 1.x profile" );
			$this->assertSame( array(), $items['Soldering 101']['acme'], "$label: hidden 1.x profile" );
		}
	}

	public function test_sitemaps(): void {
		foreach ( array( '', 'admin' ) as $viewer ) {
			$res = $this->get( '/wp-sitemap-acmemembers-1.xml', $viewer );
			$this->assertSame( 200, $res['status'] );
			foreach ( array( 'alice', 'carol', 'erin', 'gina', 'henry', 'filler01', 'filler14' ) as $m ) {
				$this->assertStringContainsString( '<loc>' . home_url( '/members/' . $m . '/' ) . '</loc>', $res['body'] );
			}
			foreach ( array( 'bob', 'dave', 'frank' ) as $m ) {
				$this->assertStringNotContainsString( '/members/' . $m . '/', $res['body'], "$m's profile is not public" );
			}

			$res = $this->get( '/wp-sitemap-users-1.xml', $viewer );
			$this->assertSame( 200, $res['status'] );
			$this->assertStringContainsString( '<loc>' . home_url( '/author/alice/' ) . '</loc>', $res['body'] );
			$this->assertStringContainsString( '<loc>' . home_url( '/author/carol/' ) . '</loc>', $res['body'] );
			$this->assertStringContainsString( '<loc>' . home_url( '/author/admin/' ) . '</loc>', $res['body'], 'Authors who are not members are unaffected' );
			$this->assertStringNotContainsString( '/author/bob/', $res['body'] );
			$this->assertStringNotContainsString( '/author/dave/', $res['body'] );
		}
		$index = $this->get( '/wp-sitemap.xml' );
		$this->assertStringContainsString( 'wp-sitemap-acmemembers-1.xml', $index['body'] );
		$this->assertStringContainsString( 'wp-sitemap-users-1.xml', $index['body'] );
	}

	public function test_api_with_cookie_authentication(): void {
		$login = $this->login( 'gina' );
		$res   = $this->http( 'GET', '/wp-json/acme-members/v1/members/' . uid( 'bob' ), array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( 'Bobco Hosting', $res['json']['fields']['company'] ?? null );

		// Without the REST nonce, cookies don't authenticate: anonymous view.
		$res = $this->http( 'GET', '/wp-json/acme-members/v1/members/' . uid( 'bob' ), array( 'login' => $login ) );
		$this->assertSame( 404, $res['status'] );
		$res = $this->http( 'GET', '/wp-json/acme-members/v1/members?per_page=50' );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( '19', $res['headers']['x-wp-total'] ?? null );

		$res = $this->http( 'PATCH', '/wp-json/acme-members/v1/members/me', array( 'body' => array( 'city' => 'Nowhere' ), 'json' => true ) );
		$this->assertSame( 401, $res['status'] );

		$res = $this->http( 'GET', '/wp-json/wp/v2/search?type=acme-member&search=Bobco' );
		$this->assertSame( array(), $res['json'] );
		$res = $this->http( 'GET', '/wp-json/wp/v2/users/' . uid( 'carol' ) );
		$this->assertSame( ksorted( expected( 'carol', 'public' ) ), ksorted( (array) $res['json']['acme_profile'] ) );
	}
}

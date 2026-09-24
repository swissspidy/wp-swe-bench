<?php
/**
 * The numbers and who may see them (unchanged behaviour).
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;

class StatsTest extends WPSB\Stats\StatsTestCase {

	public function test_site_numbers_match_the_database(): void {
		$data = $this->assertFresh( uid( 'eva' ), null, 0 );
		$this->assertSame( 'site', $data['scope'] );
		$this->assertSame( 320, $data['posts']['total'] );
		$this->assertArrayHasKey( 'generated_at', $data );
		$this->assertFresh( 1, 0, 0 );
	}

	public function test_author_numbers_match_the_database(): void {
		foreach ( array( 'bruno', 'chiara', 'dmitri', 'esme', 'farid', 'hugo' ) as $login ) {
			$data = $this->assertFresh( uid( $login ), null, uid( $login ), "Own numbers of $login" );
			$this->assertSame( 'author:' . uid( $login ), $data['scope'] );
			$this->assertFresh( uid( 'eva' ), uid( $login ), uid( $login ), "Editor looking at $login" );
		}
		$this->assertSame( 0, expected( uid( 'hugo' ) )['posts']['total'] );
	}

	public function test_permissions(): void {
		$this->assertSame( 401, $this->summary( 0 )->get_status() );
		$this->assertSame( 403, $this->summary( uid( 'gina' ) )->get_status(), 'Subscribers see nothing' );
		$this->assertSame( 403, $this->summary( uid( 'bruno' ), 0 )->get_status(), 'Authors must not see the site numbers' );
		$this->assertSame( 403, $this->summary( uid( 'bruno' ), uid( 'chiara' ) )->get_status(), 'Authors must not see other authors' );
		$this->assertSame( 403, $this->summary( uid( 'farid' ), 0 )->get_status(), 'Contributors must not see the site numbers' );
		$this->assertSame( 200, $this->summary( uid( 'bruno' ), uid( 'bruno' ) )->get_status() );
		$this->assertSame( 200, $this->summary( uid( 'eva' ), uid( 'bruno' ) )->get_status() );
	}

	public function test_php_api_and_shortcodes(): void {
		$this->assertSame( expected( 0 ), numbers( acme_stats_get() ) );
		$this->assertSame( expected( uid( 'dmitri' ) ), numbers( acme_stats_get( uid( 'dmitri' ) ) ) );

		$site = expected( 0 );
		$html = do_shortcode( '[acme_stats]' );
		$this->assertStringContainsString( number_format_i18n( $site['posts']['total'] ) . ' published posts', $html );
		$this->assertStringContainsString( number_format_i18n( $site['words']['total'] ) . ' words', $html );
		$this->assertStringContainsString( number_format_i18n( $site['comments']['approved'] ) . ' comments', $html );

		$this->assertSame( '', do_shortcode( '[acme_stats scope="me"]' ), 'Logged out: nothing' );
		wp_set_current_user( uid( 'chiara' ) );
		$mine = expected( uid( 'chiara' ) );
		$html = do_shortcode( '[acme_stats scope="me"]' );
		$this->assertStringContainsString( 'data-scope="author:' . uid( 'chiara' ) . '"', $html );
		$this->assertStringContainsString( number_format_i18n( $mine['posts']['total'] ) . ' published posts', $html );
		$this->assertStringNotContainsString( 'Bruno Costa', $html );
		wp_set_current_user( uid( 'gina' ) );
		$this->assertSame( '', do_shortcode( '[acme_stats scope="me"]' ) );
	}
}

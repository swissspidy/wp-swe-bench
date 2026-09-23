<?php
/**
 * The cache works across real requests and processes (web server, WP-CLI).
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\new_request;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;

class HttpCacheTest extends WPSB\Stats\HttpStatsTestCase {

	public function test_repeat_requests_do_not_recompute(): void {
		$this->reset_computes();
		$first = $this->http_summary( uid( 'eva' ) );
		$this->assertSame( expected( 0 ), numbers( $first ) );
		$this->assertSame( array( 'site' ), $this->computes() );
		$second = $this->http_summary( uid( 'eva' ) );
		$this->assertSame( numbers( $first ), numbers( $second ) );
		$this->assertSame( $first['generated_at'], $second['generated_at'] );
		$this->assertSame( array( 'site' ), $this->computes(), 'The second request recomputed the stats' );

		$bruno = $this->http_summary( uid( 'bruno' ) );
		$this->assertSame( expected( uid( 'bruno' ) ), numbers( $bruno ) );
		$this->http_summary( uid( 'bruno' ) );
		$this->http_summary( uid( 'eva' ) );
		$this->assertSame( array( 'site', 'author:' . uid( 'bruno' ) ), $this->computes() );

		// The dashboard (widget) uses the same cache.
		$login = $this->http_login( uid( 'eva' ) );
		$page  = $this->http( 'GET', '/wp-admin/index.php', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'data-scope="site"', $page['body'] );
		$this->assertStringContainsString( number_format_i18n( $first['posts']['total'] ) . ' published posts', $page['body'] );
		$this->assertSame( array( 'site', 'author:' . uid( 'bruno' ) ), $this->computes() );
	}

	public function test_changes_made_in_other_requests_show_up(): void {
		$this->http_summary( uid( 'eva' ) );
		$this->http_summary( uid( 'chiara' ) );

		// Chiara publishes a post through the REST API (the editor).
		$login = $this->http_login( uid( 'chiara' ) );
		$r     = $this->http(
			'POST',
			'/wp-json/wp/v2/posts',
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'title'      => 'Breaking: tram line opens',
					'content'    => 'The new tram line opened today with a small ceremony.',
					'status'     => 'publish',
					'categories' => array( get_cat_ID( 'Local' ) ),
				),
			)
		);
		$this->assertSame( 201, $r['status'], $r['body'] );
		$id              = (int) $r['json']['id'];
		$this->cleanup[] = static fn() => wp_delete_post( $id, true );

		new_request();
		$this->assertSame( expected( 0 ), numbers( $this->http_summary( uid( 'eva' ) ) ) );
		$this->assertSame( expected( uid( 'chiara' ) ), numbers( $this->http_summary( uid( 'chiara' ) ) ) );

		// A reader comments; the comment waits for moderation.
		$c = $this->http( 'POST', '/wp-comments-post.php', array( 'body' => array( 'comment_post_ID' => $id, 'author' => 'Reader', 'email' => 'reader@mail.example', 'comment' => 'Finally!' ) ) );
		$this->assertContains( $c['status'], array( 200, 302 ), $c['body'] );
		new_request();
		$site = $this->http_summary( uid( 'eva' ) );
		$this->assertSame( expected( 0 ), numbers( $site ) );

		// Changes made in this process (e.g. an import script) reach the web server too.
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		$this->assertSame( expected( 0 ), numbers( $this->http_summary( uid( 'eva' ) ) ) );
		$this->assertSame( expected( uid( 'chiara' ) ), numbers( $this->http_summary( uid( 'chiara' ) ) ) );
	}

	public function test_wp_cli_uses_the_same_cache(): void {
		$this->reset_computes();
		$out = $this->wp_cli( 'acme-stats show --author=esme --format=json' );
		$this->assertSame( 0, $out['exit'], $out['stderr'] );
		$this->assertSame( expected( uid( 'esme' ) ), numbers( json_decode( $out['stdout'], true ) ) );
		$this->assertSame( array( 'author:' . uid( 'esme' ) ), $this->computes() );
		$this->assertSame( expected( uid( 'esme' ) ), numbers( $this->http_summary( uid( 'esme' ) ) ) );
		$this->assertSame( array( 'author:' . uid( 'esme' ) ), $this->computes(), 'The web request recomputed what WP-CLI had just computed' );
	}
}

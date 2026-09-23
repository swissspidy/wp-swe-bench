<?php
/**
 * wp-admin screens and the "Approve" row action respect the capability model.
 */

class AdminTest extends NewsroomCase {

	private function get( string $user, string $path ): array {
		return $this->http( 'GET', $path, array( 'login' => $this->login( $user ) ) );
	}

	/** "Approve" links in a Stories list: story ID => URL. */
	private function approve_links( string $html ): array {
		preg_match_all( '#href="([^"]*admin-post\.php\?[^"]*action=acme_newsroom_approve[^"]*)"#', $html, $m );
		$links = array();
		foreach ( $m[1] as $url ) {
			$url = html_entity_decode( $url );
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
			$links[ (int) $q['story'] ] = $url;
		}
		return $links;
	}

	private function approved_in_db( int $id ): bool {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_acme_approved'", $id ) );
		return in_array( (string) $v, array( '1', 'yes' ), true );
	}

	private function approve_url( int $id, string $nonce ): string {
		return '/wp-admin/admin-post.php?action=acme_newsroom_approve&story=' . $id . '&_wpnonce=' . $nonce;
	}

	public function test_freelancer_admin_access(): void {
		$res = $this->get( 'fiona', '/wp-admin/post-new.php?post_type=story' );
		$this->assertSame( 200, $res['status'], 'freelancers can start a story' );

		$res = $this->get( 'fiona', '/wp-admin/media-new.php' );
		$this->assertSame( 200, $res['status'], 'freelancers can upload images' );

		$res = $this->get( 'fiona', '/wp-admin/post-new.php' );
		$this->assertNotSame( 200, $res['status'], 'freelancers cannot start a blog post' );

		$res = $this->get( 'fiona', '/wp-admin/edit.php?post_type=story' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Harbor fire', $res['body'] );
		$this->assertStringContainsString( 'City budget', $res['body'] );
		foreach ( array( "Fay&#8217;s column", "Carl&#8217;s draft", 'Council vote', 'Ferry strike' ) as $other ) {
			$this->assertStringNotContainsString( $other, $res['body'], 'freelancers only see their own stories' );
		}
		$this->assertSame( array(), $this->approve_links( $res['body'] ) );
	}

	public function test_stories_list_for_the_desk(): void {
		$res = $this->get( 'erin', '/wp-admin/edit.php?post_type=story' );
		$this->assertSame( 200, $res['status'] );
		foreach ( array( 'Harbor fire', 'Council vote', "Fay&#8217;s column", 'Ferry strike' ) as $title ) {
			$this->assertStringContainsString( $title, $res['body'] );
		}
		$this->assertStringContainsString( 'Approval', $res['body'] );
		$links = $this->approve_links( $res['body'] );
		$this->assertArrayHasKey( $this->story_id( 'Harbor fire' ), $links );
		$this->assertArrayHasKey( $this->story_id( "Carl's draft" ), $links );
		$this->assertArrayNotHasKey( $this->story_id( 'City budget' ), $links, 'already approved' );
		$this->assertArrayNotHasKey( $this->story_id( 'Council vote' ), $links, 'published' );
	}

	public function test_no_approve_action_for_own_stories(): void {
		$note  = $this->story_id( "Editor's note" );
		$links = $this->approve_links( $this->get( 'erin', '/wp-admin/edit.php?post_type=story' )['body'] );
		$this->assertArrayNotHasKey( $note, $links, 'erin cannot approve her own story' );
		$links = $this->approve_links( $this->get( 'sven', '/wp-admin/edit.php?post_type=story' )['body'] );
		$this->assertArrayHasKey( $note, $links );

		// Even with a valid token for the action.
		$erin = $this->login( 'erin' );
		$this->http( 'GET', $this->approve_url( $note, $this->nonce_for( $this->user_id( 'erin' ), 'acme_newsroom_approve_' . $note, $erin['logged_in'] ) ), array( 'login' => $erin ) );
		$this->assertFalse( $this->approved_in_db( $note ) );
	}

	public function test_approve_row_action(): void {
		$harbor = $this->story_id( 'Harbor fire' );
		$sven   = $this->login( 'sven' );
		$fiona  = $this->login( 'fiona' );
		$carl   = $this->login( 'carl' );

		// Forged: wrong token, or a valid token of a user who may not approve.
		$this->http( 'GET', $this->approve_url( $harbor, 'deadbeef00' ), array( 'login' => $sven ) );
		$this->http( 'GET', $this->approve_url( $harbor, $this->nonce_for( $this->user_id( 'fiona' ), 'acme_newsroom_approve_' . $harbor, $fiona['logged_in'] ) ), array( 'login' => $fiona ) );
		$this->http( 'GET', $this->approve_url( $harbor, $this->nonce_for( $this->user_id( 'carl' ), 'acme_newsroom_approve_' . $harbor, $carl['logged_in'] ) ), array( 'login' => $carl ) );
		$this->assertFalse( $this->approved_in_db( $harbor ) );

		$links = $this->approve_links( $this->get( 'sven', '/wp-admin/edit.php?post_type=story' )['body'] );
		$this->assertArrayHasKey( $harbor, $links );
		$res = $this->http( 'GET', $links[ $harbor ], array( 'login' => $sven ) );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$this->assertTrue( $this->approved_in_db( $harbor ) );
		global $wpdb;
		$this->assertSame( (string) $this->user_id( 'sven' ), $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_acme_approved_by'", $harbor ) ) );

		// Clean up the seeded story.
		foreach ( array( '_acme_approved', '_acme_approved_by', '_acme_approved_at' ) as $key ) {
			delete_post_meta( $harbor, $key );
		}
	}

	public function test_freelance_credit_on_the_front_end(): void {
		$res = $this->http( 'GET', '/stories/ferry-strike/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( '<p class="acme-story-credit">Freelance contribution by Frank Freelance</p>', $res['body'] );
		$res = $this->http( 'GET', '/stories/council-vote/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringNotContainsString( 'acme-story-credit', $res['body'] );
	}
}

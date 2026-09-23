<?php
/**
 * Writing, submitting and approving stories over HTTP (REST + wp-admin).
 */

class WorkflowTest extends NewsroomCase {

	private function desk_id( string $name ): int {
		$term = get_term_by( 'name', $name, 'desk' );
		$this->assertNotFalse( $term );
		return (int) $term->term_id;
	}

	private function approved_in_db( int $id ): bool {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_acme_approved'", $id ) );
		return in_array( (string) $v, array( '1', 'yes' ), true );
	}

	public function test_freelancer_writes_and_submits_stories(): void {
		$city = $this->desk_id( 'City' );
		$id   = $this->new_story( 'fiona', 'draft', array( 'desk' => array( $city ) ) );
		$res  = $this->api( 'fiona', 'GET', "/wp/v2/stories/$id?context=edit" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( $city ), $res['json']['desk'], 'freelancers file their stories under a desk' );
		$this->assertSame( (int) $this->user_id( 'fiona' ), $res['json']['author'] );

		$res = $this->api( 'fiona', 'POST', "/wp/v2/stories/$id", array( 'title' => 'Updated by Fiona' ) );
		$this->assertSame( 200, $res['status'], $res['body'] );

		$res = $this->api( 'fiona', 'POST', "/wp/v2/stories/$id", array( 'status' => 'publish' ) );
		$this->assertContains( $res['status'], array( 401, 403 ), 'freelancers cannot publish' );
		$res = $this->api( 'fiona', 'POST', "/wp/v2/stories/$id", array( 'status' => 'pending' ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'pending', $res['json']['status'] );

		// Only stories: no blog posts, no pages.
		$res = $this->api( 'fiona', 'POST', '/wp/v2/posts', array( 'title' => 'A blog post', 'status' => 'draft' ) );
		$this->assertContains( $res['status'], array( 401, 403 ), 'freelancers cannot write posts: ' . $res['body'] );

		// Other people's drafts are neither editable nor listed.
		$fay = $this->story_id( "Fay's column" );
		$res = $this->api( 'fiona', 'POST', "/wp/v2/stories/$fay", array( 'title' => 'Hijacked' ) );
		$this->assertContains( $res['status'], array( 401, 403 ) );
		$res = $this->api( 'fiona', 'GET', '/wp/v2/stories?status=draft,pending&context=edit&per_page=100' );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$authors = array_unique( array_column( $res['json'], 'author' ) );
		$this->assertSame( array( $this->user_id( 'fiona' ) ), array_values( $authors ) );

		// Own unapproved drafts can be deleted.
		$res = $this->api( 'fiona', 'DELETE', "/wp/v2/stories/$id" );
		$this->assertSame( 200, $res['status'], $res['body'] );
	}

	public function test_authors_and_section_editors(): void {
		$id  = $this->new_story( 'alice' );
		$res = $this->api( 'alice', 'POST', "/wp/v2/stories/$id", array( 'status' => 'publish' ) );
		$this->assertContains( $res['status'], array( 401, 403 ), 'authors cannot publish on this site' );

		$id  = $this->new_story( 'sven', 'publish' );
		$res = $this->api( 'sven', 'GET', "/wp/v2/stories/$id?context=edit" );
		$this->assertSame( 'publish', $res['json']['status'] );
		$res = $this->api( 'sven', 'DELETE', '/wp/v2/stories/' . $this->story_id( "Carl's draft" ) );
		$this->assertContains( $res['status'], array( 401, 403 ), 'section editors cannot delete others\' stories' );
	}

	public function test_approval_locks_the_story_for_its_author(): void {
		$id = $this->new_story( 'fiona', 'pending' );

		$res = $this->api( 'fiona', 'POST', "/acme-newsroom/v1/stories/$id/approval" );
		$this->assertContains( $res['status'], array( 401, 403 ), 'freelancers cannot approve' );
		foreach ( array( 'carl', 'alice' ) as $user ) {
			$res = $this->api( $user, 'POST', "/acme-newsroom/v1/stories/$id/approval" );
			$this->assertContains( $res['status'], array( 401, 403 ), "$user cannot approve" );
		}
		$this->assertFalse( $this->approved_in_db( $id ) );

		$res = $this->api( 'erin', 'POST', "/acme-newsroom/v1/stories/$id/approval" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertTrue( $res['json']['approved'] );
		$this->assertSame( $this->user_id( 'erin' ), $res['json']['approved_by'] );
		$this->assertTrue( $this->approved_in_db( $id ) );

		// The author can no longer change or delete it.
		$res = $this->api( 'fiona', 'POST', "/wp/v2/stories/$id", array( 'title' => 'Changed after approval' ) );
		$this->assertContains( $res['status'], array( 401, 403 ), $res['body'] );
		$res = $this->api( 'fiona', 'DELETE', "/wp/v2/stories/$id" );
		$this->assertContains( $res['status'], array( 401, 403 ) );
		$this->assertNotSame( 'Changed after approval', get_post_field( 'post_title', $id, 'raw' ) );

		// The desk can, and publishes it.
		$res = $this->api( 'erin', 'POST', "/wp/v2/stories/$id", array( 'title' => 'Edited by the desk', 'status' => 'publish' ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'publish', $res['json']['status'] );
		$this->assertTrue( $this->api( 'fiona', 'GET', "/wp/v2/stories/$id" )['json']['approval']['approved'] );

		// Withdrawing the approval of an unpublished story gives it back to the author.
		$id2 = $this->new_story( 'fiona', 'pending' );
		$this->assertSame( 200, $this->api( 'sven', 'POST', "/acme-newsroom/v1/stories/$id2/approval" )['status'] );
		$this->assertContains( $this->api( 'fiona', 'POST', "/wp/v2/stories/$id2", array( 'title' => 'x' ) )['status'], array( 401, 403 ) );
		$res = $this->api( 'sven', 'DELETE', "/acme-newsroom/v1/stories/$id2/approval" );
		$this->assertSame( 200, $res['status'] );
		$this->assertFalse( $res['json']['approved'] );
		$this->assertSame( 200, $this->api( 'fiona', 'POST', "/wp/v2/stories/$id2", array( 'title' => 'Fixed the typo' ) )['status'] );
	}

	public function test_nobody_approves_their_own_story(): void {
		$id  = $this->new_story( 'erin', 'pending' );
		$res = $this->api( 'erin', 'POST', "/acme-newsroom/v1/stories/$id/approval" );
		$this->assertContains( $res['status'], array( 401, 403 ) );
		$this->assertFalse( $this->approved_in_db( $id ) );
		$this->assertSame( 200, $this->api( 'sven', 'POST', "/acme-newsroom/v1/stories/$id/approval" )['status'] );
		$this->assertTrue( $this->approved_in_db( $id ) );
	}

	public function test_desk_approval_over_rest_still_works(): void {
		$harbor = $this->story_id( 'Harbor fire' );
		$res    = $this->api( 'erin', 'GET', "/acme-newsroom/v1/stories/$harbor/approval" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( 'id' => $harbor, 'approved' => false, 'approved_by' => 0, 'approved_at' => null ), $res['json'] );

		$res = $this->api( 'sven', 'POST', "/acme-newsroom/v1/stories/$harbor/approval" );
		$this->assertSame( 200, $res['status'] );
		$this->assertTrue( $res['json']['approved'] );
		$this->assertSame( $this->user_id( 'sven' ), $res['json']['approved_by'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT/', (string) $res['json']['approved_at'] );

		$res = $this->api( 'sven', 'DELETE', "/acme-newsroom/v1/stories/$harbor/approval" );
		$this->assertSame( 200, $res['status'] );
		$this->assertFalse( $this->approved_in_db( $harbor ) );

		// Legacy (1.x) approvals are still approvals.
		$tram = $this->story_id( 'Tram extension' );
		$res  = $this->api( 'erin', 'GET', '/wp/v2/stories/' . $tram . '?context=edit' );
		$this->assertTrue( $res['json']['approval']['approved'] );
	}
}

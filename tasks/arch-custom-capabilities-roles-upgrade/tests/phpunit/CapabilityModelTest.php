<?php
/**
 * Roles, users and the capability matrix after the 3.0 upgrade of the seeded site.
 */

class CapabilityModelTest extends NewsroomCase {

	private function assertStoryCaps( string $role, array $expected ): void {
		$caps = $this->role_caps( $role );
		$this->assertNotNull( $caps, "role $role missing" );
		$have = array_values( array_intersect( self::STORY_CAPS, $caps ) );
		sort( $have );
		sort( $expected );
		$this->assertSame( $expected, $have, "story capabilities of $role" );
	}

	public function test_existing_roles_get_story_capabilities_matching_their_post_capabilities(): void {
		$this->assertStoryCaps( 'administrator', self::STORY_CAPS );
		$this->assertStoryCaps( 'editor', self::STORY_CAPS );
		// Custom role without delete/private capabilities.
		$this->assertStoryCaps( 'section_editor', array( 'edit_stories', 'edit_others_stories', 'edit_published_stories', 'publish_stories', 'approve_stories' ) );
		// Authors can't publish on this site.
		$this->assertStoryCaps( 'author', array( 'edit_stories', 'edit_published_stories', 'delete_stories', 'delete_published_stories' ) );
		$this->assertStoryCaps( 'contributor', array( 'edit_stories', 'delete_stories' ) );
		$this->assertStoryCaps( 'subscriber', array() );
		$this->assertStoryCaps( 'newsletter_manager', array() );
	}

	public function test_site_owner_customizations_are_kept(): void {
		$this->assertContains( 'manage_newsletter', $this->role_caps( 'editor' ) );
		$this->assertNotContains( 'publish_posts', $this->role_caps( 'author' ) );
		$this->assertContains( 'moderate_comments', $this->role_caps( 'section_editor' ) );
		$this->assertNotContains( 'delete_others_posts', $this->role_caps( 'section_editor' ) );
		$this->assertSame( array( 'manage_newsletter', 'read' ), $this->role_caps( 'newsletter_manager' ) );
		$roles = $this->roles();
		$this->assertSame( 'Section Editor', $roles['section_editor']['name'] );
		$this->assertSame( 'Newsletter Manager', $roles['newsletter_manager']['name'] );
	}

	public function test_freelancer_role(): void {
		$roles = $this->roles();
		$this->assertArrayHasKey( 'acme_freelancer', $roles );
		$this->assertSame( 'Freelancer', $roles['acme_freelancer']['name'] );
		$this->assertSame( array( 'delete_stories', 'edit_stories', 'read', 'upload_files' ), $this->role_caps( 'acme_freelancer' ) );
	}

	public function test_flagged_contributors_became_freelancers(): void {
		$this->assertSame( array( 'acme_freelancer' ), $this->user_roles( 'fiona' ) );
		$this->assertSame( array( 'acme_freelancer' ), $this->user_roles( 'frank' ), '1.x flag "yes"' );
		$this->assertSame( array( 'acme_freelancer', 'newsletter_manager' ), $this->user_roles( 'fay' ), 'other roles are kept' );
		$this->assertSame( array( 'author' ), $this->user_roles( 'felix' ), 'flagged staff keep their role' );
		$this->assertSame( array( 'contributor' ), $this->user_roles( 'nora' ), 'flag "0" = not a freelancer' );
		$this->assertSame( array( 'contributor' ), $this->user_roles( 'carl' ) );
		$this->assertSame( array( 'section_editor' ), $this->user_roles( 'sven' ) );

		global $wpdb;
		$this->assertSame( 0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'acme_freelancer'" ), 'the old flag must be removed' );

		$this->assertSame(
			array( true, true, true, false, false, false ),
			$this->cli_json( 'array_map( static fn( $l ) => \Acme\Newsroom\is_freelancer( get_user_by( "login", $l )->ID ), array( "fiona", "frank", "fay", "felix", "nora", "erin" ) )' )
		);
	}

	public function test_primitive_capabilities(): void {
		$this->assertUserCan(
			array(
				array( 'fiona', 'edit_stories', null, true ),
				array( 'fiona', 'upload_files', null, true ),
				array( 'fiona', 'edit_posts', null, false ),
				array( 'fiona', 'publish_stories', null, false ),
				array( 'fiona', 'edit_others_stories', null, false ),
				array( 'fiona', 'approve_stories', null, false ),
				array( 'alice', 'publish_stories', null, false ),
				array( 'alice', 'edit_published_stories', null, true ),
				array( 'sven', 'approve_stories', null, true ),
				array( 'sven', 'delete_others_stories', null, false ),
				array( 'erin', 'approve_stories', null, true ),
				array( 'carl', 'edit_stories', null, true ),
				array( 'carl', 'approve_stories', null, false ),
				array( 'sam', 'edit_stories', null, false ),
				array( 'admin', 'approve_stories', null, true ),
			)
		);
	}

	public function test_meta_capabilities_per_story(): void {
		$harbor  = $this->story_id( 'Harbor fire' );        // fiona, draft
		$budget  = $this->story_id( 'City budget' );        // fiona, pending, approved
		$ferry   = $this->story_id( 'Ferry strike' );       // frank, published, approved (1.x)
		$tram    = $this->story_id( 'Tram extension' );     // frank, draft, approved (1.x "yes")
		$council = $this->story_id( 'Council vote' );       // erin, published
		$note    = $this->story_id( "Editor's note" );      // erin, draft
		$fay     = $this->story_id( "Fay's column" );       // fay, draft
		$carl    = $this->story_id( "Carl's draft" );       // carl, draft
		$felix   = $this->story_id( 'Felix feature' );      // felix, draft

		$this->assertUserCan(
			array(
				// Freelancers: own drafts until approved.
				array( 'fiona', 'edit_post', $harbor, true ),
				array( 'fiona', 'delete_post', $harbor, true ),
				array( 'fiona', 'edit_post', $budget, false ),
				array( 'fiona', 'delete_post', $budget, false ),
				array( 'frank', 'edit_post', $tram, false ),
				array( 'frank', 'delete_post', $tram, false ),
				array( 'frank', 'edit_post', $ferry, false ),
				array( 'fiona', 'edit_post', $fay, false ),
				array( 'fiona', 'read_post', $fay, false ),
				array( 'fiona', 'edit_post', $council, false ),
				array( 'fay', 'edit_post', $fay, true ),
				array( 'fiona', 'edit_story', $harbor, true ),
				array( 'fiona', 'edit_story', $budget, false ),
				array( 'fiona', 'delete_story', $budget, false ),
				array( 'frank', 'delete_story', $tram, false ),
				array( 'erin', 'delete_story', $harbor, true ),
				// Contributors and authors.
				array( 'carl', 'edit_post', $carl, true ),
				array( 'carl', 'edit_post', $harbor, false ),
				array( 'felix', 'edit_post', $felix, true ),
				array( 'alice', 'edit_post', $harbor, false ),
				// The desk.
				array( 'erin', 'edit_post', $budget, true ),
				array( 'erin', 'edit_post', $tram, true ),
				array( 'erin', 'delete_post', $harbor, true ),
				array( 'sven', 'edit_post', $harbor, true ),
				array( 'sven', 'edit_post', $budget, true ),
				array( 'sven', 'delete_post', $carl, false ),
				// Approving.
				array( 'erin', 'approve_story', $harbor, true ),
				array( 'erin', 'approve_story', $fay, true ),
				array( 'erin', 'approve_story', $note, false ),
				array( 'sven', 'approve_story', $note, true ),
				array( 'sven', 'approve_story', $carl, true ),
				array( 'admin', 'approve_story', $harbor, true ),
				array( 'alice', 'approve_story', $harbor, false ),
				array( 'carl', 'approve_story', $harbor, false ),
				array( 'fiona', 'approve_story', $harbor, false ),
				array( 'fiona', 'approve_story', $fay, false ),
				array( 'sam', 'approve_story', $harbor, false ),
			)
		);
	}

	public function test_story_capabilities_are_independent_of_post_capabilities(): void {
		$poster = $this->create_user( 'subscriber' );
		$writer = $this->create_user( 'subscriber' );
		$this->created_users[] = $poster;
		$this->created_users[] = $writer;
		$p = new WP_User( $poster );
		$p->add_cap( 'edit_posts' );
		$w = new WP_User( $writer );
		$w->add_cap( 'edit_stories' );

		$poster_login = get_userdata( $poster )->user_login;
		$writer_login = get_userdata( $writer )->user_login;
		$harbor       = $this->story_id( 'Harbor fire' );
		$this->assertUserCan(
			array(
				array( $poster_login, 'edit_stories', null, false ),
				array( $writer_login, 'edit_stories', null, true ),
				array( $writer_login, 'edit_posts', null, false ),
				array( $poster_login, 'edit_post', $harbor, false ),
			)
		);

		$res = $this->http( 'POST', '/wp-json/wp/v2/stories', array( 'login' => $this->http_login( $poster ), 'rest_nonce' => true, 'json' => true, 'body' => array( 'title' => 'By a post writer' ) ) );
		$this->assertContains( $res['status'], array( 401, 403 ), 'edit_posts alone must not allow writing stories' );
		$res = $this->http( 'POST', '/wp-json/wp/v2/stories', array( 'login' => $this->http_login( $writer ), 'rest_nonce' => true, 'json' => true, 'body' => array( 'title' => 'By a story writer' ) ) );
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->created_posts[] = (int) $res['json']['id'];
	}
}

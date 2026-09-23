<?php
/**
 * The 3.0 upgrade: automatic, idempotent, respects the site owner's role changes.
 */

class UpgradeTest extends NewsroomCase {

	/** Roles + user roles as a comparable snapshot. */
	private function state(): array {
		$roles = $this->roles();
		ksort( $roles );
		foreach ( $roles as &$role ) {
			$caps = array_keys( array_filter( (array) $role['capabilities'] ) );
			sort( $caps );
			$role = array( $role['name'], $caps );
		}
		unset( $role );
		$users = array();
		foreach ( array( 'admin', 'erin', 'sven', 'alice', 'carl', 'fiona', 'frank', 'fay', 'felix', 'nora', 'sam' ) as $login ) {
			$users[ $login ] = $this->user_roles( $login );
		}
		return array(
			'roles' => $roles,
			'users' => $users,
		);
	}

	public function test_seeded_site_was_upgraded(): void {
		$this->assertSame( '3.0.0', $this->raw_option( 'acme_newsroom_version' ) );
		$this->assertNotNull( $this->role_caps( 'acme_freelancer' ) );
	}

	public function test_first_front_end_request_after_deploy_upgrades(): void {
		$this->restore_pristine_roles();
		$this->assertNull( $this->role_caps( 'acme_freelancer' ) );
		$this->assertSame( array( 'contributor' ), $this->user_roles( 'frank' ) );

		// A visitor is the first to hit the site after the deploy.
		$res = $this->http( 'GET', '/stories/ferry-strike/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Freelance contribution by Frank Freelance', $res['body'] );

		wp_cache_flush();
		$this->assertSame( '3.0.0', $this->raw_option( 'acme_newsroom_version' ) );
		$this->assertSame( array( 'acme_freelancer' ), $this->user_roles( 'frank' ) );
		$this->assertSame( array( 'delete_stories', 'edit_stories', 'read', 'upload_files' ), $this->role_caps( 'acme_freelancer' ) );
		$this->assertContains( 'approve_stories', $this->role_caps( 'section_editor' ) );
	}

	public function test_running_the_upgrade_again_changes_nothing(): void {
		$this->restore_pristine_roles();
		$this->fresh_request();
		$first = $this->state();
		$this->assertContains( 'publish_stories', $first['roles']['editor'][1] );

		$this->set_raw_option( 'acme_newsroom_version', '2.3.0' );
		$this->fresh_request();
		$this->fresh_request();
		$this->assertSame( $first, $this->state() );
		$this->assertSame( '3.0.0', $this->raw_option( 'acme_newsroom_version' ) );
	}

	public function test_later_changes_by_the_site_owner_survive_another_upgrade_run(): void {
		// After the upgrade the site owner adjusts things …
		$this->cli_json(
			'( function () {
				get_role( "section_editor" )->remove_cap( "approve_stories" );
				get_role( "author" )->add_cap( "publish_stories" );
				if ( get_role( "acme_freelancer" ) ) {
					get_role( "acme_freelancer" )->add_cap( "read_private_stories" );
				}
				get_role( "contributor" )->remove_cap( "delete_stories" );
				$fiona = get_user_by( "login", "fiona" );
				$fiona->set_role( "author" );
				$carl = get_user_by( "login", "carl" );
				$carl->set_role( "acme_freelancer" );
				return true;
			} )()'
		);
		$customized = $this->state();
		$this->assertSame( array( 'author' ), $customized['users']['fiona'] );

		// … and later a deploy script resets the version, so the upgrade runs again.
		$this->set_raw_option( 'acme_newsroom_version', '2.3.0' );
		$this->fresh_request();
		$this->assertSame( $customized, $this->state() );
		$this->assertSame( '3.0.0', $this->raw_option( 'acme_newsroom_version' ) );

		$this->assertUserCan(
			array(
				array( 'sven', 'approve_story', $this->story_id( 'Harbor fire' ), false ),
				array( 'alice', 'publish_stories', null, true ),
				array( 'carl', 'edit_posts', null, false ),
			)
		);
	}

	public function test_upgrade_follows_the_roles_as_they_are_at_upgrade_time(): void {
		$this->restore_pristine_roles();
		// The owner changed some roles before the update was deployed.
		$roles = $this->roles();
		unset( $roles['contributor']['capabilities']['delete_posts'] );
		$roles['contributor']['capabilities']['upload_files'] = true;
		$roles['subscriber']['capabilities']['edit_posts']    = true;
		$roles['editor']['capabilities']['delete_others_posts'] = false;
		$this->set_raw_option( 'wp_user_roles', $roles );

		$this->fresh_request();

		$contributor = $this->role_caps( 'contributor' );
		$this->assertContains( 'edit_stories', $contributor );
		$this->assertNotContains( 'delete_stories', $contributor );
		$this->assertContains( 'upload_files', $contributor );
		$this->assertContains( 'edit_stories', $this->role_caps( 'subscriber' ) );
		$editor = $this->role_caps( 'editor' );
		$this->assertNotContains( 'delete_others_stories', $editor );
		$this->assertContains( 'approve_stories', $editor );
		$this->assertContains( 'manage_newsletter', $editor );
	}
}

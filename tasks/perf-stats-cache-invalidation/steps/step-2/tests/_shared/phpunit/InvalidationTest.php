<?php
/**
 * Every change to what the stats count shows up in the next load; nothing else throws the cache away.
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\new_request;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;

class InvalidationTest extends WPSB\Stats\StatsTestCase {

	/** Warm the caches of the site, bruno and chiara. */
	private function warm(): void {
		$this->load( uid( 'eva' ) );
		$this->load( uid( 'bruno' ) );
		$this->load( uid( 'chiara' ) );
	}

	/** All three scopes must match the database (optionally after a simulated new request). */
	private function assertAllFresh( string $what, bool $new_request = false ): void {
		if ( $new_request ) {
			new_request();
		}
		$this->assertFresh( uid( 'eva' ), null, 0, "Site numbers after: $what" );
		$this->assertFresh( uid( 'bruno' ), null, uid( 'bruno' ), "Bruno's numbers after: $what" );
		$this->assertFresh( uid( 'chiara' ), null, uid( 'chiara' ), "Chiara's numbers after: $what" );
	}

	private function bruno_post( int $n = 0 ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_author = %d AND comment_count > 0 ORDER BY ID LIMIT %d, 1", uid( 'bruno' ), $n ) );
	}

	public function test_publishing(): void {
		$this->warm();
		$id = $this->create_post(
			array(
				'post_author'   => uid( 'bruno' ),
				'post_content'  => '<p>One two three four five six seven.</p>',
				'post_date'     => '2026-02-28 23:30:00',
				'post_category' => array( get_cat_ID( 'Science' ) ),
			)
		);
		$this->assertAllFresh( 'publishing a post' );
		$this->assertAllFresh( 'publishing a post (next request)', true );

		// A draft that gets published later.
		$draft = $this->create_post( array( 'post_author' => uid( 'chiara' ), 'post_status' => 'draft', 'post_content' => 'Short.' ) );
		$this->assertAllFresh( 'saving a draft' );
		wp_publish_post( $draft );
		$this->assertAllFresh( 'publishing a draft' );
	}

	public function test_scheduled_post_going_live(): void {
		global $wpdb;
		$future = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'future' AND post_type = 'post' ORDER BY ID LIMIT 1" );
		$wpdb->update( $wpdb->posts, array( 'post_author' => uid( 'bruno' ) ), array( 'ID' => $future ) );
		clean_post_cache( $future );
		$this->warm();
		wp_publish_post( $future ); // What cron does when the date is reached.
		$this->assertAllFresh( 'a scheduled post going live' );
	}

	public function test_editing_published_posts(): void {
		$id = $this->bruno_post();
		$this->warm();
		wp_update_post( array( 'ID' => $id, 'post_content' => get_post_field( 'post_content', $id ) . ' <p>and ten more words to count here right now please</p>' ) );
		$this->assertAllFresh( 'editing the content' );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'A much better headline' ) );
		$this->assertAllFresh( 'changing the title', true );

		wp_update_post( array( 'ID' => $id, 'post_date' => '2019-01-15 10:00:00', 'post_date_gmt' => '2019-01-15 09:00:00' ) );
		$this->assertAllFresh( 'changing the date' );

		wp_update_post( array( 'ID' => $id, 'post_author' => uid( 'chiara' ) ) );
		$this->assertAllFresh( 'changing the author' );
	}

	public function test_unpublishing_trashing_deleting(): void {
		$a = $this->bruno_post( 0 );
		$b = $this->bruno_post( 1 );
		$c = $this->bruno_post( 2 );
		$this->warm();
		wp_update_post( array( 'ID' => $a, 'post_status' => 'draft' ) );
		$this->assertAllFresh( 'unpublishing' );
		wp_update_post( array( 'ID' => $a, 'post_status' => 'private' ) );
		$this->assertAllFresh( 'making it private' );
		wp_trash_post( $b );
		$this->assertAllFresh( 'trashing' );
		wp_untrash_post( $b );
		wp_publish_post( $b );
		$this->assertAllFresh( 'restoring and republishing', true );
		wp_delete_post( $c, true );
		$this->assertAllFresh( 'deleting without trash' );
	}

	public function test_comments(): void {
		$post = $this->bruno_post();
		$this->warm();
		$approved = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_content' => 'Great', 'comment_approved' => 1 ) );
		$this->assertAllFresh( 'a new approved comment' );
		$pending = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_content' => 'Hmm', 'comment_approved' => 0 ) );
		$this->assertAllFresh( 'a comment awaiting moderation' );
		wp_set_comment_status( $pending, 'approve' );
		$this->assertAllFresh( 'approving a comment', true );
		wp_set_comment_status( $approved, 'hold' );
		$this->assertAllFresh( 'unapproving a comment' );
		wp_spam_comment( $approved );
		$this->assertAllFresh( 'marking as spam' );
		wp_unspam_comment( $approved );
		$this->assertAllFresh( 'not spam after all' );
		wp_trash_comment( $pending );
		$this->assertAllFresh( 'trashing a comment' );
		wp_delete_comment( $approved, true );
		$this->assertAllFresh( 'deleting a comment', true );
	}

	public function test_categories(): void {
		$post = $this->bruno_post();
		$this->warm();
		wp_set_post_categories( $post, array( get_cat_ID( 'Culture' ), get_cat_ID( 'Local' ) ) );
		$this->assertAllFresh( 'changing the categories of a post' );
		wp_update_term( get_cat_ID( 'Sport' ), 'category', array( 'name' => 'Sports & Games' ) );
		$this->assertAllFresh( 'renaming a category' );
		$new = wp_insert_term( 'Weather', 'category' );
		wp_set_post_categories( $this->bruno_post( 1 ), array( $new['term_id'] ), true );
		$this->assertAllFresh( 'adding a new category to a post', true );
		wp_delete_term( get_cat_ID( 'Politics' ), 'category' );
		$this->assertAllFresh( 'deleting a category' );
	}

	public function test_authors(): void {
		$this->warm();
		wp_update_user( array( 'ID' => uid( 'bruno' ), 'display_name' => 'Bruno C.' ) );
		$this->assertAllFresh( 'renaming an author' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( uid( 'dmitri' ), uid( 'chiara' ) );
		$this->assertAllFresh( 'deleting an author and giving the posts to another' );
	}

	public function test_settings(): void {
		$this->warm();
		update_option( 'acme_stats_settings', array( 'top_posts' => 3, 'public_totals' => true ) );
		$data = $this->assertFresh( uid( 'eva' ), null, 0 );
		$this->assertCount( 3, $data['comments']['top_posts'] );
	}

	public function test_things_that_do_not_count_keep_the_cache(): void {
		$published = $this->bruno_post();
		$draft     = $this->create_post( array( 'post_author' => uid( 'bruno' ), 'post_status' => 'draft', 'post_content' => 'Draft' ) );
		$this->warm();
		new_request();

		// Editors work on drafts all day.
		wp_update_post( array( 'ID' => $draft, 'post_content' => 'Draft, longer now', 'post_title' => 'Draft title' ) );
		wp_set_post_categories( $draft, array( get_cat_ID( 'Business' ) ) );
		$this->create_post( array( 'post_author' => uid( 'chiara' ), 'post_status' => 'draft' ) );
		$this->create_post( array( 'post_author' => uid( 'chiara' ), 'post_status' => 'pending' ) );
		wp_insert_comment( array( 'comment_post_ID' => $draft, 'comment_content' => 'Internal note', 'comment_approved' => 0 ) );
		// …and keep published posts open in the editor: edit locks, autosaves, custom fields.
		wp_set_current_user( uid( 'bruno' ) );
		require_once ABSPATH . 'wp-admin/includes/post.php';
		wp_set_post_lock( $published );
		wp_create_post_autosave(
			array(
				'post_ID'      => $published,
				'post_title'   => 'Autosaved title',
				'post_content' => 'Autosaved content with more words',
				'post_excerpt' => '',
			)
		);
		update_post_meta( $published, 'subtitle', 'A custom field' );
		wp_set_current_user( 0 );
		// Pages are not counted.
		$this->create_post( array( 'post_type' => 'page', 'post_content' => 'A new page with words' ) );
		update_option( 'blogdescription', 'Changed tagline' );
		new_request();

		foreach ( array( 'eva', 'bruno', 'chiara' ) as $login ) {
			list( $data, $computes ) = $this->load( uid( $login ) );
			$this->assertSame( array(), $computes, "Numbers of $login were recomputed although nothing they count changed" );
		}
		$this->assertAllFresh( 'drafts, autosaves, edit locks, pages' );
	}
}

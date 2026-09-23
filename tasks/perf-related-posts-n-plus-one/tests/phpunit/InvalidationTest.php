<?php
/**
 * Lists must reflect changes immediately, in the same request and in later ones.
 */

use function WPSB\Related\list_html;
use function WPSB\Related\section_ids;
use function WPSB\Related\seed_id;

class InvalidationTest extends WPSB\TestCase {

	/** A new published post sharing all tags and categories of $source (scores highest). */
	private function twin_of( int $source, array $args = array() ): int {
		return $this->create_post(
			array_merge(
				array(
					'post_title'    => 'Twin of ' . $source,
					'post_date'     => '2026-02-01 10:00:00',
					'post_date_gmt' => '2026-02-01 10:00:00',
					'post_category' => wp_get_post_categories( $source ),
					'tags_input'    => wp_get_post_tags( $source, array( 'fields' => 'ids' ) ),
					'post_author'   => 2,
				),
				$args
			)
		);
	}

	/** Render the lists of an "archive" of posts in one go, like a loop would. */
	private function loop_lists( array $ids ): array {
		$q   = new WP_Query(
			array(
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
			)
		);
		$out = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$out[ get_the_ID() ] = section_ids( list_html( get_the_ID() ) );
		}
		wp_reset_postdata();
		return $out;
	}

	public function test_editor_picks_changes_apply_immediately(): void {
		$source = seed_id( 17 );
		acme_related_get_ids( $source );
		delete_post_meta( $source, '_acme_related_manual' );
		$base   = acme_related_get_ids( $source );
		$pick_a = seed_id( 150 );
		$pick_b = seed_id( 151 );

		update_post_meta( $source, '_acme_related_manual', array( $pick_a, $pick_b ) );
		$this->assertSame( array( $pick_a, $pick_b ), array_slice( acme_related_get_ids( $source ), 0, 2 ) );
		$this->assertSame( array( $pick_a, $pick_b ), array_slice( section_ids( list_html( $source ) ), 0, 2 ) );

		// Legacy (1.x) comma separated format written by an old import script.
		update_post_meta( $source, '_acme_related_manual', $pick_b . ', ' . $pick_a );
		$this->assertSame( array( $pick_b, $pick_a ), array_slice( acme_related_get_ids( $source ), 0, 2 ) );

		delete_post_meta( $source, '_acme_related_manual' );
		$this->assertSame( $base, acme_related_get_ids( $source ) );
		$this->assertSame( $base, section_ids( list_html( $source ) ) );
	}

	public function test_editor_picks_saved_through_rest_apply(): void {
		$source = seed_id( 24 );
		$pick   = seed_id( 199 ); // Flagged "never show", but editor picks win.
		acme_related_get_ids( $source );
		$this->login_as( 'administrator' );
		$res = $this->rest( 'POST', '/wp/v2/posts/' . $source, array(), array( 'meta' => array( '_acme_related_manual' => array( $pick ) ) ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		wp_set_current_user( 0 );
		$this->assertSame( $pick, acme_related_get_ids( $source )[0] );
		$this->assertSame( $pick, $this->rest_data( $this->rest( 'GET', '/wp/v2/posts/' . $source ) )['acme_related'][0]['id'] );
	}

	public function test_new_post_with_shared_tags_appears(): void {
		$source = seed_id( 59 );
		$others = array( seed_id( 60 ), seed_id( 61 ), $source, seed_id( 62 ) );
		$before = $this->loop_lists( $others );

		$twin = $this->twin_of( $source );
		$this->assertContains( $twin, acme_related_get_ids( $source ), 'A new post sharing all terms must be related' );
		$after = $this->loop_lists( $others );
		$this->assertContains( $twin, $after[ $source ] );
		$this->assertNotSame( $before[ $source ], $after[ $source ] );
	}

	public function test_tag_changes_apply_immediately(): void {
		$source = seed_id( 66 );
		$other  = seed_id( 1 );
		$ids    = acme_related_get_ids( $source, 12 );
		$this->assertNotContains( $other, array_slice( $ids, 0, 3 ) );

		// Give the other post all terms of the source and make it newest among equals.
		wp_set_post_terms( $other, wp_get_post_tags( $source, array( 'fields' => 'ids' ) ), 'post_tag', false );
		wp_set_post_terms( $other, wp_get_post_categories( $source ), 'category', false );
		$ids = acme_related_get_ids( $source, 12 );
		$this->assertContains( $other, $ids );

		// And take them away again.
		wp_set_post_terms( $other, array(), 'post_tag', false );
		wp_set_post_terms( $other, array( get_cat_ID( 'Sponsored' ) ), 'category', false );
		$this->assertNotContains( $other, acme_related_get_ids( $source, 12 ) );

		// Removing the source's own tags changes its list too.
		$first = acme_related_get_ids( $source, 12 );
		wp_set_post_terms( $source, array(), 'post_tag', false );
		$this->assertNotSame( $first, acme_related_get_ids( $source, 12 ) );
	}

	public function test_unpublished_and_trashed_posts_disappear(): void {
		$source = seed_id( 73 );
		$ids    = acme_related_get_ids( $source );
		$gone   = $ids[ count( $ids ) - 1 ];
		$this->assertContains( $gone, section_ids( list_html( $source ) ) );

		wp_update_post( array( 'ID' => $gone, 'post_status' => 'draft' ) );
		$this->assertNotContains( $gone, acme_related_get_ids( $source ) );
		$this->assertNotContains( $gone, section_ids( list_html( $source ) ) );

		wp_update_post( array( 'ID' => $gone, 'post_status' => 'publish' ) );
		$this->assertContains( $gone, acme_related_get_ids( $source ) );

		wp_trash_post( $gone );
		$this->assertNotContains( $gone, section_ids( list_html( $source ) ) );
		wp_untrash_post( $gone );
		wp_update_post( array( 'ID' => $gone, 'post_status' => 'publish' ) );
		$this->assertContains( $gone, section_ids( list_html( $source ) ) );

		wp_update_post( array( 'ID' => $gone, 'post_password' => 'members' ) );
		$this->assertNotContains( $gone, acme_related_get_ids( $source ) );
	}

	public function test_never_show_flag_applies_immediately(): void {
		$source = seed_id( 80 );
		$picks  = Acme\Related\Engine::parse_manual( get_post_meta( $source, '_acme_related_manual', true ) );
		$ids    = array_values( array_diff( acme_related_get_ids( $source ), $picks ) );
		$victim = end( $ids );
		update_post_meta( $victim, '_acme_related_exclude', 'yes' );
		$this->assertNotContains( $victim, acme_related_get_ids( $source ) );
		update_post_meta( $victim, '_acme_related_exclude', '0' );
		$this->assertContains( $victim, acme_related_get_ids( $source ) );
	}

	public function test_item_details_are_current(): void {
		$source = seed_id( 94 );
		$html   = list_html( $source );
		$ids    = section_ids( $html );
		$item   = $ids[ count( $ids ) - 1 ];

		wp_update_post( array( 'ID' => $item, 'post_title' => 'Completely new headline' ) );
		$this->assertStringContainsString( '>Completely new headline</a>', list_html( $source ) );

		$author = (int) get_post_field( 'post_author', $item );
		wp_update_user( array( 'ID' => $author, 'display_name' => 'Renamed Author' ) );
		$this->assertStringContainsString( '>Renamed Author</a>', list_html( $source ) );

		$cat = get_category_by_slug( acme_related_get_items( $source )[ count( $ids ) - 1 ]['category']['slug'] );
		wp_update_term( $cat->term_id, 'category', array( 'name' => 'Renamed Section' ) );
		$this->assertStringContainsString( '>Renamed Section</a>', list_html( $source ) );

		$image = (int) $GLOBALS['wpdb']->get_var( "SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'attachment' AND post_title = 'Journal photo 12'" );
		set_post_thumbnail( $item, $image );
		update_post_meta( $image, '_wp_attachment_image_alt', 'A brand new alt text' );
		$this->assertStringContainsString( 'journal-photo-12-150x150.png', list_html( $source ) );
		$this->assertStringContainsString( 'alt="A brand new alt text"', list_html( $source ) );

		update_post_meta( $item, '_acme_editors_pick', '1' );
		$this->assertStringContainsString( 'Editor&#039;s pick', list_html( $source ) );
		$items = acme_related_get_items( $source );
		$this->assertSame( "Editor's pick", end( $items )['badge'] ?? null );
	}

	public function test_view_counts_are_current(): void {
		$source = seed_id( 101 );
		$items  = acme_related_get_items( $source );
		$item   = $items[0];
		for ( $i = 0; $i < 3; $i++ ) {
			$res = $this->rest( 'POST', '/acme-related/v1/views/' . $item['id'] );
			$this->assertSame( 200, $res->get_status() );
		}
		$this->assertSame( $item['views'] + 3, $res->get_data()['views'] );
		$this->assertSame( $item['views'] + 3, acme_related_get_items( $source )[0]['views'] );
		$this->assertStringContainsString( number_format_i18n( $item['views'] + 3 ) . ' views', list_html( $source ) );
	}

	public function test_hidden_and_display_settings_still_respected(): void {
		$main   = $GLOBALS['wp_the_query'];
		$hidden = seed_id( 50 );
		$this->go_single( $hidden );
		$this->assertStringNotContainsString( 'acme-related', apply_filters( 'the_content', get_post( $hidden )->post_content ) );
		$visible = seed_id( 52 );
		$this->go_single( $visible );
		$this->assertStringContainsString( 'class="acme-related"', apply_filters( 'the_content', get_post( $visible )->post_content ) );
		WPSB\Related\set_settings( array( 'display' => 'none' ) );
		$this->assertStringNotContainsString( 'class="acme-related"', apply_filters( 'the_content', get_post( $visible )->post_content ) );
		$GLOBALS['wp_the_query'] = $main;
		$GLOBALS['wp_query']     = $main;
	}

	private function go_single( int $id ): void {
		$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		$GLOBALS['wp_query']->the_post();
	}
}

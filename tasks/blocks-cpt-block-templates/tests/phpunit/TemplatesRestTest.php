<?php
/**
 * The plugin's templates and template part as seen by the Site Editor (REST API, in-process).
 */

use const WPSB\Courses\THEME;

class TemplatesRestTest extends WPSB\TestCase {

	private function by_id( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$out[ $item['id'] ] = $item;
		}
		return $out;
	}

	private function blocks_in( string $content ): array {
		$names = array();
		$walk  = static function ( $blocks ) use ( &$walk, &$names ) {
			foreach ( $blocks as $b ) {
				if ( $b['blockName'] ) {
					$names[] = $b['blockName'];
				}
				$walk( $b['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );
		return $names;
	}

	public function test_templates_are_listed_as_plugin_templates(): void {
		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/wp/v2/templates', array( 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status() );
		$all = $this->by_id( $this->rest_data( $res ) );

		foreach ( array( 'single-acme_course', 'archive-acme_course', 'taxonomy-acme_course_topic' ) as $slug ) {
			$id = THEME . '//' . $slug;
			$this->assertArrayHasKey( $id, $all, "Template $id must be available to the Site Editor" );
			$t = $all[ $id ];
			$this->assertSame( 'plugin', $t['original_source'], "$id original_source" );
			$this->assertSame( 'Acme Courses', $t['author_text'], "$id must be attributed to Acme Courses" );
			$this->assertNotSame( '', trim( $t['content']['raw'] ) );
			$title = is_array( $t['title'] ) ? ( $t['title']['raw'] ?? $t['title']['rendered'] ) : $t['title'];
			$this->assertNotSame( '', trim( (string) $title ) );
			$names = $this->blocks_in( $t['content']['raw'] );
			$this->assertContains( 'core/template-part', $names, "$id must use the theme header/footer" );
		}

		$archive = $this->blocks_in( $all[ THEME . '//archive-acme_course' ]['content']['raw'] );
		$this->assertContains( 'acme-courses/course-price', $archive );
		$this->assertContains( 'acme-courses/course-duration', $archive );
		$this->assertNotContains( 'core/missing', $archive );

		// Nothing is listed twice.
		$ids = array_map( static fn( $t ) => $t['id'], $this->rest_data( $res ) );
		$this->assertSame( array_values( array_unique( $ids ) ), array_values( $ids ) );
	}

	public function test_single_template_is_resolved_by_id(): void {
		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/wp/v2/templates/' . THEME . '//single-acme_course', array( 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status() );
		$data  = $this->rest_data( $res );
		$names = $this->blocks_in( $data['content']['raw'] );
		$this->assertContains( 'core/post-title', $names );
		$this->assertContains( 'core/post-content', $names );
		$this->assertContains( 'core/template-part', $names );
		$parts = array();
		foreach ( parse_blocks( $data['content']['raw'] ) as $b ) {
			$stack = array( $b );
			while ( $stack ) {
				$cur = array_pop( $stack );
				if ( 'core/template-part' === $cur['blockName'] ) {
					$parts[] = $cur['attrs']['slug'] ?? '';
				}
				array_push( $stack, ...$cur['innerBlocks'] );
			}
		}
		$this->assertContains( 'course-summary', $parts, 'The single course template must include the course-summary part' );
	}

	public function test_course_summary_part_is_available(): void {
		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/wp/v2/template-parts', array( 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status() );
		$all = $this->by_id( $this->rest_data( $res ) );
		$id  = THEME . '//course-summary';
		$this->assertArrayHasKey( $id, $all, 'course-summary template part must be listed' );
		$this->assertSame( 'Acme Courses', $all[ $id ]['author_text'] );
		$names = $this->blocks_in( $all[ $id ]['content']['raw'] );
		foreach ( array( 'acme-courses/course-price', 'acme-courses/course-duration', 'acme-courses/enroll-button' ) as $block ) {
			$this->assertContains( $block, $names );
		}
		$this->assertSame( 1, count( array_filter( $this->rest_data( $res ), static fn( $t ) => 'course-summary' === $t['slug'] ) ) );

		$single = $this->rest( 'GET', '/wp/v2/template-parts/' . $id, array( 'context' => 'edit' ) );
		$this->assertSame( 200, $single->get_status() );
		$this->assertSame( 'wp_template_part', $this->rest_data( $single )['type'] );

		// The part is not a page template.
		$this->assertSame( 404, $this->rest( 'GET', '/wp/v2/templates/' . THEME . '//course-summary' )->get_status() );
	}

	public function test_templates_are_not_exposed_to_visitors(): void {
		$this->assertContains( $this->rest( 'GET', '/wp/v2/templates' )->get_status(), array( 401, 403 ) );
		$this->login_as( 'subscriber' );
		$this->assertContains( $this->rest( 'GET', '/wp/v2/template-parts' )->get_status(), array( 401, 403 ) );
	}

	public function test_blocks_are_registered_for_the_editors(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array( 'acme-courses/course-price', 'acme-courses/course-duration', 'acme-courses/enroll-button' ) as $name ) {
			$type = $registry->get_registered( $name );
			$this->assertNotNull( $type, "$name must be registered" );
			$this->assertTrue( $type->is_dynamic(), "$name must be dynamic" );
		}
	}
}

<?php
/**
 * The CSV importer (wp acme-testimonials import) writes 4.x blocks.
 */

use function WPSB\Testimonials\cls;
use function WPSB\Testimonials\dom;
use function WPSB\Testimonials\inner_html;

class ImporterTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private ?int $post_id = null;

	protected function tearDown(): void {
		if ( $this->post_id ) {
			wp_delete_post( $this->post_id, true );
		}
		parent::tearDown();
	}

	private function import(): array {
		$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'title' => 'Jane Doe', 'numberposts' => 1 ) );
		$this->assertNotEmpty( $found, 'seeded avatar attachment' );
		$avatar = $found[0];
		$csv = tempnam( sys_get_temp_dir(), 'csv' ) . '.csv';
		file_put_contents( $csv, str_replace( 'AVATAR_ID', (string) $avatar->ID, file_get_contents( __DIR__ . '/fixtures/import.csv' ) ) );
		$res = $this->wp_cli( 'acme-testimonials import ' . escapeshellarg( $csv ) . ' --title=Imported --status=publish --porcelain' );
		unlink( $csv );
		$this->assertSame( 0, $res['exit'], print_r( $res, true ) );
		$this->post_id = (int) trim( $res['stdout'] );
		$this->assertGreaterThan( 0, $this->post_id, print_r( $res, true ) );
		wp_cache_flush();
		$post   = get_post( $this->post_id );
		$blocks = array_values(
			array_filter(
				parse_blocks( $post->post_content ),
				static function ( $b ) {
					return 'acme/testimonial' === $b['blockName'];
				}
			)
		);
		$this->assertCount( 4, $blocks );
		return array( $blocks, $avatar );
	}

	public function test_imported_ratings_are_numbers_with_half_stars(): void {
		list( $blocks ) = $this->import();
		$this->assertSame( 4.5, $blocks[0]['attrs']['rating'] ?? null, 'Rating 4.5 must be stored as the number 4.5' );
		$this->assertSame( 3, $blocks[1]['attrs']['rating'] ?? null, 'Rating 3 must be stored as a number' );
		$this->assertEmpty( $blocks[2]['attrs']['rating'] ?? 0, 'No rating: not rated' );
		$this->assertSame( 5, $blocks[3]['attrs']['rating'] ?? null, 'Ratings above 5 are clamped' );
	}

	public function test_imported_markup_is_the_4x_format(): void {
		list( $blocks, $avatar ) = $this->import();

		$expect = array(
			array( 'Half a star short of perfect.', 'Eva Novak', 'Product owner, Kolibri', 4.5, array( 'is-full', 'is-full', 'is-full', 'is-full', 'is-half' ) ),
			array( 'Works <em>great</em> for our team.', 'Raj Patel', null, 3, array( 'is-full', 'is-full', 'is-full', 'is-empty', 'is-empty' ) ),
			array( 'No rating given here.', 'Mia Wong', 'Designer', 0, null ),
			array( 'Too many stars in the export.', 'Leo Martin', 'Tester', 5, array( 'is-full', 'is-full', 'is-full', 'is-full', 'is-full' ) ),
		);
		foreach ( $blocks as $i => $block ) {
			list( $quote, $name, $role, $rating, $stars ) = $expect[ $i ];
			$x    = dom( $block['innerHTML'] );
			$root = $x->query( '//body/figure[' . cls( 'wp-block-acme-testimonial' ) . ']' );
			$this->assertSame( 1, $root->length, "[$i] figure root: " . $block['innerHTML'] );
			$fig = $root->item( 0 );
			$this->assertSame( $rating > 0, false !== strpos( ' ' . $fig->getAttribute( 'class' ) . ' ', ' has-rating ' ), "[$i] has-rating" );
			$this->assertSame( $quote, inner_html( $x->query( './blockquote[' . cls( 'acme-testimonial__quote' ) . ']/p', $fig )->item( 0 ) ) );
			$cite = $x->query( './figcaption[' . cls( 'acme-testimonial__byline' ) . ']/cite[' . cls( 'acme-testimonial__name' ) . ']', $fig );
			$this->assertSame( 1, $cite->length, "[$i] <cite> name" );
			$this->assertSame( $name, inner_html( $cite->item( 0 ) ) );
			$roles = $x->query( './figcaption/span[' . cls( 'acme-testimonial__role' ) . ']', $fig );
			if ( null === $role ) {
				$this->assertSame( 0, $roles->length );
			} else {
				$this->assertSame( $role, inner_html( $roles->item( 0 ) ) );
			}
			$r = $x->query( './div[' . cls( 'acme-testimonial__rating' ) . ']', $fig );
			if ( null === $stars ) {
				$this->assertSame( 0, $r->length, "[$i] no rating element" );
			} else {
				$this->assertSame( 1, $r->length );
				$div = $r->item( 0 );
				$this->assertSame( 'img', $div->getAttribute( 'role' ) );
				$this->assertSame( "Rated $rating out of 5", $div->getAttribute( 'aria-label' ) );
				$states = array();
				foreach ( $x->query( './span[' . cls( 'acme-testimonial__star' ) . ']', $div ) as $span ) {
					preg_match( '/\bis-(full|half|empty)\b/', $span->getAttribute( 'class' ), $m );
					$states[] = 'is-' . ( $m[1] ?? '?' );
				}
				$this->assertSame( $stars, $states, "[$i] stars" );
				$this->assertSame( '', trim( $div->textContent ) );
			}
			$this->assertStringNotContainsString( 'data-rating', $block['innerHTML'] );
		}

		// Avatar: thumbnail, inside the byline, decorative.
		$x   = dom( $blocks[0]['innerHTML'] );
		$img = $x->query( '//figcaption[' . cls( 'acme-testimonial__byline' ) . ']/img[' . cls( 'acme-testimonial__avatar' ) . ']' );
		$this->assertSame( 1, $img->length, 'Avatar in the byline' );
		$this->assertSame( wp_get_attachment_image_src( $avatar->ID, 'thumbnail' )[0], $img->item( 0 )->getAttribute( 'src' ) );
		$this->assertSame( '', $img->item( 0 )->getAttribute( 'alt' ) );
		$this->assertSame( array( '48', '48' ), array( $img->item( 0 )->getAttribute( 'width' ), $img->item( 0 )->getAttribute( 'height' ) ) );
		$this->assertSame( $avatar->ID, $blocks[0]['attrs']['avatarId'] ?? null );
		$this->assertSame( 0, dom( $blocks[1]['innerHTML'] )->query( '//img' )->length );
	}

	public function test_imported_page_has_structured_data(): void {
		$this->import();
		$res = $this->http( 'GET', wp_make_link_relative( get_permalink( $this->post_id ) ) );
		$this->assertSame( 200, $res['status'] );
		$reviews = WPSB\Testimonials\reviews( $res['body'] );
		$this->assertNotNull( $reviews, 'JSON-LD missing' );
		$this->assertSame( array( 'Eva Novak', 'Raj Patel', 'Mia Wong', 'Leo Martin' ), array_map( fn( $r ) => $r['author']['name'] ?? null, $reviews ) );
		$this->assertSame( array( 4.5, 3, null, 5 ), array_map( fn( $r ) => $r['reviewRating']['ratingValue'] ?? null, $reviews ) );
	}
}

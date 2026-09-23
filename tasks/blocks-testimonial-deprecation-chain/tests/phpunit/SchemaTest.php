<?php
/**
 * Review structured data for every saved format (content that was never re-saved).
 */

use function WPSB\Testimonials\reviews;

class SchemaTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] */
	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();
		parent::tearDown();
	}

	private function reviews_for( string $slug, string $type = 'post' ): array {
		$post = get_page_by_path( $slug, OBJECT, $type );
		$this->assertNotNull( $post, "seeded $slug" );
		$res = $this->http( 'GET', wp_make_link_relative( get_permalink( $post ) ) );
		$this->assertSame( 200, $res['status'] );
		$reviews = reviews( $res['body'] );
		$this->assertNotNull( $reviews, "JSON-LD missing on $slug" );
		return $reviews;
	}

	private static function names( array $reviews ): array {
		return array_map( fn( $r ) => $r['author']['name'] ?? null, $reviews );
	}

	private static function ratings( array $reviews ): array {
		return array_map( fn( $r ) => $r['reviewRating']['ratingValue'] ?? null, $reviews );
	}

	public function test_legacy_content_still_has_reviews(): void {
		$v1 = $this->reviews_for( 'v1-testimonials' );
		$this->assertCount( 3, $v1 );
		$this->assertStringStartsWith( 'Jane Doe', self::names( $v1 )[0] );
		$this->assertStringStartsWith( 'Acme Suite saved us two days a week.', $v1[0]['reviewBody'] );
		foreach ( $v1 as $review ) {
			$this->assertSame( 'Acme Suite', $review['itemReviewed']['name'] ?? null, 'acme_testimonials_schema_review filter' );
		}
		$v3 = $this->reviews_for( 'v3-testimonials' );
		$this->assertSame( array( 'Jane Doe', 'Kim Nguyen', 'Luis Romero' ), self::names( $v3 ) );
		$this->assertSame( array( 5, null, 3 ), self::ratings( $v3 ) );
	}

	public function test_half_star_ratings_of_old_content(): void {
		$v2 = $this->reviews_for( 'v2-testimonials' );
		$this->assertSame( array( 'Maria Garcia', 'Tom Becker', 'Priya Natarajan', 'Sam Lee' ), self::names( $v2 ) );
		$this->assertSame( array( 4, 4.5, null, null ), self::ratings( $v2 ) );
	}

	public function test_imported_content_ratings(): void {
		$reviews = $this->reviews_for( 'customer-reviews', 'page' );
		$this->assertSame( array( 'Omar Haddad', 'Lena Fischer', 'Chris Park', 'Ana Souza' ), self::names( $reviews ) );
		$this->assertSame( array( 5, 4, 3, null ), self::ratings( $reviews ) );
	}

	public function test_4x_markup_is_understood(): void {
		$content = '<!-- wp:acme/testimonial {"rating":4.5,"className":"is-style-card"} -->' . "\n"
			. '<figure class="wp-block-acme-testimonial is-style-card has-rating"><blockquote class="acme-testimonial__quote"><p>Really <strong>good</strong>.</p></blockquote>'
			. '<figcaption class="acme-testimonial__byline"><cite class="acme-testimonial__name">Zoe Adams</cite><span class="acme-testimonial__role">CFO</span></figcaption>'
			. '<div class="acme-testimonial__rating" role="img" aria-label="Rated 4.5 out of 5"><span class="acme-testimonial__star is-full"></span><span class="acme-testimonial__star is-full"></span><span class="acme-testimonial__star is-full"></span><span class="acme-testimonial__star is-full"></span><span class="acme-testimonial__star is-half"></span></div></figure>' . "\n"
			. '<!-- /wp:acme/testimonial -->';
		$id              = $this->create_post( array( 'post_content' => $content ) );
		$this->created[] = $id;
		$res             = $this->http( 'GET', wp_make_link_relative( get_permalink( $id ) ) );
		$reviews         = reviews( $res['body'] );
		$this->assertNotNull( $reviews );
		$this->assertSame( array( 'Zoe Adams' ), self::names( $reviews ) );
		$this->assertSame( array( 4.5 ), self::ratings( $reviews ) );
		$this->assertSame( 'Really good.', $reviews[0]['reviewBody'] );
	}

	public function test_no_structured_data_without_testimonials(): void {
		$res = $this->http( 'GET', wp_make_link_relative( get_permalink( get_page_by_path( 'hello-world', OBJECT, 'post' ) ) ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertNull( reviews( $res['body'] ) );
	}
}

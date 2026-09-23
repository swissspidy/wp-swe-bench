<?php
/**
 * Review structured data (JSON-LD) for testimonials.
 *
 * @package Acme\Testimonials
 */

namespace Acme\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Prints schema.org Review items for the testimonials of the current post.
 *
 * The SEO team relies on this output (Search Console "Review snippets").
 */
class Schema {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'print_json_ld' ) );
	}

	/**
	 * Print the JSON-LD block on singular views that contain testimonials.
	 */
	public function print_json_ld() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! has_block( 'acme/testimonial', $post ) ) {
			return;
		}
		$reviews = $this->reviews_for_content( $post->post_content );
		if ( ! $reviews ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $reviews,
		);
		printf(
			'<script type="application/ld+json" class="acme-testimonials-schema">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Build Review items for all testimonial blocks in some post content.
	 *
	 * @param string $content Post content.
	 * @return array[] schema.org Review items.
	 */
	public function reviews_for_content( $content ) {
		$reviews = array();
		foreach ( acme_testimonials_find_blocks( parse_blocks( $content ) ) as $block ) {
			$review = $this->review( $block );
			if ( $review ) {
				$reviews[] = $review;
			}
		}
		return $reviews;
	}

	/**
	 * Build one Review item from a parsed testimonial block.
	 *
	 * Name and quote are sourced from the saved HTML (they are not stored in the
	 * block comment), so this has to understand every markup version we ever saved.
	 *
	 * @param array $block Parsed block.
	 * @return array|null
	 */
	public function review( array $block ) {
		$html  = $block['innerHTML'];
		$attrs = $block['attrs'];

		$quote = '';
		if ( preg_match( '#<blockquote class="acme-testimonial__quote">(.*?)</blockquote>#s', $html, $m ) ) {
			$quote = $m[1];
		} elseif ( preg_match( '#<p class="acme-testimonial__quote">(.*?)</p>#s', $html, $m ) ) {
			// 1.x markup.
			$quote = $m[1];
		}

		$name = '';
		if ( preg_match( '#<span class="acme-testimonial__name">(.*?)</span>#s', $html, $m ) ) {
			$name = $m[1];
		} elseif ( preg_match( '#<cite class="acme-testimonial__author">(.*?)</cite>#s', $html, $m ) ) {
			// 1.x markup: "Name, Role" in one field.
			$name = $m[1];
		}

		$quote = trim( wp_strip_all_tags( $quote ) );
		$name  = trim( wp_strip_all_tags( $name ) );
		if ( '' === $quote ) {
			return null;
		}

		$review = array(
			'@type'        => 'Review',
			'itemReviewed' => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'reviewBody'   => $quote,
		);
		if ( '' !== $name ) {
			$review['author'] = array(
				'@type' => 'Person',
				'name'  => $name,
			);
		}
		$rating = acme_testimonials_normalize_rating( $attrs['rating'] ?? 0 );
		if ( $rating > 0 ) {
			$review['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			);
		}

		/**
		 * Filters one Review item of the testimonials structured data.
		 *
		 * @param array $review Review item.
		 * @param array $block  Parsed block.
		 */
		return apply_filters( 'acme_testimonials_schema_review', $review, $block );
	}
}

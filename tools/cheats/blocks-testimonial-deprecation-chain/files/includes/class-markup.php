<?php
/**
 * Server-side builder for testimonial block markup.
 *
 * Used by the CSV importer. It MUST produce exactly what the block's save()
 * function (src/testimonial/save.js) produces, otherwise imported blocks show
 * up as invalid in the editor.
 *
 * @package Acme\Testimonials
 */

namespace Acme\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Builds serialized acme/testimonial blocks (4.x markup).
 */
class Markup {

	/**
	 * Build one serialized testimonial block.
	 *
	 * @param array $data {
	 *     Testimonial data.
	 *
	 *     @type string     $quote     Quote (inline HTML allowed).
	 *     @type string     $name      Author name.
	 *     @type string     $role      Author role / company. Optional.
	 *     @type int|string $rating    Rating 0–5, half stars allowed. Optional.
	 *     @type int        $avatar_id Attachment ID of the author's photo. Optional.
	 *     @type string     $className Extra CSS classes. Optional.
	 * }
	 * @return string Serialized block.
	 */
	public static function testimonial( array $data ) {
		$data = wp_parse_args(
			$data,
			array(
				'quote'     => '',
				'name'      => '',
				'role'      => '',
				'rating'    => '',
				'avatar_id' => 0,
				'className' => '',
			)
		);

		$attrs  = array();
		$rating = acme_testimonials_normalize_rating( $data['rating'] );
		if ( $rating > 0 ) {
			// Always a number (int or float), as the block stores it.
			$attrs['rating'] = $rating;
		}

		$avatar_url = '';
		if ( $data['avatar_id'] ) {
			$image      = wp_get_attachment_image_src( (int) $data['avatar_id'], 'thumbnail' );
			$avatar_url = $image ? (string) $image[0] : '';
			if ( $avatar_url ) {
				$attrs['avatarId'] = (int) $data['avatar_id'];
			}
		}

		$classes = array( 'wp-block-acme-testimonial' );
		if ( $data['className'] ) {
			$attrs['className'] = $data['className'];
			$classes[]          = $data['className'];
		}
		if ( $rating > 0 ) {
			$classes[] = 'has-rating';
		}

		$html  = '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<blockquote class="acme-testimonial__quote"><p>' . wp_kses_post( $data['quote'] ) . '</p></blockquote>';
		$html .= '<figcaption class="acme-testimonial__byline">';
		if ( $avatar_url ) {
			$html .= '<img class="acme-testimonial__avatar" src="' . esc_url( $avatar_url ) . '" alt="" width="48" height="48"/>';
		}
		$html .= '<cite class="acme-testimonial__name">' . wp_kses_post( $data['name'] ) . '</cite>';
		if ( '' !== trim( $data['role'] ) ) {
			$html .= '<span class="acme-testimonial__role">' . wp_kses_post( $data['role'] ) . '</span>';
		}
		$html .= '</figcaption>';
		if ( $rating > 0 ) {
			$html .= self::rating( $rating );
		}
		$html .= '</figure>';

		return get_comment_delimited_block_content( 'acme/testimonial', $attrs, $html );
	}

	/**
	 * Star rating markup (same as src/testimonial/rating.js).
	 *
	 * @param int|float $rating Normalised rating (> 0).
	 * @return string
	 */
	public static function rating( $rating ) {
		$html = sprintf(
			'<div class="acme-testimonial__rating" role="img" aria-label="%s">',
			esc_attr( sprintf( 'Rated %s out of 5', $rating ) )
		);
		foreach ( acme_testimonials_star_states( $rating ) as $state ) {
			$html .= '<span class="acme-testimonial__star is-' . $state . '"></span>';
		}
		return $html . '</div>';
	}
}

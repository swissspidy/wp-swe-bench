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
 * Builds serialized acme/testimonial blocks.
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
	 *     @type int|string $rating    Rating. Optional.
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
			// Keep what the CSV said.
			$attrs['rating'] = $data['rating'];
		}

		$avatar_url = '';
		if ( $data['avatar_id'] ) {
			$avatar_url = (string) wp_get_attachment_url( (int) $data['avatar_id'] );
			if ( $avatar_url ) {
				$attrs['avatarId'] = (int) $data['avatar_id'];
			}
		}

		$classes = array( 'wp-block-acme-testimonial' );
		if ( $rating > 0 ) {
			$classes[] = 'has-rating';
		}
		if ( $data['className'] ) {
			$attrs['className'] = $data['className'];
			$classes[]          = $data['className'];
		}

		$html = '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		if ( $avatar_url ) {
			$html .= sprintf(
				'<img class="acme-testimonial__avatar wp-image-%d" src="%s" alt="%s"/>',
				(int) $data['avatar_id'],
				esc_url( $avatar_url ),
				esc_attr( wp_strip_all_tags( $data['name'] ) )
			);
		}
		$html .= '<blockquote class="acme-testimonial__quote"><p>' . wp_kses_post( $data['quote'] ) . '</p></blockquote>';
		$html .= '<figcaption class="acme-testimonial__byline">';
		$html .= '<span class="acme-testimonial__name">' . wp_kses_post( $data['name'] ) . '</span>';
		if ( '' !== trim( $data['role'] ) ) {
			$html .= '<span class="acme-testimonial__role">' . wp_kses_post( $data['role'] ) . '</span>';
		}
		$html .= '</figcaption>';
		if ( $rating > 0 ) {
			$html .= sprintf(
				'<div class="acme-testimonial__rating" data-rating="%1$d" aria-label="%1$d out of 5 stars">%2$s</div>',
				$rating,
				self::stars( $rating )
			);
		}
		$html .= '</figure>';

		return get_comment_delimited_block_content( 'acme/testimonial', $attrs, $html );
	}

	/**
	 * Star characters for a rating (same as save.js).
	 *
	 * @param int $rating Rating 1–5.
	 * @return string
	 */
	public static function stars( $rating ) {
		return str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
	}
}

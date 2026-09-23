<?php
/**
 * FAQPage structured data (JSON-LD) for posts containing FAQ blocks.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and prints the schema.org FAQPage graph.
 */
class Schema {

	/**
	 * Print JSON-LD in the head of singular views.
	 */
	public function print_json_ld() {
		$options = get_options();
		if ( empty( $options['structured_data'] ) || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || ! has_block( 'acme/faq', $post ) ) {
			return;
		}
		$data = $this->build( $post );
		if ( empty( $data['mainEntity'] ) ) {
			return;
		}
		echo '<script type="application/ld+json" class="acme-faq-schema">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n";
	}

	/**
	 * The FAQPage data for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public function build( \WP_Post $post ) {
		$url      = get_permalink( $post );
		$entities = array();
		foreach ( $this->questions( parse_blocks( $post->post_content ) ) as $qa ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => plain_text( $qa['question'] ),
				'url'            => $url . '#' . question_anchor( $qa['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => plain_text( $qa['answer'] ),
				),
			);
		}

		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);

		/**
		 * Filters the FAQPage structured data of a post.
		 *
		 * @since 1.1.0
		 *
		 * @param array    $data Data.
		 * @param \WP_Post $post Post.
		 */
		return apply_filters( 'acme_faq_schema_data', $data, $post );
	}

	/**
	 * Question/answer pairs from parsed blocks (recursively).
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array<int, array{question:string, answer:string}>
	 */
	private function questions( array $blocks ) {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( 'acme/faq' === $block['blockName'] && empty( $block['innerBlocks'] ) ) {
				// 1.0: questions and answers live in a definition list.
				$out = array_merge( $out, Renderer::legacy_items( $block['innerHTML'] ) );
			} elseif ( 'acme/faq-item' === $block['blockName'] ) {
				$question = isset( $block['attrs']['question'] ) && is_string( $block['attrs']['question'] ) ? $block['attrs']['question'] : '';
				if ( '' === $question && preg_match( '#<h3 class="acme-faq__question">(.*?)</h3>#s', $block['innerHTML'], $m ) ) {
					// Items saved by 1.2 – 1.3.
					$question = $m[1];
				}
				if ( '' !== $question ) {
					$answer = '';
					foreach ( $block['innerBlocks'] as $inner ) {
						$answer .= render_block( $inner );
					}
					$out[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, $this->questions( $block['innerBlocks'] ) );
			}
		}
		return $out;
	}
}

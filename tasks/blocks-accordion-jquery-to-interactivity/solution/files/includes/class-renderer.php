<?php
/**
 * Server rendering of the FAQ blocks (accessible accordion markup).
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Renders acme/faq and acme/faq-item for every saved format (1.0, 1.2+, 2.0).
 */
class Renderer {

	const STORE = 'acme/faq';

	/**
	 * Ids handed out during this request (item ids must be unique in the page).
	 *
	 * @var array<string, bool>
	 */
	private static $ids = array();

	/**
	 * Forget handed-out ids (new page render).
	 */
	public static function reset_ids() {
		self::$ids = array();
	}

	/**
	 * A page-unique id based on $base.
	 *
	 * @param string $base Wanted id.
	 * @return string
	 */
	public static function unique_id( $base ) {
		$id = $base;
		$n  = 2;
		while ( isset( self::$ids[ $id ] ) ) {
			$id = $base . '-' . $n;
			++$n;
		}
		self::$ids[ $id ] = true;
		return $id;
	}

	/**
	 * Render callback of acme/faq-item.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Rendered inner content.
	 * @param \WP_Block $block      Block.
	 * @return string
	 */
	public static function render_item( $attributes, $content, $block ) {
		$saved    = isset( $block->parsed_block['innerHTML'] ) ? (string) $block->parsed_block['innerHTML'] : '';
		$question = isset( $attributes['question'] ) && is_string( $attributes['question'] ) ? $attributes['question'] : '';
		$anchor   = isset( $attributes['anchor'] ) && is_string( $attributes['anchor'] ) ? $attributes['anchor'] : '';

		// 1.2 – 1.3 items keep the question (and anchor) in the saved markup.
		if ( '' === $question && '' !== trim( $saved ) ) {
			if ( preg_match( '#<h3[^>]*class="[^"]*\bacme-faq__question\b[^"]*"[^>]*>(.*?)</h3>#s', $saved, $m ) ) {
				$question = $m[1];
			}
		}
		if ( '' === $anchor && '' !== trim( $saved ) ) {
			$tags = new \WP_HTML_Tag_Processor( $saved );
			if ( $tags->next_tag() && is_string( $tags->get_attribute( 'id' ) ) ) {
				$anchor = $tags->get_attribute( 'id' );
			}
		}

		$answer = '';
		foreach ( $block->inner_blocks as $inner ) {
			$answer .= $inner->render();
		}

		$extra = array();
		if ( ! empty( $attributes['className'] ) ) {
			$extra[] = $attributes['className'];
		}
		return self::item_markup( $question, $answer, $anchor, $extra );
	}

	/**
	 * Markup of one question.
	 *
	 * @param string   $question Question HTML.
	 * @param string   $answer   Answer HTML.
	 * @param string   $anchor   Custom anchor ('' for the default).
	 * @param string[] $classes  Extra classes.
	 * @return string
	 */
	public static function item_markup( $question, $answer, $anchor = '', array $classes = array() ) {
		$id     = self::unique_id( '' !== $anchor ? sanitize_html_class( $anchor ) : question_anchor( $question ) );
		$toggle = $id . '-toggle';
		$panel  = $id . '-panel';

		$classes = array_merge( array( 'wp-block-acme-faq-item', 'acme-faq__item' ), array_map( 'sanitize_html_class', $classes ) );

		return sprintf(
			'<div class="%1$s" id="%2$s" data-wp-context="%3$s" data-wp-class--is-open="state.isOpen">'
			. '<h3 class="acme-faq__heading"><button type="button" class="acme-faq__toggle" id="%4$s" aria-controls="%5$s" data-wp-bind--aria-expanded="state.isOpen" data-wp-on--click="actions.toggle" data-wp-on--keydown="actions.navigate">%6$s</button></h3>'
			. '<div class="acme-faq__panel" id="%5$s" role="region" aria-labelledby="%4$s" data-wp-bind--hidden="!state.isOpen">%7$s</div>'
			. '</div>',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			esc_attr( $id ),
			esc_attr( wp_json_encode( array( 'id' => $id ) ) ),
			esc_attr( $toggle ),
			esc_attr( $panel ),
			wp_kses( $question, self::question_tags() ),
			$answer
		);
	}

	/**
	 * Render callback of acme/faq.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Rendered items.
	 * @param \WP_Block $block      Block.
	 * @return string
	 */
	public static function render_faq( $attributes, $content, $block ) {
		$saved = isset( $block->parsed_block['innerHTML'] ) ? (string) $block->parsed_block['innerHTML'] : '';
		$attrs = array();

		// 1.0 – 1.1: all questions in a definition list inside the saved markup.
		if ( empty( $block->inner_blocks ) && false !== strpos( $saved, 'acme-faq__list' ) ) {
			$content = '';
			foreach ( self::legacy_items( $saved ) as $item ) {
				$content .= self::item_markup( $item['question'], '<p>' . $item['answer'] . '</p>' );
			}
		}

		// 1.2 – 1.3: the saved wrapper is still around the rendered questions.
		if ( ! empty( $block->inner_blocks ) && preg_match( '#^\s*<div\b#', $saved ) ) {
			$content = preg_replace( '#^\s*<div\b[^>]*>#', '', $content );
			$content = preg_replace( '#</div>\s*$#', '', $content );
		}

		// 1.2 – 1.3: the anchor of the FAQ lives in the saved wrapper.
		if ( empty( $attributes['anchor'] ) && '' !== trim( $saved ) ) {
			$tags = new \WP_HTML_Tag_Processor( $saved );
			if ( $tags->next_tag() && is_string( $tags->get_attribute( 'id' ) ) ) {
				$attrs['id'] = $tags->get_attribute( 'id' );
			}
		}

		$ids  = array();
		$tags = new \WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag( array( 'class_name' => 'acme-faq__item' ) ) ) {
			$id = $tags->get_attribute( 'id' );
			if ( is_string( $id ) ) {
				$ids[] = $id;
			}
		}
		if ( ! $ids ) {
			return '';
		}

		$context = array(
			'open'          => ! empty( $attributes['openFirst'] ) ? array( $ids[0] ) : array(),
			'allowMultiple' => ! empty( $attributes['allowMultiple'] ),
			'ids'           => $ids,
		);

		wp_interactivity_state(
			self::STORE,
			array(
				'isOpen' => static function () {
					$context = wp_interactivity_get_context();
					return isset( $context['id'], $context['open'] ) && in_array( $context['id'], (array) $context['open'], true );
				},
			)
		);

		$attrs['class']                         = 'acme-faq';
		$attrs['data-wp-interactive']           = self::STORE;
		$attrs['data-wp-context']               = wp_json_encode( $context );
		$attrs['data-wp-init']                  = 'callbacks.openFromHash';
		$attrs['data-wp-on-window--hashchange'] = 'callbacks.openFromHash';

		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes( $attrs ), $content );
	}

	/**
	 * Question/answer pairs of a 1.0 FAQ.
	 *
	 * @param string $html Saved markup.
	 * @return array<int, array{question:string, answer:string}>
	 */
	public static function legacy_items( $html ) {
		$items = array();
		if ( preg_match_all( '#<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>#s', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $pair ) {
				$items[] = array(
					'question' => trim( $pair[1] ),
					'answer'   => trim( $pair[2] ),
				);
			}
		}
		return $items;
	}

	/**
	 * Inline tags allowed in questions.
	 *
	 * @return array
	 */
	private static function question_tags() {
		return array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'code'   => array(),
		);
	}
}

<?php
/**
 * Markup of the components (shared by the blocks and the shortcode).
 *
 * The markup is a contract: the runtime (assets/js) and several themes rely on it.
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Component markup.
 */
class Renderer {

	/** @var int */
	private static $counter = 0;

	/**
	 * Unique ID prefix for one component instance.
	 *
	 * @param string $type Component.
	 * @return string
	 */
	private static function uid( $type ) {
		++self::$counter;
		return 'acme-' . $type . '-' . self::$counter;
	}

	/**
	 * Allowed HTML in item content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function content( $content ) {
		return wpautop( wp_kses_post( (string) $content ) );
	}

	/**
	 * Tabs.
	 *
	 * @param array[] $tabs  [ [ 'title' => ..., 'content' => ... ] ].
	 * @param string  $class Extra classes.
	 * @return string
	 */
	public function tabs( array $tabs, $class = '' ) {
		$tabs = array_values( array_filter( $tabs, 'is_array' ) );
		if ( ! $tabs ) {
			return '';
		}
		$id   = self::uid( 'tabs' );
		$list = '';
		$body = '';
		foreach ( $tabs as $i => $tab ) {
			$title = isset( $tab['title'] ) && '' !== $tab['title'] ? $tab['title'] : sprintf( /* translators: %d: tab number */ __( 'Tab %d', 'acme-ui-kit' ), $i + 1 );
			$list .= sprintf(
				'<button type="button" role="tab" class="acme-tabs__tab%1$s" id="%2$s-tab-%3$d" aria-controls="%2$s-panel-%3$d" aria-selected="%4$s" tabindex="%5$s">%6$s</button>',
				0 === $i ? ' is-active' : '',
				esc_attr( $id ),
				$i,
				0 === $i ? 'true' : 'false',
				0 === $i ? '0' : '-1',
				esc_html( $title )
			);
			$body .= sprintf(
				'<div role="tabpanel" class="acme-tabs__panel" id="%1$s-panel-%2$d" aria-labelledby="%1$s-tab-%2$d"%3$s>%4$s</div>',
				esc_attr( $id ),
				$i,
				0 === $i ? '' : ' hidden',
				self::content( isset( $tab['content'] ) ? $tab['content'] : '' )
			);
		}
		return sprintf(
			'<div class="%s" data-acme-component="tabs"><div class="acme-tabs__list" role="tablist">%s</div>%s</div>',
			esc_attr( trim( 'acme-tabs ' . $class ) ),
			$list,
			$body
		);
	}

	/**
	 * Accordion.
	 *
	 * @param array[] $items  [ [ 'title' => ..., 'content' => ... ] ].
	 * @param bool    $single Only one item open at a time.
	 * @param string  $class  Extra classes.
	 * @return string
	 */
	public function accordion( array $items, $single = false, $class = '' ) {
		$items = array_values( array_filter( $items, 'is_array' ) );
		if ( ! $items ) {
			return '';
		}
		$id   = self::uid( 'accordion' );
		$html = '';
		foreach ( $items as $i => $item ) {
			$html .= sprintf(
				'<div class="acme-accordion__item"><h3 class="acme-accordion__heading"><button type="button" class="acme-accordion__toggle" id="%1$s-toggle-%2$d" aria-expanded="false" aria-controls="%1$s-panel-%2$d">%3$s<span class="acme-icon acme-icon--chevron" aria-hidden="true"></span></button></h3><div class="acme-accordion__panel" id="%1$s-panel-%2$d" role="region" aria-labelledby="%1$s-toggle-%2$d" hidden>%4$s</div></div>',
				esc_attr( $id ),
				$i,
				esc_html( isset( $item['title'] ) ? $item['title'] : '' ),
				self::content( isset( $item['content'] ) ? $item['content'] : '' )
			);
		}
		return sprintf(
			'<div class="%s" data-acme-component="accordion" data-single="%s">%s</div>',
			esc_attr( trim( 'acme-accordion ' . $class ) ),
			$single ? 'true' : 'false',
			$html
		);
	}

	/**
	 * Carousel.
	 *
	 * @param array[] $slides [ [ 'caption' => ..., 'color' => '#hex' ] ].
	 * @param string  $class  Extra classes.
	 * @return string
	 */
	public function carousel( array $slides, $class = '' ) {
		$slides = array_values( array_filter( $slides, 'is_array' ) );
		if ( ! $slides ) {
			return '';
		}
		$html = '';
		foreach ( $slides as $slide ) {
			$color = isset( $slide['color'] ) ? sanitize_hex_color( $slide['color'] ) : '';
			$html .= sprintf(
				'<li class="acme-carousel__slide"%s><p class="acme-carousel__caption">%s</p></li>',
				$color ? ' style="--acme-slide-color:' . esc_attr( $color ) . '"' : '',
				esc_html( isset( $slide['caption'] ) ? $slide['caption'] : '' )
			);
		}
		return sprintf(
			'<div class="%1$s" data-acme-component="carousel" data-current="0"><div class="acme-carousel__viewport"><ul class="acme-carousel__track">%2$s</ul></div><button type="button" class="acme-carousel__prev" aria-label="%3$s"><span class="acme-icon acme-icon--chevron-left" aria-hidden="true"></span></button><button type="button" class="acme-carousel__next" aria-label="%4$s"><span class="acme-icon acme-icon--chevron-right" aria-hidden="true"></span></button><p class="acme-carousel__status" aria-live="polite">1 / %5$d</p></div>',
			esc_attr( trim( 'acme-carousel ' . $class ) ),
			$html,
			esc_attr__( 'Previous slide', 'acme-ui-kit' ),
			esc_attr__( 'Next slide', 'acme-ui-kit' ),
			count( $slides )
		);
	}
}

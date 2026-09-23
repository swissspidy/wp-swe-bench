<?php
/**
 * HTML output of the related list.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the related list. Themes style this markup, don't change it.
 */
class Renderer {

	/** @var Settings */
	private $settings;

	/** @var Views */
	private $views;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Views    $views    Views.
	 */
	public function __construct( Settings $settings, Views $views ) {
		$this->settings = $settings;
		$this->views    = $views;
	}

	/**
	 * The whole list.
	 *
	 * @param int     $post_id Post the list belongs to.
	 * @param array[] $items   Items (see Item::from_post()).
	 * @param array   $args    Optional overrides: heading, show_thumbnails, show_views, class.
	 * @return string
	 */
	public function render_list( $post_id, array $items, array $args = array() ) {
		if ( ! $items ) {
			return '';
		}
		$args = wp_parse_args(
			$args,
			array(
				'heading'         => $this->settings->get( 'heading' ),
				'show_thumbnails' => (bool) $this->settings->get( 'show_thumbnails' ),
				'show_views'      => (bool) $this->settings->get( 'show_views' ),
				'class'           => '',
			)
		);

		$heading_id = 'acme-related-' . (int) $post_id;
		$classes    = trim( 'acme-related ' . $args['class'] );

		$html  = sprintf( '<section class="%s" aria-labelledby="%s">', esc_attr( $classes ), esc_attr( $heading_id ) );
		$html .= sprintf( '<h2 class="acme-related__heading" id="%s">%s</h2>', esc_attr( $heading_id ), esc_html( $args['heading'] ) );
		$html .= '<ul class="acme-related__list">';
		foreach ( $items as $item ) {
			$html .= $this->render_item( $item, $args );
		}
		$html .= '</ul></section>';

		/**
		 * Filters the HTML of the related list.
		 *
		 * @param string  $html    HTML.
		 * @param int     $post_id Post the list belongs to.
		 * @param array[] $items   Items.
		 */
		return apply_filters( 'acme_related_list_html', $html, $post_id, $items );
	}

	/**
	 * One item.
	 *
	 * @param array $item Item data.
	 * @param array $args Display args.
	 * @return string
	 */
	public function render_item( array $item, array $args ) {
		$html = sprintf( '<li class="acme-related__item" data-post-id="%d">', (int) $item['id'] );

		if ( $args['show_thumbnails'] && ! empty( $item['image'] ) ) {
			$html .= sprintf(
				'<a class="acme-related__thumb" href="%1$s" tabindex="-1" aria-hidden="true"><img src="%2$s" width="%3$d" height="%4$d" alt="%5$s" loading="lazy" decoding="async" /></a>',
				esc_url( $item['link'] ),
				esc_url( $item['image']['src'] ),
				(int) $item['image']['width'],
				(int) $item['image']['height'],
				esc_attr( $item['image']['alt'] )
			);
		}

		$html .= '<div class="acme-related__body">';
		if ( ! empty( $item['category'] ) ) {
			$html .= sprintf( '<a class="acme-related__category" href="%s">%s</a>', esc_url( $item['category']['link'] ), esc_html( $item['category']['name'] ) );
		}
		$html .= sprintf( '<a class="acme-related__title" href="%s">%s</a>', esc_url( $item['link'] ), esc_html( wp_strip_all_tags( $item['title'] ) ) );

		$meta = array();
		if ( ! empty( $item['author'] ) ) {
			/* translators: %s: author name linked to the author archive */
			$meta[] = sprintf( __( 'By %s', 'acme-related' ), sprintf( '<a href="%s">%s</a>', esc_url( $item['author']['link'] ), esc_html( $item['author']['name'] ) ) );
		}
		$meta[] = sprintf( '<time datetime="%s">%s</time>', esc_attr( $item['date'] ), esc_html( $item['date_display'] ) );
		/* translators: %s: number of minutes */
		$meta[] = esc_html( sprintf( _n( '%s min read', '%s min read', $item['reading_time'], 'acme-related' ), number_format_i18n( $item['reading_time'] ) ) );
		if ( $args['show_views'] ) {
			/* translators: %s: number of views */
			$meta[] = esc_html( sprintf( _n( '%s view', '%s views', $item['views'], 'acme-related' ), number_format_i18n( $item['views'] ) ) );
		}
		$html .= '<span class="acme-related__meta">' . implode( ' <span aria-hidden="true">&middot;</span> ', $meta ) . '</span>';

		if ( ! empty( $item['badge'] ) ) {
			$html .= sprintf( '<span class="acme-related__badge">%s</span>', esc_html( $item['badge'] ) );
		}
		$html .= '</div></li>';

		/**
		 * Filters the HTML of one related item.
		 *
		 * @param string $html HTML.
		 * @param array  $item Item data.
		 */
		return apply_filters( 'acme_related_item_html', $html, $item );
	}
}

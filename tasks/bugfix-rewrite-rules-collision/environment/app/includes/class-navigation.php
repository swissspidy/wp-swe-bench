<?php
/**
 * Doc navigation: breadcrumbs, child docs, cross-links.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end navigation elements.
 *
 * Markup (styled by the theme):
 *
 *     <nav class="acme-docs-breadcrumbs" aria-label="Breadcrumbs">
 *       <a href="/docs/acme-cloud/">Acme Cloud</a> › <a href="…/getting-started/">Getting started</a> › <span>Installation</span>
 *     </nav>
 *     … content …
 *     <nav class="acme-docs-children"><ul><li><a href="…">Child</a></li></ul></nav>
 */
class Navigation {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'wrap_content' ), 15 );
		add_shortcode( 'acme_doc_link', array( $this, 'doc_link_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Stylesheet.
	 */
	public function enqueue() {
		if ( is_singular( Post_Types::DOC ) || is_tax( Post_Types::PRODUCT ) ) {
			wp_enqueue_style( 'acme-docs', ACME_DOCS_URL . 'assets/docs.css', array(), ACME_DOCS_VERSION );
		}
	}

	/**
	 * Adds breadcrumbs and child docs to single docs.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function wrap_content( $content ) {
		if ( ! is_singular( Post_Types::DOC ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		return self::breadcrumbs( $post ) . $content . self::children( $post );
	}

	/**
	 * Breadcrumbs markup.
	 *
	 * @param \WP_Post $post Doc.
	 * @return string
	 */
	public static function breadcrumbs( $post ) {
		$items   = array();
		$product = Permalinks::get_product( $post );
		if ( $product ) {
			$items[] = sprintf( '<a href="%s">%s</a>', esc_url( get_term_link( $product ) ), esc_html( $product->name ) );
		}
		$version = Permalinks::get_version( $post );
		if ( $version ) {
			$items[] = sprintf( '<span class="acme-docs-version">%s</span>', esc_html( $version ) );
		}
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
			$items[] = sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $ancestor_id ) ), esc_html( get_the_title( $ancestor_id ) ) );
		}
		$items[] = '<span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span>';

		return '<nav class="acme-docs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'acme-docs' ) . '">' . implode( ' &rsaquo; ', $items ) . '</nav>';
	}

	/**
	 * Child docs markup.
	 *
	 * @param \WP_Post $post Doc.
	 * @return string
	 */
	public static function children( $post ) {
		$children = get_posts(
			array(
				'post_type'      => Post_Types::DOC,
				'post_parent'    => $post->ID,
				'post_status'    => 'publish',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'posts_per_page' => 100,
			)
		);
		if ( ! $children ) {
			return '';
		}
		$html = '<nav class="acme-docs-children" aria-label="' . esc_attr__( 'In this section', 'acme-docs' ) . '"><ul>';
		foreach ( $children as $child ) {
			$html .= sprintf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $child ) ), esc_html( get_the_title( $child ) ) );
		}
		return $html . '</ul></nav>';
	}

	/**
	 * `[acme_doc_link product="acme-cli" path="getting-started/installation" version="v2"]Text[/acme_doc_link]`
	 *
	 * Cross-links between docs (also across products) that survive moving docs around.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Link text.
	 * @return string
	 */
	public function doc_link_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'product' => '',
				'path'    => '',
				'version' => '',
			),
			$atts,
			'acme_doc_link'
		);
		$doc  = acme_docs_get_doc_by_path( $atts['product'], $atts['path'], $atts['version'] );
		$text = null !== $content && '' !== $content ? do_shortcode( $content ) : ( $doc ? esc_html( get_the_title( $doc ) ) : esc_html( $atts['path'] ) );
		if ( ! $doc ) {
			return '<span class="acme-doc-link acme-doc-link--missing">' . $text . '</span>';
		}
		return sprintf( '<a class="acme-doc-link" href="%s">%s</a>', esc_url( get_permalink( $doc ) ), $text );
	}
}

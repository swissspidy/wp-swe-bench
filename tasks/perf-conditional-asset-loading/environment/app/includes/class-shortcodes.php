<?php
/**
 * Legacy shortcodes (1.x, still used in a few hundred classic posts):
 *
 *     [acme_tabs]
 *       [acme_tab title="One"]First panel[/acme_tab]
 *       [acme_tab title="Two"]Second panel[/acme_tab]
 *     [/acme_tabs]
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes.
 */
class Shortcodes {

	/** @var Renderer */
	private $renderer;

	/** @var array[]|null Tabs collected while rendering [acme_tabs]. */
	private $collecting = null;

	/**
	 * Constructor.
	 *
	 * @param Renderer $renderer Renderer.
	 */
	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'acme_tabs', array( $this, 'tabs' ) );
		add_shortcode( 'acme_tab', array( $this, 'tab' ) );
	}

	/**
	 * [acme_tabs].
	 *
	 * @param array|string $atts    Attributes.
	 * @param string       $content Content.
	 * @return string
	 */
	public function tabs( $atts, $content = '' ) {
		$atts             = shortcode_atts( array( 'class' => '' ), $atts, 'acme_tabs' );
		$this->collecting = array();
		do_shortcode( (string) $content );
		$tabs             = $this->collecting;
		$this->collecting = null;
		return $this->renderer->tabs( $tabs, 'acme-tabs--shortcode ' . sanitize_html_class( $atts['class'] ) );
	}

	/**
	 * [acme_tab] (only inside [acme_tabs]).
	 *
	 * @param array|string $atts    Attributes.
	 * @param string       $content Content.
	 * @return string
	 */
	public function tab( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'acme_tab' );
		if ( null === $this->collecting ) {
			return do_shortcode( (string) $content );
		}
		$this->collecting[] = array(
			'title'   => $atts['title'],
			'content' => trim( do_shortcode( shortcode_unautop( (string) $content ) ) ),
		);
		return '';
	}
}

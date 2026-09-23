<?php
/**
 * Blocks: acme/tabs, acme/accordion, acme/carousel (rendered on the server).
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration.
 */
class Blocks {

	/** @var Renderer */
	private $renderer;

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
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Wrapper classes from the block attributes.
	 *
	 * @param array $attributes Attributes.
	 * @param string $block     Block class.
	 * @return string
	 */
	private function classes( array $attributes, $block ) {
		$classes = array( 'wp-block-acme-' . $block );
		if ( ! empty( $attributes['align'] ) ) {
			$classes[] = 'align' . sanitize_html_class( $attributes['align'] );
		}
		if ( ! empty( $attributes['className'] ) ) {
			foreach ( preg_split( '/\s+/', (string) $attributes['className'] ) as $class ) {
				$classes[] = sanitize_html_class( $class );
			}
		}
		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Register the block types.
	 */
	public function register() {
		$items = array(
			'type'  => 'array',
			'items' => array( 'type' => 'object' ),
		);
		$common = array(
			'editor_script' => 'acme-ui-blocks-editor',
			'supports'      => array(
				'align' => array( 'wide', 'full' ),
				'html'  => false,
			),
		);

		register_block_type(
			'acme/tabs',
			$common + array(
				'title'           => __( 'Tabs', 'acme-ui-kit' ),
				'attributes'      => array(
					'tabs'      => $items + array(
						'default' => array(
							array(
								'title'   => __( 'First tab', 'acme-ui-kit' ),
								'content' => '',
							),
							array(
								'title'   => __( 'Second tab', 'acme-ui-kit' ),
								'content' => '',
							),
						),
					),
					'className' => array( 'type' => 'string' ),
					'align'     => array( 'type' => 'string' ),
				),
				'render_callback' => function ( $attributes ) {
					return $this->renderer->tabs( (array) $attributes['tabs'], $this->classes( $attributes, 'tabs' ) );
				},
			)
		);

		register_block_type(
			'acme/accordion',
			$common + array(
				'title'           => __( 'Accordion', 'acme-ui-kit' ),
				'attributes'      => array(
					'items'     => $items + array(
						'default' => array(
							array(
								'title'   => __( 'Question', 'acme-ui-kit' ),
								'content' => '',
							),
						),
					),
					'single'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'className' => array( 'type' => 'string' ),
					'align'     => array( 'type' => 'string' ),
				),
				'render_callback' => function ( $attributes ) {
					return $this->renderer->accordion( (array) $attributes['items'], ! empty( $attributes['single'] ), $this->classes( $attributes, 'accordion' ) );
				},
			)
		);

		register_block_type(
			'acme/carousel',
			$common + array(
				'title'           => __( 'Carousel', 'acme-ui-kit' ),
				'attributes'      => array(
					'slides'    => $items + array(
						'default' => array(
							array(
								'caption' => '',
								'color'   => '#eef1f8',
							),
						),
					),
					'className' => array( 'type' => 'string' ),
					'align'     => array( 'type' => 'string' ),
				),
				'render_callback' => function ( $attributes ) {
					return $this->renderer->carousel( (array) $attributes['slides'], $this->classes( $attributes, 'carousel' ) );
				},
			)
		);
	}
}

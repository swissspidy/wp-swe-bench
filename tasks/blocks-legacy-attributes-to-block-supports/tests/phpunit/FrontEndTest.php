<?php
/**
 * Front-end output of notices and statistics saved in every format.
 */

use function WPSB\ContentBlocks\blocks;
use function WPSB\ContentBlocks\render_slug;

class FrontEndTest extends WPSB\TestCase {

	const PAD_KEYS = array( 'padding-top', 'padding-right', 'padding-bottom', 'padding-left' );

	private function notices( string $html ): array {
		return blocks( $html, 'wp-block-acme-notice-box' );
	}

	private function assertNoticeShell( array $n, string $tone ): void {
		$this->assertContains( 'is-tone-' . $tone, $n['classes'] );
		$this->assertSame( 'note', $n['attrs']['role'] ?? null );
		$body = $n['xpath']->query( './*[' . WPSB\ContentBlocks\has_class_xpath( 'wp-block-acme-notice-box__body' ) . ']', $n['node'] );
		$this->assertSame( 1, $body->length, 'Notice body element missing' );
		foreach ( array( 'acme-notice', 'acme-notice--' . $tone, 'is-bordered' ) as $legacy ) {
			$this->assertNotContains( $legacy, $n['classes'], "Legacy class $legacy still output" );
		}
	}

	private function assertNoOwnDesign( array $n ): void {
		foreach ( $n['classes'] as $class ) {
			$this->assertDoesNotMatchRegularExpression( '/^has-.*(color|background|font-size)$/', $class, 'Block without own settings must not get design classes' );
			$this->assertNotSame( 'has-background', $class );
		}
		foreach ( array( 'background', 'background-color', 'color', 'font-size', 'padding', 'padding-top' ) as $prop ) {
			$this->assertArrayNotHasKey( $prop, $n['style'], "Inline $prop must not be output for a block without its own setting" );
		}
	}

	private function assertPadding( array $n, string $value ): void {
		foreach ( self::PAD_KEYS as $key ) {
			$this->assertSame( $value, $n['style'][ $key ] ?? null, "$key wrong: " . wp_json_encode( $n['style'] ) );
		}
	}

	public function test_1_0_notice_with_defaults_follows_the_theme(): void {
		$all = $this->notices( render_slug( 'notices-v1' ) );
		$this->assertCount( 3, $all );
		$this->assertNoticeShell( $all[0], 'warning' );
		$this->assertNoOwnDesign( $all[0] );
		$this->assertStringContainsString( 'The office is closed on Friday.', $all[0]['text'] );
	}

	public function test_1_0_notice_with_palette_values_uses_presets(): void {
		$all = $this->notices( render_slug( 'notices-v1' ) );
		$n   = $all[1];
		$this->assertNoticeShell( $n, 'info' );
		foreach ( array( 'has-sand-background-color', 'has-background', 'has-umber-color', 'has-text-color', 'has-large-font-size' ) as $class ) {
			$this->assertContains( $class, $n['classes'], "Missing $class" );
		}
		$this->assertArrayNotHasKey( 'background-color', $n['style'] );
		$this->assertArrayNotHasKey( 'color', $n['style'] );
		$this->assertArrayNotHasKey( 'font-size', $n['style'] );
		$this->assertPadding( $n, 'var(--wp--preset--spacing--30)' );
		$this->assertStringContainsString( 'Palette colours from 1.0.', $n['text'] );
	}

	public function test_1_0_notice_with_custom_values_keeps_them(): void {
		$all = $this->notices( render_slug( 'notices-v1' ) );
		$n   = $all[2];
		$this->assertNoticeShell( $n, 'error' );
		$this->assertSame( '#abcdef', strtolower( $n['style']['background-color'] ?? '' ) );
		$this->assertContains( 'has-background', $n['classes'] );
		// "#fff" is the theme's White ("#FFFFFF").
		$this->assertContains( 'has-white-color', $n['classes'] );
		$this->assertArrayNotHasKey( 'color', $n['style'] );
		$this->assertSame( '15px', $n['style']['font-size'] ?? null );
		$this->assertPadding( $n, '18px' );
		$this->assertStringContainsString( 'First', $n['text'] );
		$this->assertStringContainsString( 'Second', $n['text'] );
	}

	public function test_1_3_notices_are_converted(): void {
		$all = $this->notices( render_slug( 'notices-v2' ) );
		$this->assertCount( 4, $all );

		$this->assertNoticeShell( $all[0], 'success' );
		$this->assertNoOwnDesign( $all[0] );
		$this->assertSame( 0, $all[0]['xpath']->query( './/*[' . WPSB\ContentBlocks\has_class_xpath( 'wp-block-acme-notice-box__icon' ) . ']', $all[0]['node'] )->length, 'Show icon = off must be kept' );

		// Core's default palette red is not a theme colour: custom.
		$n = $all[1];
		$this->assertNoticeShell( $n, 'warning' );
		$this->assertSame( '#cf2e2e', strtolower( $n['style']['background-color'] ?? '' ) );
		$this->assertNotContains( 'has-vivid-red-background-color', $n['classes'] );
		$this->assertContains( 'has-ink-color', $n['classes'] );
		$this->assertContains( 'has-huge-font-size', $n['classes'] );
		$this->assertPadding( $n, 'var(--wp--preset--spacing--50)' );
		$this->assertContains( 'is-style-outlined', $n['classes'] );
		$this->assertSame( 1, $n['xpath']->query( './*[' . WPSB\ContentBlocks\has_class_xpath( 'wp-block-acme-notice-box__icon' ) . '][@aria-hidden="true"]', $n['node'] )->length );

		// Invalid colour dropped, padding 0 dropped, custom class kept.
		$n = $all[2];
		$this->assertNoticeShell( $n, 'info' );
		$this->assertArrayNotHasKey( 'background-color', $n['style'] );
		$this->assertNotContains( 'has-background', $n['classes'] );
		$this->assertContains( 'has-teal-color', $n['classes'] );
		$this->assertContains( 'has-small-font-size', $n['classes'] );
		$this->assertArrayNotHasKey( 'padding-top', $n['style'] );
		$this->assertContains( 'custom-note', $n['classes'] );
		$this->assertContains( 'is-style-outlined', $n['classes'] );

		// Nested in columns, with an anchor.
		$n = $all[3];
		$this->assertContains( 'has-blush-background-color', $n['classes'] );
		$this->assertSame( 'nested-note', $n['attrs']['id'] ?? null );
		$this->assertStringContainsString( 'Nested in columns.', $n['text'] );
	}

	public function test_statistics_are_converted(): void {
		$all = blocks( render_slug( 'acme-in-numbers', 'page' ), 'wp-block-acme-stat' );
		$this->assertCount( 3, $all );

		// Default 48px number: no size of its own, the theme decides.
		$this->assertArrayNotHasKey( 'font-size', $all[0]['style'] );
		$this->assertArrayNotHasKey( 'color', $all[0]['style'] );
		foreach ( $all[0]['classes'] as $class ) {
			$this->assertDoesNotMatchRegularExpression( '/^has-.*(font-size|color)$/', $class );
		}
		$this->assertSame( '99.9%uptime', str_replace( ' ', '', $all[0]['text'] ) );

		$this->assertContains( 'has-navy-color', $all[1]['classes'] );
		$this->assertContains( 'has-x-large-font-size', $all[1]['classes'] );
		$this->assertContains( 'is-style-card', $all[1]['classes'] );
		$this->assertContains( 'has-text-align-center', $all[1]['classes'] );
		$this->assertNotContains( 'is-boxed', $all[1]['classes'] );
		$this->assertArrayNotHasKey( 'color', $all[1]['style'] );

		$this->assertSame( '#123456', strtolower( $all[2]['style']['color'] ?? '' ) );
		$this->assertSame( '60px', $all[2]['style']['font-size'] ?? null );
		$this->assertContains( 'has-text-color', $all[2]['classes'] );

		$xpath = $all[2]['xpath'];
		$value = $xpath->query( './/*[' . WPSB\ContentBlocks\has_class_xpath( 'wp-block-acme-stat__value' ) . ']', $all[2]['node'] )->item( 0 );
		$label = $xpath->query( './/*[' . WPSB\ContentBlocks\has_class_xpath( 'wp-block-acme-stat__label' ) . ']', $all[2]['node'] )->item( 0 );
		$this->assertSame( '3.2M', $value ? trim( $value->textContent ) : null );
		$this->assertSame( 'monthly readers', $label ? trim( $label->textContent ) : null );
		$this->assertStringContainsString( '<em>monthly</em>', $label ? $label->ownerDocument->saveHTML( $label ) : '' );
	}

	public function test_content_saved_in_the_new_format_renders_standard_markup(): void {
		$id   = $this->create_post(
			array(
				'post_content' => wp_slash(
					'<!-- wp:acme/notice-box {"tone":"warning","backgroundColor":"navy","style":{"color":{"text":"#fafafa"},"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"fontSize":"medium","className":"is-style-outlined"} -->' . "\n"
					. '<div class="wp-block-acme-notice-box is-tone-warning is-style-outlined has-text-color has-navy-background-color has-background has-medium-font-size" style="color:#fafafa;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)" role="note"><span class="wp-block-acme-notice-box__icon" aria-hidden="true"></span><div class="wp-block-acme-notice-box__body"><!-- wp:paragraph -->' . "\n"
					. '<p>Brand new.</p>' . "\n"
					. '<!-- /wp:paragraph --></div></div>' . "\n"
					. '<!-- /wp:acme/notice-box -->'
				),
			)
		);
		$post = get_post( $id );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();
		$all = $this->notices( $html );
		$this->assertCount( 1, $all, $html );
		foreach ( array( 'has-navy-background-color', 'has-background', 'has-text-color', 'has-medium-font-size', 'is-style-outlined', 'is-tone-warning' ) as $class ) {
			$this->assertContains( $class, $all[0]['classes'] );
		}
		$this->assertSame( '#fafafa', $all[0]['style']['color'] ?? null );
		$this->assertPadding( $all[0], 'var(--wp--preset--spacing--40)' );
		$this->assertStringContainsString( 'Brand new.', $all[0]['text'] );
	}

	public function test_plugin_css_does_not_override_the_theme(): void {
		foreach ( array( 'acme/notice-box', 'acme/stat' ) as $name ) {
			$type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
			$this->assertNotNull( $type, "$name not registered" );
			$css = '';
			foreach ( (array) $type->style_handles as $handle ) {
				$style = wp_styles()->registered[ $handle ] ?? null;
				if ( ! $style ) {
					continue;
				}
				if ( is_string( $style->src ) && '' !== $style->src ) {
					$path = str_replace( content_url(), WP_CONTENT_DIR, strtok( $style->src, '?' ) );
					if ( is_file( $path ) ) {
						$css .= file_get_contents( $path );
					}
				}
				$css .= implode( "\n", (array) ( $style->extra['after'] ?? array() ) );
			}
			$this->assertDoesNotMatchRegularExpression( '/(background|padding|border)[a-z-]*\s*:/i', $css, "$name front-end CSS must leave colours, padding and borders to the theme" );
			$this->assertDoesNotMatchRegularExpression( '/(^|[;{\s])color\s*:/i', $css, "$name front-end CSS must not set colours" );
		}
	}
}

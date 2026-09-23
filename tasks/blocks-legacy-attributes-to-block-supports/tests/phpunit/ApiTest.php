<?php
/**
 * Mobile app API: GET /acme-blocks/v1/posts/<id>/notices.
 */

use function WPSB\ContentBlocks\post_id;

class ApiTest extends WPSB\TestCase {

	private function notices( string $slug ): array {
		$res = $this->rest( 'GET', '/acme-blocks/v1/posts/' . post_id( $slug ) . '/notices' );
		$this->assertSame( 200, $res->get_status() );
		return $this->rest_data( $res );
	}

	public function test_1_0_notices(): void {
		$this->assertSame(
			array(
				array( 'tone' => 'warning', 'background' => null, 'text' => null, 'padding' => null, 'font_size' => null, 'outlined' => false, 'content' => 'The office is closed on Friday.' ),
				array( 'tone' => 'info', 'background' => '#fff3cd', 'text' => '#664d03', 'padding' => 16, 'font_size' => 24, 'outlined' => false, 'content' => 'Palette colours from 1.0.' ),
				array( 'tone' => 'error', 'background' => '#abcdef', 'text' => '#ffffff', 'padding' => 18, 'font_size' => 15, 'outlined' => false, 'content' => 'Custom colours from 1.0. First Second' ),
			),
			$this->notices( 'notices-v1' )
		);
	}

	public function test_1_3_notices(): void {
		$this->assertSame(
			array(
				array( 'tone' => 'success', 'background' => null, 'text' => null, 'padding' => null, 'font_size' => null, 'outlined' => false, 'content' => 'No colours chosen.' ),
				array( 'tone' => 'warning', 'background' => '#cf2e2e', 'text' => '#1f2937', 'padding' => 32, 'font_size' => 48, 'outlined' => true, 'content' => 'Outage Core default red, not a theme colour.' ),
				array( 'tone' => 'info', 'background' => null, 'text' => '#0f766e', 'padding' => null, 'font_size' => 14, 'outlined' => true, 'content' => 'Named colour and a custom class.' ),
				array( 'tone' => 'info', 'background' => '#f8d7da', 'text' => null, 'padding' => null, 'font_size' => null, 'outlined' => false, 'content' => 'Nested in columns.' ),
			),
			$this->notices( 'notices-v2' )
		);
	}

	public function test_notices_saved_in_the_new_format(): void {
		$id = $this->create_post(
			array(
				'post_content' => wp_slash(
					'<!-- wp:acme/notice-box {"backgroundColor":"navy","textColor":"white","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","right":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20"}}},"fontSize":"x-large","className":"is-style-outlined"} -->' . "\n"
					. '<div class="wp-block-acme-notice-box is-tone-info is-style-outlined has-white-color has-navy-background-color has-text-color has-background has-x-large-font-size" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)" role="note"><span class="wp-block-acme-notice-box__icon" aria-hidden="true"></span><div class="wp-block-acme-notice-box__body"><!-- wp:paragraph -->' . "\n<p>Preset one.</p>\n<!-- /wp:paragraph --></div></div>\n<!-- /wp:acme/notice-box -->\n\n"
					. '<!-- wp:acme/notice-box {"tone":"error","style":{"color":{"background":"#0a0b0c"},"spacing":{"padding":{"top":"13px","right":"13px","bottom":"13px","left":"13px"}},"typography":{"fontSize":"21px"}}} -->' . "\n"
					. '<div class="wp-block-acme-notice-box is-tone-error has-background" style="background-color:#0a0b0c;padding-top:13px;padding-right:13px;padding-bottom:13px;padding-left:13px;font-size:21px" role="note"><span class="wp-block-acme-notice-box__icon" aria-hidden="true"></span><div class="wp-block-acme-notice-box__body"><!-- wp:paragraph -->' . "\n<p>Custom one.</p>\n<!-- /wp:paragraph --></div></div>\n<!-- /wp:acme/notice-box -->"
				),
			)
		);
		$res = $this->rest( 'GET', '/acme-blocks/v1/posts/' . $id . '/notices' );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame(
			array(
				array( 'tone' => 'info', 'background' => '#1e3a8a', 'text' => '#ffffff', 'padding' => 8, 'font_size' => 32, 'outlined' => true, 'content' => 'Preset one.' ),
				array( 'tone' => 'error', 'background' => '#0a0b0c', 'text' => null, 'padding' => 13, 'font_size' => 21, 'outlined' => false, 'content' => 'Custom one.' ),
			),
			$this->rest_data( $res )
		);
	}

	public function test_private_posts_stay_private(): void {
		$id  = post_id( 'internal-notice' );
		$res = $this->rest( 'GET', '/acme-blocks/v1/posts/' . $id . '/notices' );
		$this->assertContains( $res->get_status(), array( 401, 403 ) );
		$this->assertStringNotContainsString( 'salary', wp_json_encode( $this->rest_data( $res ) ) );

		$this->login_as( 'administrator' );
		$res = $this->rest( 'GET', '/acme-blocks/v1/posts/' . $id . '/notices' );
		$this->assertSame( 200, $res->get_status() );
		$data = $this->rest_data( $res );
		$this->assertSame( '#1e3a8a', $data[0]['background'] );
		$this->assertSame( '#ffffff', $data[0]['text'] );
		$this->assertSame( 'error', $data[0]['tone'] );
	}
}

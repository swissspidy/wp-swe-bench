<?php
/**
 * Front-end markup of callouts in every saved format.
 */

use function WPSB\Callouts\callouts;
use function WPSB\Callouts\render_content;
use function WPSB\Callouts\render_slug;
use function WPSB\Callouts\squish;

class CalloutFrontEndTest extends WPSB\TestCase {

	private function assertCallout( array $c, string $type, string $label, ?string $title ): void {
		$this->assertSame( 'aside', $c['tag'], 'Callout wrapper must be an <aside>' );
		$this->assertContains( 'wp-block-acme-callout', $c['classes'] );
		$this->assertContains( 'is-type-' . $type, $c['classes'], 'Missing is-type-' . $type . ' in ' . implode( ' ', $c['classes'] ) );
		$this->assertSame( 'note', $c['attrs']['role'] ?? null, 'role="note" missing' );
		$this->assertSame( $label, $c['attrs']['aria-label'] ?? null, 'aria-label must be the type label' );
		if ( null === $title ) {
			$this->assertNull( $c['title'], 'A callout without a title must not output a title element' );
		} else {
			$this->assertNotNull( $c['title'], 'Title element (.wp-block-acme-callout__title) missing' );
			$this->assertSame( 'p', $c['title_tag'] );
			$this->assertSame( $title, squish( $c['title'] ) );
		}
		$this->assertNotNull( $c['body'], 'Body element (div.wp-block-acme-callout__body) missing' );
		$this->assertSame( 'div', strtolower( $c['body']->nodeName ) );
	}

	private function assertNoLegacyClasses( string $html ): void {
		foreach ( callouts( $html ) as $c ) {
			foreach ( $c['classes'] as $cls ) {
				$this->assertDoesNotMatchRegularExpression( '/^(callout|callout-.+|acme-callout.*)$/', $cls, 'Legacy class still rendered: ' . $cls );
			}
		}
		$this->assertDoesNotMatchRegularExpression( '/(?<![\w-])acme-callout(__content|__title|--[a-z]+)?(?![\w-])/', $html, 'Legacy acme-callout classes still rendered' );
		$this->assertDoesNotMatchRegularExpression( '/class="[^"]*\bcallout-(info|success|warning|danger|tip)\b/', $html );
	}

	private function first_child_element( \DOMElement $el ): ?\DOMElement {
		foreach ( $el->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				return $child;
			}
		}
		return null;
	}

	public function test_callout_from_1_0_renders_new_markup(): void {
		$html = render_slug( 'legacy-v0-callout' );
		$all  = callouts( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertCallout( $all[0], 'warning', 'Warning', 'Heads up' );

		$p = $this->first_child_element( $all[0]['body'] );
		$this->assertNotNull( $p );
		$this->assertSame( 'p', strtolower( $p->nodeName ), 'Legacy text must be rendered as a paragraph inside the body' );
		$this->assertStringContainsString( '<a href="https://example.org/docs">read the docs</a>', $all[0]['body_html'] );
		$this->assertStringContainsString( '<strong>first</strong>', $all[0]['body_html'] );
		$this->assertStringContainsString( 'Before you start the migration', $html, 'Surrounding content must be kept' );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_callouts_from_1_3_render_new_markup(): void {
		$html = render_slug( 'v1-callout' );
		$all  = callouts( $html );
		$this->assertCount( 2, $all, $html );

		$this->assertCallout( $all[0], 'danger', 'Danger', 'Do not delete' );
		$this->assertStringContainsString( '<em>not</em>', $all[0]['title_html'], 'Inline title formatting must be kept' );
		$this->assertStringContainsString( 'Deleting the <strong>production</strong> database is irreversible.', $all[0]['body_html'] );

		$this->assertCallout( $all[1], 'info', 'Info', null );
		$this->assertSame( 'An info callout without a title.', squish( $all[1]['body']->textContent ) );
		$p = $this->first_child_element( $all[1]['body'] );
		$this->assertSame( 'p', $p ? strtolower( $p->nodeName ) : null );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_anchor_is_preserved(): void {
		$all = callouts( render_slug( 'anchored-callout' ) );
		$this->assertCount( 1, $all );
		$this->assertCallout( $all[0], 'success', 'Success', 'Solved' );
		$this->assertSame( 'faq-note', $all[0]['attrs']['id'] ?? null, 'The HTML anchor (id) of existing callouts must survive' );
	}

	public function test_nested_callouts_render(): void {
		$html = render_slug( 'nested-callouts', 'page' );
		$all  = callouts( $html );
		$this->assertCount( 2, $all, $html );
		$this->assertCallout( $all[0], 'success', 'Success', 'Column one' );
		$this->assertStringContainsString( 'Nested inside columns.', $all[0]['body_html'] );
		$this->assertCallout( $all[1], 'info', 'Info', 'Old one in a group' );
		$this->assertStringContainsString( 'Still from version 1.0.', $all[1]['body_html'] );
		$this->assertStringContainsString( 'wp-block-columns', $html );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_custom_type_registered_by_filter(): void {
		$all = callouts( render_slug( 'custom-type-callout' ) );
		$this->assertCount( 1, $all );
		$this->assertCallout( $all[0], 'tip', 'Tip', 'Pro tip' );
	}

	public function test_synced_pattern_callout(): void {
		$html = render_slug( 'uses-synced-callout' );
		$all  = callouts( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertCallout( $all[0], 'warning', 'Warning', 'Maintenance window' );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_classic_shortcodes_render_new_markup(): void {
		$html = render_slug( 'shortcode-callout' );
		$all  = callouts( $html );
		$this->assertCount( 2, $all, $html );
		$this->assertCallout( $all[0], 'warning', 'Warning', 'Old school' );
		$this->assertStringContainsString( '<em>body</em>', $all[0]['body_html'] );
		$this->assertStringContainsString( 'Shortcode', $all[0]['body']->textContent );
		// No type => the site's default type from the settings ("success" on this site).
		$this->assertCallout( $all[1], 'success', 'Success', null );
		$this->assertStringContainsString( 'A callout without a type.', $all[1]['body']->textContent );
		$this->assertStringNotContainsString( '[callout', $html );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_mixed_formats_in_one_post(): void {
		$html = render_slug( 'mixed-callouts' );
		$all  = callouts( $html );
		$this->assertCount( 3, $all, $html );
		$this->assertCallout( $all[0], 'danger', 'Danger', 'Legacy danger' );
		$this->assertCallout( $all[1], 'warning', 'Warning', 'Newer warning' );
		$this->assertCallout( $all[2], 'success', 'Success', 'In a shortcode block' );
		$this->assertNoLegacyClasses( $html );
	}

	public function test_unknown_type_falls_back_to_info(): void {
		$content = '<!-- wp:acme/callout {"type":"note"} -->' . "\n" .
			'<div class="wp-block-acme-callout acme-callout acme-callout--note"><p class="acme-callout__title">Old note</p><div class="acme-callout__content">Type no longer exists.</div></div>' . "\n" .
			'<!-- /wp:acme/callout -->';
		$all     = callouts( render_content( $content ) );
		$this->assertCount( 1, $all );
		$this->assertCallout( $all[0], 'info', 'Info', 'Old note' );
		$this->assertNotContains( 'is-type-note', $all[0]['classes'] );
	}

	public function test_crafted_type_attribute_is_neutralized(): void {
		$payload = '\" onmouseover=\"alert(1)\" data-x=\"';
		$content = '<!-- wp:acme/callout {"type":"' . $payload . '"} -->' . "\n" .
			'<div class="wp-block-acme-callout acme-callout acme-callout--x"><div class="acme-callout__content">Hi</div></div>' . "\n" .
			'<!-- /wp:acme/callout -->' . "\n\n" .
			'<!-- wp:acme/callout {"type":"<script>alert(2)</script>"} -->' . "\n" .
			'<div class="wp-block-acme-callout callout callout-x"><strong>T</strong><p>Body</p></div>' . "\n" .
			'<!-- /wp:acme/callout -->' . "\n\n" .
			'[callout type="&quot; onclick=&quot;alert(3)" title="<img src=x onerror=alert(4)>"]Text[/callout]';
		$html    = render_content( $content );
		$this->assertStringNotContainsString( 'onmouseover', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$all = callouts( $html );
		$this->assertCount( 3, $all, $html );
		foreach ( $all as $c ) {
			$this->assertContains( 'is-type-info', $c['classes'] );
			foreach ( array_keys( $c['attrs'] ) as $name ) {
				$this->assertDoesNotMatchRegularExpression( '/^(on|data-x)/i', $name, 'Injected attribute on the wrapper: ' . $name );
			}
		}
	}

	public function test_type_labels_follow_the_filter(): void {
		$filter = static function ( $types ) {
			$types['warning'] = 'Careful!';
			return $types;
		};
		add_filter( 'acme_callouts_types', $filter );
		try {
			$all = callouts( render_slug( 'legacy-v0-callout' ) );
		} finally {
			remove_filter( 'acme_callouts_types', $filter );
		}
		$this->assertSame( 'Careful!', $all[0]['attrs']['aria-label'] ?? null );
	}

	public function test_no_php_warnings_while_rendering(): void {
		$errors = array();
		set_error_handler(
			static function ( $no, $str, $file, $line ) use ( &$errors ) {
				if ( false !== strpos( $file, 'acme-callouts' ) ) {
					$errors[] = "$str in $file:$line";
				}
				return false;
			}
		);
		try {
			foreach ( array( 'legacy-v0-callout', 'v1-callout', 'anchored-callout', 'custom-type-callout', 'shortcode-callout', 'mixed-callouts', 'uses-synced-callout' ) as $slug ) {
				render_slug( $slug );
			}
			render_slug( 'nested-callouts', 'page' );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $errors );
	}

	public function test_callout_counter_still_counts_all_formats(): void {
		$content = file_get_contents( __DIR__ . '/fixtures/mixed-callouts.html' ) . "\n\n" . file_get_contents( __DIR__ . '/fixtures/nested-callouts.html' );
		$id      = $this->create_post( array( 'post_content' => $content ) );
		$this->assertSame( '5', (string) get_post_meta( $id, '_acme_callout_count', true ) );
	}
}

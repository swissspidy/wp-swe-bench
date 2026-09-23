<?php
/**
 * Server-rendered FAQ markup, served by the real (Playground) web server.
 */

use function WPSB\Faq\faqs;
use function WPSB\Faq\ids;
use function WPSB\Faq\json_ld;
use function WPSB\Faq\script_srcs;

class FaqFrontEndTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] */
	private array $cleanup = array();

	protected function tearDown(): void {
		foreach ( $this->cleanup as $id ) {
			wp_delete_post( $id, true );
		}
		parent::tearDown();
	}

	private function get( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return $res['body'];
	}

	private function publish( string $content, string $slug ): string {
		$this->login_as( 'administrator' );
		$id = $this->create_post( array( 'post_content' => wp_slash( $content ), 'post_name' => $slug ) );
		wp_set_current_user( 0 );
		$this->cleanup[] = $id;
		return wp_make_link_relative( get_permalink( $id ) );
	}

	/** Checks every accessibility relation of one item; $open = expected state. */
	private function assertItem( array $item, bool $open, string $html ): void {
		$ids = ids( $html );
		$this->assertNotNull( $item['button'], 'Each question must be a <button class="acme-faq__toggle">' );
		$this->assertSame( 'button', $item['button_type'], 'type="button"' );
		$this->assertSame( 'h3', $item['heading_tag'], 'The button must sit in the question heading (<h3 class="acme-faq__heading">)' );
		$this->assertStringContainsString( 'acme-faq__heading', (string) $item['heading_class'] );
		$this->assertNotNull( $item['panel'], 'Each answer must be a .acme-faq__panel' );
		$this->assertSame( 'region', $item['role'] );
		$this->assertNotEmpty( $item['panel_id'] );
		$this->assertNotEmpty( $item['button_id'] );
		$this->assertSame( $item['panel_id'], $item['controls'], 'aria-controls must point at the answer panel' );
		$this->assertSame( $item['button_id'], $item['labelledby'], 'aria-labelledby must point at the question button' );
		$this->assertSame( 1, count( array_keys( $ids, $item['panel_id'], true ) ), 'Panel id must be unique' );
		$this->assertSame( 1, count( array_keys( $ids, $item['button_id'], true ) ), 'Button id must be unique' );
		$this->assertSame( 1, count( array_keys( $ids, $item['id'], true ) ), 'Item id must be unique: ' . $item['id'] );
		$this->assertSame( $open ? 'true' : 'false', $item['expanded'], 'aria-expanded of "' . $item['question'] . '"' );
		$this->assertSame( ! $open, $item['hidden'], 'hidden attribute of the answer of "' . $item['question'] . '"' );
	}

	public function test_faq_from_1_0_renders_the_accessible_markup(): void {
		$html = $this->get( '/help-legacy-v1/' );
		$all  = faqs( $html );
		$this->assertCount( 1, $all );
		$items = $all[0]['items'];
		$this->assertCount( 3, $items, 'Each question of the 1.0 definition list becomes an item' );
		$this->assertSame( array( 'faq-how-do-i-reset-my-password', 'faq-can-i-change-my-username', 'faq-where-are-my-invoices' ), array_column( $items, 'id' ) );
		foreach ( $items as $item ) {
			$this->assertItem( $item, false, $html );
		}
		$this->assertSame( 'How do I reset my password?', $items[0]['question'] );
		$this->assertStringContainsString( '<em>username</em>', $items[1]['question_html'], 'Inline formatting of questions is kept' );
		$this->assertStringContainsString( '<a href="/wp-login.php?action=lostpassword">lost password</a>', $items[0]['answer_html'] );
		$this->assertStringContainsString( '<strong>Account → Billing</strong>', $items[2]['answer_html'] );
		$this->assertStringNotContainsString( '<dl', $html );
		$this->assertStringContainsString( 'Still stuck? Contact support.', $html );
	}

	public function test_faq_from_1_3_open_first_and_custom_anchor(): void {
		$html = $this->get( '/help-center/' );
		$all  = faqs( $html );
		$this->assertCount( 1, $all );
		$items = $all[0]['items'];
		$this->assertSame( array( 'faq-what-is-acme', 'faq-how-long-does-shipping-take', 'billing', 'faq-is-there-a-rest-api' ), array_column( $items, 'id' ), 'Items without an HTML anchor get faq-{slug}; custom anchors are kept' );
		$this->assertItem( $items[0], true, $html );
		$this->assertItem( $items[1], false, $html );
		$this->assertItem( $items[2], false, $html );
		$this->assertItem( $items[3], false, $html );
		$this->assertStringContainsString( '<code>REST</code>', $items[3]['question_html'] );
		$this->assertStringContainsString( '<li>USA: 3 days</li>', $items[1]['answer_html'], 'Rich answers (lists) are kept' );
		$this->assertStringContainsString( 'Acme makes <strong>everything</strong>.', $items[0]['answer_html'] );
	}

	public function test_several_faqs_on_a_page_have_unique_ids(): void {
		$html = $this->get( '/two-faqs/' );
		$all  = faqs( $html );
		$this->assertCount( 2, $all );
		$this->assertSame( array( 'faq-what-is-acme', 'faq-do-you-ship-abroad', 'faq-can-i-return-an-item' ), array_column( $all[0]['items'], 'id' ) );
		$this->assertSame( array( 'faq-what-is-acme-2', 'faq-who-founded-acme' ), array_column( $all[1]['items'], 'id' ), 'Duplicate questions get -2, -3, …' );
		foreach ( $all[0]['items'] as $item ) {
			$this->assertItem( $item, false, $html );
		}
		$this->assertItem( $all[1]['items'][0], true, $html );
		$this->assertItem( $all[1]['items'][1], false, $html );

		$html = $this->get( '/faq-in-group/' );
		$all  = faqs( $html );
		$this->assertCount( 1, $all );
		$this->assertSame( array( 'faq-is-this-nested', 'faq-does-it-still-work' ), array_column( $all[0]['items'], 'id' ) );
	}

	public function test_no_jquery_on_pages_with_faqs(): void {
		foreach ( array( '/help-legacy-v1/', '/help-center/', '/two-faqs/' ) as $path ) {
			$html = $this->get( $path );
			foreach ( script_srcs( $html ) as $src ) {
				$this->assertDoesNotMatchRegularExpression( '#/jquery(-migrate)?(\.min)?\.js#', $src, "$path loads jQuery" );
			}
			$this->assertStringNotContainsString( "id='jquery-core-js'", $html );
			$this->assertStringNotContainsString( 'id="jquery-core-js"', $html );
		}
	}

	public function test_importer_format_and_settings(): void {
		$make = static function ( string $question, string $answer, array $extra = array() ) {
			return '<!-- wp:acme/faq-item ' . wp_json_encode( array_merge( array( 'question' => $question ), $extra ), JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP ) . " -->\n"
				. "<!-- wp:paragraph -->\n<p>$answer</p>\n<!-- /wp:paragraph -->\n<!-- /wp:acme/faq-item -->";
		};
		$path = $this->publish(
			'<!-- wp:acme/faq {"allowMultiple":true} -->' . "\n"
			. $make( 'First imported?', 'Answer one.' ) . "\n\n"
			. $make( 'Second <em>imported</em>?', 'Answer two.' ) . "\n\n"
			. $make( '<img src=x onerror=alert(1)>Evil "question"', 'Answer three.' ) . "\n"
			. '<!-- /wp:acme/faq -->',
			'wpsb-imported-faq'
		);
		$html = $this->get( $path );
		$all  = faqs( $html );
		$this->assertCount( 1, $all );
		$items = $all[0]['items'];
		$this->assertCount( 3, $items );
		$this->assertSame( array( 'faq-first-imported', 'faq-second-imported' ), array( $items[0]['id'], $items[1]['id'] ) );
		foreach ( $items as $item ) {
			$this->assertItem( $item, false, $html );
		}
		$this->assertStringContainsString( '<em>imported</em>', $items[1]['question_html'] );
		$this->assertStringNotContainsString( 'onerror', $html, 'Question markup must be sanitized' );
		$this->assertSame( 'Answer two.', $items[1]['answer'] );
		$ld = json_ld( $html );
		$this->assertCount( 1, $ld, 'Structured data must also work for FAQs in the current format' );
		$this->assertSame( array( 'First imported?', 'Second imported?' ), array_slice( array_column( $ld[0]['mainEntity'], 'name' ), 0, 2 ) );
		$this->assertSame( 'Answer one.', $ld[0]['mainEntity'][0]['acceptedAnswer']['text'] );

		$path = $this->publish(
			'<!-- wp:acme/faq {"openFirst":true} -->' . "\n" . $make( 'Only one', 'Yes.' ) . "\n<!-- /wp:acme/faq -->",
			'wpsb-imported-open-first'
		);
		$html  = $this->get( $path );
		$items = faqs( $html )[0]['items'];
		$this->assertItem( $items[0], true, $html );
	}

	public function test_structured_data_still_describes_all_questions(): void {
		$html = $this->get( '/help-center/' );
		$ld   = json_ld( $html );
		$this->assertCount( 1, $ld, 'FAQPage JSON-LD expected' );
		$names = array_column( $ld[0]['mainEntity'], 'name' );
		$this->assertSame( array( 'What is Acme?', 'How long does shipping take?', 'How do I pay?', 'Is there a REST API?' ), $names );
		$this->assertStringEndsWith( '/help-center/#faq-what-is-acme', $ld[0]['mainEntity'][0]['url'] );
		$this->assertContains( 'faq-what-is-acme', ids( $html ), 'The structured data URL must point at the question' );

		$html = $this->get( '/help-legacy-v1/' );
		$ld   = json_ld( $html );
		$this->assertCount( 1, $ld );
		$this->assertSame( array( 'How do I reset my password?', 'Can I change my username?', 'Where are my invoices?' ), array_column( $ld[0]['mainEntity'], 'name' ) );
		$this->assertSame( 'No, usernames are permanent.', $ld[0]['mainEntity'][1]['acceptedAnswer']['text'] );
	}

	public function test_structured_data_setting_still_works(): void {
		$saved = get_option( 'acme_faq_options' );
		update_option( 'acme_faq_options', array( 'structured_data' => false ) );
		try {
			$this->assertCount( 0, json_ld( $this->get( '/help-center/' ) ) );
		} finally {
			update_option( 'acme_faq_options', $saved );
		}
		$this->assertCount( 1, json_ld( $this->get( '/help-center/' ) ) );
	}
}

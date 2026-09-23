<?php
/**
 * The classic [acme_contact] shortcode keeps working (pass-to-pass).
 */

use function WPSB\Contact\cookies_from;
use function WPSB\Contact\xpath;

class ShortcodeTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function setUp(): void {
		parent::setUp();
		$this->clear_mails();
	}

	private function form(): array {
		$res = $this->http( 'GET', '/contact/' );
		$this->assertSame( 200, $res['status'] );
		$x    = xpath( $res['body'] );
		$form = $x->query( '//form[@id="acme-contact"]' );
		$this->assertSame( 1, $form->length );
		$data = array();
		foreach ( $x->query( './/input[@type="hidden"]', $form->item( 0 ) ) as $input ) {
			$data[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
		}
		$options = array();
		foreach ( $x->query( './/select[@name="acme_subject"]/option', $form->item( 0 ) ) as $o ) {
			$options[] = $o->getAttribute( 'value' );
		}
		return array( html_entity_decode( $form->item( 0 )->getAttribute( 'action' ) ), $data, $options );
	}

	private function send( string $action, array $data ): string {
		$res = $this->http( 'POST', $action, array( 'body' => $data ) );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$cookie = cookies_from( $res );
		return $this->http( 'GET', $res['headers']['location'], $cookie ? array( 'cookie' => $cookie ) : array() )['body'];
	}

	public function test_shortcode_form_sends_mail(): void {
		list( $action, $data, $options ) = $this->form();
		$this->assertSame( array( '', 'Sales', 'Support', 'Press' ), $options );
		$data += array(
			'acme_name'    => 'Classic Carl',
			'acme_email'   => 'carl@example.org',
			'acme_subject' => 'Support',
			'acme_message' => 'Old but gold',
			'acme_website' => '',
		);
		$page = $this->send( $action, $data );
		$this->assertStringContainsString( 'Thanks! We will get back to you within one business day.', $page );
		$mails = $this->mails();
		$this->assertCount( 1, $mails );
		$this->assertStringStartsWith( '[Acme Web] ', $mails[0]['subject'] );
		$this->assertStringContainsString( 'Support', $mails[0]['subject'] );
		$this->assertStringContainsString( 'Message: Old but gold', $mails[0]['message'] );
	}

	public function test_shortcode_validation(): void {
		list( $action, $data ) = $this->form();
		$data += array(
			'acme_name'    => 'Classic Carl',
			'acme_email'   => 'carl@',
			'acme_subject' => 'Not an option',
			'acme_message' => 'x',
		);
		$page = $this->send( $action, $data );
		$this->assertStringContainsString( 'Please enter a valid email address.', $page );
		$this->assertStringContainsString( 'Please choose one of the options.', $page );
		$this->assertSame( array(), $this->mails() );
	}

	public function test_classic_post_still_renders_the_shortcode(): void {
		$res = $this->http( 'GET', '/classic-contact/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'id="acme-contact"', $res['body'] );
		$this->assertStringContainsString( 'Send it', $res['body'] );
		$this->assertStringNotContainsString( '[acme_contact', $res['body'] );
	}
}

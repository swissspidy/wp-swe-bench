<?php
/**
 * F-1: values from submissions are shown as text in the inbox, the single view and the e-mail.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\by_email;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\xpath;

class EscapingTest extends HttpCase {

	/** No element was injected by the stored values. */
	private function assertNoInjectedMarkup( string $html, string $where ): void {
		$this->assertAbsent( '<script>alert("acme-xss-1")', $html, "$where: raw script tag" );
		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*onerror/i', $html, "$where: raw img tag" );
		$xp = xpath( $html );
		$this->assertSame( 0, $xp->query( "//script[contains(., 'acme-xss')]" )->length, "$where: script element" );
		$this->assertSame( 0, $xp->query( '//*[@onerror]' )->length, "$where: element with an event handler" );
	}

	public function test_inbox_list_shows_values_as_text(): void {
		$res = $this->inbox( 'admin', array( 's' => 'mallory' ) );
		$this->assertPresent( 'mallory@example.org', $res['body'], 'response' );
		$this->assertNoInjectedMarkup( $res['body'], 'list' );
		$this->assertPresent( '&lt;script&gt;alert(', $res['body'], 'the name is shown as text' );
		$this->assertPresent( '&lt;img src=x onerror=alert(', $res['body'], 'the message is shown as text' );

		// Default view (first page) too, as the editors see it.
		$res = $this->inbox( 'erin' );
		$this->assertNoInjectedMarkup( $res['body'], 'list (editor)' );
		$this->assertPresent( '&lt;script&gt;alert(', $res['body'], 'response' );
		$this->assertPresent( 'Visitor 34', $res['body'], 'response' );
	}

	public function test_single_view_shows_values_as_text(): void {
		$res = $this->view( 'admin', sub_id( 'mallory@example.org' ) );
		$this->assertNoInjectedMarkup( $res['body'], 'view' );
		$xp    = xpath( $res['body'] );
		$cells = array();
		foreach ( $xp->query( "//div[contains(@class,'wrap')]//table//tr" ) as $tr ) {
			$th = $xp->query( './th', $tr )->item( 0 );
			$td = $xp->query( './td', $tr )->item( 0 );
			if ( $th && $td ) {
				$cells[ trim( $th->textContent ) ] = trim( $td->textContent );
			}
		}
		$this->assertSame( '<script>alert("acme-xss-1")</script>', $cells['Your name'] ?? null, 'the visitor sees exactly what was typed' );
		$this->assertSame( 'Nice site <img src=x onerror=alert("acme-xss-2")> see you', $cells['Message'] ?? null );
		$this->assertStringContainsString( 'mallory@example.org', $cells['E-mail'] ?? '' );
	}

	public function test_notification_email_shows_values_as_text(): void {
		$res = $this->submit_public(
			'contact-us',
			array(
				'name'    => '<b id="acme-b">Bold Bob</b>',
				'email'   => 'bob@example.org',
				'website' => 'https://bob.example',
				'topic'   => 'Press',
				'message' => "Line one <script>alert(\"acme-xss-mail\")</script>\nLine <i>two</i> & more",
			)
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$this->assertNotNull( by_email( 'bob@example.org' ) );

		$mails = $this->mails_about( 'New submission: Contact us' );
		$this->assertCount( 1, $mails, 'one notification' );
		$mail = $mails[0];
		$this->assertSame( 'jobs@acme-recruiting.example', is_array( $mail['to'] ) ? $mail['to'][0] : $mail['to'] );
		$this->assertStringContainsString( 'text/html', implode( "\n", (array) $mail['headers'] ), 'still an HTML e-mail' );

		$body = $mail['message'];
		$this->assertAbsent( '<script>alert("acme-xss-mail")', $body, 'response' );
		$this->assertAbsent( '<b id="acme-b">', $body, 'response' );
		$this->assertAbsent( '<i>two</i>', $body, 'response' );
		$this->assertPresent( '&lt;script&gt;alert(', $body, 'response' );
		$this->assertPresent( '&lt;b id=', $body, 'response' );

		$xp = xpath( $body );
		$this->assertSame( 0, $xp->query( '//script|//b|//i' )->length, 'no injected elements' );
		$text = preg_replace( '/\s+/', ' ', $xp->query( '//body' )->item( 0 )->textContent );
		$this->assertStringContainsString( '<b id="acme-b">Bold Bob</b>', $text, 'the e-mail shows what was typed' );
		$this->assertStringContainsString( 'Line <i>two</i> & more', $text );
		$this->assertStringContainsString( 'Your name', $text );
		$this->assertStringContainsString( 'Press', $text );
		$this->assertStringContainsString( 'bob@example.org', $text );
		$this->assertSame( 1, $xp->query( "//a[@href='https://bob.example']" )->length, 'website link kept' );
	}
}

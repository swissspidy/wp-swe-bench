<?php
/**
 * R-1: only absolute http(s) website values become links (single view and e-mail).
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\by_email;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\xpath;

class WebsiteLinkTest extends HttpCase {

	/** Every link target in a document. */
	private function hrefs( string $html ): array {
		$out = array();
		foreach ( xpath( $html )->query( '//a[@href]' ) as $a ) {
			$out[] = $a->getAttribute( 'href' );
		}
		return $out;
	}

	private function assertNoScriptLinks( string $html, string $where ): void {
		foreach ( $this->hrefs( $html ) as $href ) {
			$norm = strtolower( preg_replace( '/[\s\x00-\x1f]+/', '', html_entity_decode( $href, ENT_QUOTES | ENT_HTML5 ) ) );
			foreach ( array( 'javascript:', 'data:', 'vbscript:' ) as $scheme ) {
				$this->assertStringStartsNotWith( $scheme, $norm, "$where: link to $href" );
			}
		}
	}

	/** Cell of a label/value table row. */
	private function cell( string $html, string $label ): ?DOMElement {
		$xp = xpath( $html );
		$td = $xp->query( "//tr[normalize-space(th)='{$label}']/td" )->item( 0 );
		return $td instanceof DOMElement ? $td : null;
	}

	public function test_single_view_links_only_web_addresses(): void {
		$html = $this->view( 'admin', sub_id( 'mallory@example.org' ) )['body'];
		$this->assertNoScriptLinks( $html, 'mallory view' );
		$cell = $this->cell( $html, 'Website' );
		$this->assertNotNull( $cell );
		$this->assertSame( 'javascript:alert(document.cookie)', trim( $cell->textContent ), 'shown as text' );
		$this->assertSame( 0, $cell->getElementsByTagName( 'a' )->length );

		$html = $this->view( 'admin', sub_id( 'formula3@example.org' ) )['body'];
		$cell = $this->cell( $html, 'Website' );
		$this->assertSame( 'www.formula3.example', trim( $cell->textContent ) );
		$this->assertSame( 0, $cell->getElementsByTagName( 'a' )->length, 'no scheme: not a link' );

		$html = $this->view( 'erin', sub_id( 'anna@example.org' ) )['body'];
		$cell = $this->cell( $html, 'Portfolio website' );
		$this->assertSame( 1, $cell->getElementsByTagName( 'a' )->length );
		$this->assertSame( 'https://anna.example', $cell->getElementsByTagName( 'a' )->item( 0 )->getAttribute( 'href' ) );
	}

	public function test_email_links_only_web_addresses(): void {
		$cases = array(
			'jsmixed'  => 'JaVaScRiPt:alert(document.domain)',
			'jsspaces' => "  javascript:alert('x')",
			'data'     => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
			'good'     => 'https://ok.example/path?a=1&b=2',
			'http'     => 'http://plain.example',
		);
		foreach ( $cases as $key => $website ) {
			$this->clear_mails();
			$email = "link-{$key}@example.org";
			$res   = $this->submit_public(
				'contact-us',
				array(
					'name'    => "Link {$key}",
					'email'   => $email,
					'website' => $website,
					'topic'   => 'Sales',
					'message' => 'Hello',
				)
			);
			if ( in_array( $key, array( 'good', 'http' ), true ) ) {
				$this->assertRedirectStatus( $res, 'sent' );
			}
			if ( ! by_email( $email ) ) {
				continue; // Rejecting such values at submission time is fine too.
			}
			$mails = $this->mails_about( 'New submission: Contact us' );
			$this->assertCount( 1, $mails, $key );
			$html = $mails[0]['message'];
			$this->assertNoScriptLinks( $html, "e-mail ($key)" );
			$cell = $this->cell( $html, 'Website' );
			$this->assertNotNull( $cell, "$key: website row" );
			if ( 'good' === $key || 'http' === $key ) {
				$this->assertSame( 1, $cell->getElementsByTagName( 'a' )->length, "$key: linked" );
				$this->assertSame( $website, $cell->getElementsByTagName( 'a' )->item( 0 )->getAttribute( 'href' ), "$key: exact target" );
			} else {
				$this->assertSame( 0, $cell->getElementsByTagName( 'a' )->length, "$key: not linked" );
				$this->assertSame( trim( $website ), trim( $cell->textContent ), "$key: shown as text" );
			}
		}
	}
}

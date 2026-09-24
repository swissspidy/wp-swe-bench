<?php
/**
 * R-3 (3): files are downloaded through the plugin, as attachments, by inbox users only.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\absolute;
use function WPSB\Forms\pdf_bytes;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\xpath;

class DownloadTest extends HttpCase {

	/** URL of the link with this text on a submission's single view. */
	private function file_link( string $login, int $id, string $name ): string {
		$html = $this->view( $login, $id )['body'];
		$this->assertAbsent( '/wp-content/uploads/acme-forms/', $html, 'the view links to public file addresses' );
		$a = xpath( $html )->query( "//div[contains(@class,'wrap')]//a[normalize-space(.)='{$name}']" )->item( 0 );
		$this->assertNotNull( $a, "link for $name" );
		$url = absolute( $a->getAttribute( 'href' ) );
		$this->assertStringNotContainsString( '/wp-content/uploads/', $url );
		return $url;
	}

	private function assertAttachment( array $res, string $name, string $bytes ): void {
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 200 ) );
		$this->assertTrue( $bytes === $res['body'], "$name: file content" );
		$this->assertMatchesRegularExpression( '/^attachment\s*;\s*filename="?' . preg_quote( $name, '/' ) . '"?(\s*;|$)/i', $res['headers']['content-disposition'] ?? '', "$name: attachment" );
		$this->assertSame( 'nosniff', strtolower( trim( $res['headers']['x-content-type-options'] ?? '' ) ), "$name: nosniff" );
	}

	private function upload_cv( string $u ): string {
		$bytes = pdf_bytes( "download-{$u}" );
		$res   = $this->submit_public(
			'job-application',
			array(
				'name'  => 'Dora Download',
				'email' => "dl-{$u}@example.org",
			),
			array( 'acme_file_cv' => array( "cv-{$u}.pdf", $bytes, 'application/pdf' ) )
		);
		$this->assertRedirectStatus( $res, 'sent' );
		return $bytes;
	}

	public function test_new_uploads_download_as_attachments(): void {
		$u     = wp_generate_password( 8, false, false );
		$bytes = $this->upload_cv( $u );
		$id    = sub_id( "dl-{$u}@example.org" );
		foreach ( array( 'admin', 'erin' ) as $login ) {
			$url = $this->file_link( $login, $id, "cv-{$u}.pdf" );
			$this->assertAttachment( $this->http( 'GET', $url, array( 'login' => $this->session( $login ) ) ), "cv-{$u}.pdf", $bytes );
		}
	}

	public function test_legacy_files_stay_downloadable(): void {
		$id  = sub_id( 'anna@example.org' );
		$dir = WP_CONTENT_DIR . '/uploads/acme-forms/2025/11/';

		$url = $this->file_link( 'admin', $id, 'cv-anna.pdf' );
		$this->assertAttachment( $this->http( 'GET', $url, array( 'login' => $this->session( 'admin' ) ) ), 'cv-anna.pdf', file_get_contents( $dir . 'cv-anna.pdf' ) );

		$url = $this->file_link( 'admin', $id, 'portfolio.html' );
		$res = $this->http( 'GET', $url, array( 'login' => $this->session( 'admin' ) ) );
		$this->assertAttachment( $res, 'portfolio.html', file_get_contents( $dir . 'portfolio.html' ) );
		$this->assertStringStartsNotWith( 'text/html', strtolower( $res['headers']['content-type'] ?? '' ), 'never served as a page' );

		$url = $this->file_link( 'erin', sub_id( 'ben@example.org' ), 'cv-ben.docx' );
		$this->assertAttachment( $this->http( 'GET', $url, array( 'login' => $this->session( 'erin' ) ) ), 'cv-ben.docx', file_get_contents( WP_CONTENT_DIR . '/uploads/acme-forms/2026/05/cv-ben.docx' ) );
	}

	public function test_downloads_are_for_inbox_users_only(): void {
		$u     = wp_generate_password( 8, false, false );
		$bytes = $this->upload_cv( $u );
		$url   = $this->file_link( 'admin', sub_id( "dl-{$u}@example.org" ), "cv-{$u}.pdf" );
		foreach ( array( 'sally', 'arthur', 'connie' ) as $login ) {
			$res = $this->http( 'GET', $url, array( 'login' => $this->session( $login ) ) );
			$this->assertSame( 403, $res['status'], $login );
			$this->assertAbsent( "download-{$u}", $res['body'], "$login gets the file" );
		}
		$res = $this->http( 'GET', $url );
		$this->assertNotSame( 200, $res['status'], 'logged out' );
		$this->assertAbsent( "download-{$u}", $res['body'], 'logged out gets the file' );

		$legacy = $this->file_link( 'admin', sub_id( 'anna@example.org' ), 'portfolio.html' );
		$res    = $this->http( 'GET', $legacy, array( 'login' => $this->session( 'sally' ) ) );
		$this->assertSame( 403, $res['status'] );
		$this->assertAbsent( 'Anna Andersson: selected work', $res['body'], 'subscriber gets the legacy file' );
	}

	public function test_download_link_cannot_reach_other_files(): void {
		$url = $this->file_link( 'admin', sub_id( 'anna@example.org' ), 'cv-anna.pdf' );
		foreach ( array( 'path', 'file', 'name', 'filename' ) as $param ) {
			$res = $this->http( 'GET', $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $param . '=' . rawurlencode( '../../../../wp-config.php' ), array( 'login' => $this->session( 'admin' ) ) );
			$this->assertAbsent( 'DB_NAME', $res['body'], $param );
			$this->assertAbsent( 'AUTH_KEY', $res['body'], $param );
		}
	}
}

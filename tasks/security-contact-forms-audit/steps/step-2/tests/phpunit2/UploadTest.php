<?php
/**
 * R-3 (1, 2): per-field allowed types, content checks and unguessable stored names.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\by_email;
use function WPSB\Forms\count_rows;
use function WPSB\Forms\files_of;
use function WPSB\Forms\jpeg_bytes;
use function WPSB\Forms\pdf_bytes;
use function WPSB\Forms\png_bytes;
use function WPSB\Forms\upload_files;

class UploadTest extends HttpCase {

	private const HTML = "<!DOCTYPE html>\n<html><head><title>x</title></head><body><script>document.title='pwned'</script></body></html>\n";
	private const SVG  = "<?xml version=\"1.0\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\" onload=\"alert(1)\"><rect width=\"1\" height=\"1\"/></svg>\n";
	private const GIF  = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

	private function apply( string $email, array $files ): array {
		return $this->submit_public(
			'job-application',
			array(
				'name'  => 'Applicant ' . $email,
				'email' => $email,
				'notes' => 'Hello',
			),
			$files
		);
	}

	private function valid_cv(): array {
		return array( 'cv-valid.pdf', pdf_bytes( 'valid' ), 'application/pdf' );
	}

	public function test_allowed_files_are_accepted(): void {
		$u   = wp_generate_password( 8, false, false );
		$res = $this->apply(
			"ok-{$u}@example.org",
			array(
				'acme_file_cv'     => array( "cv-{$u}.pdf", pdf_bytes( $u ), 'application/pdf' ),
				'acme_file_sample' => array( "shot-{$u}.PNG", png_bytes(), 'image/png' ),
				'acme_file_photo'  => array( "me-{$u}.jpg", jpeg_bytes(), 'image/jpeg' ),
			)
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$row = by_email( "ok-{$u}@example.org" );
		$this->assertNotNull( $row );
		$files = files_of( $row );
		$this->assertSame( "cv-{$u}.pdf", $files['cv']['name'] ?? null );
		$this->assertSame( "shot-{$u}.PNG", $files['sample']['name'] ?? null, 'extensions compared case-insensitively' );
		$this->assertSame( "me-{$u}.jpg", $files['photo']['name'] ?? null );

		// A field without its own list takes the default list.
		$res = $this->apply( "txt-{$u}@example.org", array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_photo' => array( 'about-me.txt', "Plain text about me.\n", 'text/plain' ) ) );
		$this->assertRedirectStatus( $res, 'sent' );
		$this->assertSame( 'about-me.txt', files_of( by_email( "txt-{$u}@example.org" ) )['photo']['name'] ?? null );
	}

	public function test_refused_files(): void {
		$cases = array(
			'cv html'            => array( 'acme_file_cv' => array( 'cv.html', self::HTML, 'text/html' ) ),
			'cv svg'             => array( 'acme_file_cv' => array( 'cv.svg', self::SVG, 'image/svg+xml' ) ),
			'cv html as pdf'     => array( 'acme_file_cv' => array( 'cv.pdf', self::HTML, 'application/pdf' ) ),
			'cv php'             => array( 'acme_file_cv' => array( 'cv.php', "<?php echo 'x';\n", 'application/pdf' ) ),
			'cv phtml'           => array( 'acme_file_cv' => array( 'cv.PHTML', "<?php echo 'x';\n", 'application/pdf' ) ),
			'cv txt not listed'  => array( 'acme_file_cv' => array( 'cv.txt', "My CV\n", 'text/plain' ) ),
			'sample html listed' => array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_sample' => array( 'work.html', self::HTML, 'text/html' ) ),
			'sample svg'         => array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_sample' => array( 'work.svg', self::SVG, 'image/svg+xml' ) ),
			'sample html as png' => array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_sample' => array( 'work.png', self::HTML, 'image/png' ) ),
			'photo gif default'  => array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_photo' => array( 'me.gif', self::GIF, 'image/gif' ) ),
			'photo xhtml'        => array( 'acme_file_cv' => $this->valid_cv(), 'acme_file_photo' => array( 'me.xhtml', self::HTML, 'application/xhtml+xml' ) ),
		);
		$rows  = count_rows();
		$files = upload_files();
		$i     = 0;
		foreach ( $cases as $label => $upload ) {
			$email = 'refused-' . ( ++$i ) . '@example.org';
			$res   = $this->apply( $email, $upload );
			$this->assertRedirectStatus( $res, 'invalid' );
			$this->assertNull( by_email( $email ), "$label: nothing stored" );
			$this->assertSame( $files, upload_files(), "$label: no file left on disk" );
		}
		$this->assertSame( $rows, count_rows() );
		$this->assertSame( array(), $this->mails_about( 'New submission' ), 'no notifications' );
	}

	public function test_stored_names_cannot_be_guessed(): void {
		$u      = wp_generate_password( 10, false, false );
		$bytes  = pdf_bytes( "guess-{$u}" );
		$before = upload_files();
		$res    = $this->apply( "guess-{$u}@example.org", array( 'acme_file_cv' => array( "cv-{$u}.pdf", $bytes, 'application/pdf' ) ) );
		$this->assertRedirectStatus( $res, 'sent' );

		$new = array_diff_key( upload_files(), $before );
		$this->assertCount( 1, $new );
		$path = (string) key( $new );
		$this->assertSame( md5( $bytes ), $new[ $path ] );
		$this->assertStringContainsString( '/wp-content/uploads/acme-forms/', $path );
		$this->assertStringNotContainsString( $u, $path, 'stored name does not contain the original name' );
		$this->assertStringNotContainsString( 'cv-', basename( $path ) );
		$this->assertSame( "cv-{$u}.pdf", files_of( by_email( "guess-{$u}@example.org" ) )['cv']['name'] ?? null, 'staff still see the original name' );

		// The old predictable address does not exist.
		$res = $this->http( 'GET', '/wp-content/uploads/acme-forms/' . gmdate( 'Y/m' ) . "/cv-{$u}.pdf", array( 'follow' => true ) );
		$this->assertAbsent( "guess-{$u}", $res['body'], 'file reachable under its original name' );
	}
}

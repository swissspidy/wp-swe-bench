<?php
/**
 * Public forms keep working for visitors: validation, storage, uploads, notifications.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\by_email;
use function WPSB\Forms\count_rows;
use function WPSB\Forms\data_of;
use function WPSB\Forms\files_of;
use function WPSB\Forms\pdf_bytes;
use function WPSB\Forms\upload_files;
use function WPSB\Forms\xpath;

class PublicFormTest extends HttpCase {

	public function test_form_is_rendered_on_the_page(): void {
		$res = $this->get( null, '/contact/' );
		$this->assertSame( 200, $res['status'] );
		$xp   = xpath( $res['body'] );
		$form = $xp->query( "//form[.//input[@name='acme_form_id']]" )->item( 0 );
		$this->assertNotNull( $form );
		$this->assertStringContainsString( 'admin-post.php', $form->getAttribute( 'action' ) );
		foreach ( array( 'name', 'email', 'website', 'topic', 'message' ) as $field ) {
			$this->assertSame( 1, $xp->query( ".//*[@name='acme_fields[{$field}]']", $form )->length, $field );
		}
		$this->assertSame( 3, $xp->query( ".//select[@name='acme_fields[topic]']/option[@value!='']", $form )->length );

		$res = $this->get( null, '/careers/' );
		$this->assertSame( 1, xpath( $res['body'] )->query( "//input[@type='file' and @name='acme_file_cv']" )->length );
	}

	public function test_valid_submission_is_stored_and_notified(): void {
		$before = count_rows();
		$res    = $this->submit_public(
			'contact-us',
			array(
				'name'    => 'Nora Newcomer',
				'email'   => 'nora@example.org',
				'website' => '',
				'topic'   => 'Sales',
				'message' => "Do you ship to Iceland?\nThanks, Nora",
			)
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$this->assertStringContainsString( '/contact/', $res['headers']['location'] );
		$this->assertSame( $before + 1, count_rows() );

		$row = by_email( 'nora@example.org' );
		$this->assertNotNull( $row );
		$this->assertSame( 'new', $row->status );
		$data = data_of( $row );
		$this->assertSame( 'Nora Newcomer', $data['name'] );
		$this->assertSame( "Do you ship to Iceland?\nThanks, Nora", $data['message'] );

		$mails = $this->mails_about( 'New submission: Contact us' );
		$this->assertCount( 1, $mails );
		$this->assertStringStartsWith( '[Acme Recruiting]', $mails[0]['subject'] );
		$this->assertStringContainsString( 'Nora Newcomer', $mails[0]['message'] );
		$this->assertStringContainsString( 'Do you ship to Iceland?', $mails[0]['message'] );
		$this->assertStringContainsString( 'submission=' . $row->id, $mails[0]['message'], 'link to the submission' );

		// Logged-in visitors (e.g. a subscriber) can use the form too.
		$res = $this->submit_public(
			'contact-us',
			array(
				'name'    => 'Sally',
				'email'   => 'sally@example.org',
				'topic'   => 'Support',
				'message' => 'Hi',
			),
			array(),
			'sally'
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$this->assertNotNull( by_email( 'sally@example.org' ) );
	}

	public function test_invalid_submissions_are_rejected(): void {
		$before = count_rows();
		$cases  = array(
			'missing message' => array( 'name' => 'A', 'email' => 'a@example.org', 'topic' => 'Sales', 'message' => '' ),
			'bad e-mail'      => array( 'name' => 'A', 'email' => 'not-an-email', 'topic' => 'Sales', 'message' => 'x' ),
			'unknown option'  => array( 'name' => 'A', 'email' => 'a@example.org', 'topic' => 'Hacking', 'message' => 'x' ),
		);
		foreach ( $cases as $label => $fields ) {
			$res = $this->submit_public( 'contact-us', $fields );
			$this->assertRedirectStatus( $res, 'invalid' );
		}
		$this->assertSame( $before, count_rows(), 'nothing stored' );
		$this->assertSame( array(), $this->mails_about( 'New submission' ), 'no notification' );

		// Job applications need a CV.
		$res = $this->submit_public( 'job-application', array( 'name' => 'Carl', 'email' => 'carl@example.org' ) );
		$this->assertRedirectStatus( $res, 'invalid' );
		$this->assertSame( $before, count_rows() );
	}

	public function test_job_application_with_cv_upload(): void {
		$files_before = upload_files();
		$token        = 'cv-' . wp_generate_password( 8, false, false );
		$bytes        = pdf_bytes( $token );
		$res          = $this->submit_public(
			'job-application',
			array(
				'name'    => 'Carla Candidate',
				'email'   => 'carla@example.org',
				'website' => 'https://carla.example',
				'notes'   => 'See my CV.',
			),
			array( 'acme_file_cv' => array( "{$token}.pdf", $bytes, 'application/pdf' ) )
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$row = by_email( 'carla@example.org' );
		$this->assertNotNull( $row );
		$files = files_of( $row );
		$this->assertSame( "{$token}.pdf", $files['cv']['name'] ?? null, 'original file name kept' );

		$new = array_diff_key( upload_files(), $files_before );
		$this->assertCount( 1, $new, 'one file stored' );
		$this->assertSame( md5( $bytes ), reset( $new ), 'stored unchanged' );
		$this->assertStringContainsString( '/uploads/acme-forms/', key( $new ) );

		$mails = $this->mails_about( 'New submission: Job application' );
		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( "{$token}.pdf", $mails[0]['message'] );
	}
}

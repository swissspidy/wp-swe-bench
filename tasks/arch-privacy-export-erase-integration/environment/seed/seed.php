<?php
/**
 * Seed a copy of the shop's loyalty data: members (2.x and 1.x profile formats), a long points
 * ledger, newsletter subscribers (1.x mixed-case addresses, guests), orders with notes (1.x single
 * note + 2.x lists, guest orders) and look-alike addresses of other people.
 */

use Acme\Loyalty\Installer;
use Acme\Loyalty\Orders;

global $wpdb;

Installer::install();
Acme\Loyalty\Members::register_role();

// The welcome bonus / referral logic would add rows we don't control: seed the ledger by hand.
remove_all_actions( 'user_register' );

$ledger = Installer::ledger_table();
$subs   = Installer::subscribers_table();
$now    = time();
$ago    = static function ( $spec ) use ( $now ) {
	return gmdate( 'Y-m-d H:i:s', strtotime( $spec, $now ) );
};

$mk_user = static function ( $login, $email, $display, $role = 'loyalty_member' ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => 'password',
			'display_name' => $display,
			'first_name'   => strtok( $display, ' ' ),
			'role'         => $role,
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	return $id;
};

$ledger_row = static function ( $user_id, $email, $points, $reason, $created, $ip = '', $note = '', $order_id = 0 ) use ( $wpdb, $ledger ) {
	$wpdb->insert(
		$ledger,
		array(
			'user_id'    => $user_id,
			'email'      => $email,
			'points'     => $points,
			'reason'     => $reason,
			'order_id'   => $order_id,
			'note'       => $note,
			'ip_address' => $ip,
			'created_at' => $created,
		)
	);
	return (int) $wpdb->insert_id;
};

$subscriber = static function ( $email, $first, $status, $subscribed, $args = array() ) use ( $wpdb, $subs ) {
	$wpdb->insert(
		$subs,
		array_merge(
			array(
				'email'           => $email,
				'first_name'      => $first,
				'user_id'         => 0,
				'status'          => $status,
				'source'          => 'footer',
				'token'           => wp_generate_password( 32, false ),
				'ip_address'      => '',
				'subscribed_at'   => $subscribed,
				'confirmed_at'    => 'confirmed' === $status ? $subscribed : null,
				'unsubscribed_at' => null,
			),
			$args
		)
	);
	return (int) $wpdb->insert_id;
};

$order = static function ( $number, $status, $customer_id, $email, $total, $date, array $notes = array(), $legacy_note = '' ) {
	$id = wp_insert_post(
		array(
			'post_type'     => Orders::POST_TYPE,
			'post_status'   => 'private',
			'post_title'    => $number,
			'post_date_gmt' => $date,
			'post_date'     => $date,
			'post_author'   => 1,
		),
		true
	);
	update_post_meta( $id, '_acme_order_number', $number );
	update_post_meta( $id, '_acme_order_status', $status );
	update_post_meta( $id, '_acme_order_customer_id', $customer_id );
	update_post_meta( $id, '_acme_order_email', $email );
	update_post_meta( $id, '_acme_order_total', $total );
	if ( $notes ) {
		update_post_meta( $id, '_acme_order_notes', $notes );
	}
	if ( '' !== $legacy_note ) {
		update_post_meta( $id, '_acme_order_note', $legacy_note );
	}
	return $id;
};
$note = static function ( $author, $text, $date, $visible = null ) {
	return array(
		'date'     => $date,
		'author'   => $author,
		'staff_id' => 'staff' === $author ? 1 : 0,
		'visible'  => null === $visible ? 'customer' === $author : $visible,
		'text'     => $text,
	);
};

// ---------------------------------------------------------------------------------------------
// Staff.
$mk_user( 'barista', 'barista@acme-coffee.example', 'Bea Barista', 'editor' );

// Marco: 1.x member (legacy profile keys), referred Jane.
$marco = $mk_user( 'marco', 'marco@example.org', 'Marco Rossi' );
update_user_meta( $marco, 'acme_loyalty_member_since', '2017-03-02' );
update_user_meta( $marco, 'acme_loyalty_referral_code', 'MARCO017' );
update_user_meta( $marco, 'acme_loyalty_phone', '+1 555 0142' );
update_user_meta( $marco, 'acme_loyalty_dob', '23/09/1979' );
update_user_meta( $marco, 'acme_loyalty_prefs', 'sms,post|Harbour' );
for ( $i = 0; $i < 12; $i++ ) {
	$ledger_row( $marco, 'marco@example.org', 20 + $i, 'purchase', gmdate( 'Y-m-d H:i:s', strtotime( '2018-01-10 10:00:00' ) + $i * 45 * DAY_IN_SECONDS ), '198.51.100.' . ( 10 + $i ), 'POS sale' );
}
$subscriber( 'marco@example.org', 'Marco', 'pending', $ago( '-3 weeks' ), array( 'user_id' => $marco, 'source' => 'checkout', 'ip_address' => '198.51.100.7' ) );
$order( 'AC-1010', 'completed', $marco, 'marco@example.org', '18.00', '2024-02-02 09:00:00', array( $note( 'customer', 'Grind for moka pot please', '2024-02-02 09:00:00' ) ) );

// Jane: 2.x member with a long history. She changed her email address in 2022.
$jane = $mk_user( 'jane', 'jane.doe@example.com', 'Jane Doe' );
update_user_meta( $jane, 'acme_loyalty_member_since', '2019-01-05' );
update_user_meta( $jane, 'acme_loyalty_referral_code', 'JANE2019' );
update_user_meta( $jane, '_acme_loyalty_referred_by', $marco );
update_user_meta( $jane, 'acme_loyalty_phone', '+1 555 0100' );
update_user_meta( $jane, 'acme_loyalty_birthday', '1988-04-12' );
update_user_meta( $jane, 'acme_loyalty_tier', 'gold' );
update_user_meta(
	$jane,
	'acme_loyalty_preferences',
	array(
		'channels' => array( 'email', 'sms' ),
		'store'    => 'Downtown',
	)
);
$start = strtotime( '2019-01-05 08:00:00' );
$end   = strtotime( '-7 days', $now );
$step  = (int) floor( ( $end - $start ) / 250 );
for ( $i = 0; $i < 250; $i++ ) {
	$when   = gmdate( 'Y-m-d H:i:s', $start + $i * $step );
	$email  = $when < '2022-06-01' ? 'jane@oldmail.example' : 'jane.doe@example.com';
	$reason = 0 === $i ? 'welcome' : ( 0 === $i % 25 ? 'redeem' : 'purchase' );
	$points = 'redeem' === $reason ? -100 : ( 'welcome' === $reason ? 50 : 10 + ( $i % 7 ) );
	$ledger_row( $jane, $email, $points, $reason, $when, '203.0.113.' . ( $i % 250 ), 'redeem' === $reason ? 'Free bag of Harbour Blend' : sprintf( 'POS sale %04d', $i ) );
}
// 1.x mailing-list import, before she had an account: mixed case, not linked.
$subscriber( 'Jane.Doe@Example.com', 'Jane', 'confirmed', '2018-11-20 14:00:00', array( 'source' => 'import', 'ip_address' => '203.0.113.99' ) );
// Orders: legacy single note, 2.x notes, one still processing, one guest order from before she registered.
$order(
	'AC-1001',
	'completed',
	$jane,
	'jane.doe@example.com',
	'32.50',
	'2023-05-04 10:00:00',
	array(
		$note( 'staff', 'Customer called about the grinder setting', '2023-05-04 11:00:00', false ),
		$note( 'staff', 'Your order has shipped', '2023-05-05 09:00:00', true ),
	),
	'Leave at the back door'
);
$order( 'AC-1002', 'cancelled', $jane, 'jane.doe@example.com', '12.00', '2024-01-15 10:00:00', array( $note( 'customer', 'Please gift wrap, it is for my sister Anna', '2024-01-15 10:00:00' ) ) );
$order( 'AC-1003', 'processing', $jane, 'jane.doe@example.com', '41.20', $ago( '-2 days' ), array( $note( 'customer', 'Ring twice, flat 4B', $ago( '-2 days' ) ) ) );
$order( 'AC-0950', 'completed', 0, 'JANE.DOE@example.com', '9.90', '2018-10-01 16:00:00', array( $note( 'customer', 'Deliver to reception desk', '2018-10-01 16:00:00' ) ) );

// Sam: member whose newsletter row uses his work address (linked to the account only).
$sam = $mk_user( 'sam', 'sam@example.org', 'Sam Lee' );
update_user_meta( $sam, 'acme_loyalty_member_since', '2021-07-01' );
update_user_meta( $sam, 'acme_loyalty_referral_code', 'SAMLEE21' );
$ledger_row( $sam, 'sam@example.org', 50, 'welcome', '2021-07-01 10:00:00', '192.0.2.44' );
$subscriber( 'sam.work@example.org', 'Sam', 'confirmed', '2021-07-02 10:00:00', array( 'user_id' => $sam, 'source' => 'checkout' ) );

// Look-alikes of Jane (other people!).
$janet = $mk_user( 'janet', 'janet.doe@example.com', 'Janet Doe' );
update_user_meta( $janet, 'acme_loyalty_member_since', '2020-02-02' );
update_user_meta( $janet, 'acme_loyalty_phone', '+1 555 0199' );
$ledger_row( $janet, 'janet.doe@example.com', 50, 'welcome', '2020-02-02 10:00:00', '192.0.2.10', 'Welcome' );
$ledger_row( $janet, 'janet.doe@example.com', 30, 'purchase', $ago( '-1 month' ), '192.0.2.11', 'POS sale' );
$subscriber( 'mary-jane.doe@example.com', 'Mary Jane', 'confirmed', '2020-01-01 10:00:00' );
$subscriber( 'jane.doe@example.com.au', 'Jane', 'confirmed', '2021-01-01 10:00:00' );
$subscriber( 'jane.doe@example.co', 'Jane', 'confirmed', '2021-01-01 10:00:00' );
$order( 'AC-1100', 'completed', 0, 'jane.doe@example.co', '5.00', '2022-03-03 10:00:00', array( $note( 'customer', 'Not the same Jane', '2022-03-03 10:00:00' ) ) );
$order( 'AC-1101', 'completed', $janet, 'janet.doe@example.com', '7.00', '2022-04-03 10:00:00', array( $note( 'customer', 'Janet note', '2022-04-03 10:00:00' ) ) );

// Former member: account deleted in 2023, the ledger still carries her email.
$ledger_row( 987654, 'former.member@example.com', 50, 'welcome', '2020-05-05 10:00:00', '192.0.2.77', 'Welcome' );
$ledger_row( 987654, 'former.member@example.com', 25, 'purchase', '2020-06-05 10:00:00', '192.0.2.77', 'POS sale' );
$ledger_row( 987654, 'Former.Member@example.com', -75, 'redeem', '2020-07-05 10:00:00', '192.0.2.78', 'Redeemed a mug' );

// Guest: no account, newsletter (1.x address, unsubscribed) + guest orders.
$subscriber( 'Guest.Gina@Example.NET', 'Gina', 'unsubscribed', '2019-04-01 10:00:00', array( 'unsubscribed_at' => $ago( '-5 months' ), 'source' => 'store', 'ip_address' => '192.0.2.200' ) );
$order( 'AC-1020', 'completed', 0, 'guest.gina@example.net', '22.00', '2024-06-01 10:00:00', array( $note( 'customer', 'Grind for French press', '2024-06-01 10:00:00' ), $note( 'staff', 'Out of stock, substituted', '2024-06-01 12:00:00', false ) ) );
$order( 'AC-1021', 'processing', 0, 'Guest.Gina@example.net', '15.00', $ago( '-1 day' ), array( $note( 'customer', 'Gate code 4321', $ago( '-1 day' ) ) ) );

// Newsletter rows for the retention rules.
$subscriber( 'old.pending@example.com', 'Olga', 'pending', '2023-01-10 10:00:00' );
$subscriber( 'recent.pending@example.com', 'Rita', 'pending', $ago( '-2 months' ) );
$subscriber( 'old.unsub@example.com', 'Otto', 'unsubscribed', '2019-02-01 10:00:00', array( 'unsubscribed_at' => '2022-05-01 10:00:00' ) );
$subscriber( 'recent.unsub@example.com', 'Rob', 'unsubscribed', '2019-02-01 10:00:00', array( 'unsubscribed_at' => $ago( '-3 months' ) ) );
$subscriber( 'legacy.unsub@example.com', 'Lea', 'unsubscribed', '2017-06-01 10:00:00', array( 'source' => 'import' ) ); // 1.x: no unsubscribe date.
$subscriber( 'loyal.fan@example.com', 'Lou', 'confirmed', '2018-02-01 10:00:00' );

// Filler subscribers.
for ( $i = 1; $i <= 40; $i++ ) {
	$subscriber( sprintf( 'reader%02d@example.net', $i ), 'Reader', 0 === $i % 5 ? 'pending' : 'confirmed', gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i * 11 ) . ' days', $now ) ) );
}

// Pages.
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'My loyalty',
		'post_name'    => 'my-loyalty',
		'post_content' => '<!-- wp:shortcode -->[acme_loyalty_account]<!-- /wp:shortcode -->',
	)
);
wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Newsletter',
		'post_name'    => 'newsletter',
		'post_content' => '<!-- wp:shortcode -->[acme_newsletter]<!-- /wp:shortcode -->',
	)
);

echo 'ledger rows: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$ledger}" ) . "\n";
echo 'subscribers: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$subs}" ) . "\n";

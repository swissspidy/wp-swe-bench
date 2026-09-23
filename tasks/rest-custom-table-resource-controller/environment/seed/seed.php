<?php
/**
 * Seed ~2000 leads (deterministic) plus a few hand-picked ones.
 */

use Acme\Leads\Repository;

global $wpdb;
mt_srand( 20260923 );

$first = array( 'Ada', 'Ben', 'Carla', 'David', 'Elena', 'Farid', 'Greta', 'Hugo', 'Ines', 'Jonas', 'Kira', 'Liam', 'Mila', 'Noah', 'Olga', 'Paul', 'Quinn', 'Rosa', 'Sven', 'Tara', 'Uwe', 'Vera', 'Walt', 'Xenia', 'Yusuf', 'Zoe', 'Anton', 'Bea', 'Chen', 'Dora', 'Emil', 'Fiona', 'Gil', 'Hana', 'Ivan', 'Jana', 'Karl', 'Lea', 'Marco', 'Nina', 'Oscar', 'Pia', 'Rafael', 'Sara', 'Theo', 'Ulla', 'Viktor', 'Wanda', 'Yara', 'Zeno' );
$last  = array( 'Abbott', 'Berger', 'Costa', 'Dietrich', 'Engel', 'Fischer', 'Garcia', 'Huber', 'Iversen', 'Jansen', 'Keller', 'Lang', 'Meyer', 'Novak', 'Ortiz', 'Peters', 'Quast', 'Richter', 'Schmid', 'Tanaka', 'Urban', 'Vogel', 'Weber', 'Xu', 'Young', 'Zimmer', 'Arnold', 'Brandt', 'Conti', 'Dubois', 'Eriksen', 'Ferrari', 'Graf', 'Hoffmann', 'Ito', 'Jung', 'Kraus', 'Lorenz', 'Moreau', 'Nagel' );
$companies = array( '', 'Globex', 'Initech', 'Umbrella', 'Hooli', 'Vandelay Industries', 'Stark Industries', 'Wayne Enterprises', 'Soylent', 'Tyrell Corp', 'Cyberdyne', 'Wonka Industries' );
$domains   = array( 'example.com', 'example.org', 'gmail.com', 'mail.example.net', 'EXAMPLE.COM' );
$statuses  = array( 'new', 'new', 'new', 'contacted', 'contacted', 'qualified', 'won', 'lost' );
$sources   = array( 'form', 'form', 'import', 'event', 'phone' );

$rita = get_user_by( 'login', 'rita' )->ID;
$raj  = get_user_by( 'login', 'raj' )->ID;

$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante venenatis dapibus posuere velit aliquet. ';

$table = Repository::table();
$wpdb->query( 'START TRANSACTION' );

$base = strtotime( '2024-01-01 08:00:00 UTC' );
$ts   = $base;
for ( $i = 0; $i < 1990; $i++ ) {
	// Every 10th lead was imported in the same second as the previous one.
	if ( 0 !== $i % 10 ) {
		$ts += 30000 + mt_rand( 0, 14000 );
	}
	$f       = $first[ $i % 50 ];
	$l       = $last[ intdiv( $i, 50 ) % 40 ];
	$company = $companies[ mt_rand( 0, count( $companies ) - 1 ) ];
	$email   = strtolower( $f . '.' . $l ) . ( $i % 3 ? '' : $i ) . '@' . $domains[ mt_rand( 0, count( $domains ) - 1 ) ];
	$owner   = 0;
	if ( 0 === $i % 5 ) {
		$owner = $rita;
	} elseif ( 1 === $i % 5 ) {
		$owner = $raj;
	}
	$wpdb->insert(
		$table,
		array(
			'name'       => $f . ' ' . $l,
			'email'      => $email,
			'company'    => $company,
			'status'     => $statuses[ mt_rand( 0, count( $statuses ) - 1 ) ],
			'source'     => $sources[ mt_rand( 0, count( $sources ) - 1 ) ],
			'score'      => mt_rand( 0, 100 ),
			'owner_id'   => $owner,
			'notes'      => 0 === $i % 7 ? str_repeat( $lorem, 25 ) . "\n(lead #$i)" : '',
			'created_at' => gmdate( 'Y-m-d H:i:s', $ts ),
			'updated_at' => gmdate( 'Y-m-d H:i:s', $ts + mt_rand( 0, 86400 * 30 ) ),
		)
	);
}

// Hand-picked leads.
$special = array(
	array( 'Sean O\'Brien', 'sean.obrien@obrien-consulting.example', 'O\'Brien Consulting', 'qualified', $rita, 'Wants a demo. Budget approved.' ),
	array( 'Mary_Ann Smith', 'mary_ann.smith@smith-co.example', 'Smith & Co', 'new', $rita, '' ),
	array( 'Maryann Smithers', 'maryann.smithers@example.org', '', 'contacted', $raj, '' ),
	array( 'Percy Hundred', '100%club@percy.example', '100% Club', 'new', 0, '' ),
	array( 'ANNA UPPERCASE', 'Anna.Upper@EXAMPLE.ORG', 'Caps Ltd', 'lost', $raj, 'Went with a competitor.' ),
	array( 'Zed Quiet', 'zed@quiet.example', 'Quiet Inc', 'won', 0, 'Signed 3-year deal.' ),
	array( 'Tie One', 'tie.one@ties.example', 'Ties', 'new', 0, '' ),
	array( 'Tie Two', 'tie.two@ties.example', 'Ties', 'new', 0, '' ),
	array( 'Tie Three', 'tie.three@ties.example', 'Ties', 'new', 0, '' ),
	array( 'Tie Four', 'tie.four@ties.example', 'Ties', 'new', 0, '' ),
);
foreach ( $special as $n => $s ) {
	$created = 0 === strpos( $s[0], 'Tie ' ) ? '2025-06-01 12:00:00' : gmdate( 'Y-m-d H:i:s', strtotime( '2025-03-14 09:26:53 UTC' ) + $n * 3600 );
	$wpdb->insert(
		$table,
		array(
			'name'       => $s[0],
			'email'      => $s[1],
			'company'    => $s[2],
			'status'     => $s[3],
			'source'     => 'form',
			'score'      => 55,
			'owner_id'   => $s[4],
			'notes'      => $s[5],
			'created_at' => $created,
			'updated_at' => $created,
		)
	);
}
$wpdb->query( 'COMMIT' );

WP_CLI::log( 'Leads: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );

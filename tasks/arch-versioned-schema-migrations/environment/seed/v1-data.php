<?php
/**
 * Recreate the production database of a site that has run Acme CRM since 1.2 and was
 * updated to 1.4.0 by replacing the files (no re-activation): 1.2-era tables, no stage /
 * updated_at columns, no secondary indexes, acme_crm_version still "1.2.0".
 *
 * wp eval-file seed/v1-data.php
 */

global $wpdb;

$contacts = $wpdb->prefix . 'acme_crm_contacts';
$notes    = $wpdb->prefix . 'acme_crm_notes';
$charset  = $wpdb->get_charset_collate();

$wpdb->query( "DROP TABLE IF EXISTS $contacts" );
$wpdb->query( "DROP TABLE IF EXISTS $notes" );
$wpdb->query(
	"CREATE TABLE IF NOT EXISTS $contacts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		full_name varchar(191) NOT NULL DEFAULT '',
		email varchar(191) NOT NULL DEFAULT '',
		phone varchar(50) NOT NULL DEFAULT '',
		company varchar(191) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'lead',
		owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
		source varchar(50) NOT NULL DEFAULT '',
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (id)
	) $charset"
);
$wpdb->query(
	"CREATE TABLE IF NOT EXISTS $notes (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
		author_id bigint(20) unsigned NOT NULL DEFAULT 0,
		body text NOT NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (id)
	) $charset"
);

// Hand-picked contacts (ids 1..32), then generated ones up to 1200.
$named = array(
	array( 'Ludwig van Beethoven', 'customer', 'Bonn Pianos' ),
	array( 'Juan de la Cruz', 'lead', '' ),
	array( 'Vincent van der Berg', 'inactive', 'Berg BV' ),
	array( 'Van Morrison', 'customer', '' ),
	array( 'Mary Ann Smith', 'lead', 'Smith & Co' ),
	array( 'Martin Luther King Jr.', 'customer', '' ),
	array( 'John Smith III', 'lead', '' ),
	array( 'Bob Jr.', 'lead', '' ),
	array( 'Cher', 'customer', '' ),
	array( 'Smith, John', 'lead', 'Acme Anvils' ),
	array( 'van Dyke, Dick', 'inactive', '' ),
	array( '  Grace   Brewster  Hopper ', 'customer', 'US Navy' ),
	array( '', 'lead', 'Anonymous Ltd' ),
	array( '   ', 'lead', '' ),
	array( 'Leonardo da Vinci', 'vip', 'Workshop' ),
	array( 'Anna Maria de Souza', 'lead', '' ),
	array( 'de Gaulle', 'inactive', '' ),
	array( 'Jean-Luc Picard', 'customer', 'Starfleet' ),
	array( 'José María Álvarez del Castillo', 'lead', '' ),
	array( 'Otto von Bismarck', 'inactive', '' ),
	array( 'Pieter ter Horst', 'lead', '' ),
	array( 'Sammy Davis jr', 'customer', '' ),
	array( 'Madonna', 'lead', '' ),
	array( 'Prince Rogers Nelson', 'customer', '' ),
	array( 'Ali bin Hassan', 'lead', '' ),
	array( 'Hans De Vries', 'lead', '' ),
	array( "O'Brien, Conan", 'customer', '' ),
	array( 'Lee, Ann, Marie', 'lead', '' ),
	array( 'Dr Who', 'lead', 'TARDIS' ),
	array( 'Emma de la Fontaine Jr.', 'lead', '' ),
	array( 'Karl der Große', 'inactive', '' ),
	array( 'A B', 'lead', '' ),
);

$firsts    = array( 'Anna', 'Ben', 'Carla', 'David', 'Eva', 'Frank', 'Greta', 'Hugo', 'Ines', 'Jonas', 'Klara', 'Lukas', 'Mia', 'Noah', 'Olga', 'Paul', 'Rosa', 'Sven', 'Tina', 'Umberto', 'Vera', 'Walter', 'Xenia', 'Yusuf', 'Zoe' );
$lasts     = array( 'Meier', 'Schmidt', 'van Leeuwen', 'Rossi', 'de Jong', 'Novak', 'Dubois', 'García', 'von Arx', 'Kowalski', 'Nguyen', "O'Neill", 'del Rio', 'Andersen', 'Fischer', 'di Marco', 'Kim', 'Weber', 'la Salle', 'Okafor' );
$middles   = array( '', '', '', 'Maria', '', 'J.', '', '' );
$statuses  = array( 'lead', 'lead', 'customer', 'inactive', 'lead' );
$companies = array( '', 'Globex', 'Initech', 'Umbrella', '', 'Hooli', 'Stark Industries', '' );

$wpdb->query( 'START TRANSACTION' );
$i = 0;
for ( $n = 1; $n <= 1200; $n++ ) {
	if ( $n <= count( $named ) ) {
		list( $name, $status, $company ) = $named[ $n - 1 ];
		$email = sprintf( 'named%02d@example.test', $n );
	} else {
		$f       = $firsts[ $n % count( $firsts ) ];
		$l       = $lasts[ intdiv( $n, count( $firsts ) ) % count( $lasts ) ];
		$m       = $middles[ $n % count( $middles ) ];
		$name    = trim( "$f $m" ) . " $l";
		$status  = $statuses[ $n % count( $statuses ) ];
		$company = $companies[ $n % count( $companies ) ];
		$email   = sprintf( 'contact%04d@example.test', $n );
		if ( 0 === $n % 97 ) {
			$name .= ' Jr.';
		}
		if ( 0 === $n % 211 ) {
			$name = "$l, $f";
		}
	}
	$wpdb->insert(
		$contacts,
		array(
			'full_name'  => $name,
			'email'      => $email,
			'phone'      => sprintf( '+41 44 555 %04d', $n ),
			'company'    => $company,
			'status'     => $status,
			'owner_id'   => 0 === $n % 3 ? 2 : 1,
			'source'     => 0 === $n % 4 ? 'website' : 'manual',
			'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '2023-01-01 08:00:00 UTC' ) + $n * 3 * HOUR_IN_SECONDS ),
		)
	);
}
for ( $n = 1; $n <= 60; $n++ ) {
	$wpdb->insert(
		$notes,
		array(
			'contact_id' => ( $n % 20 ) + 1,
			'author_id'  => 1,
			'body'       => "Call notes #$n: discussed the renewal.",
			'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '2024-03-01 09:00:00 UTC' ) + $n * DAY_IN_SECONDS ),
		)
	);
}
$wpdb->query( 'COMMIT' );

update_option( 'acme_crm_version', '1.2.0' );
update_option(
	'acme_crm_settings',
	array(
		'notify_email' => 'sales@acme.example',
		'form_stage'   => 'lead',
	)
);

echo 'contacts: ' . $wpdb->get_var( "SELECT COUNT(*) FROM $contacts" ) . "\n";

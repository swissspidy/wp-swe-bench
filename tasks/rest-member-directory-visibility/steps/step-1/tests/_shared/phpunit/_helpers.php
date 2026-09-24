<?php
/**
 * Expected visibility matrix for the seeded community + small HTML/REST helpers.
 */

namespace WPSB\Members;

/** Seeded field values (2.x meta or 1.x arrays, as the member would see them). */
const VALUES = array(
	'alice' => array(
		'job_title' => 'Head of Data',
		'company'   => 'Northwind Traders',
		'city'      => 'Berlin',
		'phone'     => '+49 30 555 0101',
		'website'   => 'https://alice.example',
		'bio'       => 'Runs the Berlin data meetup.',
	),
	'bob'   => array(
		'job_title' => 'Site Reliability Engineer',
		'company'   => 'Bobco Hosting',
		'city'      => 'Berlin',
		'phone'     => '+49 30 555 0102',
		'bio'       => 'Keeps the servers warm.',
	),
	'carol' => array(
		'job_title' => 'Product Designer',
		'company'   => 'Carol Secret Corp',
		'city'      => 'Leipzig',
		'phone'     => '+49 341 555 0103',
		'website'   => 'https://carol.example',
		'bio'       => 'Designs things.',
	),
	'dave'  => array(
		'job_title' => 'Chief Tinkerer',
		'company'   => 'Dave Industries',
		'city'      => 'Hamburg',
		'phone'     => '+49 40 555 0104',
		'bio'       => 'Hidden legacy profile.',
	),
	'erin'  => array(
		'job_title' => 'Community Manager',
		'company'   => 'Erin & Co',
		'city'      => 'Hamburg',
		'phone'     => '+49 40 555 0105',
		'website'   => 'https://erin.example',
		'bio'       => 'Legacy public profile.',
	),
	'frank' => array(
		'job_title' => 'Freelance Welder',
		'company'   => 'Fisher Metalworks',
		'city'      => 'Munich',
		'phone'     => '+49 89 555 0106',
	),
	'gina'  => array(
		'job_title' => 'Embedded Engineer',
		'city'      => 'Munich',
		'phone'     => '+49 89 555 0107',
	),
	'henry' => array(
		'job_title' => 'Woodworker',
		'city'      => 'Berlin',
	),
);

/** Visible field keys per viewer class ('public', 'members', 'all'); null = profile hidden. */
const VISIBLE = array(
	'alice' => array(
		'public'  => array( 'job_title', 'company', 'city', 'website', 'bio' ),
		'members' => array( 'job_title', 'company', 'city', 'phone', 'website', 'bio' ),
	),
	'bob'   => array(
		'public'  => null,
		'members' => array( 'job_title', 'company', 'city', 'phone', 'bio' ),
	),
	'carol' => array(
		'public'  => array( 'job_title', 'phone', 'website', 'bio' ),
		'members' => array( 'job_title', 'city', 'phone', 'website', 'bio' ),
	),
	'dave'  => array(
		'public'  => null,
		'members' => null,
	),
	'erin'  => array(
		'public'  => array( 'job_title', 'company', 'city', 'website', 'bio' ),
		'members' => array( 'job_title', 'company', 'city', 'website', 'bio' ),
	),
	'frank' => array(
		'public'  => null,
		'members' => null,
	),
	'henry' => array(
		'public'  => array( 'job_title', 'city' ),
		'members' => array( 'job_title', 'city' ),
	),
);

/** Viewer login => class. */
const VIEWERS = array(
	''      => 'public',
	'sue'   => 'public',
	'eddie' => 'public',
	'gina'  => 'members',
	'admin' => 'all',
);

function uid( string $login ): int {
	if ( '' === $login ) {
		return 0;
	}
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "seeded user $login missing" );
	}
	return (int) $u->ID;
}

/**
 * Expected `fields` of a member for a viewer class, or null when the profile is hidden.
 */
function expected( string $member, string $class ): ?array {
	if ( 'all' === $class ) {
		return VALUES[ $member ];
	}
	if ( 'gina' === $member ) {
		return 'members' === $class ? VALUES['gina'] : array( 'job_title' => VALUES['gina']['job_title'], 'city' => VALUES['gina']['city'] );
	}
	$keys = VISIBLE[ $member ][ $class ];
	if ( null === $keys ) {
		return null;
	}
	return array_intersect_key( VALUES[ $member ], array_flip( $keys ) );
}

/** All members (logins) visible for a viewer class (the 14 fillers are public with phone members-only). */
function visible_logins( string $class, string $viewer = '' ): array {
	$out = array();
	foreach ( array( 'alice', 'bob', 'carol', 'dave', 'erin', 'frank', 'gina', 'henry' ) as $m ) {
		if ( $m === $viewer || null !== expected( $m, $class ) ) {
			$out[] = $m;
		}
	}
	for ( $i = 1; $i <= 14; $i++ ) {
		$out[] = sprintf( 'filler%02d', $i );
	}
	return $out;
}

/** Sort key-value arrays for comparison. */
function ksorted( ?array $a ): ?array {
	if ( null !== $a ) {
		ksort( $a );
	}
	return $a;
}

/** JSON-normalize REST data (objects → arrays). */
function norm( $data ) {
	return json_decode( wp_json_encode( $data ), true );
}

function xpath( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function cls( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/**
 * Directory cards in HTML: member ID => [field key => text].
 */
function cards( string $html ): array {
	$x   = xpath( $html );
	$out = array();
	foreach ( $x->query( '//li[' . cls( 'acme-member-card' ) . ']' ) as $li ) {
		$fields = array();
		foreach ( $x->query( './/*[' . cls( 'acme-member-card__field' ) . ']', $li ) as $f ) {
			if ( preg_match( '/acme-member-card__field--([a-z_]+)/', $f->getAttribute( 'class' ), $m ) ) {
				$fields[ $m[1] ] = trim( $f->textContent );
			}
		}
		ksort( $fields );
		$out[ (int) $li->getAttribute( 'data-member-id' ) ] = $fields;
	}
	return $out;
}

/**
 * Author card on an author archive: null if absent, else [field key => text].
 */
function author_card( string $html ): ?array {
	$x    = xpath( $html );
	$card = $x->query( '//*[' . cls( 'acme-author-card' ) . ']' );
	if ( 0 === $card->length ) {
		return null;
	}
	$fields = array();
	foreach ( $x->query( './/*[' . cls( 'acme-author-card__field' ) . ']', $card->item( 0 ) ) as $f ) {
		if ( preg_match( '/acme-author-card__field--([a-z_]+)/', $f->getAttribute( 'class' ), $m ) ) {
			$fields[ $m[1] ] = trim( $f->textContent );
		}
	}
	ksort( $fields );
	return $fields;
}

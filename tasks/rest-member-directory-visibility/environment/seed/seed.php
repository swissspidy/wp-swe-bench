<?php
/**
 * Seed the community: members with 2.x and 1.x profiles and every visibility combination,
 * authors among them, non-member accounts, directory pages.
 */

use Acme\Members\Members;

Members::register_role();

$mk = static function ( $login, $display, $roles, array $meta = array() ) {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@makers.example',
			'user_pass'    => 'password',
			'display_name' => $display,
			'first_name'   => strtok( $display, ' ' ),
			'role'         => array_shift( $roles ),
		)
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$user = new WP_User( $id );
	foreach ( $roles as $role ) {
		$user->add_role( $role );
	}
	foreach ( $meta as $key => $value ) {
		update_user_meta( $id, $key, $value );
	}
	return $id;
};

$fields = static function ( array $values ) {
	$meta = array();
	foreach ( $values as $key => $value ) {
		$meta[ 'acme_member_' . $key ] = $value;
	}
	return $meta;
};

// 2.x, public profile, default field visibility (phone: members only).
$alice = $mk(
	'alice',
	'Alice Archer',
	array( 'acme_member', 'author' ),
	$fields(
		array(
			'job_title' => 'Head of Data',
			'company'   => 'Northwind Traders',
			'city'      => 'Berlin',
			'phone'     => '+49 30 555 0101',
			'website'   => 'https://alice.example',
			'bio'       => 'Runs the Berlin data meetup.',
		)
	) + array( 'acme_member_visibility' => 'public' )
);

// 2.x, members-only profile.
$bob = $mk(
	'bob',
	'Bob Builder',
	array( 'acme_member', 'author' ),
	$fields(
		array(
			'job_title' => 'Site Reliability Engineer',
			'company'   => 'Bobco Hosting',
			'city'      => 'Berlin',
			'phone'     => '+49 30 555 0102',
			'bio'       => 'Keeps the servers warm.',
		)
	) + array( 'acme_member_visibility' => 'members' )
);

// 2.x, public profile with per-field overrides.
$carol = $mk(
	'carol',
	'Carol Chen',
	array( 'acme_member', 'author' ),
	$fields(
		array(
			'job_title' => 'Product Designer',
			'company'   => 'Carol Secret Corp',
			'city'      => 'Leipzig',
			'phone'     => '+49 341 555 0103',
			'website'   => 'https://carol.example',
			'bio'       => 'Designs things.',
		)
	) + array(
		'acme_member_visibility'       => 'public',
		'acme_member_field_visibility' => array(
			'company' => 'private',
			'city'    => 'members',
			'phone'   => 'public',
		),
	)
);

// 1.x profile, hidden (private) with the 1.x flag. Writes blog posts.
$dave = $mk(
	'dave',
	'Dave Dorsey',
	array( 'acme_member', 'author' ),
	array(
		'acme_member_profile'      => array(
			'title'   => 'Chief Tinkerer',
			'company' => 'Dave Industries',
			'city'    => 'Hamburg',
			'phone'   => '+49 40 555 0104',
			'about'   => 'Hidden legacy profile.',
		),
		'acme_member_hide_profile' => '1',
	)
);

// 1.x profile, visible, phone hidden with the 1.x flag.
$erin = $mk(
	'erin',
	'Erin Early',
	array( 'acme_member' ),
	array(
		'acme_member_profile'    => array(
			'title'   => 'Community Manager',
			'company' => 'Erin & Co',
			'city'    => 'Hamburg',
			'phone'   => '+49 40 555 0105',
			'website' => 'https://erin.example',
			'about'   => 'Legacy public profile.',
		),
		'acme_member_hide_phone' => '1',
	)
);

// 2.x private profile.
$frank = $mk(
	'frank',
	'Frank Fisher',
	array( 'acme_member' ),
	$fields(
		array(
			'job_title' => 'Freelance Welder',
			'company'   => 'Fisher Metalworks',
			'city'      => 'Munich',
			'phone'     => '+49 89 555 0106',
		)
	) + array( 'acme_member_visibility' => 'private' )
);

// 2.x public; used as the "member" viewer in tests.
$gina = $mk(
	'gina',
	'Gina Gomez',
	array( 'acme_member' ),
	$fields(
		array(
			'job_title' => 'Embedded Engineer',
			'city'      => 'Munich',
			'phone'     => '+49 89 555 0107',
		)
	) + array( 'acme_member_visibility' => 'public' )
);

// 2.x setting wins over a left-over 1.x flag.
$henry = $mk(
	'henry',
	'Henry Hall',
	array( 'acme_member' ),
	$fields(
		array(
			'job_title' => 'Woodworker',
			'city'      => 'Berlin',
		)
	) + array(
		'acme_member_visibility'   => 'public',
		'acme_member_hide_profile' => '1',
	)
);

// Filler members.
for ( $i = 1; $i <= 14; $i++ ) {
	$mk(
		sprintf( 'filler%02d', $i ),
		sprintf( 'Zoe Filler %02d', $i ),
		array( 'acme_member' ),
		$fields(
			array(
				'job_title' => 'Analyst',
				'city'      => 'Munich',
				'phone'     => sprintf( '+49 89 555 1%03d', $i ),
			)
		)
	);
}

// Not members.
$mk( 'eddie', 'Eddie Editor', array( 'editor' ) );
$mk( 'sue', 'Sue Subscriber', array( 'subscriber' ) );

// Blog posts by members.
$post = static function ( $author, $title, $days_ago ) {
	return wp_insert_post(
		array(
			'post_title'   => $title,
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $title ) . ' – notes from the workshop.</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) ),
		)
	);
};
$post( $alice, 'Data meetup recap', 1 );
$post( $alice, 'Visualising sensor data', 5 );
$post( $bob, 'Our new rack', 2 );
$post( $carol, 'Design sprint notes', 3 );
$post( $dave, 'Soldering 101', 4 );

// Pages.
$page = static function ( $slug, $title, $content ) {
	return wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
};
$page( 'members', 'Members', '<!-- wp:shortcode -->[acme_member_directory per_page="50"]<!-- /wp:shortcode -->' );
$page( 'berlin-members', 'Members in Berlin', '<!-- wp:acme/member-directory {"city":"Berlin","perPage":10} /-->' );
$page( 'edit-profile', 'Edit your profile', '<!-- wp:shortcode -->[acme_member_edit_profile]<!-- /wp:shortcode -->' );

echo 'members: ' . count( Members::all_ids() ) . "\n";

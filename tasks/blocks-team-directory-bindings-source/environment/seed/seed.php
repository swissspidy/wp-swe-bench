<?php
/**
 * Seed: team members in every state, legacy 1.x members, photos, pages with
 * [team_member] shortcodes and pages/posts using bindings to member fields.
 */

wp_set_current_user( 1 );
$alex = get_user_by( 'login', 'alex' )->ID;

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/** Create a small PNG portrait and import it as an attachment. */
$photo = static function ( $slug, $rgb, $alt ) {
	$img = imagecreatetruecolor( 64, 64 );
	imagefill( $img, 0, 0, imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] ) );
	$tmp = sys_get_temp_dir() . "/$slug.png";
	imagepng( $img, $tmp );
	imagedestroy( $img );
	$id = media_handle_sideload(
		array(
			'name'     => "$slug.png",
			'tmp_name' => $tmp,
		),
		0,
		ucfirst( $slug )
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() );
		exit( 1 );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return $id;
};

$member = static function ( $slug, $name, $status, $meta, $args = array() ) {
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'    => 'acme_member',
				'post_status'  => $status,
				'post_name'    => $slug,
				'post_title'   => $name,
				'post_author'  => 1,
				'post_content' => '',
			),
			$args
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() );
		exit( 1 );
	}
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, wp_slash( $value ) );
	}
	return $id;
};

$bound = static function ( $block, array $bindings, $attrs, $html ) {
	$map = array();
	foreach ( $bindings as $attribute => $args ) {
		$map[ $attribute ] = array(
			'source' => 'acme/team-member',
			'args'   => $args,
		);
	}
	$attrs['metadata'] = array( 'bindings' => $map );
	return serialize_block(
		array(
			'blockName'    => $block,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerContent' => array( $html ),
		)
	);
};

$p = static function ( $args, $fallback ) use ( $bound ) {
	return $bound( 'core/paragraph', array( 'content' => $args ), array(), "\n<p>$fallback</p>\n" );
};
$h = static function ( $args, $fallback, $level = 2 ) use ( $bound ) {
	return $bound( 'core/heading', array( 'content' => $args ), 2 === $level ? array() : array( 'level' => $level ), "\n<h$level class=\"wp-block-heading\">$fallback</h$level>\n" );
};
$img = static function ( $args ) use ( $bound ) {
	return $bound(
		'core/image',
		array(
			'url' => $args,
			'alt' => $args,
		),
		array(),
		"\n<figure class=\"wp-block-image\"><img alt=\"\"/></figure>\n"
	);
};
$btn = static function ( $text_args, $url_args, $fallback ) use ( $bound ) {
	$button = $bound(
		'core/button',
		array(
			'text' => $text_args,
			'url'  => $url_args,
		),
		array(),
		"\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\">$fallback</a></div>\n"
	);
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">$button</div>\n<!-- /wp:buttons -->";
};
$para = static function ( $text ) {
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
};

$ada_photo   = $photo( 'ada', array( 200, 80, 80 ), 'Portrait of Ada Lovelace' );
$grace_photo = $photo( 'grace', array( 80, 80, 200 ), '' );

$ada = $member(
	'ada-lovelace',
	'Ada Lovelace',
	'publish',
	array(
		'_acme_role'        => 'Head of Engineering',
		'_acme_email'       => 'ada@acme.test',
		'_acme_phone'       => '+1 (555) 010-1001',
		'_acme_photo_id'    => (string) $ada_photo,
		'_acme_profile_url' => 'https://profiles.acme.test/ada',
		'_acme_notes'       => 'Salary band E7, retention bonus pending',
		'_acme_pronouns'    => 'she/her',
	)
);
// Ada's own page shows her role via a binding without a member ID (current post).
wp_update_post(
	array(
		'ID'           => $ada,
		'post_content' => wp_slash( $para( 'Ada leads our engineering teams.' ) . "\n\n" . $p( array( 'key' => 'role' ), 'Role' ) . "\n\n" . $p( array( 'key' => 'pronouns' ), 'Pronouns' ) ),
	)
);

$grace = $member(
	'grace-hopper',
	'Grace Hopper',
	'publish',
	array(
		'_acme_role'        => 'Compiler Lead',
		'_acme_email'       => 'grace@acme.test',
		'_acme_photo_id'    => (string) $grace_photo,
		'_acme_profile_url' => 'https://profiles.acme.test/grace',
	),
	array( 'post_author' => $alex )
);

// Created with 1.x and never edited since: only the old serialized array.
$linus = $member(
	'linus-legacy',
	'Linus Legacy',
	'publish',
	array(
		'acme_member_meta' => array(
			'role'  => 'Kernel Maintainer',
			'email' => 'linus@acme.test',
			'phone' => '+44 20 7946 0000',
		),
	)
);

$dora  = $member( 'dora-draft', 'Dora Draft', 'draft', array( '_acme_role' => 'Secret Project Lead', '_acme_email' => 'dora@acme.test', '_acme_phone' => '+1 555 010 7777' ) );
$pete  = $member( 'pete-private', 'Pete Private', 'private', array( '_acme_role' => 'Acquisition Target', '_acme_phone' => '+1 555 010 9999', '_acme_email' => 'pete@acme.test' ) );
$pat   = $member( 'pat-pending', 'Pat Pending', 'pending', array( '_acme_role' => 'Incoming CFO' ) );
$paula = $member( 'paula-protected', 'Paula Protected', 'publish', array( '_acme_role' => 'Board Member', '_acme_email' => 'paula@acme.test' ), array( 'post_password' => 'board' ) );
$hank  = $member(
	'hank-hostile',
	'Hank <em>Hostile</em>',
	'publish',
	array(
		'_acme_role'        => 'R&D <script>alert("role")</script><b>Lead</b>',
		'_acme_email'       => 'hank@acme.test',
		'_acme_phone'       => '+1 555 <img src=x onerror=alert(1)>',
		'_acme_profile_url' => 'javascript:alert(document.domain)',
		'_acme_pronouns'    => 'he/him <script>alert(2)</script>',
	)
);

wp_set_object_terms( $ada, 'Engineering', 'acme_department' );
wp_set_object_terms( $grace, 'Engineering', 'acme_department' );
wp_set_object_terms( $linus, 'Engineering', 'acme_department' );

$page = static function ( $slug, $title, $content, $author = 1, $type = 'page', $status = 'publish' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_author'  => $author,
			'post_content' => wp_slash( $content ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() );
		exit( 1 );
	}
	return $id;
};

// Classic pages with the shortcodes.
$page( 'leadership', 'Leadership', "Meet our engineering lead:\n\n[team_member id=\"$ada\" fields=\"role,email,phone,profile_url,pronouns\"]\n\n[team_member id=\"$dora\"]\n\n[team_member id=\"$linus\"]" );
$page( 'engineering-team', 'Engineering team', '[team_directory department="engineering" fields="role,email,phone"]' );

// Block page: explicit member IDs.
$page(
	'meet-ada',
	'Meet Ada',
	implode(
		"\n\n",
		array(
			$h( array( 'key' => 'name', 'memberId' => $ada ), 'Name' ),
			$img( array( 'key' => 'photo', 'memberId' => $ada ) ),
			$p( array( 'key' => 'role', 'memberId' => $ada ), 'Role' ),
			$btn( array( 'key' => 'email', 'memberId' => $ada ), array( 'key' => 'email', 'memberId' => $ada ), 'Email' ),
			$btn( array( 'key' => 'phone', 'memberId' => $ada ), array( 'key' => 'phone', 'memberId' => $ada ), 'Call' ),
			$btn( array( 'key' => 'name', 'memberId' => $ada ), array( 'key' => 'profile_url', 'memberId' => $ada ), 'Profile' ),
			$p( array( 'key' => 'phone', 'memberId' => $grace ), 'Phone' ),
			$img( array( 'key' => 'photo', 'memberId' => $grace ) ),
		)
	)
);

// Members that must never be shown to visitors.
$page(
	'internal-members',
	'Internal members',
	implode(
		"\n\n",
		array(
			$p( array( 'key' => 'role', 'memberId' => $dora ), 'Role of the draft member' ),
			$p( array( 'key' => 'phone', 'memberId' => $pete ), 'Phone of the private member' ),
			$p( array( 'key' => 'role', 'memberId' => $pat ), 'Role of the pending member' ),
			$p( array( 'key' => 'role', 'memberId' => $paula ), 'Role of the protected member' ),
			$h( array( 'key' => 'name', 'memberId' => $pete ), 'Name of the private member' ),
			$btn( array( 'key' => 'email', 'memberId' => $dora ), array( 'key' => 'email', 'memberId' => $dora ), 'Email the draft member' ),
		)
	)
);

// The team overview: a Query Loop over members.
$query = '<!-- wp:query {"queryId":7,"query":{"perPage":50,"pages":0,"offset":0,"postType":"acme_member","order":"asc","orderBy":"title","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->' . "\n"
	. '<div class="wp-block-query"><!-- wp:post-template -->' . "\n"
	. '<!-- wp:group {"className":"team-card","layout":{"type":"constrained"}} -->' . "\n"
	. '<div class="wp-block-group team-card">'
	. $h( array( 'key' => 'name' ), 'Name', 3 ) . "\n\n"
	. $p( array( 'key' => 'role' ), 'Role' ) . "\n\n"
	. $btn( array( 'key' => 'email' ), array( 'key' => 'email' ), 'Email' )
	. '</div>' . "\n"
	. '<!-- /wp:group -->' . "\n"
	. '<!-- /wp:post-template --></div>' . "\n"
	. '<!-- /wp:query -->';
$page( 'our-team', 'Our team', $para( 'Everyone at Acme.' ) . "\n\n" . $query );

// A post by an author that references members she may or may not edit.
$page(
	'alex-team-post',
	'Working with the compiler team',
	implode(
		"\n\n",
		array(
			$para( 'Some people you should know.' ),
			$p( array( 'key' => 'role', 'memberId' => $ada ), 'Ada role' ),
			$p( array( 'key' => 'role', 'memberId' => $grace ), 'Grace role' ),
			$p( array( 'key' => 'phone', 'memberId' => $pete ), 'Pete phone' ),
		)
	),
	$alex,
	'post'
);

echo "seeded\n";

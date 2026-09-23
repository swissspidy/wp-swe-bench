<?php
/**
 * Seeds the catalogue: 40 authors (some unpublished), 100+ books, a few books still in
 * the 1.x relation format, and the unpublished fixtures the security review used.
 */

use Acme\Library\Relationships;

$user = static function ( string $login, string $role, string $name ): int {
	$id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_pass'    => 'password',
			'user_email'   => "$login@acme-publishing.example",
			'display_name' => $name,
			'role'         => $role,
		)
	);
	return (int) $id;
};
$eddie = $user( 'eddie', 'editor', 'Eddie Editor' );
$wanda = $user( 'wanda', 'author', 'Wanda Writer' );
$user( 'sam', 'subscriber', 'Sam Subscriber' );
$user( 'cora', 'contributor', 'Cora Contributor' );

$post = static function ( string $type, string $title, string $slug, string $status = 'publish', array $meta = array(), int $author = 1 ): int {
	return (int) wp_insert_post(
		array(
			'post_type'    => $type,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => $status,
			'post_author'  => $author,
			'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $title ) . " …</p>\n<!-- /wp:paragraph -->",
			'post_date'    => '2025-01-01 10:00:00',
			'meta_input'   => $meta,
		),
		true
	);
};

$first = array( 'Ada', 'Grace', 'Alan', 'Barbara', 'Donald', 'Edsger', 'Frances', 'Hedy', 'Ivan', 'Jean', 'Katherine', 'Linus', 'Margaret', 'Niklaus', 'Ole', 'Radia', 'Sophie', 'Tim', 'Ursula', 'Vint', 'Whitfield', 'Xiao', 'Yukihiro', 'Zelda', 'Anita', 'Bjarne', 'Carol', 'Dennis', 'Evelyn', 'Fran', 'Guido', 'Hal', 'Irma', 'John', 'Kay' );
$last  = array( 'Lovelace', 'Hopper', 'Turing', 'Liskov', 'Knuth', 'Dijkstra', 'Allen', 'Lamarr', 'Sutherland', 'Sammet', 'Johnson', 'Torvalds', 'Hamilton', 'Wirth', 'Dahl', 'Perlman', 'Wilson', 'Berners-Lee', 'Franklin', 'Cerf', 'Diffie', 'Wang', 'Matsumoto', 'Fitzgerald', 'Borg', 'Stroustrup', 'Shaw', 'Ritchie', 'Berezin', 'Bilas', 'van Rossum', 'Abelson', 'Wyman', 'Backus', 'Nygaard' );

$authors = array();
foreach ( $first as $i => $name ) {
	$full      = $name . ' ' . $last[ $i ];
	$authors[] = $post( 'author', $full, sanitize_title( $full ), 'publish', array( 'acme_born' => 1900 + $i * 2 ) );
}
// Unpublished authors.
$draft_authors = array(
	$post( 'author', 'Secret Pseudonym', 'secret-pseudonym', 'draft' ),
	$post( 'author', 'Upcoming Debutant', 'upcoming-debutant', 'draft' ),
	$post( 'author', 'Pending Reviewer', 'pending-reviewer', 'pending' ),
);
$private_authors = array(
	$post( 'author', 'Private Ghostwriter', 'private-ghostwriter', 'private' ),
	$post( 'author', 'Internal Committee', 'internal-committee', 'private' ),
);
$wanda_author = $post( 'author', 'Wanda Writes', 'wanda-writes', 'publish', array(), $wanda );

$adjectives = array( 'Silent', 'Recursive', 'Hidden', 'Lazy', 'Concurrent', 'Pure', 'Mutable', 'Distributed', 'Elegant', 'Forgotten' );
$nouns      = array( 'Compiler', 'Garden', 'Stack', 'Kernel', 'Lambda', 'Protocol', 'Pointer', 'Cathedral', 'Bazaar', 'Machine' );

for ( $i = 0; $i < 100; $i++ ) {
	$title = sprintf( 'The %s %s', $adjectives[ $i % 10 ], $nouns[ intdiv( $i, 10 ) ] );
	$book  = $post( 'book', $title, sanitize_title( $title ), 'publish', array( 'acme_isbn' => sprintf( '978%010d', 3000000 + $i ), 'acme_year' => 1970 + ( $i % 50 ) ) );
	$ids   = array( $authors[ $i % 35 ] );
	if ( 0 === $i % 3 ) {
		$ids[] = $authors[ ( $i * 7 + 3 ) % 35 ];
	}
	if ( 0 === $i % 10 ) {
		$ids[] = $authors[ ( $i * 11 + 5 ) % 35 ];
	}
	Relationships::set_author_ids( $book, array_values( array_unique( $ids ) ) );
}

$ada   = $authors[0];
$grace = $authors[1];
$alan  = $authors[2];

// Fixtures from the security review.
$hidden = $post( 'book', 'The Hidden Hand', 'the-hidden-hand', 'publish', array( 'acme_year' => 2024 ) );
Relationships::set_author_ids( $hidden, array( $draft_authors[0], $ada, $private_authors[0], $grace ) );
$memoirs = $post( 'book', 'Memoirs of a Ghost', 'memoirs-of-a-ghost', 'publish', array( 'acme_year' => 2023 ) );
Relationships::set_author_ids( $memoirs, array( $private_authors[0] ) );
$sequel = $post( 'book', 'Unannounced Sequel', 'unannounced-sequel', 'draft', array( 'acme_year' => 2027 ) );
Relationships::set_author_ids( $sequel, array( $ada, $alan ) );
$guide = $post( 'book', 'Internal Style Guide', 'internal-style-guide', 'private', array( 'acme_year' => 2022 ) );
Relationships::set_author_ids( $guide, array( $grace, $private_authors[1] ) );
$wandas = $post( 'book', "Wanda's First Book", 'wandas-first-book', 'publish', array( 'acme_year' => 2021 ), $wanda );
Relationships::set_author_ids( $wandas, array( $wanda_author ) );

// Books that still use the 1.x format (imported after the 2.0 migration).
$post( 'book', 'The Old Catalogue', 'the-old-catalogue', 'publish', array( 'acme_year' => 1998, Relationships::LEGACY_META => "$grace, $ada" ) );
$post( 'book', 'Pamphlet of 1999', 'pamphlet-of-1999', 'publish', array( 'acme_year' => 1999, Relationships::LEGACY_META => $draft_authors[0] . ',' . $alan ) );
$post( 'book', 'Unsorted Notes', 'unsorted-notes', 'draft', array( 'acme_year' => 1997, Relationships::LEGACY_META => (string) $alan ) );

echo 'authors: ', count( $authors ) + 6, "\n";

<?php
/**
 * Acme Tasks fixtures (run with `wp eval-file`).
 */

use Acme\Tasks\Post_Type;
use Acme\Tasks\Task_Repository;

$users = array();
foreach ( array(
	'alice' => 'author',
	'bob'   => 'author',
	'carol' => 'contributor',
	'erin'  => 'editor',
	'sam'   => 'subscriber',
) as $login => $role ) {
	$users[ $login ] = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@example.org',
			'user_pass'    => 'password',
			'role'         => $role,
			'display_name' => ucfirst( $login ),
		)
	);
}

/**
 * Create a list with tasks. Tasks: [title, position, extra columns].
 */
function acme_seed_list( $slug, $title, $owner, $members, $color, array $tasks, $archived = false ) {
	$list_id = wp_insert_post(
		array(
			'post_type'   => Post_Type::NAME,
			'post_status' => 'publish',
			'post_title'  => $title,
			'post_name'   => $slug,
			'post_author' => $owner,
		),
		true
	);
	update_post_meta( $list_id, Post_Type::META_COLOR, $color );
	update_post_meta( $list_id, Post_Type::META_MEMBERS, $members );
	if ( $archived ) {
		update_post_meta( $list_id, Post_Type::META_ARCHIVE, 1 );
	}
	foreach ( $tasks as $task ) {
		Task_Repository::insert(
			array_merge(
				array(
					'list_id'    => $list_id,
					'title'      => $task[0],
					'position'   => $task[1],
					'created_by' => $owner,
				),
				$task[2] ?? array()
			)
		);
	}
	Task_Repository::flush_cache();
	return $list_id;
}

acme_seed_list(
	'website-relaunch',
	'Website relaunch',
	$users['alice'],
	array( $users['bob'], $users['carol'] ),
	'#d63638',
	array(
		array( 'Write homepage copy', 0, array( 'notes' => "Tone: friendly.\nMax 120 words." ) ),
		array(
			'Pick hero photos',
			1,
			array(
				'status'       => 'done',
				'completed_at' => '2026-09-01 10:00:00',
			),
		),
		array(
			'Set up redirects',
			2,
			array(
				'due_date'    => '2026-10-01',
				'assignee_id' => $users['bob'],
			),
		),
		array( 'Test contact forms', 3 ),
		array( 'Launch', 4, array( 'due_date' => '2026-10-15' ) ),
	)
);

acme_seed_list(
	'groceries',
	'Groceries',
	$users['bob'],
	array(),
	'#00a32a',
	array(
		array( 'Milk', 0 ),
		array( 'Coffee beans', 1 ),
		array( 'Apples', 2 ),
	)
);

// Created with 1.0: members stored as a comma separated string.
acme_seed_list(
	'board-meeting',
	'Board meeting',
	$users['erin'],
	$users['alice'] . ',' . $users['carol'],
	'#dba617',
	array(
		array( 'Book room', 0 ),
		array( 'Send agenda', 1, array( 'assignee_id' => $users['alice'] ) ),
	)
);

// Migrated from post meta by the 1.3 upgrade, which copied the old (sparse) positions as-is.
acme_seed_list(
	'onboarding-checklist',
	'Onboarding checklist',
	$users['alice'],
	array( $users['bob'] ),
	'#8c8f94',
	array(
		array( 'Order laptop', 0 ),
		array( 'Create accounts', 2 ),
		array( 'Print badge', 2 ),
		array( 'Intro meeting', 5 ),
		array( 'Read the handbook', 9 ),
	)
);

acme_seed_list(
	'office-move',
	'Office move',
	$users['alice'],
	array( $users['bob'] ),
	'#2271b1',
	array(
		array( 'Pack desks', 1 ),
		array( 'Label boxes', 1 ),
		array( 'Book movers', 4, array( 'due_date' => '2026-11-02' ) ),
		array( 'Update address', 7 ),
	)
);

acme_seed_list(
	'old-sprint',
	'Old sprint',
	$users['alice'],
	array(),
	'#3858e9',
	array(
		array( 'Retro', 0, array( 'status' => 'done' ) ),
	),
	true
);

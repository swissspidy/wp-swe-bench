<?php
/**
 * Seeds three years' worth of production-like activity log data in the
 * formats the plugin wrote over time (run with `wp eval-file`).
 *
 * Deterministic: the hidden tests' fixtures were generated from this data.
 */

mt_srand( 20240101 );

$users = array();
foreach ( array( 'olivia' => 'editor', 'marcus' => 'author', 'priya' => 'administrator', 'tom' => 'subscriber', 'lena' => 'administrator', 'kofi' => 'editor' ) as $login => $role ) {
	$users[ $login ] = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@acme.example',
			'user_pass'    => 'password',
			'role'         => $role,
			'display_name' => ucfirst( $login ),
		)
	);
}
$actors = array( 1, 1, 1, $users['olivia'], $users['olivia'], $users['marcus'], $users['priya'], $users['lena'], $users['kofi'], $users['tom'], 0, 999 );

$titles = array( 'Spring sale', 'Café “Menü” – 🎉 launch', 'Quarterly report', 'Hiring: senior engineer', 'Privacy policy update', 'Tips & tricks', 'O\'Brien interview', '100% renewable energy', 'under_score naming', 'Invoice #4411 follow-up', 'Ümlaut Straße notes', '<b>Bold</b> claims' );
$plugins = array( 'akismet/akismet.php', 'acme-forms/acme-forms.php', 'acme-backup/acme-backup.php', 'hello.php' );

/**
 * Pick one.
 *
 * @param array $list Items.
 * @return mixed
 */
function wpsb_pick( array $list ) {
	return $list[ mt_rand( 0, count( $list ) - 1 ) ];
}

$entries = array(); // id => normalized entry.
$time    = 1704067200; // 2024-01-01 00:00:00 UTC.
$last_id = 17400;

for ( $id = 1; $id <= $last_id; $id++ ) {
	$r = mt_rand( 1, 100 );
	if ( $r <= 3 ) {
		$step = 0; // Same second as the previous entry.
	} else {
		$step = mt_rand( 30, 3200 );
	}
	$time += $step;
	$t     = $time;
	if ( 0 === $id % 997 ) {
		$t -= 86400 * 3; // Late-imported entry (older than its neighbours).
	}

	$user    = wpsb_pick( $actors );
	$roll    = mt_rand( 1, 100 );
	$ctx     = array();
	$obj     = '';
	$oid     = 0;
	if ( $roll <= 40 ) {
		$action = 'user_login';
		$login  = $user ? ( $user > 900 ? 'former-employee' : get_userdata( $user )->user_login ) : 'cron';
		$msg    = $login . ' logged in';
		$obj    = 'user';
		$oid    = $user;
	} elseif ( $roll <= 65 ) {
		$action = 'post_published';
		$title  = wpsb_pick( $titles );
		$type   = mt_rand( 1, 4 ) === 1 ? 'page' : 'post';
		$msg    = ( 'page' === $type ? 'Page' : 'Post' ) . ' “' . $title . '” published';
		$obj    = $type;
		$oid    = mt_rand( 10, 900 );
		$ctx    = array( 'from' => wpsb_pick( array( 'draft', 'pending', 'future' ) ) );
	} elseif ( $roll <= 73 ) {
		$action = 'post_trashed';
		$title  = wpsb_pick( $titles );
		$msg    = 'Post “' . $title . '” moved to the trash';
		$obj    = 'post';
		$oid    = mt_rand( 10, 900 );
		$ctx    = array( 'from' => 'publish' );
	} elseif ( $roll <= 78 ) {
		$action = 'user_registered';
		$new    = mt_rand( 1000, 5000 );
		$msg    = 'New user member' . $new;
		$obj    = 'user';
		$oid    = $new;
		$ctx    = array( 'roles' => array( 'subscriber' ) );
	} elseif ( $roll <= 82 ) {
		$action = mt_rand( 0, 1 ) ? 'plugin_activated' : 'plugin_deactivated';
		$plugin = wpsb_pick( $plugins );
		$msg    = 'Plugin ' . $plugin . ( 'plugin_activated' === $action ? ' activated' : ' deactivated' );
		$obj    = 'plugin';
		$ctx    = array( 'plugin' => $plugin );
	} elseif ( $roll <= 92 ) {
		$action = 'backup_completed';
		$size   = mt_rand( 10, 900 );
		$msg    = 'Backup completed (' . $size . ' MB, 100% verified)';
		$obj    = 'backup';
		$oid    = $id;
		$ctx    = array(
			'size_mb' => $size,
			'files'   => mt_rand( 1000, 50000 ),
			'targets' => array( 's3', 'local' ),
			'meta'    => array(
				'host'  => 'web-' . mt_rand( 1, 4 ),
				'ratio' => mt_rand( 10, 99 ) / 100,
			),
		);
	} else {
		$action = 'form_submitted';
		$msg    = 'Form “' . wpsb_pick( array( 'Contact', 'Quote request', 'Newsletter' ) ) . '” submitted by ' . wpsb_pick( array( 'jane@example.org', 'o\'neil@example.org', 'max_mustermann@example.de' ) );
		$obj    = 'form';
		$oid    = mt_rand( 1, 5 );
		$ctx    = array( 'fields' => mt_rand( 2, 9 ), 'spam_score' => mt_rand( 0, 100 ) / 10 );
	}

	if ( 0 === $id % 211 ) {
		$msg .= ' ' . str_repeat( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 12 );
	}

	$ip = '';
	if ( mt_rand( 1, 10 ) > 1 ) {
		$ip = mt_rand( 0, 5 ) ? '192.0.2.' . mt_rand( 1, 254 ) : '2001:db8::' . dechex( mt_rand( 1, 65535 ) );
	}

	$entries[ $id ] = array(
		'id'          => $id,
		'time'        => $t,
		'user_id'     => $user,
		'action'      => $action,
		'object_type' => $obj,
		'object_id'   => $oid,
		'message'     => $msg,
		'ip'          => $ip,
		'context'     => $ctx,
	);
}

// Entries deleted by admins over the years.
foreach ( array_rand( $entries, 60 ) as $gone ) {
	unset( $entries[ $gone ] );
}

$legacy_events = array_flip( Acme\ActivityLog\Log_Store::LEGACY_EVENTS );

/**
 * Normalized entry => stored format.
 *
 * @param array $e      Entry.
 * @param bool  $legacy 1.x format.
 * @return array
 */
function wpsb_store_format( array $e, $legacy, array $legacy_events ) {
	if ( ! $legacy ) {
		return Acme\ActivityLog\Log_Store::compact( $e );
	}
	$raw = array(
		'id'        => $e['id'],
		'timestamp' => gmdate( 'Y-m-d H:i:s', $e['time'] ),
		'user'      => $e['user_id'],
		'event'     => isset( $legacy_events[ $e['action'] ] ) ? $legacy_events[ $e['action'] ] : $e['action'],
		'post_id'   => in_array( $e['object_type'], array( 'post', 'page' ), true ) ? $e['object_id'] : 0,
		'message'   => $e['message'],
	);
	$mod = $e['id'] % 7;
	if ( 0 === $mod ) {
		$raw['details'] = '';
	} elseif ( 1 === $mod ) {
		$raw['details'] = 'not json';
	} elseif ( $e['context'] ) {
		$raw['details'] = wp_json_encode( $e['context'] );
	}
	return $raw;
}

$chunks = array( 1 => array(), 2 => array(), 3 => array(), 0 => array() );
foreach ( $entries as $id => $e ) {
	if ( $id <= 5000 ) {
		$chunks[1][] = wpsb_store_format( $e, $id <= 3200, $legacy_events );
	} elseif ( $id <= 10000 ) {
		$chunks[2][] = wpsb_store_format( $e, false, $legacy_events );
	} elseif ( $id <= 15000 ) {
		$chunks[3][] = wpsb_store_format( $e, false, $legacy_events );
		if ( $id > 14850 ) {
			$chunks[0][] = wpsb_store_format( $e, false, $legacy_events ); // 2.1.0 rotation bug.
		}
	} else {
		$chunks[0][] = wpsb_store_format( $e, false, $legacy_events );
	}
}

foreach ( array( 1, 2, 3 ) as $n ) {
	delete_option( 'acme_activity_log_archive_' . $n );
	add_option( 'acme_activity_log_archive_' . $n, $chunks[ $n ], '', true );
}
delete_option( 'acme_activity_log' );
add_option( 'acme_activity_log', $chunks[0], '', true );
update_option( 'acme_activity_archive_count', 3, true );
update_option( 'acme_activity_last_id', $last_id, true );

// Per-user screen state: 2.x option + 1.x per-user options.
update_option(
	'acme_activity_ui_state',
	array(
		1                 => array(
			'per_page'       => 25,
			'hidden_columns' => array( 'ip' ),
			'last_seen'      => 1730000000,
		),
		$users['priya']   => array(
			'per_page'       => 50,
			'hidden_columns' => array(),
			'last_seen'      => 1725000000,
		),
		$users['olivia']  => array(
			'per_page'       => 7,
			'hidden_columns' => array( 'object', 'bogus' ),
			'last_seen'      => 1712345678,
		),
		999               => array(
			'per_page'       => 100,
			'hidden_columns' => array(),
			'last_seen'      => 1700000000,
		),
	),
	true
);
add_option( 'acme_activity_ui_1', array( 'rows' => 10, 'hide' => 'user', 'seen' => 1690000000 ), '', true );
add_option( 'acme_activity_ui_' . $users['lena'], array( 'rows' => 100, 'hide' => 'ip, object', 'seen' => 1720000000, 'seen_ids' => range( 1, 3000 ) ), '', true );
add_option( 'acme_activity_ui_' . $users['kofi'], array( 'rows' => 50, 'hide' => 'context', 'seen' => 0 ), '', true );
add_option( 'acme_activity_ui_888', array( 'rows' => 25, 'hide' => '', 'seen' => 1680000000 ), '', true );

WP_CLI::log( sprintf( 'Seeded %d log entries (%d bytes autoloaded).', count( $entries ), strlen( serialize( wp_load_alloptions() ) ) ) );

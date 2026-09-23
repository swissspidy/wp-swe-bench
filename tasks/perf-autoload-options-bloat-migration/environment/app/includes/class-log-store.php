<?php
/**
 * Log storage.
 *
 * The log lives in options: the "current" chunk (`acme_activity_log`) receives
 * new entries; when it reaches CHUNK_SIZE entries it is moved to an archive
 * chunk (`acme_activity_log_archive_{n}`) and a new current chunk is started.
 *
 * Stored entry formats:
 *
 * - 2.x (compact keys): array( 'id', 't' (unix), 'u', 'a', 'ot', 'oid', 'm', 'ip', 'c' (array) )
 * - 1.x (before 2.0):   array( 'id', 'timestamp' ('Y-m-d H:i:s', UTC), 'user', 'event',
 *                              'post_id', 'message', 'details' (JSON string) )
 *
 * Entries are always handed out normalized, see Log_Store::normalize().
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed log store.
 */
class Log_Store {

	/**
	 * Current chunk.
	 */
	const OPTION = 'acme_activity_log';

	/**
	 * Archive chunks: prefix + 1..n.
	 */
	const ARCHIVE_PREFIX = 'acme_activity_log_archive_';

	/**
	 * Number of archive chunks.
	 */
	const ARCHIVE_COUNT_OPTION = 'acme_activity_archive_count';

	/**
	 * Last ID handed out.
	 */
	const LAST_ID_OPTION = 'acme_activity_last_id';

	/**
	 * Entries per chunk before rotation.
	 */
	const CHUNK_SIZE = 5000;

	/**
	 * 1.x event names => 2.x actions.
	 *
	 * @var array<string, string>
	 */
	const LEGACY_EVENTS = array(
		'login'      => 'user_login',
		'publish'    => 'post_published',
		'trash'      => 'post_trashed',
		'register'   => 'user_registered',
		'activate'   => 'plugin_activated',
		'deactivate' => 'plugin_deactivated',
	);

	/**
	 * Request-level cache of all entries.
	 *
	 * @var array|null
	 */
	private $all = null;

	/**
	 * Add an entry. Returns the new entry ID.
	 *
	 * @param array $entry Normalized entry (without id).
	 * @return int
	 */
	public function insert( array $entry ) {
		$id = (int) get_option( self::LAST_ID_OPTION, 0 ) + 1;
		update_option( self::LAST_ID_OPTION, $id, true );

		$entry['id'] = $id;

		$chunk   = $this->get_chunk( self::OPTION );
		$chunk[] = self::compact( $entry );

		if ( count( $chunk ) >= self::CHUNK_SIZE ) {
			$this->rotate( $chunk );
		} else {
			update_option( self::OPTION, $chunk, true );
		}

		$this->all = null;
		return $id;
	}

	/**
	 * Move a full current chunk to the archive and start a new one.
	 *
	 * @param array $chunk Full chunk.
	 */
	private function rotate( array $chunk ) {
		$n = (int) get_option( self::ARCHIVE_COUNT_OPTION, 0 ) + 1;
		update_option( self::ARCHIVE_PREFIX . $n, $chunk, true );
		update_option( self::ARCHIVE_COUNT_OPTION, $n, true );
		update_option( self::OPTION, array(), true );
	}

	/**
	 * Names of all chunk options, oldest first.
	 *
	 * @return string[]
	 */
	public function chunk_names() {
		$names = array();
		$count = (int) get_option( self::ARCHIVE_COUNT_OPTION, 0 );
		for ( $i = 1; $i <= $count; $i++ ) {
			$names[] = self::ARCHIVE_PREFIX . $i;
		}
		$names[] = self::OPTION;
		return $names;
	}

	/**
	 * Raw chunk.
	 *
	 * @param string $name Option name.
	 * @return array
	 */
	public function get_chunk( $name ) {
		$chunk = get_option( $name, array() );
		return is_array( $chunk ) ? $chunk : array();
	}

	/**
	 * All entries, normalized, keyed by ID.
	 *
	 * Chunks written by 2.1.0 may contain the same entry twice (in the last
	 * archive chunk and in the current chunk). They are identical, so the first
	 * one wins.
	 *
	 * @return array<int, array>
	 */
	public function all() {
		if ( null !== $this->all ) {
			return $this->all;
		}
		$all = array();
		foreach ( $this->chunk_names() as $name ) {
			foreach ( $this->get_chunk( $name ) as $raw ) {
				$entry = self::normalize( $raw );
				if ( ! $entry['id'] || isset( $all[ $entry['id'] ] ) ) {
					continue;
				}
				$all[ $entry['id'] ] = $entry;
			}
		}
		$this->all = $all;
		return $all;
	}

	/**
	 * Find one entry.
	 *
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public function get( $id ) {
		$all = $this->all();
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	/**
	 * Query entries.
	 *
	 * @param array $args See acme_activity_get_entries().
	 * @return array{entries: array[], total: int}
	 */
	public function query( array $args ) {
		$args    = self::parse_query_args( $args );
		$matches = array();

		foreach ( $this->all() as $entry ) {
			if ( self::matches( $entry, $args ) ) {
				$matches[] = $entry;
			}
		}

		$desc = 'ASC' !== $args['order'];
		usort(
			$matches,
			static function ( $a, $b ) use ( $desc ) {
				$cmp = array( $a['time'], $a['id'] ) <=> array( $b['time'], $b['id'] );
				return $desc ? -$cmp : $cmp;
			}
		);

		$total = count( $matches );
		if ( $args['per_page'] > 0 ) {
			$matches = array_slice( $matches, ( $args['page'] - 1 ) * $args['per_page'], $args['per_page'] );
		}

		return array(
			'entries' => array_values( $matches ),
			'total'   => $total,
		);
	}

	/**
	 * Distinct actions present in the log (for the admin filter).
	 *
	 * @return string[]
	 */
	public function distinct_actions() {
		$actions = array();
		foreach ( $this->all() as $entry ) {
			$actions[ $entry['action'] ] = true;
		}
		$actions = array_keys( $actions );
		sort( $actions );
		return $actions;
	}

	/**
	 * Delete entries by ID.
	 *
	 * @param int[] $ids Entry IDs.
	 * @return int Number of entries deleted.
	 */
	public function delete( array $ids ) {
		$ids = array_flip( array_map( 'intval', $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$deleted = array();
		foreach ( $this->chunk_names() as $name ) {
			$chunk = $this->get_chunk( $name );
			$kept  = array();
			foreach ( $chunk as $raw ) {
				$id = isset( $raw['id'] ) ? (int) $raw['id'] : 0;
				if ( isset( $ids[ $id ] ) ) {
					$deleted[ $id ] = true;
					continue;
				}
				$kept[] = $raw;
			}
			if ( count( $kept ) !== count( $chunk ) ) {
				update_option( $name, $kept );
			}
		}
		$this->all = null;
		return count( $deleted );
	}

	/**
	 * Normalize query args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	public static function parse_query_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'action'      => '',
				'user_id'     => 0,
				'object_type' => '',
				'object_id'   => 0,
				'since'       => 0,
				'until'       => 0,
				'search'      => '',
				'order'       => 'DESC',
				'per_page'    => 20,
				'page'        => 1,
			)
		);

		$args['action']      = array_values( array_filter( array_map( 'strval', (array) $args['action'] ) ) );
		$args['user_id']     = (int) $args['user_id'];
		$args['object_type'] = (string) $args['object_type'];
		$args['object_id']   = (int) $args['object_id'];
		$args['since']       = (int) $args['since'];
		$args['until']       = (int) $args['until'];
		$args['search']      = trim( (string) $args['search'] );
		$args['order']       = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$args['per_page']    = (int) $args['per_page'];
		$args['page']        = max( 1, (int) $args['page'] );
		return $args;
	}

	/**
	 * Does an entry match the (parsed) query args?
	 *
	 * @param array $entry Normalized entry.
	 * @param array $args  Parsed args.
	 * @return bool
	 */
	private static function matches( array $entry, array $args ) {
		if ( $args['action'] && ! in_array( $entry['action'], $args['action'], true ) ) {
			return false;
		}
		if ( $args['user_id'] && $entry['user_id'] !== $args['user_id'] ) {
			return false;
		}
		if ( '' !== $args['object_type'] && $entry['object_type'] !== $args['object_type'] ) {
			return false;
		}
		if ( $args['object_id'] && $entry['object_id'] !== $args['object_id'] ) {
			return false;
		}
		if ( $args['since'] && $entry['time'] < $args['since'] ) {
			return false;
		}
		if ( $args['until'] && $entry['time'] > $args['until'] ) {
			return false;
		}
		if ( '' !== $args['search'] && false === stripos( $entry['message'], $args['search'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Normalize a stored entry (any format) to the public shape.
	 *
	 * @param mixed $raw Stored entry.
	 * @return array{id:int, time:int, user_id:int, action:string, object_type:string, object_id:int, message:string, ip:string, context:array}
	 */
	public static function normalize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		if ( isset( $raw['event'] ) || isset( $raw['timestamp'] ) ) {
			// 1.x format.
			$event   = isset( $raw['event'] ) ? (string) $raw['event'] : '';
			$post_id = isset( $raw['post_id'] ) ? (int) $raw['post_id'] : 0;
			$details = isset( $raw['details'] ) ? json_decode( (string) $raw['details'], true ) : array();
			$time    = isset( $raw['timestamp'] ) ? strtotime( $raw['timestamp'] . ' UTC' ) : 0;

			return array(
				'id'          => isset( $raw['id'] ) ? (int) $raw['id'] : 0,
				'time'        => $time ? (int) $time : 0,
				'user_id'     => isset( $raw['user'] ) ? (int) $raw['user'] : 0,
				'action'      => isset( self::LEGACY_EVENTS[ $event ] ) ? self::LEGACY_EVENTS[ $event ] : $event,
				'object_type' => $post_id ? 'post' : '',
				'object_id'   => $post_id,
				'message'     => isset( $raw['message'] ) ? (string) $raw['message'] : '',
				'ip'          => '',
				'context'     => is_array( $details ) ? $details : array(),
			);
		}

		return array(
			'id'          => isset( $raw['id'] ) ? (int) $raw['id'] : 0,
			'time'        => isset( $raw['t'] ) ? (int) $raw['t'] : 0,
			'user_id'     => isset( $raw['u'] ) ? (int) $raw['u'] : 0,
			'action'      => isset( $raw['a'] ) ? (string) $raw['a'] : '',
			'object_type' => isset( $raw['ot'] ) ? (string) $raw['ot'] : '',
			'object_id'   => isset( $raw['oid'] ) ? (int) $raw['oid'] : 0,
			'message'     => isset( $raw['m'] ) ? (string) $raw['m'] : '',
			'ip'          => isset( $raw['ip'] ) ? (string) $raw['ip'] : '',
			'context'     => isset( $raw['c'] ) && is_array( $raw['c'] ) ? $raw['c'] : array(),
		);
	}

	/**
	 * Normalized entry => 2.x storage format.
	 *
	 * @param array $entry Normalized entry.
	 * @return array
	 */
	public static function compact( array $entry ) {
		return array(
			'id'  => (int) $entry['id'],
			't'   => (int) $entry['time'],
			'u'   => (int) $entry['user_id'],
			'a'   => (string) $entry['action'],
			'ot'  => (string) $entry['object_type'],
			'oid' => (int) $entry['object_id'],
			'm'   => (string) $entry['message'],
			'ip'  => (string) $entry['ip'],
			'c'   => (array) $entry['context'],
		);
	}
}

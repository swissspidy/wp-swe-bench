<?php
/**
 * wp-swe-bench persistent object cache drop-in (grading only).
 *
 * Behaves like a persistent (Redis/Memcached-style) object cache: values survive the request
 * and are shared by every PHP process of the site (Playground workers, WP-CLI, PHPUnit).
 * Storage: a separate SQLite database (wp-content/wpsb-object-cache.sqlite), with an
 * in-memory runtime layer per request. `add` is atomic across processes (INSERT OR IGNORE),
 * as with Redis SETNX / Memcached add.
 *
 * Test helper: $wp_object_cache->wpsb_new_request() drops the in-memory layer, as if a new
 * request started (the persistent store is kept).
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPSB_OBJECT_CACHE_DB' ) ) {
	define( 'WPSB_OBJECT_CACHE_DB', WP_CONTENT_DIR . '/wpsb-object-cache.sqlite' );
}

class WPSB_Object_Cache {

	/** @var array<string, array<string, mixed>> Runtime layer: group => key => value. */
	private $cache = array();

	/** @var array<string, bool> */
	private $non_persistent = array();

	/** @var array<string, bool> */
	private $global_groups = array();

	/** @var int */
	private $blog_id = 1;

	/** @var PDO|null */
	private $pdo = null;

	public $cache_hits   = 0;
	public $cache_misses = 0;

	/** Drop the in-memory layer (a new request). */
	public function wpsb_new_request() {
		$this->cache = array();
	}

	private function db() {
		if ( null === $this->pdo ) {
			$this->pdo = new PDO( 'sqlite:' . WPSB_OBJECT_CACHE_DB );
			$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
			$this->pdo->setAttribute( PDO::ATTR_TIMEOUT, 30 );
			$this->pdo->exec( 'PRAGMA journal_mode = DELETE' );
			$this->pdo->exec( 'CREATE TABLE IF NOT EXISTS cache ( g TEXT NOT NULL, k TEXT NOT NULL, v BLOB NOT NULL, e INTEGER NOT NULL DEFAULT 0, PRIMARY KEY ( g, k ) )' );
		}
		return $this->pdo;
	}

	private function q( $sql, array $params = array() ) {
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			try {
				$st = $this->db()->prepare( $sql );
				$st->execute( $params );
				return $st;
			} catch ( PDOException $e ) {
				if ( false === stripos( $e->getMessage(), 'locked' ) && false === stripos( $e->getMessage(), 'busy' ) ) {
					error_log( 'wpsb object cache: ' . $e->getMessage() );
					return null;
				}
				usleep( 50000 );
			}
		}
		return null;
	}

	private function group( $group ) {
		return '' === (string) $group ? 'default' : (string) $group;
	}

	private function persistent( $group ) {
		return empty( $this->non_persistent[ $group ] );
	}

	private function g( $group ) {
		return ( isset( $this->global_groups[ $group ] ) ? 'g' : 'b' . $this->blog_id ) . ':' . $group;
	}

	private function copy_value( $value ) {
		return is_object( $value ) ? clone $value : $value;
	}

	private function load( $key, $group ) {
		$st = $this->q( 'SELECT v, e FROM cache WHERE g = ? AND k = ?', array( $this->g( $group ), $key ) );
		$row = $st ? $st->fetch( PDO::FETCH_ASSOC ) : false;
		if ( ! $row ) {
			return array( false, null );
		}
		if ( $row['e'] && (int) $row['e'] < time() ) {
			$this->q( 'DELETE FROM cache WHERE g = ? AND k = ? AND e = ?', array( $this->g( $group ), $key, $row['e'] ) );
			return array( false, null );
		}
		return array( true, unserialize( $row['v'] ) );
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$group = $this->group( $group );
		$key   = (string) $key;
		if ( ! $force && isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] ) ) {
			$found = true;
			++$this->cache_hits;
			return $this->copy_value( $this->cache[ $group ][ $key ] );
		}
		if ( $this->persistent( $group ) ) {
			list( $hit, $value ) = $this->load( $key, $group );
			if ( $hit ) {
				$this->cache[ $group ][ $key ] = $value;
				$found                         = true;
				++$this->cache_hits;
				return $this->copy_value( $value );
			}
		}
		$found = false;
		++$this->cache_misses;
		return false;
	}

	public function get_multiple( $keys, $group = 'default', $force = false ) {
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = $this->get( $key, $group, $force );
		}
		return $out;
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$group = $this->group( $group );
		$key   = (string) $key;
		$data  = $this->copy_value( $data );
		$this->cache[ $group ][ $key ] = $data;
		if ( $this->persistent( $group ) ) {
			return null !== $this->q( 'INSERT OR REPLACE INTO cache ( g, k, v, e ) VALUES ( ?, ?, ?, ? )', array( $this->g( $group ), $key, serialize( $data ), $expire ? time() + (int) $expire : 0 ) );
		}
		return true;
	}

	public function set_multiple( array $data, $group = 'default', $expire = 0 ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			$out[ $key ] = $this->set( $key, $value, $group, $expire );
		}
		return $out;
	}

	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}
		$group = $this->group( $group );
		$key   = (string) $key;
		if ( ! $this->persistent( $group ) ) {
			if ( isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] ) ) {
				return false;
			}
			$this->cache[ $group ][ $key ] = $this->copy_value( $data );
			return true;
		}
		// Drop an expired entry, then add atomically.
		$this->q( 'DELETE FROM cache WHERE g = ? AND k = ? AND e > 0 AND e < ?', array( $this->g( $group ), $key, time() ) );
		$st = $this->q( 'INSERT OR IGNORE INTO cache ( g, k, v, e ) VALUES ( ?, ?, ?, ? )', array( $this->g( $group ), $key, serialize( $data ), $expire ? time() + (int) $expire : 0 ) );
		if ( ! $st || 1 !== $st->rowCount() ) {
			return false;
		}
		$this->cache[ $group ][ $key ] = $this->copy_value( $data );
		return true;
	}

	public function add_multiple( array $data, $group = '', $expire = 0 ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			$out[ $key ] = $this->add( $key, $value, $group, $expire );
		}
		return $out;
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$found = false;
		$this->get( $key, $group, true, $found );
		if ( ! $found ) {
			return false;
		}
		return $this->set( $key, $data, $group, $expire );
	}

	public function delete( $key, $group = 'default' ) {
		$group = $this->group( $group );
		$key   = (string) $key;
		$had   = isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] );
		unset( $this->cache[ $group ][ $key ] );
		if ( $this->persistent( $group ) ) {
			$st = $this->q( 'DELETE FROM cache WHERE g = ? AND k = ?', array( $this->g( $group ), $key ) );
			return ( $st && $st->rowCount() > 0 ) || $had;
		}
		return $had;
	}

	public function delete_multiple( array $keys, $group = '' ) {
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = $this->delete( $key, $group );
		}
		return $out;
	}

	private function crement( $key, $offset, $group ) {
		$group = $this->group( $group );
		$key   = (string) $key;
		if ( ! $this->persistent( $group ) ) {
			if ( ! isset( $this->cache[ $group ][ $key ] ) ) {
				return false;
			}
			$this->cache[ $group ][ $key ] = max( 0, (int) $this->cache[ $group ][ $key ] + $offset );
			return $this->cache[ $group ][ $key ];
		}
		$db = $this->db();
		try {
			$db->exec( 'BEGIN IMMEDIATE' );
			list( $hit, $value ) = $this->load( $key, $group );
			if ( ! $hit ) {
				$db->exec( 'COMMIT' );
				return false;
			}
			$value = max( 0, ( is_numeric( $value ) ? (int) $value : 0 ) + $offset );
			$this->q( 'UPDATE cache SET v = ? WHERE g = ? AND k = ?', array( serialize( $value ), $this->g( $group ), $key ) );
			$db->exec( 'COMMIT' );
		} catch ( PDOException $e ) {
			try {
				$db->exec( 'ROLLBACK' );
			} catch ( PDOException $ignored ) { // phpcs:ignore
			}
			return false;
		}
		$this->cache[ $group ][ $key ] = $value;
		return $value;
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
		return $this->crement( $key, (int) $offset, $group );
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
		return $this->crement( $key, -(int) $offset, $group );
	}

	public function flush() {
		$this->cache = array();
		$this->q( 'DELETE FROM cache' );
		return true;
	}

	public function flush_runtime() {
		$this->cache = array();
		return true;
	}

	public function flush_group( $group ) {
		$group = $this->group( $group );
		unset( $this->cache[ $group ] );
		$this->q( 'DELETE FROM cache WHERE g = ?', array( $this->g( $group ) ) );
		return true;
	}

	public function add_global_groups( $groups ) {
		foreach ( (array) $groups as $g ) {
			$this->global_groups[ $g ] = true;
		}
	}

	public function add_non_persistent_groups( $groups ) {
		foreach ( (array) $groups as $g ) {
			$this->non_persistent[ $g ] = true;
		}
	}

	public function switch_to_blog( $blog_id ) {
		$this->blog_id = (int) $blog_id;
	}

	public function stats() {
		echo '<p>wpsb object cache: ' . (int) $this->cache_hits . ' hits, ' . (int) $this->cache_misses . ' misses</p>';
	}

	public function close() {
		$this->pdo = null;
		return true;
	}
}

function wp_cache_init() {
	$GLOBALS['wp_object_cache'] = new WPSB_Object_Cache();
}
function wp_cache_supports( $feature ) {
	return in_array( $feature, array( 'add_multiple', 'set_multiple', 'get_multiple', 'delete_multiple', 'flush_runtime', 'flush_group' ), true );
}
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add( $key, $data, $group, (int) $expire );
}
function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->add_multiple( $data, $group, (int) $expire );
}
function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->replace( $key, $data, $group, (int) $expire );
}
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set( $key, $data, $group, (int) $expire );
}
function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
	return $GLOBALS['wp_object_cache']->set_multiple( $data, $group, (int) $expire );
}
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	return $GLOBALS['wp_object_cache']->get( $key, $group, $force, $found );
}
function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
	return $GLOBALS['wp_object_cache']->get_multiple( $keys, $group, $force );
}
function wp_cache_delete( $key, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete( $key, $group );
}
function wp_cache_delete_multiple( array $keys, $group = '' ) {
	return $GLOBALS['wp_object_cache']->delete_multiple( $keys, $group );
}
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->incr( $key, $offset, $group );
}
function wp_cache_decr( $key, $offset = 1, $group = '' ) {
	return $GLOBALS['wp_object_cache']->decr( $key, $offset, $group );
}
function wp_cache_flush() {
	return $GLOBALS['wp_object_cache']->flush();
}
function wp_cache_flush_runtime() {
	return $GLOBALS['wp_object_cache']->flush_runtime();
}
function wp_cache_flush_group( $group ) {
	return $GLOBALS['wp_object_cache']->flush_group( $group );
}
function wp_cache_close() {
	return true;
}
function wp_cache_add_global_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_global_groups( $groups );
}
function wp_cache_add_non_persistent_groups( $groups ) {
	$GLOBALS['wp_object_cache']->add_non_persistent_groups( $groups );
}
function wp_cache_switch_to_blog( $blog_id ) {
	$GLOBALS['wp_object_cache']->switch_to_blog( $blog_id );
}
function wp_cache_reset() {
	$GLOBALS['wp_object_cache']->flush_runtime();
}

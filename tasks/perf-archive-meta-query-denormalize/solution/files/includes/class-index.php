<?php
/**
 * Search index: a denormalized copy of the searchable listing meta.
 *
 * Post meta stays the source of truth (the CRM sync and importers write it
 * directly). Every change to one of the searchable meta keys, however it is
 * made, re-indexes the listing, so the search never has to join or
 * pattern-match the post meta table.
 *
 * Tables:
 * - `{prefix}acme_listing_index`    one row per listing: status, price, bedrooms, city.
 *                                   NULL = the meta does not exist.
 * - `{prefix}acme_listing_features` one row per listing and feature slug.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Search index.
 */
class Index {

	/**
	 * Schema version.
	 */
	const DB_VERSION = 1;

	/**
	 * Installed schema version option.
	 */
	const DB_VERSION_OPTION = 'acme_re_index_db_version';

	/**
	 * Listings per backfill batch.
	 */
	const BATCH = 200;

	/**
	 * Meta keys the index is built from.
	 *
	 * @var string[]
	 */
	const WATCHED_KEYS = array( Listing::META_PRICE, Listing::META_BEDROOMS, Listing::META_CITY, Listing::META_FEATURES, Listing::META_STATUS );

	/**
	 * Index table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_listing_index';
	}

	/**
	 * Features table.
	 *
	 * @return string
	 */
	public static function features_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_listing_features';
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'added_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_change' ), 10, 3 );
		add_action( 'save_post_' . Listing::POST_TYPE, array( $this, 'on_save' ), 20 );
		add_action( 'deleted_post', array( $this, 'on_deleted' ) );
	}

	/**
	 * Is the schema installed?
	 *
	 * @return bool
	 */
	public static function is_installed() {
		return (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION;
	}

	/**
	 * Create / update the tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$index    = self::table();
		$features = self::features_table();

		dbDelta(
			"CREATE TABLE {$index} (
			post_id bigint(20) unsigned NOT NULL,
			status varchar(20) NULL,
			price bigint(20) NULL,
			bedrooms smallint(5) NULL,
			city varchar(191) NULL,
			PRIMARY KEY  (post_id),
			KEY status_price (status,price),
			KEY status_bedrooms (status,bedrooms),
			KEY city_status (city,status)
		) {$charset};
		CREATE TABLE {$features} (
			post_id bigint(20) unsigned NOT NULL,
			feature varchar(64) NOT NULL,
			PRIMARY KEY  (post_id,feature),
			KEY feature (feature,post_id)
		) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop the tables.
	 */
	public static function uninstall() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::features_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Meta added / updated / deleted.
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public function on_meta_change( $meta_id, $object_id, $meta_key ) {
		if ( ! in_array( $meta_key, self::WATCHED_KEYS, true ) ) {
			return;
		}
		if ( $object_id ) {
			$this->index_post( (int) $object_id );
			return;
		}
		// delete_post_meta_by_key() (no post ID): rebuild everything.
		$this->rebuild();
	}

	/**
	 * Listing saved (covers listings created without any searchable meta).
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_save( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) ) {
			$this->index_post( (int) $post_id );
		}
	}

	/**
	 * Listing deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_deleted( $post_id ) {
		$this->remove( (int) $post_id );
	}

	/**
	 * (Re-)index one listing from its meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function index_post( $post_id ) {
		global $wpdb;
		if ( ! self::is_installed() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || Listing::POST_TYPE !== $post->post_type ) {
			$this->remove( $post_id );
			return;
		}

		$meta = get_post_meta( $post_id );
		$one  = static function ( $key ) use ( $meta ) {
			return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : null;
		};

		$price    = $one( Listing::META_PRICE );
		$bedrooms = $one( Listing::META_BEDROOMS );
		$row      = array(
			'post_id'  => $post_id,
			'status'   => $one( Listing::META_STATUS ),
			'price'    => null === $price ? null : (int) $price,
			'bedrooms' => null === $bedrooms ? null : (int) $bedrooms,
			'city'     => $one( Listing::META_CITY ),
		);

		$wpdb->replace( self::table(), $row, array( '%d', '%s', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$features = Features::normalize( maybe_unserialize( (string) $one( Listing::META_FEATURES ) ) );
		$wpdb->delete( self::features_table(), array( 'post_id' => $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $features ) {
			$values = array();
			$params = array();
			foreach ( $features as $feature ) {
				$values[] = '(%d, %s)';
				$params[] = $post_id;
				$params[] = $feature;
			}
			$sql = 'INSERT INTO ' . self::features_table() . ' (post_id, feature) VALUES ' . implode( ', ', $values );
			$wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Remove a listing from the index.
	 *
	 * @param int $post_id Post ID.
	 */
	public function remove( $post_id ) {
		global $wpdb;
		if ( ! self::is_installed() ) {
			return;
		}
		$wpdb->delete( self::table(), array( 'post_id' => $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::features_table(), array( 'post_id' => $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Rebuild the whole index from post meta.
	 *
	 * @param callable|null $progress Called with the number of listings indexed so far.
	 * @return int Number of listings indexed.
	 */
	public function rebuild( $progress = null ) {
		global $wpdb;
		if ( ! self::is_installed() ) {
			self::install();
		}
		$wpdb->query( 'DELETE FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . self::features_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery

		$done    = 0;
		$last_id = 0;
		do {
			$ids = array_map(
				'intval',
				$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d", Listing::POST_TYPE, $last_id, self::BATCH )
				)
			);
			if ( ! $ids ) {
				break;
			}
			_prime_post_caches( $ids, false, true );
			foreach ( $ids as $id ) {
				$this->index_post( $id );
			}
			$batch   = count( $ids );
			$done   += $batch;
			$last_id = end( $ids );
			if ( $progress ) {
				call_user_func( $progress, $done );
			}
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		} while ( self::BATCH === $batch );

		return $done;
	}
}

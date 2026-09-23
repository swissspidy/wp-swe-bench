<?php
/**
 * Integration with the WordPress personal data tools (Tools → Export / Erase Personal Data)
 * and the privacy policy guide.
 *
 * A person is identified by the email address of the request:
 * - their account (if an account uses that address): profile meta, ledger rows linked to the
 *   account, newsletter rows linked to the account, orders linked to the account;
 * - rows that carry the address themselves (compared case-insensitively, 1.x data kept the
 *   spelling as typed): ledger rows of deleted accounts, newsletter rows, (guest) orders.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Personal data exporters, erasers and privacy policy text.
 */
class Privacy {

	/**
	 * Max rows handled per exporter/eraser call.
	 */
	const PER_PAGE = 100;

	/**
	 * Replacement for anonymized order email addresses.
	 */
	const ANONYMOUS_EMAIL = 'deleted@site.invalid';

	/**
	 * Every user meta key the plugin writes (including 1.x keys).
	 */
	const USER_META_KEYS = array(
		'acme_loyalty_member_since',
		'acme_loyalty_tier',
		'acme_loyalty_phone',
		'acme_loyalty_referral_code',
		'_acme_loyalty_referred_by',
		'acme_loyalty_birthday',
		'acme_loyalty_preferences',
		'acme_loyalty_dob',
		'acme_loyalty_prefs',
	);

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	/**
	 * Register the exporters.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporters( $exporters ) {
		$exporters['acme-loyalty-membership'] = array(
			'exporter_friendly_name' => __( 'Acme Loyalty membership', 'acme-loyalty' ),
			'callback'               => array( $this, 'export_membership' ),
		);
		$exporters['acme-loyalty-points']     = array(
			'exporter_friendly_name' => __( 'Acme Loyalty points history', 'acme-loyalty' ),
			'callback'               => array( $this, 'export_points' ),
		);
		$exporters['acme-loyalty-newsletter'] = array(
			'exporter_friendly_name' => __( 'Acme Loyalty newsletter', 'acme-loyalty' ),
			'callback'               => array( $this, 'export_newsletter' ),
		);
		$exporters['acme-loyalty-orders']     = array(
			'exporter_friendly_name' => __( 'Acme Loyalty orders', 'acme-loyalty' ),
			'callback'               => array( $this, 'export_orders' ),
		);
		return $exporters;
	}

	/**
	 * Register the erasers.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_erasers( $erasers ) {
		$erasers['acme-loyalty-membership'] = array(
			'eraser_friendly_name' => __( 'Acme Loyalty membership', 'acme-loyalty' ),
			'callback'             => array( $this, 'erase_membership' ),
		);
		$erasers['acme-loyalty-points']     = array(
			'eraser_friendly_name' => __( 'Acme Loyalty points history', 'acme-loyalty' ),
			'callback'             => array( $this, 'erase_points' ),
		);
		$erasers['acme-loyalty-newsletter'] = array(
			'eraser_friendly_name' => __( 'Acme Loyalty newsletter', 'acme-loyalty' ),
			'callback'             => array( $this, 'erase_newsletter' ),
		);
		$erasers['acme-loyalty-orders']     = array(
			'eraser_friendly_name' => __( 'Acme Loyalty orders', 'acme-loyalty' ),
			'callback'             => array( $this, 'erase_orders' ),
		);
		return $erasers;
	}

	// ---------------------------------------------------------------------------------------------
	// Lookups.
	// ---------------------------------------------------------------------------------------------

	/**
	 * Normalized email.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	private static function normalize( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/**
	 * Account ID for an email address (0 if none).
	 *
	 * @param string $email Email.
	 * @return int
	 */
	private static function user_id_for( $email ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			global $wpdb;
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE LOWER(user_email) = %s LIMIT 1", self::normalize( $email ) ) );
			return $id;
		}
		return (int) $user->ID;
	}

	/**
	 * WHERE clause matching ledger/newsletter rows of a person.
	 *
	 * @param string $email   Email.
	 * @param int    $user_id Account ID or 0.
	 * @return string Prepared SQL fragment.
	 */
	private static function person_where( $email, $user_id ) {
		global $wpdb;
		if ( $user_id > 0 ) {
			return $wpdb->prepare( '( user_id = %d OR email = %s )', $user_id, $email );
		}
		return $wpdb->prepare( 'email = %s', $email );
	}

	/**
	 * Order IDs of a person.
	 *
	 * @param string $email         Email.
	 * @param int    $user_id       Account ID or 0.
	 * @param string $status_filter 'all', 'erasable' (not processing) or 'processing'.
	 * @param int    $limit         Limit.
	 * @param int    $offset        Offset.
	 * @return int[]
	 */
	private static function order_ids( $email, $user_id, $status_filter = 'all', $limit = self::PER_PAGE, $offset = 0 ) {
		global $wpdb;
		$match = $wpdb->prepare( 'e.meta_value = %s', $email );
		if ( $user_id > 0 ) {
			$match = '( ' . $match . $wpdb->prepare( ' OR c.meta_value = %s', (string) $user_id ) . ' )';
		}
		$status = '';
		if ( 'erasable' === $status_filter ) {
			$status = " AND COALESCE(s.meta_value, '') NOT IN ( '', 'processing' )";
		} elseif ( 'processing' === $status_filter ) {
			$status = " AND COALESCE(s.meta_value, '') IN ( '', 'processing' )";
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fragments prepared above.
		$sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_acme_order_email'
			LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_acme_order_customer_id'
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_acme_order_status'
			WHERE p.post_type = %s AND {$match}{$status}
			ORDER BY p.ID ASC LIMIT %d OFFSET %d";
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, Orders::POST_TYPE, $limit, $offset ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable
	}

	/**
	 * Add a name/value pair when the value is not empty.
	 *
	 * @param array  $data  Data pairs.
	 * @param string $name  Name.
	 * @param mixed  $value Value.
	 */
	private static function pair( array &$data, $name, $value ) {
		if ( null !== $value && '' !== (string) $value ) {
			$data[] = array(
				'name'  => $name,
				'value' => (string) $value,
			);
		}
	}

	// ---------------------------------------------------------------------------------------------
	// Exporters.
	// ---------------------------------------------------------------------------------------------

	/**
	 * Membership profile (user meta).
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (unused, one item).
	 * @return array
	 */
	public function export_membership( $email, $page = 1 ) {
		$user_id = self::user_id_for( $email );
		$items   = array();
		if ( $user_id && self::has_profile_data( $user_id ) ) {
			$profile = Members::get_profile( $user_id );
			$data    = array();
			if ( Members::is_member( $user_id ) ) {
				self::pair( $data, __( 'Tier', 'acme-loyalty' ), acme_loyalty_tier_label( $profile['tier'] ) );
			}
			self::pair( $data, __( 'Member since', 'acme-loyalty' ), $profile['member_since'] );
			self::pair( $data, __( 'Birthday', 'acme-loyalty' ), $profile['birthday'] );
			self::pair( $data, __( 'Phone', 'acme-loyalty' ), $profile['phone'] );
			self::pair( $data, __( 'Referral code', 'acme-loyalty' ), $profile['referral_code'] );
			if ( $profile['referred_by'] ) {
				$referrer = get_userdata( $profile['referred_by'] );
				self::pair( $data, __( 'Referred by', 'acme-loyalty' ), $referrer ? $referrer->display_name : '' );
			}
			self::pair( $data, __( 'Preferred contact channels', 'acme-loyalty' ), Members::format_channels( $profile['channels'] ) );
			self::pair( $data, __( 'Favourite store', 'acme-loyalty' ), $profile['store'] );
			self::pair( $data, __( 'Points balance', 'acme-loyalty' ), (string) Ledger::balance( $user_id ) );

			$items[] = array(
				'group_id'          => 'acme-loyalty-membership',
				'group_label'       => __( 'Loyalty membership', 'acme-loyalty' ),
				'group_description' => __( 'Your Acme Loyalty member profile.', 'acme-loyalty' ),
				'item_id'           => 'acme-loyalty-member-' . $user_id,
				'data'              => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Does the account have any loyalty profile meta?
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	private static function has_profile_data( $user_id ) {
		foreach ( self::USER_META_KEYS as $key ) {
			if ( metadata_exists( 'user', $user_id, $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Points history (ledger rows), paged.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (1-based).
	 * @return array
	 */
	public function export_points( $email, $page = 1 ) {
		global $wpdb;
		$page    = max( 1, (int) $page );
		$table   = Installer::ledger_table();
		$where   = self::person_where( $email, self::user_id_for( $email ) );
		$reasons = acme_loyalty_reasons();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) );
		$items = array();
		foreach ( $rows as $row ) {
			$data = array();
			self::pair( $data, __( 'Date', 'acme-loyalty' ), $row->created_at );
			self::pair( $data, __( 'Points', 'acme-loyalty' ), (string) (int) $row->points );
			self::pair( $data, __( 'Reason', 'acme-loyalty' ), $reasons[ $row->reason ] ?? $row->reason );
			self::pair( $data, __( 'Order', 'acme-loyalty' ), $row->order_id ? Orders::number( (int) $row->order_id ) : '' );
			self::pair( $data, __( 'Note', 'acme-loyalty' ), $row->note );
			self::pair( $data, __( 'IP address', 'acme-loyalty' ), $row->ip_address );
			$items[] = array(
				'group_id'    => 'acme-loyalty-points',
				'group_label' => __( 'Loyalty points history', 'acme-loyalty' ),
				'item_id'     => 'acme-loyalty-ledger-' . (int) $row->id,
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Newsletter subscriptions.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (1-based).
	 * @return array
	 */
	public function export_newsletter( $email, $page = 1 ) {
		global $wpdb;
		$page     = max( 1, (int) $page );
		$table    = Installer::subscribers_table();
		$where    = self::person_where( $email, self::user_id_for( $email ) );
		$statuses = Newsletter::statuses();
		$sources  = Newsletter::sources();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) );
		$items = array();
		foreach ( $rows as $row ) {
			$data = array();
			self::pair( $data, __( 'Email', 'acme-loyalty' ), $row->email );
			self::pair( $data, __( 'First name', 'acme-loyalty' ), $row->first_name );
			self::pair( $data, __( 'Status', 'acme-loyalty' ), $statuses[ $row->status ] ?? $row->status );
			self::pair( $data, __( 'Subscribed on', 'acme-loyalty' ), acme_loyalty_format_date( $row->subscribed_at ) );
			self::pair( $data, __( 'Confirmed on', 'acme-loyalty' ), acme_loyalty_format_date( $row->confirmed_at ) );
			self::pair( $data, __( 'Unsubscribed on', 'acme-loyalty' ), acme_loyalty_format_date( $row->unsubscribed_at ) );
			self::pair( $data, __( 'Signup source', 'acme-loyalty' ), $sources[ $row->source ] ?? $row->source );
			self::pair( $data, __( 'IP address', 'acme-loyalty' ), $row->ip_address );
			$items[] = array(
				'group_id'    => 'acme-loyalty-newsletter',
				'group_label' => __( 'Newsletter subscription', 'acme-loyalty' ),
				'item_id'     => 'acme-loyalty-subscriber-' . (int) $row->id,
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Orders and their notes.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (1-based).
	 * @return array
	 */
	public function export_orders( $email, $page = 1 ) {
		$page     = max( 1, (int) $page );
		$ids      = self::order_ids( $email, self::user_id_for( $email ), 'all', self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$statuses = Orders::statuses();
		$items    = array();
		foreach ( $ids as $order_id ) {
			$data = array();
			self::pair( $data, __( 'Order number', 'acme-loyalty' ), Orders::number( $order_id ) );
			self::pair( $data, __( 'Order date', 'acme-loyalty' ), get_post_field( 'post_date_gmt', $order_id ) );
			self::pair( $data, __( 'Status', 'acme-loyalty' ), $statuses[ Orders::status( $order_id ) ] ?? Orders::status( $order_id ) );
			self::pair( $data, __( 'Total', 'acme-loyalty' ), get_post_meta( $order_id, '_acme_order_total', true ) );
			foreach ( Orders::get_notes( $order_id ) as $note ) {
				if ( 'customer' === $note['author'] ) {
					self::pair( $data, __( 'Customer note', 'acme-loyalty' ), $note['text'] );
				} elseif ( ! empty( $note['visible'] ) ) {
					self::pair( $data, __( 'Store note', 'acme-loyalty' ), $note['text'] );
				}
			}
			$items[] = array(
				'group_id'    => 'acme-loyalty-orders',
				'group_label' => __( 'Orders', 'acme-loyalty' ),
				'item_id'     => 'acme-loyalty-order-' . $order_id,
				'data'        => $data,
			);
		}
		return array(
			'data' => $items,
			'done' => count( $ids ) < self::PER_PAGE,
		);
	}

	// ---------------------------------------------------------------------------------------------
	// Erasers.
	// ---------------------------------------------------------------------------------------------

	/**
	 * Eraser response.
	 *
	 * @param bool     $removed  Items removed.
	 * @param bool     $retained Items retained.
	 * @param string[] $messages Messages.
	 * @param bool     $done     Done.
	 * @return array
	 */
	private static function result( $removed, $retained, array $messages, $done ) {
		return array(
			'items_removed'  => (bool) $removed,
			'items_retained' => (bool) $retained,
			'messages'       => $messages,
			'done'           => (bool) $done,
		);
	}

	/**
	 * Delete the profile meta of the account.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (unused).
	 * @return array
	 */
	public function erase_membership( $email, $page = 1 ) {
		$user_id = self::user_id_for( $email );
		$removed = false;
		if ( $user_id ) {
			foreach ( self::USER_META_KEYS as $key ) {
				if ( metadata_exists( 'user', $user_id, $key ) ) {
					delete_user_meta( $user_id, $key );
					$removed = true;
				}
			}
			wp_cache_delete( 'balance_' . $user_id, 'acme_loyalty' );
		}
		return self::result( $removed, false, array(), true );
	}

	/**
	 * Anonymize ledger rows (kept for accounting). Rows drop out of the match once anonymized,
	 * so every call works on the first rows still matching.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (ignored, see above).
	 * @return array
	 */
	public function erase_points( $email, $page = 1 ) {
		global $wpdb;
		$table   = Installer::ledger_table();
		$user_id = self::user_id_for( $email );
		$where   = self::person_where( $email, $user_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared.
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", self::PER_PAGE, ( max( 1, (int) $page ) - 1 ) * self::PER_PAGE ) ) );

		foreach ( $ids as $id ) {
			$wpdb->update(
				$table,
				array(
					'user_id'    => 0,
					'email'      => '',
					'ip_address' => '',
					'note'       => '',
				),
				array( 'id' => $id )
			);
		}
		if ( $user_id ) {
			wp_cache_delete( 'balance_' . $user_id, 'acme_loyalty' );
		}

		$messages = array();
		if ( $ids ) {
			$messages[] = __( 'Points history entries were anonymized and kept for accounting.', 'acme-loyalty' );
		}
		return self::result( (bool) $ids, (bool) $ids, $messages, count( $ids ) < self::PER_PAGE );
	}

	/**
	 * Delete newsletter subscriptions.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (ignored, deleted rows drop out).
	 * @return array
	 */
	public function erase_newsletter( $email, $page = 1 ) {
		global $wpdb;
		$table = Installer::subscribers_table();
		$where = self::person_where( $email, self::user_id_for( $email ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared.
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", self::PER_PAGE, ( max( 1, (int) $page ) - 1 ) * self::PER_PAGE ) ) );
		foreach ( $ids as $id ) {
			$wpdb->delete( $table, array( 'id' => $id ) );
		}
		return self::result( (bool) $ids, false, array(), count( $ids ) < self::PER_PAGE );
	}

	/**
	 * Remove personal data from finished orders; report orders still being processed.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page (ignored, anonymized orders drop out).
	 * @return array
	 */
	public function erase_orders( $email, $page = 1 ) {
		$user_id = self::user_id_for( $email );
		$ids     = self::order_ids( $email, $user_id, 'erasable', self::PER_PAGE );

		foreach ( $ids as $order_id ) {
			$notes = get_post_meta( $order_id, '_acme_order_notes', true );
			if ( is_array( $notes ) ) {
				$notes = array_values(
					array_filter(
						$notes,
						static function ( $note ) {
							return ! isset( $note['author'] ) || 'customer' !== $note['author'];
						}
					)
				);
				update_post_meta( $order_id, '_acme_order_notes', $notes );
			}
			delete_post_meta( $order_id, '_acme_order_note' );
			update_post_meta( $order_id, '_acme_order_email', self::ANONYMOUS_EMAIL );
			update_post_meta( $order_id, '_acme_order_customer_id', 0 );
		}

		if ( count( $ids ) >= self::PER_PAGE ) {
			return self::result( true, false, array(), false );
		}

		$messages = array();
		foreach ( self::order_ids( $email, $user_id, 'processing', 1000 ) as $order_id ) {
			/* translators: %s: order number */
			$messages[] = sprintf( __( 'Order %s is still being processed; it was not changed.', 'acme-loyalty' ), Orders::number( $order_id ) );
		}
		return self::result( (bool) $ids, (bool) $messages, $messages, true );
	}

	// ---------------------------------------------------------------------------------------------
	// Privacy policy guide.
	// ---------------------------------------------------------------------------------------------

	/**
	 * Suggested privacy policy text.
	 */
	public function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$months = (int) acme_loyalty_settings()['retention_months'];

		$text  = '<h2>' . esc_html__( 'Loyalty programme, newsletter and orders', 'acme-loyalty' ) . '</h2>';
		$text .= '<p>' . esc_html__( 'When you join our loyalty programme we store your member profile (tier, birthday, phone number, referral code, contact preferences and favourite store) and a history of the points you earn and redeem, including the IP address and a note for each entry.', 'acme-loyalty' ) . '</p>';
		$text .= '<p>' . esc_html__( 'When you sign up for our newsletter we store your email address, your first name, where and when you signed up and the IP address you signed up from. You do not need an account for this.', 'acme-loyalty' ) . '</p>';
		$text .= '<p>' . esc_html__( 'For orders we store your email address and the notes you add to an order (for example delivery instructions).', 'acme-loyalty' ) . '</p>';
		$text .= '<p>' . esc_html__( 'You can request an export or the erasure of this data. Points history entries are kept for accounting, but anonymized: they are no longer linked to you. Orders that are still being processed are not changed.', 'acme-loyalty' ) . '</p>';
		if ( $months > 0 ) {
			$text .= '<p>' . esc_html(
				sprintf(
					/* translators: %d: number of months */
					_n( 'Unconfirmed and cancelled newsletter sign-ups are deleted, and IP addresses are removed from the points history, after %d month.', 'Unconfirmed and cancelled newsletter sign-ups are deleted, and IP addresses are removed from the points history, after %d months.', $months, 'acme-loyalty' ),
					$months
				)
			) . '</p>';
		} else {
			$text .= '<p>' . esc_html__( 'We keep this data until you ask us to delete it.', 'acme-loyalty' ) . '</p>';
		}

		wp_add_privacy_policy_content( 'Acme Loyalty', wp_kses_post( $text ) );
	}
}

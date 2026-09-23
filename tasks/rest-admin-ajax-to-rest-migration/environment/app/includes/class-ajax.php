<?php
/**
 * admin-ajax handlers for the inventory screen.
 *
 * Also used by the warehouse's barcode-scanner page and the label printer
 * integration, which call these actions directly with the same parameters.
 *
 *   acme_inv_list          GET   page, orderby, order, low_stock   → {items, total, pages}
 *   acme_inv_search        GET   q, page                           → {items, total, pages}
 *   acme_inv_update_stock  POST  id, stock                         → {success, item}
 *   acme_inv_bulk_adjust   POST  ids[], delta, reason              → {success, updated}
 *   acme_inv_export        GET                                     → CSV download
 *   acme_inv_delete        POST  id                                → {success}
 *
 * Every request from the screen carries `nonce` (action "acme_inventory").
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax.
 */
class Ajax {

	const PER_PAGE = 20;

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_ajax_acme_inv_list', array( $this, 'list_items' ) );
		add_action( 'wp_ajax_acme_inv_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_acme_inv_update_stock', array( $this, 'update_stock' ) );
		add_action( 'wp_ajax_acme_inv_bulk_adjust', array( $this, 'bulk_adjust' ) );
		add_action( 'wp_ajax_acme_inv_export', array( $this, 'export' ) );
		add_action( 'wp_ajax_acme_inv_delete', array( $this, 'delete' ) );
	}

	/**
	 * Send JSON and exit.
	 *
	 * @param mixed $data   Data.
	 * @param int   $status HTTP status.
	 */
	protected function json( $data, $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( $data );
		wp_die( '', '', array( 'response' => null ) );
	}

	/**
	 * Error response.
	 *
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 */
	protected function error( $message, $status = 400 ) {
		$this->json(
			array(
				'success' => false,
				'data'    => array( 'message' => $message ),
			),
			$status
		);
	}

	/**
	 * Paged list payload.
	 *
	 * @param array $args Query args.
	 * @param int   $page Page.
	 * @return array
	 */
	protected function paged( array $args, $page ) {
		$total = Items::count( $args );
		return array(
			'items' => Items::query(
				$args + array(
					'limit'  => self::PER_PAGE,
					'offset' => ( max( 1, $page ) - 1 ) * self::PER_PAGE,
				)
			),
			'total' => $total,
			'pages' => (int) ceil( $total / self::PER_PAGE ),
		);
	}

	/**
	 * List.
	 */
	public function list_items() {
		check_ajax_referer( 'acme_inventory', 'nonce' );
		if ( ! acme_inventory_current_user_can_manage() ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'name',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc',
			'low_stock' => ! empty( $_GET['low_stock'] ),
		);
		$page = isset( $_GET['page'] ) ? (int) $_GET['page'] : 1;
		// phpcs:enable
		$this->json( $this->paged( $args, $page ) );
	}

	/**
	 * Search (SKU or name).
	 */
	public function search() {
		check_ajax_referer( 'acme_inventory', 'nonce' );
		// Warehouse staff with editor accounts used the scanner page (#212).
		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$page = isset( $_GET['page'] ) ? (int) $_GET['page'] : 1;
		// phpcs:enable
		$this->json(
			$this->paged(
				array(
					'search'    => $q,
					'low_stock' => ! empty( $_GET['low_stock'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				),
				$page
			)
		);
	}

	/**
	 * Inline stock edit.
	 */
	public function update_stock() {
		check_ajax_referer( 'acme_inventory', 'nonce', false );
		if ( ! current_user_can( 'edit_posts' ) ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$stock = isset( $_POST['stock'] ) ? (int) $_POST['stock'] : 0;
		// phpcs:enable
		if ( ! Items::find( $id ) ) {
			$this->error( __( 'Unknown item.', 'acme-inventory' ), 404 );
		}
		Items::set_stock( $id, $stock, __( 'Inline edit', 'acme-inventory' ), 'ajax' );
		$this->json(
			array(
				'success' => true,
				'item'    => Items::find( $id ),
			)
		);
	}

	/**
	 * Bulk adjustment: add delta to the stock of every selected item.
	 */
	public function bulk_adjust() {
		if ( ! acme_inventory_current_user_can_manage() ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$ids    = isset( $_REQUEST['ids'] ) ? array_map( 'intval', (array) $_REQUEST['ids'] ) : array();
		$delta  = isset( $_REQUEST['delta'] ) ? (int) $_REQUEST['delta'] : 0;
		$reason = isset( $_REQUEST['reason'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['reason'] ) ) : '';
		// phpcs:enable
		$updated = 0;
		foreach ( $ids as $id ) {
			if ( Items::adjust( $id, $delta, $reason, 'ajax' ) ) {
				++$updated;
			}
		}
		$this->json(
			array(
				'success' => true,
				'updated' => $updated,
			)
		);
	}

	/**
	 * CSV export of everything.
	 */
	public function export() {
		if ( ! acme_inventory_current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'acme-inventory' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Csv::filename() . '"' );
		echo Csv::build( Items::query( array( 'search' => $search, 'limit' => -1 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Delete.
	 */
	public function delete() {
		check_ajax_referer( 'acme_inventory', 'nonce' );
		if ( ! current_user_can( 'delete_posts' ) ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! Items::delete( $id ) ) {
			$this->error( __( 'Unknown item.', 'acme-inventory' ), 404 );
		}
		$this->json( array( 'success' => true ) );
	}
}

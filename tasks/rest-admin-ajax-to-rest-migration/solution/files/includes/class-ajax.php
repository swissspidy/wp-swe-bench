<?php
/**
 * Deprecated admin-ajax actions, kept for the barcode-scanner page and the
 * label printer integration. The Inventory screen uses the REST API.
 *
 *   acme_inv_list          GET        page, orderby, order, low_stock   → {items, total, pages}
 *   acme_inv_search        GET        q, page                           → {items, total, pages}
 *   acme_inv_update_stock  POST only  id, stock                         → {success, item}
 *   acme_inv_bulk_adjust   POST only  ids[], delta, reason              → {success, updated}
 *   acme_inv_export        GET                                          → CSV download
 *   acme_inv_delete        POST only  id                                → {success}
 *
 * Every action requires the inventory capability and a valid `nonce`
 * (action "acme_inventory"); all of them go through Service (same rules as
 * the REST API).
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
	 * Error response from a WP_Error.
	 *
	 * @param \WP_Error $error Error.
	 */
	protected function wp_error( \WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
		$detail = is_array( $data ) && ! empty( $data['params'] ) ? implode( ' ', (array) $data['params'] ) : $error->get_error_message();
		$this->error( $detail, $status );
	}

	/**
	 * Capability, nonce and (for writes) method checks.
	 *
	 * @param bool $write Whether the action changes data.
	 */
	protected function guard( $write = false ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( $write && 'POST' !== $method ) {
			$this->error( __( 'This action only accepts POST requests.', 'acme-inventory' ), 405 );
		}
		if ( ! acme_inventory_current_user_can_manage() ) {
			$this->error( __( 'You are not allowed to do that.', 'acme-inventory' ), 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'acme_inventory' ) ) {
			$this->error( __( 'Your session has expired. Please reload the page.', 'acme-inventory' ), 403 );
		}
	}

	/**
	 * Paged list payload (legacy shape: raw rows).
	 *
	 * @param array $args Query args.
	 * @param int   $page Page.
	 * @return array
	 */
	protected function paged( array $args, $page ) {
		$args  = Service::query_args( $args );
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
		$this->guard();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified in guard().
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
		$this->guard();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified in guard().
		$args = array(
			'search'    => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'low_stock' => ! empty( $_GET['low_stock'] ),
		);
		$page = isset( $_GET['page'] ) ? (int) $_GET['page'] : 1;
		// phpcs:enable
		$this->json( $this->paged( $args, $page ) );
	}

	/**
	 * Inline stock edit.
	 */
	public function update_stock() {
		$this->guard( true );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$stock = isset( $_POST['stock'] ) ? sanitize_text_field( wp_unslash( $_POST['stock'] ) ) : null;
		// phpcs:enable
		$row = Service::update( $id, array( 'stock' => $stock ), 'ajax' );
		if ( is_wp_error( $row ) ) {
			$this->wp_error( $row );
		}
		$this->json(
			array(
				'success' => true,
				'item'    => $row,
			)
		);
	}

	/**
	 * Bulk adjustment (all or nothing).
	 */
	public function bulk_adjust() {
		$this->guard( true );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		$ids    = isset( $_POST['ids'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$delta  = isset( $_POST['delta'] ) ? sanitize_text_field( wp_unslash( $_POST['delta'] ) ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		// phpcs:enable
		$rows = Service::bulk_adjust( $ids, $delta, $reason, 'ajax' );
		if ( is_wp_error( $rows ) ) {
			$this->wp_error( $rows );
		}
		$this->json(
			array(
				'success' => true,
				'updated' => count( $rows ),
			)
		);
	}

	/**
	 * CSV export.
	 */
	public function export() {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in guard().
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Csv::filename() . '"' );
		echo Service::export_csv( array( 'search' => $search ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		exit;
	}

	/**
	 * Delete.
	 */
	public function delete() {
		$this->guard( true );
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$row = Service::delete( $id );
		if ( is_wp_error( $row ) ) {
			$this->wp_error( $row );
		}
		$this->json( array( 'success' => true ) );
	}
}

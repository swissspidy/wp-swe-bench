<?php
/**
 * REST API: acme-inventory/v1/items.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Items REST controller.
 */
class Rest_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'acme-inventory/v1';
		$this->rest_base = 'items';
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'serve_csv' ), 10, 4 );
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Item ID.', 'acme-inventory' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => array( 'context' => $this->get_context_param( array( 'default' => 'view' ) ) ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-adjust',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_adjust' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'ids'    => array(
						'description' => __( 'Items to adjust.', 'acme-inventory' ),
						'type'        => 'array',
						'items'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'minItems'    => 1,
						'maxItems'    => Service::MAX_BULK,
						'required'    => true,
					),
					'delta'  => array(
						'description'       => __( 'Change of the stock (non-zero).', 'acme-inventory' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => static function ( $value, $request, $param ) {
							$valid = rest_validate_request_arg( $value, $request, $param );
							if ( is_wp_error( $valid ) ) {
								return $valid;
							}
							return 0 !== (int) $value ? true : new \WP_Error( 'rest_invalid_param', __( 'The adjustment must not be 0.', 'acme-inventory' ) );
						},
					),
					'reason' => array(
						'description' => __( 'Reason for the adjustment log.', 'acme-inventory' ),
						'type'        => 'string',
						'default'     => '',
						'maxLength'   => 191,
					),
				),
			)
		);
		$export_args = $this->get_collection_params();
		unset( $export_args['page'], $export_args['per_page'], $export_args['context'] );
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $export_args,
			)
		);
	}

	/**
	 * Every route: the inventory capability.
	 *
	 * @return true|\WP_Error
	 */
	public function permissions_check() {
		if ( acme_inventory_current_user_can_manage() ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to manage the inventory.', 'acme-inventory' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * List.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$args     = Service::query_args( $request->get_params() );
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$total    = Items::count( $args );
		$rows     = Items::query(
			$args + array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);
		$data     = array();
		foreach ( $rows as $row ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}
		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * One item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$row = Items::find( (int) $request['id'] );
		if ( ! $row ) {
			return Service::not_found();
		}
		return $this->prepare_item_for_response( $row, $request );
	}

	/**
	 * Update.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$fields = array();
		foreach ( array( 'name', 'stock', 'low_stock_threshold', 'location' ) as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$fields[ $field ] = $request[ $field ];
			}
		}
		$row = Service::update( (int) $request['id'], $fields, 'rest' );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return $this->prepare_item_for_response( $row, $request );
	}

	/**
	 * Delete.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$row = Service::delete( (int) $request['id'] );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return new \WP_REST_Response(
			array(
				'deleted'  => true,
				'previous' => $this->prepare_item_for_response( $row, $request )->get_data(),
			)
		);
	}

	/**
	 * Bulk adjust.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_adjust( $request ) {
		$rows = Service::bulk_adjust( $request['ids'], $request['delta'], (string) $request['reason'], 'rest' );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}
		return new \WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * CSV export (served raw by serve_csv()).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function export( $request ) {
		$response = new \WP_REST_Response( Service::export_csv( $request->get_params() ) );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . Csv::filename() . '"' );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Output the export as CSV instead of JSON.
	 *
	 * @param bool              $served  Already served.
	 * @param \WP_HTTP_Response $result  Response.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function serve_csv( $served, $result, $request, $server ) {
		if ( $served || '/' . $this->namespace . '/' . $this->rest_base . '/export' !== $request->get_route() ) {
			return $served;
		}
		if ( 200 !== $result->get_status() || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
		return true;
	}

	/**
	 * Row → item.
	 *
	 * @param object           $item    Row.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$updated = empty( $item->updated_at ) || '0000-00-00 00:00:00' === $item->updated_at ? null : gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $item->updated_at . ' UTC' ) );
		$data    = array(
			'id'                  => (int) $item->id,
			'sku'                 => (string) $item->sku,
			'name'                => (string) $item->name,
			'stock'               => (int) $item->stock,
			'low_stock_threshold' => (int) $item->low_stock_threshold,
			'location'            => (string) $item->location,
			'low_stock'           => (int) $item->stock <= (int) $item->low_stock_threshold,
			'updated_at'          => $updated,
		);
		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->filter_response_by_context( $this->add_additional_fields_to_object( $data, $request ), $context );
		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);
		return $response;
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'inventory-item',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array(
					'type'     => 'integer',
					'context'  => array( 'view', 'edit', 'embed' ),
					'readonly' => true,
				),
				'sku'                 => array(
					'type'     => 'string',
					'context'  => array( 'view', 'edit', 'embed' ),
					'readonly' => true,
				),
				'name'                => array(
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 191,
					'context'   => array( 'view', 'edit', 'embed' ),
				),
				'stock'               => array(
					'type'    => 'integer',
					'minimum' => 0,
					'context' => array( 'view', 'edit', 'embed' ),
				),
				'low_stock_threshold' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'context' => array( 'view', 'edit' ),
				),
				'location'            => array(
					'type'      => 'string',
					'maxLength' => 100,
					'context'   => array( 'view', 'edit' ),
				),
				'low_stock'           => array(
					'type'     => 'boolean',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'updated_at'          => array(
					'type'     => array( 'string', 'null' ),
					'format'   => 'date-time',
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Collection params.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                        = parent::get_collection_params();
		$params['context']['default']  = 'view';
		$params['per_page']['default'] = 20;
		$params['search']['description'] = __( 'Substring of the SKU or name.', 'acme-inventory' );
		$params['low_stock']           = array(
			'description' => __( 'Only items at or below their threshold.', 'acme-inventory' ),
			'type'        => 'boolean',
			'default'     => false,
		);
		$params['orderby']             = array(
			'type'    => 'string',
			'enum'    => array_keys( Items::ORDERBY ),
			'default' => 'name',
		);
		$params['order']               = array(
			'type'    => 'string',
			'enum'    => array( 'asc', 'desc' ),
			'default' => 'asc',
		);
		return $params;
	}
}

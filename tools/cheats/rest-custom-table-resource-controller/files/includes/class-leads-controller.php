<?php
/**
 * REST API v2: /acme-leads/v2/leads.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Leads resource.
 */
class Leads_Controller extends \WP_REST_Controller {

	/**
	 * Response field => table column.
	 */
	const FIELD_COLUMNS = array(
		'id'         => 'id',
		'name'       => 'name',
		'email'      => 'email',
		'company'    => 'company',
		'status'     => 'status',
		'source'     => 'source',
		'score'      => 'score',
		'owner'      => 'owner_id',
		'notes'      => 'notes',
		'created_at' => 'created_at',
		'updated_at' => 'updated_at',
	);

	/**
	 * Max leads per bulk request.
	 */
	const BULK_MAX = 100;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'acme-leads/v2';
		$this->rest_base = 'leads';
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_update' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'ids'    => array(
							'description' => __( 'Lead IDs.', 'acme-leads' ),
							'type'        => 'array',
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'minItems'    => 1,
							'maxItems'    => self::BULK_MAX,
							'required'    => true,
						),
						'status' => array(
							'description' => __( 'New status.', 'acme-leads' ),
							'type'        => 'string',
							'enum'        => Repository::STATUSES,
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Lead ID.', 'acme-leads' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array( 'context' => $this->get_context_param( array( 'default' => 'view' ) ) ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Error for missing permissions (401 when logged out).
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $message ) {
		return new WP_Error( 'rest_forbidden', $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Reading requires acme_view_leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( Installer::CAP_VIEW ) ? true : $this->forbidden( __( 'Sorry, you are not allowed to view leads.', 'acme-leads' ) );
	}

	/**
	 * Reading one lead requires acme_view_leads (visibility is checked in get_item()).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Changing leads requires acme_manage_leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( Installer::CAP_MANAGE ) ? true : $this->forbidden( __( 'Sorry, you are not allowed to edit leads.', 'acme-leads' ) );
	}

	/**
	 * Owner restriction for the current user (0: sees all leads).
	 *
	 * @return int
	 */
	private function owner_scope() {
		return current_user_can( Installer::CAP_MANAGE ) ? 0 : get_current_user_id();
	}

	/**
	 * Table columns needed for the requested fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	private function columns_for( $request ) {
		// The server strips unrequested fields from the response anyway.
		return Repository::COLUMNS;
	}

	/**
	 * Convert a request date-time to UTC `Y-m-d H:i:s`.
	 *
	 * @param string|null $value Date-time.
	 * @return string|null
	 */
	private function utc( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$timestamp = rest_parse_date( $value );
		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Invalid cursor error.
	 *
	 * @return WP_Error
	 */
	private function invalid_cursor() {
		return new WP_Error( 'acme_leads_invalid_cursor', __( 'Invalid or expired cursor.', 'acme-leads' ), array( 'status' => 400 ) );
	}

	/**
	 * GET /leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$orderby  = $request['orderby'];
		$order    = $request['order'];
		$per_page = (int) $request['per_page'];

		$after = null;
		if ( null !== $request['cursor'] && '' !== $request['cursor'] ) {
			$payload = Cursor::decode( (string) $request['cursor'] );
			if ( ! $payload || ( $payload['o'] ?? null ) !== $orderby || ( $payload['d'] ?? null ) !== $order || ! isset( $payload['i'] ) || ! array_key_exists( 'v', $payload ) ) {
				return $this->invalid_cursor();
			}
			$after = array(
				'value' => $payload['v'],
				'id'    => (int) $payload['i'],
			);
		}

		$column = 'id' === $orderby ? 'id' : $orderby;
		$rows   = Repository::query_keyset(
			array(
				'columns'        => $this->columns_for( $request ),
				'statuses'       => (array) $request['status'],
				'owner'          => $this->owner_scope(),
				'created_after'  => $this->utc( $request['created_after'] ),
				'created_before' => $this->utc( $request['created_before'] ),
				'search'         => (string) $request['search'],
				'orderby'        => $orderby,
				'order'          => $order,
				'after'          => $after,
				'limit'          => $per_page + 1,
			)
		);

		$has_more = count( $rows ) > $per_page;
		$rows     = array_slice( $rows, 0, $per_page );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $row, $request ) );
		}
		$response = rest_ensure_response( $items );

		if ( $has_more && $rows ) {
			$last   = end( $rows );
			$cursor = Cursor::encode(
				array(
					'o' => $orderby,
					'd' => $order,
					'v' => 'id' === $column ? (int) $last['id'] : ( 'score' === $column ? (int) $last[ $column ] : $last[ $column ] ),
					'i' => (int) $last['id'],
				)
			);
			$response->header( 'X-Next-Cursor', $cursor );

			$query = $request->get_query_params();
			unset( $query['cursor'] );
			$query['cursor'] = $cursor;
			$next            = add_query_arg( urlencode_deep( $query ), rest_url( $this->namespace . '/' . $this->rest_base ) );
			$response->link_header( 'next', $next );
		}

		return $response;
	}

	/**
	 * GET /leads/<id>, with ETag / If-None-Match.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$scope   = $this->owner_scope();
		$columns = $this->columns_for( $request );
		if ( $scope ) {
			$columns[] = 'owner_id';
		}
		$row = Repository::find_columns( (int) $request['id'], $columns );
		if ( ! $row || ( $scope && (int) $row['owner_id'] !== $scope ) ) {
			return new WP_Error( 'rest_not_found', __( 'Lead not found.', 'acme-leads' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $row, $request );
		$fields   = $this->get_fields_for_response( $request );
		$data     = array_intersect_key( $response->get_data(), array_flip( $fields ) );
		$etag     = '"' . md5( (string) wp_json_encode( $data ) ) . '"';

		if ( $this->etag_matches( (string) $request->get_header( 'If-None-Match' ), $etag ) ) {
			$not_modified = new WP_REST_Response( null, 304 );
			$not_modified->header( 'ETag', $etag );
			return $not_modified;
		}

		$response->header( 'ETag', $etag );
		return $response;
	}

	/**
	 * Whether an If-None-Match header matches an ETag (weak comparison).
	 *
	 * @param string $header Header value.
	 * @param string $etag   Current ETag.
	 * @return bool
	 */
	private function etag_matches( $header, $etag ) {
		$header = trim( $header );
		if ( '' === $header ) {
			return false;
		}
		if ( '*' === $header ) {
			return true;
		}
		$strip = static function ( $tag ) {
			$tag = trim( $tag );
			return 0 === strpos( $tag, 'W/' ) ? substr( $tag, 2 ) : $tag;
		};
		foreach ( explode( ',', $header ) as $candidate ) {
			if ( $strip( $candidate ) === $strip( $etag ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * PATCH /leads/<id>: status, notes, owner.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id   = (int) $request['id'];
		$lead = Repository::find( $id );
		if ( ! $lead ) {
			return new WP_Error( 'rest_not_found', __( 'Lead not found.', 'acme-leads' ), array( 'status' => 404 ) );
		}

		$details = array();
		if ( isset( $request['notes'] ) ) {
			$details['notes'] = (string) $request['notes'];
		}
		if ( isset( $request['owner'] ) ) {
			$owner = (int) $request['owner'];
			if ( $owner && ! user_can( $owner, Installer::CAP_VIEW ) ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'Leads can only be assigned to the sales team.', 'acme-leads' ),
					array(
						'status' => 400,
						'params' => array( 'owner' => __( 'Not a sales team member.', 'acme-leads' ) ),
					)
				);
			}
			$details['owner_id'] = $owner;
		}
		Repository::update_details( $id, $details );

		if ( isset( $request['status'] ) ) {
			Repository::update_status( $id, $request['status'] );
		}

		$row = Repository::find_columns( $id, $this->columns_for( $request ) );
		return $this->prepare_item_for_response( $row, $request );
	}

	/**
	 * POST /leads/bulk.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_update( $request ) {
		$status = $request['status'];
		$ids    = array_values( array_unique( array_map( 'intval', (array) $request['ids'] ) ) );

		$current   = Repository::statuses_for( $ids );
		$updated   = array();
		$unchanged = array();
		$not_found = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $current[ $id ] ) ) {
				$not_found[] = $id;
			} elseif ( $current[ $id ] === $status ) {
				$unchanged[] = $id;
			} else {
				$updated[] = $id;
			}
		}

		Repository::bulk_set_status( $updated, $status );

		foreach ( $updated as $id ) {
			/** This action is documented in includes/class-repository.php */
			do_action( 'acme_leads_status_changed', $id, $status, $current[ $id ] );
		}

		return rest_ensure_response(
			array(
				'updated'   => $updated,
				'unchanged' => $unchanged,
				'not_found' => $not_found,
			)
		);
	}

	/**
	 * Prepare a (possibly partial) row.
	 *
	 * @param array           $item    Row.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = array();
		foreach ( self::FIELD_COLUMNS as $field => $column ) {
			if ( ! array_key_exists( $column, $item ) ) {
				continue;
			}
			$value = $item[ $column ];
			switch ( $field ) {
				case 'id':
				case 'score':
				case 'owner':
					$value = (int) $value;
					break;
				case 'created_at':
				case 'updated_at':
					$value = gmdate( 'Y-m-d\TH:i:sP', strtotime( $value . ' UTC' ) );
					break;
				default:
					$value = (string) $value;
			}
			$data[ $field ] = $value;
		}
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->filter_response_by_context( $data, $context );
		return rest_ensure_response( $data );
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'        => $this->get_context_param( array( 'default' => 'view' ) ),
			'per_page'       => array(
				'description' => __( 'Maximum number of leads per page.', 'acme-leads' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'cursor'         => array(
				'description' => __( 'Continue after the page that returned this cursor (X-Next-Cursor).', 'acme-leads' ),
				'type'        => 'string',
			),
			'orderby'        => array(
				'description' => __( 'Sort by.', 'acme-leads' ),
				'type'        => 'string',
				'enum'        => array( 'created_at', 'name', 'score', 'id' ),
				'default'     => 'created_at',
			),
			'order'          => array(
				'description' => __( 'Sort direction.', 'acme-leads' ),
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
			),
			'status'         => array(
				'description' => __( 'Only leads with these statuses.', 'acme-leads' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => Repository::STATUSES,
				),
			),
			'created_after'  => array(
				'description' => __( 'Only leads created at or after this date-time.', 'acme-leads' ),
				'type'        => 'string',
				'format'      => 'date-time',
			),
			'created_before' => array(
				'description' => __( 'Only leads created before this date-time.', 'acme-leads' ),
				'type'        => 'string',
				'format'      => 'date-time',
			),
			'search'         => array(
				'description' => __( 'Search in name and email.', 'acme-leads' ),
				'type'        => 'string',
			),
		);
	}

	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$readonly = static function ( array $prop ) {
			return array_merge(
				$prop,
				array(
					'context'  => array( 'view', 'edit' ),
					'readonly' => true,
				)
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'acme-lead',
			'type'       => 'object',
			'properties' => array(
				'id'         => $readonly(
					array(
						'description' => __( 'Lead ID.', 'acme-leads' ),
						'type'        => 'integer',
					)
				),
				'name'       => $readonly(
					array(
						'description' => __( 'Name.', 'acme-leads' ),
						'type'        => 'string',
					)
				),
				'email'      => $readonly(
					array(
						'description' => __( 'Email.', 'acme-leads' ),
						'type'        => 'string',
						'format'      => 'email',
					)
				),
				'company'    => $readonly(
					array(
						'description' => __( 'Company.', 'acme-leads' ),
						'type'        => 'string',
					)
				),
				'status'     => array(
					'description' => __( 'Pipeline status.', 'acme-leads' ),
					'type'        => 'string',
					'enum'        => Repository::STATUSES,
					'context'     => array( 'view', 'edit' ),
				),
				'source'     => $readonly(
					array(
						'description' => __( 'Where the lead came from.', 'acme-leads' ),
						'type'        => 'string',
						'enum'        => Repository::SOURCES,
					)
				),
				'score'      => $readonly(
					array(
						'description' => __( 'Score (0-100).', 'acme-leads' ),
						'type'        => 'integer',
					)
				),
				'owner'      => array(
					'description' => __( 'Assigned sales rep (user ID, 0 = unassigned).', 'acme-leads' ),
					'type'        => 'integer',
					'minimum'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'notes'      => array(
					'description' => __( 'Internal notes.', 'acme-leads' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'created_at' => $readonly(
					array(
						'description' => __( 'Creation date (UTC).', 'acme-leads' ),
						'type'        => 'string',
						'format'      => 'date-time',
					)
				),
				'updated_at' => $readonly(
					array(
						'description' => __( 'Last change (UTC).', 'acme-leads' ),
						'type'        => 'string',
						'format'      => 'date-time',
					)
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}

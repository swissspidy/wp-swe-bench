<?php
/**
 * "Upcoming events" variation of the Query Loop block.
 *
 * A Query Loop block with the namespace `acme/upcoming-events` lists upcoming events
 * (see Upcoming). The editor preview (REST) and the front end apply the same query:
 * - front end: the namespace is turned into a `query.acmeEvents = "upcoming"` flag
 *   (so it reaches the inner post template and pagination blocks through the block
 *   context), then the Query Loop's query vars are replaced;
 * - editor: the Query Loop passes unknown `query` keys to the REST API, so the same
 *   flag arrives as the `acmeEvents` parameter of /wp/v2/events.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Query Loop integration.
 */
class Query_Loop {

	const VARIATION = 'acme/upcoming-events';
	const FLAG      = 'acmeEvents';
	const FLAG_VAL  = 'upcoming';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'render_block_data', array( $this, 'flag_variation' ), 10, 1 );
		add_filter( 'query_loop_block_query_vars', array( $this, 'query_vars' ), 10, 2 );
		add_filter( 'rest_' . POST_TYPE . '_query', array( $this, 'rest_query' ), 10, 2 );
		add_filter( 'rest_' . POST_TYPE . '_collection_params', array( $this, 'rest_params' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_variation' ) );
	}

	/**
	 * Whether a query attribute/context array belongs to an upcoming events loop.
	 *
	 * @param mixed $query Query attribute.
	 * @return bool
	 */
	public static function is_upcoming( $query ) {
		return is_array( $query ) && isset( $query[ self::FLAG ] ) && self::FLAG_VAL === $query[ self::FLAG ];
	}

	/**
	 * Loops saved with the variation's namespace (including ones created by the
	 * importer, which only sets the namespace) get the flag and a fixed post type.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return array
	 */
	public function flag_variation( $parsed_block ) {
		if (
			'core/query' !== ( $parsed_block['blockName'] ?? '' )
			|| self::VARIATION !== ( $parsed_block['attrs']['namespace'] ?? '' )
		) {
			return $parsed_block;
		}
		$query = isset( $parsed_block['attrs']['query'] ) && is_array( $parsed_block['attrs']['query'] ) ? $parsed_block['attrs']['query'] : array();

		$query[ self::FLAG ]  = self::FLAG_VAL;
		$query['postType']    = POST_TYPE;
		$query['inherit']     = false;
		$query['perPage']     = isset( $query['perPage'] ) ? $query['perPage'] : 5;
		$parsed_block['attrs']['query'] = $query;

		return $parsed_block;
	}

	/**
	 * Front end: replace the Query Loop's query.
	 *
	 * @param array     $query Query vars.
	 * @param \WP_Block $block Post template / pagination block.
	 * @return array
	 */
	public function query_vars( $query, $block ) {
		if ( ! $block instanceof \WP_Block || ! self::is_upcoming( $block->context['query'] ?? null ) ) {
			return $query;
		}
		return Upcoming::apply( $query );
	}

	/**
	 * Editor preview: same query for REST requests carrying the flag.
	 *
	 * @param array            $args    WP_Query args.
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function rest_query( $args, $request ) {
		if ( self::FLAG_VAL !== $request->get_param( self::FLAG ) ) {
			return $args;
		}
		return Upcoming::apply( $args );
	}

	/**
	 * Document the flag on the collection.
	 *
	 * @param array $params Collection params.
	 * @return array
	 */
	public function rest_params( $params ) {
		$params[ self::FLAG ] = array(
			'description' => __( 'Set to "upcoming" to list upcoming, non-cancelled events ordered by start.', 'acme-events-lite' ),
			'type'        => 'string',
			'enum'        => array( self::FLAG_VAL ),
		);
		return $params;
	}

	/**
	 * Enqueue the variation (every block editor: posts, pages, Site Editor).
	 */
	public function enqueue_variation() {
		$asset_file = ACME_EVENTS_DIR . 'build/upcoming-events.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'acme-events-upcoming-variation',
			ACME_EVENTS_URL . 'build/upcoming-events.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'acme-events-upcoming-variation', 'acme-events-lite' );
	}
}

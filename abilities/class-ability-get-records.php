<?php
/**
 * Ability: stream/get-records — query the Stream activity log.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

require_once __DIR__ . '/trait-view-stream-permission.php';

/**
 * Class - Ability_Get_Records
 */
class Ability_Get_Records extends Ability {

	use Trait_View_Stream_Permission;

	/**
	 * Maximum records returned in a single call.
	 *
	 * @const int
	 */
	const MAX_PER_PAGE = 1000;

	/**
	 * Default records per page when caller does not specify.
	 *
	 * @const int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'stream/get-records';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return __( 'Get Stream Records', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return __( 'Query the Stream activity log with filters such as date range, user, connector, context, action, and IP. Returns a paginated array of log records.', 'stream' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations() {
		return array(
			'readonly'     => true,
			'idempotent'   => true,
			'instructions' => __( 'Use to investigate site activity. Always pass narrow filters (date range, user, connector) where possible: the activity log can be very large, and unfiltered queries are paginated to records_per_page (default 20). Combine with stream/get-record when you need full metadata for a specific event.', 'stream' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_input_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'search'           => array(
					'type'        => 'string',
					'description' => 'Free-text search term.',
				),
				'search_field'     => array(
					'type'        => 'string',
					'description' => 'Column to search against. Defaults to summary.',
					'enum'        => array( 'summary', 'ip' ),
				),
				'date_from'        => array(
					'type'        => 'string',
					'description' => 'Inclusive lower bound, YYYY-MM-DD.',
					'format'      => 'date',
				),
				'date_to'          => array(
					'type'        => 'string',
					'description' => 'Inclusive upper bound, YYYY-MM-DD.',
					'format'      => 'date',
				),
				'user_id'          => array(
					'type'        => 'integer',
					'description' => 'WordPress user ID. 0 matches system actions.',
				),
				'user_id__in'      => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'maxItems'    => 100,
					'description' => 'Match any of these user IDs (max 100).',
				),
				'user_role'        => array(
					'type'        => 'string',
					'description' => 'WordPress role slug.',
				),
				'connector'        => array(
					'type'        => 'string',
					'description' => 'Connector slug (e.g. posts, users).',
				),
				'connector__in'    => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'maxItems'    => 100,
					'description' => 'Match any of these connector slugs (max 100).',
				),
				'context'          => array(
					'type'        => 'string',
					'description' => 'Context slug.',
				),
				'action'           => array(
					'type'        => 'string',
					'description' => 'Action slug (created, updated, deleted, etc.).',
				),
				'ip'               => array(
					'type'        => 'string',
					'description' => 'IP address to filter by.',
				),
				'object_id'        => array(
					'type'        => 'integer',
					'description' => 'ID of the object the record refers to.',
				),
				'records_per_page' => array(
					'type'        => 'integer',
					'description' => 'Page size (1-' . self::MAX_PER_PAGE . ').',
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
					'default'     => self::DEFAULT_PER_PAGE,
				),
				'paged'            => array(
					'type'        => 'integer',
					'description' => 'Page number (1-indexed).',
					'minimum'     => 1,
					'default'     => 1,
				),
				'order'            => array(
					'type'        => 'string',
					'description' => 'Sort direction.',
					'enum'        => array( 'asc', 'desc', 'ASC', 'DESC' ),
					'default'     => 'desc',
				),
				'orderby'          => array(
					'type'        => 'string',
					'description' => 'Column to order by. Must be one of Stream\'s sortable columns; unknown values fall back to ID in Query::query().',
					'enum'        => array(
						'ID',
						'created',
						'user_id',
						'user_role',
						'summary',
						'connector',
						'context',
						'action',
						'site_id',
						'blog_id',
						'object_id',
					),
					'default'     => 'created',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_output_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'records' => array(
					'type'        => 'array',
					'description' => 'Matching log records.',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'properties'           => array(
							'ID'        => array( 'type' => 'integer' ),
							'site_id'   => array( 'type' => 'integer' ),
							'blog_id'   => array( 'type' => 'integer' ),
							'object_id' => array( 'type' => array( 'integer', 'null' ) ),
							'user_id'   => array( 'type' => 'integer' ),
							'user_role' => array( 'type' => 'string' ),
							'summary'   => array( 'type' => 'string' ),
							'created'   => array( 'type' => 'string' ),
							'connector' => array( 'type' => 'string' ),
							'context'   => array( 'type' => 'string' ),
							'action'    => array( 'type' => 'string' ),
							'ip'        => array( 'type' => array( 'string', 'null' ) ),
						),
					),
				),
				'total'   => array(
					'type'        => 'integer',
					'description' => 'Total matching records, ignoring pagination.',
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed $input Validated input matching get_input_schema(), or null.
	 */
	public function execute( $input = null ) {
		$input = (array) $input;

		$allowed_keys = array(
			'search',
			'search_field',
			'date_from',
			'date_to',
			'user_id',
			'user_id__in',
			'user_role',
			'connector',
			'connector__in',
			'context',
			'action',
			'ip',
			'object_id',
			'records_per_page',
			'paged',
			'order',
			'orderby',
		);

		$args = array_intersect_key( $input, array_flip( $allowed_keys ) );

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = self::DEFAULT_PER_PAGE;
		}

		$records = $this->plugin->db->get_records( $args );
		$total   = $this->plugin->db->get_found_records_count();

		// Records are stdClass objects; convert to arrays for schema-friendly output.
		$records = array_map(
			static function ( $record ) {
				return (array) $record;
			},
			$records
		);

		return array(
			'records' => $records,
			'total'   => (int) $total,
		);
	}
}

<?php
namespace WP_Stream;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Test_Query_Unit extends TestCase {
	/**
	 * Args captured from wp_stream_db_query.
	 *
	 * @var array|null
	 */
	protected static $captured_db_query_args;

	/**
	 * SQL captured from wp_stream_db_query.
	 *
	 * @var string|null
	 */
	protected static $captured_db_query_sql;

	/**
	 * Args captured from wp_stream_db_count_query.
	 *
	 * @var array|null
	 */
	protected static $captured_db_count_query_args;

	/**
	 * Query under test.
	 *
	 * @var Query
	 */
	protected $query;

	protected function set_up() {
		parent::set_up();
		self::$captured_db_query_args       = null;
		self::$captured_db_query_sql        = null;
		self::$captured_db_count_query_args = null;
		$this->query                        = Mockery::mock( Query::class )->makePartial();

		$wpdb             = Mockery::mock();
		$wpdb->stream     = 'wp_stream';
		$wpdb->streammeta = 'wp_streammeta';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( array( self::class, 'prepare_stub' ) );
		$this->query->wpdb = $wpdb;
	}

	/**
	 * Capture $args from wp_stream_db_query; pass the query through.
	 *
	 * @param string $query SQL.
	 * @param array  $args  Query args.
	 * @return string
	 */
	public static function capture_db_query_filter_args( $query, $args ) {
		self::$captured_db_query_args = $args;
		return $query;
	}

	/**
	 * Capture SQL from wp_stream_db_query; pass the query through.
	 *
	 * @param string $query SQL.
	 * @param array  $args  Query args.
	 * @return string
	 */
	public static function capture_db_query_sql( $query, $args ) {
		unset( $args );
		self::$captured_db_query_sql = $query;
		return $query;
	}

	/**
	 * Capture $args from wp_stream_db_count_query; pass the query through.
	 *
	 * @param string $query SQL.
	 * @param array  $args  Query args.
	 * @return string
	 */
	public static function capture_db_count_query_filter_args( $query, $args ) {
		self::$captured_db_count_query_args = $args;
		return $query;
	}

	/**
	 * Named prepare stand-in. Quotes strings; leaves ints/floats bare. Not esc_sql.
	 * Mirrors $wpdb->prepare unpacking a single array of replacements.
	 *
	 * @param string $query Query with %d / %s placeholders.
	 * @param mixed  ...$args Replacement values.
	 * @return string
	 */
	public static function prepare_stub( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
			$args = $args[0];
		}

		$escaped = array();
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$escaped[] = $arg;
			} else {
				$escaped[] = "'" . (string) $arg . "'";
			}
		}

		$query = str_replace( array( '%d', '%f', '%s' ), '%s', $query );

		return vsprintf( $query, $escaped );
	}

	/**
	 * Test the where_columns method with numeric zero and skips empty string.
	 */
	public function test_where_columns_emits_numeric_zero_and_skips_empty_string() {
		$where = $this->query->where_columns(
			$this->args(
				array(
					'user_id'   => 0,
					'user_role' => '',
					'connector' => 'posts',
				)
			)
		);

		$this->assertStringContainsString( 'wp_stream.user_id = 0', $where );
		$this->assertStringContainsString( "wp_stream.connector = 'posts'", $where );
		$this->assertStringNotContainsString( 'user_role', $where );
	}

	/**
	 * Test the where_columns method with search, user_role, connector, context, and action.
	 */
	public function test_where_columns_search_sits_between_user_role_and_connector() {
		$where = $this->query->where_columns(
			$this->args(
				array(
					'user_role' => 'administrator',
					'search'    => 'hello',
					'connector' => 'posts',
					'context'   => 'post',
					'action'    => 'updated',
				)
			)
		);

		$user_role_pos = strpos( $where, 'user_role' );
		$search_pos    = strpos( $where, 'LIKE' );
		$connector_pos = strpos( $where, 'connector' );
		$context_pos   = strpos( $where, 'context' );
		$action_pos    = strpos( $where, 'action' );

		$this->assertNotFalse( $user_role_pos );
		$this->assertNotFalse( $search_pos );
		$this->assertNotFalse( $connector_pos );
		$this->assertLessThan( $search_pos, $user_role_pos );
		$this->assertLessThan( $connector_pos, $search_pos );
		$this->assertLessThan( $context_pos, $connector_pos );
		$this->assertLessThan( $action_pos, $context_pos );
	}

	/**
	 * Test the where_columns method with disallowed search field.
	 */
	public function test_where_columns_rejects_disallowed_search_field() {
		$where = $this->query->where_columns(
			$this->args(
				array(
					'search'       => 'hello',
					'search_field' => 'summary; DROP TABLE wp_stream',
				)
			)
		);

		$this->assertSame( '', $where );
	}

	/**
	 * Test the where_columns method with search and ip.
	 */
	public function test_where_columns_allowlisted_search_and_ip() {
		Functions\when( 'wp_stream_filter_var' )->justReturn( '127.0.0.1' );

		$where = $this->query->where_columns(
			$this->args(
				array(
					'search'       => 'hello',
					'search_field' => 'summary',
					'ip'           => '127.0.0.1',
				)
			)
		);

		$this->assertStringContainsString( "wp_stream.summary LIKE '%hello%'", $where );
		$this->assertStringContainsString( "wp_stream.ip = '127.0.0.1'", $where );
	}

	/**
	 * Test the where_dates method with date.
	 */
	public function test_where_dates_expands_date_to_from_and_to() {
		Functions\when( 'get_gmt_from_date' )->returnArg( 1 );

		$where = $this->query->where_dates(
			$this->args(
				array(
					'date' => '2020-01-01',
				)
			)
		);

		$this->assertStringContainsString( 'DATE(wp_stream.created) >=', $where );
		$this->assertStringContainsString( 'DATE(wp_stream.created) <=', $where );
	}

	/**
	 * Test the query method with date_from, date_to, and date.
	 */
	public function test_query_date_alias_leaves_filter_args_unchanged() {
		Functions\when( 'get_gmt_from_date' )->returnArg( 1 );

		Filters\expectApplied( 'wp_stream_db_query' )
			->once()
			->andReturnUsing( array( self::class, 'capture_db_query_filter_args' ) );
		Filters\expectApplied( 'wp_stream_db_count_query' )
			->once()
			->andReturnUsing( array( self::class, 'capture_db_count_query_filter_args' ) );

		$this->query->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );
		$this->query->wpdb->shouldReceive( 'get_var' )->once()->andReturn( 0 );

		$this->query->query(
			$this->args(
				array(
					'date'      => '2020-01-01',
					'date_from' => '2019-01-01',
					'date_to'   => '2019-12-31',
				)
			)
		);

		$this->assertSame( '2020-01-01', self::$captured_db_query_args['date'] );
		$this->assertSame( '2019-01-01', self::$captured_db_query_args['date_from'] );
		$this->assertSame( '2019-12-31', self::$captured_db_query_args['date_to'] );
		$this->assertSame( '2020-01-01', self::$captured_db_count_query_args['date'] );
		$this->assertSame( '2019-01-01', self::$captured_db_count_query_args['date_from'] );
		$this->assertSame( '2019-12-31', self::$captured_db_count_query_args['date_to'] );
	}

	/**
	 * Test the where_dates method with date_after and date_before.
	 */
	public function test_where_dates_after_and_before() {
		Functions\when( 'get_gmt_from_date' )->returnArg( 1 );

		$where = $this->query->where_dates(
			$this->args(
				array(
					'date_after'  => '2020-06-15',
					'date_before' => '2020-06-16',
				)
			)
		);

		$this->assertStringContainsString( "DATE(wp_stream.created) > '2020-06-15 00:00:00'", $where );
		$this->assertStringContainsString( "DATE(wp_stream.created) < '2020-06-16 00:00:00'", $where );
	}

	/**
	 * Test the where_in method.
	 *
	 * @dataProvider data_where_in
	 *
	 * @param array  $overrides Args merged into defaults.
	 * @param string $expected  Expected WHERE fragment.
	 */
	public function test_where_in( $overrides, $expected ) {
		$this->assertSame( $expected, $this->query->where_in( $this->args( $overrides ) ) );
	}

	/**
	 * Data provider for test_where_in.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function data_where_in() {
		return array(
			// Empty list: no filter (callers default to array).
			'empty_array'              => array(
				array( 'connector__in' => array() ),
				'',
			),
			// Type guard: list table / abilities / DB defaults always pass arrays.
			'non_array'                => array(
				array( 'connector__not_in' => 'posts' ),
				'',
			),
			'single_element'           => array(
				array( 'user_id__in' => array( 1 ) ),
				' AND wp_stream.user_id IN (1)',
			),
			'two_element_in'           => array(
				array( 'connector__in' => array( 'posts', 'users' ) ),
				" AND wp_stream.connector IN ('posts','users')",
			),
			// record → ID after suffix strip; allowlisted column.
			'record_in'                => array(
				array( 'record__in' => array( 1, 2 ) ),
				' AND wp_stream.ID IN (1,2)',
			),
			// __not_in does not also match __in (suffix -4 of __not_in is "t_in").
			'not_in'                   => array(
				array( 'connector__not_in' => array( 'posts', 'users' ) ),
				" AND wp_stream.connector NOT IN ('posts','users')",
			),
			'disallowed_field_skipped' => array(
				array( 'meta_key__in' => array( 'x' ) ),
				'',
			),
		);
	}

	/**
	 * Test the select method.
	 *
	 * @dataProvider data_select
	 *
	 * @param mixed  $fields   Fields arg.
	 * @param string $expected Expected SELECT list.
	 */
	public function test_select( $fields, $expected ) {
		$this->assertSame(
			$expected,
			$this->query->select( $this->args( array( 'fields' => $fields ) ) )
		);
	}

	/**
	 * Data provider for test_select.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function data_select() {
		return array(
			'empty_fields'       => array( array(), 'wp_stream.*' ),
			'scalar_created'     => array( 'created', 'wp_stream.created' ),
			'allowlist_and_skip' => array(
				array( 'ID', 'meta', 'ID); DROP TABLE wp_stream' ),
				'wp_stream.ID',
			),
			'all_rejected'       => array( array( 'meta' ), 'wp_stream.*' ),
			'multiple_valid'     => array(
				array( 'ID', 'summary' ),
				'wp_stream.ID, wp_stream.summary',
			),
		);
	}

	/**
	 * Test the orderby method.
	 *
	 * @dataProvider data_orderby
	 *
	 * @param array  $overrides Args merged into defaults.
	 * @param string $expected  Expected ORDER BY clause.
	 */
	public function test_orderby( $overrides, $expected ) {
		$this->assertSame( $expected, $this->query->orderby( $this->args( $overrides ) ) );
	}

	/**
	 * Data provider for test_orderby.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function data_orderby() {
		return array(
			'orderby_date_falls_back_to_id' => array(
				array( 'orderby' => 'date' ),
				'ORDER BY wp_stream.ID DESC',
			),
			'orderby_ip_falls_back_to_id'   => array(
				array( 'orderby' => 'ip' ),
				'ORDER BY wp_stream.ID DESC',
			),
			'allowlisted_created_asc'       => array(
				array(
					'orderby' => 'created',
					'order'   => 'asc',
				),
				'ORDER BY wp_stream.created ASC',
			),
			'junk_direction_defaults_desc'  => array(
				array(
					'orderby' => 'created',
					'order'   => 'sideways',
				),
				'ORDER BY wp_stream.created DESC',
			),
			'meta_value_without_key'        => array(
				array(
					'orderby'  => 'meta_value',
					'meta_key' => '',
				),
				'ORDER BY wp_stream.ID DESC',
			),
			'meta_value_num_without_key'    => array(
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => '',
				),
				'ORDER BY wp_stream.ID DESC',
			),
			'meta_value_num_with_key'       => array(
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => 'foo',
				),
				'ORDER BY CAST(wp_streammeta.meta_value AS SIGNED) DESC',
			),
			'meta_value_with_key'           => array(
				array(
					'orderby'  => 'meta_value',
					'meta_key' => 'foo',
				),
				'ORDER BY wp_streammeta.meta_value DESC',
			),
		);
	}

	/**
	 * Test the join method.
	 *
	 * @dataProvider data_join
	 *
	 * @param array  $overrides Args merged into defaults.
	 * @param string $expected  Expected JOIN clause.
	 */
	public function test_join( $overrides, $expected ) {
		$this->assertSame( $expected, $this->query->join( $this->args( $overrides ) ) );
	}

	/**
	 * Data provider for test_join.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function data_join() {
		$meta_join = "LEFT JOIN wp_streammeta ON wp_streammeta.record_id = wp_stream.ID AND wp_streammeta.meta_key = 'foo'";

		return array(
			'no_meta_orderby'         => array(
				array( 'orderby' => 'created' ),
				'',
			),
			'meta_value_without_key'  => array(
				array(
					'orderby'  => 'meta_value',
					'meta_key' => '',
				),
				'',
			),
			'meta_value_with_key'     => array(
				array(
					'orderby'  => 'meta_value',
					'meta_key' => 'foo',
				),
				$meta_join,
			),
			'meta_value_num_with_key' => array(
				array(
					'orderby'  => 'meta_value_num',
					'meta_key' => 'foo',
				),
				$meta_join,
			),
		);
	}

	/**
	 * Test the query_meta_orderby_includes_join method.
	 *
	 * Assembled query includes JOIN and meta ORDER BY together.
	 */
	public function test_query_meta_orderby_includes_join() {
		Filters\expectApplied( 'wp_stream_db_query' )
			->once()
			->andReturnUsing( array( self::class, 'capture_db_query_sql' ) );
		Filters\expectApplied( 'wp_stream_db_count_query' )
			->once()
			->andReturnUsing( array( self::class, 'pass_through_count_query' ) );

		$this->query->wpdb->shouldReceive( 'get_results' )->once()->andReturn( array() );
		$this->query->wpdb->shouldReceive( 'get_var' )->once()->andReturn( 0 );

		$this->query->query(
			$this->args(
				array(
					'orderby'  => 'meta_value',
					'meta_key' => 'foo',
				)
			)
		);

		$this->assertStringContainsString(
			"LEFT JOIN wp_streammeta ON wp_streammeta.record_id = wp_stream.ID AND wp_streammeta.meta_key = 'foo'",
			self::$captured_db_query_sql
		);
		$this->assertStringContainsString( 'ORDER BY wp_streammeta.meta_value DESC', self::$captured_db_query_sql );
	}

	/**
	 * Pass count query through unchanged.
	 *
	 * @param string $query SQL.
	 * @param array  $args  Query args.
	 * @return string
	 */
	public static function pass_through_count_query( $query, $args ) {
		unset( $args );
		return $query;
	}

	/**
	 * Test the limits method.
	 *
	 * @dataProvider data_limits
	 *
	 * @param int    $paged            Page number.
	 * @param int    $records_per_page Page size.
	 * @param string $expected         Expected LIMIT clause.
	 */
	public function test_limits( $paged, $records_per_page, $expected ) {
		$this->assertSame(
			$expected,
			$this->query->limits(
				$this->args(
					array(
						'paged'            => $paged,
						'records_per_page' => $records_per_page,
					)
				)
			)
		);
	}

	/**
	 * Data provider for test_limits.
	 *
	 * @return array<string, array{0: int, 1: int, 2: string}>
	 */
	public static function data_limits() {
		return array(
			'page_one'       => array( 1, 20, 'LIMIT 0, 20' ),
			'page_two'       => array( 2, 10, 'LIMIT 10, 10' ),
			'zero_page_size' => array( 1, 0, 'LIMIT 0, 0' ),
			// paged below 1 clamps to page 1 (offset 0).
			'paged_zero'     => array( 0, 20, 'LIMIT 0, 20' ),
		);
	}

	/**
	 * Minimal args bag so fragment methods do not hit undefined keys.
	 *
	 * @param array $overrides Values to merge.
	 * @return array
	 */
	private function args( array $overrides ) {
		return array_merge(
			array(
				'site_id'          => null,
				'blog_id'          => null,
				'object_id'        => null,
				'user_id'          => null,
				'user_role'        => null,
				'search'           => null,
				'search_field'     => 'summary',
				'connector'        => null,
				'context'          => null,
				'action'           => null,
				'ip'               => null,
				'date'             => null,
				'date_from'        => null,
				'date_to'          => null,
				'date_after'       => null,
				'date_before'      => null,
				'paged'            => 1,
				'records_per_page' => 20,
				'order'            => 'desc',
				'orderby'          => 'date',
				'fields'           => array(),
				'meta_key'         => null,
			),
			$overrides
		);
	}
}

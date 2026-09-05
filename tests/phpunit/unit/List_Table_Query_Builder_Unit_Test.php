<?php
namespace WP_Stream;

use Brain\Monkey\Filters;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class List_Table_Query_Builder_Unit_Test extends TestCase {
	/**
	 * Builder under test.
	 *
	 * @var List_Table_Query_Builder
	 */
	protected $builder;

	protected function set_up() {
		parent::set_up();
		$this->builder = new List_Table_Query_Builder();
	}

	/**
	 * Test order, orderby, search, and date scalar passthrough.
	 */
	public function test_build_args_passes_order_search_and_dates() {
		$args = $this->builder->build_args(
			array(
				'order'            => 'asc',
				'orderby'          => 'created',
				'search'           => 'hello',
				'date'             => '2020-01-01',
				'date_from'        => '2019-01-01',
				'date_to'          => '2019-12-31',
				'date_after'       => '2020-06-15',
				'date_before'      => '2020-06-16',
				'paged'            => 2,
				'records_per_page' => 10,
			)
		);

		$this->assertSame( 'asc', $args['order'] );
		$this->assertSame( 'created', $args['orderby'] );
		$this->assertSame( 'hello', $args['search'] );
		$this->assertSame( '2020-01-01', $args['date'] );
		$this->assertSame( '2019-01-01', $args['date_from'] );
		$this->assertSame( '2019-12-31', $args['date_to'] );
		$this->assertSame( '2020-06-15', $args['date_after'] );
		$this->assertSame( '2020-06-16', $args['date_before'] );
		$this->assertSame( 2, $args['paged'] );
		$this->assertSame( 10, $args['records_per_page'] );
	}

	/**
	 * Test scalar property filters, including user_id = 0.
	 *
	 * @param array $input    Input bag.
	 * @param array $expected Expected subset of args.
	 */
	#[DataProvider( 'data_property_filters' )]
	public function test_build_args_property_filters( $input, $expected ) {
		$args = $this->builder->build_args( $input );

		foreach ( $expected as $key => $value ) {
			$this->assertSame( $value, $args[ $key ], "Failed asserting args[{$key}]" );
		}
	}

	/**
	 * Data provider for property filter cases.
	 *
	 * @return array<string, array{0: array, 1: array}>
	 */
	public static function data_property_filters() {
		return array(
			'user_id_zero'   => array(
				array(
					'user_id'          => 0,
					'records_per_page' => 20,
				),
				array( 'user_id' => 0 ),
			),
			'scalar_filters' => array(
				array(
					'connector'        => 'posts',
					'context'          => 'post',
					'action'           => 'updated',
					'ip'               => '127.0.0.1',
					'records_per_page' => 20,
				),
				array(
					'connector' => 'posts',
					'context'   => 'post',
					'action'    => 'updated',
					'ip'        => '127.0.0.1',
				),
			),
		);
	}

	/**
	 * Empty-string property filters are omitted from args.
	 */
	public function test_build_args_skips_empty_string_property() {
		$args = $this->builder->build_args(
			array(
				'user_role'        => '',
				'records_per_page' => 20,
			)
		);

		$this->assertArrayNotHasKey( 'user_role', $args );
	}

	/**
	 * Test __in / __not_in comma-list explosion and array passthrough.
	 */
	public function test_build_args_explodes_in_and_not_in_lists() {
		$args = $this->builder->build_args(
			array(
				'connector__in'    => 'posts,users',
				'user_id__not_in'  => '1,2',
				'action__in'       => array( 'updated', 'created' ),
				'records_per_page' => 20,
			)
		);

		$this->assertSame( array( 'posts', 'users' ), $args['connector__in'] );
		$this->assertSame( array( '1', '2' ), $args['user_id__not_in'] );
		$this->assertSame( array( 'updated', 'created' ), $args['action__in'] );
	}

	/**
	 * Test group-{connector} context remaps to connector with empty context.
	 */
	public function test_build_args_remaps_group_context_to_connector() {
		$args = $this->builder->build_args(
			array(
				'context'          => 'group-posts',
				'records_per_page' => 20,
			)
		);

		$this->assertSame( 'posts', $args['connector'] );
		$this->assertSame( '', $args['context'] );
	}

	/**
	 * Test stream_records_per_page filter is applied.
	 */
	public function test_build_args_applies_stream_records_per_page_filter() {
		Filters\expectApplied( 'stream_records_per_page' )
			->once()
			->with( 20 )
			->andReturn( 50 );

		$args = $this->builder->build_args(
			array(
				'records_per_page' => 20,
			)
		);

		$this->assertSame( 50, $args['records_per_page'] );
	}

	/**
	 * Test default records_per_page when omitted from input.
	 */
	public function test_build_args_defaults_records_per_page() {
		Filters\expectApplied( 'stream_records_per_page' )
			->once()
			->andReturn( 20 );

		$args = $this->builder->build_args( array() );

		$this->assertSame( 20, $args['records_per_page'] );
	}

	/**
	 * Test paged=0 is preserved via isset, and omitted paged stays absent.
	 *
	 * @param array $input           Input bag.
	 * @param bool  $expect_paged_key Whether args should contain paged.
	 * @param mixed $expected_paged  Expected paged value when present.
	 */
	#[DataProvider( 'data_paged_preservation' )]
	public function test_build_args_paged_preservation( $input, $expect_paged_key, $expected_paged ) {
		$args = $this->builder->build_args( $input );

		if ( $expect_paged_key ) {
			$this->assertArrayHasKey( 'paged', $args );
			$this->assertSame( $expected_paged, $args['paged'] );
			$this->assertSame( 20, $args['records_per_page'] );
		} else {
			$this->assertArrayNotHasKey( 'paged', $args );
		}
	}

	/**
	 * Data provider for paged isset vs empty() contract.
	 *
	 * @return array<string, array{0: array, 1: bool, 2: mixed}>
	 */
	public static function data_paged_preservation() {
		return array(
			'paged_zero' => array(
				array(
					'paged'            => 0,
					'records_per_page' => 20,
				),
				true,
				0,
			),
			'omit_paged' => array(
				array(
					'records_per_page' => 20,
				),
				false,
				null,
			),
		);
	}
}

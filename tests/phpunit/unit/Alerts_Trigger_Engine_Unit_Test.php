<?php
namespace WP_Stream;

use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class Alerts_Trigger_Engine_Unit_Test extends TestCase {
	/**
	 * Engine under test.
	 *
	 * @var Alerts_Trigger_Engine
	 */
	protected $engine;

	/**
	 * Plugin mock with an Alerts façade for hydration.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	protected function set_up() {
		parent::set_up();
		self::stub_wp_query();

		$this->plugin         = Mockery::mock( Plugin::class );
		$this->plugin->alerts = Mockery::mock( Alerts::class );
		$this->engine         = new Alerts_Trigger_Engine( $this->plugin );
	}

	protected function tear_down() {
		Alerts_Trigger_Engine_Wp_Query_Stub::$posts_to_return = array();
		Alerts_Trigger_Engine_Wp_Query_Stub::$last_args       = null;
		parent::tear_down();
	}

	/**
	 * Alias a test-local WP_Query so check_records() can run on the host.
	 *
	 * @return void
	 */
	private static function stub_wp_query() {
		if ( ! class_exists( \WP_Query::class, false ) ) {
			class_alias( Alerts_Trigger_Engine_Wp_Query_Stub::class, 'WP_Query' );
		}
	}

	public function test_register_hooks_attaches_check_records_to_record_inserted() {
		$this->assertSame(
			10,
			has_filter( 'wp_stream_record_inserted', array( $this->engine, 'check_records' ) )
		);
	}

	/**
	 * Returns only alerts whose check_record() is true.
	 *
	 * @param array<int, bool> $outcomes       Per-alert check_record results.
	 * @param int              $expected_count Expected match count.
	 */
	#[DataProvider( 'data_matching_outcomes' )]
	public function test_matching_alerts_filters_by_check_record( $outcomes, $expected_count ) {
		$record_id = 5;
		$recordarr = array(
			'action' => 'updated',
		);
		$alerts    = array();
		foreach ( $outcomes as $matches ) {
			$alert = Mockery::mock( Alert::class )->makePartial();
			$alert->shouldReceive( 'check_record' )
				->once()
				->with( $record_id, $recordarr )
				->andReturn( $matches );
			$alerts[] = $alert;
		}

		$result = $this->engine->matching_alerts( $alerts, $record_id, $recordarr );

		$this->assertCount( $expected_count, $result );
		foreach ( $result as $alert ) {
			$this->assertContains( $alert, $alerts );
		}
	}

	/**
	 * Match / no-match / mixed lists. Scalars only; mocks stay in the test.
	 *
	 * @return array<string, array{0: array<int, bool>, 1: int}>
	 */
	public static function data_matching_outcomes() {
		return array(
			'all_match'  => array( array( true, true ), 2 ),
			'none_match' => array( array( false, false ), 0 ),
			'mixed'      => array( array( true, false, true ), 2 ),
		);
	}

	public function test_matching_alerts_empty_input_returns_empty() {
		$this->assertSame(
			array(),
			$this->engine->matching_alerts( array(), 1, array() )
		);
	}

	public function test_matching_alerts_skips_false_entries() {
		$recordarr = array(
			'action' => 'updated',
		);
		$matching  = Mockery::mock( Alert::class )->makePartial();
		$matching->shouldReceive( 'check_record' )
			->once()
			->with( 7, $recordarr )
			->andReturn( true );

		$result = $this->engine->matching_alerts( array( false, $matching ), 7, $recordarr );

		$this->assertSame( array( $matching ), $result );
	}

	public function test_check_records_returns_recordarr_when_no_enabled_alerts() {
		$recordarr = array(
			'action' => 'activated',
		);

		$this->plugin->alerts->shouldReceive( 'get_alert' )->never();

		$result = $this->engine->check_records( 11, $recordarr );

		$this->assertSame( $recordarr, $result );
		$this->assertSame(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => 'wp_stream_enabled',
			),
			Alerts_Trigger_Engine_Wp_Query_Stub::$last_args
		);
	}

	/**
	 * Hydrates query posts and sends only matching alerts.
	 *
	 * @param array<int, bool> $outcomes       Per-alert check_record results.
	 * @param int              $expected_count Unused; shared provider with matching_alerts.
	 */
	#[DataProvider( 'data_matching_outcomes' )]
	public function test_check_records_sends_only_matching_alerts( $outcomes, $expected_count ) {
		unset( $expected_count );
		$record_id = 9;
		$recordarr = array(
			'connector' => 'installer',
			'action'    => 'activated',
		);
		$hydrated  = array();

		foreach ( $outcomes as $index => $matches ) {
			$post_id = $index + 1;
			Alerts_Trigger_Engine_Wp_Query_Stub::$posts_to_return[] = (object) array(
				'ID' => $post_id,
			);

			$alert = Mockery::mock( Alert::class )->makePartial();
			$alert->shouldReceive( 'check_record' )
				->once()
				->with( $record_id, $recordarr )
				->andReturn( $matches );

			if ( $matches ) {
				$alert->shouldReceive( 'send_alert' )
					->once()
					->with( $record_id, $recordarr );
			} else {
				$alert->shouldReceive( 'send_alert' )->never();
			}

			$hydrated[ $post_id ] = $alert;
		}

		$this->plugin->alerts->shouldReceive( 'get_alert' )
			->times( count( $outcomes ) )
			->andReturnUsing(
				static function ( $post_id ) use ( $hydrated ) {
					return $hydrated[ $post_id ];
				}
			);

		$result = $this->engine->check_records( $record_id, $recordarr );

		$this->assertSame( $recordarr, $result );
	}
}

/**
 * Test-local WP_Query for Alerts_Trigger_Engine::check_records().
 */
class Alerts_Trigger_Engine_Wp_Query_Stub {

	/**
	 * Posts returned by the next query.
	 *
	 * @var array<int, object>
	 */
	public static $posts_to_return = array();

	/**
	 * Arguments passed to the last constructor call.
	 *
	 * @var array|null
	 */
	public static $last_args;

	/**
	 * Query posts.
	 *
	 * @var array<int, object>
	 */
	public $posts = array();

	/**
	 * @param array $args WP_Query arguments.
	 */
	public function __construct( $args = array() ) {
		self::$last_args = $args;
		$this->posts     = self::$posts_to_return;
	}
}

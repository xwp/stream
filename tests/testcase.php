<?php
namespace WP_Stream;

class WP_StreamTestCase extends \WP_Ajax_UnitTestCase {
	/**
	 * Holds the plugin base class
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Custom action prefix for test custom triggered actions
	 *
	 * @var string
	 */
	protected $action_prefix = 'wp_stream_test_';

	/**
	 * Holds the mocked class.
	 *
	 * @var MockBuilder
	 */
	protected $mock;

	/**
	 * PHP unit setup function
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->plugin = $GLOBALS['wp_stream'];
		$this->assertNotEmpty( $this->plugin );
		self::ensure_mercator_mapping_table();
	}

	/**
	 * Ensure Mercator's domain_mapping table exists when its version option is stale.
	 *
	 * WP's test runner rewrites CREATE TABLE to CREATE TEMPORARY TABLE and rolls
	 * back most writes. Mercator's check_table() (fired on admin_init via
	 * _handleAjax) can still leave mercator.db.version set in sitemeta without a
	 * durable mapping table. Mapping::create() then short-circuits on the version
	 * option and returns WP_Error, breaking later Connector_Mercator_Test cases.
	 *
	 * @return void
	 */
	protected static function ensure_mercator_mapping_table(): void {
		if ( ! is_multisite() || ! function_exists( 'Mercator\\check_table' ) ) {
			return;
		}

		global $wpdb;

		$table  = $wpdb->base_prefix . 'domain_mapping';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists ) {
			return;
		}

		delete_site_option( 'mercator.db.version' );
		\Mercator\check_table();
	}

	/**
	 * Work around WordPress core's expectDeprecated() fataling on PHPUnit 10+.
	 *
	 * Core's implementation calls \PHPUnit\Util\Test::parseTestMethodAnnotations(),
	 * which was removed in PHPUnit 10. We still register the doing-it-wrong /
	 * deprecated catchers so setExpectedIncorrectUsage() / setExpectedDeprecated()
	 * keep working. Annotation parsing is omitted (zero @expectedDeprecated uses;
	 * the one @expectedIncorrectUsage was converted).
	 *
	 * @link https://core.trac.wordpress.org/ticket/59486#comment:8
	 * @link https://core.trac.wordpress.org/ticket/62004
	 * @return void
	 */
	public function expectDeprecated(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		add_action( 'deprecated_function_run', array( $this, 'deprecated_function_run' ), 10, 3 );
		add_action( 'deprecated_argument_run', array( $this, 'deprecated_function_run' ), 10, 3 );
		add_action( 'deprecated_class_run', array( $this, 'deprecated_function_run' ), 10, 3 );
		add_action( 'deprecated_file_included', array( $this, 'deprecated_function_run' ), 10, 4 );
		add_action( 'deprecated_hook_run', array( $this, 'deprecated_function_run' ), 10, 4 );
		add_action( 'doing_it_wrong_run', array( $this, 'doing_it_wrong_run' ), 10, 3 );

		add_action( 'deprecated_function_trigger_error', '__return_false' );
		add_action( 'deprecated_argument_trigger_error', '__return_false' );
		add_action( 'deprecated_class_trigger_error', '__return_false' );
		add_action( 'deprecated_file_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Helper function to check validity of action
	 *
	 * @param array  $tests
	 * @param string $function_call
	 */
	protected function do_action_validation( array $tests = array(), $function_call = 'has_action' ) {
		foreach ( $tests as $test ) {
			list( $action, $class, $function ) = $test;

			// Default WP priority
			$priority = isset( $test[3] ) ? $test[3] : 10;

			// Default function call
			$function_call = ( in_array( $function_call, array( 'has_action', 'has_filter' ), true ) ) ? $function_call : 'has_action';

			// Run assertion here
			$this->assertEquals(
				$priority,
				$function_call( $action, array( $class, $function ) ),
				"$action $function_call is not attached to $class::$function. It might also have the wrong priority (validated priority: $priority)"
			);
			$this->assertTrue(
				method_exists( $class, $function ),
				"Class '$class' doesn't implement the '$function' function"
			);
		}
	}

	/**
	 * Helper function to check validity of filters
	 *
	 * @param array $tests
	 */
	protected function do_filter_validation( array $tests = array() ) {
		$this->do_action_validation( $tests, 'has_filter' );
	}
}

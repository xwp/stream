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
		self::silence_cli_header_output();
		self::silence_yoast_indexable_option_watchers();
	}

	/**
	 * Keep header() and setcookie() from warning after WP's test bootstrap has printed.
	 *
	 * WordPress PHPUnit bootstrap.php:261 runs install.php via system(),
	 * which writes "Installing..." to stdout and marks headers sent. PHPUnit 11
	 * still reports those E_WARNINGs even though WP_Ajax_UnitTestCase masks
	 * error_reporting. Stream tests assert on logs/JSON/user meta, not cookies
	 * or Referrer-Policy. Skip cookie emission via the plugins' own filters and
	 * drop wp_admin_headers() (no headers_sent() guard) before admin_init runs.
	 *
	 * Do not add send_auth_cookies => __return_false here: Connector_Two_Factor
	 * treats that filter as "2FA interstitial, do not log login".
	 *
	 * @return void
	 */
	protected static function silence_cli_header_output(): void {
		add_action( 'admin_init', array( self::class, 'remove_admin_http_headers' ), 0 );
		add_filter( 'user_switching_send_auth_cookies', '__return_false' );
	}

	/**
	 * Remove admin_init callbacks that call header() without checking headers_sent().
	 *
	 * @return void
	 */
	public static function remove_admin_http_headers(): void {
		remove_action( 'admin_init', 'wp_admin_headers' );
	}

	/**
	 * Unhook Yoast indexable watchers from option updates on multisite.
	 *
	 * Yoast is forced active via WP_TEST_ACTIVATED_PLUGINS (network map on
	 * multisite), but activate_plugin() — and therefore per-site schema — runs
	 * only in the single-site bootstrap. Factory blogs never get
	 * {prefix}{blog_id}_yoast_indexable.
	 *
	 * Several Indexable_* watchers then hit that missing table on option
	 * updates, and Yoast's should_index_indexables filter is too late:
	 * Indexable_Home_Page_Watcher::build_indexable() queries first;
	 * Indexable_HomeUrl_Watcher::reset_permalinks() → reset_permalink()
	 * issues UPDATE with no filter at all. Typical Stream triggers:
	 * wp_update_site( public ) → update_option( blog_public ); Mercator
	 * Mapping::make_primary() → update_option( home ).
	 *
	 * Stream does not test Yoast indexables on multisite
	 * (Connector_WordPress_SEO_Test skips). Drop every Indexable_* watcher
	 * on update_option_* so later option writes cannot reopen the noise.
	 *
	 * @return void
	 */
	protected static function silence_yoast_indexable_option_watchers(): void {
		if ( ! is_multisite() || empty( $GLOBALS['wp_filter'] ) ) {
			return;
		}

		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook ) {
			if ( ! is_string( $hook_name ) || 0 !== strpos( $hook_name, 'update_option_' ) ) {
				continue;
			}

			if ( ! $hook instanceof \WP_Hook ) {
				continue;
			}

			self::remove_yoast_indexable_watchers_from_hook( $hook_name, $hook );
		}
	}

	/**
	 * Remove Yoast Indexable_* watcher callbacks from one option-update hook.
	 *
	 * @param string   $hook_name Hook name (update_option_*).
	 * @param \WP_Hook $hook      Hook object.
	 * @return void
	 */
	protected static function remove_yoast_indexable_watchers_from_hook( $hook_name, $hook ): void {
		$prefix    = 'Yoast\\WP\\SEO\\Integrations\\Watchers\\Indexable_';
		$to_remove = array();

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = isset( $callback['function'] ) ? $callback['function'] : null;
				if ( ! is_array( $function ) || ! isset( $function[0] ) || ! is_object( $function[0] ) ) {
					continue;
				}

				if ( 0 !== strpos( get_class( $function[0] ), $prefix ) ) {
					continue;
				}

				$to_remove[] = array( $function, (int) $priority );
			}
		}

		foreach ( $to_remove as $item ) {
			remove_action( $hook_name, $item[0], $item[1] );
		}
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
	 * Resolve an Admin collaborator.
	 *
	 * Collaborators are public on Admin; this helper remains for tests that
	 * prefer a named lookup.
	 *
	 * @param Admin  $admin Admin instance.
	 * @param string $name  Property name (menu|assets|records|settings|ajax|purge).
	 * @return object
	 */
	protected function get_admin_collaborator( Admin $admin, string $name ) {
		return $admin->{$name};
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

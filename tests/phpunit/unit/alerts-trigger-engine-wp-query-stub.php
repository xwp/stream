<?php
/**
 * Test-local WP_Query for Alerts_Trigger_Engine::check_records().
 *
 * Lives in its own file so the WPCS Generic.Files.OneObjectStructurePerFile
 * sniff is satisfied and so PHPUnit does not auto-discover it as a test.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alerts_Trigger_Engine_Wp_Query_Stub
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
	 * Capture query arguments and expose stub posts.
	 *
	 * @param array $args WP_Query arguments.
	 */
	public function __construct( $args = array() ) {
		self::$last_args = $args;
		$this->posts     = self::$posts_to_return;
	}
}

<?php
/**
 * Unit tests for BuddyPress summary templates.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use Brain\Monkey\Functions;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests that BuddyPress summaries substitute values once.
 */
class Connector_BuddyPress_Unit_Test extends TestCase {

	/**
	 * Arguments from the most recent log() call.
	 *
	 * @var array
	 */
	private static $captured_log = array();

	protected function set_up() {
		parent::set_up();
		$this->stubTranslationFunctions();
		self::$captured_log = array();

		require_once dirname( __DIR__, 3 ) . '/connectors/class-connector-buddypress.php';
	}

	/**
	 * Store a log() call for assertions.
	 *
	 * @param string   $message   Summary template.
	 * @param array    $args      Template arguments.
	 * @param int      $object_id Object ID.
	 * @param string   $context   Context.
	 * @param string   $action    Action.
	 * @param int|null $user_id   User ID.
	 * @return bool
	 */
	public static function capture_log( $message, $args, $object_id, $context, $action, $user_id = null ) {
		self::$captured_log = compact( 'message', 'args', 'object_id', 'context', 'action', 'user_id' );

		return true;
	}

	/**
	 * A group whose name contains percent signs.
	 *
	 * @return object
	 */
	private function group_with_percent_name() {
		return (object) array(
			'id'   => 7,
			'name' => '100% done %',
			'slug' => 'favourites',
		);
	}

	/**
	 * Connector whose log() calls are captured.
	 *
	 * @return Connector_BuddyPress
	 */
	private function connector() {
		$connector = $this->getMockBuilder( Connector_BuddyPress::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		$connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log' ) );

		return $connector;
	}

	/**
	 * Summary after the single substitution Log::log() performs.
	 *
	 * @return string
	 */
	private function rendered_summary() {
		return vsprintf( self::$captured_log['message'], self::$captured_log['args'] );
	}

	/**
	 * Percent signs in a group name stay literal in the created summary.
	 */
	public function test_created_group_summary_keeps_percent_signs_literal() {
		$group = $this->group_with_percent_name();

		$this->connector()->group_action( $group, 'created' );

		$this->assertSame( '"%s" group created', self::$captured_log['message'] );
		$this->assertSame( $group->name, self::$captured_log['args']['name'] );
		$this->assertSame( '"100% done %" group created', $this->rendered_summary() );
	}

	/**
	 * Member and group names keep their own percent signs in a ban summary.
	 */
	public function test_banned_group_summary_keeps_percent_signs_literal() {
		Functions\when( 'get_user_by' )->justReturn( (object) array( 'display_name' => 'Jane %s' ) );

		$group = $this->group_with_percent_name();

		$this->connector()->group_action( $group, 'banned', array( 'user_id' => 5 ) );

		$this->assertSame( 'Banned "%2$s" from "%1$s"', self::$captured_log['message'] );
		$this->assertSame( 'Banned "Jane %s" from "100% done %"', $this->rendered_summary() );
	}

	/**
	 * Promotion fills the template once from the member, role, and group name.
	 */
	public function test_promoted_group_summary_keeps_percent_signs_literal() {
		$group = $this->group_with_percent_name();

		$this->connector()->group_action(
			$group,
			'promoted',
			array(
				'user_id'           => 5,
				'status'            => 'mod',
				'user_display_name' => 'Jane %s',
				'role_label'        => 'Moderator %',
			)
		);

		$this->assertSame( 'Promoted "%1$s" to "%2$s" in "%3$s"', self::$captured_log['message'] );
		$this->assertSame( 'Promoted "Jane %s" to "Moderator %" in "100% done %"', $this->rendered_summary() );
	}

	/**
	 * An unknown group action is ignored.
	 */
	public function test_unknown_group_action_does_not_log() {
		$connector = $this->getMockBuilder( Connector_BuddyPress::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		$connector->expects( $this->never() )
			->method( 'log' );

		$connector->group_action( $this->group_with_percent_name(), 'unknown' );
	}

	/**
	 * Percent signs in a profile field name stay literal.
	 */
	public function test_profile_field_summary_keeps_percent_signs_literal() {
		$field = (object) array(
			'id'       => 3,
			'name'     => 'Job %s',
			'group_id' => 1,
		);

		$this->connector()->field_action( $field, 'created' );

		$this->assertSame( 'Created profile field "%s"', self::$captured_log['message'] );
		$this->assertSame( 'Created profile field "Job %s"', $this->rendered_summary() );
	}

	/**
	 * Percent signs in a profile field group name stay literal.
	 */
	public function test_profile_field_group_summary_keeps_percent_signs_literal() {
		$group = (object) array(
			'id'   => 4,
			'name' => 'Appearance %',
		);

		$this->connector()->field_group_action( $group, 'created' );

		$this->assertSame( 'Created profile field group "%s"', self::$captured_log['message'] );
		$this->assertSame( 'Created profile field group "Appearance %"', $this->rendered_summary() );
	}

	/**
	 * A field with no ID before save is logged as created, even after an ID is assigned.
	 */
	public function test_new_profile_field_is_logged_as_created() {
		$field = (object) array(
			'id'       => null,
			'name'     => 'Job Title',
			'group_id' => 1,
		);

		$connector = $this->connector();
		$connector->callback_xprofile_field_before_save( $field );
		$field->id = 9;
		$connector->callback_xprofile_field_after_save( $field );

		$this->assertSame( 'created', self::$captured_log['action'] );
		$this->assertSame( 'Created profile field "%s"', self::$captured_log['message'] );
	}

	/**
	 * A field that already has an ID is logged as updated.
	 */
	public function test_existing_profile_field_is_logged_as_updated() {
		$field = (object) array(
			'id'       => 9,
			'name'     => 'Job Title',
			'group_id' => 1,
		);

		$connector = $this->connector();
		$connector->callback_xprofile_field_before_save( $field );
		$connector->callback_xprofile_field_after_save( $field );

		$this->assertSame( 'updated', self::$captured_log['action'] );
		$this->assertSame( 'Updated profile field "%s"', self::$captured_log['message'] );
	}

	/**
	 * Component changes log when only the always-loaded component list is available.
	 */
	public function test_component_change_logs_without_admin_component_list() {
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'bp_core_get_components' )->justReturn(
			array(
				'groups' => array(
					'title' => 'User Groups',
				),
			)
		);

		$this->connector()->check_bp_active_components(
			array(
				'members' => 1,
			),
			array(
				'members' => 1,
				'groups'  => 1,
			)
		);

		$this->assertSame( 'components', self::$captured_log['context'] );
		$this->assertSame( 'activated', self::$captured_log['action'] );
		$this->assertSame( '"User Groups" component activated', self::$captured_log['message'] );
	}

	/**
	 * Component changes use the admin list when that function is loaded.
	 */
	public function test_component_change_prefers_admin_component_list() {
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'bp_core_admin_get_components' )->justReturn(
			array(
				'groups' => array(
					'title' => 'User Groups',
				),
			)
		);
		Functions\when( 'bp_core_get_components' )->justReturn(
			array(
				'groups' => array(
					'title' => 'Public Groups',
				),
			)
		);

		$this->connector()->check_bp_active_components(
			array(
				'members' => 1,
			),
			array(
				'members' => 1,
				'groups'  => 1,
			)
		);

		$this->assertSame( '"User Groups" component activated', self::$captured_log['message'] );
	}
}

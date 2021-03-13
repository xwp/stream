<?php
/**
 * WP Integration Test w/ BuddyPress
 *
 * Tests for BuddyPress connector class callbacks.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

/**
 * Mocked delete field function from XProfile admin function page.
 *
 * @global string $message The feedback message to show.
 * @global string $type The type of feedback message to show.
 *
 * @param int    $field_id    The field to delete.
 * @param string $field_type  The type of field being deleted.
 * @param bool   $delete_data Should the field data be deleted too.
 */
function xprofile_admin_delete_field( $field_id, $field_type = 'field', $delete_data = false ) {
	global $message, $type;

	$field_type  = ( 'field' == $field_type ) ? __( 'field', 'buddypress' ) : __( 'option', 'buddypress' );

	// Handle the deletion of field
	$field = \xprofile_get_field( $field_id, null, false );

	if ( !$field->delete( (bool) $delete_data ) ) {
		/* translators: %s: the field type */
		$message = sprintf( __( 'There was an error deleting the %s. Please try again.', 'buddypress' ), $field_type );
		$type    = 'error';

		return false;
	} else {
		/* translators: %s: the field type */
		$message = sprintf( __( 'The %s was deleted successfully!', 'buddypress' ), $field_type );
		$type    = 'success';

		/**
		 * Fires at the end of the field deletion process, if successful.
		 *
		 * @since 1.0.0
		 *
		 * @param \BP_XProfile_Field $field Current BP_XProfile_Field object.
		 */
		do_action( 'xprofile_fields_deleted_field', $field );

		return true;
	}
}

/**
 * Handles the deletion of profile data groups.
 *
 * @param int $group_id ID of the group to delete.
 */
function xprofile_admin_delete_group( $group_id ) {
	global $message, $type;

	// Handle the deletion of group.
	$group = new \BP_XProfile_Group( $group_id );

	if ( ! $group->delete() ) {
		$message = _x( 'There was an error deleting the group. Please try again.', 'Error when deleting profile fields group', 'buddypress' );
		$type    = 'error';

		return false;
	} else {
		$message = _x( 'The group was deleted successfully.', 'Profile fields group was deleted successfully', 'buddypress' );
		$type    = 'success';

		/**
		 * Fires at the end of group deletion process, if successful.
		 *
		 * @since 1.0.0
		 *
		 * @param \BP_XProfile_Group $group Current BP_XProfile_Group object.
		 */
		do_action( 'xprofile_groups_deleted_group', $group );

		return true;
	}
}

class Test_WP_Stream_Connector_BuddyPress extends WP_StreamTestCase {
	/**
	 * Run before each test
	 */
	public function setUp() {
		parent::setUp();

		// Make partial of Connector_BuddyPress class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_BuddyPress::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
		update_option( 'users_can_register', true );
	}

	/**
	 * Run after each test
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function test_buddypress_installed_and_activated() {
		$this->assertTrue( is_callable( 'buddypress' ) );
	}

	public function test_option_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => null,
							'new_value'    => false,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => false,
							'new_value'    => true,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => null,
							'new_value'    => null,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo(
						sprintf(
							// translators: Placeholder refers to component title (e.g. "Members")
							__( '"%1$s" component %2$s', 'stream' ),
							'Extended Profiles',
							'activated'
						)
					),
					$this->equalTo(
						array(
							'option'     => 'xprofile',
							'option_key' => 'bp-active-components',
							'old_value'  => null,
							'value'      => array(
								'title'       => __( 'Extended Profiles', 'buddypress' ),
								'description' => __( 'Customize your community with fully editable profile fields that allow your users to describe themselves.', 'buddypress' )
							),
						)
					),

					$this->equalTo( null ),
					$this->equalTo( 'components' ),
					$this->equalTo( 'activated' ),
				)
			);

		// Modify BuddyPress-related options to trigger callbacks.
		add_option( 'bp_restrict_group_creation', false );
		update_option( 'bp_restrict_group_creation', true );
		delete_option( 'bp_restrict_group_creation' );

		bp_update_option( 'bp-active-components', bp_core_get_components( 'all' ) );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_option' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_update_option' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_delete_option' ) );
	}

	public function test_activity_callbacks() {
		$activity_args = array(
			'component'     => \buddypress()->activity->id,
			'content'       => 'Testing testing 123',
			'primary_link'  => 'http://example.com',
			'type'          => 'activity_update',
			'recorded_time' => \bp_core_current_time(),
		);

		// Create activity for later use.
		$activity = new \BP_Activity_Activity( \bp_activity_add( $activity_args ) );

		// Expected log actions.
		$this->mock->expects( $this->exactly( 3 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s an activity title (e.g. "Update") */
							__( 'Marked activity "%s" as spam', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo(	$activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'spammed' ),
				),
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s: an activity title (e.g. "Update") */
							__( 'Unmarked activity "%s" as spam', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo(	$activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'unspammed' ),
				),
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s: an activity title (e.g. "Update") */
							__( '"%s" activity deleted', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo( $activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'deleted' ),
				)
			);

		// Update activity to trigger callbacks.
		\bp_activity_mark_as_spam( $activity );
		\bp_activity_mark_as_ham( $activity );
		\bp_activity_delete_by_activity_id( $activity->id );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_mark_as_spam' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_mark_as_ham' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_before_activity_delete' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_deleted_activities' ) );
	}

	public function test_group_action_callbacks() {
		// Authenticate as admin and re-evaluate user access.
		wp_set_current_user( 1 );
		bp_update_is_item_admin( bp_user_has_access(), 'core' );

		// Create users for later use.
		$test_user_id = self::factory()->user->create( array( 'display_name' => 'testuser' ) );

		// Expected log actions.
		$this->mock->expects( $this->exactly( 13 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( esc_html__( '"Test group" group created' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name' => 'Test group',
								'slug' => 'test-group'
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'created'
				),
				array(
					$this->equalTo( esc_html__( '"Old test group" group updated' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name' => 'Old test group',
								'slug' => 'test-group'
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'updated'
				),
				array(
					$this->equalTo( esc_html__( '"Old test group" group updated' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name' => 'Old test group',
								'slug' => 'old-test-group'
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'updated'
				),
				array(
					$this->equalTo( esc_html__( '"Old test group" group updated' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name' => 'Old test group',
								'slug' => 'old-test-group'
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'updated'
				),
				array(
					$this->equalTo( esc_html__( '"testuser" joined group "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'joined'
				),
				array(
					$this->equalTo( __( 'Promoted "testuser" to "Moderator" in "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
								'status'  => 'mod',
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'promoted'
				),
				array(
					$this->equalTo( __( 'Demoted "testuser" to "Member" in "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'demoted'
				),
				array(
					$this->equalTo( esc_html__( 'Banned "testuser" from "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'banned'
				),
				array(
					$this->equalTo( esc_html__( 'Unbanned "testuser" from "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'unbanned'
				),
				array(
					$this->equalTo( esc_html__( '"testuser" left group "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'left'
				),
				array(
					$this->equalTo( esc_html__( '"testuser" joined group "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'joined'
				),
				array(
					$this->equalTo( esc_html__( 'Removed "testuser" from "Old test group"' ) ),
					$this->callback(
						function( $subject ) use ( $test_user_id ) {
							$expected = array(
								'name'    => 'Old test group',
								'slug'    => 'old-test-group',
								'user_id' => $test_user_id,
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'removed'
				),
				array(
					$this->equalTo( esc_html__( '"Old test group" group deleted' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array(
								'name' => 'Old test group',
								'slug' => 'old-test-group',
							);
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'groups',
					'deleted'
				)
			);

		// Create and manipulate Groups to trigger callbacks.
		$group_args = array(
			'creator_id'   => 1,
			'name'         => 'Test group',
			'description'  => 'Lorem ipsum dolor',
			'slug'         => 'test-group',
			'status'       => 'public',
		);
		$group_id   = \groups_create_group( $group_args );

		\groups_create_group(
			array(
				'group_id' => $group_id,
				'name'     => 'Old test group',
			)
		);

		\groups_edit_base_group_details(
			array(
				'group_id'    => $group_id,
				'slug'        => 'old-test-group',
				'description' => 'Lorem ipsum dolor two',
			)
		);

		// Manually reset duplicate log blocker flag
		$this->mock->is_update = false;

		\groups_edit_group_settings( $group_id, false, 'public' );

		\groups_join_group( $group_id, $test_user_id );
		\groups_promote_member( $test_user_id, $group_id, 'mod' );
		\groups_demote_member( $test_user_id, $group_id );

		// In order to bypass bulk activity deletion logs.
		$this->mock->ignore_activity_bulk_deletion = true;
		\groups_ban_member( $test_user_id, $group_id );

		\groups_unban_member( $test_user_id, $group_id );
		\groups_leave_group( $group_id, $test_user_id );
		\groups_join_group( $group_id, $test_user_id );

		// In order to bypass bulk activity deletion logs.
		$this->mock->ignore_activity_bulk_deletion = true;
		\groups_remove_member( $test_user_id, $group_id );

		\groups_delete_group( $group_id );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_create_group' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_update_group' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_details_updated' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_settings_updated' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_leave_group' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_join_group' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_promote_member' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_demote_member' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_ban_member' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_unban_member' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_remove_member' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_groups_before_delete_group' ) );
	}

	public function test_profile_action_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->exactly( 6 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( esc_html__( 'Created profile field group "Test field group"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'group_name' => 'Test field group' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'created',
				),
				array(
					$this->equalTo( esc_html__( 'Created profile field "Test field"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'field_name' => 'Test field' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'created',
				),
				array(
					$this->equalTo( esc_html__( 'Updated profile field "Test field"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'field_name' => 'Test field' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'updated',
				),
				array(
					$this->equalTo( esc_html__( 'Deleted profile field "Test field"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'field_name' => 'Test field' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'deleted',
				),
				array(
					$this->equalTo( esc_html__( 'Updated profile field group "Test field group"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'group_name' => 'Test field group' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'updated',
				),
				array(
					$this->equalTo( esc_html__( 'Deleted profile field group "Test field group"' ) ),
					$this->callback(
						function( $subject ) {
							$expected = array( 'group_name' => 'Test field group' );
							return $expected === array_intersect_key( $expected, $subject );
						}
					),
					$this->greaterThan( 0 ),
					'profile_fields',
					'deleted',
				)
			);

		// Create/trigger/update fields and field groups to trigger callbacks.
		// Create new field group.
		$field_group_args = array(
			'name'        => 'Test field group',
			'description' => 'Lorem ipsum dolor',
			'slug'        => 'test-field-group',
			'can_delete'  => 'false',
		);
		$group_id         = \xprofile_insert_field_group( $field_group_args );
		$group            = new \BP_XProfile_Group( $group_id );

		// Create new field in field group.
		$field_args       = array(
			'name'           => 'Test field',
			'description'    => 'Lorem ipsum dolor',
			'type'           => 'textbox',
			'field_group_id' => $group_id
		);
		$field_id         = \xprofile_insert_field( $field_args );
		$field            = new \BP_XProfile_Field( $field_id );

		// Update field.
		$field->is_required = true;
		$field->save();

		// Delete field.
		$this->assertTrue( xprofile_admin_delete_field( $field_id, 'textbox' ) );

		// Update field group.
		$group->can_delete = 1;
		$group->save();

		// Delete field group.
		$this->assertTrue( xprofile_admin_delete_group( $group->id ) );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_group_before_save' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_group_after_save' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_field_before_save' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_field_after_save' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_fields_deleted_field' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_xprofile_groups_deleted_group' ) );
	}
}

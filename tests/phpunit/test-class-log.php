<?php

namespace WP_Stream;

class Test_Log extends WP_StreamTestCase {

	public function setUp(): void {
		parent::setUp();

		$admin_role = get_role( 'administrator' );

		/**
		 * Add user roles for testing.
		 */
		$role = 'this_longish_user_role_slug_to_be_logged_in_stream';
		$long = get_role( $role );
		if ( ! $long ) {
			$long = add_role(
				$role,
				'This longish user_role slug to be logged in stream',
				(array) $admin_role->capabilities
			);
		}

		$role       = 'this_is_a_really_long_user_role_slug_that_will_not_be_logged_in_stream';
		$extra_long = get_role( $role );
		if ( ! $extra_long ) {
			$extra_long = add_role(
				$role,
				'This is a really long user_role slug that will not be logged in stream',
				(array) $admin_role->capabilities
			);
		}

		/**
		 * Add users for testing and assign roles.
		 */
		$user_id = wp_create_user( 'test1', 'password', 'test1@example.com' );
		wp_update_user(
			array(
				'ID'   => $user_id,
				'role' => $long->name,
			)
		);

		$user_id = wp_create_user( 'test2', 'password', 'test2@example.com' );
		wp_update_user(
			array(
				'ID'   => $user_id,
				'role' => $extra_long->name,
			)
		);
	}

	public function test_field_lengths() {

		$user1 = get_user_by( 'slug', 'test1' );
		$user2 = get_user_by( 'slug', 'test2' );

		// Test user_role length (<=50)
		$result = $this->plugin->log->log( 'test_connector', 'Test user_role 1', array(), 0, 'settings', 'test', $user1->ID );
		$this->assertNotEmpty( $result );
		$record = $this->plugin->db->query(
			array(
				'search'       => $result,
				'search_field' => 'ID',
			)
		);
		$this->assertEquals( $result, $record[0]->ID );

		// Test user_role length (>50)
		$result = $this->plugin->log->log( 'test_connector', 'Test user_role 2', array(), 0, 'settings', 'test', $user2->ID );
		$this->assertEmpty( $result );

		// Test connector length

		// Test context length

		// Test action length

		// Test IP length
	}

	public function test_can_map_exclude_rules_settings_to_rows() {
		$rules_settings = array(
			'exclude_row' => array(
				null,
				null,
			),
			'action'      => array(
				'one',
				null,
				'three',
			),
		);

		$this->assertEquals(
			array(
				array(
					'exclude_row'    => null,
					'action'         => 'one',
					'author_or_role' => null,
					'connector'      => null,
					'context'        => null,
					'ip_address'     => null,
				),
				array(
					'exclude_row'    => null,
					'action'         => null,
					'author_or_role' => null,
					'connector'      => null,
					'context'        => null,
					'ip_address'     => null,
				),
			),
			$this->plugin->log->exclude_rules_by_rows( $rules_settings )
		);
	}

	public function test_can_match_record_exclude() {
		$rules = array(
			'action' => 'mega_action',
		);

		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'action'     => 'mega_action',
					'ip_address' => '1.1.1.1',
				),
				$rules
			),
			'Record action is the same'
		);

		$this->assertFalse(
			$this->plugin->log->record_matches_rules(
				array(
					'action' => 'different_action',
				),
				$rules
			),
			'Record action is different'
		);
	}

	public function test_can_match_record_id_address() {
		$this->assertFalse(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '1.1.1.1',
				),
				array(
					'ip_address' => '8.8.8.8',
				)
			),
			'Record IP address is different'
		);

		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '1.1.1.1',
				),
				array(
					'ip_address' => '1.1.1.1',
				),
				'Record and rule IP addresses match'
			)
		);

		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '1.1.1.1',
				),
				array(
					'ip_address' => '8.8.8.8,1.1.1.1',
				),
				'Record IP address is one of the IP addresses in the rule'
			)
		);
	}

	public function test_ip_address_rule_matches_with_whitespace() {
		// Whitespace around commas (admin form join + user paste) must not
		// silently fail the IP match. See issue #1824.
		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '8.8.8.8',
				),
				array(
					'ip_address' => '1.1.1.1, 8.8.8.8',
				),
				'Trailing space after comma does not break the match'
			)
		);

		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '8.8.8.8',
				),
				array(
					'ip_address' => '8.8.8.8 ',
				),
				'Trailing space on a single-IP rule still matches'
			)
		);

		$this->assertFalse(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '',
				),
				array(
					'ip_address' => '1.1.1.1',
				),
				'Empty record IP never matches an IP-only rule'
			)
		);

		// Empty tokens between commas (e.g. user-pasted "1.1.1.1, ,") must be
		// dropped, not treated as a valid match against the empty record IP.
		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '1.1.1.1',
				),
				array(
					'ip_address' => '1.1.1.1, ,',
				),
				'Empty comma-separated tokens are dropped before matching'
			)
		);

		// Stored value may already be an array (direct API callers, tests).
		// Both shapes must match.
		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '8.8.8.8',
				),
				array(
					'ip_address' => array( '127.0.0.1', '8.8.8.8' ),
				),
				'Array-shaped IP rule matches the second entry'
			)
		);

		$this->assertTrue(
			$this->plugin->log->record_matches_rules(
				array(
					'ip_address' => '8.8.8.8',
				),
				array(
					'ip_address' => array( ' 8.8.8.8 ' ),
				),
				'Array-shaped IP rule trims whitespace per entry'
			)
		);
	}

	public function test_ip_only_exclude_rule_excludes_record() {
		// End-to-end coverage for the bug in #1824. Shape mirrors the
		// parallel-array rule format produced by both the wp-admin Exclude
		// list and the stream/create-exclusion-rule ability.
		$this->plugin->settings->options['exclude_rules'] = array(
			'exclude_row'    => array( 0 => '' ),
			'author_or_role' => array( 0 => '' ),
			'connector'      => array( 0 => '' ),
			'context'        => array( 0 => '' ),
			'action'         => array( 0 => '' ),
			'ip_address'     => array( 0 => '127.0.0.1' ),
		);

		$user = $this->factory->user->create_and_get();
		$user->add_role( 'administrator' );

		$this->assertTrue(
			$this->plugin->log->is_record_excluded(
				'users',
				'profile',
				'updated',
				$user,
				'127.0.0.1'
			),
			'IP-only rule excludes a record from the matching IP'
		);

		$this->assertFalse(
			$this->plugin->log->is_record_excluded(
				'users',
				'profile',
				'updated',
				$user,
				'8.8.8.8'
			),
			'IP-only rule does not exclude a record from a different IP'
		);

		// Whitespace in the stored IP value must still match.
		$this->plugin->settings->options['exclude_rules']['ip_address'][0] = '127.0.0.1, 8.8.8.8';
		$this->assertTrue(
			$this->plugin->log->is_record_excluded(
				'users',
				'profile',
				'updated',
				$user,
				'8.8.8.8'
			),
			'Comma-joined IP list with whitespace matches the second entry'
		);
	}
}

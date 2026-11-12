<?php
/**
 * Tests for the user picker API (wp_users-backed, combobox support).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class User_Picker_Test
 *
 * @package WP_Stream
 */
class User_Picker_Test extends WP_StreamTestCase {

	/**
	 * IDs created for this test.
	 *
	 * @var int[]
	 */
	private array $user_ids = array();

	/**
	 * Picker under test (stateless).
	 *
	 * @var User_Picker
	 */
	private User_Picker $picker;

	public function setUp(): void {
		parent::setUp();
		$this->picker = new User_Picker();

		$names = array(
			'WendyPicker Alpha',
			'XanderPicker Beta',
			'YannickPicker Gamma',
		);
		foreach ( $names as $name ) {
			$this->user_ids[] = self::factory()->user->create(
				array(
					'role'         => 'editor',
					'display_name' => $name,
					'user_login'   => strtolower( str_replace( ' ', '', $name ) ),
				)
			);
		}
	}

	public function tearDown(): void {
		foreach ( $this->user_ids as $user_id ) {
			self::delete_user_completely( $user_id );
		}

		parent::tearDown();
	}

	public function test_default_preload_cap_is_fifty() {
		// Guards the documented default; a stray edit here silently forces
		// every picker into combobox mode on small sites.
		$this->assertSame( 50, $this->plugin->admin->preload_users_max );
	}

	public function test_picker_under_cap_lists_all_users_and_wp_cli_sorted() {
		$cap     = 50 + count( $this->user_ids ) + 5;
		$picker  = $this->picker->get( $cap );
		$labels  = wp_list_pluck( $picker['items'], 'label' );
		$by_name = array_values(
			array_filter(
				$labels,
				function ( $label ) {
					return false !== strpos( $label, 'Picker ' );
				}
			)
		);

		$this->assertFalse( $picker['ajax'] );
		$this->assertCount( 3, $by_name, 'All created users should be listed' );
		$this->assertContains( 'WP-CLI', $labels, 'WP-CLI pseudo-user should be listed' );

		$sorted = $labels;
		usort(
			$sorted,
			function ( $a, $b ) {
				return strnatcasecmp( $a, $b );
			}
		);
		$this->assertSame( $sorted, $labels, 'Picker items should be sorted by display label' );

		foreach ( $picker['items'] as $item ) {
			$this->assertArrayHasKey( 'id', $item );
			$this->assertArrayHasKey( 'text', $item );
			$this->assertArrayHasKey( 'label', $item );
			$this->assertArrayHasKey( 'icon', $item );
		}
	}

	public function test_picker_over_cap_switches_to_ajax() {
		$total  = count_users();
		$picker = $this->picker->get( max( 1, $total['total_users'] - 1 ) );

		$this->assertTrue( $picker['ajax'] );
		$this->assertSame( array(), $picker['items'] );
	}

	public function test_picker_zero_cap_forces_ajax() {
		$picker = $this->picker->get( 0 );

		$this->assertTrue( $picker['ajax'] );
		$this->assertSame( array(), $picker['items'] );
	}

	public function test_labels_for_wp_cli_and_deleted_users() {
		$this->assertSame( 'WP-CLI', $this->picker->label( 0 ) );

		$deleted_id = self::factory()->user->create( array( 'display_name' => 'Deleted Picker User' ) );
		self::delete_user_completely( $deleted_id );

		$this->assertSame(
			sprintf( 'Unknown user %d', $deleted_id ),
			$this->picker->label( $deleted_id )
		);
	}

	public function test_label_for_value_rejects_non_numeric_input() {
		$this->assertSame( '', $this->picker->label_for_value( 'banana' ) );
		$this->assertSame( '', $this->picker->label_for_value( '1e5' ) );
		$this->assertSame( 'WP-CLI', $this->picker->label_for_value( '0' ) );
	}

	public function test_search_matches_display_name_and_login() {
		$results = $this->picker->search( 'WendyPicker', 50 );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'WendyPicker Alpha', $results[0]['label'] );

		$results = $this->picker->search( 'yannickpickergamma', 50 );
		$this->assertNotEmpty( $results, 'Search should match user logins' );
		$this->assertSame( 'YannickPicker Gamma', $results[0]['label'] );
	}

	public function test_search_offers_wp_cli_for_system_queries() {
		$results = $this->picker->search( 'cli', 50 );
		$labels  = wp_list_pluck( $results, 'label' );

		$this->assertContains( 'WP-CLI', $labels );
	}

	public function test_search_without_matches_returns_empty_list() {
		$results = $this->picker->search( 'no-such-user-zzz', 50 );

		$this->assertSame( array(), $results );
	}

	public function test_search_respects_limit() {
		$results = $this->picker->search( 'Picker', 2 );

		$this->assertCount( 2, $results );
	}
}

<?php
/**
 * Tests for Ability_Get_Exclusion_Rules.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Ability_Get_Exclusion_Rules
 */
class Test_Ability_Get_Exclusion_Rules extends Abilities_TestCase {

	/**
	 * Ability under test.
	 *
	 * @var Ability_Get_Exclusion_Rules
	 */
	protected $ability;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		require_once $this->plugin->locations['dir'] . 'abilities/class-ability-get-exclusion-rules.php';
		$this->ability = new Ability_Get_Exclusion_Rules( $this->plugin );
	}

	public function test_name_and_schema() {
		$this->assertSame( 'stream/get-exclusion-rules', $this->ability->get_name() );
		$this->assertSame( array(), $this->ability->get_input_schema() );

		$output = $this->ability->get_output_schema();
		$this->assertSame( 'array', $output['type'] );
	}

	public function test_permissions() {
		wp_set_current_user( $this->subscriber_user_id );
		$this->assertFalse( $this->ability->permission_callback() );

		wp_set_current_user( $this->admin_user_id );
		$this->assertTrue( $this->ability->permission_callback() );
	}

	public function test_returns_empty_array_when_no_rules_configured() {
		wp_set_current_user( $this->admin_user_id );
		$result = $this->ability->execute( array() );
		$this->assertSame( array(), $result );
	}

	public function test_returns_pivoted_rule_rows() {
		wp_set_current_user( $this->admin_user_id );

		// Inject a couple of rules in the parallel-array shape Stream uses.
		$this->plugin->settings->options['exclude_rules'] = array(
			'exclude_row'    => array( 'rule-a', 'rule-b' ),
			'author_or_role' => array( 'administrator', '0' ),
			'connector'      => array( 'posts', 'users' ),
			'context'        => array( 'post', '' ),
			'action'         => array( 'updated', '' ),
			'ip_address'     => array( '', '127.0.0.1' ),
		);

		$result = $this->ability->execute( array() );

		$this->assertCount( 2, $result );
		$this->assertSame( 'administrator', $result[0]['author_or_role'] );
		$this->assertSame( 'posts', $result[0]['connector'] );
		$this->assertSame( '127.0.0.1', $result[1]['ip_address'] );
		// exclude_row is internal — should not appear in output.
		$this->assertArrayNotHasKey( 'exclude_row', $result[0] );
	}
}

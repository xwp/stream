<?php
namespace WP_Stream;

/**
 * Class Alert_Trigger_Author_Test
 *
 * @package WP_Stream
 * @group alerts
 */
class Alert_Trigger_Author_Test extends Alert_Trigger_TestCase {

	public function setUp(): void {
		parent::setUp();
		$this->trigger = new Alert_Trigger_Author( $this->plugin );

		$this->alert                               = $this->plugin->alerts->get_alert();
		$this->alert->alert_meta['trigger_author'] = '1';
	}

	public function test_check_record_bad() {
		$data            = $this->dummy_stream_data();
		$data['user_id'] = '2';

		$status = $this->trigger->check_record( true, null, $data, $this->alert );
		$this->assertFalse( $status );
	}

	public function test_save_fields() {
		$_POST['wp_stream_trigger_author'] = '0';

		$this->assertNotEquals( '0', $this->alert->alert_meta['trigger_author'] );
		$this->trigger->save_fields( $this->alert );
		$this->assertEquals( '0', $this->alert->alert_meta['trigger_author'] );

		$_POST['wp_stream_trigger_author'] = '12';
		$this->trigger->save_fields( $this->alert );
		$this->assertEquals( '12', $this->alert->alert_meta['trigger_author'] );

		$_POST['wp_stream_trigger_author'] = '1e5';
		$this->trigger->save_fields( $this->alert );
		$this->assertSame( '', $this->alert->alert_meta['trigger_author'] );

		$_POST['wp_stream_trigger_author'] = array( '12' );
		$this->trigger->save_fields( $this->alert );
		$this->assertSame( '', $this->alert->alert_meta['trigger_author'] );

		unset( $_POST['wp_stream_trigger_author'] );
	}
	public function test_check_record_wp_cli_does_not_match_other_users() {
		$this->alert->alert_meta['trigger_author'] = '0';

		$other = $this->dummy_stream_data();
		$this->assertFalse(
			$this->trigger->check_record( true, null, $other, $this->alert )
		);

		$cli            = $this->dummy_stream_data();
		$cli['user_id'] = '0';
		$this->assertTrue(
			$this->trigger->check_record( true, null, $cli, $this->alert )
		);
	}

	public function test_get_display_value_for_wp_cli() {
		$this->alert->alert_meta['trigger_author'] = '0';
		$this->assertSame( 'WP-CLI', $this->trigger->get_display_value( 'list_table', $this->alert ) );

		$this->alert->alert_meta['trigger_author'] = '';
		$this->assertSame( 'Any User', $this->trigger->get_display_value( 'list_table', $this->alert ) );
	}

	public function test_add_fields_uses_combobox_over_cap() {
		$this->plugin->admin->preload_users_max = 0;
		$form                                   = new Form_Generator();
		$this->trigger->add_fields( $form, $this->alert );
		$this->assertSame( 'user_combobox', $form->fields[0]['type'] );
	}

	public function test_add_fields_uses_grouped_select_under_cap() {
		$this->plugin->admin->preload_users_max = 50;
		$form                                   = new Form_Generator();
		$this->trigger->add_fields( $form, $this->alert );
		$this->assertSame( 'grouped_select', $form->fields[0]['type'] );
	}
}

<?php
namespace WP_Stream;

/**
 * Class Test_Alert_Port
 *
 * @package WP_Stream
 * @group alerts
 */
class Test_Alert_Port extends WP_StreamTestCase {

	/**
	 * Alert_Port instance under test.
	 *
	 * @var Alert_Port
	 */
	private $alert_port;

	public function setUp(): void {
		parent::setUp();
		$this->alert_port = new Alert_Port( $this->plugin );
	}

	/**
	 * Seed an alert post and return its ID.
	 *
	 * @param string $type       Alert type slug.
	 * @param string $status     Alert status.
	 * @param array  $alert_meta Alert meta.
	 * @return int
	 */
	private function seed_alert( $type, $status, array $alert_meta = array() ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Alerts::POST_TYPE,
				'post_status' => $status,
				'post_title'  => 'Test alert',
			)
		);
		update_post_meta( $post_id, 'alert_type', $type );
		update_post_meta( $post_id, 'alert_meta', $alert_meta );

		return $post_id;
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->alert_port->plugin );
	}

	public function test_get_export_data_shape() {
		$this->seed_alert(
			'email',
			Alerts::STATUS_ENABLED,
			array(
				'trigger_author'    => '',
				'trigger_connector' => 'posts',
				'trigger_context'   => 'post',
				'trigger_action'    => 'updated',
				'email_recipient'   => 'admin@example.com',
				'custom_thirdparty' => 'kept',
			)
		);

		$data = $this->alert_port->get_export_data();

		$this->assertSame( Alert_Port::GENERATOR, $data['generator'] );
		$this->assertSame( Alert_Port::FORMAT_VERSION, $data['version'] );
		$this->assertCount( 1, $data['alerts'] );

		$row = $data['alerts'][0];
		$this->assertSame( 'email', $row['alert_type'] );
		$this->assertSame( Alerts::STATUS_ENABLED, $row['status'] );

		// Unknown / third-party meta keys must survive export.
		$this->assertArrayHasKey( 'custom_thirdparty', $row['alert_meta'] );
		$this->assertSame( 'kept', $row['alert_meta']['custom_thirdparty'] );
	}

	public function test_import_round_trip() {
		$first_id  = $this->seed_alert(
			'highlight',
			Alerts::STATUS_ENABLED,
			array(
				'trigger_action' => 'updated',
				'color'          => 'red',
			)
		);
		$second_id = $this->seed_alert(
			'email',
			Alerts::STATUS_DISABLED,
			array( 'email_recipient' => 'admin@example.com' )
		);

		$payload = $this->alert_port->get_export_data();

		// Wipe the originals so only imported copies remain.
		wp_delete_post( $first_id, true );
		wp_delete_post( $second_id, true );

		$result = $this->alert_port->import_alerts( $payload['alerts'] );

		$this->assertSame( 2, $result['imported'] );
		$this->assertSame( 0, $result['skipped'] );

		$all = $this->plugin->alerts->get_alerts();
		$this->assertCount( 2, $all );

		// Imported alert_meta values must round-trip intact.
		$found_red = false;
		foreach ( $all as $alert ) {
			if ( 'highlight' === $alert->alert_type && isset( $alert->alert_meta['color'] ) ) {
				$this->assertSame( 'red', $alert->alert_meta['color'] );
				$found_red = true;
			}
		}
		$this->assertTrue( $found_red, 'Imported highlight color was lost.' );
	}

	public function test_import_skips_unknown_alert_type() {
		$payload = array(
			array(
				'status'     => Alerts::STATUS_ENABLED,
				'alert_type' => 'bogus_type',
				'alert_meta' => array(),
			),
			array(
				'status'     => Alerts::STATUS_ENABLED,
				'alert_type' => 'highlight',
				'alert_meta' => array( 'color' => 'blue' ),
			),
		);

		$result = $this->alert_port->import_alerts( $payload );

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	public function test_import_resets_missing_trigger_author() {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$payload = array(
			array(
				'status'     => Alerts::STATUS_ENABLED,
				'alert_type' => 'highlight',
				'alert_meta' => array(
					'trigger_author' => (string) $user,
				),
			),
			array(
				'status'     => Alerts::STATUS_ENABLED,
				'alert_type' => 'highlight',
				'alert_meta' => array(
					'trigger_author' => '999999999',
				),
			),
		);

		$result = $this->alert_port->import_alerts( $payload );

		$this->assertSame( 2, $result['imported'] );

		$all     = $this->plugin->alerts->get_alerts();
		$authors = array();
		foreach ( $all as $alert ) {
			$authors[] = $alert->alert_meta['trigger_author'];
		}

		// Existing user preserved, missing user reset to "any".
		$this->assertContains( (string) $user, $authors );
		$this->assertContains( '', $authors );
	}

	public function test_import_skips_entry_without_type() {
		$result = $this->alert_port->import_alerts(
			array(
				array(
					'status'     => Alerts::STATUS_ENABLED,
					'alert_meta' => array(),
				),
			)
		);

		$this->assertSame( 0, $result['imported'] );
		$this->assertSame( 1, $result['skipped'] );
	}

	public function test_import_skips_non_array_entry() {
		$result = $this->alert_port->import_alerts(
			array(
				'not-an-array',
				array(
					'status'     => Alerts::STATUS_ENABLED,
					'alert_type' => 'highlight',
					'alert_meta' => array( 'color' => 'green' ),
				),
			)
		);

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 1, $result['skipped'] );
	}
}

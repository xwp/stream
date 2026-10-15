<?php
namespace WP_Stream;

class Connector_Test extends WP_StreamTestCase {
	/**
	 * Holds the Connector base class
	 *
	 * @var Connector
	 */
	protected $connector;

	public function setUp(): void {
		parent::setUp();

		$this->connector = new class() extends Connector {
			/**
			 * Connector slug
			 *
			 * @var string
			 */
			public string $name = 'maintenance';

			/**
			 * Actions registered for this connector
			 *
			 * @var array
			 */
			public array $actions = array(
				'simulate_fault',
				'hyphenated-action',
			);

			/**
			 * Return translated connector label
			 *
			 * @return string Translated connector label
			 */
			public function get_label() {
				return esc_html__( 'Maintenance', 'stream' );
			}

			/**
			 * Return translated action labels
			 *
			 * @return array Action label translations
			 */
			public function get_action_labels() {
				return array(
					'simulated_fault' => esc_html__( 'Fault', 'stream' ),
				);
			}

			/**
			 * Return translated context labels
			 *
			 * @return array Context label translations
			 */
			public function get_context_labels() {
				return array(
					'ae35' => esc_html__( 'AE35 Unit', 'stream' ),
				);
			}

			/**
			 * Log the ae35 test result
			 *
			 * @action ae35_test
			 */
			public function callback_simulate_fault() {
				// This is used to check if this callback method actually ran
				do_action( 'wp_stream_test_child_callback_simulate_fault' );
			}

			/**
			 * Log the hyphenated action callback.
			 *
			 * @action hyphenated-action
			 *
			 * @return void
			 */
			public function callback_hyphenated_action() {
				do_action( 'wp_stream_test_child_callback_hyphenated_action' );
			}
		};

		$this->assertNotEmpty( $this->connector );
	}

	public function test_register() {
		foreach ( $this->connector->actions as $tag ) {
			$this->assertFalse( has_action( $tag ) );
		}

		$this->connector->register();

		foreach ( $this->connector->actions as $tag ) {
			$this->assertGreaterThan( 0, has_action( $tag ) );
		}
	}

	public function test_unregister() {
		$this->connector->register();

		foreach ( $this->connector->actions as $tag ) {
			$this->assertGreaterThan( 0, has_action( $tag ) );
		}

		$this->connector->unregister();

		foreach ( $this->connector->actions as $tag ) {
			$this->assertFalse( has_action( $tag ) );
		}
	}

	public function test_callback() {
		global $wp_current_filter;
		$action              = $this->connector->actions[0];
		$wp_current_filter[] = $action; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->connector->callback();

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_' . $action ) );
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'child_callback_' . $action ) );
	}

	public function test_callback_hyphenated() {
		global $wp_current_filter;
		$action              = $this->connector->actions[1];
		$wp_current_filter[] = $action; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->connector->callback();

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_hyphenated_action' ) );
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'child_callback_hyphenated_action' ) );
	}

	public function test_action_links() {
		$current_links = array(
			'IMDB' => '',
		);

		$new_links = $this->connector->action_links( $current_links, null );

		$this->assertEquals( $current_links, $new_links );
	}

	public function test_log() {
		$percent_failure = 100;
		$hours_remaining = 72;

		$message = 'I\'ve just picked up a fault in the AE35 unit. It\'s going to go %1$s%% failure in %2$s hours.';

		$id = $this->connector->log(
			$message,
			array(
				$percent_failure,
				$hours_remaining,
			),
			null,
			'ae35',
			'simulate_fault',
			get_current_user_id()
		);

		global $wpdb;
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->stream} WHERE ID = %d ORDER BY created DESC LIMIT 1", array( $id ) ) );
		$this->assertNotEmpty( $result );

		$this->assertEquals( sprintf( $message, $percent_failure, $hours_remaining ), $result->summary );
		$this->assertEquals( 'maintenance', $result->connector );
		$this->assertEquals( 'ae35', $result->context );
		$this->assertEquals( 'simulate_fault', $result->action );
	}

	public function test_delayed_log() {
		$action = $this->connector->actions[0];

		$percent_failure = 100;
		$hours_remaining = 72;

		$message = 'I\'ve just picked up a fault in the AE35 unit. It\'s going to go %1$s%% failure in %2$s hours.';

		$this->connector->delayed_log(
			$action,
			$message,
			array(
				$percent_failure,
				$hours_remaining,
			),
			null,
			'ae35',
			'simulate_fault',
			get_current_user_id()
		);

		$this->assertNotEmpty( $this->connector->delayed[ $action ] );
		$this->assertIsArray( $this->connector->delayed[ $action ] );

		global $wpdb;
		$first_count = $wpdb->get_var( "SELECT COUNT( ID ) FROM {$wpdb->stream}" );

		$this->connector->delayed_log_commit();

		$second_count = $wpdb->get_var( "SELECT COUNT( ID ) FROM {$wpdb->stream}" );
		$this->assertEquals( $second_count, $first_count + 1 );
	}

	public function test_delayed_log_commit() {
		$action = $this->connector->actions[0];

		$percent_failure = 100;
		$hours_remaining = 72;

		$message = 'I\'ve just picked up a fault in the AE35 unit. It\'s going to go %1$s%% failure in %2$s hours.';

		$this->connector->delayed = array(
			$action => array(
				$message,
				array(
					$percent_failure,
					$hours_remaining,
				),
				null,
				'ae35',
				'simulate_fault',
				get_current_user_id(),
			),
		);

		global $wpdb;
		$first_count = $wpdb->get_var( "SELECT COUNT( ID ) FROM {$wpdb->stream}" );

		$this->connector->delayed_log_commit();

		$second_count = $wpdb->get_var( "SELECT COUNT( ID ) FROM {$wpdb->stream}" );
		$this->assertEquals( $second_count, $first_count + 1 );
	}

	public function test_get_changed_keys() {
		$array_one = array(
			'one' => 'foo',
			'two' => array(
				'a' => 'alpha',
				'b' => 'beta',
			),
		);
		$array_two = $array_one;

		$this->assertEmpty( $this->connector->get_changed_keys( $array_one, $array_two ) );

		$array_two['one']      = 'bar';
		$array_two['two']['a'] = 'aleph';

		$this->assertEquals( array( 'one', 'two' ), $this->connector->get_changed_keys( $array_one, $array_two ) );
		$this->assertEquals( array( 'one', 'two', 'two::a' ), array_keys( $this->connector->get_changed_keys( $array_one, $array_two, 1 ) ) );
	}

	public function test_is_dependency_satisfied() {
		$this->assertTrue( $this->connector->is_dependency_satisfied() );
	}

	/**
	 * Test that percentages are escaped.
	 *
	 * @return void
	 */
	public function test_escape_percentages() {
		$escaped_value = $this->connector->escape_percentages( 'This is a message with a % sign' );
		$this->assertEquals(
			'This is a message with a %% sign',
			$escaped_value
		);
	}

	/**
	 * Credential-looking setting names are recognised regardless of case or
	 * surrounding words, since connectors log arbitrary third-party option
	 * names that an allowlist could not anticipate.
	 *
	 * @return void
	 */
	public function test_is_secret_key() {
		$secret = array(
			'mailserver_pass',
			'password',
			'rg_gforms_captcha_private_key',
			'stripe_secret_key',
			'API_KEY',
			'publishable_token',
			'webhook',
			'client_secret',
			'rg_gforms_key',
			// PayPal NVP gateways match none of the other patterns.
			'api_signature',
			'paypal_pro_signature',
		);
		foreach ( $secret as $key ) {
			$this->assertTrue(
				$this->connector->is_secret_key( $key ),
				"$key should be treated as a secret."
			);
		}

		$safe = array(
			'blogname',
			'general_records_ttl',
			'title',
			'enabled',
			'email_recipient',
			'default_category',
			// Words merely ending in "key" must not be caught; only the "_key"
			// suffix used by real credential option names.
			'monkey',
			'turkey',
			// Public halves of key pairs are meant to be published.
			'rg_gforms_captcha_public_key',
			'stripe_publishable_key',
			'recaptcha_site_key',
			'',
		);
		foreach ( $safe as $key ) {
			$this->assertFalse(
				$this->connector->is_secret_key( $key ),
				"$key should not be treated as a secret."
			);
		}
	}

	/**
	 * The public-name exemption exists only to stop the broad "_key" suffix
	 * rule from catching published key halves. It must not rescue a name that
	 * also carries an explicit secret marker, or the exemption would become a
	 * bypass.
	 *
	 * @return void
	 */
	public function test_explicit_secret_marker_beats_public_name() {
		$this->assertTrue( $this->connector->is_secret_key( 'secret_site_key' ) );
		$this->assertTrue( $this->connector->is_secret_key( 'webhook_public_key' ) );
		$this->assertTrue( $this->connector->is_secret_key( 'private_key_public_key' ) );

		// Still exempt when nothing marks them as secret.
		$this->assertFalse( $this->connector->is_secret_key( 'recaptcha_site_key' ) );
		$this->assertFalse( $this->connector->is_secret_key( 'rg_gforms_captcha_public_key' ) );
	}

	/**
	 * A scalar is redacted based on its own key, which is the shape used by the
	 * settings and Gravity Forms connectors.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_scalar() {
		$this->assertSame(
			Connector::REDACTED_PLACEHOLDER,
			$this->connector->redact_secret_values( 'hunter2', 'mailserver_pass' )
		);
		$this->assertSame(
			'My Site',
			$this->connector->redact_secret_values( 'My Site', 'blogname' )
		);
	}

	/**
	 * An unset credential stays an empty string rather than gaining the
	 * placeholder, so a reader can distinguish "was never set" from "set but
	 * withheld" -- the latter carries the marker.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_leaves_empty_values() {
		$this->assertSame( '', $this->connector->redact_secret_values( '', 'password' ) );
		$this->assertNotSame(
			Connector::REDACTED_PLACEHOLDER,
			$this->connector->redact_secret_values( '', 'password' ),
			'An empty credential must not look like a withheld one.'
		);
	}

	/**
	 * Whole settings arrays (payment gateways, integration configs) are
	 * redacted member-wise and recursively, keeping non-secret settings intact
	 * so the record still describes what changed.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_array() {
		$gateway = array(
			'enabled'         => 'yes',
			'title'           => 'Credit Card',
			'secret_key'      => 'sk_live_deadbeefdeadbeef',
			'publishable_key' => 'pk_live_public',
			'nested'          => array(
				'webhook'     => 'https://example.com/hook/abcdef',
				'description' => 'Pay by card',
			),
		);

		$redacted = $this->connector->redact_secret_values( $gateway, 'woocommerce_stripe_settings' );

		$this->assertSame( 'yes', $redacted['enabled'] );
		$this->assertSame( 'Credit Card', $redacted['title'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['secret_key'], 'A live API secret must not be persisted.' );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['nested']['webhook'], 'Nested credentials must be redacted too.' );
		$this->assertSame( 'Pay by card', $redacted['nested']['description'] );

		// The publishable half of a key pair is designed to be public, so
		// redacting it would remove audit detail for no benefit.
		$this->assertSame( 'pk_live_public', $redacted['publishable_key'] );

		$this->assertStringNotContainsString(
			'sk_live_deadbeefdeadbeef',
			maybe_serialize( $redacted ),
			'The secret must not survive serialization into record metadata.'
		);
	}

	/**
	 * A secret parent can hold credentials under opaque keys. Jetpack keys
	 * `user_tokens` by user ID, so no child name shows that the value is a
	 * token. The parent name must make all children secret.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_secret_parent_map() {
		$input = array(
			'1'  => 'user.token.one',
			'42' => 'user.token.forty.two',
		);

		$redacted = $this->connector->redact_secret_values( $input, 'user_tokens' );

		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['1'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['42'] );
		$this->assertStringNotContainsString(
			'user.token.one',
			maybe_serialize( $redacted ),
			'A token under an opaque key must not reach record metadata.'
		);
	}

	/**
	 * The parent name goes down through every level, so a deep tree under a
	 * secret parent cannot keep a credential.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_secret_parent_nested_map() {
		$input = array(
			'facebook_1' => array(
				'id'           => '1234',
				'access_token' => 'live.access.token',
				'profile'      => array(
					'name' => 'Example Page',
				),
			),
		);

		$redacted = $this->connector->redact_secret_values( $input, 'publicize_credentials' );

		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['facebook_1']['id'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['facebook_1']['access_token'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['facebook_1']['profile']['name'] );
	}

	/**
	 * A parent that is not secret keeps the current behaviour: each child is
	 * judged by its own name, so non-secret settings stay readable.
	 *
	 * @return void
	 */
	public function test_redact_secret_values_keeps_children_of_safe_parent() {
		$input = array(
			'blogname' => 'My Site',
			'api_key'  => 'live-key',
		);

		$redacted = $this->connector->redact_secret_values( $input, 'jetpack_options' );

		$this->assertSame( 'My Site', $redacted['blogname'] );
		$this->assertSame( Connector::REDACTED_PLACEHOLDER, $redacted['api_key'] );
	}
}

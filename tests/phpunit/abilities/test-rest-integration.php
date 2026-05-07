<?php
/**
 * REST integration tests for Stream abilities.
 *
 * Verifies that abilities registered through Abilities API actually serve HTTP
 * requests at /wp-abilities/v1/abilities/stream/{slug}/run with correct status
 * codes and method routing. Complements the per-ability unit tests, which only
 * exercise execute() in isolation.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Abilities_REST_Integration
 */
class Test_Abilities_REST_Integration extends Abilities_TestCase {

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Loader instance used to register all 11 abilities under test.
	 *
	 * @var Abilities
	 */
	protected $loader;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		// Boot a fresh REST server for each test so route registration is clean.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// Register the stream category and all 11 Stream abilities. The Abilities
		// loader is the production code path; we just drive it with the
		// $wp_current_filter trick so wp_register_ability_category() and
		// wp_register_ability() pass their doing_action() guards.
		$this->loader = new Abilities( $this->plugin );
		$this->loader->load_abilities();

		global $wp_current_filter;

		if ( ! wp_has_ability_category( Abilities::CATEGORY_SLUG ) ) {
			$wp_current_filter[] = 'wp_abilities_api_categories_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->loader->register_category();
			array_pop( $wp_current_filter );
		}

		foreach ( $this->loader->abilities as $ability ) {
			if ( wp_has_ability( $ability->get_name() ) ) {
				continue;
			}
			$wp_current_filter[] = 'wp_abilities_api_init'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$ability->register();
			array_pop( $wp_current_filter );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		parent::tearDown();
	}

	/**
	 * Build the run-endpoint URL for a Stream ability slug.
	 *
	 * @param string $slug Ability slug (without "stream/" prefix).
	 * @return string Route path.
	 */
	private function run_url( $slug ) {
		return '/wp-abilities/v1/abilities/stream/' . $slug . '/run';
	}

	// -------------------------------------------------------------------
	// Read-only ability: stream/get-records (GET).
	// -------------------------------------------------------------------

	/**
	 * GET on a readonly ability dispatches successfully for an admin.
	 */
	public function test_get_records_returns_200_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'GET', $this->run_url( 'get-records' ) );
		// Input must be passed even if empty: WP REST validates against the schema.
		$request->set_query_params( array( 'input' => array() ) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Body: ' . wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'records', $data );
		$this->assertArrayHasKey( 'total', $data );
	}

	public function test_get_records_returns_403_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$request = new \WP_REST_Request( 'GET', $this->run_url( 'get-records' ) );
		$request->set_query_params( array( 'input' => array() ) );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_records_rejects_post_method() {
		wp_set_current_user( $this->admin_user_id );

		$request  = new \WP_REST_Request( 'POST', $this->run_url( 'get-records' ) );
		$response = $this->server->dispatch( $request );

		// WP core enforces GET for readonly abilities; POST returns 405.
		$this->assertSame( 405, $response->get_status() );
	}

	// -------------------------------------------------------------------
	// Write ability: stream/create-alert (POST).
	// -------------------------------------------------------------------

	/**
	 * POST on a write ability dispatches successfully for an admin.
	 */
	public function test_create_alert_returns_200_for_admin() {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'POST', $this->run_url( 'create-alert' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'alert_type'      => 'highlight',
						'trigger_author'  => 'any',
						'trigger_context' => 'any',
						'trigger_action'  => 'any',
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Body: ' . wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'id', $data );
		$this->assertIsInt( $data['id'] );
	}

	public function test_create_alert_returns_403_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$request = new \WP_REST_Request( 'POST', $this->run_url( 'create-alert' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'alert_type'      => 'highlight',
						'trigger_author'  => 'any',
						'trigger_context' => 'any',
						'trigger_action'  => 'any',
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_create_alert_rejects_get_method() {
		wp_set_current_user( $this->admin_user_id );

		$request  = new \WP_REST_Request( 'GET', $this->run_url( 'create-alert' ) );
		$response = $this->server->dispatch( $request );

		// Write abilities require POST; GET returns 405.
		$this->assertSame( 405, $response->get_status() );
	}

	// -------------------------------------------------------------------
	// Destructive ability: stream/purge-records (DELETE).
	// -------------------------------------------------------------------

	/**
	 * DELETE on a destructive+idempotent ability dispatches successfully.
	 */
	public function test_purge_records_returns_200_for_admin_with_filters() {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'DELETE', $this->run_url( 'purge-records' ) );
		$request->set_query_params(
			array(
				'input' => array(
					'confirm'         => true,
					'older_than_days' => 365,
				),
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Body: ' . wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertGreaterThanOrEqual( 0, $data['deleted'] );
	}

	public function test_purge_records_returns_403_for_subscriber() {
		wp_set_current_user( $this->subscriber_user_id );

		$request = new \WP_REST_Request( 'DELETE', $this->run_url( 'purge-records' ) );
		$request->set_query_params(
			array(
				'input' => array(
					'confirm'         => true,
					'older_than_days' => 365,
				),
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_purge_records_rejects_post_method() {
		wp_set_current_user( $this->admin_user_id );

		$request = new \WP_REST_Request( 'POST', $this->run_url( 'purge-records' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'input' => array(
						'confirm'         => true,
						'older_than_days' => 365,
					),
				)
			)
		);

		$response = $this->server->dispatch( $request );

		// Destructive abilities require DELETE; POST returns 405.
		$this->assertSame( 405, $response->get_status() );
	}

	// -------------------------------------------------------------------
	// Discovery: routes are listed and unknown abilities return 404.
	// -------------------------------------------------------------------

	/**
	 * Unknown ability slugs route to a 404 from the core run controller.
	 *
	 * @expectedIncorrectUsage WP_Abilities_Registry::get_registered
	 */
	public function test_unknown_ability_returns_404() {
		wp_set_current_user( $this->admin_user_id );

		$request  = new \WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/stream/no-such-ability/run' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_all_eleven_stream_abilities_appear_in_list_endpoint() {
		wp_set_current_user( $this->admin_user_id );

		$request  = new \WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );

		$names = array();
		foreach ( $data as $ability ) {
			if ( isset( $ability['name'] ) ) {
				$names[] = $ability['name'];
			}
		}

		foreach ( $this->loader->get_ability_slugs() as $slug ) {
			$this->assertContains( 'stream/' . $slug, $names );
		}
	}
}

<?php
/**
 * WP Integration Test w/ Mercator
 *
 * Tests for Mercator connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_Mercator_Test extends WP_StreamTestCase {

	/**
	 * Recorded log() arguments from mocked connector callbacks.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private static $recorded_log_calls = array();

	/**
	 * Records log() arguments for post-hoc assertions.
	 *
	 * @param mixed ...$args Log method arguments.
	 * @return void
	 */
	public static function record_log_call( ...$args ) {
		self::$recorded_log_calls[] = $args;
	}

	public function setUp(): void {
		parent::setUp();

		self::$recorded_log_calls = array();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		// Add hook to provide mock blog details because sub-sites aren't
		// created with an options table to use.
		add_filter( 'site_details', array( $this, 'get_testsite_details' ) );

		// Make partial of Connector_Mercator class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Mercator::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Mock function that define stub blog details that would be store in the blog options table
	 * which isn't created during tests.
	 *
	 * @param object $details
	 * @return object
	 */
	public function get_testsite_details( $details ) {
		global $base;

		$details->blogname   = 'testsite';
		$details->siteurl    = $base . '/testsite';
		$details->post_count = 0;
		$details->home       = $base . '/testsite';

		return $details;
	}

	/**
	 * Test for get_context_labels().
	 *
	 * @group ms-required
	 */
	public function test_get_context_labels() {
		// Validate this works for foreign characters as well.
		$id     = $this->factory->blog->create( array( 'title' => 'ובזכויותיהם' ) );
		$labels = $this->mock->get_context_labels();
		$this->assertArrayHasKey( 'blog-1', $labels );
		$this->assertArrayHasKey( 'blog-' . $id, $labels );
	}

	public function test_callback_mercator_mapping_made_primary() {
		$blog_id = $this->factory->blog->create( array( 'title' => 'testsite' ) );
		$mapping = \Mercator\Mapping::create( $blog_id, 'example.com' );

		$this->mock->expects( $this->exactly( 3 ) )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'record_log_call' ) );

		$mapping->make_primary();

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_mercator_mapping_made_primary' ) );

		$this->assertSame(
			_x(
				'"%1$s" domain alias was created for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			self::$recorded_log_calls[0][0]
		);
		$this->assertSame(
			array(
				'domain'    => 'example.org',
				'site_name' => 'testsite',
			),
			self::$recorded_log_calls[0][1]
		);
		$this->assertSame( $blog_id, self::$recorded_log_calls[0][2] );
		$this->assertSame( 'testsite', self::$recorded_log_calls[0][3] );
		$this->assertSame( 'created', self::$recorded_log_calls[0][4] );

		$this->assertSame(
			_x(
				'"%1$s" domain alias was deleted for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			self::$recorded_log_calls[1][0]
		);
		$this->assertSame(
			array(
				'domain'    => 'example.com',
				'site_name' => 'testsite',
			),
			self::$recorded_log_calls[1][1]
		);
		$this->assertSame( $blog_id, self::$recorded_log_calls[1][2] );
		$this->assertSame( 'testsite', self::$recorded_log_calls[1][3] );
		$this->assertSame( 'deleted', self::$recorded_log_calls[1][4] );

		$this->assertSame(
			_x(
				'"%1$s" domain alias was make primary for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			self::$recorded_log_calls[2][0]
		);
		$this->assertSame(
			array(
				'domain'    => 'example.com',
				'site_name' => 'testsite',
			),
			self::$recorded_log_calls[2][1]
		);
		$this->assertSame( $blog_id, self::$recorded_log_calls[2][2] );
		$this->assertSame( 'testsite', self::$recorded_log_calls[2][3] );
		$this->assertSame( 'made_primary', self::$recorded_log_calls[2][4] );
	}

	public function test_callback_mercator_mapping_updated() {
		// Make blog and aliases for later use.
		$blog_id = $this->factory->blog->create( array( 'title' => 'testsite' ) );
		$mapping = \Mercator\Mapping::create( $blog_id, 'example.com', true );
		$mapping->make_primary();

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'The domain alias "%1$s" was updated to "%2$s" for site "%3$s"',
						'1. Old Domain alias 2. Domain alias 2. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'old_domain' => 'example.com',
						'domain'     => 'testsite.com',
						'site_name'  => 'testsite',
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		// Change site alias to trigger callback.
		$mapping->set_domain( 'testsite.com' );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_mercator_mapping_updated' ) );
	}

	public function test_callback_mercator_mapping_deleted() {
		// Make blog and alias for later use.
		$blog_id = $this->factory->blog->create( array( 'title' => 'testsite' ) );
		$mapping = \Mercator\Mapping::create( $blog_id, 'example.com', true );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" domain alias was deleted for "%2$s"',
						'1. Domain alias 2. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'domain'    => 'example.com',
						'site_name' => 'testsite',
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'deleted' )
			);

		/*
		 * Execute action to trigger callback because the tables need to
		 * run the \Mercator\Mapping::delete() don't currently exist.
		 */
		do_action( 'mercator.mapping.deleted', $mapping ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_mercator_mapping_deleted' ) );
	}

	public function test_callback_mercator_mapping_created() {
		// Make blog for later use.
		$blog_id = $this->factory->blog->create( array( 'title' => 'testsite' ) );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" domain alias was created for "%2$s"',
						'1. Domain alias 2. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'domain'    => 'example.com',
						'site_name' => 'testsite',
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'created' )
			);

		// Create and assign domain alias to site to trigger callback.
		\Mercator\Mapping::create( $blog_id, 'example.com', true );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_mercator_mapping_created' ) );
	}
}

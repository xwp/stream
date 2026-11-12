<?php
/**
 * Tests for Blogs(multisite) connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_Blogs_Test extends WP_StreamTestCase {

	/**
	 * Holds the connector blogs base class.
	 *
	 * @var Connector_Blogs
	 */
	protected $connector_blogs;

	/**
	 * Run before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test requires multisite.' );
		}

		$this->plugin->connectors->unload_connector( 'blogs' );

		// Add hook to provide mock blog details because sub-sites aren't
		// created with an options table to use.
		add_filter( 'site_details', array( $this, 'get_testsite_details' ) );

		// Make partial of Connector_Blogs class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Blogs::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

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
		$id     = self::factory()->blog->create( array( 'title' => 'ובזכויותיהם' ) );
		$labels = $this->mock->get_context_labels();
		$this->assertArrayHasKey( 'blog-1', $labels );
		$this->assertArrayHasKey( 'blog-' . $id, $labels );
	}

	public function test_callback_wp_initialize_site() {
		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%s" site was created',
						'1. Site name',
						'stream'
					)
				),
				$this->callback( array( self::class, 'assert_created_site_context' ) ),
				$this->greaterThan( 0 ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'created' )
			);

		// Create new blog to trigger callback.
		self::factory()->blog->create( array( 'title' => 'testsite' ) );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_wp_initialize_site' ) );
	}

	/**
	 * Assert created-site log context values (id / siteurl assigned at create time).
	 *
	 * @param mixed $context Log context argument.
	 * @return bool
	 */
	public static function assert_created_site_context( $context ): bool {
		return is_array( $context )
			&& isset( $context['site_name'], $context['siteurl'], $context['id'] )
			&& 'testsite' === $context['site_name']
			&& is_string( $context['siteurl'] )
			&& '' !== $context['siteurl']
			&& is_numeric( $context['id'] )
			&& (int) $context['id'] > 0
			&& 3 === count( $context );
	}

	public function test_callback_wp_delete_site() {
		global $wpdb;

		// Create site for later use.
		$blog_id = self::factory()->blog->create( array( 'title' => 'testsite' ) );

		// Temporary tables will trigger DB errors when we attempt to reference them as new temporary tables.
		$suppress = $wpdb->suppress_errors();

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%s" site was deleted',
						'1. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'deleted' )
			);

		// Delete blog to trigger callback.
		// Fix Mercator actions.
		remove_all_actions( 'delete_blog' );
		add_action( 'wp_delete_blog', '\Mercator\clear_mappings_on_delete' );

		wpmu_delete_blog( $blog_id, true );
		$wpdb->suppress_errors( $suppress );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_wp_delete_site' ) );
	}

	public function test_callback_wpmu_activate_blog() {
		// Create site for later use.
		$blog_id = self::factory()->blog->create( array( 'title' => 'testsite' ) );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%s" site was registered',
						'1. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'created' ),
				1
			);

		// Activate blog to trigger callback.
		do_action( 'wpmu_activate_blog', $blog_id, 1, 'password', 'testsite', array() );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_wpmu_activate_blog' ) );
	}

	public function test_callback_add_user_to_blog() {
		// Create site and user for later use.
		$blog_id = self::factory()->blog->create( array( 'title' => 'testsite' ) );
		$user_id = self::factory()->user->create( array( 'display_name' => 'testuser' ) );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'%1$s was added to the "%2$s" site with %3$s capabilities',
						'1. User\'s name, 2. Site name, 3. Role',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'user_name' => 'testuser',
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'role_name' => 'subscriber',
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		// Add user to site to trigger callback.
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_add_user_to_blog' ) );
	}

	public function test_callback_remove_user_from_blog() {
		// Create site and add user to site for later use.
		$blog_id = self::factory()->blog->create( array( 'title' => 'testsite' ) );
		$user_id = self::factory()->user->create( array( 'display_name' => 'testuser' ) );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'%1$s was removed from the "%2$s" site',
						'1. User\'s name, 2. Site name',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'user_name' => 'testuser',
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		// Remove user from site to trigger callback.
		remove_user_from_blog( $user_id, $blog_id );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_remove_user_from_blog' ) );
	}

	public function test_callback_update_blog_status_spam() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as spam', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'spam' => '1' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_make_spam_blog' ) );
	}

	public function test_callback_update_blog_status_ham() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		wp_update_site( $blog_id, array( 'spam' => '1' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as not spam', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'spam' => '0' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_make_ham_blog' ) );
	}

	public function test_callback_update_blog_status_mature() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as mature', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'mature' => '1' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_mature_blog' ) );
	}

	public function test_callback_update_blog_status_unmature() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		wp_update_site( $blog_id, array( 'mature' => '1' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as not mature', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'mature' => '0' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_unmature_blog' ) );
	}

	public function test_callback_update_blog_status_archived() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'archived', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'archive_blog' )
			);

		wp_update_site( $blog_id, array( 'archived' => '1' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_archive_blog' ) );
	}

	public function test_callback_update_blog_status_unarchived() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		wp_update_site( $blog_id, array( 'archived' => '1' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'restored from archive', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'archived' => '0' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_unarchive_blog' ) );
	}

	public function test_callback_update_blog_status_trashed() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'trashed', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'trashed' )
			);

		wp_update_site( $blog_id, array( 'deleted' => '1' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_make_delete_blog' ) );
	}

	public function test_callback_update_blog_status_restored() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		wp_update_site( $blog_id, array( 'deleted' => '1' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'restored', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'restored' )
			);

		wp_update_site( $blog_id, array( 'deleted' => '0' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_make_undelete_blog' ) );
	}

	public function test_callback_update_blog_status_public() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as public', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'public' => '1' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_blog_public' ) );
	}

	public function test_callback_update_blog_status_private() {
		$blog_id = self::factory()->blog->create(
			array(
				'title' => 'testsite',
				'meta'  => array( 'public' => '0' ),
			)
		);

		wp_update_site( $blog_id, array( 'public' => '1' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo(
					_x(
						'"%1$s" site was %2$s',
						'1. Site name, 2. Status',
						'stream'
					)
				),
				$this->equalTo(
					array(
						'site_name' => 'testsite',
						'siteurl'   => '//testsite',
						'id'        => $blog_id,
						'status'    => esc_html__( 'marked as private', 'stream' ),
					)
				),
				$this->equalTo( $blog_id ),
				$this->equalTo( 'testsite' ),
				$this->equalTo( 'updated' )
			);

		wp_update_site( $blog_id, array( 'public' => '0' ) );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_update_blog_public' ) );
	}
}

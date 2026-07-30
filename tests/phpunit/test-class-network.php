<?php
/**
 * Tests for the Network class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Test_Network
 *
 * Focused on the multisite authorization boundary: which blog's records a
 * caller is allowed to read, and what may be treated as network-admin context.
 * The Stream tables are shared across the whole network, so these checks are
 * the only thing keeping one site's activity log out of another site's reach.
 */
class Test_Network extends WP_StreamTestCase {

	/**
	 * Network instance under test.
	 *
	 * @var Network
	 */
	protected $network;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$this->network = new Network( $this->plugin );
	}

	/**
	 * A site administrator holds manage_options but has no network authority,
	 * so a caller-supplied blog_id must be discarded in favour of the current
	 * blog. A numeric type check is not an authorization check: honouring an
	 * arbitrary ?blog_id= would expose another site's records from the shared
	 * Stream table.
	 *
	 * @group ms-required
	 */
	public function test_site_admin_cannot_choose_blog_id() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$other_blog_id = (int) get_current_blog_id() + 100;

		$args = $this->network->network_query_args(
			array(
				'site_id' => null,
				'blog_id' => $other_blog_id,
			)
		);

		$this->assertSame(
			get_current_blog_id(),
			$args['blog_id'],
			'A site administrator must be pinned to the current blog.'
		);
		$this->assertNotSame(
			$other_blog_id,
			$args['blog_id'],
			'The requested blog_id must not survive for a non-network user.'
		);
	}

	/**
	 * The same restriction applies to a Stream viewer with no settings
	 * capability at all.
	 *
	 * @group ms-required
	 */
	public function test_subscriber_cannot_choose_blog_id() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$args = $this->network->network_query_args(
			array(
				'site_id' => null,
				'blog_id' => (int) get_current_blog_id() + 100,
			)
		);

		$this->assertSame( get_current_blog_id(), $args['blog_id'] );
	}

	/**
	 * A super admin legitimately administers the whole network, so an explicit
	 * blog_id must still be honoured for them -- the fix must not break
	 * cross-site filtering in the Network Admin.
	 *
	 * @group ms-required
	 */
	public function test_super_admin_may_choose_blog_id() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		$other_blog_id = (int) get_current_blog_id() + 100;

		try {
			$args = $this->network->network_query_args(
				array(
					'site_id' => null,
					'blog_id' => $other_blog_id,
				)
			);

			$this->assertSame(
				$other_blog_id,
				$args['blog_id'],
				'A super admin must retain cross-site record filtering.'
			);
		} finally {
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * The site_id argument keeps its existing default behaviour for every caller.
	 *
	 * @group ms-required
	 */
	public function test_site_id_defaults_to_current_network() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$args = $this->network->network_query_args(
			array(
				'site_id' => null,
				'blog_id' => null,
			)
		);

		$this->assertSame( (int) get_current_site()->id, (int) $args['site_id'] );
	}

	/**
	 * HTTP_REFERER is caller-controlled, so it can only ever be a hint about
	 * where a request originated -- never a grant of authority. Without a
	 * network capability a spoofed Referer must not be treated as network
	 * admin, otherwise a site-level viewer could lift the per-blog query
	 * restriction and have their actions logged against blog_id 0.
	 *
	 * @group ms-required
	 */
	public function test_spoofed_referer_does_not_grant_network_context() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse(
			$this->network->can_view_network_records(),
			'A site administrator must not be treated as a network-wide reader.'
		);
	}

	/**
	 * Counterpart to the above: a genuine super admin is recognised.
	 *
	 * @group ms-required
	 */
	public function test_super_admin_may_view_network_records() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		try {
			$this->assertTrue( $this->network->can_view_network_records() );
		} finally {
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * The blog_id_logged() filter zeroes the recorded blog when the request is
	 * in network admin context. Since that context can no longer be set from a
	 * Referer alone, a site user's actions stay attributed to their own blog.
	 *
	 * @group ms-required
	 */
	public function test_blog_id_logged_keeps_site_attribution_for_site_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$blog_id = get_current_blog_id();

		$this->assertSame(
			$blog_id,
			$this->network->blog_id_logged( $blog_id ),
			'A site user must not have their activity recorded as network activity.'
		);
	}
}

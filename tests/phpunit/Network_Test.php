<?php
/**
 * Tests for the Network class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Network_Test
 *
 * Focused on the multisite authorization boundary: which blog's records a
 * caller is allowed to read, and what may be treated as network-admin context.
 * The Stream tables are shared across the whole network, so these checks are
 * the only thing keeping one site's activity log out of another site's reach.
 */
class Network_Test extends WP_StreamTestCase {

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
	 * End-to-end form of the same rule: an ajax request that carries a
	 * network-admin Referer, from a user with no network capability, must not
	 * become network-admin context.
	 *
	 * WP_NETWORK_ADMIN is a constant, thus a true result would stay for all
	 * later tests in the process. The assertion is that the method refuses,
	 * which keeps the constant undefined.
	 *
	 * @group ms-required
	 */
	public function test_spoofed_referer_does_not_set_network_admin_over_ajax() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$original_referer        = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
		$_SERVER['HTTP_REFERER'] = network_admin_url(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			$this->assertFalse(
				$this->network->ajax_network_admin(),
				'A spoofed Referer must not grant network-admin context.'
			);
			$this->assertFalse(
				defined( 'WP_NETWORK_ADMIN' ),
				'WP_NETWORK_ADMIN must stay undefined for a site-level user.'
			);
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );

			if ( null === $original_referer ) {
				unset( $_SERVER['HTTP_REFERER'] );
			} else {
				$_SERVER['HTTP_REFERER'] = $original_referer; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			}
		}
	}

	/**
	 * Counterpart: a super admin on the same ajax request does get network
	 * context. The check runs through can_view_network_records() to avoid the
	 * WP_NETWORK_ADMIN constant, which would stay for all later tests.
	 *
	 * @group ms-required
	 */
	public function test_super_admin_gets_network_context_over_ajax() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		$original_referer        = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
		$_SERVER['HTTP_REFERER'] = network_admin_url(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		add_filter( 'wp_doing_ajax', '__return_true' );

		try {
			$this->assertTrue(
				0 === stripos( $_SERVER['HTTP_REFERER'], network_admin_url() ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				&& $this->network->can_view_network_records(),
				'A super admin must keep network-admin context over ajax.'
			);
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			revoke_super_admin( $user_id );

			if ( null === $original_referer ) {
				unset( $_SERVER['HTTP_REFERER'] );
			} else {
				$_SERVER['HTTP_REFERER'] = $original_referer; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			}
		}
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

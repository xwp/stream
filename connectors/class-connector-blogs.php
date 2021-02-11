<?php
/**
 * Connector for Blog actions.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Blogs
 */
class Connector_Blogs extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'blogs';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'wp_initialize_site',
		'wp_delete_site',
		'wpmu_activate_blog',
		'wpmu_new_user',
		'add_user_to_blog',
		'remove_user_from_blog',
		'make_spam_blog',
		'make_ham_blog',
		'mature_blog',
		'unmature_blog',
		'archive_blog',
		'unarchive_blog',
		'make_delete_blog',
		'make_undelete_blog',
		'update_blog_public',
	);

	/**
	 * Register connector in the WP Frontend
	 *
	 * @var bool
	 */
	public $register_frontend = false;

	/**
	 * Return translated connector label
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'Sites' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return array(
			'archive_blog' => esc_html__( 'Archived', 'stream' ),
			'created'      => esc_html__( 'Created', 'stream' ),
			'deleted'      => esc_html__( 'Deleted', 'stream' ),
			'updated'      => esc_html__( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array
	 */
	public function get_context_labels() {
		$labels = array();

		if ( is_multisite() && ! wp_is_large_network() ) {
			$blogs = wp_stream_get_sites();

			foreach ( $blogs as $blog ) {
				$blog_details   = get_blog_details( $blog->blog_id );
				$key            = sprintf( 'blog-%d', $blog->blog_id );
				$labels[ $key ] = $blog_details->blogname;
			}
		}

		return $labels;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links   Previous links registered.
	 * @param  object $record  Stream record.
	 *
	 * @return array
	 */
	public function action_links( $links, $record ) {
		$links [ esc_html__( 'Site Admin' ) ] = get_admin_url( $record->object_id );

		if ( $record->object_id ) {
			$site_admin_link = get_admin_url( $record->object_id );

			if ( $site_admin_link ) {
				$links [ esc_html__( 'Site Admin' ) ] = $site_admin_link;
			}

			$site_settings_link = add_query_arg(
				array(
					'id' => $record->object_id,
				),
				network_admin_url( 'site-info.php' )
			);

			if ( $site_settings_link ) {
				$links [ esc_html__( 'Site Settings', 'stream' ) ] = $site_settings_link;
			}
		}

		return $links;
	}

	/**
	 * Blog created.
	 *
	 * @action wp_initialize_site
	 *
	 * @param WP_Site $new_site  New site object.
	 * @param array   $args      Arguments for the initialization.
	 */
	public function callback_wp_initialize_site( $new_site, $args ) {
		$blogname = ! empty( $args['title'] ) ? $args['title'] : $new_site->blogname;
		$blog_id  = $new_site->blog_id;

		$this->log(
			/* translators: %s: site name (e.g. "FooBar Blog") */
			_x(
				'"%s" site was created',
				'1. Site name',
				'stream'
			),
			array(
				'site_name' => ! empty( $blogname ) ? $blogname : 'Site %d',
				'siteurl'   => $new_site->siteurl,
				'id'        => $new_site->blog_id,
			),
			$blog_id,
			sanitize_key( $blogname ),
			'created'
		);
	}

	/**
	 * A site has been deleted from the database.
	 *
	 * @action wp_delete_site
	 *
	 * @param WP_Site $old_site  Deleted site object.
	 */
	public function callback_wp_delete_site( $old_site ) {
		$this->log(
			/* translators: %s: site name (e.g. "FooBar Blog") */
			_x(
				'"%s" site was deleted',
				'1. Site name',
				'stream'
			),
			array(
				'site_name' => $old_site->blogname,
				'siteurl'   => $old_site->siteurl,
				'id'        => $old_site->blog_id,
			),
			$old_site->blog_id,
			sanitize_key( $old_site->blogname ),
			'deleted'
		);
	}

	/**
	 * Blog registered
	 *
	 * @action wpmu_activate_blog
	 *
	 * @param int $blog_id  Blog ID.
	 * @param int $user_id  User ID.
	 */
	public function callback_wpmu_activate_blog( $blog_id, $user_id ) {
		$blog = get_site( $blog_id );

		$this->log(
			/* translators: %s: site name (e.g. "FooBar Blog") */
			_x(
				'"%s" site was registered',
				'1. Site name',
				'stream'
			),
			array(
				'site_name' => $blog->blogname,
				'siteurl'   => $blog->siteurl,
				'id'        => $blog->blog_id,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'created',
			$user_id
		);
	}

	/**
	 * User added to a blog
	 *
	 * @action add_user_to_blog
	 *
	 * @param int    $user_id  User ID.
	 * @param string $role     User role.
	 * @param int    $blog_id  Blog ID.
	 */
	public function callback_add_user_to_blog( $user_id, $role, $blog_id ) {
		$blog = get_site( $blog_id );
		$user = get_user_by( 'id', $user_id );

		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$this->log(
			/* translators: %1$s: a user's display name, %2$s: a site name, %3$s: a user role (e.g. "Jane Doe", "FooBar Blog", "subscriber") */
			_x(
				'%1$s was added to the "%2$s" site with %3$s capabilities',
				'1. User\'s name, 2. Site name, 3. Role',
				'stream'
			),
			array(
				'user_name' => $user->display_name,
				'site_name' => $blog->blogname,
				'siteurl'   => $blog->siteurl,
				'id'        => $blog->blog_id,
				'role_name' => $role,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'updated'
		);
	}

	/**
	 * User removed from a blog
	 *
	 * @action remove_user_from_blog
	 *
	 * @param int $user_id  User ID.
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_remove_user_from_blog( $user_id, $blog_id ) {
		$blog = get_site( $blog_id );
		$user = get_user_by( 'id', $user_id );

		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$this->log(
			/* translators: %1$s: a user's display name, %2$s: a site name (e.g. "Jane Doe", "FooBar Blog") */
			_x(
				'%1$s was removed from the "%2$s" site',
				'1. User\'s name, 2. Site name',
				'stream'
			),
			array(
				'user_name' => $user->display_name,
				'site_name' => $blog->blogname,
				'siteurl'   => $blog->siteurl,
				'id'        => $blog->blog_id,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'updated'
		);
	}

	/**
	 * Blog marked as spam
	 *
	 * @action make_spam_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_make_spam_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as spam', 'stream' ), 'updated' );
	}

	/**
	 * Blog not marked as spam
	 *
	 * @action make_ham_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_make_ham_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as not spam', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as mature
	 *
	 * @action mature_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_mature_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as mature', 'stream' ), 'updated' );
	}

	/**
	 * Blog not marked as mature
	 *
	 * @action unmature_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_unmature_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as not mature', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as archived
	 *
	 * @action archive_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_archive_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'archived', 'stream' ), 'archive_blog' );
	}

	/**
	 * Blog not marked as archived
	 *
	 * @action unarchive_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_unarchive_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'restored from archive', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as deleted
	 *
	 * @action make_delete_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_make_delete_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'trashed', 'stream' ), 'trashed' );
	}

	/**
	 * Blog not marked as deleted
	 *
	 * @action undelete_blog
	 *
	 * @param int $blog_id  Blog ID.
	 */
	public function callback_make_undelete_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'restored', 'stream' ), 'restored' );
	}

	/**
	 * Blog marked as public or private
	 *
	 * @action update_blog_public
	 *
	 * @param int    $blog_id  Blog ID.
	 * @param string $value    Status flag.
	 */
	public function callback_update_blog_public( $blog_id, $value ) {
		if ( absint( $value ) ) {
			$status = esc_html__( 'marked as public', 'stream' );
		} else {
			$status = esc_html__( 'marked as private', 'stream' );
		}

		$this->callback_update_blog_status( $blog_id, $status, 'updated' );
	}

	/**
	 * Blog updated
	 *
	 * @action update_blog_status
	 *
	 * @param int    $blog_id  Blog ID.
	 * @param string $status   Blog Status.
	 * @param string $action   Action.
	 */
	public function callback_update_blog_status( $blog_id, $status, $action ) {
		$blog = get_site( $blog_id );
		$this->log(
			/* translators: %1$s: a site name, %2$s: a blog status (e.g. "FooBar Blog", "archived") */
			_x(
				'"%1$s" site was %2$s',
				'1. Site name, 2. Status',
				'stream'
			),
			array(
				'site_name' => $blog->blogname,
				'siteurl'   => $blog->siteurl,
				'id'        => $blog->blog_id,
				'status'    => $status,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			$action
		);
	}
}

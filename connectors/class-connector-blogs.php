<?php
namespace WP_Stream;

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
		'wpmu_new_blog',
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
			$blogs = wp_get_sites();

			foreach ( $blogs as $blog ) {
				$blog_details   = get_blog_details( $blog['blog_id'] );
				$key            = sanitize_key( $blog_details->blogname );
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
	 * @param array $links
	 * @param Record $record
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
	 * Blog created
	 *
	 * @action wpmu_new_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_wpmu_new_blog( $blog_id ) {
		$blog = get_blog_details( $blog_id );

		$this->log(
			_x(
				'"%1$s" site was created',
				'1. Site name',
				'stream'
			),
			array(
				'site_name' => $blog->blogname,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'created'
		);
	}

	/**
	 * Blog registered
	 *
	 * @action wpmu_activate_blog
	 *
	 * @param int $blog_id
	 * @param int $user_id
	 */
	public function callback_wpmu_activate_blog( $blog_id, $user_id ) {
		$blog = get_blog_details( $blog_id );

		$this->log(
			_x(
				'"%1$s" site was registered',
				'1. Site name',
				'stream'
			),
			array(
				'site_name' => $blog->blogname,
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
	 * @param int    $user_id
	 * @param string $role
	 * @param int    $blog_id
	 */
	public function callback_add_user_to_blog( $user_id, $role, $blog_id ) {
		$blog = get_blog_details( $blog_id );
		$user = get_user_by( 'id', $user_id );

		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$this->log(
			_x(
				'%1$s was added to the "%2$s" site with %3$s capabilities',
				'1. User\'s name, 2. Site name, 3. Role',
				'stream'
			),
			array(
				'user_name' => $user->display_name,
				'site_name' => $blog->blogname,
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
	 * @param int $user_id
	 * @param int $blog_id
	 */
	public function callback_remove_user_from_blog( $user_id, $blog_id ) {
		$blog = get_blog_details( $blog_id );
		$user = get_user_by( 'id', $user_id );

		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$this->log(
			_x(
				'%1$s was removed from the "%2$s" site',
				'1. User\'s name, 2. Site name',
				'stream'
			),
			array(
				'user_name' => $user->display_name,
				'site_name' => $blog->blogname,
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
	 * @param int $blog_id
	 */
	public function callback_make_spam_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as spam', 'stream' ), 'updated' );
	}

	/**
	 * Blog not marked as spam
	 *
	 * @action make_ham_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_make_ham_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as not spam', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as mature
	 *
	 * @action mature_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_mature_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as mature', 'stream' ), 'updated' );
	}

	/**
	 * Blog not marked as mature
	 *
	 * @action unmature_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_unmature_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'marked as not mature', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as archived
	 *
	 * @action archive_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_archive_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'archived', 'stream' ), 'archive_blog' );
	}

	/**
	 * Blog not marked as archived
	 *
	 * @action unarchive_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_unarchive_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'restored from archive', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as deleted
	 *
	 * @action make_delete_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_make_delete_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'deleted', 'stream' ), 'deleted' );
	}

	/**
	 * Blog not marked as deleted
	 *
	 * @action undelete_blog
	 *
	 * @param int $blog_id
	 */
	public function callback_make_undelete_blog( $blog_id ) {
		$this->callback_update_blog_status( $blog_id, esc_html__( 'restored', 'stream' ), 'updated' );
	}

	/**
	 * Blog marked as public or private
	 *
	 * @action update_blog_public
	 *
	 * @param int    $blog_id
	 * @param string $value
	 */
	public function callback_update_blog_public( $blog_id, $value ) {
		if ( $value ) {
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
	 * @param int    $blog_id
	 * @param string $status
	 * @param string $action
	 */
	public function callback_update_blog_status( $blog_id, $status, $action ) {
		$blog = get_blog_details( $blog_id );

		$this->log(
			_x(
				'"%1$s" site was %2$s',
				'1. Site name, 2. Status',
				'stream'
			),
			array(
				'site_name' => $blog->blogname,
				'status'    => $status,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			$action
		);
	}
}

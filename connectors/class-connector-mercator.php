<?php
/**
 * Connector for Mercator
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Mercator
 */
class Connector_Mercator extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'mercator';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'mercator.mapping.updated',
		'mercator.mapping.deleted',
		'mercator.mapping.created',
		'mercator.mapping.made_primary',
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
		return esc_html__( 'Mercator' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array
	 */
	public function get_action_labels() {
		return array(
			'made_primary' => esc_html__( 'Make primary domain', 'stream' ),
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
				$blog_details   = get_site( $blog->blog_id );
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
					'id'     => $record->object_id,
					'action' => 'mercator-aliases',
				),
				network_admin_url( 'admin.php' )
			);

			if ( $site_settings_link ) {
				$links [ esc_html__( 'Domain mapping Settings', 'stream' ) ] = $site_settings_link;
			}
		}

		return $links;
	}

	/**
	 * Log if domain is made primary.
	 *
	 * @param object $mapping  Mapping object.
	 */
	public function callback_mercator_mapping_made_primary( $mapping ) {
		$blog_id = $mapping->get_site_id();
		$blog    = get_site( $blog_id );

		$this->log(
			/* translators: %1$s: domain alias, %2$s: site name (e.g. "FooBar Blog") */
			_x(
				'"%1$s" domain alias was make primary for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			array(
				'domain'    => $mapping->get_domain(),
				'site_name' => $blog->blogname,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'made_primary'
		);
	}

	/**
	 * Log if domain alias is updated.
	 *
	 * @param object $mapping      Mapping object.
	 * @param object $old_mapping  Old mapping object from before update.
	 */
	public function callback_mercator_mapping_updated( $mapping, $old_mapping ) {

		$blog_id = $mapping->get_site_id();
		$blog    = get_site( $blog_id );

		$this->log(
			/* translators: %1$s: domain alias, %2$s: site name (e.g. "FooBar Blog") */
			_x(
				'The domain alias "%1$s" was updated to "%2$s" for site "%3$s"',
				'1. Old Domain alias 2. Domain alias 2. Site name',
				'stream'
			),
			array(
				'old_domain' => $old_mapping->get_domain(),
				'domain'     => $mapping->get_domain(),
				'site_name'  => $blog->blogname,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'updated'
		);

	}

	/**
	 * Log if domain alias is deleted.
	 *
	 * @param object $mapping  Mapping of deleted alias.
	 */
	public function callback_mercator_mapping_deleted( $mapping ) {

		$blog_id = $mapping->get_site_id();
		$blog    = get_site( $blog_id );

		$this->log(
			/* translators: %1$s: domain alias, %2$s: site name (e.g. "FooBar Blog") */
			_x(
				'"%1$s" domain alias was deleted for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			array(
				'domain'    => $mapping->get_domain(),
				'site_name' => $blog->blogname,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'deleted'
		);

	}

	/**
	 * Log if domain alias is created.
	 *
	 * @param object $mapping  Mapping object.
	 */
	public function callback_mercator_mapping_created( $mapping ) {
		$blog_id = $mapping->get_site_id();
		$blog    = get_site( $blog_id );

		$this->log(
			/* translators: %1$s: domain alias, %2$s: site name (e.g. "FooBar Blog") */
			_x(
				'"%1$s" domain alias was created for "%2$s"',
				'1. Domain alias 2. Site name',
				'stream'
			),
			array(
				'domain'    => $mapping->get_domain(),
				'site_name' => $blog->blogname,
			),
			$blog_id,
			sanitize_key( $blog->blogname ),
			'created'
		);
	}
}

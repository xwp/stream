<?php

class WP_Stream_Connector_bbPress extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'bbpress';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '2.5.4';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'bbp_toggle_topic_admin',
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public static $options = array(
		'bbpress' => null,
	);

	/**
	 * Flag to stop logging update logic twice
	 *
	 * @var bool
	 */
	public static $is_update = false;

	/**
	 * @var bool
	 */
	public static $_deleted_activity = false;

	/**
	 * @var array
	 */
	public static $_delete_activity_args = array();

	/**
	 * @var bool
	 */
	public static $ignore_activity_bulk_deletion = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( class_exists( 'bbPress' ) && function_exists( 'bbp_get_version' ) && version_compare( bbp_get_version(), self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return _x( 'bbPress', 'bbpress', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'     => _x( 'Created', 'bbpress', 'stream' ),
			'updated'     => _x( 'Updated', 'bbpress', 'stream' ),
			'activated'   => _x( 'Activated', 'bbpress', 'stream' ),
			'deactivated' => _x( 'Deactivated', 'bbpress', 'stream' ),
			'deleted'     => _x( 'Deleted', 'bbpress', 'stream' ),
			'trashed'     => _x( 'Trashed', 'bbpress', 'stream' ),
			'untrashed'   => _x( 'Restored', 'bbpress', 'stream' ),
			'generated'   => _x( 'Generated', 'bbpress', 'stream' ),
			'imported'    => _x( 'Imported', 'bbpress', 'stream' ),
			'exported'    => _x( 'Exported', 'bbpress', 'stream' ),
			'closed'      => _x( 'Closed', 'bbpress', 'stream' ),
			'opened'      => _x( 'Opened', 'bbpress', 'stream' ),
			'sticked'     => _x( 'Sticked', 'bbpress', 'stream' ),
			'unsticked'   => _x( 'Unsticked', 'bbpress', 'stream' ),
			'spammed'     => _x( 'Marked as spam', 'bbpress', 'stream' ),
			'unspammed'   => _x( 'Unmarked as spam', 'bbpress', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'settings' => _x( 'Settings', 'bbpress', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array $links      Previous links registered
	 * @param  object $record    Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( 'settings' === $record->context ) {
			$option = wp_stream_get_meta( $record, 'option', true );
			$links[ __( 'Edit', 'stream' ) ] = esc_url( add_query_arg(
				array(
					'page' => 'bbpress',
				),
				admin_url( 'options-general.php' )
			) . esc_url_raw( '#' . $option ) );
		}
		return $links;
	}

	public static function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'settings' === $data['connector'] && 'bbpress' === $data['args']['context'] ) {
			$settings = bbp_admin_get_settings_fields();

			/* fix for missing title for this single field */
			$settings['bbp_settings_features']['_bbp_allow_threaded_replies']['title'] = __( 'Reply Threading', 'stream' );

			$option   = $data['args']['option'];
			foreach ( $settings as $section => $fields ) {
				if ( isset( $fields[ $option ] ) ) {
					$field = $fields[ $option ];
					break;
				}
			}

			if ( ! isset( $field ) ) {
				return $data;
			}

			$data['args']['label'] = $field['title'];
			$data['connector']     = self::$name;
			$data['context']       = 'settings';
			$data['action']        = 'updated';
		}
		elseif ( 'posts' === $data['connector'] && in_array( $data['context'], array( 'forum', 'topic', 'reply' ) ) ) {
			if ( 'reply' === $data['context'] ) {
				if ( 'updated' === $data['action'] ) {
					$data['message'] = __( 'Replied on "%1$s"', 'stream' );
					$data['args']['post_title'] = get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title;
				}
				$data['args']['post_title'] = sprintf(
					__( 'Reply to: %s', 'stream' ),
					get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title
				);
			}

			$data['connector'] = self::$name;
		}
		elseif ( 'taxonomies' === $data['connector'] && in_array( $data['context'], array( 'topic-tag' ) ) ) {
			$data['connector'] = self::$name;
		}

		return $data;
	}

	public static function callback_bbp_toggle_topic_admin( $success, $post_data, $action, $message ) {

		if ( ! empty( $message['failed'] ) ) {
			return;
		}

		$action  = $message['bbp_topic_toggle_notice'];
		$actions = self::get_action_labels();

		if ( ! isset( $actions[ $action ] ) ) {
			return;
		}

		$label = $actions[ $action ];
		$topic = get_post( $message['topic_id'] );

		self::log(
			_x( '%1$s "%2$s" topic', '1: Action, 2: Topic title', 'stream' ),
			array(
				'action_title' => $actions[ $action ],
				'topic_title' => $topic->post_title,
				'action' => $action,
			),
			$topic->ID,
			'topic',
			$action
		);
	}

}

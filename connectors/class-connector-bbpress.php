<?php
/**
 * Connector for bbPress
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_BbPress
 */
class Connector_BbPress extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'bbpress';

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
	public $actions = array(
		'bbp_toggle_topic_admin',
	);

	/**
	 * Tracked option keys
	 *
	 * @var array
	 */
	public $options = array(
		'bbpress' => null,
	);

	/**
	 * Flag to stop logging update logic twice
	 *
	 * @var bool
	 */
	public $is_update = false;

	/**
	 * Stores an activity to be deleted for use across multiple callbacks.
	 *
	 * @var bool
	 */
	public $deleted_activity = false;

	/**
	 * Stores post data of an activity to be deleted for use across multiple callbacks.
	 *
	 * @var array
	 */
	public $delete_activity_args = array();

	/**
	 * Flag for ignoring irrelevant activity deletions.
	 *
	 * @var bool
	 */
	public $ignore_activity_bulk_deletion = false;

	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
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
	public function get_label() {
		return esc_html_x( 'bbPress', 'bbpress', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created'     => esc_html_x( 'Created', 'bbpress', 'stream' ),
			'updated'     => esc_html_x( 'Updated', 'bbpress', 'stream' ),
			'activated'   => esc_html_x( 'Activated', 'bbpress', 'stream' ),
			'deactivated' => esc_html_x( 'Deactivated', 'bbpress', 'stream' ),
			'deleted'     => esc_html_x( 'Deleted', 'bbpress', 'stream' ),
			'trashed'     => esc_html_x( 'Trashed', 'bbpress', 'stream' ),
			'untrashed'   => esc_html_x( 'Restored', 'bbpress', 'stream' ),
			'generated'   => esc_html_x( 'Generated', 'bbpress', 'stream' ),
			'imported'    => esc_html_x( 'Imported', 'bbpress', 'stream' ),
			'exported'    => esc_html_x( 'Exported', 'bbpress', 'stream' ),
			'closed'      => esc_html_x( 'Closed', 'bbpress', 'stream' ),
			'opened'      => esc_html_x( 'Opened', 'bbpress', 'stream' ),
			'sticked'     => esc_html_x( 'Sticked', 'bbpress', 'stream' ),
			'unsticked'   => esc_html_x( 'Unsticked', 'bbpress', 'stream' ),
			'spammed'     => esc_html_x( 'Marked as spam', 'bbpress', 'stream' ),
			'unspammed'   => esc_html_x( 'Unmarked as spam', 'bbpress', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'settings' => esc_html_x( 'Settings', 'bbpress', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links   Previous links registered.
	 * @param  object $record  Stream record.
	 *
	 * @return array             Action links
	 */
	public function action_links( $links, $record ) {
		if ( 'settings' === $record->context ) {
			$option                                  = $record->get_meta( 'option', true );
			$links[ esc_html__( 'Edit', 'stream' ) ] = esc_url(
				add_query_arg(
					array(
						'page' => 'bbpress',
					),
					admin_url( 'options-general.php' )
				) . esc_url_raw( '#' . $option )
			);
		}
		return $links;
	}

	/**
	 * Register the connector
	 */
	public function register() {
		parent::register();

		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data  Record data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'settings' === $data['connector'] && 'bbpress' === $data['args']['context'] ) {
			$settings = \bbp_admin_get_settings_fields();

			/* fix for missing title for this single field */
			$settings['bbp_settings_features']['_bbp_allow_threaded_replies']['title'] = esc_html__( 'Reply Threading', 'stream' );

			$option = $data['args']['option'];

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
			$data['connector']     = $this->name;
			$data['context']       = 'settings';
			$data['action']        = 'updated';
		} elseif ( 'posts' === $data['connector'] && in_array( $data['context'], array( 'forum', 'topic', 'reply' ), true ) ) {
			if ( 'reply' === $data['context'] ) {
				if ( 'updated' === $data['action'] ) {
					/* translators: %s: a post title (e.g. "Hello World") */
					$data['message']            = esc_html__( 'Replied on "%s"', 'stream' );
					$data['args']['post_title'] = get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title;
				}
				$data['args']['post_title'] = sprintf(
					/* translators: %s a post title (e.g. "Hello World") */
					__( 'Reply to: %s', 'stream' ),
					get_post( wp_get_post_parent_id( $data['object_id'] ) )->post_title
				);
			}

			$data['connector'] = $this->name;
		} elseif ( 'taxonomies' === $data['connector'] && in_array( $data['context'], array( 'topic-tag' ), true ) ) {
			$data['connector'] = $this->name;
		}

		return $data;
	}

	/**
	 * Tracks togging the forum topics
	 *
	 * @param bool     $success    If action success.
	 * @param \WP_Post $post_data  Post data.
	 * @param string   $action     Record action.
	 * @param string   $message    Message status data.
	 *
	 * @return array|bool
	 */
	public function callback_bbp_toggle_topic_admin( $success, $post_data, $action, $message ) {
		unset( $success );
		unset( $post_data );
		unset( $action );

		if ( ! empty( $message['failed'] ) ) {
			return;
		}

		$action  = $message['bbp_topic_toggle_notice'];
		$actions = $this->get_action_labels();

		if ( ! isset( $actions[ $action ] ) ) {
			return;
		}

		$topic = get_post( $message['topic_id'] );

		$this->log(
			/* translators: %1$s: an action, %2$s: a topic title (e.g. "Created", "Read this first") */
			_x( '%1$s "%2$s" topic', '1: Action, 2: Topic title', 'stream' ),
			array(
				'action_title' => $actions[ $action ],
				'topic_title'  => $topic->post_title,
				'action'       => $action,
			),
			$topic->ID,
			'topic',
			$action
		);
	}
}

<?php
namespace WP_Stream;

class Alerts_List {
	/**
	 * Hold Plugin class
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'bulk_actions-edit-wp_stream_alerts', array( $this, 'supress_bulk_actions' ), 10, 1 );
		add_filter( 'disable_months_dropdown', array( $this, 'supress_months_dropdown' ), 10, 2 );

		add_filter( 'request', array( $this, 'parse_request' ), 10, 2 );
		add_filter( 'views_edit-wp_stream_alerts', array( $this, 'manage_views' ) );

		add_filter( 'manage_wp_stream_alerts_posts_columns', array( $this, 'manage_columns' ) );
		add_action( 'manage_wp_stream_alerts_posts_custom_column', array( $this, 'column_data' ), 10, 2 );

	}

	/**
	 * Default to wp_stream_enabled and wp_stream_disabled when querying for alerts
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array
	 */
	function parse_request( $query_vars ) {
		$screen = get_current_screen();
		if ( 'edit-wp_stream_alerts' === $screen->id && 'wp_stream_alerts' === $query_vars['post_type'] && empty( $query_vars['post_status'] ) ) {
			$query_vars['post_status'] = array( 'wp_stream_enabled', 'wp_stream_disabled' );
		}
		return $query_vars;
	}

	/**
	 *
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array
	 */
	function manage_views( $views ) {

		// Move trash to end of the list
		$trash = $views['trash'];
		unset( $views['trash'] );
		$views['trash'] = $trash;

		return $views;
	}

	/**
	 *
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array
	 */
	function manage_columns( $columns ) {
		$columns = array(
			'cb' => $columns['cb'],
			'alert_type' => __( 'Type', 'stream' ),
			'alert_trigger' => __( 'Trigger', 'stream' ),
			'alert_status' => __( 'Status', 'stream' ),
		);
		return $columns;
	}

	/**
	 *
	 *
	 * @param int   $record_id The record being processed.
	 * @param array $recordarr Record data.
	 * @return array
	 */
	function column_data( $column_name, $post_id ) {

		$alert = $this->plugin->alerts->get_alert( $post_id );
		if ( ! $alert ) {
			return;
		}

		switch ( $column_name ) {
			case 'alert_type' :
				$alert_type = $alert->alert_type;
				esc_html_e( $this->plugin->alerts->alert_types[ $alert_type ]->name );
				break;
			case 'alert_trigger' :
				$values = array();
				foreach ( $this->plugin->alerts->alert_triggers as $trigger_type => $trigger_obj ) {
					$value = $trigger_obj->get_display_value( 'list_table', $alert );
					$values[] = '<span class="alert_trigger_value alert_trigger_' . esc_attr( $trigger_type ) . '">' . esc_html( $value ) . '</span>';
				}

				echo join( '', $values ); // xss ok
				break;
			case 'alert_status' :
				$post_status = get_post_status( $post_id );
				$post_status_object = get_post_status_object( $post_status );
				if ( $post_status_object ) {
					esc_html_e( $post_status_object->label );
				}
				break;
		}
	}

	public function supress_bulk_actions( $actions ) {
		return array();
	}

	public function supress_months_dropdown( $status, $post_type ) {
		if ( 'wp_stream_alerts' === $post_type ) {
			$status = true;
		}
		return $status;
	}
}
